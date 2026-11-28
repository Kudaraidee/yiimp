// NEB work-service client, neb-pool/1 protocol and share verification.
//
// The C PoW engine in this directory is the verbatim reference implementation
// from the NEB node (Apache-2.0). This file connects it to yiimp:
//
//   node work service (TCP 3441) -> NEB_JOB -> YAAMP_JOB_TEMPLATE
//   miner mining.submit -> BLAKE3 header + reference ticket -> yiimp share
//   chain share -> node RPC submitshares, block -> work service Submit frame
//
// Wire details follow spec/pool.md and spec/node.md from the NEB repository.

#include "stratum.h"
#include "work.h"

extern "C" {
#include "algos/nebhash/blake3_shared.h"
#include "algos/nebhash/chacha8.h"
#include "algos/nebhash/shares.h"
#include "algos/nebhash/nebhash2.h"
}

#include <ctype.h>
#include <errno.h>
#include <limits.h>
#include <math.h>
#include <stdlib.h>
#include <strings.h>
#include <string.h>
#include <sys/socket.h>
#include <time.h>
#include <netinet/tcp.h>
#include <unistd.h>

#include <deque>
#include <limits>
#include <map>
#include <memory>
#include <set>
#include <string>
#include <vector>

using std::shared_ptr;
using std::string;
using std::vector;

// ---------------------------------------------------------------------------
// Small wire helpers
// ---------------------------------------------------------------------------

#define NEB_MSG_HELLO 1
#define NEB_MSG_JOB 2
#define NEB_MSG_SUBMIT 4
#define NEB_MSG_RESULT 5
#define NEB_MSG_SHARE 6
#define NEB_MSG_MODEL 7

// Reference neb-pool/1 limits (spec/pool.md section 9 and neb-pool/src/main.rs).
#define NEB_K_MAX 48
// Display unit shared with the web UI: one miner "difficulty" is 10^10
// expected hashes. d=0.1 therefore targets 10^9 expected hashes per share.
#define NEB_MINER_DIFF_SCALE 10000000000.0
#define NEB_VARDiff_WINDOW_MS 60000LL
#define NEB_RATE_HIGH 12
#define NEB_RATE_LOW 3
#define NEB_MAX_SUBMITS_PER_SEC 25
#define NEB_MAX_BAD_IN_A_ROW 30
#define NEB_PRIOR_TARGET_GRACE_MS 30000LL

// Vardiff windows and rate limits must not jump when the wall clock is stepped.
static long long neb_monotonic_ms()
{
	struct timespec ts;
	clock_gettime(CLOCK_MONOTONIC, &ts);
	return (long long) ts.tv_sec * 1000LL + ts.tv_nsec / 1000000LL;
}

static const char hexdigits[] = "0123456789abcdef";

static int hexval(unsigned char c)
{
	if (c >= '0' && c <= '9') return c - '0';
	if (c >= 'a' && c <= 'f') return c - 'a' + 10;
	if (c >= 'A' && c <= 'F') return c - 'A' + 10;
	return -1;
}

static bool parse_hex_bytes(const char *src, size_t expect, uint8_t *out)
{
	if (!src || strlen(src) != expect * 2) return false;
	for (size_t i = 0; i < expect; i++)
	{
		int hi = hexval((unsigned char) src[2*i]);
		int lo = hexval((unsigned char) src[2*i+1]);
		if (hi < 0 || lo < 0) return false;
		out[i] = (uint8_t) ((hi << 4) | lo);
	}
	return true;
}

static void bytes_to_hex(const uint8_t *in, size_t len, char *out)
{
	for (size_t i = 0; i < len; i++)
	{
		out[2*i] = hexdigits[in[i] >> 4];
		out[2*i+1] = hexdigits[in[i] & 15];
	}
	out[2*len] = 0;
}

static uint32_t read_le32(const uint8_t *p)
{
	return (uint32_t) p[0] | ((uint32_t) p[1] << 8) |
		((uint32_t) p[2] << 16) | ((uint32_t) p[3] << 24);
}

static uint64_t read_le64(const uint8_t *p)
{
	uint64_t v = 0;
	for (int i = 7; i >= 0; i--) v = (v << 8) | p[i];
	return v;
}

static void write_le32(uint8_t *p, uint32_t v)
{
	p[0] = (uint8_t) v;
	p[1] = (uint8_t) (v >> 8);
	p[2] = (uint8_t) (v >> 16);
	p[3] = (uint8_t) (v >> 24);
}

static void write_varint(vector<uint8_t> &out, uint64_t v)
{
	if (v < 0xfd) out.push_back((uint8_t) v);
	else if (v <= 0xffff)
	{
		out.push_back(0xfd);
		for (int i = 0; i < 2; i++) out.push_back((uint8_t) (v >> (8*i)));
	}
	else if (v <= 0xffffffffULL)
	{
		out.push_back(0xfe);
		for (int i = 0; i < 4; i++) out.push_back((uint8_t) (v >> (8*i)));
	}
	else
	{
		out.push_back(0xff);
		for (int i = 0; i < 8; i++) out.push_back((uint8_t) (v >> (8*i)));
	}
}

struct NEB_READER
{
	const uint8_t *p;
	size_t len;
	size_t pos;

	NEB_READER(const uint8_t *data, size_t size): p(data), len(size), pos(0) {}

	bool bytes(size_t n, const uint8_t **out)
	{
		if (n > len - pos) return false;
		*out = p + pos;
		pos += n;
		return true;
	}
	bool u8(uint8_t *v)
	{
		const uint8_t *q;
		if (!bytes(1, &q)) return false;
		*v = *q;
		return true;
	}
	bool u32(uint32_t *v)
	{
		const uint8_t *q;
		if (!bytes(4, &q)) return false;
		*v = read_le32(q);
		return true;
	}
	bool u64(uint64_t *v)
	{
		const uint8_t *q;
		if (!bytes(8, &q)) return false;
		*v = read_le64(q);
		return true;
	}
	bool varint(uint64_t *v)
	{
		uint8_t prefix;
		if (!u8(&prefix)) return false;
		if (prefix < 0xfd) { *v = prefix; return true; }
		if (prefix == 0xfd)
		{
			const uint8_t *q;
			if (!bytes(2, &q)) return false;
			*v = read_le32(q) & 0xffff;
			return true;
		}
		if (prefix == 0xfe)
		{
			const uint8_t *q;
			if (!bytes(4, &q)) return false;
			*v = read_le32(q);
			return true;
		}
		if (prefix == 0xff)
		{
			const uint8_t *q;
			if (!bytes(8, &q)) return false;
			*v = read_le64(q);
			return true;
		}
		return false;
	}
	bool varbytes(vector<uint8_t> *out)
	{
		uint64_t n;
		if (!varint(&n) || n > len - pos) return false;
		out->assign(p + pos, p + pos + (size_t) n);
		pos += (size_t) n;
		return true;
	}
	bool done() const { return pos == len; }
};

// H(tag, parts...) is BLAKE3 over the tag and the parts, with no separators.
static void hash_parts(const char *tag, size_t taglen,
	const uint8_t *a, size_t alen,
	const uint8_t *b, size_t blen,
	const uint8_t *c, size_t clen,
	uint8_t out[32])
{
	vector<uint8_t> buf;
	buf.reserve(taglen + alen + blen + clen);
	buf.insert(buf.end(), tag, tag + taglen);
	if (a && alen) buf.insert(buf.end(), a, a + alen);
	if (b && blen) buf.insert(buf.end(), b, b + blen);
	if (c && clen) buf.insert(buf.end(), c, c + clen);
	neb_blake3_hash(buf.data(), buf.size(), out);
}

// ---------------------------------------------------------------------------
// Dataset and model store
// ---------------------------------------------------------------------------

struct NEB_DATASET
{
	uint32_t n;
	uint32_t nin;
	uint32_t record_size;
	int arch;
	vector<uint8_t> records;
};

struct NEB_MODEL
{
	uint8_t hash[32];
	int slot;
	int arch;
	string packed_hex;
	shared_ptr<neb2_model> model;
};

struct NEB_JOB
{
	uint64_t node_id;
	bool clean;
	uint32_t version;
	uint8_t parent[32];
	uint32_t time_start;
	uint32_t time_window;
	uint32_t bits;
	uint8_t target[32];
	uint8_t share_target[32];
	vector<uint8_t> coinbase_prefix;
	vector<uint8_t> coinbase_suffix;
	uint8_t extranonce_size;
	vector<vector<uint8_t> > branch;
	uint32_t tx_count;
	uint8_t shares_root[32];
	uint32_t height;
	uint32_t expiry;
	uint8_t slot;
	uint8_t weights_hash[32];
	string weights_hex;
	shared_ptr<NEB_MODEL> model;
};

static pthread_mutex_t g_neb_lock = PTHREAD_MUTEX_INITIALIZER;
static NEB_DATASET g_neb_datasets[16];
static int g_neb_dataset_count = 0;
static uint8_t g_neb_dataset_hash[32];
static shared_ptr<NEB_MODEL> g_neb_slot_model[16];
static shared_ptr<NEB_MODEL> g_neb_last_model;
static std::map<string, shared_ptr<NEB_MODEL> > g_neb_models;
static shared_ptr<NEB_JOB> g_neb_current;
static vector<shared_ptr<NEB_JOB> > g_neb_jobs;
// Duplicate attempts are keyed by parent and the complete 80-byte header.
static std::map<string, std::set<vector<uint8_t> > > g_neb_seen;
static char g_neb_chain_id[65] = { 0 };
static bool g_neb_started = false;
static uint64_t g_neb_session_counter = 0;

struct NEB_PENDING_BLOCK
{
	uint64_t node_id;
	uint32_t height;
	uint8_t id[32];
	int userid;
	int workerid;
	int coinid;
	double network_diff;
	double share_diff;
	bool solo;
	// Keep the submitting session plus the exact share fields so the miner can
	// be told only after the node has really accepted the block.
	int client_id;
	char job_id[16];
	char extranonce2[65];
	char ntime[16];
	char nonce[16];
};
static std::deque<NEB_PENDING_BLOCK> g_neb_pending_blocks;

static int g_neb_node_sock = -1;
static pthread_mutex_t g_neb_sock_lock = PTHREAD_MUTEX_INITIALIZER;

static int neb_dataset_get(void *ctx, uint32_t index, uint8_t *x)
{
	NEB_DATASET *ds = (NEB_DATASET *) ctx;
	if (index >= ds->n) return 0;
	const uint8_t *rec = ds->records.data() + (size_t) index * ds->record_size;
	memcpy(x, rec, ds->nin);
	return rec[ds->nin];
}

static int neb2_dataset_get(void *ctx, uint32_t index, uint8_t *x)
{
	NEB_DATASET *ds = (NEB_DATASET *) ctx;
	if (index >= ds->n) return 0;
	const uint8_t *rec = ds->records.data() + (size_t) index * ds->record_size;
	memcpy(x, rec, ds->nin);
	return rec[ds->nin];
}

static bool nebhash_load_datasets(const char *path)
{
	FILE *f = fopen(path, "rb");
	if (!f)
	{
		stratumlog("nebhash: cannot open dataset %s: %s\n", path, strerror(errno));
		return false;
	}
	if (fseek(f, 0, SEEK_END) != 0)
	{
		fclose(f);
		return false;
	}
	long filelen = ftell(f);
	if (filelen <= 8 || fseek(f, 0, SEEK_SET) != 0)
	{
		fclose(f);
		return false;
	}
	vector<uint8_t> bytes((size_t) filelen);
	if (fread(bytes.data(), 1, bytes.size(), f) != bytes.size())
	{
		fclose(f);
		stratumlog("nebhash: short read on dataset %s\n", path);
		return false;
	}
	fclose(f);

	if (memcmp(bytes.data(), "NMDS", 4) || bytes.size() < 8)
	{
		stratumlog("nebhash: dataset %s has no NMDS header\n", path);
		return false;
	}
	uint32_t count = read_le32(bytes.data() + 4);
	if (!count || count > 16)
	{
		stratumlog("nebhash: dataset set count %u out of range\n", count);
		return false;
	}

	size_t pos = 8;
	NEB_DATASET loaded[16];
	for (uint32_t i = 0; i < count; i++)
	{
		if (pos + 12 > bytes.size() || memcmp(bytes.data() + pos, "NMD2", 4))
		{
			stratumlog("nebhash: dataset %u has no NMD2 header\n", i);
			return false;
		}
		uint32_t arch_id = read_le32(bytes.data() + pos + 4);
		uint32_t n = read_le32(bytes.data() + pos + 8);
		const neb2_arch *arch = neb2_arch_get((int) arch_id);
		if (!arch || !n)
		{
			stratumlog("nebhash: dataset %u has unsupported arch %u\n", i, arch_id);
			return false;
		}
		uint64_t need = (uint64_t) arch->nin + 1;
		uint64_t total = 12 + (uint64_t) n * need;
		if (pos + total > bytes.size())
		{
			stratumlog("nebhash: dataset %u is truncated\n", i);
			return false;
		}
		loaded[i].n = n;
		loaded[i].nin = (uint32_t) arch->nin;
		loaded[i].record_size = (uint32_t) need;
		loaded[i].arch = (int) arch_id;
		loaded[i].records.assign(bytes.begin() + pos + 12, bytes.begin() + pos + (size_t) total);
		for (uint32_t j = 0; j < n; j++)
		{
			const uint8_t *rec = loaded[i].records.data() + (size_t) j * need;
			if (rec[arch->nin] >= NEB2_NOUT)
			{
				stratumlog("nebhash: dataset %u sample %u has bad label\n", i, j);
				return false;
			}
			for (int k = 0; k < arch->nin; k++)
				if (rec[k] > 7)
				{
					stratumlog("nebhash: dataset %u sample %u has bad pixel\n", i, j);
					return false;
				}
		}
		pos += (size_t) total;
	}
	if (pos != bytes.size())
	{
		stratumlog("nebhash: trailing bytes in dataset file\n");
		return false;
	}

	CommonLock(&g_neb_lock);
	for (uint32_t i = 0; i < count; i++) g_neb_datasets[i] = loaded[i];
	g_neb_dataset_count = (int) count;
	neb_blake3_hash(bytes.data(), bytes.size(), g_neb_dataset_hash);
	CommonUnlock(&g_neb_lock);

	char hashhex[65];
	bytes_to_hex(g_neb_dataset_hash, 32, hashhex);
	stratumlog("nebhash: loaded %u datasets, dataset_hash %s\n", count, hashhex);
	return true;
}

static shared_ptr<NEB_MODEL> nebhash_decode_model(uint8_t slot,
	const uint8_t *payload, size_t len)
{
	// The caller already stripped the one-byte slot from the frame. The
	// remaining payload is exactly a 32-byte weights hash plus packed weights.
	if (len < 32) return shared_ptr<NEB_MODEL>();

	CommonLock(&g_neb_lock);
	if (slot >= (uint8_t) g_neb_dataset_count)
	{
		CommonUnlock(&g_neb_lock);
		return shared_ptr<NEB_MODEL>();
	}
	int arch = g_neb_datasets[slot].arch;
	CommonUnlock(&g_neb_lock);

	const neb2_arch *a = neb2_arch_get(arch);
	if (!a) return shared_ptr<NEB_MODEL>();

	size_t packed_len = (size_t) a->nweights / 2;
	if (len - 32 != packed_len)
	{
		stratumlog("nebhash: model frame slot %u has %zu weight bytes, expected %zu\n",
			slot, len - 32, packed_len);
		return shared_ptr<NEB_MODEL>();
	}

	shared_ptr<NEB_MODEL> m(new NEB_MODEL());
	m->slot = slot;
	m->arch = arch;
	memcpy(m->hash, payload, 32);

	m->model.reset(new neb2_model);
	memset(m->model.get(), 0, sizeof(neb2_model));
	m->model->arch = arch;
	const uint8_t *packed = payload + 32;
	for (int i = 0; i < a->nweights; i++)
	{
		uint8_t nibble = (i & 1) ? (packed[i/2] >> 4) : (packed[i/2] & 15);
		m->model->w[i] = (int8_t) ((int8_t) (nibble << 4) >> 4);
	}
	vector<char> packed_hex(packed_len * 2 + 1, 0);
	bytes_to_hex(packed, packed_len, packed_hex.data());
	m->packed_hex.assign(packed_hex.data());

	uint8_t wh[32], rh[32];
	neb2_model_hashes(m->model.get(), wh, rh);
	if (memcmp(wh, m->hash, 32))
	{
		stratumlog("nebhash: model slot %u weights hash mismatch; ignored\n", slot);
		return shared_ptr<NEB_MODEL>();
	}
	return m;
}

// ---------------------------------------------------------------------------
// Header and ticket evaluation
// ---------------------------------------------------------------------------

static void nebhash_build_body_root(const NEB_JOB &job,
	const uint8_t *extranonce, size_t extranonce_len, uint8_t out[32])
{
	vector<uint8_t> coinbase;
	coinbase.reserve(job.coinbase_prefix.size() + extranonce_len + job.coinbase_suffix.size());
	coinbase.insert(coinbase.end(), job.coinbase_prefix.begin(), job.coinbase_prefix.end());
	coinbase.insert(coinbase.end(), extranonce, extranonce + extranonce_len);
	coinbase.insert(coinbase.end(), job.coinbase_suffix.begin(), job.coinbase_suffix.end());

	uint8_t coinhash[32], root[32], le[4];
	neb_blake3_hash(coinbase.data(), coinbase.size(), coinhash);
	hash_parts("leaf", 4, coinhash, 32, NULL, 0, NULL, 0, root);
	for (size_t i = 0; i < job.branch.size(); i++)
		hash_parts("node", 4, root, 32, job.branch[i].data(), job.branch[i].size(), NULL, 0, root);

	write_le32(le, job.tx_count);
	hash_parts("body", 4, le, 4, root, 32, job.shares_root, 32, out);
}

static void nebhash_build_header(const NEB_JOB &job,
	const uint8_t *extranonce, size_t extranonce_len,
	uint32_t ntime, uint32_t nonce, uint8_t header[80])
{
	write_le32(header, job.version);
	memcpy(header + 4, job.parent, 32);
	nebhash_build_body_root(job, extranonce, extranonce_len, header + 36);
	write_le32(header + 68, ntime);
	write_le32(header + 72, job.bits);
	write_le32(header + 76, nonce);
}

// Evaluate only the ticket; header/key construction stays in the caller so a
// duplicate header can be rejected before running the expensive model pass.
static bool nebhash_evaluate_key(const NEB_JOB &job, const uint8_t key[32], uint8_t ticket[32])
{
	// Keep the dataset lock for the whole evaluation: the context points into
	// g_neb_datasets, so it must not be invalidated while the ticket runs.
	CommonLock(&g_neb_lock);
	if (job.slot >= (uint8_t) g_neb_dataset_count || !job.model)
	{
		CommonUnlock(&g_neb_lock);
		return false;
	}
	NEB_DATASET *ds = &g_neb_datasets[job.slot];
	const neb2_arch *arch = neb2_arch_get(ds->arch);
	if (!arch || arch->id != job.model->arch)
	{
		CommonUnlock(&g_neb_lock);
		return false;
	}
	int32_t *g = (int32_t *) malloc((size_t) arch->nweights * sizeof(int32_t));
	if (!g)
	{
		CommonUnlock(&g_neb_lock);
		return false;
	}

	neb2_dataset dataset;
	dataset.n = ds->n;
	dataset.get = neb2_dataset_get;
	dataset.ctx = ds;

	uint8_t digest[32];
	int eval_rc = neb2_share_eval(job.model->model.get(), &dataset, key, g, digest, ticket, NULL, NULL);
	free(g);
	CommonUnlock(&g_neb_lock);
	return eval_rc == 0;
}

// ---------------------------------------------------------------------------
// 256-bit difficulty helpers
// ---------------------------------------------------------------------------

void nebhash_diff_to_target(double diff, uint8_t le[32])
{
	if (!(diff > 0) || !isfinite(diff))
	{
		memset(le, 0xff, 32);
		return;
	}

	long double maxv = ldexpl(1.0L, 256);
	long double t = maxv / (long double) diff - 1.0L;
	if (t < 0) t = 0;
	if (t > maxv - 1) t = maxv - 1;
	for (int i = 0; i < 32; i++)
	{
		long double scale = ldexpl(1.0L, 8*i);
		long double q = floorl(t / scale);
		le[i] = (uint8_t) fmodl(q, 256.0L);
	}
}

// Exact LE shift: the reference pool uses share_target * 2^k, saturating at
// 2^256-1. Deriving it via a floating-point difficulty introduces an off-by-one
// in the target that would reject a valid low-difficulty share.
static void nebhash_shift_target(const uint8_t base[32], uint32_t k, uint8_t out[32])
{
	int top = -1;
	for (int i = 31; i >= 0; i--)
		if (base[i]) { top = i; break; }
	if (top < 0)
	{
		memset(out, 0, 32);
		return;
	}
	uint32_t bitlen = (uint32_t) top * 8 + (uint32_t) (8 - __builtin_clz((unsigned int) base[top]) + 24);
	if (bitlen > 256 || k > 256 - bitlen)
	{
		memset(out, 0xff, 32);
		return;
	}
	size_t bytes = k / 8;
	unsigned int bits = k % 8;
	memset(out, 0, 32);
	for (size_t i = 0; i + bytes < 32; i++)
	{
		out[i + bytes] |= (uint8_t) (base[i] << bits);
		if (bits && i + bytes + 1 < 32)
			out[i + bytes + 1] |= (uint8_t) (base[i] >> (8 - bits));
	}
}

uint256 nebhash_diff_to_uint256(double diff)
{
	uint8_t le[32];
	nebhash_diff_to_target(diff, le);
	return uint256(vector<unsigned char>(le, le + 32));
}

static double nebhash_le_to_diff(const uint8_t le[32])
{
	long double v = 0;
	for (int i = 31; i >= 0; i--) v = v * 256.0L + (long double) le[i];
	return (double) (ldexpl(1.0L, 256) / (v + 1.0L));
}

double nebhash_target_to_diff(const uint8_t le[32]) { return nebhash_le_to_diff(le); }
double nebhash_ticket_to_diff(const uint8_t ticket[32]) { return nebhash_le_to_diff(ticket); }

void nebhash_target_to_hex(const uint8_t le[32], char hex[65])
{
	bytes_to_hex(le, 32, hex);
}

double nebhash_target_hex_to_diff(const char *hex)
{
	uint8_t le[32];
	if (!parse_hex_bytes(hex, 32, le)) return 0;
	return nebhash_le_to_diff(le);
}

// Numeric compare for NEB's little-endian 256-bit values.
static bool nebhash_le_lte(const uint8_t a[32], const uint8_t b[32])
{
	for (int i = 31; i >= 0; i--)
	{
		if (a[i] < b[i]) return true;
		if (a[i] > b[i]) return false;
	}
	return true;
}

// Convert a miner-visible difficulty (d=0.1) to a pool target, never making
// the pool target harder than the node's share target.
static void nebhash_target_from_display_diff(double diff,
	const uint8_t share_target[32], uint8_t out[32])
{
	if (!(diff > 0) || !isfinite(diff))
	{
		memcpy(out, share_target, 32);
		return;
	}
	nebhash_diff_to_target(diff * NEB_MINER_DIFF_SCALE, out);
	if (nebhash_le_lte(out, share_target))
		memcpy(out, share_target, 32);
}

// Target currently published to a session. Sessions without a d=/sd=
// override keep the exact share_target << k behaviour.
static void nebhash_client_target(const YAAMP_CLIENT *client,
	const uint8_t share_target[32], uint8_t out[32])
{
	if (client && client->neb_diff > 0)
		nebhash_target_from_display_diff(client->neb_diff, share_target, out);
	else
		nebhash_shift_target(share_target, client ? client->neb_k : 0, out);
}

void nebhash_client_set_target_silent(YAAMP_CLIENT *client, const uint8_t le[32])
{
	if (!client) return;
	client->share_target = uint256(vector<unsigned char>(le, le + 32));
	client->next_target = client->share_target;
}

void nebhash_client_publish_target(YAAMP_CLIENT *client)
{
	if (!client) return;
	char hex[65];
	bytes_to_hex(client->share_target.begin(), 32, hex);
	client_call(client, "mining.set_target", "[\"%s\"]", hex);
}

void nebhash_client_set_target_bytes(YAAMP_CLIENT *client, const uint8_t le[32])
{
	nebhash_client_set_target_silent(client, le);
	nebhash_client_publish_target(client);
}

void nebhash_client_set_target(YAAMP_CLIENT *client, double difficulty)
{
	uint8_t le[32];
	nebhash_diff_to_target(difficulty, le);
	nebhash_client_set_target_bytes(client, le);
}

static void nebhash_current_share_target(uint8_t le[32])
{
	CommonLock(&g_neb_lock);
	shared_ptr<NEB_JOB> job = g_neb_current;
	if (job) memcpy(le, job->share_target, 32);
	CommonUnlock(&g_neb_lock);
	if (!job) memset(le, 0, 32);
}

static void nebhash_pool_target(uint32_t k, uint8_t le[32])
{
	uint8_t share_target[32];
	nebhash_current_share_target(share_target);
	nebhash_shift_target(share_target, k, le);
}

void nebhash_initial_target(uint8_t le[32])
{
	nebhash_pool_target(g_nebhash_start_k, le);
}

static bool nebhash_template_target(YAAMP_JOB_TEMPLATE *templ, const YAAMP_CLIENT *client, uint8_t le[32])
{
	uint8_t base[32];
	if (!templ || !parse_hex_bytes(templ->neb_share_target, 32, base)) return false;
	nebhash_client_target(client, base, le);
	return true;
}

// Target used to validate a submit. The prior, easier target stays valid for a
// short grace window after vardiff makes the target harder (reference pool).
static void nebhash_submit_target(YAAMP_CLIENT *client, YAAMP_JOB_TEMPLATE *templ, uint8_t le[32])
{
	uint8_t base[32];
	if (!templ || !parse_hex_bytes(templ->neb_share_target, 32, base))
	{
		memset(le, 0, 32);
		return;
	}
	nebhash_client_target(client, base, le);
	if (!client->neb_k_changed ||
		neb_monotonic_ms() - client->neb_k_changed >= NEB_PRIOR_TARGET_GRACE_MS)
		return;

	// The previous, easier target stays valid briefly after a harder change.
	if (client->neb_diff > 0)
	{
		if (client->neb_diff_prev < client->neb_diff)
			nebhash_target_from_display_diff(client->neb_diff_prev, base, le);
	}
	else if (client->neb_k_prev > client->neb_k)
		nebhash_shift_target(base, client->neb_k_prev, le);
}

bool nebhash_current_job_ready()
{
	CommonLock(&g_neb_lock);
	bool ready = g_neb_current && g_neb_current->model;
	CommonUnlock(&g_neb_lock);
	return ready;
}

double nebhash_initial_difficulty()
{
	uint8_t target[32];
	nebhash_initial_target(target);
	return nebhash_target_to_diff(target);
}

void nebhash_client_init(YAAMP_CLIENT *client)
{
	if (!client) return;
	client->neb_subscribed = false;
	client->neb_authorized = false;
	client->neb_k = g_nebhash_start_k;
	client->neb_k_prev = g_nebhash_start_k;
	client->neb_k_changed = neb_monotonic_ms();
	client->neb_diff = 0;
	client->neb_diff_prev = 0;
	client->neb_vardiff_start = neb_monotonic_ms();
	client->neb_vardiff_shares = 0;
	client->neb_second_start = neb_monotonic_ms();
	client->neb_second_shares = 0;
	client->neb_last_share_time = 0;
	client->neb_speed_start = 0;
	client->neb_speed_diff = 0;
	client->neb_bad_in_a_row = 0;
	client->neb_models_count = 0;
	client->neb_first_job = true;
	client->neb_vardiff_repush = false;
	client->neb_login_count = 0;
	memset(client->neb_logins, 0, sizeof(client->neb_logins));
	memset(client->neb_login_userid, 0, sizeof(client->neb_login_userid));
	memset(client->neb_login_workerid, 0, sizeof(client->neb_login_workerid));
	memset(client->neb_login_solo, 0, sizeof(client->neb_login_solo));
	client->neb_session = __sync_add_and_fetch(&g_neb_session_counter, 1);

	// The pool target is share_target << start_k, shifted exactly. Never seed
	// a NEB session from yiimp's generic difficulty (16), which is unrelated
	// to the node's 256-bit share target.
	uint8_t target[32];
	nebhash_initial_target(target);
	nebhash_client_set_target_silent(client, target);
	client->difficulty_actual = nebhash_target_to_diff(target);
}

// NEB share arrival is a Poisson process: one lucky share after 4 s is not a
// 40 Mh/s miner. Decay the accumulated expected work (target difficulty) with
// a 600 s time constant and divide by that same constant, so both the web 600 s
// window and the stratum job log converge on the miner's sustained rate
// without share-luck spikes. Ticket score is deliberately not used: its
// heavy tail made a steady ~5 Mh/s miner read as 18 Mh/s.
#define NEB_SPEED_WINDOW 600.0

static void nebhash_speed_decay(YAAMP_CLIENT *client, long long now)
{
	if (!client->neb_speed_start)
	{
		client->neb_speed_start = now;
		return;
	}
	if (now <= client->neb_speed_start) return;

	double elapsed = (double) (now - client->neb_speed_start);
	client->neb_speed_diff *= exp(-elapsed / NEB_SPEED_WINDOW);
	client->neb_speed_start = now;
}

double nebhash_client_speed(YAAMP_CLIENT *client)
{
	if (!client) return 0;

	nebhash_speed_decay(client, (long long) time(NULL));
	client->speed = client->neb_speed_diff / NEB_SPEED_WINDOW;
	return client->speed;
}

void nebhash_client_note_share_speed(YAAMP_CLIENT *client, double target_diff)
{
	if (!client) return;

	long long now = (long long) time(NULL);
	nebhash_speed_decay(client, now);
	client->neb_speed_diff += target_diff;
	client->neb_last_share_time = now;
	client->speed = client->neb_speed_diff / NEB_SPEED_WINDOW;
}

void nebhash_apply_password(YAAMP_CLIENT *client)
{
	if (!client) return;

	// Mirror yiimp's password semantics: d= pins the target for the whole
	// session, sd= only seeds it and leaves vardiff active. d= wins if both
	// are present. A plain integer 1..48 keeps the reference pool's k shift;
	// any other positive number is the miner/web display difficulty, where
	// 0.1 means 10^9 expected hashes per share.
	const char *value = client_password_param(client->password, "d", 1);
	bool fixed = true;
	if (!value)
	{
		value = client_password_param(client->password, "sd", 2);
		fixed = false;
	}
	if (!value || !*value) return;

	errno = 0;
	char *end = NULL;
	long k = strtol(value, &end, 10);
	while (*end == ' ' || *end == '\t') end++;
	bool integer_token = !errno && end != value && (!*end || *end == ',');
	bool use_k = integer_token && k >= 1 && k <= NEB_K_MAX;
	double diff = 0;

	if (!use_k)
	{
		// A pure integer outside 1..48 was always invalid; do not silently
		// reinterpret it as a display difficulty (d=49, d=-1, d=0).
		if (integer_token)
		{
			stratumlog("nebhash: ignoring invalid password k '%s' for client %d\n",
				value, client->userid);
			return;
		}

		errno = 0;
		end = NULL;
		diff = strtod(value, &end);
		while (*end == ' ' || *end == '\t') end++;
		if (errno || end == value || (*end && *end != ',') ||
			!(diff > 0) || !isfinite(diff))
		{
			stratumlog("nebhash: ignoring invalid password difficulty '%s' for client %d\n",
				value, client->userid);
			return;
		}
	}

	uint8_t share_target[32], target[32];
	nebhash_current_share_target(share_target);
	bool has_work = false;
	for (int i = 0; i < 32; i++) if (share_target[i]) { has_work = true; break; }
	if (!has_work) return;

	long long now = neb_monotonic_ms();
	client->neb_k_changed = now;
	client->neb_vardiff_start = now;
	client->neb_vardiff_shares = 0;
	client->difficulty_fixed = fixed;

	if (use_k)
	{
		client->neb_k = (uint32_t) k;
		client->neb_k_prev = (uint32_t) k;
		client->neb_diff = 0;
		client->neb_diff_prev = 0;
		nebhash_shift_target(share_target, client->neb_k, target);
		stratumlog("nebhash: password %s=%ld k for client %d (fixed=%d)\n",
			fixed ? "d" : "sd", k, client->userid, fixed ? 1 : 0);
	}
	else
	{
		// The pool target may never be harder than the node's share target.
		double max_diff = nebhash_target_to_diff(share_target) / NEB_MINER_DIFF_SCALE;
		if (diff > max_diff) diff = max_diff;
		if (diff < 1e-6) diff = 1e-6;

		client->neb_k = g_nebhash_start_k;
		client->neb_k_prev = g_nebhash_start_k;
		client->neb_diff = diff;
		client->neb_diff_prev = diff;
		nebhash_target_from_display_diff(diff, share_target, target);
		stratumlog("nebhash: password %s=%.6g difficulty for client %d (fixed=%d)\n",
			fixed ? "d" : "sd", diff, client->userid, fixed ? 1 : 0);
	}

	nebhash_client_set_target_silent(client, target);
	client->difficulty_actual = nebhash_target_to_diff(target);
}

int nebhash_extranonce2_size()
{
	int en2 = 12;
	CommonLock(&g_neb_lock);
	if (g_neb_current && g_neb_current->extranonce_size > 4)
		en2 = (int) g_neb_current->extranonce_size - 4;
	CommonUnlock(&g_neb_lock);
	if (en2 > 28) en2 = 28;
	return en2;
}

int nebhash_send_error(YAAMP_CLIENT *client, int code, const char *message)
{
	if (!client || !client->sock) return -1;
	char id[1024];
	if (client->id_str && *client->id_str)
		snprintf(id, sizeof(id), "\"%s\"", client->id_str);
	else
		snprintf(id, sizeof(id), "%d", client->id_int);

	// neb-pool/1 uses a null result and a two-element error array. Yiimp's
	// generic client_send_error() emits result:false and a third null element,
	// which strict miners reject.
	return socket_send(client->sock,
		"{\"id\":%s,\"result\":null,\"error\":[%d,\"%s\"]}\n",
		id, code, message ? message : "error");
}

static int neb_login_index(YAAMP_CLIENT *client, const char *login)
{
	if (!client || !login) return -1;
	for (int i = 0; i < client->neb_login_count; i++)
		if (!strcmp(client->neb_logins[i], login)) return i;
	return -1;
}

static void neb_copy_field(char *dst, size_t size, const char *src)
{
	if (!size) return;
	strncpy(dst, src ? src : "", size - 1);
	dst[size - 1] = 0;
}

void nebhash_split_login(const char *login, char address[1088], char worker[1088])
{
	snprintf(address, 1088, "%s", login ? login : "");
	worker[0] = 0;
	// neb-pool/1 splits only on the first '.'. An absent or empty worker is
	// reported as "default", matching the reference pool.
	char *sep = strchr(address, '.');
	if (sep)
	{
		snprintf(worker, 1088, "%s", sep + 1);
		*sep = 0;
	}
	if (!*worker) snprintf(worker, 1088, "default");
}

static bool nebhash_valid_worker(const char *worker)
{
	if (!worker || !*worker) return false;
	size_t len = strlen(worker);
	if (len > 32) return false;
	for (size_t i = 0; i < len; i++)
	{
		char c = worker[i];
		bool ok = (c >= 'a' && c <= 'z') || (c >= 'A' && c <= 'Z') ||
			(c >= '0' && c <= '9') || c == '_' || c == '-';
		if (!ok) return false;
	}
	return true;
}

bool nebhash_add_login(YAAMP_CLIENT *client, const char *login, const char *password)
{
	if (!client || !login || !*login) return false;

	char address[1088], worker[1088];
	nebhash_split_login(login, address, worker);
	if (!nebhash_valid_address(address) || !nebhash_valid_worker(worker)) return false;

	bool solo = password && strcasestr(password, "solo");
	int idx = neb_login_index(client, login);
	if (idx >= 0)
	{
		client->neb_login_solo[idx] = solo;
		return true;
	}
	if (client->neb_login_count >= 8) return false;

	idx = client->neb_login_count++;
	snprintf(client->neb_logins[idx], 1088, "%s", login);
	client->neb_login_userid[idx] = 0;
	client->neb_login_workerid[idx] = 0;
	client->neb_login_solo[idx] = solo;
	client->neb_authorized = true;
	return true;
}

bool nebhash_login_authorized(YAAMP_CLIENT *client, const char *login)
{
	return neb_login_index(client, login) >= 0 && client->neb_authorized;
}

bool nebhash_select_login(YAAMP_CLIENT *client, const char *login)
{
	int idx = neb_login_index(client, login);
	if (idx < 0 || !client->neb_authorized) return false;

	// The first login's account/worker were already created by the normal
	// authorize path; cache them instead of inserting a duplicate worker.
	if (idx == 0 && !client->neb_login_userid[idx] && client->userid && client->workerid)
	{
		client->neb_login_userid[idx] = client->userid;
		client->neb_login_workerid[idx] = client->workerid;
	}

	if (!client->neb_login_userid[idx] || !client->neb_login_workerid[idx])
	{
		int saved_userid = client->userid;
		int saved_workerid = client->workerid;
		int saved_coinid = client->coinid;
		bool saved_solo = client->solo;
		char saved_username[1088], saved_worker[1088];
		snprintf(saved_username, sizeof(saved_username), "%s", client->username);
		snprintf(saved_worker, sizeof(saved_worker), "%s", client->worker);

		char address[1088], worker[1088];
		nebhash_split_login(login, address, worker);
		neb_copy_field(client->username, sizeof(client->username), address);
		neb_copy_field(client->worker, sizeof(client->worker), worker);
		client->solo = client->neb_login_solo[idx];
		client->userid = 0;
		client->workerid = 0;

		CommonLock(&g_db_mutex);
		db_add_user(g_db, client);
		if (client->userid > 0) db_add_worker(g_db, client);
		CommonUnlock(&g_db_mutex);

		if (client->userid <= 0 || client->workerid <= 0)
		{
			client->userid = saved_userid;
			client->workerid = saved_workerid;
			client->coinid = saved_coinid;
			client->solo = saved_solo;
			neb_copy_field(client->username, sizeof(client->username), saved_username);
			neb_copy_field(client->worker, sizeof(client->worker), saved_worker);
			return false;
		}
		client->neb_login_userid[idx] = client->userid;
		client->neb_login_workerid[idx] = client->workerid;
	}

	client->userid = client->neb_login_userid[idx];
	client->workerid = client->neb_login_workerid[idx];
	client->solo = client->neb_login_solo[idx];
	char address[1088], worker[1088];
	nebhash_split_login(login, address, worker);
	neb_copy_field(client->username, sizeof(client->username), address);
	neb_copy_field(client->worker, sizeof(client->worker), worker);
	return true;
}

void nebhash_clear_workers(YAAMP_CLIENT *client)
{
	if (!client || client->reconnecting) return;

	CommonLock(&g_db_mutex);
	for (int i = 0; i < client->neb_login_count; i++)
	{
		int workerid = client->neb_login_workerid[i];
		client->neb_login_workerid[i] = 0;
		if (!workerid) continue;
		client->workerid = workerid;
		db_clear_worker(g_db, client);
	}
	CommonUnlock(&g_db_mutex);
	client->workerid = 0;
}

// Returning false from mining.submit makes client_thread break its read loop
// and close the socket. object_delete() alone would only mark the object and
// leave the thread using it until the asynchronous pruner frees it.
static bool nebhash_close_client_session(YAAMP_CLIENT *client)
{
	if (client && client->sock && client->sock->sock >= 0)
		shutdown(client->sock->sock, SHUT_RDWR);
	return false;
}

bool nebhash_submit_rate_ok(YAAMP_CLIENT *client)
{
	long long now = neb_monotonic_ms();
	if (now - client->neb_second_start >= 1000LL)
	{
		client->neb_second_start = now;
		client->neb_second_shares = 0;
	}
	if (++client->neb_second_shares > NEB_MAX_SUBMITS_PER_SEC) return false;
	return true;
}

bool nebhash_note_bad_submit(YAAMP_CLIENT *client)
{
	return ++client->neb_bad_in_a_row > NEB_MAX_BAD_IN_A_ROW;
}

void nebhash_note_good_submit(YAAMP_CLIENT *client)
{
	client->neb_bad_in_a_row = 0;
}

static bool nebhash_vardiff_apply(YAAMP_CLIENT *client, const uint8_t share_target[32],
	uint32_t counted, long long now)
{
	long long elapsed = now - client->neb_vardiff_start;
	if (elapsed < 1) elapsed = 1;
	if (counted <= NEB_RATE_HIGH * 2 && elapsed < NEB_VARDiff_WINDOW_MS)
		return false;

	double scaled = (double) counted * (double) NEB_VARDiff_WINDOW_MS / (double) elapsed;
	uint32_t oldk = client->neb_k;
	double olddiff = client->neb_diff;
	bool changed = false;

	if (client->neb_diff > 0)
	{
		// d=/sd= sessions vardiff on the displayed difficulty itself. Doubling
		// the displayed difficulty halves the target, exactly like k -= 1.
		double max_diff = nebhash_target_to_diff(share_target) / NEB_MINER_DIFF_SCALE;
		const double min_diff = 1e-6;
		if (scaled > (double) NEB_RATE_HIGH && client->neb_diff < max_diff)
		{
			uint32_t steps = (uint32_t) floor(log(scaled / 6.0) / log(2.0));
			if (steps < 1) steps = 1;
			client->neb_diff *= ldexp(1.0, (int) steps);
			if (client->neb_diff > max_diff) client->neb_diff = max_diff;
		}
		else if (scaled < (double) NEB_RATE_LOW && client->neb_diff > min_diff)
		{
			client->neb_diff /= 2.0;
			if (client->neb_diff < min_diff) client->neb_diff = min_diff;
		}
		changed = client->neb_diff != olddiff;
	}
	else
	{
		if (scaled > (double) NEB_RATE_HIGH && client->neb_k > 0)
		{
			uint32_t steps = (uint32_t) floor(log(scaled / 6.0) / log(2.0));
			if (steps < 1) steps = 1;
			if (steps > client->neb_k) steps = client->neb_k;
			client->neb_k -= steps;
		}
		else if (scaled < (double) NEB_RATE_LOW && client->neb_k < NEB_K_MAX)
			client->neb_k++;
		changed = client->neb_k != oldk;
	}

	client->neb_vardiff_start = now;
	client->neb_vardiff_shares = 0;
	if (!changed) return false;

	if (client->neb_diff > 0) client->neb_diff_prev = olddiff;
	else client->neb_k_prev = oldk;
	client->neb_k_changed = now;

	uint8_t target[32];
	nebhash_client_target(client, share_target, target);
	client->difficulty_actual = nebhash_target_to_diff(target);
	nebhash_client_set_target_bytes(client, target);

	// The caller re-pushes the job only after it releases the job object
	// lock. Re-entering object_find() while locked would unlock it twice.
	client->neb_vardiff_repush = true;
	return true;
}

bool nebhash_vardiff(YAAMP_CLIENT *client, YAAMP_JOB *job)
{
	if (!client || !job || !job->templ || client->difficulty_fixed || !client->neb_authorized)
		return false;

	long long now = neb_monotonic_ms();
	// Reference vardiff clears the consecutive-bad counter on every accepted
	// share, even when k does not change.
	client->neb_bad_in_a_row = 0;
	if (!client->neb_vardiff_start) client->neb_vardiff_start = now;
	client->neb_vardiff_shares++;

	uint8_t share_target[32];
	if (!parse_hex_bytes(job->templ->neb_share_target, 32, share_target)) return false;
	return nebhash_vardiff_apply(client, share_target, client->neb_vardiff_shares, now);
}

// A miner that never finds a share at the initial target would never trigger
// the submit-driven vardiff above and could stay on an impossible target
// forever. Run the same 60 s window from the periodic client loop so a quiet
// session gets easier until it starts producing shares; once shares arrive the
// normal submit-driven adjustments take over.
bool nebhash_vardiff_tick(YAAMP_CLIENT *client)
{
	if (!client || client->difficulty_fixed || !client->neb_authorized || client->deleted)
		return false;

	uint8_t share_target[32];
	nebhash_current_share_target(share_target);
	bool has_work = false;
	for (int i = 0; i < 32; i++) if (share_target[i]) { has_work = true; break; }
	if (!has_work) return false;

	long long now = neb_monotonic_ms();
	if (!client->neb_vardiff_start) client->neb_vardiff_start = now;
	uint32_t oldk = client->neb_k;
	double olddiff = client->neb_diff;
	if (!nebhash_vardiff_apply(client, share_target, client->neb_vardiff_shares, now))
		return false;

	if (client->neb_diff > 0)
		stratumlog("nebhash: idle vardiff difficulty %.6g -> %.6g for client %d\n",
			olddiff, client->neb_diff, client->userid);
	else
		stratumlog("nebhash: idle vardiff k %u -> %u for client %d\n",
			oldk, client->neb_k, client->userid);
	return true;
}

// ---------------------------------------------------------------------------
// Work-service frames
// ---------------------------------------------------------------------------

static bool neb_send_all(int sock, const uint8_t *data, size_t len)
{
	while (len)
	{
		ssize_t n = send(sock, data, len, MSG_NOSIGNAL);
		if (n > 0) { data += n; len -= (size_t) n; continue; }
		if (n < 0 && errno == EINTR) continue;
		return false;
	}
	return true;
}

static bool neb_send_frame(int sock, uint8_t type, const vector<uint8_t> &payload)
{
	if (payload.size() + 1 > 2*1024*1024) return false;
	uint8_t head[4];
	uint32_t len = (uint32_t) payload.size() + 1;
	write_le32(head, len);
	if (!neb_send_all(sock, head, 4)) return false;
	if (!neb_send_all(sock, &type, 1)) return false;
	return payload.empty() || neb_send_all(sock, payload.data(), payload.size());
}

static bool neb_parse_job(const uint8_t *p, size_t len, shared_ptr<NEB_JOB> &out)
{
	NEB_READER r(p, len);
	if (!(out.reset(new NEB_JOB()), r.u64(&out->node_id))) return false;
	// The Job frame does not carry its dataset slot; it comes from the Model
	// frame immediately before it. Initialize these fields so a missing or
	// out-of-order Model is reported deterministically instead of using heap
	// garbage.
	out->slot = 0;
	memset(out->weights_hash, 0, sizeof(out->weights_hash));
	uint8_t clean;
	if (!r.u8(&clean) || clean > 1) return false;
	out->clean = clean != 0;
	if (!r.u32(&out->version)) return false;
	const uint8_t *q;
	if (!r.bytes(32, &q)) return false;
	memcpy(out->parent, q, 32);
	if (!r.u32(&out->time_start) || !r.u32(&out->time_window) || !r.u32(&out->bits)) return false;
	if (!r.bytes(32, &q)) return false;
	memcpy(out->target, q, 32);
	if (!r.bytes(32, &q)) return false;
	memcpy(out->share_target, q, 32);
	if (!r.varbytes(&out->coinbase_prefix) || !r.varbytes(&out->coinbase_suffix)) return false;
	if (!r.u8(&out->extranonce_size) || out->extranonce_size < 1 || out->extranonce_size > 32) return false;

	uint64_t nbranch;
	if (!r.varint(&nbranch) || nbranch > 4096) return false;
	for (uint64_t i = 0; i < nbranch; i++)
	{
		if (!r.bytes(32, &q)) return false;
		out->branch.push_back(vector<uint8_t>(q, q + 32));
	}
	if (!r.u32(&out->tx_count) || !out->tx_count) return false;
	if (!r.bytes(32, &q)) return false;
	memcpy(out->shares_root, q, 32);
	if (!r.u32(&out->height) || !r.u32(&out->expiry)) return false;
	return r.done();
}

static void nebhash_install_model(const shared_ptr<NEB_MODEL> &m)
{
	char hashhex[65];
	bytes_to_hex(m->hash, 32, hashhex);
	CommonLock(&g_neb_lock);
	g_neb_slot_model[m->slot] = m;
	g_neb_last_model = m;
	g_neb_models[hashhex] = m;
	CommonUnlock(&g_neb_lock);
	stratumlog("nebhash: model slot %d hash %s\n", m->slot, hashhex);
}

static void nebhash_install_job(const shared_ptr<NEB_JOB> &job)
{
	CommonLock(&g_neb_lock);
	g_neb_jobs.push_back(job);
	while (g_neb_jobs.size() > 32) g_neb_jobs.erase(g_neb_jobs.begin());
	std::set<string> live_parents;
	for (size_t i = 0; i < g_neb_jobs.size(); i++)
	{
		char parenthex[65];
		bytes_to_hex(g_neb_jobs[i]->parent, 32, parenthex);
		live_parents.insert(parenthex);
	}
	for (std::map<string, std::set<vector<uint8_t> > >::iterator it = g_neb_seen.begin();
		it != g_neb_seen.end(); )
	{
		if (!live_parents.count(it->first)) g_neb_seen.erase(it++);
		else ++it;
	}
	g_neb_current = job;
	CommonUnlock(&g_neb_lock);

	char parenthash[65];
	bytes_to_hex(job->parent, 32, parenthash);
	stratumlog("nebhash: job %llu height %u slot %u bits %08x parent %s clean %d\n",
		(unsigned long long) job->node_id, job->height, job->slot, job->bits,
		parenthash, job->clean ? 1 : 0);
	job_signal();
}

static void nebhash_refresh_coind(YAAMP_COIND *coind)
{
	if (!coind) return;

	json_value *json = rpc_call(&coind->rpc, "getinfo", "[]", coind);
	if (!json) return;
	json_value *result = json_get_object(json, "result");
	if (result && result->type == json_object)
	{
		const char *chainid = json_get_string(result, "chainid");
		if (chainid && strlen(chainid) == 64)
		{
			CommonLock(&g_neb_lock);
			snprintf(g_neb_chain_id, sizeof(g_neb_chain_id), "%s", chainid);
			CommonUnlock(&g_neb_lock);
		}

		// NEB has no getblocktemplate/getmininginfo. Feed the coind state
		// coind_can_mine() and the web UI need from the node's getinfo RPC.
		int height = (int) json_get_int(result, "blocks");
		if (height > 0) coind->height = height;

		json_int_t bits_value = json_get_int(result, "bits");
		if (bits_value > 0)
		{
			uint32_t bits = (uint32_t) bits_value;
			uint32_t shift = bits >> 24;
			uint32_t mantissa = bits & 0xffffff;
			if (shift <= 232 && mantissa >= 0x800000 && mantissa <= 0xffffff)
			{
				long double target = ldexpl((long double) mantissa, (int) shift);
				long double maxv = ldexpl(1.0L, 256);
				if (target > 0 && target < maxv)
					coind->difficulty = (double) (maxv / (target + 1.0L));
			}
		}
	}
	json_value_free(json);

	// The work-service job carries the authoritative full 256-bit target.
	CommonLock(&g_neb_lock);
	shared_ptr<NEB_JOB> job = g_neb_current;
	CommonUnlock(&g_neb_lock);
	if (job && job->height) coind->height = (int) job->height - 1;
	if (job) coind->difficulty = nebhash_target_to_diff(job->target);
}

// The node Result frame arrives after mining.submit was already answered with
// "true". Only now do we know the share really became a block, so notify just
// the submitting session. A disconnected session simply misses the notice;
// block accounting has already happened and remains authoritative.
static void nebhash_notify_miner_block(const NEB_PENDING_BLOCK &pending)
{
	if (!pending.client_id) return;

	YAAMP_CLIENT *client = (YAAMP_CLIENT *) object_find(&g_list_client, pending.client_id, true);
	if (!client) return;

	if (!client->deleted && client->sock && client->neb_subscribed)
	{
		char blockhash[65];
		bytes_to_hex(pending.id, 32, blockhash);
		// ccminer f3847da parses params as [job_id, nonce], either strings
		// or numbers. Both values here are the same hex strings the miner
		// saw in mining.notify / mining.submit.
		client_call(client, "mining.block", "[\"%s\",\"%s\"]",
			pending.job_id, pending.nonce);
		stratumlog("nebhash: notified client %d of accepted block %s\n",
			pending.client_id, blockhash);
	}

	object_unlock(client);
}

static void nebhash_block_result(bool accepted, uint8_t code)
{
	NEB_PENDING_BLOCK pending;
	memset(&pending, 0, sizeof(pending));
	CommonLock(&g_neb_lock);
	if (!g_neb_pending_blocks.empty())
	{
		pending = g_neb_pending_blocks.front();
		g_neb_pending_blocks.pop_front();
	}
	CommonUnlock(&g_neb_lock);
	if (!pending.node_id) return;

	char blockhash[65];
	bytes_to_hex(pending.id, 32, blockhash);
	stratumlog("nebhash: node %s block at height %u id %s (code %u)\n",
		accepted ? "ACCEPTED" : "refused", pending.height, blockhash, code);
	if (!accepted) return;

	block_add(pending.userid, pending.workerid, pending.coinid, pending.height,
		pending.network_diff, pending.share_diff, blockhash, blockhash, 0, pending.solo);
	block_confirm(pending.coinid, blockhash);
	job_signal();
	nebhash_notify_miner_block(pending);
}

// Model and parent state belong to one work-service connection. The reference
// pool always uses the most recent Model frame for the following Job frame and
// marks a job clean only when its parent changed.
// A malformed frame means our parser and the node's writer disagree. Reconnect
// instead of leaving bad bytes buffered or keeping stale model/job state.
static bool nebhash_handle_node_frame(uint8_t type, const uint8_t *p, size_t len,
	shared_ptr<NEB_MODEL> &latest_model, bool &have_parent, uint8_t last_parent[32])
{
	if (type == NEB_MSG_MODEL)
	{
		if (len < 1)
		{
			stratumlog("nebhash: empty model frame\n");
			return false;
		}
		shared_ptr<NEB_MODEL> m = nebhash_decode_model(p[0], p + 1, len - 1);
		if (!m)
		{
			stratumlog("nebhash: malformed model frame\n");
			return false;
		}
		nebhash_install_model(m);
		latest_model = m;
		return true;
	}

	if (type == NEB_MSG_JOB)
	{
		shared_ptr<NEB_JOB> job;
		if (!neb_parse_job(p, len, job))
		{
			stratumlog("nebhash: malformed job frame\n");
			return false;
		}
		if (job->extranonce_size < 8)
		{
			stratumlog("nebhash: node job extranonce below 8 bytes\n");
			return false;
		}
		// The slot belongs to the most recent Model frame, not the Job frame.
		uint8_t slot = latest_model ? (uint8_t) latest_model->slot : 0;
		if (!latest_model || slot >= (uint8_t) g_neb_dataset_count)
		{
			stratumlog("nebhash: no usable model for job\n");
			return false;
		}

		job->clean = !have_parent || memcmp(last_parent, job->parent, 32) != 0;
		memcpy(last_parent, job->parent, 32);
		have_parent = true;

		job->model = latest_model;
		job->slot = slot;
		memcpy(job->weights_hash, latest_model->hash, 32);
		job->weights_hex = latest_model->packed_hex;
		nebhash_install_job(job);
		return true;
	}

	if (type == NEB_MSG_RESULT)
	{
		// ResultMsg is exactly [accepted, code]. Only a complete result closes
		// the one-deep pending block FIFO, so unrelated frames cannot credit a
		// block that the node has not accepted.
		if (len != 2)
		{
			stratumlog("nebhash: malformed result frame (%zu bytes)\n", len);
			return false;
		}
		nebhash_block_result(p[0] == 1, p[1]);
		return true;
	}

	return true;
}

static bool nebhash_send_hello(int sock)
{
	vector<uint8_t> payload;
	payload.push_back(1); // version, LE16
	payload.push_back(0);
	const char name[] = "yiimp/1";
	write_varint(payload, strlen(name));
	payload.insert(payload.end(), name, name + strlen(name));
	return neb_send_frame(sock, NEB_MSG_HELLO, payload);
}

static void *nebhash_work_thread(void *arg)
{
	(void) arg;
	struct timeval tv;
	tv.tv_sec = 5;
	tv.tv_usec = 0;

	while (!g_exiting)
	{
		struct hostent *ent = gethostbyname(g_nebhash_work_host);
		if (!ent)
		{
			stratumlog("nebhash: cannot resolve work host %s\n", g_nebhash_work_host);
			sleep(3);
			continue;
		}
		struct sockaddr_in addr;
		memset(&addr, 0, sizeof(addr));
		addr.sin_family = AF_INET;
		addr.sin_port = htons((unsigned short) g_nebhash_work_port);
		memcpy(&addr.sin_addr, ent->h_addr, ent->h_length);

		int sock = socket(AF_INET, SOCK_STREAM, 0);
		if (sock < 0) { sleep(3); continue; }
		if (connect(sock, (struct sockaddr *) &addr, sizeof(addr)) < 0)
		{
			stratumlog("nebhash: work service %s:%d unavailable: %s\n",
				g_nebhash_work_host, g_nebhash_work_port, strerror(errno));
			close(sock);
			sleep(3);
			continue;
		}
		setsockopt(sock, SOL_SOCKET, SO_RCVTIMEO, &tv, sizeof(tv));
		int one = 1;
		setsockopt(sock, IPPROTO_TCP, TCP_NODELAY, &one, sizeof(one));
		if (!nebhash_send_hello(sock))
		{
			close(sock);
			sleep(3);
			continue;
		}
		CommonLock(&g_neb_sock_lock);
		g_neb_node_sock = sock;
		CommonUnlock(&g_neb_sock_lock);
		stratumlog("nebhash: connected to work service %s:%d\n", g_nebhash_work_host, g_nebhash_work_port);

		vector<uint8_t> buf;
		uint8_t tmp[65536];
		shared_ptr<NEB_MODEL> latest_model;
		uint8_t last_parent[32];
		bool have_parent = false;
		memset(last_parent, 0, sizeof(last_parent));
		while (!g_exiting)
		{
			bool fatal = false;
			ssize_t n = recv(sock, tmp, sizeof(tmp), 0);
			if (n > 0)
			{
				buf.insert(buf.end(), tmp, tmp + n);
				while (buf.size() >= 4)
				{
					uint32_t framelen = read_le32(buf.data());
					if (framelen < 1 || framelen > 2*1024*1024)
					{
						stratumlog("nebhash: bad frame length %u\n", framelen);
						fatal = true;
						break;
					}
					if (buf.size() < 4 + framelen) break;
					uint8_t frame_type = buf[4];
					if (frame_type < 1 || frame_type > 7)
					{
						stratumlog("nebhash: bad frame type %u\n", frame_type);
						fatal = true;
						break;
					}
					// A Result closes the one-deep pending-block FIFO. Take
					// the socket lock so a submit that is currently sending a
					// Submit frame cannot lose its Result to this reader.
					bool locked = frame_type == NEB_MSG_RESULT;
					if (locked) CommonLock(&g_neb_sock_lock);
					bool ok = nebhash_handle_node_frame(frame_type, buf.data() + 5,
						framelen - 1, latest_model, have_parent, last_parent);
					if (locked) CommonUnlock(&g_neb_sock_lock);
					if (!ok)
					{
						fatal = true;
						break;
					}
					buf.erase(buf.begin(), buf.begin() + 4 + framelen);
				}
				if (fatal) break;
				continue;
			}
			if (n == 0) break;
			if (errno == EINTR || errno == EAGAIN || errno == EWOULDBLOCK) continue;
			break;
		}

		CommonLock(&g_neb_sock_lock);
		if (g_neb_node_sock == sock) g_neb_node_sock = -1;
		CommonUnlock(&g_neb_sock_lock);
		close(sock);
		stratumlog("nebhash: work service disconnected; retrying in 3 s\n");
		sleep(3);
	}
	return NULL;
}

bool nebhash_init()
{
	if (g_neb_started) return true;
	if (!nebhash_load_datasets(g_nebhash_dataset)) return false;

	// The configured chain id is a fallback; the node's getinfo RPC is
	// authoritative. db_update_coinds() has already run by this point.
	if (g_nebhash_chain_id[0])
	{
		CommonLock(&g_neb_lock);
		snprintf(g_neb_chain_id, sizeof(g_neb_chain_id), "%s", g_nebhash_chain_id);
		CommonUnlock(&g_neb_lock);
	}
	g_list_coind.Enter();
	for (CLI li = g_list_coind.first; li; li = li->next)
	{
		YAAMP_COIND *coind = (YAAMP_COIND *) li->data;
		if (!coind || coind->deleted || strcmp(coind->algo, "nebhash")) continue;
		nebhash_refresh_coind(coind);
		break;
	}
	g_list_coind.Leave();

	pthread_t thread;
	if (pthread_create(&thread, NULL, nebhash_work_thread, NULL) != 0)
	{
		stratumlog("nebhash: cannot start work-service thread\n");
		return false;
	}
	pthread_detach(thread);
	g_neb_started = true;
	return true;
}

// ---------------------------------------------------------------------------
// Template and protocol integration
// ---------------------------------------------------------------------------

bool nebhash_build_template(YAAMP_COIND *coind, YAAMP_JOB_TEMPLATE *templ)
{
	if (!coind || !templ) return false;
	nebhash_refresh_coind(coind);

	CommonLock(&g_neb_lock);
	shared_ptr<NEB_JOB> job = g_neb_current;
	CommonUnlock(&g_neb_lock);
	if (!job || !job->model) return false;

	templ->created = time(NULL);
	templ->height = (int) job->height;
	templ->txcount = (int) job->tx_count;
	templ->neb_node_id = job->node_id;
	templ->neb_slot = job->slot;
	templ->neb_clean = job->clean;
	templ->neb_version = job->version;
	templ->neb_time_start = job->time_start;
	templ->neb_time_window = job->time_window;
	templ->neb_bits = job->bits;
	templ->neb_tx_count = job->tx_count;
	templ->neb_expiry = job->expiry;
	templ->neb_extranonce_size = job->extranonce_size;

	bytes_to_hex(job->target, 32, templ->neb_target);
	bytes_to_hex(job->share_target, 32, templ->neb_share_target);
	bytes_to_hex(job->shares_root, 32, templ->neb_shares_root);
	bytes_to_hex(job->weights_hash, 32, templ->neb_weights_hash);
	bytes_to_hex(job->parent, 32, templ->prevhash_hex);
	bytes_to_hex(job->parent, 32, templ->prevhash_be);
	bytes_to_hex(job->coinbase_prefix.data(), job->coinbase_prefix.size(), templ->coinb1);
	bytes_to_hex(job->coinbase_suffix.data(), job->coinbase_suffix.size(), templ->coinb2);

	snprintf(templ->version, sizeof(templ->version), "%08x", job->version);
	snprintf(templ->nbits, sizeof(templ->nbits), "%08x", job->bits);
	snprintf(templ->ntime, sizeof(templ->ntime), "%u", job->time_start);

	snprintf(templ->flags, sizeof(templ->flags), "neb:%llu", (unsigned long long) job->node_id);
	snprintf(templ->neb_weights_hex, sizeof(templ->neb_weights_hex), "%s", job->weights_hex.c_str());
	templ->neb_branch.clear();
	for (size_t i = 0; i < job->branch.size(); i++)
	{
		char branchhex[65];
		bytes_to_hex(job->branch[i].data(), 32, branchhex);
		templ->neb_branch.push_back(branchhex);
	}
	return true;
}

void nebhash_get_chain_id(char hex[65])
{
	CommonLock(&g_neb_lock);
	snprintf(hex, 65, "%s", g_neb_chain_id);
	CommonUnlock(&g_neb_lock);
}

void nebhash_subscribe_result(YAAMP_CLIENT *client, char *out, size_t size)
{
	char chain[65] = { 0 }, dataset[65] = { 0 };
	nebhash_get_chain_id(chain);
	CommonLock(&g_neb_lock);
	bytes_to_hex(g_neb_dataset_hash, 32, dataset);
	CommonUnlock(&g_neb_lock);

	int en2 = nebhash_extranonce2_size();

	snprintf(out, size,
		"{\"session\":\"%llx\",\"extranonce1\":\"%s\",\"extranonce2_size\":%d,"
		"\"chain_id\":\"%s\",\"dataset_hash\":\"%s\"}",
		(unsigned long long) client->neb_session, client->extranonce1, en2, chain, dataset);
}

static bool nebhash_client_has_model(YAAMP_CLIENT *client, const char *hash)
{
	for (int i = 0; i < client->neb_models_count; i++)
		if (!strcmp(client->neb_models[i], hash)) return true;
	return false;
}

static void nebhash_client_add_model(YAAMP_CLIENT *client, const char *hash)
{
	if (nebhash_client_has_model(client, hash)) return;
	if (client->neb_models_count >= 8)
	{
		for (int i = 1; i < 8; i++)
			memcpy(client->neb_models[i-1], client->neb_models[i], 65);
		client->neb_models_count = 7;
	}
	snprintf(client->neb_models[client->neb_models_count++], 65, "%s", hash);
}

static void nebhash_send_model(YAAMP_CLIENT *client, YAAMP_JOB_TEMPLATE *templ)
{
	if (nebhash_client_has_model(client, templ->neb_weights_hash)) return;
	client_call(client, "mining.set_model", "[%d,\"%s\",\"%s\"]",
		templ->neb_slot, templ->neb_weights_hash, templ->neb_weights_hex);
	nebhash_client_add_model(client, templ->neb_weights_hash);
}

string nebhash_job_mining_notify(YAAMP_JOB *job, YAAMP_CLIENT *client)
{
	YAAMP_JOB_TEMPLATE *templ = job->templ;
	bool need_target = client && client->neb_first_job;
	if (client)
	{
		// Reference order for every pushed job: target, model, then notify.
		// The session is seeded with the start target at subscribe so submits
		// can be checked before the first job, but the reference pool still
		// publishes it as an explicit set_target on the first pushed job.
		uint8_t target[32];
		if (nebhash_template_target(templ, client, target) &&
			(need_target || memcmp(client->share_target.begin(), target, 32) != 0))
			nebhash_client_set_target_bytes(client, target);
		nebhash_send_model(client, templ);
	}

	// The reference pool always marks the first job after login as clean so
	// miners drop any stale job they may still hold from a previous session.
	bool clean = templ->neb_clean || (client && client->neb_first_job);
	if (client) client->neb_first_job = false;

	string branch = "[";
	for (size_t i = 0; i < templ->neb_branch.size(); i++)
	{
		if (i) branch += ",";
		branch += "\"" + templ->neb_branch[i] + "\"";
	}
	branch += "]";

	char notification[512];
	int n = snprintf(notification, sizeof(notification),
		"{\"id\":null,\"method\":\"mining.notify\",\"params\":[{"
		"\"job_id\":\"%x\",\"clean\":%s,\"version\":%u,"
		"\"parent\":\"%s\",\"time_start\":%u,\"time_window\":%u,"
		"\"bits\":%u,\"target\":\"%s\",\"share_target\":\"%s\","
		"\"coinbase_prefix\":\"%s\",\"coinbase_suffix\":\"%s\","
		"\"branch\":%s,\"tx_count\":%u,\"shares_root\":\"%s\","
		"\"height\":%u,\"slot\":%u,\"weights_hash\":\"%s\",\"expiry\":%u"
		"}]}\n",
		job->id, clean ? "true" : "false", templ->neb_version,
		templ->prevhash_hex, templ->neb_time_start, templ->neb_time_window,
		templ->neb_bits, templ->neb_target, templ->neb_share_target,
		templ->coinb1, templ->coinb2, branch.c_str(), templ->neb_tx_count,
		templ->neb_shares_root, (unsigned) templ->height, templ->neb_slot,
		templ->neb_weights_hash, templ->neb_expiry);
	if (n < 0) return string();

	// Build the full line once so a large merkle branch or coinbase cannot be
	// truncated by yiimp's fixed-size job-send buffers.
	string out;
	out.reserve((size_t) n + 1);
	char *buf = (char *) malloc((size_t) n + 1);
	if (!buf) return string();
	snprintf(buf, (size_t) n + 1,
		"{\"id\":null,\"method\":\"mining.notify\",\"params\":[{"
		"\"job_id\":\"%x\",\"clean\":%s,\"version\":%u,"
		"\"parent\":\"%s\",\"time_start\":%u,\"time_window\":%u,"
		"\"bits\":%u,\"target\":\"%s\",\"share_target\":\"%s\","
		"\"coinbase_prefix\":\"%s\",\"coinbase_suffix\":\"%s\","
		"\"branch\":%s,\"tx_count\":%u,\"shares_root\":\"%s\","
		"\"height\":%u,\"slot\":%u,\"weights_hash\":\"%s\",\"expiry\":%u"
		"}]}\n",
		job->id, clean ? "true" : "false", templ->neb_version,
		templ->prevhash_hex, templ->neb_time_start, templ->neb_time_window,
		templ->neb_bits, templ->neb_target, templ->neb_share_target,
		templ->coinb1, templ->coinb2, branch.c_str(), templ->neb_tx_count,
		templ->neb_shares_root, (unsigned) templ->height, templ->neb_slot,
		templ->neb_weights_hash, templ->neb_expiry);
	out.assign(buf);
	free(buf);
	return out;
}

void nebhash_job_mining_notify_buffer(YAAMP_JOB *job, YAAMP_CLIENT *client, char *buffer, size_t size)
{
	string out = nebhash_job_mining_notify(job, client);
	if (!buffer || !size) return;
	snprintf(buffer, size, "%s", out.c_str());
}

static bool json_number_or_string(json_value *v, uint64_t *out)
{
	if (!v) return false;
	if (v->type == json_integer)
	{
		if (v->u.integer < 0) return false;
		*out = (uint64_t) v->u.integer;
		return true;
	}
	if (v->type == json_double)
	{
		double d = v->u.dbl;
		if (!isfinite(d) || d < 0.0 || d > (double) UINT64_MAX || floor(d) != d)
			return false;
		*out = (uint64_t) d;
		return true;
	}
	if (v->type == json_string && v->u.string.ptr && *v->u.string.ptr)
	{
		const char *s = v->u.string.ptr;
		if (*s == '-' || *s == '+') return false;
		for (const char *p = s; *p; p++)
			if (*p < '0' || *p > '9') return false;
		char *end = NULL;
		errno = 0;
		unsigned long long n = strtoull(s, &end, 10);
		if (errno == ERANGE || end == s || *end) return false;
		*out = (uint64_t) n;
		return true;
	}
	return false;
}

static void nebhash_submit_chain_share(YAAMP_COIND *coind, const NEB_JOB &job,
	const uint8_t header[80], uint32_t ntime, uint32_t nonce)
{
	uint8_t record[40];
	memcpy(record, header + 36, 32);
	write_le32(record + 32, ntime);
	write_le32(record + 36, nonce);
	char recordhex[81], parenth[65];
	bytes_to_hex(record, 40, recordhex);
	bytes_to_hex(job.parent, 32, parenth);

	char params[512];
	snprintf(params, sizeof(params), "[\"%s\",[\"%s\"]]", parenth, recordhex);
	json_value *json = rpc_call(&coind->rpc, "submitshares", params, coind);
	if (!json)
	{
		debuglog("nebhash: submitshares RPC failed\n");
		return;
	}
	json_value *result = json_get_object(json, "result");
	if (result && result->type == json_integer)
		debuglog("nebhash: submitshares kept %lld new record(s)\n", (long long) result->u.integer);
	json_value_free(json);
}

static bool nebhash_submit_block(YAAMP_COIND *coind, const shared_ptr<NEB_JOB> &job,
	const uint8_t *extranonce, size_t extranonce_len, uint32_t ntime, uint32_t nonce,
	const NEB_PENDING_BLOCK *pending)
{
	vector<uint8_t> payload;
	uint8_t le64[8];
	for (int i = 0; i < 8; i++) le64[i] = (uint8_t) (job->node_id >> (8*i));
	payload.insert(payload.end(), le64, le64 + 8);
	write_varint(payload, extranonce_len);
	payload.insert(payload.end(), extranonce, extranonce + extranonce_len);

	uint8_t le[4];
	write_le32(le, ntime);
	payload.insert(payload.end(), le, le + 4);
	write_le32(le, nonce);
	payload.insert(payload.end(), le, le + 4);

	// Queue the candidate in the same critical section as the send: the node
	// reader pops the FIFO on Result, and it must not see an empty queue before
	// a submission that is already on the wire has been recorded.
	CommonLock(&g_neb_sock_lock);
	CommonLock(&g_neb_lock);
	int sock = g_neb_node_sock;
	bool sent = sock >= 0 && neb_send_frame(sock, NEB_MSG_SUBMIT, payload);
	if (sent && pending) g_neb_pending_blocks.push_back(*pending);
	CommonUnlock(&g_neb_lock);
	CommonUnlock(&g_neb_sock_lock);

	if (!sent) debuglog("nebhash: no work-service socket for block submission\n");
	(void) coind;
	return sent;
}

bool nebhash_submit(YAAMP_CLIENT *client, json_value *json_params)
{
	if (!json_params || json_params->type != json_array || json_params->u.array.length < 5)
	{
		client->submit_bad++;
		nebhash_send_error(client,20, "Bad submit");
		return true;
	}

	json_value **v = json_params->u.array.values;
	if (!v[0] || v[0]->type != json_string || !v[1] || v[1]->type != json_string ||
		!v[2] || v[2]->type != json_string)
	{
		client->submit_bad++;
		nebhash_send_error(client,20, "Bad submit");
		return true;
	}

	// Reference check order: session, then authorized login, then rate limit.
	if (!client->neb_subscribed || !g_list_client.Find(client))
	{
		nebhash_send_error(client,25, "Session not subscribed");
		client->submit_bad++;
		return true;
	}
	if (!nebhash_login_authorized(client, v[0]->u.string.ptr))
	{
		nebhash_send_error(client,24, "Login not authorized");
		client->submit_bad++;
		return true;
	}
	if (!nebhash_submit_rate_ok(client))
	{
		nebhash_send_error(client,20, "Too many submits");
		return nebhash_close_client_session(client);
	}

	uint64_t ntime64 = 0, nonce64 = 0;
	if (!json_number_or_string(v[3], &ntime64) || !json_number_or_string(v[4], &nonce64))
	{
		client->submit_bad++;
		nebhash_send_error(client,20, "Bad submit");
		return true;
	}

	const char *jobid_str = v[1]->u.string.ptr;
	if (strlen(jobid_str) > 16 || !*jobid_str)
	{
		client->submit_bad++;
		nebhash_send_error(client,21, "Invalid job id");
		return true;
	}
	for (const char *p = jobid_str; *p; p++)
		if (!isxdigit((unsigned char) *p))
		{
			client->submit_bad++;
			nebhash_send_error(client,21, "Invalid job id");
			return true;
		}
	char *jobid_end = NULL;
	errno = 0;
	unsigned long parsed_jobid = strtoul(jobid_str, &jobid_end, 16);
	if (errno == ERANGE || !jobid_end || *jobid_end || parsed_jobid > INT_MAX)
	{
		client->submit_bad++;
		nebhash_send_error(client,21, "Invalid job id");
		return true;
	}
	int jobid = (int) parsed_jobid;
	YAAMP_JOB *job = (YAAMP_JOB *) object_find(&g_list_job, jobid, true);
	if (!job)
	{
		client->submit_bad++;
		nebhash_send_error(client,21, "Job not found or past its expiry");
		return true;
	}
	YAAMP_JOB_TEMPLATE *templ = job->templ;
	if (!templ || !templ->neb_node_id || !templ->neb_weights_hex[0] || !job->coind)
	{
		object_unlock(job);
		nebhash_send_error(client,21, "Invalid job");
		return true;
	}
	if (templ->neb_expiry && (uint64_t) time(NULL) > templ->neb_expiry)
	{
		object_unlock(job);
		nebhash_send_error(client,21, "Job not found or past its expiry");
		client->submit_bad++;
		return true;
	}

	uint32_t ntime = (uint32_t) ntime64;
	uint32_t nonce = (uint32_t) nonce64;
	const char *en2hex = v[2]->u.string.ptr;
	size_t en2len = strlen(en2hex) / 2;
	if ((strlen(en2hex) & 1) || en2len > 32 ||
		en2len != (size_t) (templ->neb_extranonce_size - 4) ||
		templ->neb_extranonce_size < 8)
	{
		object_unlock(job);
		nebhash_send_error(client,27, "Extranonce2 of the wrong length");
		client->submit_bad++;
		return true;
	}
	if ((uint64_t) ntime < (uint64_t) templ->neb_time_start ||
		(uint64_t) ntime > (uint64_t) templ->neb_time_start + (uint64_t) templ->neb_time_window)
	{
		object_unlock(job);
		nebhash_send_error(client,26, "Time outside the window of the job");
		client->submit_bad++;
		return true;
	}

	uint8_t en1[8], en2[32];
	if (!parse_hex_bytes(client->extranonce1, 4, en1) ||
		!parse_hex_bytes(en2hex, en2len, en2))
	{
		object_unlock(job);
		nebhash_send_error(client,27, "Invalid extranonce");
		client->submit_bad++;
		return true;
	}
	uint8_t extranonce[36];
	memcpy(extranonce, en1, 4);
	memcpy(extranonce + 4, en2, en2len);
	size_t extranonce_len = 4 + en2len;

	CommonLock(&g_neb_lock);
	shared_ptr<NEB_MODEL> model = g_neb_models[string(templ->neb_weights_hash)];
	CommonUnlock(&g_neb_lock);
	if (!model)
	{
		object_unlock(job);
		nebhash_send_error(client,20, "Model not available");
		return true;
	}

	// Rebuild the immutable part of the job from the template. Templates are
	// self-contained so late shares after a job change still verify.
	shared_ptr<NEB_JOB> nbjob(new NEB_JOB());
	nbjob->node_id = templ->neb_node_id;
	nbjob->version = templ->neb_version;
	nbjob->time_start = templ->neb_time_start;
	nbjob->time_window = templ->neb_time_window;
	nbjob->bits = templ->neb_bits;
	nbjob->tx_count = templ->neb_tx_count;
	nbjob->expiry = templ->neb_expiry;
	nbjob->slot = (uint8_t) templ->neb_slot;
	nbjob->extranonce_size = templ->neb_extranonce_size;
	nbjob->model = model;
	parse_hex_bytes(templ->prevhash_hex, 32, nbjob->parent);
	parse_hex_bytes(templ->neb_target, 32, nbjob->target);
	parse_hex_bytes(templ->neb_share_target, 32, nbjob->share_target);
	parse_hex_bytes(templ->neb_shares_root, 32, nbjob->shares_root);
	nbjob->coinbase_prefix.resize(strlen(templ->coinb1)/2);
	if (!nbjob->coinbase_prefix.empty())
		parse_hex_bytes(templ->coinb1, nbjob->coinbase_prefix.size(), nbjob->coinbase_prefix.data());
	nbjob->coinbase_suffix.resize(strlen(templ->coinb2)/2);
	if (!nbjob->coinbase_suffix.empty())
		parse_hex_bytes(templ->coinb2, nbjob->coinbase_suffix.size(), nbjob->coinbase_suffix.data());
	for (size_t i = 0; i < templ->neb_branch.size(); i++)
	{
		uint8_t b[32];
		if (parse_hex_bytes(templ->neb_branch[i].c_str(), 32, b))
			nbjob->branch.push_back(vector<uint8_t>(b, b + 32));
	}

	uint8_t header[80], key[32], ticket[32];
	nebhash_build_header(*nbjob, extranonce, extranonce_len, ntime, nonce, header);

	// Duplicate detection uses the complete 80-byte header keyed by parent and
	// happens before the expensive ticket evaluation, as in the reference pool.
	char parenthex[65];
	bytes_to_hex(nbjob->parent, 32, parenthex);
	vector<uint8_t> header_key(header, header + 80);
	CommonLock(&g_neb_lock);
	bool duplicate = !g_neb_seen[string(parenthex)].insert(header_key).second;
	CommonUnlock(&g_neb_lock);
	if (duplicate)
	{
		// A retry is neither an accepted nor a rejected share in the
		// reference pool; do not feed it into yiimp's share accounting.
		object_unlock(job);
		nebhash_send_error(client,22, "Duplicate share");
		return true;
	}

	neb_blake3_hash(header, 80, key);
	if (!nebhash_evaluate_key(*nbjob, key, ticket))
	{
		object_unlock(job);
		nebhash_send_error(client,20, "No verification context");
		return true;
	}

	char ticket_hex[65], ntimehex[16], noncehex[16];
	bytes_to_hex(ticket, 32, ticket_hex);
	snprintf(ntimehex, sizeof(ntimehex), "%08x", ntime);
	snprintf(noncehex, sizeof(noncehex), "%08x", nonce);

	uint8_t target_le[32];
	nebhash_submit_target(client, templ, target_le);
	if (!nebhash_le_lte(ticket, target_le))
	{
		object_unlock(job);
		nebhash_send_error(client,23, "Low difficulty share");
		client->submit_bad++;
		if (nebhash_note_bad_submit(client))
			return nebhash_close_client_session(client);
		return true;
	}

	// Dev-fee miners authorize several neb1 logins and submit a fraction of
	// their work under the second one. Switch account/worker attribution for
	// this share, then restore the session's primary login before returning.
	int saved_userid = client->userid;
	int saved_workerid = client->workerid;
	int saved_coinid = client->coinid;
	bool saved_solo = client->solo;
	char saved_username[1088], saved_worker[1088];
	snprintf(saved_username, sizeof(saved_username), "%s", client->username);
	snprintf(saved_worker, sizeof(saved_worker), "%s", client->worker);
	const char *submit_login = v[0]->u.string.ptr;
	if (!nebhash_select_login(client, submit_login))
	{
		object_unlock(job);
		nebhash_send_error(client, 24, "Login not authorized");
		client->submit_bad++;
		return true;
	}
	bool restore_login = strcmp(submit_login, client->neb_logins[0]) != 0;

	bool chain_share = nebhash_le_lte(ticket, nbjob->share_target);
	bool block = nebhash_le_lte(ticket, nbjob->target);

	if (chain_share)
		nebhash_submit_chain_share(job->coind, *nbjob, header, ntime, nonce);

	if (block)
	{
		char blockhash[65];
		bytes_to_hex(key, 32, blockhash);
		NEB_PENDING_BLOCK pending;
		memset(&pending, 0, sizeof(pending));
		pending.node_id = nbjob->node_id;
		pending.height = templ->height;
		memcpy(pending.id, key, 32);
		pending.userid = client->userid;
		pending.workerid = client->workerid;
		pending.coinid = job->coind->id;
		pending.network_diff = nebhash_target_to_diff(nbjob->target);
		pending.share_diff = nebhash_ticket_to_diff(ticket);
		pending.solo = client->solo;
		pending.client_id = client->id;
		snprintf(pending.job_id, sizeof(pending.job_id), "%x", job->id);
		snprintf(pending.extranonce2, sizeof(pending.extranonce2), "%s", en2hex);
		snprintf(pending.ntime, sizeof(pending.ntime), "%s", ntimehex);
		snprintf(pending.nonce, sizeof(pending.nonce), "%s", noncehex);

		// Credit the finder only after the node returns an accepted Result for
		// this submission. Results are consumed in FIFO order by the reader.
		bool sent = nebhash_submit_block(job->coind, nbjob, extranonce, extranonce_len,
			ntime, nonce, &pending);
		stratumlog("*** BLOCK candidate %s height %u id %s by %s (sent to node: %d)\n",
			job->coind->name, templ->height, blockhash, client->sock->ip, sent ? 1 : 0);
		job_signal();
	}

	double share_diff = nebhash_ticket_to_diff(ticket);
	stratumlog("client %d on job %d sent diff %0.3f ticket %s\n",
		client->userid, job->id, share_diff, ticket_hex);

	client_record_difficulty(client);
	client->submit_bad = 0;
	client->shares++;

	share_add(client, job, true, (char *) en2hex, ntimehex, noncehex,
		share_diff, 0, templ->height);

	if (restore_login)
	{
		client->userid = saved_userid;
		client->workerid = saved_workerid;
		client->coinid = saved_coinid;
		client->solo = saved_solo;
		neb_copy_field(client->username, sizeof(client->username), saved_username);
		neb_copy_field(client->worker, sizeof(client->worker), saved_worker);
	}

	// This is the reference pool's vardiff step. It returns after the job lock
	// is released so the target change can re-push the current notification.
	bool repush = nebhash_vardiff(client, job);
	int repush_jobid = job->id;
	object_unlock(job);

	if (repush && client->neb_vardiff_repush)
	{
		client->neb_vardiff_repush = false;
		job_send_jobid(client, repush_jobid);
	}

	client_send_result(client, "true");
	return true;
}

// ---------------------------------------------------------------------------
// bech32m wallet address validation (neb1..., 32-byte lock_data)
// ---------------------------------------------------------------------------

static int neb_bech32_char(char c)
{
	static const char *charset = "qpzry9x8gf2tvdw0s3jn54khce6mua7l";
	for (int i = 0; i < 32; i++) if (charset[i] == c) return i;
	return -1;
}

static uint32_t neb_bech32_polymod(const vector<uint8_t> &values)
{
	static const uint32_t gen[5] = { 0x3b6a57b2u, 0x26508e6du, 0x1ea119fau, 0x3d4233ddu, 0x2a1462b3u };
	uint32_t chk = 1;
	for (uint8_t value : values)
	{
		uint32_t top = chk >> 25;
		chk = ((chk & 0x01ffffffu) << 5) ^ value;
		for (int i = 0; i < 5; i++)
			if ((top >> i) & 1) chk ^= gen[i];
	}
	return chk;
}

static bool neb_convert_bits(const vector<uint8_t> &in, vector<uint8_t> *out)
{
	uint32_t acc = 0;
	int bits = 0;
	for (uint8_t v : in)
	{
		if (v > 31) return false;
		acc = (acc << 5) | v;
		bits += 5;
		while (bits >= 8)
		{
			bits -= 8;
			out->push_back((uint8_t) ((acc >> bits) & 0xff));
		}
	}
	return bits == 4 && ((acc << 4) & 0xff) == 0;
}

bool nebhash_valid_address(const char *address)
{
	if (!address || !*address) return false;
	size_t len = strlen(address);
	if (len != 62) return false;

	bool lower = true, upper = true;
	for (size_t i = 0; i < len; i++)
	{
		unsigned char c = (unsigned char) address[i];
		if (c >= 'a' && c <= 'z') upper = false;
		else if (c >= 'A' && c <= 'Z') lower = false;
	}
	if (!lower && !upper) return false;
	if (strncasecmp(address, "neb1", 4)) return false;

	string text;
	for (size_t i = 0; i < len; i++)
		text.push_back((char) tolower((unsigned char) address[i]));
	if (text[3] != '1') return false;
	if (text.compare(0, 3, "neb")) return false;

	vector<uint8_t> values;
	for (size_t i = 4; i < text.size(); i++)
	{
		int v = neb_bech32_char(text[i]);
		if (v < 0) return false;
		values.push_back((uint8_t) v);
	}
	if (values.size() != 58) return false;

	vector<uint8_t> verify;
	for (const char *p = "neb"; *p; p++) verify.push_back((uint8_t) (*p >> 5));
	verify.push_back(0);
	for (const char *p = "neb"; *p; p++) verify.push_back((uint8_t) (*p & 31));
	verify.insert(verify.end(), values.begin(), values.end());
	if (neb_bech32_polymod(verify) != 0x2bc830a3u) return false;

	values.resize(values.size() - 6);
	vector<uint8_t> bytes;
	if (!neb_convert_bits(values, &bytes) || bytes.size() != 32) return false;
	return true;
}
