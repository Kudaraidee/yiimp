// One sph hash with 64-byte output in one call (init, update, close), for
// the x11-style chains.

#pragma once

#include <stddef.h>
#include <stdint.h>

#ifdef __cplusplus
extern "C" {
#endif

enum sph512_algo {
	SPH_BLAKE512,
	SPH_BMW512,
	SPH_GROESTL512,
	SPH_SKEIN512,
	SPH_JH512,
	SPH_KECCAK512,
	SPH_LUFFA512,
	SPH_CUBEHASH512,
	SPH_SHAVITE512,
	SPH_SIMD512,
	SPH_ECHO512,
	SPH_HAMSI512,
	SPH_FUGUE512,
	SPH_SHABAL512,
	SPH_WHIRLPOOL,
	SPH_SHA512,
	SPH_GOST512,
};

// out: 64 bytes; it may be the same buffer as in
void sph512(enum sph512_algo algo, const void *in, size_t len, void *out);

#ifdef __cplusplus
}
#endif
