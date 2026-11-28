#include <stdio.h>
#include <stdlib.h>
#include <string.h>

#include "soterg.h"
#include <third_party/sph/sph_blake.h>
#include <third_party/sph/sph_groestl.h>
#include <third_party/sph/sph_jh.h>
#include <third_party/sph/sph_keccak.h>
#include <third_party/sph/sph_skein.h>
#include <third_party/sph/sph_luffa.h>
#include <third_party/sph/sph_cubehash.h>
#include <third_party/sph/sph_shavite.h>
#include <third_party/sph/sph_simd.h>
#include <third_party/sph/sph_echo.h>
#include <third_party/sph/sph_hamsi.h>
#include <third_party/sph/sph_shabal.h>
#include <third_party/sph/sph_sha2.h>

#include "algos/common/common.h"

#define TIME_MASK 0xFFFFFFA0  // 96s bitmask

// Keep exactly 12 algorithms
enum Algo {
    BLAKE = 0,
    SHABAL,
    GROESTL,
    JH,
    KECCAK,
    SKEIN,
    LUFFA,
    CUBEHASH,
    SIMD,
    ECHO,
    HAMSI,
    SHA512,
    HASH_FUNC_COUNT
};

static uint8_t GetNibble(const uint8_t* hash, int index)
{
        index = 63 - index;
        if (index % 2 == 1)
            return(hash[index / 2] >> 4);
        return(hash[index / 2] & 0x0F);
}

// Helper function to get hash selection with fallback logic
static inline int GetHashSelection(const uint32_t* prevblock, int index)
{
    const uint8_t* data = (const uint8_t*)prevblock;
    const int START = 48;
    const int MASK = 0xF;

    int pos = START + (index & MASK);
    int pos_rev = 63 - pos;
//    int nibble = (pos_rev & 1) ? (data[pos_rev >> 1] & 0xF) : (data[pos_rev >> 1] >> 4);
    int nibble = GetNibble(data, pos);

    // Fast path: 75-85% of cases
    if (nibble < 12) return nibble;

    // Slow path: search next 15 positions
    for (int i = 1; i < 16; ++i) {
        pos = START + ((index + i) & MASK);
	pos_rev = 63 - pos;
        //nibble = (pos_rev & 1) ? (data[pos_rev >> 1] & 0xF) : (data[pos_rev >> 1] >> 4);
        //nibble = (pos_rev & 1) ?  (data[pos_rev >> 1] >> 4) : (data[pos_rev >> 1] & 0xF);
	nibble = GetNibble(data, pos);
        if (nibble < 12) return nibble;
    }

    // Fallback: mathematically guaranteed to be 0-11
    return nibble % 12;
}

static void getAlgoString(const uint32_t* prevblock, char *output)
{
    char *sptr = output;

    for (uint8_t j = 0; j < HASH_FUNC_COUNT; j++) {
        int hashSelection = GetHashSelection(prevblock, j);
        if (hashSelection >= 10)
            sprintf(sptr, "%c", 'A' + (hashSelection - 10));
        else
            sprintf(sptr, "%u", (uint32_t) hashSelection);
        sptr++;
    }
    *sptr = '\0';
}

static void getprevblock(const uint32_t timeStamp, void* prevblock)
{
    int32_t maskedTime = timeStamp & TIME_MASK;
    sha256d((unsigned char*)prevblock, (const unsigned char*)&(maskedTime), sizeof(maskedTime));
}

void soterg_hash(const char* input, char* output, uint32_t len)
{
    unsigned char _ALIGN(64) hash[128];
    char hashOrder[HASH_FUNC_COUNT + 1] = { 0 };

    sph_blake512_context ctx_blake;
    sph_shabal512_context ctx_shabal;
    sph_groestl512_context ctx_groestl;
    sph_jh512_context ctx_jh;
    sph_keccak512_context ctx_keccak;
    sph_skein512_context ctx_skein;
    sph_luffa512_context ctx_luffa;
    sph_cubehash512_context ctx_cubehash;
    sph_sha512_context ctx_sha512;
    sph_simd512_context ctx_simd;
    sph_echo512_context ctx_echo;
    sph_hamsi512_context ctx_hamsi;

    void *in = (void*) input;
    int size = 80;

    uint32_t *in32 = (uint32_t*)input;
    uint32_t ntime = in32[17];

    uint32_t _ALIGN(64) prevblock[8];
    getprevblock(ntime, &prevblock);
    getAlgoString(&prevblock[0], hashOrder);

    for (int i = 0; i < 12; i++)
    {
        const char elem = hashOrder[i];
        const uint8_t algo = elem >= 'A' ? elem - 'A' + 10 : elem - '0';

        switch (algo)
        {
            case BLAKE:
                sph_blake512_init(&ctx_blake);
                sph_blake512(&ctx_blake, in, size);
                sph_blake512_close(&ctx_blake, hash);
                break;
            case KECCAK:
                sph_keccak512_init(&ctx_keccak);
                sph_keccak512(&ctx_keccak, in, size);
                sph_keccak512_close(&ctx_keccak, hash);
                break;
            case SKEIN:
                sph_skein512_init(&ctx_skein);
                sph_skein512(&ctx_skein, in, size);
                sph_skein512_close(&ctx_skein, hash);
                break;
            case LUFFA:
                sph_luffa512_init(&ctx_luffa);
                sph_luffa512(&ctx_luffa, in, size);
                sph_luffa512_close(&ctx_luffa, hash);
                break;
            case CUBEHASH:
                sph_cubehash512_init(&ctx_cubehash);
                sph_cubehash512(&ctx_cubehash, in, size);
                sph_cubehash512_close(&ctx_cubehash, hash);
                break;
            case SIMD:
                sph_simd512_init(&ctx_simd);
                sph_simd512(&ctx_simd, in, size);
                sph_simd512_close(&ctx_simd, hash);
                break;
            case HAMSI:
                sph_hamsi512_init(&ctx_hamsi);
                sph_hamsi512(&ctx_hamsi, in, size);
                sph_hamsi512_close(&ctx_hamsi, hash);
                break;
            case SHA512:
                sph_sha512_init(&ctx_sha512);
                sph_sha512(&ctx_sha512,(const void*) in, size);
                sph_sha512_close(&ctx_sha512,(void*) hash);
                break;
            case JH:
                sph_jh512_init(&ctx_jh);
                sph_jh512(&ctx_jh, in, size);
                sph_jh512_close(&ctx_jh, hash);
                break;
            case SHABAL:
                sph_shabal512_init(&ctx_shabal);
                sph_shabal512(&ctx_shabal, in, size);
                sph_shabal512_close(&ctx_shabal, hash);
                break;
            case GROESTL:
                sph_groestl512_init(&ctx_groestl);
                sph_groestl512(&ctx_groestl, in, size);
                sph_groestl512_close(&ctx_groestl, hash);
                break;
            case ECHO:
                sph_echo512_init(&ctx_echo);
                sph_echo512(&ctx_echo, in, size);
                sph_echo512_close(&ctx_echo, hash);
                break;
        }
        in = (void*) hash;
        size = 64;
    }
    memcpy(output, hash, 32);
}
