#include <stdio.h>
#include <stdlib.h>
#include <string.h>

#include <third_party/sph/sph_sha2.h>
#include <third_party/sph/sph_haval.h>
#include <third_party/sph/sph_tiger.h>
#include <third_party/sph/sph_panama.h>
// reference BLAKE2 (algos/blake2-ref), the implementation libalgos.a links.
// sha3/blake2s.h declares a same-named blake2s() with another signature;
// calling that one hit the blake2-ref symbol and left the hash all-zero.
#include "third_party/blake2-ref/blake2.h"

#include "third_party/SWIFFTX/SWIFFTX.h"
#include "algos/misc/lane.h"
#include <third_party/sph/sph_streebog.h>
#include "algos/lyra2/Lyra2.h"

#include "algos/common/common.h"
#include "algos/common/sph512.h"

void x25x_hash(const char* input, char* output, uint32_t len)
{
        sph_haval256_5_context    ctx_haval;
        sph_tiger_context         ctx_tiger;
        sph_sha256_context        ctx_sha;
        sph_panama_context        ctx_panama;

//	unsigned char _ALIGN(128) hash[25][64] = { [0 ... 24] = { [0 ... 63] = 0 } };
	unsigned char _ALIGN(128) hash[25][64] = { 0 };

	sph512(SPH_BLAKE512, input, len, &hash[0]);

	sph512(SPH_BMW512, &hash[0], 64, &hash[1]);

	sph512(SPH_GROESTL512, &hash[1], 64, &hash[2]);

	sph512(SPH_SKEIN512, &hash[2], 64, &hash[3]);

	sph512(SPH_JH512, &hash[3], 64, &hash[4]);

	sph512(SPH_KECCAK512, &hash[4], 64, &hash[5]);

	sph512(SPH_LUFFA512, &hash[5], 64, &hash[6]);

	sph512(SPH_CUBEHASH512, &hash[6], 64, &hash[7]);

	sph512(SPH_SHAVITE512, &hash[7], 64, &hash[8]);

	sph512(SPH_SIMD512, &hash[8], 64, &hash[9]);

	sph512(SPH_ECHO512, &hash[9], 64, &hash[10]);

	sph512(SPH_HAMSI512, &hash[10], 64, &hash[11]);

	sph512(SPH_FUGUE512, &hash[11], 64, &hash[12]);

	sph512(SPH_SHABAL512, (const void*) &hash[12], 64, &hash[13]);

	sph512(SPH_WHIRLPOOL, (const void*) &hash[13], 64, &hash[14]);

	sph512(SPH_SHA512, (const void*) &hash[14], 64, (void*) &hash[15]);

	unsigned char temp[SWIFFTX_OUTPUT_BLOCK_SIZE] = {0};
	InitializeSWIFFTX();
	ComputeSingleSWIFFTX((unsigned char*)&hash[12], temp, false);
	memcpy((unsigned char*)&hash[16], temp, 64);

	sph_haval256_5_init(&ctx_haval);
	sph_haval256_5(&ctx_haval,(const void*) &hash[16], 64);
	sph_haval256_5_close(&ctx_haval,&hash[17]);

	sph_tiger_init(&ctx_tiger);
	sph_tiger (&ctx_tiger, (const void*) &hash[17], 64);
	sph_tiger_close(&ctx_tiger, (void*) &hash[18]);

	LYRA2((void*) &hash[19], 32, (const void*) &hash[18], 32, (const void*) &hash[18], 32, 1, 4, 4);

	sph512(SPH_GOST512, (const void*) &hash[19], 64, (void*) &hash[20]);

	sph_sha256_init(&ctx_sha);
	sph_sha256 (&ctx_sha, (const void*) &hash[20], 64);
	sph_sha256_close(&ctx_sha, (void*) &hash[21]);

	sph_panama_init(&ctx_panama);
	sph_panama (&ctx_panama, (const void*) &hash[21], 64 );
	sph_panama_close(&ctx_panama, (void*) &hash[22]);

	laneHash(512, (const BitSequence*) &hash[22], 512, (BitSequence*) &hash[23]);

	// NEW simple shuffle algorithm, instead of just reversing
	#define X25X_SHUFFLE_BLOCKS (24 /* number of algos so far */ * 64 /* output bytes per algo */ / 2 /* block size */)
	#define X25X_SHUFFLE_ROUNDS 12

	static const uint16_t x25x_round_const[X25X_SHUFFLE_ROUNDS] = {
		0x142c, 0x5830, 0x678c, 0xe08c,
		0x3c67, 0xd50d, 0xb1d8, 0xecb2,
		0xd7ee, 0x6783, 0xfa6c, 0x4b9c
	};

	uint16_t* block_pointer = (uint16_t*)hash;
	for (int r = 0; r < X25X_SHUFFLE_ROUNDS; r++) {
		for (int i = 0; i < X25X_SHUFFLE_BLOCKS; i++) {
			uint16_t block_value = block_pointer[X25X_SHUFFLE_BLOCKS - i - 1];
			block_pointer[i] ^= block_pointer[block_value % X25X_SHUFFLE_BLOCKS] + (x25x_round_const[r] << (i % 16));
		}
	}

	blake2s((void*)&hash[24], 32, (const void*)(&hash[0]), 64 * 24, NULL, 0);

	memcpy(output, &hash[24], 32);
}
