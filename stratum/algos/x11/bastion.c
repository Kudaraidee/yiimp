#include <stdlib.h>
#include <stdint.h>
#include <string.h>
#include <stdio.h>
#include "algos/common/sph512.h"

#define _ALIGN(x) __attribute__ ((aligned(x)))

void bastion_hash(const char* input, char* output, uint32_t len)
{
	unsigned char _ALIGN(128) hash[64] = { 0 };

	HEFTY1(input, len, hash);

	sph512(SPH_LUFFA512, hash, 64, hash);

	if (hash[0] & 0x8)
	{
		sph512(SPH_FUGUE512, hash, 64, hash);
	} else {
		sph512(SPH_SKEIN512, hash, 64, hash);
	}

	sph512(SPH_WHIRLPOOL, hash, 64, hash);

	sph512(SPH_FUGUE512, hash, 64, hash);

	if (hash[0] & 0x8)
	{
		sph512(SPH_ECHO512, hash, 64, hash);
	} else {
		sph512(SPH_LUFFA512, hash, 64, hash);
	}

	sph512(SPH_SHABAL512, hash, 64, hash);

	sph512(SPH_SKEIN512, hash, 64, hash);

	if (hash[0] & 0x8)
	{
		sph512(SPH_SHABAL512, hash, 64, hash);
	} else {
		sph512(SPH_WHIRLPOOL, hash, 64, hash);
	}

	sph512(SPH_SHABAL512, hash, 64, hash);

	if (hash[0] & 0x8)
	{
		sph512(SPH_HAMSI512, hash, 64, hash);
	} else {
		sph512(SPH_LUFFA512, hash, 64, hash);
	}

	memcpy(output, hash, 32);
}

