#include <stdlib.h>
#include <stdint.h>
#include <string.h>
#include <stdio.h>
#include "algos/common/sph512.h"

void x12_hash(const char* input, char* output, uint32_t len)
{
	uint32_t hash[16];

	sph512(SPH_BLAKE512, input, len, hash);

	sph512(SPH_BMW512, hash, 64, hash);

	sph512(SPH_LUFFA512, hash, 64, hash);

	sph512(SPH_CUBEHASH512, hash, 64, hash);

	sph512(SPH_SHAVITE512, hash, 64, hash);

	sph512(SPH_SIMD512, hash, 64, hash);

	sph512(SPH_ECHO512, hash, 64, hash);

	sph512(SPH_GROESTL512, hash, 64, hash);

	sph512(SPH_SKEIN512, hash, 64, hash);

	sph512(SPH_JH512, hash, 64, hash);

	sph512(SPH_KECCAK512, hash, 64, hash);

	sph512(SPH_HAMSI512, hash, 64, hash);

	memcpy(output, hash, 32);
}
