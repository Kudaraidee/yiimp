/* Reference implementation of spec/shares.md sections 4 to 7: initial state, share evaluation (sample indices,
 * gradient, digest, ticket), model step and state hashes. Scalar and portable; the contract of each function is in
 * shares.h. Range arguments assume weights and activations in -8..7 and pixels in 0..7. */
#include "shares.h"
#include <stdlib.h>
#include <string.h>
#include "blake3_min.h"
#include "chacha8.h"

static int clipi(int v, int lo, int hi) { return v < lo ? lo : (v > hi ? hi : v); }

/* rshift(x, n) of spec/shares.md section 2. x + 2^(n-1) cannot overflow: the largest operand is
 * |z1| <= 784 * 7 * 8 = 43904. The spec requires an arithmetic right shift of negative values; ISO C leaves it
 * implementation-defined, and gcc, clang and MSVC provide it. */
static int32_t rshift(int32_t x, int n) { return (x + ((int32_t)1 << (n - 1))) >> n; }

static uint32_t get_le32(const uint8_t *p) {
  return (uint32_t)p[0] | ((uint32_t)p[1] << 8) | ((uint32_t)p[2] << 16) | ((uint32_t)p[3] << 24);
}
static void put_le32(uint8_t *p, uint32_t v) {
  p[0] = (uint8_t)v; p[1] = (uint8_t)(v >> 8); p[2] = (uint8_t)(v >> 16); p[3] = (uint8_t)(v >> 24);
}

void neb_model_init(const uint8_t seed[32], neb_model *m) {
  uint8_t in[14 + 32], key[32];
  memcpy(in, "neb-model-init", 14);
  memcpy(in + 14, seed, 32);
  blake3_hash(in, sizeof in, key);
  uint8_t *s = (uint8_t *)malloc(NEB_N1 + NEB_N2);
  chacha8_stream(key, s, NEB_N1 + NEB_N2);
  /* Byte k is the sum of its four 2-bit fields minus 6, in -6..6 (spec section 4). The first NEB_N1 bytes fill W1,
   * the next NEB_N2 bytes fill W2. */
  for (int k = 0; k < NEB_N1 + NEB_N2; k++) {
    const int b = s[k];
    const int w = (b & 3) + ((b >> 2) & 3) + ((b >> 4) & 3) + ((b >> 6) & 3) - 6;
    if (k < NEB_N1) m->w1[k] = (int8_t)w;
    else m->w2[k - NEB_N1] = (int8_t)w;
  }
  free(s);
  memset(m->r1, 0, sizeof m->r1);
  memset(m->r2, 0, sizeof m->r2);
}

void neb_share_indices(const uint8_t key[32], uint32_t n, uint32_t idx[NEB_NB]) {
  uint8_t u[64];
  chacha8_stream(key, u, sizeof u);
  /* u_j < 2^32 and n < 2^32, so the product fits in uint64 and the quotient by 2^32 is below n. */
  for (int j = 0; j < NEB_NB; j++) idx[j] = (uint32_t)(((uint64_t)get_le32(u + 4 * j) * n) >> 32);
}

void neb_share_eval(const neb_model *m, const neb_dataset *ds, const uint8_t key[32], int32_t *g1, int32_t *g2,
                    uint8_t digest[32], uint8_t ticket[32], uint32_t idx_out[NEB_NB], uint32_t *nclip) {
  uint32_t idx[NEB_NB];
  uint8_t X[NEB_NB][NEB_NIN];
  int T[NEB_NB][NEB_NOUT];
  int y1[NEB_NB][NEB_NH], a1[NEB_NB][NEB_NH], E1[NEB_NB][NEB_NH], E2[NEB_NB][NEB_NOUT];
  neb_share_indices(key, ds->n, idx);
  if (idx_out) memcpy(idx_out, idx, sizeof idx);
  for (int b = 0; b < NEB_NB; b++) {
    const int label = ds->get(ds->ctx, idx[b], X[b]);
    for (int o = 0; o < NEB_NOUT; o++) T[b][o] = (o == label) ? NEB_TPOS : NEB_TNEG;
  }
  /* Section 5.2 sample by sample. Bounds: |z1| <= 784 * 7 * 8 = 43904, |z2| <= 128 * 8 * 8 = 8192 and
   * |m| <= 10 * 8 * 8 = 640, so every sum is exact in int32. */
  for (int b = 0; b < NEB_NB; b++) {
    for (int h = 0; h < NEB_NH; h++) {
      int32_t z = 0;
      const int8_t *w = m->w1 + (size_t)h * NEB_NIN;
      for (int i = 0; i < NEB_NIN; i++) z += (int32_t)X[b][i] * (int32_t)w[i];
      y1[b][h] = rshift(z, NEB_S1);
      a1[b][h] = clipi(y1[b][h], -8, 7);
    }
    for (int o = 0; o < NEB_NOUT; o++) {
      int32_t z = 0;
      for (int h = 0; h < NEB_NH; h++) z += (int32_t)a1[b][h] * (int32_t)m->w2[o * NEB_NH + h];
      E2[b][o] = clipi(clipi(rshift(z, NEB_S2), -8, 7) - T[b][o], -8, 7);
    }
    for (int h = 0; h < NEB_NH; h++) {
      int32_t mm = 0;
      for (int o = 0; o < NEB_NOUT; o++) mm += (int32_t)E2[b][o] * (int32_t)m->w2[o * NEB_NH + h];
      /* The error reaches layer 1 only where the activation was not clipped. */
      E1[b][h] = (y1[b][h] >= -8 && y1[b][h] <= 7) ? clipi(rshift(mm, NEB_SB), -8, 7) : 0;
    }
  }
  /* Gradients, clip and digest. Before the clip |G2| <= 16 * 8 * 8 = 1024 and |G1| <= 16 * 8 * 7 = 896, exact in
   * int32. Layer 2 is folded before layer 1; the order is irrelevant because each lane D[f mod 8] is a sum modulo
   * 2^32 (section 5.3). f is the flat row-major index, so f + 7919 * l < 2^32; (uint32_t)c is the two's complement
   * of the clipped entry. */
  uint32_t clipped = 0, D[8] = {0, 0, 0, 0, 0, 0, 0, 0};
  for (int o = 0; o < NEB_NOUT; o++)
    for (int h = 0; h < NEB_NH; h++) {
      int32_t g = 0;
      for (int b = 0; b < NEB_NB; b++) g += (int32_t)E2[b][o] * (int32_t)a1[b][h];
      const int32_t c = clipi(g, -NEB_CLIP, NEB_CLIP);
      clipped += (c != g);
      g2[o * NEB_NH + h] = c;
      const uint32_t f = (uint32_t)(o * NEB_NH + h);
      D[f & 7] += (uint32_t)c ^ (f + 7919u * 2u);
    }
  for (int h = 0; h < NEB_NH; h++)
    for (int i = 0; i < NEB_NIN; i++) {
      int32_t g = 0;
      for (int b = 0; b < NEB_NB; b++) g += (int32_t)E1[b][h] * (int32_t)X[b][i];
      const int32_t c = clipi(g, -NEB_CLIP, NEB_CLIP);
      clipped += (c != g);
      g1[h * NEB_NIN + i] = c;
      const uint32_t f = (uint32_t)(h * NEB_NIN + i);
      D[f & 7] += (uint32_t)c ^ (f + 7919u * 1u);
    }
  if (nclip) *nclip = clipped;
  /* ticket = BLAKE3(digest || key), a 64-byte input (section 5.4) */
  uint8_t in[64];
  for (int d = 0; d < 8; d++) put_le32(digest + 4 * d, D[d]);
  memcpy(in, digest, 32);
  memcpy(in + 32, key, 32);
  blake3_hash(in, sizeof in, ticket);
}

/* Section 6 for one layer, entry by entry. v = R + GS is exact: |R| <= 2^su and |GS| <= n * CLIP, so |v| < 2^31 for
 * n <= NEB_MAX_SHARES. d moves a weight by at most 1. The residual keeps v - d * 2^su, clamped to [-2^su, 2^su]; the
 * clamp changes it only when |v| > 2^(su+1), and it bounds how long the gradient mass of an adversary persists. */
static void apply_layer(int8_t *w, int32_t *r, const int32_t *gs, int n, int su) {
  const int32_t unit = (int32_t)1 << su;
  for (int e = 0; e < n; e++) {
    int32_t v = r[e] + gs[e];
    const int32_t q = v >= 0 ? (v >> su) : -((-v) >> su); /* tdiv: toward zero; -v cannot overflow */
    const int32_t d = clipi(q, -1, 1);
    v -= d * unit;
    r[e] = clipi(v, -unit, unit);
    w[e] = (int8_t)clipi(w[e] - d, -8, 7);
  }
}

void neb_model_apply(neb_model *m, const int32_t *gs1, const int32_t *gs2) {
  apply_layer(m->w1, m->r1, gs1, NEB_N1, NEB_SU1);
  apply_layer(m->w2, m->r2, gs2, NEB_N2, NEB_SU2);
}

void neb_model_hashes(const neb_model *m, uint8_t weights_hash[32], uint8_t residual_hash[32]) {
  /* Messages: tag || pack(W1) || pack(W2) and tag || LE32(R1) || LE32(R2), row-major (spec section 7). pack stores
   * element e in the low nibble of byte e / 2 for even e and in the high nibble for odd e (section 3); `& 15` is the
   * two's complement nibble of a value in -8..7. NEB_N1 and NEB_N2 are even, so each matrix packs to whole bytes.
   * buf is sized for the longer message, the residual one: 18 + 4 * 101632 = 406546 bytes. */
  static const char tw[] = "neb-model-weights", tr[] = "neb-model-residual";
  const size_t lw = sizeof tw - 1, lr = sizeof tr - 1;
  const size_t npack = (NEB_N1 + NEB_N2) / 2;
  uint8_t *buf = (uint8_t *)malloc(lr + 4 * (NEB_N1 + NEB_N2));
  memcpy(buf, tw, lw);
  for (int j = 0; j < NEB_N1 / 2; j++) buf[lw + j] = (uint8_t)((m->w1[2 * j] & 15) | ((m->w1[2 * j + 1] & 15) << 4));
  for (int j = 0; j < NEB_N2 / 2; j++)
    buf[lw + NEB_N1 / 2 + j] = (uint8_t)((m->w2[2 * j] & 15) | ((m->w2[2 * j + 1] & 15) << 4));
  blake3_hash(buf, lw + npack, weights_hash);
  memcpy(buf, tr, lr);
  for (int e = 0; e < NEB_N1; e++) put_le32(buf + lr + 4 * e, (uint32_t)m->r1[e]);
  for (int e = 0; e < NEB_N2; e++) put_le32(buf + lr + 4 * (NEB_N1 + e), (uint32_t)m->r2[e]);
  blake3_hash(buf, lr + 4 * (NEB_N1 + NEB_N2), residual_hash);
  free(buf);
}

int neb_model_predict(const neb_model *m, const uint8_t x[NEB_NIN]) {
  int a1[NEB_NH];
  for (int h = 0; h < NEB_NH; h++) {
    int32_t z = 0;
    for (int i = 0; i < NEB_NIN; i++) z += (int32_t)x[i] * (int32_t)m->w1[h * NEB_NIN + i];
    a1[h] = clipi(rshift(z, NEB_S1), -8, 7);
  }
  int best = 0;
  int32_t bestz = 0;
  for (int o = 0; o < NEB_NOUT; o++) {
    int32_t z = 0;
    for (int h = 0; h < NEB_NH; h++) z += (int32_t)a1[h] * (int32_t)m->w2[o * NEB_NH + h];
    /* only a strictly larger sum replaces the best class, so ties go to the smallest index */
    if (o == 0 || z > bestz) { best = o; bestz = z; }
  }
  return best;
}
