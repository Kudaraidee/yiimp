#ifndef NEB_BLAKE3_SHARED_H
#define NEB_BLAKE3_SHARED_H
#include <stddef.h>
#include <stdint.h>

/* One-shot BLAKE3 default-mode hash with a 32-byte output, backed by the
 * canonical implementation in stratum/algos/blake3/. This keeps nebhash on
 * exactly the same primitive as xelisv2 without a second consensus-critical
 * BLAKE3 implementation. */
void neb_blake3_hash(const uint8_t *in, size_t len, uint8_t out[32]);

#endif
