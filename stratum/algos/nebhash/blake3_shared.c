#include "algos/nebhash/blake3_shared.h"
#include "third_party/blake3/blake3.h"

void neb_blake3_hash(const uint8_t *in, size_t len, uint8_t out[32])
{
	blake3_hasher hasher;
	blake3_hasher_init(&hasher);
	blake3_hasher_update(&hasher, in, len);
	blake3_hasher_finalize(&hasher, out, BLAKE3_OUT_LEN);
}
