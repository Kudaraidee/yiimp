#include "fresh.h"
#include <stdlib.h>
#include <stdint.h>
#include <string.h>
#include <stdio.h>
#include "algos/common/sph512.h"

void fresh_hash(const char* input, char* output, uint32_t len)
{
    //these uint512 in the c++ source of the client are backed by an array of uint32
    uint32_t hashA[16], hashB[16];

    sph512(SPH_SHAVITE512, input, len, hashA);

    sph512(SPH_SIMD512, hashA, 64, hashB);

    sph512(SPH_SHAVITE512, hashB, 64, hashA);

    sph512(SPH_SIMD512, hashA, 64, hashB);

    sph512(SPH_ECHO512, hashB, 64, hashA);

    memcpy(output, hashA, 32);
}

