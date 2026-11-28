
#include "skein.h"
#include <stdlib.h>
#include <stdint.h>
#include <string.h>
#include <stdio.h>

#include "third_party/sph/sph_skein.h"
#include "algos/sha2/sha256.h"

#include <stdlib.h>

void skein_hash(const char* input, char* output, uint32_t len)
{
    char temp[64];

    sph_skein512_context ctx_skien;
    sph_skein512_init(&ctx_skien);
    sph_skein512(&ctx_skien, input, len);
    sph_skein512_close(&ctx_skien, &temp);

    SHA256_CTX ctx_sha256;
    SHA256_Init(&ctx_sha256);
    SHA256_Update(&ctx_sha256, &temp, 64);
    SHA256_Final((unsigned char*) output, &ctx_sha256);
}

