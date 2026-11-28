#include <stdlib.h>
#include <stdint.h>
#include <string.h>
#include <stdio.h>

#include "third_party/sph/sph_streebog.h"

#include "algos/common/common.h"
#include "algos/common/sph512.h"

void phi_hash(const char* input, char* output, uint32_t len)
{
	uint8_t _ALIGN(128) hash[64];

	sph512(SPH_SKEIN512, input, len, (void*) hash);

	sph512(SPH_JH512, (const void*) hash, 64, (void*) hash);

	sph512(SPH_CUBEHASH512, (const void*) hash, 64, (void*) hash);

	sph512(SPH_FUGUE512, (const void*) hash, 64, (void*) hash);

	sph512(SPH_GOST512, (const void*) hash, 64, (void*) hash);

	sph512(SPH_ECHO512, (const void*) hash, 64, (void*) hash);

	memcpy(output, hash, 32);
}
