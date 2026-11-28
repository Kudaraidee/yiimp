#ifndef NEB_SHARES_H
#define NEB_SHARES_H
/* Training shares: the reference implementation of the proof of work and of the model step. Normative text:
 * spec/shares.md. The node compiles this code in (crate neb-pow). The CUDA kernel is compared with it, and the
 * Python oracle replays the vectors that it produces (vectors/shares.txt).
 *
 * All arithmetic is integer (int8, int32, uint32), with no floating point and no dependence on the host beyond
 * two's complement and an arithmetic right shift of negative int32 values. The constants below must equal the table
 * of spec/shares.md section 2; pow/tests/check_spec_constants.sh compares them. */
#include <stddef.h>
#include <stdint.h>

#define NEB_NIN 784
#define NEB_NH 128
#define NEB_NOUT 10
#define NEB_NB 16
#define NEB_S1 5
#define NEB_S2 5
#define NEB_SB 3
/* Update shifts. Only the test-only variant of spec/shares.md section 10.1 overrides them
 * (-DNEB_SU1=9 -DNEB_SU2=6, vectors/shares_s1.txt); a consensus build uses 15 and 12. */
#ifndef NEB_SU1
#define NEB_SU1 15
#endif
#ifndef NEB_SU2
#define NEB_SU2 12
#endif
#define NEB_CLIP 256
#define NEB_TPOS 7
#define NEB_TNEG (-8)

#define NEB_N1 (NEB_NH * NEB_NIN)  /* entries of W1, R1, G1: NH rows of NIN, row-major */
#define NEB_N2 (NEB_NOUT * NEB_NH) /* entries of W2, R2, G2: NOUT rows of NH, row-major */
/* Largest number of shares of a model step: the largest n with 2^SU1 + n * CLIP < 2^31, that is
 * floor((2^31 - 1 - 2^15) / 256) = 8388479, so that R + GS is exact in int32 (spec section 6). */
#define NEB_MAX_SHARES 8388479u

/* The state of spec section 4: weights in -8..7 and residuals with |r| <= 2^SU of their layer. The struct has
 * 508,160 bytes: allocate it on the heap. */
typedef struct {
  int8_t w1[NEB_N1];
  int8_t w2[NEB_N2];
  int32_t r1[NEB_N1];
  int32_t r2[NEB_N2];
} neb_model;

/* The dataset: n >= 1 samples. get() fills x with the NEB_NIN pixels of sample `index` (< n), values in 0..7, and
 * returns its label 0..9. It must be a pure function of index: the dataset is pinned by hash in the network
 * parameters (spec section 11.6), and another sample gives another ticket. */
typedef struct {
  uint32_t n;
  int (*get)(void *ctx, uint32_t index, uint8_t x[NEB_NIN]);
  void *ctx;
} neb_dataset;

/* The initial state of spec section 4 for a 32-byte seed (on a chain, the genesis block id, section 11.2). Writes
 * every field of m. */
void neb_model_init(const uint8_t seed[32], neb_model *m);

/* The NEB_NB sample indices of a share (spec section 5.1): idx[j] = floor(u_j * n / 2^32) for the 16 little-endian
 * words u_j of ChaCha8(key). Needs n >= 1; every idx[j] < n. */
void neb_share_indices(const uint8_t key[32], uint32_t n, uint32_t idx[NEB_NB]);

/* One share on the model m (spec sections 5.2 to 5.4). Outputs: the clipped gradients g1[NEB_N1] and g2[NEB_N2]
 * (g1[h * NEB_NIN + i], g2[o * NEB_NH + h]), the gradient digest and the ticket (32 bytes each). The ticket is the
 * BLAKE3 output in byte order; compare it with a target as a 256-bit little-endian integer. idx may be NULL.
 * nclip may be NULL; otherwise it receives the number of gradient entries that the clip changed (a statistic, not
 * part of the consensus). m and the dataset are not modified. */
void neb_share_eval(const neb_model *m, const neb_dataset *ds, const uint8_t key[32], int32_t *g1, int32_t *g2,
                    uint8_t digest[32], uint8_t ticket[32], uint32_t idx[NEB_NB], uint32_t *nclip);

/* The model step (spec section 6). gs1 and gs2 are the entry-wise sums of the clipped gradients (g1 and g2 of
 * neb_share_eval) of the n shares of the step, 1 <= n <= NEB_MAX_SHARES. The function sees only the sums, so the
 * caller enforces the range of n. The result does not depend on the order of the shares. */
void neb_model_apply(neb_model *m, const int32_t *gs1, const int32_t *gs2);

/* The weights_hash and the residual_hash of spec section 7. */
void neb_model_hashes(const neb_model *m, uint8_t weights_hash[32], uint8_t residual_hash[32]);

/* Class of a sample for accuracy reports: the first maximum of the raw int32 output sums z2 (smallest index on
 * ties). Not part of the consensus. */
int neb_model_predict(const neb_model *m, const uint8_t x[NEB_NIN]);

#endif
