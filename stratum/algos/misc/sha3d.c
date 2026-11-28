#include <stdint.h>
#include <stdlib.h>
#include <string.h>
#include <stdio.h>

#include "third_party/sph/sph_types.h"
#include "third_party/sph/sph_sha3d.h"

#define _ALIGN(x) __attribute__ ((aligned(x)))

void sha3d_hash(const char *input, char *output, uint32_t len)
{
	uint32_t _ALIGN(64) buffer[16], hash[16];
	sph_keccak_context ctx_keccak;

	sph_sha3d256_init( &ctx_keccak );
	sph_sha3d256 ( &ctx_keccak, input, len );
	sph_sha3d256_close( &ctx_keccak, (void*) buffer );

	sph_sha3d256_init( &ctx_keccak );
	sph_sha3d256 ( &ctx_keccak, buffer, 32 );
	sph_sha3d256_close( &ctx_keccak, (void*) hash );

	memcpy(output, hash, 32);
}