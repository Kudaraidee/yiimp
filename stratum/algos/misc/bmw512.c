#include "bmw512.h"
#include <stdlib.h>
#include <stdint.h>
#include <string.h>
#include <stdio.h>
#include "third_party/sph/sph_bmw.h"

 void bmw512_hash(const char* input, char* output, uint32_t len)
{
    sph_bmw512_context       ctx_bmw;
    uint32_t hashA[16];

    sph_bmw512_init(&ctx_bmw);
    sph_bmw512 (&ctx_bmw, input, len);
    sph_bmw512_close(&ctx_bmw, hashA);

     memcpy(output, hashA, 32);
}