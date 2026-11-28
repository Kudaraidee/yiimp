#include <stdlib.h>
#include <stdint.h>
#include <string.h>
#include <stdio.h>

#include "third_party/sph/sph_streebog.h"
#include "algos/common/sph512.h"

void sib_hash(const char *input, char* output, uint32_t len)
{
	//these uint512 in the c++ source of the client are backed by an array of uint32
	uint32_t hashA[16], hashB[16];

	sph512(SPH_BLAKE512, input, 80, hashA);

	sph512(SPH_BMW512, hashA, 64, hashB);

	sph512(SPH_GROESTL512, hashB, 64, hashA);

	sph512(SPH_SKEIN512, hashA, 64, hashB);

	sph512(SPH_JH512, hashB, 64, hashA);

	sph512(SPH_KECCAK512, hashA, 64, hashB);

	sph512(SPH_GOST512, hashB, 64, hashA);

	sph512(SPH_LUFFA512, hashA, 64, hashB);

	sph512(SPH_CUBEHASH512, hashB, 64, hashA);

	sph512(SPH_SHAVITE512, hashA, 64, hashB);

	sph512(SPH_SIMD512, hashB, 64, hashA);

	sph512(SPH_ECHO512, hashA, 64, hashB);

	memcpy(output, hashB, 32);
}

