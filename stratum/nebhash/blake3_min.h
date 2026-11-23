#ifndef NEB_BLAKE3_MIN_H
#define NEB_BLAKE3_MIN_H
#include <stddef.h>
#include <stdint.h>

/* BLAKE3 in default hash mode with a 32-byte output, for any input length (one chunk or the chunk tree). Portable
 * C. Consensus-critical: it derives model seeds, share keys, tickets and state hashes (spec/shares.md section 3).
 * The keyed and key-derivation modes, longer outputs and incremental input are not implemented. */
void blake3_hash(const uint8_t *in, size_t len, uint8_t out[32]);

#endif
