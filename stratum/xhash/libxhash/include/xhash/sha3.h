/* xhash: C/C++ implementation of XHash proof-of-work.
 * SHA-3 (NIST) C API (mirrors keccak.h shape).
 * Apache-2.0
 */

#pragma once

#include <stddef.h>
#include <stdint.h>
#include <xhash/hash_types.h>

#ifndef __cplusplus
#define noexcept /* Ignore noexcept in C code. */
#endif

#ifdef __cplusplus
extern "C" {
#endif

/* One-shot SHA3-256 */
union xhash_hash256 xhash_sha3_256(const uint8_t* data, size_t size) noexcept;
/* Convenience: fixed-size 32B input */
union xhash_hash256 xhash_sha3_256_32(const uint8_t data[32]) noexcept;

/* One-shot SHA3-512 */
union xhash_hash512 xhash_sha3_512(const uint8_t* data, size_t size) noexcept;
/* Convenience: fixed-size 64B input */
union xhash_hash512 xhash_sha3_512_64(const uint8_t data[64]) noexcept;

#ifdef __cplusplus
} /* extern "C" */
#endif
