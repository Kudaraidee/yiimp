// progpow: C/C++ implementation of ProgPow
// Copyright 2018-2019 Pawel Bylica.
// Copyright 2021 Andrea Lanfranchi - Upgrade PP to spec 0.9.4
// Licensed under the Apache License, Version 2.0.

/// @file
///
/// ProgPoW API
///
/// This file provides the public API for ProgPoW as the Ethash API extension.

#pragma once
#ifndef FIROPOW_PROGPOW_HPP
#define FIROPOW_PROGPOW_HPP

#include <algos/progpow/include/ethash/ethash.hpp>

namespace firopow
{
using namespace ethash;  // Include ethash namespace.

/// The ProgPoW algorithm revision implemented as specified in the spec
/// https://github.com/ifdefelse/ProgPOW#change-history.
constexpr auto revision = "0.9.4";

constexpr uint32_t num_regs = 32;
constexpr size_t num_lanes = 16;
constexpr uint32_t num_rounds = 64;
constexpr int num_cache_accesses = 11;
constexpr int num_math_operations = 18;
constexpr size_t l1_cache_size = 16 * 1024;
constexpr size_t l1_cache_num_items = l1_cache_size / sizeof(uint32_t);

result hash(const epoch_context& context, int block_number, const hash256& header_hash, uint64_t nonce) noexcept;

} // namespace firopow

#endif // FIROPOW_PROGPOW_HPP
