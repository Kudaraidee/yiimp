#include <stdio.h>
#include <stdlib.h>
#include <string.h>

#include "third_party/sph/sph_blake.h"
#include "third_party/sph/sph_bmw.h"
#include "third_party/sph/sph_groestl.h"
#include "third_party/sph/sph_skein.h"
#include "third_party/sph/sph_jh.h"
#include "third_party/sph/sph_keccak.h"
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

#define _ALIGN(x) __attribute__ ((aligned(x)))

void skydoge_hash(const char* input, char* output, uint32_t len)
{
    	sph_blake512_context     ctx_blake;
    	sph_bmw512_context       ctx_bmw;
    	sph_groestl512_context   ctx_groestl;
    	sph_skein512_context     ctx_skein;
    	sph_jh512_context        ctx_jh;
    	sph_keccak512_context    ctx_keccak;
    	sph_luffa512_context     ctx_luffa1;
    	sph_cubehash512_context  ctx_cubehash1;
    	sph_shavite512_context   ctx_shavite1;
    	sph_simd512_context      ctx_simd1;
    	sph_echo512_context      ctx_echo1;
    	sph_hamsi512_context     ctx_hamsi1;
    	sph_fugue512_context     ctx_fugue1;
    	sph_shabal512_context    ctx_shabal1;
    	sph_whirlpool_context    ctx_whirlpool1;
    	sph_sha512_context       ctx_sha512;
    	sph_sha256_context       ctx_sha;
    	sph_haval256_5_context   ctx_haval;

    	uint32_t hash[16];
    	uint32_t hashA[16];

    	sph_blake512_init(&ctx_blake);
    	sph_blake512 (&ctx_blake, input, len);
    	sph_blake512_close (&ctx_blake, hash);

    	sph_skein512_init(&ctx_skein);
    	sph_skein512 (&ctx_skein, hash, 64);
    	sph_skein512_close (&ctx_skein, hash);

    	sph_bmw512_init(&ctx_bmw);
    	sph_bmw512 (&ctx_bmw, hash, 64);
	sph_bmw512_close(&ctx_bmw, hash);
	
    	sph_groestl512_init(&ctx_groestl);
    	sph_groestl512 (&ctx_groestl, hash, 64);
    	sph_groestl512_close(&ctx_groestl, hash);

    	sph_jh512_init(&ctx_jh);
    	sph_jh512 (&ctx_jh, hash, 64);
    	sph_jh512_close(&ctx_jh, hash);

    	sph_luffa512_init (&ctx_luffa1);
    	sph_luffa512 (&ctx_luffa1, hash, 64);
    	sph_luffa512_close (&ctx_luffa1, hash);

    	sph_keccak512_init(&ctx_keccak);
    	sph_keccak512 (&ctx_keccak, hash, 64);
    	sph_keccak512_close(&ctx_keccak, hash); 

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

    	sph_hamsi512_init (&ctx_hamsi1);
    	sph_hamsi512 (&ctx_hamsi1, hash, 64);
    	sph_hamsi512_close(&ctx_hamsi1, hash);

    	sph_fugue512_init (&ctx_fugue1);
    	sph_fugue512 (&ctx_fugue1, hash, 64);
    	sph_fugue512_close(&ctx_fugue1, hash);

    	sph_shabal512_init (&ctx_shabal1);
    	sph_shabal512 (&ctx_shabal1, hash, 64);
    	sph_shabal512_close(&ctx_shabal1, hash);

    	sph_whirlpool_init (&ctx_whirlpool1);
    	sph_whirlpool (&ctx_whirlpool1, hash, 64);
    	sph_whirlpool_close(&ctx_whirlpool1, hash);

    	sph_sha512_init(&ctx_sha512);
    	sph_sha512(&ctx_sha512, hash, 64);
    	sph_sha512_close(&ctx_sha512, hash);

    	sph_simd512_init (&ctx_simd1);
    	sph_simd512 (&ctx_simd1, hash, 64);
    	sph_simd512_close(&ctx_simd1, hash);

    	sph_whirlpool_init (&ctx_whirlpool1);
    	sph_whirlpool (&ctx_whirlpool1, hash, 64);
    	sph_whirlpool_close(&ctx_whirlpool1, hash);

    	sph_sha256_init(&ctx_sha);
    	sph_sha256 (&ctx_sha, hash, 64);
    	sph_sha256_close(&ctx_sha, hashA);

   	for (int i=8;i<16;i++)
        	hashA[i]=0;

    	sph_haval256_5_init(&ctx_haval);
    	sph_haval256_5(&ctx_haval, hashA, 64);
    	sph_haval256_5_close(&ctx_haval,hash);

	memcpy(output, hash, 32);
}
