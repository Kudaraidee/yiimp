#include <stdlib.h>
#include <stdint.h>
#include <string.h>
#include <stdio.h>

#include "third_party/sph/sph_streebog.h"

#include "algos/lyra2/Lyra2.h"

#include "algos/common/common.h"
#include "algos/common/sph512.h"

void phi2_hash(const char* input, char* output, uint32_t len)
{
	unsigned char _ALIGN(128) hash[64];
	unsigned char _ALIGN(128) hashA[64];
	unsigned char _ALIGN(128) hashB[64];

	sph512(SPH_CUBEHASH512, input, len, (void*)hashB);

	LYRA2(&hashA[ 0], 32, &hashB[ 0], 32, &hashB[ 0], 32, 1, 8, 8);
	LYRA2(&hashA[32], 32, &hashB[32], 32, &hashB[32], 32, 1, 8, 8);

	sph512(SPH_JH512, (const void*)hashA, 64, (void*)hash);

	if (hash[0] & 1) {
		sph512(SPH_GOST512, (const void*)hash, 64, (void*)hash);
	} else {
		sph512(SPH_ECHO512, (const void*)hash, 64, (void*)hash);

		sph512(SPH_ECHO512, (const void*)hash, 64, (void*)hash);
	}

	sph512(SPH_SKEIN512, (const void*)hash, 64, (void*)hash);

	for (int i=0; i<32; i++)
		hash[i] ^= hash[i+32];

	memcpy(output, hash, 32);
}
