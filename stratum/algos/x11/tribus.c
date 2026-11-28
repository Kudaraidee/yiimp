#include <stdlib.h>
#include <stdint.h>
#include <string.h>
#include <stdio.h>

#include "algos/common/common.h"
#include "algos/common/sph512.h"

void tribus_hash(const char* input, char* output, uint32_t len)
{
	uint8_t _ALIGN(64) hash[64];

	sph512(SPH_JH512, input, 80, (void*) hash);

	sph512(SPH_KECCAK512, (const void*) hash, 64, (void*) hash);

	sph512(SPH_ECHO512, (const void*) hash, 64, (void*) hash);

	memcpy(output, hash, 32);
}

