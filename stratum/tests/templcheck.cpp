// templcheck: build a job template from recorded wallet RPC answers and print
// the result, to catch buffer overflows on oversized RPC values.
//
//   templcheck <fixture-dir>
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
//   p2wpkh hasmasternodes txmessage isaux

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
		else { fprintf(stderr, "coind.conf: unknown key %s\n", k); exit(2); }
	}
	fclose(f);
}

static void print_field(const char *name, const char *value)
{
	printf("%s=%s\n", name, value);
}

int main(int argc, char **argv)
{
	if (argc != 2) {
		fprintf(stderr, "usage: %s <fixture-dir>\n", argv[0]);
		return 2;
	}
	snprintf(g_rpc_fixture_dir, sizeof(g_rpc_fixture_dir), "%s", argv[1]);

	snprintf(g_log_directory, sizeof(g_log_directory), "/tmp/");
	initlog("templcheck");

	YAAMP_COIND *coind = new YAAMP_COIND;
	memset(coind, 0, sizeof(YAAMP_COIND));   // as db.cpp does
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
	is_kawpow  = !strcmp(g_current_algo->name, "kawpow");
	is_firopow = !strcmp(g_current_algo->name, "firopow");
	is_phihash = !strcmp(g_current_algo->name, "phihash");
	is_meowpow = !strcmp(g_current_algo->name, "meowpow");
	is_meraki = !strcmp(g_current_algo->name, "meraki");
	coind->pos = !strcasecmp(coind->rpcencoding, "POS");

	str_overflows = 0;
	YAAMP_JOB_TEMPLATE *templ = coind_create_template(coind);
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
	snprintf(num, sizeof(num), "%zu", strlen(templ->mweb)); print_field("mweb_len", num);
	print_field("commitment", coind->commitment);
	snprintf(num, sizeof(num), "%.8f", coind->reward); print_field("reward", num);

	// a share on this job, as client_submit() builds it
	static YAAMP_JOB_VALUES submit;
	submit_values(0, &submit, templ, "01020304", "00000000", (const char *)templ->ntime, "00000000", (uint32_t)0);
	print_field("submit_coinbase", submit.coinbase);
	print_field("submit_header", submit.header_be);
	print_field("submit_hash", submit.hash_hex);
	return 0;
}
