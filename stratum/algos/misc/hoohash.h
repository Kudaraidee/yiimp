// Copyright (c) 2024 Hoosat Oy
// Copyright (c) 2024 PePe-core developers
// Distributed under the MIT software license, see the accompanying
// file COPYING or http://www.opensource.org/licenses/mit-license.php.
//
// HoohashV110 Proof of Work Algorithm
// Adapted from https://github.com/HoosatNetwork/hoohash/ commit 9634f11410a2d71be21086e813263fa007fb6810

#ifndef HOOHASH_H
#define HOOHASH_H

#include <stdint.h>

#ifdef __cplusplus
extern "C" {
#endif

// Define the size of the hash domain
#define HOOHASH_HASH_SIZE 32

// Stratum / YAAMP: binary hash into output (32 bytes)
void hoohash_hash(const char* input, char* output, uint32_t len);

#ifdef __cplusplus
}
#endif

#endif // HOOHASH_H