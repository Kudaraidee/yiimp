#ifndef NEB_NEBHASH2_H
#define NEB_NEBHASH2_H
/* nebhash v2: the training shares of the convolutional models. Normative text: spec/nebhash-v2.md. Everything this
 * header does not redefine is as in shares.h (the sample indices of a share, the targets, the clip of the gradient,
 * the ticket, the model step rule, the state hashes).
 *
 * One implementation serves every architecture: an architecture is a table of layers (neb2_arch), and the model, the
 * share evaluation and the step read that table. All arithmetic is integer, as in shares.c. */
#include <stddef.h>
#include <stdint.h>
#include "shares.h"

#ifdef __cplusplus
extern "C" {
#endif

#define NEB2_KEP1 1 /* Kepler transit signals, 784 inputs, spec/nebhash-v2.md section 3.1 */
#define NEB2_RET2 2 /* retinal scans at 64 x 64, 4096 inputs, section 3.2 */

#define NEB2_MAX_LAYERS 6
#define NEB2_MAX_WEIGHTS 26000 /* the larger architecture, KEP1 */
#define NEB2_MAX_NIN 4096
#define NEB2_NOUT 2
/* Test-only variant of section 8: every update shift lowered by NEB2_SU_DROP so that a few shares move weights
 * (vectors/nebhash2_s1.txt, -DNEB2_SU_DROP=6). A consensus build leaves it at 0. */
#ifndef NEB2_SU_DROP
#define NEB2_SU_DROP 0
#endif

enum { NEB2_CONV1D = 1, NEB2_CONV2D = 2, NEB2_LINEAR = 3 };

/* A weight layer. A layer reads either a span of the input (nsrc == 0: x[in_off .. in_off + cin * in_len)) or the
 * concatenation of the outputs of earlier layers (src[0..nsrc), each flattened channel-major). in_len is the length
 * (conv1d) or the side (conv2d, square) of one input channel; a linear layer has in_len 1 and cin inputs. */
typedef struct {
  int kind, cin, cout, k, stride, pad, pool;
  int s, sb, su;
  int in_len, in_off, nsrc, src[2];
  /* derived by the table: conv_len is the length or side of the convolution output, out_len the same after the
   * pooling (conv_len / pool, the rest dropped), nw the weight count, w_off the offset of the layer in the model */
  int conv_len, out_len, nw, w_off;
} neb2_layer;

typedef struct {
  int id, nin, nlayers, nweights;
  neb2_layer l[NEB2_MAX_LAYERS];
} neb2_arch;

/* The architecture of an id, or NULL for an unknown id. */
const neb2_arch *neb2_arch_get(int id);

/* A model of an architecture: the weights and residuals of every layer, in layer order, each tensor row-major. The
 * struct has 130,004 bytes: allocate it on the heap. */
typedef struct {
  int32_t arch;
  int8_t w[NEB2_MAX_WEIGHTS];
  int32_t r[NEB2_MAX_WEIGHTS];
} neb2_model;

/* The dataset of a v2 architecture: get() fills x with arch->nin values in 0..7 and returns the label 0..1. */
typedef struct {
  uint32_t n;
  int (*get)(void *ctx, uint32_t index, uint8_t *x);
  void *ctx;
} neb2_dataset;

/* The initial state of section 6 for a 32-byte seed: the weights of spec/shares.md section 4 drawn from one keystream
 * in layer order, residuals 0. Returns 0, or -1 for an unknown architecture. */
int neb2_model_init(int arch, const uint8_t seed[32], neb2_model *m);

/* One share (section 4): the clipped gradient g[arch->nweights] in model order, the digest and the ticket. idx and
 * nclip may be NULL. Returns 0, or -1 when the model's architecture is unknown or memory runs out. */
int neb2_share_eval(const neb2_model *m, const neb2_dataset *ds, const uint8_t key[32], int32_t *g, uint8_t digest[32],
                    uint8_t ticket[32], uint32_t idx[NEB_NB], uint32_t *nclip);

/* One sample alone, for a miner that keeps the gradient of every sample of a model state: g[arch->nweights] gets the
 * gradient of this sample before the clip, in model order. The gradient of a share (section 4.2) is the entry-wise sum
 * of the gradients of its NEB_NB samples, clipped afterwards. When fac is not NULL it also gets, for each linear layer
 * in table order, the cout errors d of the layer then its cin inputs: the gradient of that layer is their outer
 * product. The consensus rules do not use this function. Returns 0, or -1 when the model's architecture is unknown or
 * memory runs out. */
int neb2_sample_grad(const neb2_model *m, const uint8_t *x, int label, int32_t *g, int8_t *fac);

/* The model step (section 5): gs holds the entry-wise sums of the clipped gradients of 1 .. NEB_MAX_SHARES shares. */
void neb2_model_apply(neb2_model *m, const int32_t *gs);

/* weights_hash and residual_hash of section 6. */
void neb2_model_hashes(const neb2_model *m, uint8_t weights_hash[32], uint8_t residual_hash[32]);

/* Class of a sample for accuracy reports: the first maximum of the raw output sums. Not part of the consensus.
 * Returns -1 when memory runs out. */
int neb2_model_predict(const neb2_model *m, const uint8_t *x);

#ifdef __cplusplus
}
#endif

#endif
