#include <stdint.h>
#include <string.h>

#include <sha3/sph_blake.h>
#include "algos/blake3/blake3.h"
#include "decred.h"

// Decred proof of work since DCP-0011: BLAKE3-256 over the 180-byte header
void decred_hash(const char* input, char* output, uint32_t len)
{
	if (len > 180) len = 180;

	blake3_hasher hasher;
	blake3_hasher_init(&hasher);
	blake3_hasher_update(&hasher, input, len);
	blake3_hasher_finalize(&hasher, (uint8_t *) output, BLAKE3_OUT_LEN);
}

// Decred block id: BLAKE-256 (14 rounds) of the 180-byte header, unchanged
// by DCP-0011, which only replaced the proof-of-work hash
void decred_block_hash(const char* input, char* output, uint32_t len)
{
	sph_blake256_context ctx_blake;

	sph_blake256_set_rounds(14);
	if (len > 180) len = 180;

	sph_blake256_init(&ctx_blake);
	sph_blake256(&ctx_blake, input, len);
	sph_blake256_close(&ctx_blake, output);
}
