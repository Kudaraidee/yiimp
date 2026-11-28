#include <stdint.h>
#include <stdlib.h>
#include <string.h>
#include <third_party/sph/sph_sha2.h>
#include <third_party/sph/sph_haval.h>
#include <third_party/sph/sph_tiger.h>
#include <third_party/sph/sph_streebog.h>
#include "algos/lyra2/Lyra2.h"
#include "algos/common/common.h"
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
static void getAlgoString(const uint8_t* prevblock, char *output)
{
    strcpy(output, "0123456789ABCDEF");
         for(int i = 0; i < 16; i++){
                uint8_t b = (15 - i) >> 1; // 16 ascii hex chars, reversed
                uint8_t algoDigit = (i & 1) ? prevblock[b] & 0xF : prevblock[b] >> 4;
                 int offset = algoDigit;
                // insert the nth character at the front
                char oldVal = output[offset];
                for(int j=offset; j-->0;) {
                        output[j+1] = output[j];
                }
                output[0] = oldVal;
        }
}
 void x21s_hash(const char* input, char* output, uint32_t len) {
        uint32_t hash[64/4];
        char hashOrder[HASH_FUNC_COUNT + 1] = { 0 };
        sph_haval256_5_context ctx_haval;
        sph_tiger_context ctx_tiger;
        sph_sha256_context ctx_sha;
         void *in = (void*) input;
        int size = len;
         getAlgoString(&input[4], hashOrder);
         for (int i = 0; i < 16; i++)
        {
                const char elem = hashOrder[i];
                const uint8_t algo = elem >= 'A' ? elem - 'A' + 10 : elem - '0';
                 switch (algo) {
                case BLAKE:
                        sph512(SPH_BLAKE512, in, size, hash);
                        break;
                case BMW:
                        sph512(SPH_BMW512, in, size, hash);
                        break;
                case GROESTL:
                        sph512(SPH_GROESTL512, in, size, hash);
                        break;
                case SKEIN:
                        sph512(SPH_SKEIN512, in, size, hash);
                        break;
                case JH:
                        sph512(SPH_JH512, in, size, hash);
                        break;
                case KECCAK:
                        sph512(SPH_KECCAK512, in, size, hash);
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
                in = (void*) hash;
                size = 64;
        }
         sph_haval256_5_init(&ctx_haval);
        sph_haval256_5(&ctx_haval,(const void*) hash, 64);
        sph_haval256_5_close(&ctx_haval,hash);
         sph_tiger_init(&ctx_tiger);
        sph_tiger (&ctx_tiger, (const void*) hash, 64);
        sph_tiger_close(&ctx_tiger, (void*) hash);
         LYRA2((void*) hash, 32, (const void*) hash, 32, (const void*) hash, 32, 1, 4, 4);
         sph512(SPH_GOST512, (const void*) hash, 64, (void*) hash);
         sph_sha256_init(&ctx_sha);
        sph_sha256 (&ctx_sha, (const void*) hash, 64);
        sph_sha256_close(&ctx_sha, (void*) hash);
         memcpy(output, hash, 32);
}