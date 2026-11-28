#include <stdlib.h>
#include <stdint.h>
#include <string.h>
#include <stdio.h>

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
#include "third_party/sph/sph_streebog.h"
#include "algos/lyra2/Lyra2-z.h"

#define _ALIGN(x) __attribute__ ((aligned(x)))

void cosa_hash(const char* input, char* output, uint32_t len)
{
	unsigned char _ALIGN(128) hash[128],hashB[128],hashC[128],hashD[128];

	sph_blake512_context     ctx_blake;
	sph_bmw512_context       ctx_bmw;
	sph_groestl512_context   ctx_groestl;
	sph_jh512_context        ctx_jh;
	sph_keccak512_context    ctx_keccak;
	sph_skein512_context     ctx_skein;
	sph_luffa512_context     ctx_luffa;
	sph_cubehash512_context  ctx_cubehash;
	sph_shavite512_context   ctx_shavite;
	sph_simd512_context      ctx_simd;
	sph_echo512_context      ctx_echo;
	sph_hamsi512_context     ctx_hamsi;
	sph_fugue512_context     ctx_fugue;
	sph_shabal512_context    ctx_shabal;
	sph_whirlpool_context    ctx_whirlpool;
	sph_sha512_context       ctx_sha512;
	sph_haval256_5_context   ctx_haval;
	sph_gost512_context      ctx_gost;

	sph_blake512_init(&ctx_blake);
	sph_blake512(&ctx_blake, input, len);
	sph_blake512_close(&ctx_blake, hash);

	sph_bmw512_init(&ctx_bmw);
	sph_bmw512(&ctx_bmw, hash, 64);
	sph_bmw512_close(&ctx_bmw, hash);

	sph_groestl512_init(&ctx_groestl);
	sph_groestl512(&ctx_groestl, hash, 64);
	sph_groestl512_close(&ctx_groestl, hash);

	sph_skein512_init(&ctx_skein);
	sph_skein512(&ctx_skein, hash, 64);
	sph_skein512_close(&ctx_skein, hash);

	sph_jh512_init(&ctx_jh);
	sph_jh512(&ctx_jh, hash, 64);
	sph_jh512_close(&ctx_jh, hash);

	sph_keccak512_init(&ctx_keccak);
	sph_keccak512(&ctx_keccak, hash, 64);
	sph_keccak512_close(&ctx_keccak, hash);

	sph_luffa512_init(&ctx_luffa);
	sph_luffa512(&ctx_luffa, hash, 64);
	sph_luffa512_close(&ctx_luffa, hash);

	sph_cubehash512_init(&ctx_cubehash);
	sph_cubehash512(&ctx_cubehash, hash, 64);
	sph_cubehash512_close(&ctx_cubehash, hash);

	sph_shavite512_init(&ctx_shavite);
	sph_shavite512(&ctx_shavite, hash, 64);
	sph_shavite512_close(&ctx_shavite, hash);

	sph_simd512_init(&ctx_simd);
	sph_simd512(&ctx_simd, hash, 64);
	sph_simd512_close(&ctx_simd, hash);

	sph_echo512_init(&ctx_echo);
	sph_echo512(&ctx_echo, hash, 64);
	sph_echo512_close(&ctx_echo, hash);

	sph_hamsi512_init(&ctx_hamsi);
	sph_hamsi512(&ctx_hamsi, hash, 64);
	sph_hamsi512_close(&ctx_hamsi, hash);

	sph_fugue512_init(&ctx_fugue);
	sph_fugue512(&ctx_fugue, hash, 64);
	sph_fugue512_close(&ctx_fugue, hash);

	sph_shabal512_init(&ctx_shabal);
	sph_shabal512(&ctx_shabal, hash, 64);
	sph_shabal512_close(&ctx_shabal, hash);

	sph_whirlpool_init(&ctx_whirlpool);
	sph_whirlpool(&ctx_whirlpool, hash, 64);
	sph_whirlpool_close(&ctx_whirlpool, hash);

	sph_sha512_init(&ctx_sha512);
	sph_sha512(&ctx_sha512,(const void*) hash, 64);
	sph_sha512_close(&ctx_sha512,(void*) hash);

	memset (hashB,0x0,128);
	memset (hashC,0x0,128);
	memset (hashD,0x0,128); 

	sph_haval256_5_init(&ctx_haval);
	sph_haval256_5(&ctx_haval,(const void*) hash, 64);
	sph_haval256_5_close(&ctx_haval, hashB);

	sph_gost512_init(&ctx_gost);
	sph_gost512(&ctx_gost, (const void*) hashB, 64);
	sph_gost512_close(&ctx_gost, (void*) hashC);

	LYRA2z(hashD, 32, hashC, 80, hashC, 80, 2, 66, 66);

	memcpy(output, hashD, 32);
}