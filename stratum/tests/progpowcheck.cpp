// progpowcheck: ProgPoW-family verification (light epoch context) against
// recorded vectors, the way stratum verifies a share.
//
//   progpowcheck --reference          KawPoW known answers (cpp-kawpow)
//   progpowcheck --generate           print the golden master to stdout
//   progpowcheck --check FILE         compare with FILE, exit 1 on mismatch
//
// Per variant: heights across the period and epoch boundaries, a few
// header/nonce pairs. Line format:
//   <algo> <height> <header> <nonce> <mix> <final>
// The golden master records today's behaviour (any change is a regression);
// the KawPoW answers come from outside this code base.

#include <stdarg.h>
#include <string>
#include <vector>

#include "../stratum.h"
#include "algos/progpow/kawpow/helpers.hpp"
#include "algos/progpow/kawpow/include/progpow.hpp"
#include "algos/progpow/firopow/include/progpow.hpp"
#include "algos/progpow/phihash/include/progpow.hpp"
#include "algos/progpow/meowpow/include/progpow.hpp"
#include "algos/progpow/evrprogpow/include/progpow.hpp"
#include "algos/progpow/meraki/helpers.hpp"
#include "algos/progpow/meraki/lib/meraki/progpow.hpp"

static std::string strprintf(const char *fmt, ...)
{
	char buf[1024];
	va_list ap;
	va_start(ap, fmt);
	vsnprintf(buf, sizeof(buf), fmt, ap);
	va_end(ap);
	return buf;
}

struct kat { int height; const char *header, *nonce, *mix, *final; };

// cpp-kawpow test/unittests/progpow_test_vectors.hpp
static const kat kawpow_kats[] = {
	{0, "0000000000000000000000000000000000000000000000000000000000000000", "0000000000000000",
		"6e97b47b134fda0c7888802988e1a373affeb28bcd813b6e9a0fc669c935d03a",
		"e601a7257a70dc48fccc97a7330d704d776047623b92883d77111fb36870f3d1"},
	{49, "63155f732f2bf556967f906155b510c917e48e99685ead76ea83f4eca03ab12b", "0000000007073c07",
		"d36f7e815ee09e74eceb9c96993a3d681edf2bf0921fc7bb710364042db99777",
		"e7ced124598fd2500a55ad9f9f48e3569327fe50493c77a4ac9799b96efb9463"},
	{50, "9e7248f20914913a73d80a70174c331b1d34f260535ac3631d770e656b5dd922", "00000000076e482e",
		"d6dc634ae837e2785b347648ea515e25e5d8821ae0b95e1c2a9c2d497e0dcfbd",
		"ab0ad7ef8d8ee317dd12d10310aceed7321d34fb263791c2de5776a6658d177e"},
	{99, "de37e1824c86d35d154cf65a88de6d9286aec4f7f10c3fc9f0fa1bcc2687188d", "000000003917afab",
		"fa706860e5e0e830d5d1d7157e5bea7f5f8a350c7c8612ac1d1fcf2974d64244",
		"aa85340690f2e907054324a5021937910e15edfd1ef1577231843e7d32ec3a61"},
	{29950, "ac7b55e801511b77e11d52e9599206101550144525b5679f2dab19386f23dcce", "005d409dbc23a62a",
		"5359807b77a74878269c3a3044df8618a576ce8dc52e1c48d927d4a60e7c6b79",
		"022019e5408683f7f8326b4e46b42864a3a069f17b6151e434fcaedecaadd918"},
	{29999, "e43d7e0bdc8a4a3f6e291a5ed790b9fa1a0948a2b9e33c844888690847de19f5", "005db5fa4c2a3d03",
		"d15de3f9bfedd9b6d0f498273eb3b437115bdc8326c96c6457ac06deb5c9f389",
		"4e93630b81198752f876b24380999189b7b9366c08222ac05e4237b87114f305"},
	{30000, "d34519f72c97cae8892c277776259db3320820cb5279a299d0ef1e155e5c6454", "005db8607994ff30",
		"de0348b69bf91dfe2c3d3dba6f0132e9048a5284e57b8d9d20adc5f3dc0d3236",
		"c7953d848cda6e304f77b4c6d735645c8e8508a5e74c9e9814ef37b19087cd6c"},
	{30049, "8b6ce5da0b06d18db7bd8492d9e5717f8b53e7e098d9fef7886d58a6e913ef64", "005e2e215a8ca2e7",
		"975c6a9decc89cba7ace69338d4de8510d9619aef42b1d35d0bef7e0ce0614a9",
		"c262d8055e288d04b951a844bfca8ba529f5b4d652b408e3942727d7dd90957a"},
	{30050, "c2c46173481b9ced61123d2e293b42ede5a1b323210eb2a684df0874ffe09047", "005e30899481055e",
		"362f2fabdb9699d3634b6499703f939f378ee4eac803396c2b0ed0fe1d154972",
		"4cd7e6e79e0b63d42b2b06716a919ccc7834077ec727a9ea94edcdaff2fefab8"},
	{30099, "ea42197eb2ba79c63cb5e655b8b1f612c5f08aae1a49ff236795a3516d87bc71", "005ea6aef136f88b",
		"b1196457261bd05ccb387a8ff3fd02687bf496bd7943d89419465289669e27aa",
		"39d1ebfa783b61a6fa8e9747d0f9f134efae5cfba284a2c80e8deabae6b98676"},
	{59950, "49e15ba4bf501ce8fe8876101c808e24c69a859be15de554bf85dbc095491bd6", "02ebe0503bd7b1da",
		"df3dbb1669fd35dbb0ae96bbea2d498f0c6992cbddd092aeace42dd933505f95",
		"b8984cf4021c4433f753654848d721f33a0792b4417241f0cf7c7c2db011a54a"},
	{59999, "f5c50ba5c0d6210ddb16250ec3efda178de857b2b1703d8d5403bd0f848e19cf", "02edb6275bd221e3",
		"5017df70e97ca35638cf439cdbe54f30383d335e18eb4a74d6e166736f1038fa",
		"4cf1fa62f25b577ac822a6a28d55f8b7e3ae7fe983abd868ae00927e68c41016"},
	{170915, "5b3e8dfa1aafd3924a51f33e2d672d8dae32fa528d8b1d378d6e4db0ec5d665d", "0000000044975727",
		"efb29147484c434f1cc59629da90fd0343e3b047407ecd36e9ad973bd51bbac5",
		"e7e6bb3b2f9acd3864bc86f72f87237eaf475633ef650c726ac80eb0adf116b6"},
};

// mix and final hash of one share, through the pool's epoch context handling
static void pp_hash(const char *algo, int height, const std::string &header, uint64_t nonce,
	std::string &mix, std::string &final)
{
	static int coinid = 1000;
	int id = ++coinid;	// fresh context per call: the epoch follows height exactly
	const ethash::hash256 h = to_hash256(header);

	if (is_meraki) {
		meraki_update_epoch(id, height);
		std::string mixs, nonces = strprintf("%016llx", (unsigned long long) nonce);
		uint256 f = meraki_hash(header, nonces, mixs, id, height);
		mix = mixs;
		final = f.ToString();
		return;
	}

	update_epoch(id, height);
	coin_context *c = get_coin_context(id);
	ethash::result r;
	if (is_kawpow)          r = kawpow::hash(*c->context, height, h, nonce);
	else if (is_firopow)    r = firopow::hash(*c->context, height, h, nonce);
	else if (is_phihash)    r = phihash::hash(*c->context, height, h, nonce);
	else if (is_meowpow)    r = meowpow::hash(*c->context, height, h, nonce);
	else if (is_evrprogpow) r = evrprogpow::hash(*c->context, height, h, nonce);
	mix = to_hex(r.mix_hash);
	final = to_hex(r.final_hash);
}

static bool set_algo(const char *name)
{
	YAAMP_ALGO *a = stratum_find_algo(name);
	if (!a) return false;
	g_current_algo = a;
	stratum_set_algo_flags(a);
	return true;
}

static int reference()
{
	set_algo("kawpow");
	int failed = 0, n = 0;
	for (const kat &k : kawpow_kats) {
		std::string mix, final;
		pp_hash("kawpow", k.height, k.header, strtoull(k.nonce, NULL, 16), mix, final);
		bool ok = mix == k.mix && final == k.final;
		printf("REF   kawpow %6d %s\n", k.height, ok ? "ok" : "FAIL");
		if (!ok) { printf("      want %s %s\n      got  %s %s\n", k.mix, k.final, mix.c_str(), final.c_str()); failed++; }
		n++;
	}
	printf("progpow reference: %d checked, %d failed\n", n, failed);
	return failed ? 1 : 0;
}

struct variant { const char *algo; int epoch, period; };

static std::vector<std::string> generate()
{
	// epoch/period as get_ethash_epoch_length()/period_length() have them
	static const variant v[] = {
		{"kawpow", 7500, 3}, {"firopow", 1300, 1}, {"phihash", 30000, 3},
		{"meowpow", 7500, 6}, {"evrprogpow", 12000, 3}, {"meraki", 27500, 3},
	};
	std::vector<std::string> out;
	for (const variant &x : v) {
		if (!set_algo(x.algo)) continue;
		int heights[] = {0, 1, x.period, x.epoch - 1, x.epoch, x.epoch + x.period};
		for (int hi = 0; hi < 6; hi++) {
			for (int k = 0; k < 2; k++) {
				char seed[64];
				snprintf(seed, sizeof(seed), "%s/%d/%d", x.algo, heights[hi], k);
				char header[65];
				sha256_hash_hex(seed, header, strlen(seed));
				uint64_t nonce = 0x0123456789abcdefULL * (uint64_t)(k + 1) + (uint64_t) heights[hi];
				std::string mix, final;
				pp_hash(x.algo, heights[hi], header, nonce, mix, final);
				out.push_back(strprintf("%s %d %s %016llx %s %s", x.algo, heights[hi], header,
					(unsigned long long) nonce, mix.c_str(), final.c_str()));
			}
		}
	}
	return out;
}

int main(int argc, char **argv)
{
	if (argc >= 2 && !strcmp(argv[1], "--reference")) return reference();

	if (argc >= 2 && !strcmp(argv[1], "--generate")) {
		for (const std::string &l : generate()) printf("%s\n", l.c_str());
		return 0;
	}

	if (argc >= 3 && !strcmp(argv[1], "--check")) {
		FILE *f = fopen(argv[2], "r");
		if (!f) { perror(argv[2]); return 2; }
		std::vector<std::string> want;
		char line[1024];
		while (fgets(line, sizeof(line), f)) {
			line[strcspn(line, "\r\n")] = 0;
			if (line[0] && line[0] != '#') want.push_back(line);
		}
		fclose(f);
		std::vector<std::string> got = generate();
		int failed = 0;
		for (size_t i = 0; i < std::max(want.size(), got.size()); i++) {
			const std::string w = i < want.size() ? want[i] : "(none)";
			const std::string g = i < got.size() ? got[i] : "(none)";
			if (w != g) { printf("DIFF  want %s\n      got  %s\n", w.c_str(), g.c_str()); failed++; }
		}
		printf("progpow vectors: %zu checked, %d failed\n", got.size(), failed);
		return failed ? 1 : 0;
	}

	fprintf(stderr, "usage: %s --reference | --generate | --check FILE\n", argv[0]);
	return 2;
}
