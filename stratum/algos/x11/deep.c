#include <string.h>
#include <stdlib.h>
#include "algos/common/sph512.h"

void deep_hash(const char* input, char* output, uint32_t len)
{
    char hash1[64], hash2[64];

    sph512(SPH_LUFFA512, (const void*) input, len, (void*) &hash1);

    sph512(SPH_CUBEHASH512, (const void*) &hash1, 64, (void*) &hash2);

    sph512(SPH_ECHO512, (const void*) &hash2, 64, (void*) &hash1);

    memcpy(output, &hash1, 32);
}
