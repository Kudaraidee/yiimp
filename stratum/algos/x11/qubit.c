#include "qubit.h"

#include <string.h>
#include <stdlib.h>

#include "third_party/sph/sph_cubehash.h"
#include "third_party/sph/sph_shavite.h"
#include "third_party/sph/sph_simd.h"
#include "third_party/sph/sph_echo.h"
#include "algos/common/sph512.h"

void qubit_hash(const char* input, char* output, uint32_t len)
{
    sph_cubehash512_context ctx_cubehash;
    sph_shavite512_context  ctx_shavite;
    sph_simd512_context     ctx_simd;
    sph_echo512_context     ctx_echo;

    char hash1[64];
    char hash2[64];

    sph512(SPH_LUFFA512, (const void*) input, len, (void*) &hash1); // 1

    sph_cubehash512_init(&ctx_cubehash);
    sph_cubehash512(&ctx_cubehash, (const void*) &hash1, 64); // 1
    sph_cubehash512_close(&ctx_cubehash, (void*) &hash2); // 2

    sph_shavite512_init(&ctx_shavite);
    sph_shavite512(&ctx_shavite, (const void*) &hash2, 64); // 3
    sph_shavite512_close(&ctx_shavite, (void*) &hash1); // 4

    sph_simd512_init(&ctx_simd);
    sph_simd512(&ctx_simd, (const void*) &hash1, 64); // 4
    sph_simd512_close(&ctx_simd, (void*) &hash2); // 5

    sph_echo512_init(&ctx_echo);
    sph_echo512(&ctx_echo, (const void*) &hash2, 64); // 5
    sph_echo512_close(&ctx_echo, (void*) &hash1); // 6

    memcpy(output, &hash1, 32);
}
