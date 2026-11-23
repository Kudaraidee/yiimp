#include "blake3_min.h"
#include <string.h>

/* BLAKE3 hash mode, written from the BLAKE3 specification. Inputs of more than 1024 bytes use the chunk tree; the
 * state hashes of spec/shares.md section 7 need it (inputs of 50833 and 406546 bytes). */

/* Domain flags of the compression function. */
enum { CHUNK_START = 1, CHUNK_END = 2, PARENT = 4, ROOT = 8 };
#define CHUNK_LEN 1024
#define BLOCK_LEN 64

/* The initial chaining value of every chunk and the key of every parent node (the same constants as SHA-256). */
static const uint32_t IV[8] = {0x6A09E667u, 0xBB67AE85u, 0x3C6EF372u, 0xA54FF53Au,
                               0x510E527Fu, 0x9B05688Cu, 0x1F83D9ABu, 0x5BE0CD19u};
/* Permutation of the 16 message words applied between two rounds. */
static const uint8_t MSG_PERM[16] = {2, 6, 3, 10, 7, 0, 4, 13, 1, 11, 12, 5, 9, 14, 15, 8};

static uint32_t rotr32(uint32_t x, int n) { return (x >> n) | (x << (32 - n)); }

static uint32_t load32_le(const uint8_t *p) {
  return (uint32_t)p[0] | ((uint32_t)p[1] << 8) | ((uint32_t)p[2] << 16) | ((uint32_t)p[3] << 24);
}

static void store32_le(uint8_t *p, uint32_t v) {
  p[0] = (uint8_t)v; p[1] = (uint8_t)(v >> 8); p[2] = (uint8_t)(v >> 16); p[3] = (uint8_t)(v >> 24);
}

static void g(uint32_t *s, int a, int b, int c, int d, uint32_t mx, uint32_t my) {
  s[a] = s[a] + s[b] + mx; s[d] = rotr32(s[d] ^ s[a], 16);
  s[c] = s[c] + s[d];      s[b] = rotr32(s[b] ^ s[c], 12);
  s[a] = s[a] + s[b] + my; s[d] = rotr32(s[d] ^ s[a], 8);
  s[c] = s[c] + s[d];      s[b] = rotr32(s[b] ^ s[c], 7);
}

static void round_fn(uint32_t s[16], const uint32_t m[16]) {
  g(s, 0, 4, 8, 12, m[0], m[1]);   g(s, 1, 5, 9, 13, m[2], m[3]);
  g(s, 2, 6, 10, 14, m[4], m[5]);  g(s, 3, 7, 11, 15, m[6], m[7]);
  g(s, 0, 5, 10, 15, m[8], m[9]);  g(s, 1, 6, 11, 12, m[10], m[11]);
  g(s, 2, 7, 8, 13, m[12], m[13]); g(s, 3, 4, 9, 14, m[14], m[15]);
}

static void permute(uint32_t m[16]) {
  uint32_t t[16];
  for (int i = 0; i < 16; i++) t[i] = m[MSG_PERM[i]];
  memcpy(m, t, sizeof t);
}

/* Compression function. State words: 0..7 chaining value, 8..11 IV[0..3], 12 and 13 block counter (low, high),
 * 14 block length in bytes, 15 flags. Seven rounds, the message permuted between rounds. The first 8 output words
 * are the new chaining value, and the first 32 bytes of the root output when ROOT is set; callers use only these. */
static void compress(const uint32_t cv[8], const uint8_t block[BLOCK_LEN], uint32_t block_len, uint64_t counter,
                     uint32_t flags, uint32_t out[16]) {
  uint32_t m[16], s[16];
  for (int i = 0; i < 16; i++) m[i] = load32_le(block + 4 * i);
  memcpy(s, cv, 32);
  memcpy(s + 8, IV, 16);
  s[12] = (uint32_t)counter;
  s[13] = (uint32_t)(counter >> 32);
  s[14] = block_len;
  s[15] = flags;
  for (int r = 0; r < 7; r++) {
    round_fn(s, m);
    if (r < 6) permute(m);
  }
  for (int i = 0; i < 8; i++) {
    s[i] ^= s[i + 8];
    s[i + 8] ^= cv[i];
  }
  memcpy(out, s, 64);
}

/* Chaining value of one chunk of at most 1024 bytes; counter is the index of the chunk. Blocks are zero-padded to 64
 * bytes and block_len gives the real length; an empty input is one empty block. `root` is set when the chunk is the
 * whole input: the last block then carries ROOT and the result is the hash. */
static void chunk_cv(const uint8_t *in, size_t len, uint64_t counter, int root, uint32_t out[8]) {
  uint32_t cv[8], st[16];
  size_t nblocks = len == 0 ? 1 : (len + BLOCK_LEN - 1) / BLOCK_LEN;
  memcpy(cv, IV, 32);
  for (size_t b = 0; b < nblocks; b++) {
    uint8_t blk[BLOCK_LEN] = {0};
    size_t off = b * BLOCK_LEN;
    size_t bl = len - off < BLOCK_LEN ? len - off : BLOCK_LEN;
    uint32_t flags = 0;
    if (bl) memcpy(blk, in + off, bl);
    if (b == 0) flags |= CHUNK_START;
    if (b == nblocks - 1) flags |= CHUNK_END | (root ? ROOT : 0);
    compress(cv, blk, (uint32_t)bl, counter, flags, st);
    memcpy(cv, st, 32);
  }
  memcpy(out, cv, 32);
}

/* Chaining value of a subtree of the chunk tree; chunk_counter is the index of its first chunk. The left side takes
 * the largest power-of-two number of chunks strictly smaller than the total. A parent compresses
 * left_cv || right_cv (64 bytes) from the IV with counter 0 and the PARENT flag; ROOT only on the top parent. */
static void subtree_cv(const uint8_t *in, size_t len, uint64_t chunk_counter, int root, uint32_t out[8]) {
  uint32_t l[8], r[8], cv[8], st[16];
  uint8_t blk[BLOCK_LEN];
  size_t n_chunks, left, left_len;
  if (len <= CHUNK_LEN) {
    chunk_cv(in, len, chunk_counter, root, out);
    return;
  }
  n_chunks = (len + CHUNK_LEN - 1) / CHUNK_LEN;
  for (left = 1; left * 2 < n_chunks; left *= 2) {}
  left_len = left * CHUNK_LEN;
  subtree_cv(in, left_len, chunk_counter, 0, l);
  subtree_cv(in + left_len, len - left_len, chunk_counter + left, 0, r);
  for (int i = 0; i < 8; i++) {
    store32_le(blk + 4 * i, l[i]);
    store32_le(blk + 32 + 4 * i, r[i]);
  }
  memcpy(cv, IV, 32);
  compress(cv, blk, BLOCK_LEN, 0, PARENT | (root ? ROOT : 0), st);
  memcpy(out, st, 32);
}

void blake3_hash(const uint8_t *in, size_t len, uint8_t out[32]) {
  uint32_t cv[8];
  subtree_cv(in, len, 0, 1, cv);
  for (int i = 0; i < 8; i++) store32_le(out + 4 * i, cv[i]);
}
