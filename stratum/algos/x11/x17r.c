/**
 * x17r algo implementation
 *
 * modifyed by wubei@fusionsilicon.com 2018
 */

#include <stdio.h>
#include <stdint.h>

#include <stdlib.h>
#include <string.h>
#include <memory.h>

#include "third_party/sph/sph_haval.h"

#include "algos/common/common.h"
#include "algos/common/sph512.h"

#ifndef _MSC_VER
#define _ALIGN(x) __attribute__ ((aligned(x)))
#else
#define _ALIGN(x) __declspec(align(x))
#endif

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
	HAVAL,//
	HASH_FUNC_COUNT
};

static void getAlgoString(const uint8_t* prevblock, char *output)
{
	char *sptr = output;
	for (int j = 0; j < HASH_FUNC_COUNT; j++) {
		//uint8_t b = (16 - j) >> 1; // 16 first ascii hex chars (lsb in uint256)
		//printf ("the prevblock is %d\n",prevblock[j]);
		//uint8_t algoDigit = (j & 1) ? (prevblock[b] & 0xF) : prevblock[b] >> 4;//
		uint8_t algoDigit = prevblock[j] % HASH_FUNC_COUNT;

		//printf ("the algoDigit is %d\n",algoDigit);
		if (algoDigit >= 10)
			sprintf(sptr, "%c", 'A' + (algoDigit - 10));
		else
			sprintf(sptr, "%u", (uint32_t) algoDigit);
		sptr++;
	}
	*sptr = '\0';
}

//uint32_t s_ntime = UINT32_MAX;
void x17r_hash(const char* input, char* output, uint32_t len)
{
	uint32_t s_ntime = UINT32_MAX;
	char hashOrder[HASH_FUNC_COUNT + 1] = { 0 };

	char* bytes_swap = NULL;
	bytes_swap = (char *)malloc(len);
	if (len >= 180) {
      memcpy(bytes_swap, (char *)input, 140);
	   memcpy(bytes_swap+140, (char *)input+144, len-144);
	   memcpy(bytes_swap+len-4, (char *)input+140, 4);
   } else {
	   memcpy(bytes_swap, (char *)input, len);
	}

	uint32_t _ALIGN(128) hash[64/4];

	sph_haval256_5_context   ctx_haval;//
  
   memset((char*)&hash, 0, 64);
	void *in = (void*) bytes_swap;
	int size = len;

	if (s_ntime == UINT32_MAX) {
		const uint8_t* in8 = (uint8_t*) bytes_swap;
		getAlgoString(&in8[4], hashOrder);
	}

	for (int i = 0; i < 17; i++)//
	{
		const char elem = hashOrder[i];
		const uint8_t algo = elem >= 'A' ? elem - 'A' + 10 : elem - '0';
		//printf ("the algo is %d\n",algo);
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
         sph_haval256_5(&ctx_haval, (const void*)in, size);
         sph_haval256_5_close(&ctx_haval, hash);
         memset(hash+8,0x00000000,32);
         break;
		}
		in = (void*) hash;
		size = 64;
	}
	memcpy(output, hash, 32);
	free(bytes_swap);
}
