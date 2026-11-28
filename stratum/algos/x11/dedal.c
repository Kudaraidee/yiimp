#include <stdint.h>
#include <stdio.h>
#include <memory.h>

#include "dedal.h"
#include "algos/common/sph512.h"

const uint8_t Kspeed[16] = {
	200,	// BLAKE
	236,	// BMW
	252,	// SKEIN
	224,	// KECCAK
	240,	// SHA512
	230,	// SHABAL
	79,		// WHIRLPOOL
	78,		// LUFFA
	89,		// CUBEHASH
	62,		// SHAVITE
	59,		// FUGUE
	119,	// JH
	62,		// HAMSI
	52,		// ECHO
	22,		// SIMD
	47		// GROESTL
};

static void get_hash_order(const uint32_t* prevblock, uint8_t* output, uint8_t* hashrounds)
{
	uint8_t* ord = output;
	uint8_t hr = 0;
	uint8_t* data = (uint8_t*)prevblock;
	uint16_t tspeed = 0;
	
	for (uint8_t i = 0; i < 6; i++) {
		ord[i] = data[i] % 16;
		ord[i + 6] = data[i+1] >> 4;
		tspeed += Kspeed[ord[i]] + Kspeed[ord[i + 6]];
	}
	hr = tspeed + 920 >> 7;
	
	int8_t  c = hr - 12;
	for (uint8_t i = 0; i < c ; i++) {
		if (i < 15) {
			uint8_t j = i >> 1;
			ord[i + 12] = (i & 1) ? data[j] % 6 : data[j] % 5;
		} else {
			ord[i + 12] = data[i - 15] % 4;
		}
	}
	*hashrounds = hr;
}

void dedal_hash(const char* input, char* output, uint32_t len) 
{
    unsigned char hash[128];

    const void *in = input;
    int size = len;
    uint32_t *in32 = (uint32_t*) input;
	uint8_t hashorder[32] = {};
	uint8_t hashrounds = 0;

	get_hash_order(&in32[1], hashorder, &hashrounds);

    for (int i = 0; i < hashrounds; i++) 
	{
        switch (hashorder[i]) 
		{
			case 0:
				sph512(SPH_BLAKE512, in, size, hash);
				break;
			case 1:
				sph512(SPH_BMW512, in, size, hash);
				break;
			case 2:
				sph512(SPH_SKEIN512, in, size, hash);
				break;
			case 3:
				sph512(SPH_KECCAK512, in, size, hash);
				break;
			case 4:
				sph512(SPH_SHA512, in, size, hash);
				break;
			case 5:
				sph512(SPH_SHABAL512, in, size, hash);
				break;
			case 6:
				sph512(SPH_WHIRLPOOL, in, size, hash);
				break;
			case 7:
				sph512(SPH_LUFFA512, in, size, hash);
				break;
			case 8:
				sph512(SPH_CUBEHASH512, in, size, hash);
				break;
			case 9:
				sph512(SPH_SHAVITE512, in, size, hash);
				break;
			case 10:
				sph512(SPH_FUGUE512, in, size, hash);
				break;
			case 11:
				sph512(SPH_JH512, in, size, hash);
				break;
			case 12:
				sph512(SPH_HAMSI512, in, size, hash);
				break;
			case 13:
				sph512(SPH_ECHO512, in, size, hash);
				break;
			case 14:
				sph512(SPH_SIMD512, in, size, hash);
				break;
			case 15:
				sph512(SPH_GROESTL512, in, size, hash);
				break;	
        }
        in = (void*)hash;
        size = 64;
    }
    memcpy(output, hash, 32);
}