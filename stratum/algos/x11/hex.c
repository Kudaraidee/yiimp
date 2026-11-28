// Copyright (c) 2018 The XDNA Core developers
// Distributed under the MIT software license, see the accompanying
// file COPYING or http://www.opensource.org/licenses/mit-license.php.

#include <stdint.h>
#include <stdio.h>
#include <memory.h>

#include "hex.h"
#include "algos/common/sph512.h"

enum Algo {
    BLAKE = 0,
    BMW,
    GROESTL,
    JH,
    KECCAK,
    SKEIN,
    LUFFA,
    CUBEHASH,
    SHAVITE,
    SIMD,
    ECHO,
    HAMSI,
    FUGUE,
    SHABAL,
    WHIRLPOOL,
    SHA512,
    HASH_FUNC_COUNT
};

static const int TOTAL_CYCLES = 16;

static uint8_t get_first_algo(const uint32_t* prevblock) {
    uint8_t* data = (uint8_t*)prevblock;
    return data[7] >> 4;
}

void hex_hash(const char* input, char* output, uint32_t len)
{
    unsigned char hash[128];
    uint8_t curr_algo;

    const void *in = input;
    int size = len;

    uint32_t *in32 = (uint32_t*) input;

    // initial algo = first digit of prev block hashorder (cheers, x16r)
    curr_algo = get_first_algo(&in32[1]);

    for (int i = 0; i < TOTAL_CYCLES; i++)
    {
        // Only 4 test algos yet
        switch (curr_algo) {
            case BLAKE:
                sph512(SPH_BLAKE512, in, size, hash);
                break;
            case BMW:
                sph512(SPH_BMW512, in, size, hash);
                break;
            case GROESTL:
                sph512(SPH_GROESTL512, in, size, hash);
                break;
            case JH:
                sph512(SPH_JH512, in, size, hash);
                break;
            case KECCAK:
                sph512(SPH_KECCAK512, in, size, hash);
                break;
            case SKEIN:
                sph512(SPH_SKEIN512, in, size, hash);
                break;
            case LUFFA:
                sph512(SPH_LUFFA512, in, size, hash);
                break;
            case CUBEHASH:
                sph512(SPH_CUBEHASH512, in, size, hash);
                break;
            case SHAVITE:
                sph512(SPH_SHAVITE512, in, size, hash);
                break;
            case SIMD:
                sph512(SPH_SIMD512, in, size, hash);
                break;

            case ECHO:
                sph512(SPH_ECHO512, in, size, hash);
                break;
            case HAMSI:
                sph512(SPH_HAMSI512, in, size, hash);
                break;
            case FUGUE:
                sph512(SPH_FUGUE512, in, size, hash);
                break;
            case SHABAL:
                sph512(SPH_SHABAL512, in, size, hash);
                break;
            case WHIRLPOOL:
                sph512(SPH_WHIRLPOOL, in, size, hash);
                break;
            case SHA512:
                sph512(SPH_SHA512, (const void*) in, size, (void*) hash);
                break;
        }
        // next algos = first digit on prev hash
        curr_algo = (uint8_t)hash[0] % HASH_FUNC_COUNT;
        in = (void*)hash;
        size = 64;
    }

    memcpy(output, hash, 32);
}
