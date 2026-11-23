#include "chacha8.h"
#include <string.h>

#define ROTL32(v, n) (((v) << (n)) | ((v) >> (32 - (n))))
#define QR(a, b, c, d)                                   \
  do {                                                   \
    a += b; d ^= a; d = ROTL32(d, 16);                   \
    c += d; b ^= c; b = ROTL32(b, 12);                   \
    a += b; d ^= a; d = ROTL32(d, 8);                    \
    c += d; b ^= c; b = ROTL32(b, 7);                    \
  } while (0)

static uint32_t load32_le(const uint8_t *p) {
  return (uint32_t)p[0] | ((uint32_t)p[1] << 8) | ((uint32_t)p[2] << 16) | ((uint32_t)p[3] << 24);
}

static void store32_le(uint8_t *p, uint32_t v) {
  p[0] = (uint8_t)v; p[1] = (uint8_t)(v >> 8); p[2] = (uint8_t)(v >> 16); p[3] = (uint8_t)(v >> 24);
}

void chacha8_block(const uint8_t key[32], const uint8_t iv[8], uint64_t counter, uint8_t out[64]) {
  uint32_t s[16], x[16];
  s[0] = 0x61707865u; s[1] = 0x3320646eu; s[2] = 0x79622d32u; s[3] = 0x6b206574u; /* "expand 32-byte k" */
  for (int i = 0; i < 8; i++) s[4 + i] = load32_le(key + 4 * i);
  s[12] = (uint32_t)counter;
  s[13] = (uint32_t)(counter >> 32);
  s[14] = load32_le(iv);
  s[15] = load32_le(iv + 4);
  memcpy(x, s, sizeof s);
  for (int r = 0; r < 4; r++) { /* one column round and one diagonal round per iteration */
    QR(x[0], x[4], x[8], x[12]);
    QR(x[1], x[5], x[9], x[13]);
    QR(x[2], x[6], x[10], x[14]);
    QR(x[3], x[7], x[11], x[15]);
    QR(x[0], x[5], x[10], x[15]);
    QR(x[1], x[6], x[11], x[12]);
    QR(x[2], x[7], x[8], x[13]);
    QR(x[3], x[4], x[9], x[14]);
  }
  for (int i = 0; i < 16; i++) store32_le(out + 4 * i, x[i] + s[i]); /* feed-forward of the input state */
}

void chacha8_stream(const uint8_t key[32], uint8_t *out, size_t n) {
  const uint8_t iv[8] = {0};
  uint8_t blk[64];
  for (uint64_t counter = 0; n > 0; counter++) {
    size_t m = n < 64 ? n : 64;
    chacha8_block(key, iv, counter, blk);
    memcpy(out, blk, m);
    out += m;
    n -= m;
  }
}
