#include <stdlib.h>
#include <stdint.h>
#include <string.h>
#include <stdio.h>

#include "third_party/sph/sph_sm3.h"

#include "algos/common/common.h"
#include "algos/common/sph512.h"

void hsr_hash(const char* input, char* output, uint32_t len)
{
    sm3_ctx_t               ctx_sm3;

    uint8_t _ALIGN(128) hash[64];

    sph512(SPH_BLAKE512, input, len, hash);

    sph512(SPH_BMW512, hash, 64, hash);

    sph512(SPH_GROESTL512, hash, 64, hash);

    sph512(SPH_SKEIN512, hash, 64, hash);

    sph512(SPH_JH512, hash, 64, hash);

    sph512(SPH_KECCAK512, hash, 64, hash);

    sph512(SPH_LUFFA512, hash, 64, hash);

    sph512(SPH_CUBEHASH512, hash, 64, hash);

    sph512(SPH_SHAVITE512, hash, 64, hash);

    sph512(SPH_SIMD512, hash, 64, hash);

    sph512(SPH_ECHO512, hash, 64, hash);

    sm3_init(&ctx_sm3);
    sm3_update(&ctx_sm3, hash, 64);
    memset(hash, 0, sizeof hash);
    sph_sm3_close(&ctx_sm3, hash);

    sph512(SPH_HAMSI512, hash, 64, hash);

    sph512(SPH_FUGUE512, hash, 64, hash);

    memcpy(output, hash, 32);
}
