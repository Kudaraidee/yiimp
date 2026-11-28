#include <stdlib.h>
#include <stdint.h>
#include <string.h>
#include <stdio.h>

#include "third_party/sph/sph_streebog.h"

#include "algos/common/common.h"
#include "algos/common/sph512.h"

void skunk_hash(const char *input, char* output, uint32_t len)
{
	uint32_t _ALIGN(64) hash[16];

	sph512(SPH_SKEIN512, input, 80, (void*) hash);

	sph512(SPH_CUBEHASH512, hash, 64, hash);

	sph512(SPH_FUGUE512, hash, 64, hash);

	sph512(SPH_GOST512, (const void*) hash, 64, (void*) hash);

	memcpy(output, hash, 32);
}

