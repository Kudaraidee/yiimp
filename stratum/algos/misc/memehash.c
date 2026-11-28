#include "memehash.h"

#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <stdint.h>

#include "third_party/sph/sph_blake.h"
#include "third_party/sph/sph_cubehash.h"
#include "third_party/sph/sph_shavite.h"
#include "third_party/sph/sph_simd.h"
#include "third_party/sph/sph_echo.h"
#include "third_party/sph/sph_sha2.h"

void meme_hash(const char* input, char* output, uint32_t len)
{
	sph_blake512_context     ctx_blake;
	sph_cubehash512_context  ctx_cubehash1;
	sph_shavite512_context   ctx_shavite1;
	sph_simd512_context      ctx_simd1;
	sph_echo512_context      ctx_echo1;
    sph_sha256_context       ctx_sha;

    uint32_t hash[16];
    uint32_t hashA[16];

    sph_blake512_init(&ctx_blake);
    sph_blake512 (&ctx_blake, input, len);
    sph_blake512_close (&ctx_blake, hash);

    sph_simd512_init (&ctx_simd1);
    sph_simd512 (&ctx_simd1, hash, 64);
    sph_simd512_close(&ctx_simd1, hash);

    sph_echo512_init (&ctx_echo1);
    sph_echo512 (&ctx_echo1, hash, 64);
    sph_echo512_close(&ctx_echo1, hash);

    sph_cubehash512_init (&ctx_cubehash1);
    sph_cubehash512 (&ctx_cubehash1, hash, 64);
    sph_cubehash512_close(&ctx_cubehash1, hash);

    sph_shavite512_init (&ctx_shavite1);
    sph_shavite512 (&ctx_shavite1, hash, 64);
    sph_shavite512_close(&ctx_shavite1, hash);

    sph_sha256_init(&ctx_sha);
    sph_sha256 (&ctx_sha, hash, 64);
    sph_sha256_close(&ctx_sha, hashA);

    for (int i=8;i<16;i++)
        hashA[i]=0;

    sph_sha256_init(&ctx_sha);
    sph_sha256 (&ctx_sha, hashA, 64);
    sph_sha256_close(&ctx_sha, hashA);

    for (int i=8;i<16;i++)
        hashA[i]=0;	

    sph_sha256_init(&ctx_sha);
    sph_sha256 (&ctx_sha, hashA, 64);
    sph_sha256_close(&ctx_sha, hash);	

    memcpy(output, hash, 32);
}