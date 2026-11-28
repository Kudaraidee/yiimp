// ProgPoW light verification for phihash, on the shared core (progpow/core.hpp).
// Based on cpp-ethash's progpow.cpp, Copyright 2018-2019 Pawel Bylica.
// Licensed under the Apache License, Version 2.0.

#include <algos/progpow/phihash/include/progpow.hpp>

#include <algos/progpow/lib/ethash/ethash-internal.hpp>
#include <algos/progpow/include/ethash/keccak.hpp>
#include <algos/progpow/firopow/overrides.h>
#include <algos/progpow/core.hpp>

#include <cstring>

namespace phihash
{
namespace
{
using P = progpow_core::params<num_regs, num_cache_accesses, num_math_operations, 64>;

// "ILOVEPOWPHICOIN"
const uint32_t seed_words[15] = {
    0x00000049, 0x0000004C, 0x0000004F, 0x00000056, 0x00000045,
    0x00000050, 0x0000004F, 0x00000057, 0x00000050, 0x00000048,
    0x00000049, 0x00000043, 0x0000004F, 0x00000049, 0x0000004E,
};
}  // namespace

result hash(const epoch_context& context, int block_number, const hash256& header_hash,
    uint64_t nonce) noexcept
{
    const uint64_t period = uint64_t(block_number / get_ethash_period_length());
    const auto r = progpow_core::hash_kawpow_style<P>(context, period, header_hash.word32s, nonce,
        seed_words, ethash_keccakf800, calculate_dataset_item_2048);
    result out;
    std::memcpy(out.final_hash.word32s, r.final_hash.data(), sizeof(out.final_hash));
    std::memcpy(out.mix_hash.word32s, r.mix_hash.data(), sizeof(out.mix_hash));
    return out;
}

}  // namespace phihash
