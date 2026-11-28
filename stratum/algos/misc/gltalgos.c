#include "gltalgos.h"
#include <stdlib.h>
#include <stdint.h>
#include <string.h>
#include <stdio.h>

#include "third_party/blake2-ref/blake2.h"

#include "third_party/sph/sph_blake.h"
#include "third_party/sph/sph_bmw.h"
#include "third_party/sph/sph_groestl.h"
#include "third_party/sph/sph_jh.h"
#include "third_party/sph/sph_keccak.h"
#include "third_party/sph/sph_skein.h"
#include "third_party/sph/sph_luffa.h"
#include "third_party/sph/sph_cubehash.h"
#include "third_party/sph/sph_shavite.h"
#include "third_party/sph/sph_simd.h"
#include "third_party/sph/sph_echo.h"
#include "third_party/sph/sph_hamsi.h"
#include "third_party/sph/sph_fugue.h"
#include "third_party/sph/sph_shabal.h"
#include "third_party/sph/sph_whirlpool.h"
#include "third_party/sph/sph_sha2.h"
#include "third_party/sph/sph_haval.h"
#include <third_party/sph/sph_streebog.h>


void pawelhash_hash(const char* input, char* output, uint32_t len)
{
    sph_fugue512_context     ctx_fugue;
    sph_sha512_context       ctx_sha2;
    sph_skein512_context     ctx_skein;
    sph_jh512_context        ctx_jh;
    sph_keccak512_context    ctx_keccak;
    sph_luffa512_context     ctx_luffa;
    sph_whirlpool_context    ctx_whirlpool;
    sph_shabal512_context    ctx_shabal;
    sph_echo512_context      ctx_echo;
    sph_groestl512_context   ctx_groestl;
    sph_haval256_5_context   ctx_haval;
    sph_bmw512_context       ctx_bmw;
    sph_gost512_context      ctx_gost;

    //these uint512 in the c++ source of the client are backed by an array of uint32
    uint32_t hashA[16], hashB[16];	

    sph_fugue512_init(&ctx_fugue);
    sph_fugue512(&ctx_fugue, input, len);
    sph_fugue512_close(&ctx_fugue, hashA);

    sph_sha512_init(&ctx_sha2);
    sph_sha512(&ctx_sha2, hashA, 64);
    sph_sha512_close(&ctx_sha2, hashB);
    
    sph_skein512_init(&ctx_skein);
    sph_skein512(&ctx_skein, hashB, 64);
    sph_skein512_close(&ctx_skein, hashA);

    sph_jh512_init(&ctx_jh);
    sph_jh512(&ctx_jh, hashA, 64);
    sph_jh512_close(&ctx_jh, hashB);

    sph_keccak512_init(&ctx_keccak);
    sph_keccak512(&ctx_keccak, hashB, 64);
    sph_keccak512_close(&ctx_keccak, hashA);
    
    sph_luffa512_init(&ctx_luffa);
    sph_luffa512(&ctx_luffa, hashA, 64);
    sph_luffa512_close(&ctx_luffa, hashB);
    
    sph_whirlpool_init(&ctx_whirlpool);
    sph_whirlpool(&ctx_whirlpool, hashB, 64);
    sph_whirlpool_close(&ctx_whirlpool, hashA);
    
    sph_shabal512_init(&ctx_shabal);
    sph_shabal512(&ctx_shabal, hashA, 64);
    sph_shabal512_close(&ctx_shabal, hashB);
    
    sph_echo512_init(&ctx_echo);
    sph_echo512(&ctx_echo, hashB, 64);
    sph_echo512_close(&ctx_echo, hashA);
    
    sph_groestl512_init(&ctx_groestl);
    sph_groestl512(&ctx_groestl, hashA, 64);
    sph_groestl512_close(&ctx_groestl, hashB);
    
    sph_haval256_5_init(&ctx_haval);
    sph_haval256_5(&ctx_haval, hashB, 64);
    sph_haval256_5_close(&ctx_haval, hashA);
    
    memset(&hashA[8], 0, 32);
    
    sph_bmw512_init(&ctx_bmw);
    sph_bmw512(&ctx_bmw, hashA, 64);
    sph_bmw512_close(&ctx_bmw, hashB);
    
    sph_echo512_init(&ctx_echo);
    sph_echo512(&ctx_echo, hashB, 64);
    sph_echo512_close(&ctx_echo, hashA);
    
    sph_fugue512_init(&ctx_fugue);
    sph_fugue512(&ctx_fugue, hashA, 64);
    sph_fugue512_close(&ctx_fugue, hashB);
    
    sph_bmw512_init(&ctx_bmw);
    sph_bmw512(&ctx_bmw, hashB, 64);
    sph_bmw512_close(&ctx_bmw, hashA);

    sph_gost512_init(&ctx_gost);
    sph_gost512(&ctx_gost, hashA, 64);
    sph_gost512_close(&ctx_gost, hashB);
    
    sph_shabal512_init(&ctx_shabal);
    sph_shabal512(&ctx_shabal, hashB, 64);
    sph_shabal512_close(&ctx_shabal, hashA);
    
    sph_whirlpool_init(&ctx_whirlpool);
    sph_whirlpool(&ctx_whirlpool, hashA, 64);
    sph_whirlpool_close(&ctx_whirlpool, hashB);
    
    sph_groestl512_init(&ctx_groestl);
    sph_groestl512 (&ctx_groestl, hashB, 64);
    sph_groestl512_close(&ctx_groestl, hashA);

    memcpy(output, hashA, 32);
}

void jeonghash_hash(const char* input, char* output, uint32_t len)
{
    sph_simd512_context      ctx_simd;
    sph_hamsi512_context     ctx_hamsi;
    sph_shabal512_context    ctx_shabal;
    sph_blake512_context     ctx_blake;
    sph_bmw512_context       ctx_bmw;
    sph_sha512_context       ctx_sha2;
    sph_whirlpool_context    ctx_whirlpool;
    sph_skein512_context     ctx_skein;

    //these uint512 in the c++ source of the client are backed by an array of uint32
    uint32_t hashA[16], hashB[16];

    sph_simd512_init(&ctx_simd);
    sph_simd512(&ctx_simd, input, len);
    sph_simd512_close(&ctx_simd, hashA);

    sph_hamsi512_init(&ctx_hamsi);
    sph_hamsi512(&ctx_hamsi, hashA, 64);
    sph_hamsi512_close(&ctx_hamsi, hashB);
    
    sph_shabal512_init(&ctx_shabal);
    sph_shabal512(&ctx_shabal, hashB, 64);
    sph_shabal512_close(&ctx_shabal, hashA);
    
    sph_blake512_init(&ctx_blake);
    sph_blake512(&ctx_blake, hashA, 64);
    sph_blake512_close(&ctx_blake, hashB);
    
    sph_bmw512_init(&ctx_bmw);
    sph_bmw512(&ctx_bmw, hashB, 64);
    sph_bmw512_close(&ctx_bmw, hashA);
    
    sph_sha512_init(&ctx_sha2);
    sph_sha512(&ctx_sha2, hashA, 64);
    sph_sha512_close(&ctx_sha2, hashB);
    
    sph_whirlpool_init(&ctx_whirlpool);
    sph_whirlpool(&ctx_whirlpool, hashB, 64);
    sph_whirlpool_close(&ctx_whirlpool, hashA);
    
    sph_skein512_init(&ctx_skein);
    sph_skein512(&ctx_skein, hashA, 64);
    sph_skein512_close(&ctx_skein, hashB);
    
    sph_skein512_init(&ctx_skein);
    sph_skein512(&ctx_skein, hashB, 64);
    sph_skein512_close(&ctx_skein, hashA);
    
    sph_whirlpool_init(&ctx_whirlpool);
    sph_whirlpool(&ctx_whirlpool, hashA, 64);
    sph_whirlpool_close(&ctx_whirlpool, hashB);
    
    sph_sha512_init(&ctx_sha2);
    sph_sha512(&ctx_sha2, hashB, 64);
    sph_sha512_close(&ctx_sha2, hashA);
    
    sph_bmw512_init(&ctx_bmw);
    sph_bmw512(&ctx_bmw, hashA, 64);
    sph_bmw512_close(&ctx_bmw, hashB);
    
    sph_blake512_init(&ctx_blake);
    sph_blake512(&ctx_blake, hashB, 64);
    sph_blake512_close(&ctx_blake, hashA);
    
    sph_shabal512_init(&ctx_shabal);
    sph_shabal512(&ctx_shabal, hashA, 64);
    sph_shabal512_close(&ctx_shabal, hashB);
    
    sph_hamsi512_init(&ctx_hamsi);
    sph_hamsi512(&ctx_hamsi, hashB, 64);
    sph_hamsi512_close(&ctx_hamsi, hashA);
    
    sph_simd512_init(&ctx_simd);
    sph_simd512(&ctx_simd, hashA, 64);
    sph_simd512_close(&ctx_simd, hashB);
    
    sph_simd512_init(&ctx_simd);
    sph_simd512(&ctx_simd, hashB, 64);
    sph_simd512_close(&ctx_simd, hashA);

    sph_hamsi512_init(&ctx_hamsi);
    sph_hamsi512(&ctx_hamsi, hashA, 64);
    sph_hamsi512_close(&ctx_hamsi, hashB);
    
    sph_shabal512_init(&ctx_shabal);
    sph_shabal512(&ctx_shabal, hashB, 64);
    sph_shabal512_close(&ctx_shabal, hashA);
    
    sph_blake512_init(&ctx_blake);
    sph_blake512(&ctx_blake, hashA, 64);
    sph_blake512_close(&ctx_blake, hashB);
    
    sph_bmw512_init(&ctx_bmw);
    sph_bmw512(&ctx_bmw, hashB, 64);
    sph_bmw512_close(&ctx_bmw, hashA);
    
    sph_sha512_init(&ctx_sha2);
    sph_sha512(&ctx_sha2, hashA, 64);
    sph_sha512_close(&ctx_sha2, hashB);
    
    sph_whirlpool_init(&ctx_whirlpool);
    sph_whirlpool(&ctx_whirlpool, hashB, 64);
    sph_whirlpool_close(&ctx_whirlpool, hashA);
    
    sph_skein512_init(&ctx_skein);
    sph_skein512(&ctx_skein, hashA, 64);
    sph_skein512_close(&ctx_skein, hashB);

    memcpy(output, hashB, 32);
}

void astralhash_hash(const char* input, char* output, uint32_t len)
{
    sph_luffa512_context     ctx_luffa;
    sph_skein512_context     ctx_skein;
    sph_echo512_context      ctx_echo;
    sph_whirlpool_context    ctx_whirlpool;
    sph_bmw512_context       ctx_bmw; 
    sph_blake512_context     ctx_blake;
    sph_shavite512_context   ctx_shavite;
    sph_fugue512_context     ctx_fugue;
    sph_hamsi512_context     ctx_hamsi;
    sph_haval256_5_context   ctx_haval;
    sph_sha512_context       ctx_sha2;

    //these uint512 in the c++ source of the client are backed by an array of uint32
    uint32_t hashA[16], hashB[16];	

    sph_luffa512_init(&ctx_luffa);
    sph_luffa512(&ctx_luffa, input, len);
    sph_luffa512_close(&ctx_luffa, hashA);

    sph_skein512_init(&ctx_skein);
    sph_skein512(&ctx_skein, hashA, 64);
    sph_skein512_close(&ctx_skein, hashB);
    
    sph_echo512_init(&ctx_echo);
    sph_echo512(&ctx_echo, hashB, 64);
    sph_echo512_close(&ctx_echo, hashA);
    
    sph_whirlpool_init(&ctx_whirlpool);
    sph_whirlpool(&ctx_whirlpool, hashA, 64);
    sph_whirlpool_close(&ctx_whirlpool, hashB);
    
    sph_bmw512_init(&ctx_bmw);
    sph_bmw512(&ctx_bmw, hashB, 64);
    sph_bmw512_close(&ctx_bmw, hashA);
    
    sph_blake512_init(&ctx_blake);
    sph_blake512(&ctx_blake, hashA, 64);
    sph_blake512_close(&ctx_blake, hashB);
    
    sph_shavite512_init(&ctx_shavite);
    sph_shavite512(&ctx_shavite, hashB, 64);
    sph_shavite512_close(&ctx_shavite, hashA);
    
    sph_skein512_init(&ctx_skein);
    sph_skein512(&ctx_skein, hashA, 64);
    sph_skein512_close(&ctx_skein, hashB);
    
    sph_whirlpool_init(&ctx_whirlpool);
    sph_whirlpool(&ctx_whirlpool, hashB, 64);
    sph_whirlpool_close(&ctx_whirlpool, hashA);
    
    sph_fugue512_init(&ctx_fugue);
    sph_fugue512(&ctx_fugue, hashA, 64);
    sph_fugue512_close(&ctx_fugue, hashB);
    
    sph_hamsi512_init(&ctx_hamsi);
    sph_hamsi512(&ctx_hamsi, hashB, 64);
    sph_hamsi512_close(&ctx_hamsi, hashA);
    
    sph_haval256_5_init(&ctx_haval);
    sph_haval256_5(&ctx_haval, hashA, 64);
    sph_haval256_5_close(&ctx_haval, hashB);
    
    memset(&hashB[8], 0, 32);
    
    sph_sha512_init(&ctx_sha2);
    sph_sha512(&ctx_sha2, hashB, 64);
    sph_sha512_close(&ctx_sha2, hashA);

    memcpy(output, hashA, 32);
}

void padihash_hash(const char* input, char* output, uint32_t len)
{
    sph_sha512_context       ctx_sha2;
    sph_jh512_context        ctx_jh;
    sph_luffa512_context     ctx_luffa;
    sph_echo512_context      ctx_echo;
    sph_bmw512_context       ctx_bmw; 
    sph_haval256_5_context   ctx_haval;
    sph_cubehash512_context  ctx_cubehash;
    sph_shabal512_context    ctx_shabal;

    //these uint512 in the c++ source of the client are backed by an array of uint32
    uint32_t hashA[16], hashB[16];	

    sph_sha512_init(&ctx_sha2);
    sph_sha512(&ctx_sha2, input, len);
    sph_sha512_close(&ctx_sha2, hashA);

    sph_jh512_init(&ctx_jh);
    sph_jh512(&ctx_jh, hashA, 64);
    sph_jh512_close(&ctx_jh, hashB);
    
    sph_luffa512_init(&ctx_luffa);
    sph_luffa512(&ctx_luffa, hashB, 64);
    sph_luffa512_close(&ctx_luffa, hashA);
    
    sph_echo512_init(&ctx_echo);
    sph_echo512(&ctx_echo, hashA, 64);
    sph_echo512_close(&ctx_echo, hashB);
    
    sph_bmw512_init(&ctx_bmw);
    sph_bmw512(&ctx_bmw, hashB, 64);
    sph_bmw512_close(&ctx_bmw, hashA);
    
    sph_haval256_5_init(&ctx_haval);
    sph_haval256_5(&ctx_haval, hashA, 64);
    sph_haval256_5_close(&ctx_haval, hashB);
    
    memset(&hashB[8], 0, 32);
    
    sph_cubehash512_init(&ctx_cubehash);
    sph_cubehash512(&ctx_cubehash, hashB, 64);
    sph_cubehash512_close(&ctx_cubehash, hashA);
    
    sph_shabal512_init(&ctx_shabal);
    sph_shabal512(&ctx_shabal, hashA, 64);
    sph_shabal512_close(&ctx_shabal, hashB);
    
    sph_sha512_init(&ctx_sha2);
    sph_sha512(&ctx_sha2, hashB, 64);
    sph_sha512_close(&ctx_sha2, hashA);

    sph_jh512_init(&ctx_jh);
    sph_jh512(&ctx_jh, hashA, 64);
    sph_jh512_close(&ctx_jh, hashB);
    
    sph_luffa512_init(&ctx_luffa);
    sph_luffa512(&ctx_luffa, hashB, 64);
    sph_luffa512_close(&ctx_luffa, hashA);
    
    sph_echo512_init(&ctx_echo);
    sph_echo512(&ctx_echo, hashA, 64);
    sph_echo512_close(&ctx_echo, hashB);
    
    sph_bmw512_init(&ctx_bmw);
    sph_bmw512(&ctx_bmw, hashB, 64);
    sph_bmw512_close(&ctx_bmw, hashA);
    
    sph_haval256_5_init(&ctx_haval);
    sph_haval256_5(&ctx_haval, hashA, 64);
    sph_haval256_5_close(&ctx_haval, hashB);
    
    memset(&hashB[8], 0, 32);
    
    sph_cubehash512_init(&ctx_cubehash);
    sph_cubehash512(&ctx_cubehash, hashB, 64);
    sph_cubehash512_close(&ctx_cubehash, hashA);
    
    sph_shabal512_init(&ctx_shabal);
    sph_shabal512(&ctx_shabal, hashA, 64);
    sph_shabal512_close(&ctx_shabal, hashB);
    
    sph_shabal512_init(&ctx_shabal);
    sph_shabal512(&ctx_shabal, hashB, 64);
    sph_shabal512_close(&ctx_shabal, hashA);
    
    sph_cubehash512_init(&ctx_cubehash);
    sph_cubehash512(&ctx_cubehash, hashA, 64);
    sph_cubehash512_close(&ctx_cubehash, hashB);
    
    sph_haval256_5_init(&ctx_haval);
    sph_haval256_5(&ctx_haval, hashB, 64);
    sph_haval256_5_close(&ctx_haval, hashA);
    
    memset(&hashA[8], 0, 32);
    
    sph_bmw512_init(&ctx_bmw);
    sph_bmw512(&ctx_bmw, hashA, 64);
    sph_bmw512_close(&ctx_bmw, hashB);
    
    sph_echo512_init(&ctx_echo);
    sph_echo512(&ctx_echo, hashB, 64);
    sph_echo512_close(&ctx_echo, hashA);
    
    sph_luffa512_init(&ctx_luffa);
    sph_luffa512(&ctx_luffa, hashA, 64);
    sph_luffa512_close(&ctx_luffa, hashB);
    
    sph_jh512_init(&ctx_jh);
    sph_jh512(&ctx_jh, hashB, 64);
    sph_jh512_close(&ctx_jh, hashA);
    
    sph_sha512_init(&ctx_sha2);
    sph_sha512(&ctx_sha2, hashA, 64);
    sph_sha512_close(&ctx_sha2, hashB);
    
    sph_jh512_init(&ctx_jh);
    sph_jh512(&ctx_jh, hashB, 64);
    sph_jh512_close(&ctx_jh, hashA);
    
    sph_bmw512_init(&ctx_bmw);
    sph_bmw512(&ctx_bmw, hashA, 64);
    sph_bmw512_close(&ctx_bmw, hashB);

    memcpy(output, hashB, 32);
}

void globalhash_hash(const char* input, char* output, uint32_t len)
{
    sph_gost512_context      ctx_gost;
    sph_blake512_context     ctx_blake;
    blake2b_state            ctx_blake2b[1];
    blake2s_state            ctx_blake2s[1];
    
    //these uint512 in the c++ source of the client are backed by an array of uint32
    uint32_t hashA[16], hashB[16], finalhash[8]; // finalhash is a 256 unsigned integer
    
    sph_gost512_init(&ctx_gost);
    sph_gost512 (&ctx_gost, input, len); 
    sph_gost512_close(&ctx_gost, hashA);
    
    sph_blake512_init(&ctx_blake);
    sph_blake512(&ctx_blake, hashA, 64);
    sph_blake512_close(&ctx_blake, hashB);
    
    blake2b_init( ctx_blake2b, BLAKE2B_OUTBYTES );
    blake2b_update( ctx_blake2b, hashB, 64 );
    blake2b_final( ctx_blake2b, hashA, BLAKE2B_OUTBYTES );
    
    blake2s_init( ctx_blake2s, BLAKE2S_OUTBYTES );
    blake2s_update( ctx_blake2s, hashA, 64);
    blake2s_final( ctx_blake2s, finalhash, BLAKE2S_OUTBYTES );

    memcpy(output, finalhash, 32);
}