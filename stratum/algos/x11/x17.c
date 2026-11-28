#include <stdio.h>
#include <stdlib.h>
#include <string.h>

#include <third_party/sph/sph_haval.h>
#include "algos/common/sph512.h"

void x17_hash(const char* input, char* output, uint32_t len)
{
	sph_haval256_5_context   ctx_haval;

	uint32_t hash[16];

	sph512(SPH_BLAKE512, input, len, hash);

	sph512(SPH_BMW512, hash, 64, hash);

	sph512(SPH_GROESTL512, hash, 64, hash);

	sph512(SPH_SKEIN512, hash, 64, hash);

	sph512(SPH_JH512, hash, 64, hash);

	sph512(SPH_KECCAK512, hash, 64, hash);

	sph512(SPH_LUFFA512, hash, 64, hash);

	sph512(SPH_CUBEHASH512, hash, 64, hash);

	sph512(SPH_SHAVITE512, hash, 64, hash);

	sph512(SPH_SIMD512, hash, 64, hash);

	sph512(SPH_ECHO512, hash, 64, hash);

	sph512(SPH_HAMSI512, hash, 64, hash);

	sph512(SPH_FUGUE512, hash, 64, hash);

	sph512(SPH_SHABAL512, hash, 64, hash);

	sph512(SPH_WHIRLPOOL, hash, 64, hash);

	sph512(SPH_SHA512, (const void*) hash, 64, (void*) hash);

	sph_haval256_5_init(&ctx_haval);
	sph_haval256_5(&ctx_haval,(const void*) hash, 64);
	sph_haval256_5_close(&ctx_haval, hash);

	memcpy(output, hash, 32);
}
