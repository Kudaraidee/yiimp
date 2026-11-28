/* meraki: C/C++ implementation of Ethash, the Ethereum Proof of Work algorithm.
 * Copyright 2018-2019 Pawel Bylica.
 * Licensed under the Apache License, Version 2.0.
 */

#pragma once

#include "hash_types.h"
#include <algos/progpow/include/ethash/keccak.h>

#include <stddef.h>

#ifdef __cplusplus
#define NOEXCEPT noexcept
#else
#define NOEXCEPT
#endif

#ifdef __cplusplus
extern "C" {
#endif

union meraki_hash256 meraki_keccak256(const uint8_t* data, size_t size) NOEXCEPT;
union meraki_hash256 meraki_keccak256_32(const uint8_t data[32]) NOEXCEPT;
union meraki_hash512 meraki_keccak512(const uint8_t* data, size_t size) NOEXCEPT;
union meraki_hash512 meraki_keccak512_64(const uint8_t data[64]) NOEXCEPT;

#ifdef __cplusplus
}
#endif
