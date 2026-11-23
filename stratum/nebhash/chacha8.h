#ifndef NEB_CHACHA8_H
#define NEB_CHACHA8_H
#include <stddef.h>
#include <stdint.h>

/* ChaCha8 (8 rounds, 4 double rounds) in the original layout of D. J. Bernstein: 64-bit block counter in state
 * words 12 and 13, 64-bit IV in words 14 and 15, key words 4 to 11, output words little-endian. Consensus-critical:
 * it expands keys into sample indices, the initial model and the test dataset (spec/shares.md section 3). */
void chacha8_block(const uint8_t key[32], const uint8_t iv[8], uint64_t counter, uint8_t out[64]);

/* The first n bytes of the keystream: blocks with counters 0, 1, 2, ... and an all-zero IV, concatenated. */
void chacha8_stream(const uint8_t key[32], uint8_t *out, size_t n);

#endif
