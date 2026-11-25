// Copyright (c) 2024 Hoosat Oy
// Copyright (c) 2024 PePe-core developers
// Distributed under the MIT software license, see the accompanying
// file COPYING or http://www.opensource.org/licenses/mit-license.php.
//
// HoohashV110 Proof of Work Algorithm
// Adapted from https://github.com/HoosatNetwork/hoohash/ commit 9634f11410a2d71be21086e813263fa007fb6810

#include "hoohash.h"
#include "blake3/blake3.h"
#include <string.h>
#include <math.h>

// Constants
#define PI 3.14159265358979323846
#define EPS 1e-9
#define COMPLEX_TRANSFORM_MULTIPLIER 0.000001

// xoshiro256** PRNG state
typedef struct {
    uint64_t s0;
    uint64_t s1;
    uint64_t s2;
    uint64_t s3;
} xoshiro_state;

// Safe memory read functions to avoid UB from unaligned access
static inline uint64_t read_uint64_le(const uint8_t *data) {
    uint64_t result = 0;
    for (int i = 0; i < 8; i++) {
        result |= ((uint64_t)data[i]) << (i * 8);
    }
    return result;
}

static inline uint32_t read_uint32_le(const uint8_t *data) {
    return ((uint32_t)data[0]) |
           ((uint32_t)data[1] << 8) |
           ((uint32_t)data[2] << 16) |
           ((uint32_t)data[3] << 24);
}

static inline uint32_t read_uint32_be(const uint8_t *data) {
    return ((uint32_t)data[0] << 24) |
           ((uint32_t)data[1] << 16) |
           ((uint32_t)data[2] << 8) |
           (uint32_t)data[3];
}

// xoshiro256** functions
static inline uint64_t rotl64(const uint64_t x, int k) {
    return (x << k) | (x >> (64 - k));
}

static void xoshiro_init(xoshiro_state *state, const uint8_t *bytes) {
    state->s0 = read_uint64_le(&bytes[0]);
    state->s1 = read_uint64_le(&bytes[8]);
    state->s2 = read_uint64_le(&bytes[16]);
    state->s3 = read_uint64_le(&bytes[24]);
}

static uint64_t xoshiro_gen(xoshiro_state *x) {
    uint64_t res = rotl64(x->s0 + x->s3, 23) + x->s0;
    uint64_t t = x->s1 << 17;

    x->s2 ^= x->s0;
    x->s3 ^= x->s1;
    x->s1 ^= x->s2;
    x->s0 ^= x->s3;

    x->s2 ^= t;
    x->s3 = rotl64(x->s3, 45);

    return res;
}

// Complex nonlinear transformations
static double MediumComplexNonLinear(double x) {
    return exp(sin(x) + cos(x));
}

static double IntermediateComplexNonLinear(double x) {
    if (fabs(x - PI / 2) < EPS || fabs(x - 3 * PI / 2) < EPS) {
        return 0; // Avoid singularity
    }
    return sin(x) * sin(x);
}

static double HighComplexNonLinear(double x) {
    return 1.0 / sqrt(fabs(x) + 1);
}

static double ComplexNonLinear(double x) {
    double transformFactorOne = (x * COMPLEX_TRANSFORM_MULTIPLIER) / 8.0 - floor((x * COMPLEX_TRANSFORM_MULTIPLIER) / 8.0);
    double transformFactorTwo = (x * COMPLEX_TRANSFORM_MULTIPLIER) / 4.0 - floor((x * COMPLEX_TRANSFORM_MULTIPLIER) / 4.0);

    if (transformFactorOne < 0.33) {
        if (transformFactorTwo < 0.25) {
            return MediumComplexNonLinear(x + (1 + transformFactorTwo));
        } else if (transformFactorTwo < 0.5) {
            return MediumComplexNonLinear(x - (1 + transformFactorTwo));
        } else if (transformFactorTwo < 0.75) {
            return MediumComplexNonLinear(x * (1 + transformFactorTwo));
        } else {
            return MediumComplexNonLinear(x / (1 + transformFactorTwo));
        }
    } else if (transformFactorOne < 0.66) {
        if (transformFactorTwo < 0.25) {
            return IntermediateComplexNonLinear(x + (1 + transformFactorTwo));
        } else if (transformFactorTwo < 0.5) {
            return IntermediateComplexNonLinear(x - (1 + transformFactorTwo));
        } else if (transformFactorTwo < 0.75) {
            return IntermediateComplexNonLinear(x * (1 + transformFactorTwo));
        } else {
            return IntermediateComplexNonLinear(x / (1 + transformFactorTwo));
        }
    } else {
        if (transformFactorTwo < 0.25) {
            return HighComplexNonLinear(x + (1 + transformFactorTwo));
        } else if (transformFactorTwo < 0.5) {
            return HighComplexNonLinear(x - (1 + transformFactorTwo));
        } else if (transformFactorTwo < 0.75) {
            return HighComplexNonLinear(x * (1 + transformFactorTwo));
        } else {
            return HighComplexNonLinear(x / (1 + transformFactorTwo));
        }
    }
}

static double SafeComplexTransform(double input) {
    double transformedValue;
    double rounds = 1;
    transformedValue = ComplexNonLinear(input);
    while (isnan(transformedValue) || isinf(transformedValue)) {
        input = input * 0.1;
        if (input <= 0.0000000000001) {
            return 0;
        }
        rounds++;
    }
    return transformedValue * rounds;
}

static void generateHoohashMatrix(const uint8_t *hash, double mat[64][64]) {
    xoshiro_state state;
    xoshiro_init(&state, hash);
    double normalize = 1000000.0;
    for (int i = 0; i < 64; i++) {
        for (int j = 0; j < 64; j++) {
            uint64_t val = xoshiro_gen(&state);
            uint32_t lower_4_bytes = val & 0xFFFFFFFF;
            mat[i][j] = (double)lower_4_bytes / (double)UINT32_MAX * normalize;
        }
    }
}

static double TransformFactor(double x) {
    const double granularity = 1024.0;
    return x / granularity - floor(x / granularity);
}

static void ConvertBytesToUint32Array(uint32_t *H, const uint8_t *bytes) {
    for (int i = 0; i < 8; i++) {
        H[i] = read_uint32_be(&bytes[i * 4]);
    }
}

static void HoohashMatrixMultiplication(double mat[64][64], const uint8_t *hashBytes, uint8_t *output, uint64_t nonce) {
    uint8_t scaledValues[32] = {0};
    uint8_t vector[64] = {0};
    double product[64] = {0};
    uint8_t result[32] = {0};
    uint32_t H[8] = {0};
    
    ConvertBytesToUint32Array(H, hashBytes);
    double hashXor = (double)(H[0] ^ H[1] ^ H[2] ^ H[3] ^ H[4] ^ H[5] ^ H[6] ^ H[7]);
    double nonceMod = (double)(nonce & 0xFF);
    double divider = 0.0001;
    double multiplier = 1234;
    double sw = 0.0;

    for (int i = 0; i < 32; i++) {
        vector[2 * i] = hashBytes[i] >> 4;
        vector[2 * i + 1] = hashBytes[i] & 0x0F;
    }

    // Consensus (PePe-core, gcc default -ffp-contract=fast on an FMA target)
    // fuses exactly these three multiply-adds. Spell them out with fma() and
    // build this file with -ffp-contract=off, so the result does not depend
    // on -march.
    for (int i = 0; i < 64; i++) {
        for (int j = 0; j < 64; j++) {
            if (sw <= 0.02) {
                double input = fma(mat[i][j] * hashXor, (double)vector[j], nonceMod);
                product[i] = fma(SafeComplexTransform(input) * (double)vector[j], multiplier, product[i]);
            } else {
                product[i] = fma(mat[i][j] * divider, (double)vector[j], product[i]);
            }
            sw = TransformFactor(product[i]);
        }
    }

    for (int i = 0; i < 64; i += 2) {
        uint64_t pval = (uint64_t)product[i] + (uint64_t)product[i + 1];
        scaledValues[i / 2] = (uint8_t)(pval & 0xFF);
    }

    for (int i = 0; i < 32; i++) {
        result[i] = hashBytes[i] ^ scaledValues[i];
    }
    
    blake3_hasher hasher;
    blake3_hasher_init(&hasher);
    blake3_hasher_update(&hasher, result, HOOHASH_HASH_SIZE);
    blake3_hasher_finalize(&hasher, output, HOOHASH_HASH_SIZE);
}


// Main HoohashV110 function
// For Bitcoin-derived blockchain PoW, we hash the entire (80-byte) block header
// BUT - for the matrix, so there is only one per Tip to solve, we zero the nonce

static void hoohash_hash_impl(const void* data, size_t len, uint8_t* out32)
{
    // Enforce the preimage definition: header bytes from nVersion..nNonce (80 bytes).
    // This prevents accidental consensus changes if callers pass a different length.
    if (len != 80) {
        // Deterministic error result: all 0xff is the worst possible hash and
        // fails every target. All-zero would be the best one: it passed every
        // share and block target check.
        memset(out32, 0xff, HOOHASH_HASH_SIZE);
        return;
    }

    blake3_hasher hasher;
    uint8_t firstPass[HOOHASH_HASH_SIZE];
    uint8_t matrixSeed[HOOHASH_HASH_SIZE];
    double mat[64][64];

    // First BLAKE3 pass on the full 80-byte header (nonce included).
    blake3_hasher_init(&hasher);
    blake3_hasher_update(&hasher, data, len);
    blake3_hasher_finalize(&hasher, firstPass, HOOHASH_HASH_SIZE);

    /*
     * NEW CONSENSUS (PePePoW):
     * The per-block matrix must NOT depend on the nonce.
     * matrixSeed = BLAKE3(header80 with nonce bytes zeroed)
     * nonce is still used later in HoohashMatrixMultiplication().
     */
    uint8_t masked_header[80];
    memcpy(masked_header, data, 80);
    memset(masked_header + 76, 0, 4); /* zero nNonce (uint32 little-endian at offset 76) */

    blake3_hasher_init(&hasher);
    blake3_hasher_update(&hasher, masked_header, 80);
    blake3_hasher_finalize(&hasher, matrixSeed, HOOHASH_HASH_SIZE);

    generateHoohashMatrix(matrixSeed, mat);

    // Bitcoin/Dash-style headers: nonce is 4 bytes at offset 76, little-endian
    const uint8_t* nonce_ptr = ((const uint8_t*)data) + 76;
    const uint64_t nonce = (uint64_t)read_uint32_le(nonce_ptr);

    // Perform matrix multiplication using:
    // - firstPass derived from the full header (nonce-dependent)
    // - mat derived from masked header (nonce-independent)
    HoohashMatrixMultiplication(mat, firstPass, out32, nonce);
}

void hoohash_hash(const char* input, char* output, uint32_t len)
{
    hoohash_hash_impl(input, (size_t)len, (uint8_t*)output);
}