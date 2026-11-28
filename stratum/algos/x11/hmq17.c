#include <stdio.h>
#include <stdlib.h>
#include <string.h>

#include <third_party/sph/sph_haval.h>
#include "algos/common/sph512.h"

typedef struct
{
	sph_haval256_5_context  haval1, haval2;
} hmq_contexts;

static __thread hmq_contexts base_contexts;
static __thread int hmq_context_init = 0;

static void init_contexts(hmq_contexts *ctx)
{
	sph_haval256_5_init(&ctx->haval1);
	sph_haval256_5_init(&ctx->haval2);
}

void hmq17_hash(const char* input, char* output, uint32_t len)
{
	uint32_t hash[32];

	const uint32_t mask = 24;

	hmq_contexts ctx;

	if (!hmq_context_init) {
		init_contexts(&base_contexts);
		hmq_context_init = 1;
	}
	memcpy(&ctx, &base_contexts, sizeof(hmq_contexts));

	sph512(SPH_BMW512, input, len, hash);

	sph512(SPH_WHIRLPOOL, hash, 64, hash);

	if (hash[0] & mask) {
		sph512(SPH_GROESTL512, hash, 64, hash);
	} else {
		sph512(SPH_SKEIN512, hash, 64, hash);
	}

	sph512(SPH_JH512, hash, 64, hash);

	sph512(SPH_KECCAK512, hash, 64, hash);

	if (hash[0] & mask) {
		sph512(SPH_BLAKE512, hash, 64, hash);
	} else {
		sph512(SPH_BMW512, hash, 64, hash);
	}

	sph512(SPH_LUFFA512, hash, 64, hash);

	sph512(SPH_CUBEHASH512, hash, 64, hash);

	if (hash[0] & mask) {
		sph512(SPH_KECCAK512, hash, 64, hash);
	} else {
		sph512(SPH_JH512, hash, 64, hash);
	}

	sph512(SPH_SHAVITE512, hash, 64, hash);

	sph512(SPH_SIMD512, hash, 64, hash);

	if (hash[0] & mask) {
		sph512(SPH_WHIRLPOOL, hash, 64, hash);
	} else {
		sph_haval256_5(&ctx.haval1, hash, 64);
		sph_haval256_5_close(&ctx.haval1, hash);
		memset(&hash[8], 0, 32);
	}

	sph512(SPH_ECHO512, hash, 64, hash);

	sph512(SPH_BLAKE512, hash, 64, hash);

	if (hash[0] & mask) {
		sph512(SPH_SHAVITE512, hash, 64, hash);
	} else {
		sph512(SPH_LUFFA512, hash, 64, hash);
	}

	sph512(SPH_HAMSI512, hash, 64, hash);

	sph512(SPH_FUGUE512, hash, 64, hash);

	if (hash[0] & mask) {
		sph512(SPH_ECHO512, hash, 64, hash);
	} else {
		sph512(SPH_SIMD512, hash, 64, hash);
	}

	sph512(SPH_SHABAL512, hash, 64, hash);

	sph512(SPH_WHIRLPOOL, hash, 64, hash);

	if (hash[0] & mask) {
		sph512(SPH_FUGUE512, hash, 64, hash);
	} else {
		sph512(SPH_SHA512, hash, 64, hash);
	}

	sph512(SPH_GROESTL512, hash, 64, hash);

	sph512(SPH_SHA512, hash, 64, hash);

	if (hash[0] & mask) {
		sph_haval256_5(&ctx.haval2, hash, 64);
		sph_haval256_5_close(&ctx.haval2, hash);
		memset(&hash[8], 0, 32);
	} else {
		sph512(SPH_WHIRLPOOL, hash, 64, hash);
	}

	sph512(SPH_BMW512, hash, 64, hash);

	memcpy(output, hash, 32);
}
