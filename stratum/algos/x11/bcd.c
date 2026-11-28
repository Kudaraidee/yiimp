#include "bcd.h"
#include <stdlib.h>
#include <stdint.h>
#include <string.h>
#include <stdio.h>

#include "third_party/sph/sph_sm3.h"

#include "algos/common/common.h"
#include "algos/common/sph512.h"

void bcd_hash(const char* input, char* output, uint32_t len)
{
    sm3_ctx_t                ctx_sm3;

    uint32_t hashA[16], hashB[16];

    sph512(SPH_BLAKE512, input, len, hashA);

    sph512(SPH_BMW512, hashA, 64, hashB);

    sph512(SPH_GROESTL512, hashB, 64, hashA);

    sph512(SPH_SKEIN512, hashA, 64, hashB);

    sph512(SPH_JH512, hashB, 64, hashA);

    sph512(SPH_KECCAK512, hashA, 64, hashB);

    memset(hashA, 0, sizeof(hashA));
    sm3_init(&ctx_sm3);
    sph_sm3(&ctx_sm3, hashB, 64);
    sph_sm3_close(&ctx_sm3, hashA);

    sph512(SPH_CUBEHASH512, hashA, 64, hashB);

    sph512(SPH_SHAVITE512, hashB, 64, hashA);

    sph512(SPH_SIMD512, hashA, 64, hashB);

    sph512(SPH_ECHO512, hashB, 64, hashA);

    sph512(SPH_HAMSI512, hashA, 64, hashB);

    sph512(SPH_FUGUE512, hashB, 64, hashA);

    memcpy(output, hashA, 32);
}