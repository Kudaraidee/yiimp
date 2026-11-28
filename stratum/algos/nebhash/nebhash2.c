/* Reference implementation of spec/nebhash-v2.md: the architecture tables, the initial state, the share evaluation
 * (forward pass, gradient, digest, ticket), the model step and the state hashes. Scalar and portable, integer only.
 * Range arguments assume weights and activations in -8..7 and input values in 0..7. */
#include "nebhash2.h"
#include <stdlib.h>
#include <string.h>
#include "blake3_shared.h"
#include "chacha8.h"

/* The tables of section 3, with the derived fields written out (neb2_arch_get checks nothing at run time; the unit
 * test test_nebhash2.c recomputes them). Fields: kind, cin, cout, k, stride, pad, pool, s, sb, su, in_len, in_off,
 * nsrc, src, conv_len, out_len, nw, w_off. */
static const neb2_arch KEP1 = {
    NEB2_KEP1, 784, 6, 26000,
    {{NEB2_CONV1D, 1, 8, 5, 1, 2, 4, 2, 0, 15, 583, 0, 0, {0, 0}, 583, 145, 40, 0},
     {NEB2_CONV1D, 8, 16, 5, 1, 2, 4, 4, 5, 15, 145, 0, 1, {0, 0}, 145, 36, 640, 40},
     {NEB2_CONV1D, 1, 8, 5, 1, 2, 4, 3, 0, 15, 201, 583, 0, {0, 0}, 201, 50, 40, 680},
     {NEB2_CONV1D, 8, 16, 5, 1, 2, 4, 4, 4, 15, 50, 0, 1, {2, 0}, 50, 12, 640, 720},
     {NEB2_LINEAR, 768, 32, 1, 1, 0, 1, 5, 5, 15, 1, 0, 2, {1, 3}, 1, 1, 24576, 1360},
     {NEB2_LINEAR, 32, 2, 1, 1, 0, 1, 4, 3, 12, 1, 0, 1, {4, 0}, 1, 1, 64, 25936}}};

static const neb2_arch RET2 = {
    NEB2_RET2, 4096, 5, 22280,
    {{NEB2_CONV2D, 1, 8, 3, 2, 1, 2, 3, 0, 16, 64, 0, 0, {0, 0}, 32, 16, 72, 0},
     {NEB2_CONV2D, 8, 16, 3, 1, 1, 2, 4, 6, 16, 16, 0, 1, {0, 0}, 16, 8, 1152, 72},
     {NEB2_CONV2D, 16, 32, 3, 1, 1, 2, 5, 6, 16, 8, 0, 1, {1, 0}, 8, 4, 4608, 1224},
     {NEB2_LINEAR, 512, 32, 1, 1, 0, 1, 5, 5, 16, 1, 0, 1, {2, 0}, 1, 1, 16384, 5832},
     {NEB2_LINEAR, 32, 2, 1, 1, 0, 1, 4, 3, 13, 1, 0, 1, {3, 0}, 1, 1, 64, 22216}}};

const neb2_arch *neb2_arch_get(int id) {
  if (id == NEB2_KEP1) return &KEP1;
  if (id == NEB2_RET2) return &RET2;
  return NULL;
}

static int clipi(int v, int lo, int hi) { return v < lo ? lo : (v > hi ? hi : v); }

/* rshift of spec/shares.md section 2, with rshift(x, 0) = x. Operands stay far below 2^30 (section 4.2). */
static int32_t rshift(int32_t x, int n) { return n == 0 ? x : (x + ((int32_t)1 << (n - 1))) >> n; }

static void put_le32(uint8_t *p, uint32_t v) {
  p[0] = (uint8_t)v; p[1] = (uint8_t)(v >> 8); p[2] = (uint8_t)(v >> 16); p[3] = (uint8_t)(v >> 24);
}

/* Sizes of a layer: values of one input (all channels), of the convolution grid and of the pooled output. */
static int sq(const neb2_layer *L, int n) { return L->kind == NEB2_CONV2D ? n * n : n; }
static int in_size(const neb2_layer *L) { return L->cin * sq(L, L->in_len); }
static int conv_size(const neb2_layer *L) { return L->cout * sq(L, L->conv_len); }
static int out_size(const neb2_layer *L) { return L->cout * sq(L, L->out_len); }

int neb2_model_init(int arch, const uint8_t seed[32], neb2_model *m) {
  const neb2_arch *A = neb2_arch_get(arch);
  if (!A) return -1;
  uint8_t in[14 + 32], key[32];
  memcpy(in, "neb-model-init", 14);
  memcpy(in + 14, seed, 32);
  neb_blake3_hash(in, sizeof in, key);
  uint8_t *s = (uint8_t *)malloc((size_t)A->nweights);
  if (!s) return -1;
  chacha8_stream(key, s, (size_t)A->nweights);
  memset(m, 0, sizeof *m);
  m->arch = arch;
  for (int k = 0; k < A->nweights; k++) {
    const int b = s[k];
    m->w[k] = (int8_t)((b & 3) + ((b >> 2) & 3) + ((b >> 4) & 3) + ((b >> 6) & 3) - 6);
  }
  free(s);
  return 0;
}

/* The buffers of one sample, per layer: the input values `in`, the shifted sums y and the clipped activations on the
 * convolution grid, the pooled output and the grid position of each pooled maximum, the error on the pooled output,
 * the error on the grid (d) and the error sent to the input (m). */
typedef struct {
  int8_t *in, *a;
  int32_t *y, *arg, *e, *d, *mm;
  int8_t *out;
} layer_buf;

typedef struct {
  layer_buf b[NEB2_MAX_LAYERS];
  void *mem;
} work;

static int work_alloc(const neb2_arch *A, work *W) {
  size_t bytes = 0;
  for (int i = 0; i < A->nlayers; i++) {
    const neb2_layer *L = &A->l[i];
    bytes += (size_t)in_size(L) * (1 + 4) + (size_t)conv_size(L) * (1 + 4 + 4) + (size_t)out_size(L) * (1 + 4 + 4);
  }
  bytes += 64 * (size_t)A->nlayers * 8;
  uint8_t *p = (uint8_t *)calloc(1, bytes);
  if (!p) return -1;
  W->mem = p;
  for (int i = 0; i < A->nlayers; i++) {
    const neb2_layer *L = &A->l[i];
    const size_t ni = (size_t)in_size(L), nc = (size_t)conv_size(L), no = (size_t)out_size(L);
    layer_buf *B = &W->b[i];
#define TAKE(ptr, type, count) do { ptr = (type *)p; p += ((count) * sizeof(type) + 63) & ~(size_t)63; } while (0)
    TAKE(B->in, int8_t, ni);
    TAKE(B->mm, int32_t, ni);
    TAKE(B->a, int8_t, nc);
    TAKE(B->y, int32_t, nc);
    TAKE(B->d, int32_t, nc);
    TAKE(B->out, int8_t, no);
    TAKE(B->arg, int32_t, no);
    TAKE(B->e, int32_t, no);
#undef TAKE
  }
  return 0;
}

/* The input of layer i: a span of the sample, or the concatenated pooled outputs of its sources. */
static void gather_input(const neb2_arch *A, work *W, int i, const uint8_t *x) {
  const neb2_layer *L = &A->l[i];
  int8_t *in = W->b[i].in;
  if (L->nsrc == 0) {
    for (int j = 0; j < in_size(L); j++) in[j] = (int8_t)x[L->in_off + j];
    return;
  }
  int at = 0;
  for (int s = 0; s < L->nsrc; s++) {
    const neb2_layer *S = &A->l[L->src[s]];
    memcpy(in + at, W->b[L->src[s]].out, (size_t)out_size(S));
    at += out_size(S);
  }
}

/* Forward pass of layer i (section 4.1): z, y = rshift(z, S), a = clip(y), then the pooling. */
static void forward_layer(const neb2_arch *A, const int8_t *wl, work *W, int i) {
  const neb2_layer *L = &A->l[i];
  layer_buf *B = &W->b[i];
  const int8_t *in = B->in;
  if (L->kind == NEB2_LINEAR) {
    for (int o = 0; o < L->cout; o++) {
      int32_t z = 0;
      const int8_t *w = wl + (size_t)o * L->cin;
      for (int j = 0; j < L->cin; j++) z += (int32_t)w[j] * in[j];
      B->y[o] = rshift(z, L->s);
      B->a[o] = (int8_t)clipi(B->y[o], -8, 7);
      B->out[o] = B->a[o];
      B->arg[o] = o;
    }
    return;
  }
  const int n = L->in_len, cl = L->conv_len, k = L->k, st = L->stride, pad = L->pad;
  if (L->kind == NEB2_CONV1D) {
    for (int o = 0; o < L->cout; o++)
      for (int p = 0; p < cl; p++) {
        int32_t z = 0;
        for (int c = 0; c < L->cin; c++) {
          const int8_t *w = wl + ((size_t)o * L->cin + c) * k;
          const int8_t *row = in + (size_t)c * n;
          for (int t = 0; t < k; t++) {
            const int q = p * st + t - pad;
            if (q >= 0 && q < n) z += (int32_t)w[t] * row[q];
          }
        }
        const int g = o * cl + p;
        B->y[g] = rshift(z, L->s);
        B->a[g] = (int8_t)clipi(B->y[g], -8, 7);
      }
    const int ol = L->out_len, pl = L->pool;
    for (int o = 0; o < L->cout; o++)
      for (int j = 0; j < ol; j++) {
        int best = o * cl + j * pl;
        for (int t = 1; t < pl; t++)
          if (B->a[o * cl + j * pl + t] > B->a[best]) best = o * cl + j * pl + t;
        B->out[o * ol + j] = B->a[best];
        B->arg[o * ol + j] = best;
      }
    return;
  }
  for (int o = 0; o < L->cout; o++)
    for (int py = 0; py < cl; py++)
      for (int px = 0; px < cl; px++) {
        int32_t z = 0;
        for (int c = 0; c < L->cin; c++) {
          const int8_t *w = wl + ((size_t)o * L->cin + c) * k * k;
          const int8_t *plane = in + (size_t)c * n * n;
          for (int ky = 0; ky < k; ky++) {
            const int qy = py * st + ky - pad;
            if (qy < 0 || qy >= n) continue;
            for (int kx = 0; kx < k; kx++) {
              const int qx = px * st + kx - pad;
              if (qx >= 0 && qx < n) z += (int32_t)w[ky * k + kx] * plane[qy * n + qx];
            }
          }
        }
        const int g = (o * cl + py) * cl + px;
        B->y[g] = rshift(z, L->s);
        B->a[g] = (int8_t)clipi(B->y[g], -8, 7);
      }
  /* p x p windows from (0, 0); the first maximum in row-major order of the window keeps the position */
  const int ol = L->out_len, pl = L->pool;
  for (int o = 0; o < L->cout; o++)
    for (int jy = 0; jy < ol; jy++)
      for (int jx = 0; jx < ol; jx++) {
        int best = (o * cl + jy * pl) * cl + jx * pl;
        for (int ty = 0; ty < pl; ty++)
          for (int tx = 0; tx < pl; tx++) {
            const int g = (o * cl + jy * pl + ty) * cl + jx * pl + tx;
            if (B->a[g] > B->a[best]) best = g;
          }
        const int jo = (o * ol + jy) * ol + jx;
        B->out[jo] = B->a[best];
        B->arg[jo] = best;
      }
}

/* Backward pass of layer i (section 4.2): route e through the pooling, mask, add d (x) in to the layer's gradient,
 * and when the layer reads other layers, send clip(rshift(W^T d, SB)) to their errors. */
static void backward_layer(const neb2_arch *A, const int8_t *wl, int32_t *gl, work *W, int i) {
  const neb2_layer *L = &A->l[i];
  layer_buf *B = &W->b[i];
  const int nc = conv_size(L), no = out_size(L), ni = in_size(L);
  memset(B->d, 0, (size_t)nc * sizeof(int32_t));
  for (int j = 0; j < no; j++) B->d[B->arg[j]] = B->e[j];
  /* no gradient through a clipped activation, except on the output layer (as in spec/shares.md): with the mask there,
   * an output saturated on the wrong side would stop every gradient for good */
  if (i != A->nlayers - 1)
    for (int g = 0; g < nc; g++)
      if (B->y[g] < -8 || B->y[g] > 7) B->d[g] = 0;
  const int8_t *in = B->in;
  const int send = L->nsrc > 0;
  if (send) memset(B->mm, 0, (size_t)ni * sizeof(int32_t));
  if (L->kind == NEB2_LINEAR) {
    for (int o = 0; o < L->cout; o++) {
      const int32_t d = B->d[o];
      if (d == 0) continue;
      int32_t *g = gl + (size_t)o * L->cin;
      const int8_t *w = wl + (size_t)o * L->cin;
      for (int j = 0; j < L->cin; j++) g[j] += d * in[j];
      if (send)
        for (int j = 0; j < L->cin; j++) B->mm[j] += d * w[j];
    }
  } else if (L->kind == NEB2_CONV1D) {
    const int n = L->in_len, cl = L->conv_len, k = L->k, st = L->stride, pad = L->pad;
    for (int o = 0; o < L->cout; o++)
      for (int p = 0; p < cl; p++) {
        const int32_t d = B->d[o * cl + p];
        if (d == 0) continue;
        for (int c = 0; c < L->cin; c++) {
          const size_t wo = ((size_t)o * L->cin + c) * k;
          for (int t = 0; t < k; t++) {
            const int q = p * st + t - pad;
            if (q < 0 || q >= n) continue;
            gl[wo + t] += d * in[c * n + q];
            if (send) B->mm[c * n + q] += d * wl[wo + t];
          }
        }
      }
  } else {
    const int n = L->in_len, cl = L->conv_len, k = L->k, st = L->stride, pad = L->pad;
    for (int o = 0; o < L->cout; o++)
      for (int py = 0; py < cl; py++)
        for (int px = 0; px < cl; px++) {
          const int32_t d = B->d[(o * cl + py) * cl + px];
          if (d == 0) continue;
          for (int c = 0; c < L->cin; c++) {
            const size_t wo = ((size_t)o * L->cin + c) * k * k;
            for (int ky = 0; ky < k; ky++) {
              const int qy = py * st + ky - pad;
              if (qy < 0 || qy >= n) continue;
              for (int kx = 0; kx < k; kx++) {
                const int qx = px * st + kx - pad;
                if (qx < 0 || qx >= n) continue;
                const int q = (c * n + qy) * n + qx;
                gl[wo + ky * k + kx] += d * in[q];
                if (send) B->mm[q] += d * wl[wo + ky * k + kx];
              }
            }
          }
        }
  }
  if (!send) return;
  int at = 0;
  for (int s = 0; s < L->nsrc; s++) {
    const int src = L->src[s];
    const int ns = out_size(&A->l[src]);
    for (int j = 0; j < ns; j++) W->b[src].e[j] = clipi(rshift(B->mm[at + j], L->sb), -8, 7);
    at += ns;
  }
}

int neb2_share_eval(const neb2_model *m, const neb2_dataset *ds, const uint8_t key[32], int32_t *g, uint8_t digest[32],
                    uint8_t ticket[32], uint32_t idx_out[NEB_NB], uint32_t *nclip) {
  const neb2_arch *A = neb2_arch_get(m->arch);
  if (!A) return -1;
  work W;
  if (work_alloc(A, &W) != 0) return -1;
  uint8_t *x = (uint8_t *)malloc((size_t)A->nin);
  if (!x) { free(W.mem); return -1; }
  uint32_t idx[NEB_NB];
  neb_share_indices(key, ds->n, idx);
  if (idx_out) memcpy(idx_out, idx, sizeof idx);
  memset(g, 0, (size_t)A->nweights * sizeof(int32_t));
  const int last = A->nlayers - 1;
  for (int b = 0; b < NEB_NB; b++) {
    const int label = ds->get(ds->ctx, idx[b], x);
    for (int i = 0; i < A->nlayers; i++) {
      gather_input(A, &W, i, x);
      forward_layer(A, m->w + A->l[i].w_off, &W, i);
    }
    /* E[o] = clip(a[o] - T[o], -8, 7) on the output layer (spec/shares.md section 4 targets) */
    for (int o = 0; o < NEB2_NOUT; o++) {
      const int t = (o == label) ? NEB_TPOS : NEB_TNEG;
      W.b[last].e[o] = clipi(W.b[last].out[o] - t, -8, 7);
    }
    for (int i = last; i >= 0; i--) backward_layer(A, m->w + A->l[i].w_off, g + A->l[i].w_off, &W, i);
  }
  free(x);
  free(W.mem);
  /* clip and digest: D[f mod 8] += c XOR (f + 7919 * l), l = 1 .. L, f the flat index inside the layer */
  uint32_t clipped = 0, D[8] = {0, 0, 0, 0, 0, 0, 0, 0};
  for (int i = 0; i < A->nlayers; i++) {
    const neb2_layer *L = &A->l[i];
    int32_t *gl = g + L->w_off;
    for (int f = 0; f < L->nw; f++) {
      const int32_t c = clipi(gl[f], -NEB_CLIP, NEB_CLIP);
      clipped += (c != gl[f]);
      gl[f] = c;
      D[f & 7] += (uint32_t)c ^ ((uint32_t)f + 7919u * (uint32_t)(i + 1));
    }
  }
  if (nclip) *nclip = clipped;
  uint8_t in[64];
  for (int d = 0; d < 8; d++) put_le32(digest + 4 * d, D[d]);
  memcpy(in, digest, 32);
  memcpy(in + 32, key, 32);
  neb_blake3_hash(in, sizeof in, ticket);
  return 0;
}

int neb2_sample_grad(const neb2_model *m, const uint8_t *x, int label, int32_t *g, int8_t *fac) {
  const neb2_arch *A = neb2_arch_get(m->arch);
  if (!A) return -1;
  work W;
  if (work_alloc(A, &W) != 0) return -1;
  memset(g, 0, (size_t)A->nweights * sizeof(int32_t));
  const int last = A->nlayers - 1;
  for (int i = 0; i < A->nlayers; i++) {
    gather_input(A, &W, i, x);
    forward_layer(A, m->w + A->l[i].w_off, &W, i);
  }
  for (int o = 0; o < NEB2_NOUT; o++) {
    const int t = (o == label) ? NEB_TPOS : NEB_TNEG;
    W.b[last].e[o] = clipi(W.b[last].out[o] - t, -8, 7);
  }
  for (int i = last; i >= 0; i--) backward_layer(A, m->w + A->l[i].w_off, g + A->l[i].w_off, &W, i);
  if (fac)
    for (int i = 0; i < A->nlayers; i++) {
      const neb2_layer *L = &A->l[i];
      if (L->kind != NEB2_LINEAR) continue;
      for (int o = 0; o < L->cout; o++) *fac++ = (int8_t)W.b[i].d[o];
      memcpy(fac, W.b[i].in, (size_t)L->cin);
      fac += L->cin;
    }
  free(W.mem);
  return 0;
}

void neb2_model_apply(neb2_model *m, const int32_t *gs) {
  const neb2_arch *A = neb2_arch_get(m->arch);
  if (!A) return;
  for (int i = 0; i < A->nlayers; i++) {
    const neb2_layer *L = &A->l[i];
    const int su = L->su - NEB2_SU_DROP;
    const int32_t unit = (int32_t)1 << su;
    for (int f = L->w_off; f < L->w_off + L->nw; f++) {
      int32_t v = m->r[f] + gs[f];
      const int32_t q = v >= 0 ? (v >> su) : -((-v) >> su);
      const int32_t d = clipi(q, -1, 1);
      v -= d * unit;
      m->r[f] = clipi(v, -unit, unit);
      m->w[f] = (int8_t)clipi(m->w[f] - d, -8, 7);
    }
  }
}

void neb2_model_hashes(const neb2_model *m, uint8_t weights_hash[32], uint8_t residual_hash[32]) {
  static const char tw[] = "neb-model-weights", tr[] = "neb-model-residual";
  const size_t lw = sizeof tw - 1, lr = sizeof tr - 1;
  const neb2_arch *A = neb2_arch_get(m->arch);
  const int n = A ? A->nweights : 0;
  uint8_t *buf = (uint8_t *)malloc(lr + 4 * (size_t)n);
  if (!buf) return;
  memcpy(buf, tw, lw);
  for (int j = 0; j < n / 2; j++) buf[lw + j] = (uint8_t)((m->w[2 * j] & 15) | ((m->w[2 * j + 1] & 15) << 4));
  neb_blake3_hash(buf, lw + (size_t)n / 2, weights_hash);
  memcpy(buf, tr, lr);
  for (int f = 0; f < n; f++) put_le32(buf + lr + 4 * f, (uint32_t)m->r[f]);
  neb_blake3_hash(buf, lr + 4 * (size_t)n, residual_hash);
  free(buf);
}

int neb2_model_predict(const neb2_model *m, const uint8_t *x) {
  const neb2_arch *A = neb2_arch_get(m->arch);
  if (!A) return -1;
  work W;
  if (work_alloc(A, &W) != 0) return -1;
  for (int i = 0; i < A->nlayers; i++) {
    gather_input(A, &W, i, x);
    forward_layer(A, m->w + A->l[i].w_off, &W, i);
  }
  /* the raw sums of the output layer: recompute them, the forward pass keeps only the shifted values */
  const neb2_layer *L = &A->l[A->nlayers - 1];
  const int8_t *in = W.b[A->nlayers - 1].in;
  int best = 0;
  int32_t bestz = 0;
  for (int o = 0; o < L->cout; o++) {
    int32_t z = 0;
    for (int j = 0; j < L->cin; j++) z += (int32_t)m->w[L->w_off + o * L->cin + j] * in[j];
    if (o == 0 || z > bestz) { best = o; bestz = z; }
  }
  free(W.mem);
  return best;
}
