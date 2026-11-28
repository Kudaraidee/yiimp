#include <stdio.h>
#include <stdlib.h>
#include <string.h>

#include <third_party/sph/sph_haval.h>
#include "algos/common/sph512.h"

#define _ALIGN(x) __attribute__ ((aligned(x)))

void x18_hash(const char* input, char* output, uint32_t len)
{
    sph_haval256_5_context    ctx_haval;

	//these uint512 in the c++ source of the client are backed by an array of uint32
	uint32_t _ALIGN(64) hashA[16], hashB[16];

	sph512(SPH_BLAKE512, input, len, hashA);

	sph512(SPH_BMW512, hashA, 64, hashB);

	sph512(SPH_GROESTL512, hashB, 64, hashA);

	sph512(SPH_SKEIN512, hashA, 64, hashB);

	sph512(SPH_JH512, hashB, 64, hashA);

	sph512(SPH_KECCAK512, hashA, 64, hashB);

	sph512(SPH_LUFFA512, hashB, 64, hashA);

	sph512(SPH_CUBEHASH512, hashA, 64, hashB);

	sph512(SPH_SHAVITE512, hashB, 64, hashA);

	sph512(SPH_SIMD512, hashA, 64, hashB);

	sph512(SPH_ECHO512, hashB, 64, hashA);

	sph512(SPH_HAMSI512, hashA, 64, hashB);

	sph512(SPH_FUGUE512, hashB, 64, hashA);

	sph512(SPH_SHABAL512, hashA, 64, hashB);

	sph512(SPH_WHIRLPOOL, hashB, 64, hashA);

	sph512(SPH_SHA512, hashA, 64, hashB);

	sph_haval256_5_init (&ctx_haval);
	sph_haval256_5 (&ctx_haval, hashB, 64);
	sph_haval256_5_close(&ctx_haval, hashA);

	sph512(SPH_GOST512, hashA, 64, hashB);

	memcpy(output, hashB, 32);
}
