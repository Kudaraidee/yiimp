#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <third_party/sph/sph_haval.h>
#include <third_party/sph/sph_radiogatun.h>
#include <third_party/sph/sph_panama.h>
#include "algos/common/sph512.h"

#define _ALIGN(x) __attribute__ ((aligned(x)))

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
	HAVAL,      // 256-bits output
	GOST,
	RADIOGATUN, // 256-bits output
	PANAMA,     // 256-bits output
	HASH_FUNC_COUNT
};

static __thread uint32_t s_ntime = UINT32_MAX;
static __thread char hashOrder[HASH_FUNC_COUNT + 1] = { 0 };

static void getAlgoString(const uint8_t* prevblock, char *output)
{
	char *sptr = output;

	for (int j = 0; j < HASH_FUNC_COUNT; j++) {
		char b = (19 - j) >> 1; // 16 ascii hex chars, reversed
		uint8_t algoDigit = (j & 1) ? prevblock[b] & 0xF : prevblock[b] >> 4;
		if (algoDigit >= 10)
			sprintf(sptr, "%c", 'A' + (algoDigit - 10));
		else
			sprintf(sptr, "%u", (uint32_t) algoDigit);
		sptr++;
	}
	*sptr = '\0';
}

void x20r_hash(const char* input, char* output, uint32_t len)
{
	uint32_t _ALIGN(128) hash[64/4];

	sph_haval256_5_context   ctx_haval;
	sph_radiogatun64_context ctx_radiogatun;
	sph_panama_context       ctx_panama;

	void *in = (void*) input;
	int size = len;

	if (s_ntime == UINT32_MAX) {
		const uint8_t* in8 = (uint8_t*) input;
		getAlgoString(&in8[4], hashOrder);
	}

	for (int i = 0; i < 20; i++)
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
			sph512(SPH_KECCAK512, in, size, hash);
			break;
		case LUFFA:
			sph512(SPH_LUFFA512, in, size, hash);
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
			sph512(SPH_SHA512, (const void*) in, size, (void*) hash);
			break;
		case HAVAL:
			sph_haval256_5_init(&ctx_haval);
			sph_haval256_5(&ctx_haval, in, size);
			sph_haval256_5_close(&ctx_haval, hash);
			memset(&hash[8], 0, 32);
			break;
		case GOST:
			sph512(SPH_GOST512, in, size, hash);
			break;
		case RADIOGATUN:
			sph_radiogatun64_init(&ctx_radiogatun);
			sph_radiogatun64(&ctx_radiogatun, in, size);
			sph_radiogatun64_close(&ctx_radiogatun, hash);
			memset(&hash[8], 0, 32);
			break;
		case PANAMA:
			sph_panama_init(&ctx_panama);
			sph_panama(&ctx_panama, in, size);
			sph_panama_close(&ctx_panama, hash);
			memset(&hash[8], 0, 32);
			break;
		}
		in = (void*) hash;
		size = 64;
	}
	memcpy(output, hash, 32);
}