#include <stdlib.h>
#include <stdint.h>
#include <string.h>
#include <stdio.h>

#include "sha256.h"
#include "sha256-t.h"

#include <stdlib.h>

void sha256dt_hash(const char *input, char *output, uint32_t len)
{
    char temp[32];

    SHA256_CTX ctx;
    SHA256dt_Init(&ctx);
    SHA256_Update(&ctx, input, len);
    SHA256_Final((unsigned char*) &temp, &ctx);

    SHA256dt_Init(&ctx);
    SHA256_Update(&ctx, &temp, 32);
    SHA256_Final((unsigned char*) output, &ctx);
}
