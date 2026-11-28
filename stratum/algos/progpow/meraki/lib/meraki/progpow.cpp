// ProgPoW light verification for meraki (Telestai), on the shared core
// (progpow/core.hpp) with meraki's DAG.
// Based on cpp-ethash's progpow.cpp, Copyright 2018-2019 Pawel Bylica.
// Licensed under the Apache License, Version 2.0.

#include "progpow.hpp"

#include "meraki-internal.hpp"
#include "keccak.hpp"
#include <algos/progpow/core.hpp>

#include <cstring>

namespace meraki_progpow
{
namespace
{
using P = progpow_core::params<num_regs, num_cache_accesses, num_math_operations, 32>;

// "RAVENCOINKAWPOW" (as Telestai has it)
const uint32_t seed_words[15] = {
    0x00000072, 0x00000041, 0x00000056, 0x00000045, 0x0000004E,
    0x00000043, 0x0000004F, 0x00000049, 0x0000004E, 0x0000004B,
    0x00000041, 0x00000057, 0x00000050, 0x0000004F, 0x00000057,
};
}  // namespace

result meraki_progpow_hash(const epoch_context& context, int block_number,
    const hash256& header_hash, uint64_t nonce) noexcept
{
    const uint64_t period = uint64_t(block_number / period_length);
    const auto r = progpow_core::hash_kawpow_style<P>(context, period, header_hash.word32s, nonce,
        seed_words, ethash_keccakf800, calculate_dataset_item_2048);
    result out;
    std::memcpy(out.final_hash.word32s, r.final_hash.data(), sizeof(out.final_hash));
    std::memcpy(out.mix_hash.word32s, r.mix_hash.data(), sizeof(out.mix_hash));
    return out;
}

}  // namespace meraki_progpow
