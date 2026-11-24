// meraki: C/C++ implementation of Ethash, the Ethereum Proof of Work algorithm.
// Copyright 2018-2019 Pawel Bylica.
// Licensed under the Apache License, Version 2.0.

/// @file
///
/// ProgPoW API
///
/// This file provides the public API for ProgPoW as the Ethash API extension.

#include "meraki.hpp"

#if defined(_MSC_VER)
//  Microsoft
#define EXPORT __declspec(dllexport)
#define IMPORT __declspec(dllimport)
#elif defined(__GNUC__)
//  GCC
#define EXPORT __attribute__((visibility("default")))
#define IMPORT
#else
//  do nothing and hope for the best?
#define EXPORT
#define IMPORT
#pragma warning Unknown dynamic link import / export semantics.
#endif

namespace meraki_progpow
{
    using namespace meraki; // Include meraki namespace.

    /// The ProgPoW algorithm revision implemented as specified in the spec
    /// https://github.com/ifdefelse/ProgPOW#change-history.
    constexpr auto revision = "0.9.4";

    constexpr int period_length = 3;
    constexpr uint32_t num_regs = 32;
    constexpr size_t num_lanes = 16;
    constexpr int num_cache_accesses = 12;
    constexpr int num_math_operations = 5;
    constexpr size_t l1_cache_size = 16 * 1024;
    constexpr size_t l1_cache_num_items = l1_cache_size / sizeof(uint32_t);

    result hashext(const epoch_context &context, int block_number, const hash256 &header_hash,
                   uint64_t nonce, const hash256 &mix_hash, const hash256 &boundary1, const hash256 &boundary2, int *retcode) noexcept;

    extern "C" EXPORT result meraki_progpow_hash(const epoch_context &context, int block_number, const hash256 &header_hash,
                                  uint64_t nonce) noexcept;

    result hash(const epoch_context_full &context, int block_number, const hash256 &header_hash,
                uint64_t nonce) noexcept;

    extern "C" EXPORT bool verify(const epoch_context &context, int block_number, const hash256 &header_hash,
                                  const hash256 &mix_hash, uint64_t nonce, const hash256 &boundary) noexcept;

    search_result search_light(const epoch_context &context, int block_number,
                               const hash256 &header_hash, const hash256 &boundary, uint64_t start_nonce,
                               size_t iterations) noexcept;

    search_result search(const epoch_context_full &context, int block_number,
                         const hash256 &header_hash, const hash256 &boundary, uint64_t start_nonce,
                         size_t iterations) noexcept;

} // namespace meraki_progpow
