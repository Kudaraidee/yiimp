#include <stdio.h>
#include <stdlib.h>
#include <string.h>

#include <third_party/sph/sph_sha2.h>
#include <third_party/sph/sph_haval.h>
#include <third_party/sph/sph_tiger.h>

#include "third_party/SWIFFTX/SWIFFTX.h"
#include <third_party/sph/sph_streebog.h>
#include "algos/lyra2/Lyra2.h"

#include "algos/common/common.h"
#include "algos/common/sph512.h"

void x22i_hash(const char* input, char* output, uint32_t len)
{
	sph_haval256_5_context   ctx_haval;
	sph_tiger_context        ctx_tiger;
	sph_sha256_context       ctx_sha;

	unsigned char _ALIGN(128) hash[64 * 4] = {0};
	unsigned char _ALIGN(128) hash2[64];

	sph512(SPH_BLAKE512, input, len, hash);

	sph512(SPH_BMW512, hash, 64, hash);

	sph512(SPH_GROESTL512, hash, 64, hash);

	sph512(SPH_SKEIN512, hash, 64, hash);

	sph512(SPH_JH512, hash, 64, hash);

	sph512(SPH_KECCAK512, hash, 64, hash);

	sph512(SPH_LUFFA512, hash, 64, hash);

	sph512(SPH_CUBEHASH512, hash, 64, hash);

	sph512(SPH_SHAVITE512, hash, 64, hash);

	sph512(SPH_SIMD512, hash, 64, hash);

	sph512(SPH_ECHO512, hash, 64, hash);

	sph512(SPH_HAMSI512, hash, 64, hash);

	sph512(SPH_FUGUE512, hash, 64, hash);

	sph512(SPH_SHABAL512, (const void*) hash, 64, &hash[64]);

	sph512(SPH_WHIRLPOOL, (const void*) &hash[64], 64, &hash[128]);

	sph512(SPH_SHA512, (const void*) &hash[128], 64, (void*) &hash[192]);

	InitializeSWIFFTX();
	ComputeSingleSWIFFTX((unsigned char*)hash, (unsigned char*)hash2, false);

	memset(hash, 0, 64);
	sph_haval256_5_init(&ctx_haval);
	sph_haval256_5(&ctx_haval,(const void*) hash2, 64);
	sph_haval256_5_close(&ctx_haval,hash);

	memset(hash2, 0, 64);
	sph_tiger_init(&ctx_tiger);
	sph_tiger (&ctx_tiger, (const void*) hash, 64);
	sph_tiger_close(&ctx_tiger, (void*) hash2);

	memset(hash, 0, 64);
	LYRA2((void*) hash, 32, (const void*) hash2, 32, (const void*) hash2, 32, 1, 4, 4);

	sph512(SPH_GOST512, (const void*) hash, 64, (void*) hash);

	sph_sha256_init(&ctx_sha);
	sph_sha256 (&ctx_sha, (const void*) hash, 64);
	sph_sha256_close(&ctx_sha, (void*) hash);

	memcpy(output, hash, 32);
}
