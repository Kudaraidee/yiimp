#include "algos/common/sph512.h"

#include <sha3/sph_blake.h>
#include <sha3/sph_bmw.h>
#include <sha3/sph_cubehash.h>
#include <sha3/sph_echo.h>
#include <sha3/sph_fugue.h>
#include <sha3/sph_streebog.h>
#include <sha3/sph_groestl.h>
#include <sha3/sph_hamsi.h>
#include <sha3/sph_jh.h>
#include <sha3/sph_keccak.h>
#include <sha3/sph_luffa.h>
#include <sha3/sph_sha2.h>
#include <sha3/sph_shabal.h>
#include <sha3/sph_shavite.h>
#include <sha3/sph_simd.h>
#include <sha3/sph_skein.h>
#include <sha3/sph_whirlpool.h>

#define SPH512_CASE(ID, NAME, CTX) case SPH_##ID: { CTX ctx; sph_##NAME##_init(&ctx); sph_##NAME(&ctx, in, len); sph_##NAME##_close(&ctx, out); break; }

void sph512(enum sph512_algo algo, const void *in, size_t len, void *out)
{
	switch (algo) {
	SPH512_CASE(BLAKE512, blake512, sph_blake512_context)
	SPH512_CASE(BMW512, bmw512, sph_bmw512_context)
	SPH512_CASE(GROESTL512, groestl512, sph_groestl512_context)
	SPH512_CASE(SKEIN512, skein512, sph_skein512_context)
	SPH512_CASE(JH512, jh512, sph_jh512_context)
	SPH512_CASE(KECCAK512, keccak512, sph_keccak512_context)
	SPH512_CASE(LUFFA512, luffa512, sph_luffa512_context)
	SPH512_CASE(CUBEHASH512, cubehash512, sph_cubehash512_context)
	SPH512_CASE(SHAVITE512, shavite512, sph_shavite512_context)
	SPH512_CASE(SIMD512, simd512, sph_simd512_context)
	SPH512_CASE(ECHO512, echo512, sph_echo512_context)
	SPH512_CASE(HAMSI512, hamsi512, sph_hamsi512_context)
	SPH512_CASE(FUGUE512, fugue512, sph_fugue512_context)
	SPH512_CASE(SHABAL512, shabal512, sph_shabal512_context)
	SPH512_CASE(WHIRLPOOL, whirlpool, sph_whirlpool_context)
	SPH512_CASE(SHA512, sha512, sph_sha512_context)
	SPH512_CASE(GOST512, gost512, sph_gost512_context)
	}
}
