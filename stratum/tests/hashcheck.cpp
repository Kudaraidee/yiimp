// hashcheck: run every hash function of g_algos[] on fixed inputs and compare
// with recorded vectors (stratum rework, phase 0 safety net).
//
//   hashcheck --generate [--algo NAME]   print vectors to stdout
//   hashcheck --check FILE [--algo NAME] compare with FILE, exit 1 on mismatch
//   hashcheck --reference FILE           check known answers (took over from the
//                                        removed in-binary validate_hashfunctions())
//
// The vector file is a golden master of today's behaviour (any change is a
// regression or needs a conscious re-generate). The reference file holds known
// answers from outside this code base (chain blocks, pool-accepted shares,
// miner KATs):
//   <algo> <order> <hash-hex> <header-hex>   (# comments allowed)
// order: be  = block hash as explorers show it (byte-reversed output)
//        raw = the first 32 output bytes as the hash function writes them
//
// Each algorithm runs in its own child process, so a crash or hang is
// recorded as a status instead of stopping the run. Per input we record:
//   status  OK | SIGNAL<n> | TIMEOUT | NONDET (two runs differ; no hash recorded)
//   out     number of output bytes the function actually wrote (determined by
//           running once on a 0x00- and once on a 0xFF-filled output buffer)
//   hash    first 32 output bytes, hex
//
// Line format:  <algo> <input-id> <status> <out> <hash>

#include <map>
#include <set>
#include <string>
#include <vector>

#include "../stratum.h"

#include <sys/wait.h>
#include <signal.h>
#include <unistd.h>

extern YAAMP_ALGO g_algos[];

static const int TIMEOUT_SECONDS = 120;
static const size_t BUF_SIZE = 64 * 1024;
static const size_t CMP_BYTES = 32;

struct TestInput {
	const char *id;
	uint32_t len;
	unsigned char data[256];
};

static std::vector<TestInput> make_inputs()
{
	std::vector<TestInput> v;
	TestInput t;

	// 80 zero bytes
	memset(&t, 0, sizeof(t)); t.id = "zero80"; t.len = 80;
	v.push_back(t);

	// Bitcoin block 835622 header (same as validate_hashfunctions)
	memset(&t, 0, sizeof(t)); t.id = "btc80"; t.len = 80;
	binlify(t.data, "00003e20f05d18db34a53d848ab906c5924e032a6edb0b1a74fa000000000000000000002f02bf9836314afed25ef4aa258c596995da0ae656dab671cf3abd1387ab1e70ebabfb65595a03174763046f");
	v.push_back(t);

	// counting pattern, 80 and 144 bytes (algos that honour len, e.g. equihash-style headers)
	memset(&t, 0, sizeof(t)); t.id = "pat80"; t.len = 80;
	for (int i = 0; i < 80; i++) t.data[i] = (unsigned char)(i * 7 + 3);
	v.push_back(t);

	memset(&t, 0, sizeof(t)); t.id = "pat144"; t.len = 144;
	for (int i = 0; i < 144; i++) t.data[i] = (unsigned char)(i * 13 + 1);
	v.push_back(t);

	return v;
}

static void to_hex(const unsigned char *in, size_t len, char *out)
{
	static const char *digits = "0123456789abcdef";
	for (size_t i = 0; i < len; i++) {
		out[i*2]   = digits[in[i] >> 4];
		out[i*2+1] = digits[in[i] & 15];
	}
	out[len*2] = 0;
}

static void run_hash(YAAMP_HASH_FUNCTION fn, const TestInput &in, unsigned char fill, unsigned char *out)
{
	static unsigned char inbuf[BUF_SIZE];
	memset(inbuf, 0, BUF_SIZE);
	memcpy(inbuf, in.data, sizeof(in.data));
	memset(out, fill, BUF_SIZE);
	fn((const char *)inbuf, (char *)out, in.len);
}

// child: compute all inputs for one algo, write result lines to fd
static void child_run(const YAAMP_ALGO *algo, const std::vector<TestInput> &inputs, int fd)
{
	alarm(TIMEOUT_SECONDS);
	FILE *f = fdopen(fd, "w");
	unsigned char *a = (unsigned char *)malloc(BUF_SIZE);
	unsigned char *b = (unsigned char *)malloc(BUF_SIZE);
	unsigned char *c = (unsigned char *)malloc(BUF_SIZE);

	for (const auto &in : inputs) {
		run_hash(algo->hash_function, in, 0x00, a);
		run_hash(algo->hash_function, in, 0xff, b);
		run_hash(algo->hash_function, in, 0x00, c);

		size_t written = 0;
		for (size_t i = 0; i < BUF_SIZE; i++)
			if (a[i] == b[i]) written = i + 1;

		// a result that differs between runs (uninitialised memory, stack
		// overflow) cannot be compared; record only the status, stably
		if (memcmp(a, c, BUF_SIZE)) {
			fprintf(f, "%s %s NONDET - -\n", algo->name, in.id);
			fflush(f);
			continue;
		}
		char hex[CMP_BYTES*2 + 1];
		to_hex(a, CMP_BYTES, hex);
		fprintf(f, "%s %s OK %zu %s\n", algo->name, in.id, written, hex);
		fflush(f);
	}
	fclose(f);
	_exit(0);
}

// run one algo in a child; returns result lines (one per input)
static std::vector<std::string> run_algo(const YAAMP_ALGO *algo, const std::vector<TestInput> &inputs)
{
	int p[2];
	if (pipe(p)) { perror("pipe"); exit(2); }

	pid_t pid = fork();
	if (pid < 0) { perror("fork"); exit(2); }
	if (pid == 0) { close(p[0]); child_run(algo, inputs, p[1]); }

	close(p[1]);
	std::string data;
	char buf[4096];
	ssize_t n;
	while ((n = read(p[0], buf, sizeof(buf))) > 0) data.append(buf, n);
	close(p[0]);

	int st = 0;
	waitpid(pid, &st, 0);

	std::vector<std::string> lines;
	size_t pos = 0, nl;
	while ((nl = data.find('\n', pos)) != std::string::npos) {
		lines.push_back(data.substr(pos, nl - pos));
		pos = nl + 1;
	}

	// inputs the child did not finish get the termination status
	if (lines.size() < inputs.size()) {
		char status[32];
		if (WIFSIGNALED(st) && WTERMSIG(st) == SIGALRM) snprintf(status, sizeof(status), "TIMEOUT");
		else if (WIFSIGNALED(st)) snprintf(status, sizeof(status), "SIGNAL%d", WTERMSIG(st));
		else snprintf(status, sizeof(status), "EXIT%d", WEXITSTATUS(st));
		for (size_t i = lines.size(); i < inputs.size(); i++)
			lines.push_back(std::string(algo->name) + " " + inputs[i].id + " " + status + " 0 -");
	}
	return lines;
}

// Reference check for one line, in a child (crash-safe).
// Returns 0 = match, 1 = mismatch/crash, 2 = unknown algo.
static int run_reference(const char *algo_name, bool be_order, const char *expected, const char *header_hex)
{
	YAAMP_ALGO *algo = stratum_find_algo(algo_name);
	if (!algo || !algo->hash_function) {
		printf("REF   %-14s unknown algo\n", algo_name);
		return 2;
	}
	size_t hexlen = strlen(header_hex);
	if (hexlen % 2 || hexlen / 2 > BUF_SIZE / 2) {
		printf("REF   %-14s bad header hex\n", algo_name);
		return 1;
	}

	int p[2];
	if (pipe(p)) { perror("pipe"); exit(2); }
	pid_t pid = fork();
	if (pid < 0) { perror("fork"); exit(2); }
	if (pid == 0) {
		close(p[0]);
		alarm(TIMEOUT_SECONDS);
		unsigned char *in  = (unsigned char *)calloc(1, BUF_SIZE);
		unsigned char *out = (unsigned char *)calloc(1, BUF_SIZE);
		char hex[CMP_BYTES*2 + 1], be[CMP_BYTES*2 + 1];
		binlify(in, header_hex);
		algo->hash_function((const char *)in, (char *)out, hexlen / 2);
		hexlify(hex, out, CMP_BYTES);
		string_be(hex, be);   // block hash as explorers show it
		if (write(p[1], be_order ? be : hex, CMP_BYTES*2) < 0) _exit(3);
		_exit(0);
	}
	close(p[1]);
	char got[CMP_BYTES*2 + 1] = {0};
	ssize_t n = read(p[0], got, CMP_BYTES*2);
	close(p[0]);
	int st = 0;
	waitpid(pid, &st, 0);

	if (n != (ssize_t)(CMP_BYTES*2) || !WIFEXITED(st) || WEXITSTATUS(st)) {
		printf("REF   %-14s FAIL (crashed or timed out)\n", algo_name);
		return 1;
	}
	if (strcmp(got, expected)) {
		printf("REF   %-14s FAIL\n      got:      %s\n      expected: %s\n", algo_name, got, expected);
		return 1;
	}
	printf("REF   %-14s ok\n", algo_name);
	return 0;
}

static int check_references(const char *file)
{
	FILE *f = fopen(file, "r");
	if (!f) { fprintf(stderr, "cannot open %s\n", file); return 2; }
	static char line[2 * BUF_SIZE];
	int total = 0, failed = 0;
	while (fgets(line, sizeof(line), f)) {
		if (line[0] == '#' || line[0] == '\n') continue;
		char *algo  = strtok(line, " \t\r\n");
		char *order = strtok(NULL, " \t\r\n");
		char *hash  = strtok(NULL, " \t\r\n");
		char *hdr   = strtok(NULL, " \t\r\n");
		if (!algo || !order || !hash || !hdr || (strcmp(order, "be") && strcmp(order, "raw"))) {
			printf("REF   malformed line (%s)\n", algo ? algo : "?");
			failed++;
			continue;
		}
		total++;
		if (run_reference(algo, !strcmp(order, "be"), hash, hdr)) failed++;
	}
	fclose(f);
	printf("reference: %d checked, %d failed\n", total, failed);
	return failed ? 1 : 0;
}

static std::map<std::string, std::string> load_vectors(const char *file)
{
	std::map<std::string, std::string> m;
	FILE *f = fopen(file, "r");
	if (!f) { fprintf(stderr, "cannot open %s\n", file); exit(2); }
	char line[1024];
	while (fgets(line, sizeof(line), f)) {
		if (line[0] == '#' || line[0] == '\n') continue;
		line[strcspn(line, "\n")] = 0;
		char algo[128], id[64];
		if (sscanf(line, "%127s %63s", algo, id) != 2) continue;
		m[std::string(algo) + " " + id] = line;
	}
	fclose(f);
	return m;
}

int main(int argc, char **argv)
{
	const char *check_file = NULL;
	const char *ref_file = NULL;
	const char *only = NULL;
	bool generate = false;
	const char *usage = "usage: %s --generate | --check FILE [--algo NAME] | --reference FILE\n";

	for (int i = 1; i < argc; i++) {
		if (!strcmp(argv[i], "--generate")) generate = true;
		else if (!strcmp(argv[i], "--check") && i + 1 < argc) check_file = argv[++i];
		else if (!strcmp(argv[i], "--reference") && i + 1 < argc) ref_file = argv[++i];
		else if (!strcmp(argv[i], "--algo") && i + 1 < argc) only = argv[++i];
		else { fprintf(stderr, usage, argv[0]); return 2; }
	}
	if ((int)generate + (check_file != NULL) + (ref_file != NULL) != 1) {
		fprintf(stderr, usage, argv[0]);
		return 2;
	}

	// hash functions log through debuglog(); keep that out of the way
	snprintf(g_log_directory, sizeof(g_log_directory), "/tmp/");
	initlog(NULL);

	if (ref_file) return check_references(ref_file);

	std::vector<TestInput> inputs = make_inputs();
	std::map<std::string, std::string> expected;
	if (check_file) expected = load_vectors(check_file);

	int total = 0, failed = 0, added = 0;
	std::set<std::string> seen;

	if (generate) {
		printf("# stratum hash vectors, generated by tests/hashcheck --generate\n");
		printf("# <algo> <input> <status> <bytes-written> <first-32-bytes-hex>\n");
	}

	for (int a = 0; g_algos[a].name[0]; a++) {
		const YAAMP_ALGO *algo = &g_algos[a];
		if (!algo->hash_function) continue;
		if (only && strcmp(only, algo->name)) continue;

		for (const auto &line : run_algo(algo, inputs)) {
			total++;
			if (generate) { printf("%s\n", line.c_str()); continue; }

			char name[128], id[64];
			sscanf(line.c_str(), "%127s %63s", name, id);
			std::string key = std::string(name) + " " + id;
			seen.insert(key);

			auto it = expected.find(key);
			if (it == expected.end()) {
				added++;
				printf("NEW   %s\n", line.c_str());
			} else if (it->second != line) {
				failed++;
				printf("FAIL  %s\n      expected: %s\n", line.c_str(), it->second.c_str());
			}
		}
	}

	if (generate) return 0;

	int missing = 0;
	if (!only) {
		for (const auto &e : expected) {
			if (!seen.count(e.first)) {
				missing++;
				printf("GONE  %s\n", e.second.c_str());
			}
		}
	}

	printf("hashcheck: %d checked, %d failed, %d new, %d gone\n", total, failed, added, missing);
	return (failed || missing) ? 1 : 0;
}
