#include <stdio.h>
#include <stdlib.h>
#include <string.h>

#include <third_party/sph/sph_tiger.h>

#include "algos/common/common.h"
#include "algos/common/sph512.h"

enum Algo {
	BLAKE = 0,
	BMW,
	GROESTL,
	JH,
	KECCAK,
	SKEIN,
	LUFFA,
	CUBEHASH,
	SHAVITE,
	SIMD,
	ECHO,
	HAMSI,
	FUGUE,
	SHABAL,
	WHIRLPOOL,
	SHA512,
	HASH_FUNC_COUNT
};

static void getAlgoString(const uint8_t* prevblock, char *output)
{
	char *sptr = output;

	for (int j = 0; j < HASH_FUNC_COUNT; j++) {
		char b = (15 - j) >> 1; // 16 ascii hex chars, reversed
		uint8_t algoDigit = (j & 1) ? prevblock[b] & 0xF : prevblock[b] >> 4;
		if (algoDigit >= 10)
			sprintf(sptr, "%c", 'A' + (algoDigit - 10));
		else
			sprintf(sptr, "%u", (uint32_t) algoDigit);
		sptr++;
	}
	*sptr = '\0';
}

inline void padtiger512(uint32_t* hash) {
	for (int i = (24/4); i < (64/4); i++) hash[i] = 0;
}

void x16rv2_hash(const char* input, char* output, uint32_t len)
{
	uint32_t hash[64/4];
	char hashOrder[HASH_FUNC_COUNT + 1] = { 0 };

	sph_tiger_context        ctx_tiger;

	void *in = (void*) input;
	int size = len;

	getAlgoString(&input[4], hashOrder);

	for (int i = 0; i < 16; i++)
	{
		const char elem = hashOrder[i];
		const uint8_t algo = elem >= 'A' ? elem - 'A' + 10 : elem - '0';

		switch (algo) {
		case BLAKE:
			sph512(SPH_BLAKE512, in, size, hash);
			break;
		case BMW:
			sph512(SPH_BMW512, in, size, hash);
			break;
		case GROESTL:
			sph512(SPH_GROESTL512, in, size, hash);
			break;
		case SKEIN:
			sph512(SPH_SKEIN512, in, size, hash);
			break;
		case JH:
			sph512(SPH_JH512, in, size, hash);
			break;
		case KECCAK:
			sph_tiger_init(&ctx_tiger);
			sph_tiger(&ctx_tiger, (const void*) in, size);
			sph_tiger_close(&ctx_tiger, (void*) hash);
			padtiger512(hash);

			sph512(SPH_KECCAK512, hash, 64, hash);
			break;
		case LUFFA:
			sph_tiger_init(&ctx_tiger);
			sph_tiger(&ctx_tiger, (const void*) in, size);
			sph_tiger_close(&ctx_tiger, (void*) hash);
			padtiger512(hash);

			sph512(SPH_LUFFA512, hash, 64, hash);
			break;
		case CUBEHASH:
			sph512(SPH_CUBEHASH512, in, size, hash);
			break;
		case SHAVITE:
			sph512(SPH_SHAVITE512, in, size, hash);
			break;
		case SIMD:
			sph512(SPH_SIMD512, in, size, hash);
			break;
		case ECHO:
			sph512(SPH_ECHO512, in, size, hash);
			break;
		case HAMSI:
			sph512(SPH_HAMSI512, in, size, hash);
			break;
		case FUGUE:
			sph512(SPH_FUGUE512, in, size, hash);
			break;
		case SHABAL:
			sph512(SPH_SHABAL512, in, size, hash);
			break;
		case WHIRLPOOL:
			sph512(SPH_WHIRLPOOL, in, size, hash);
			break;
		case SHA512:
			sph_tiger_init(&ctx_tiger);
			sph_tiger(&ctx_tiger, (const void*) in, size);
			sph_tiger_close(&ctx_tiger, (void*) hash);
			padtiger512(hash);

			sph512(SPH_SHA512, (const void*) hash, 64, (void*) hash);
			break;
		}
		in = (void*) hash;
		size = 64;
	}
	memcpy(output, hash, 32);
}