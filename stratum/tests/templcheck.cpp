// templcheck: build a job template from recorded wallet RPC answers and print
// the result, to catch buffer overflows on oversized RPC values.
//
//   templcheck <fixture-dir>
//   templcheck --compactsize    CompactSize (varint) known answers
//   templcheck --strfail        refused writes, for the log rate limit
//
// <fixture-dir>/coind.conf    key=value settings for the coin (see below)
// <fixture-dir>/<method>.json full JSON-RPC answer per method, served by
//                             rpc_mock.cpp (getblocktemplate.json, ...)
//
// Output: one "field=value" line per template field, compared by the
// Makefile with <fixture-dir>/expected.txt (golden master).
//
// coind.conf keys: algo symbol wallet pubkey script_pubkey rpcencoding
//   charity_address charity_percent reward_mul segwit mweb usememorypool
//   p2wpkh hasmasternodes txmessage isaux usegetwork
//
// A getauxblock.json in the fixture adds one merged-mined aux coin. The
// output also has the block (and auxpow) the share would submit, built for
// the coin's protocol: standard, equihash, decred getwork or kawpow.

#include <string>
#include <strings.h>

#include "../stratum.h"

extern char g_rpc_fixture_dir[1024];

// The coinbase script embeds time(NULL); pin it so the output is a stable
// golden master. Defined in the executable, it takes precedence over libc's.
extern "C" time_t time(time_t *t)
{
	const time_t fixed = 1767225600;   // 2026-01-01 00:00:00 UTC
	if (t) *t = fixed;
	return fixed;
}
// not in a header (client_submit.cpp, coind_template.cpp)
void build_submit_values(YAAMP_JOB_VALUES *submitvalues, YAAMP_JOB_TEMPLATE *templ,
	const char *nonce1, const char *nonce2, const char *ntime, const char *nonce, uint32_t versionbits);
YAAMP_JOB_TEMPLATE *coind_create_template(YAAMP_COIND *coind);
YAAMP_JOB_TEMPLATE *decred_create_worktemplate(YAAMP_COIND *coind);
void coind_getauxblock(YAAMP_COIND *coind);
void build_submit_values_equihash(YAAMP_JOB_VALUES *submitvalues, YAAMP_JOB_TEMPLATE *templ,
	const char *nonce1, const char *ntime, const char *nonce, const char *equihash_solution);
void build_submit_values_decred(YAAMP_JOB_VALUES *submitvalues, YAAMP_JOB_TEMPLATE *templ,
	const char *nonce1, const char *nonce2, const char *ntime, const char *nonce, const char *vote, bool usegetwork);

// newer code declares build_submit_values(..., bool compute_hash = true):
// prefer that form when it exists
template <typename... A> static auto submit_values(int, A... a) -> decltype(build_submit_values(a..., true), void())
{ build_submit_values(a..., true); }
template <typename... A> static void submit_values(long, A... a) { build_submit_values(a...); }

static void set_str(char *dst, size_t cap, const char *val, const char *key)
{
	if (strlen(val) >= cap) {
		fprintf(stderr, "coind.conf: %s longer than %zu\n", key, cap - 1);
		exit(2);
	}
	strcpy(dst, val);
}

static void load_conf(const char *dir, YAAMP_COIND *coind)
{
	char path[2048];
	snprintf(path, sizeof(path), "%s/coind.conf", dir);
	FILE *f = fopen(path, "r");
	if (!f) { fprintf(stderr, "cannot open %s\n", path); exit(2); }

	char line[4096];
	while (fgets(line, sizeof(line), f)) {
		line[strcspn(line, "\r\n")] = 0;
		if (!line[0] || line[0] == '#') continue;
		char *eq = strchr(line, '=');
		if (!eq) continue;
		*eq = 0;
		const char *k = line, *v = eq + 1;

		if      (!strcmp(k, "algo"))            set_str(g_stratum_algo, sizeof(g_stratum_algo), v, k);
		else if (!strcmp(k, "symbol"))          set_str(coind->symbol, sizeof(coind->symbol), v, k);
		else if (!strcmp(k, "wallet"))          set_str(coind->wallet, sizeof(coind->wallet), v, k);
		else if (!strcmp(k, "pubkey"))          set_str(coind->pubkey, sizeof(coind->pubkey), v, k);
		else if (!strcmp(k, "script_pubkey"))   set_str(coind->script_pubkey, sizeof(coind->script_pubkey), v, k);
		else if (!strcmp(k, "rpcencoding"))     set_str(coind->rpcencoding, sizeof(coind->rpcencoding), v, k);
		else if (!strcmp(k, "charity_address")) set_str(coind->charity_address, sizeof(coind->charity_address), v, k);
		else if (!strcmp(k, "charity_percent")) coind->charity_percent = atof(v);
		else if (!strcmp(k, "reward_mul"))      coind->reward_mul = atof(v);
		else if (!strcmp(k, "segwit"))          g_stratum_segwit = atoi(v);
		else if (!strcmp(k, "mweb"))            g_stratum_mweb = atoi(v);
		else if (!strcmp(k, "usememorypool"))   coind->usememorypool = atoi(v);
		else if (!strcmp(k, "p2wpkh"))          coind->p2wpkh = atoi(v);
		else if (!strcmp(k, "hasmasternodes"))  coind->hasmasternodes = atoi(v);
		else if (!strcmp(k, "txmessage"))       coind->txmessage = atoi(v);
		else if (!strcmp(k, "isaux"))           coind->isaux = atoi(v);
		else if (!strcmp(k, "usegetwork"))      coind->usegetwork = atoi(v);
		else { fprintf(stderr, "coind.conf: unknown key %s\n", k); exit(2); }
	}
	fclose(f);
}

static void print_field(const char *name, const char *value)
{
	printf("%s=%s\n", name, value);
}

// Bitcoin CompactSize: tx and output counts, payload lengths
static int check_compactsize()
{
	static const struct { uint64_t n; const char *hex; } kat[] = {
		{0, "00"}, {252, "fc"}, {253, "fdfd00"}, {300, "fd2c01"}, {0xffff, "fdffff"},
		{0x10000, "fe00000100"}, {0xffffffffULL, "feffffffff"},
		{0x100000000ULL, "ff0000000001000000"}, {0x0123456789abcdefULL, "ffefcdab8967452301"},
	};
	int failed = 0;
	for (const auto &k : kat) {
		char hex[COMPACTSIZE_HEX];
		ser_compactsize(k.n, hex);
		if (strcmp(hex, k.hex)) {
			printf("compactsize %llu: got %s, want %s\n", (unsigned long long) k.n, hex, k.hex);
			failed++;
		}
	}
	printf("compactsize: %d checked, %d failed\n", (int) (sizeof(kat) / sizeof(kat[0])), failed);
	return failed ? 1 : 0;
}

int main(int argc, char **argv)
{
	if (argc == 2 && !strcmp(argv[1], "--compactsize"))
		return check_compactsize();
	// 25 refused writes: the Makefile expects 10 logged lines
	if (argc == 2 && !strcmp(argv[1], "--strfail")) {
		char small[4];
		for (int i = 0; i < 25; i++) STRSET(small, "too long");
		return str_overflows == 25 ? 0 : 1;
	}
	if (argc != 2) {
		fprintf(stderr, "usage: %s <fixture-dir> | --compactsize\n", argv[0]);
		return 2;
	}
	snprintf(g_rpc_fixture_dir, sizeof(g_rpc_fixture_dir), "%s", argv[1]);

	snprintf(g_log_directory, sizeof(g_log_directory), "/tmp/");
	initlog("templcheck");

	YAAMP_COIND *coind = new YAAMP_COIND();
	coind->id = 1;
	coind->reward_mul = 1;
	strcpy(coind->rpcencoding, "POW");
	strcpy(g_stratum_algo, "sha256");
	yaamp_create_mutex(&coind->aux_mutex);
	coind->aux.coind = coind;

	load_conf(argv[1], coind);

	// same algo setup as main() in stratum.cpp
	g_current_algo = stratum_find_algo(g_stratum_algo);
	if (!g_current_algo) { fprintf(stderr, "unknown algo %s\n", g_stratum_algo); return 2; }
	stratum_set_algo_flags(g_current_algo);
	coind->pos = !strcasecmp(coind->rpcencoding, "POS");
	bool is_decred = !strcmp(coind->rpcencoding, "DCR");

	// merged mining: one aux coin, its getauxblock answer from the fixture
	char auxpath[2048];
	snprintf(auxpath, sizeof(auxpath), "%s/getauxblock.json", argv[1]);
	if (access(auxpath, R_OK) == 0) {
		YAAMP_COIND *aux = new YAAMP_COIND();
		aux->id = 2;
		strcpy(aux->name, "TAUX");
		strcpy(aux->symbol, "TAUX");
		aux->isaux = aux->enable = aux->auto_ready = true;
		aux->height = 1;
		aux->difficulty = 1;
		yaamp_create_mutex(&aux->aux_mutex);
		aux->aux.coind = aux;
		coind_getauxblock(aux);
		g_list_coind.AddTail(aux);
	}

	str_overflows = 0;
	// as coind_create_job(): decred getwork has its own template
	YAAMP_JOB_TEMPLATE *templ = coind->usegetwork && is_decred ?
		decred_create_worktemplate(coind) : coind_create_template(coind);
	if (templ && str_overflows) {
		printf("template=REJECTED\n");   // coind_create_job() drops it
		return 0;
	}
	if (!templ) {
		printf("template=NULL\n");
		return 0;
	}

	char num[64];
	print_field("version", templ->version);
	print_field("nbits", templ->nbits);
	print_field("ntime", templ->ntime);
	snprintf(num, sizeof(num), "%d", templ->height); print_field("height", num);
	snprintf(num, sizeof(num), "%lld", (long long)templ->value); print_field("value", num);
	print_field("prevhash_hex", templ->prevhash_hex);
	print_field("prevhash_be", templ->prevhash_be);
	snprintf(num, sizeof(num), "%d", templ->txcount); print_field("txcount", num);
	print_field("txmerkles", templ->txmerkles);
	snprintf(num, sizeof(num), "%d", templ->has_segwit_txs); print_field("has_segwit_txs", num);
	print_field("flags", templ->flags);
	print_field("coinb1", templ->coinb1);
	print_field("coinb2", templ->coinb2);
	print_field("coinbase", templ->coinbase);
	print_field("merkleroot", templ->merkleroot);
	print_field("extradata_hex", templ->extradata_hex);
	print_field("claim_hex", templ->claim_hex);
	snprintf(num, sizeof(num), "%zu", templ->mweb ? strlen(templ->mweb) : 0); print_field("mweb_len", num);
	print_field("commitment", coind->commitment);
	snprintf(num, sizeof(num), "%.8f", coind->reward); print_field("reward", num);

	if (is_progpow) {
		print_field("header_hash", templ->header_hash.ToString().c_str());
		print_field("header_seed", templ->header_seed.ToString().c_str());
	}

	// a share on this job, as client_submit() builds it
	static YAAMP_JOB_VALUES submit;
	if (is_equihash) {
		// solution: CompactSize 1344 + a fixed pattern (not a valid solution)
		static char solution[2 * 1347 + 1];
		strcpy(solution, "fd4005");
		for (int i = 0; i < 2 * 1344; i++) solution[6 + i] = "0123456789abcdef"[i % 16];
		solution[6 + 2 * 1344] = 0;
		build_submit_values_equihash(&submit, templ, "01020304", templ->ntime,
			"00000000000000000000000000000000000000000000000000000000", solution);
	}
	else if (is_decred)
		build_submit_values_decred(&submit, templ, "01020304", "00000000", templ->ntime, "00000000", "", coind->usegetwork);
	else
		submit_values(0, &submit, templ, "01020304", "00000000", (const char *)templ->ntime, "00000000", (uint32_t)0);
	print_field("submit_coinbase", submit.coinbase);
	print_field("submit_header", submit.header_be);
	print_field("submit_hash", submit.hash_hex);

	// what a block or aux block found by that share would submit
	if (is_progpow && !is_firopow && !is_meraki)
		print_field("submit_block", kawpow_build_block(templ, "0123456789abcdef",
			"00112233445566778899aabbccddeeff00112233445566778899aabbccddeeff").c_str());
	else if (!is_progpow)
		print_field("submit_block", client_build_block(coind, templ, &submit).c_str());
	for (int i = 0; i < templ->auxs_size; i++)
		if (templ->auxs[i]) {
			snprintf(num, sizeof(num), "submit_auxpow_%d", i);
			print_field(num, client_build_auxpow(templ, &submit, templ->auxs[i]).c_str());
		}
	return 0;
}
