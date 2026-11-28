#include <stdlib.h>
#include <stdint.h>
#include <string.h>
#include <stdio.h>

#include "jha.h"
#include "algos/common/common.h"
#include "algos/common/sph512.h"

void jha_hash(const char* input, char* output, uint32_t len)
{
	uint32_t _ALIGN(64) hash[16];

	// JHA v8: SHA3 512, on 80 bytes (not 88)
	sph512(SPH_KECCAK512, input, 80, (&hash));

	// Heavy & Light Pair Loop
	for (int round = 0; round < 3; round++)
	{
		if (hash[0] & 0x01) {
			sph512(SPH_GROESTL512, (&hash), 64, (&hash));
		} else {
			sph512(SPH_SKEIN512, (&hash), 64, (&hash));
		}

		if (hash[0] & 0x01) {
			sph512(SPH_BLAKE512, (&hash), 64, (&hash));
		} else {
			sph512(SPH_JH512, (&hash), 64, (&hash));
		}
	}

	// Return 256 bits (32x8)
	memcpy(output, hash, 32);
}
