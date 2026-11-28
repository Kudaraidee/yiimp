#include "nist5.h"
#include <stdlib.h>
#include <stdint.h>
#include <string.h>
#include <stdio.h>
#include "algos/common/sph512.h"

void nist5_hash(const char* input, char* output, uint32_t len)
{
    //these uint512 in the c++ source of the client are backed by an array of uint32
    uint32_t hash[16];

    sph512(SPH_BLAKE512, input, len, hash);

    sph512(SPH_GROESTL512, hash, 64, hash);

    sph512(SPH_JH512, hash, 64, hash);

    sph512(SPH_KECCAK512, hash, 64, hash);

    sph512(SPH_SKEIN512, hash, 64, hash);

    memcpy(output, hash, 32);
}

