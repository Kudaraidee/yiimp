/* xhash: C/C++ implementation of XHash, the Ethereum Proof of Work algorithm.
 * Copyright 2018-2019 Pawel Bylica.
 * Licensed under the Apache License, Version 2.0.
 */

#pragma once

#include <xhash/hash_types.h>

#include <stddef.h>

#ifndef __cplusplus
#define noexcept  // Ignore noexcept in C code.
#endif

#ifdef __cplusplus
extern "C" {
#endif

union xhash_hash256 xhash_keccak256(const uint8_t* data, size_t size) noexcept;
union xhash_hash256 xhash_keccak256_32(const uint8_t data[32]) noexcept;
union xhash_hash512 xhash_keccak512(const uint8_t* data, size_t size) noexcept;
union xhash_hash512 xhash_keccak512_64(const uint8_t data[64]) noexcept;

#ifdef __cplusplus
}
#endif
