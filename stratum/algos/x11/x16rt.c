#include <stdio.h>
#include <stdlib.h>
#include <string.h>

#include "x16rt.h"

#include "algos/common/common.h"
#include "algos/common/sph512.h"

#define TIME_MASK 0xffffff80

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

static void getAlgoString(const uint32_t* timeHash, char *output)
{
	char *sptr = output;
	uint8_t* data = (uint8_t*)timeHash;

	for (uint8_t j = 0; j < HASH_FUNC_COUNT; j++) {
		uint8_t b = (15 - j) >> 1; // 16 ascii hex chars, reversed
		uint8_t algoDigit = (j & 1) ? data[b] & 0xF : data[b] >> 4;

		if (algoDigit >= 10)
			sprintf(sptr, "%c", 'A' + (algoDigit - 10));
		else
			sprintf(sptr, "%u", (uint32_t) algoDigit);
		sptr++;
	}
	*sptr = '\0';
}

static void getTimeHash(const uint32_t timeStamp, void* timeHash)
{
	int32_t maskedTime = timeStamp & TIME_MASK;
	sha256d((unsigned char*)timeHash, (const unsigned char*)&(maskedTime), sizeof(maskedTime));
}

void x16rt_hash(const char* input, char* output, uint32_t len)
{
	unsigned char hash[128];
	char hashOrder[HASH_FUNC_COUNT + 1] = { 0 };

	void *in = (void*) input;
	int size = 80;

	uint32_t *in32 = (uint32_t*) input;
	uint32_t ntime = in32[17];
	uint32_t timeHash[8];
	getTimeHash(ntime, &timeHash);
	getAlgoString(&timeHash[0], hashOrder);

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
		}
		in = (void*) hash;
		size = 64;
	}
	memcpy(output, hash, 32);
}
