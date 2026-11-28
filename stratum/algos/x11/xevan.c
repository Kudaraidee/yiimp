#include <stdio.h>
#include <stdlib.h>
#include <string.h>

#include <third_party/sph/sph_haval.h>
#include "algos/common/sph512.h"

#define _ALIGN(x) __attribute__ ((aligned(x)))

void xevan_hash(const char* input, char* output, uint32_t len)
{
	uint32_t _ALIGN(64) hash[32]; // 128 bytes required
	const int dataLen = 128;

	sph_haval256_5_context   ctx_haval;

	sph512(SPH_BLAKE512, input, len, hash);

	memset(&hash[16], 0, 64);

	sph512(SPH_BMW512, hash, dataLen, hash);

	sph512(SPH_GROESTL512, hash, dataLen, hash);

	sph512(SPH_SKEIN512, hash, dataLen, hash);

	sph512(SPH_JH512, hash, dataLen, hash);

	sph512(SPH_KECCAK512, hash, dataLen, hash);

	sph512(SPH_LUFFA512, hash, dataLen, hash);

	sph512(SPH_CUBEHASH512, hash, dataLen, hash);

	sph512(SPH_SHAVITE512, hash, dataLen, hash);

	sph512(SPH_SIMD512, hash, dataLen, hash);

	sph512(SPH_ECHO512, hash, dataLen, hash);

	sph512(SPH_HAMSI512, hash, dataLen, hash);

	sph512(SPH_FUGUE512, hash, dataLen, hash);

	sph512(SPH_SHABAL512, hash, dataLen, hash);

	sph512(SPH_WHIRLPOOL, hash, dataLen, hash);

	sph512(SPH_SHA512, (const void*) hash, dataLen, (void*) hash);

	sph_haval256_5_init(&ctx_haval);
	sph_haval256_5(&ctx_haval,(const void*) hash, dataLen);
	sph_haval256_5_close(&ctx_haval, hash);

	memset(&hash[8], 0, dataLen - 32);

	sph512(SPH_BLAKE512, hash, dataLen, hash);

	sph512(SPH_BMW512, hash, dataLen, hash);

	sph512(SPH_GROESTL512, hash, dataLen, hash);

	sph512(SPH_SKEIN512, hash, dataLen, hash);

	sph512(SPH_JH512, hash, dataLen, hash);

	sph512(SPH_KECCAK512, hash, dataLen, hash);

	sph512(SPH_LUFFA512, hash, dataLen, hash);

	sph512(SPH_CUBEHASH512, hash, dataLen, hash);

	sph512(SPH_SHAVITE512, hash, dataLen, hash);

	sph512(SPH_SIMD512, hash, dataLen, hash);

	sph512(SPH_ECHO512, hash, dataLen, hash);

	sph512(SPH_HAMSI512, hash, dataLen, hash);

	sph512(SPH_FUGUE512, hash, dataLen, hash);

	sph512(SPH_SHABAL512, hash, dataLen, hash);

	sph512(SPH_WHIRLPOOL, hash, dataLen, hash);

	sph512(SPH_SHA512, (const void*) hash, dataLen, (void*) hash);

	sph_haval256_5_init(&ctx_haval);
	sph_haval256_5(&ctx_haval,(const void*) hash, dataLen);
	sph_haval256_5_close(&ctx_haval, hash);

	memcpy(output, hash, 32);
}
