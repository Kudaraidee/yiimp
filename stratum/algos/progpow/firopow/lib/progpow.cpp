// ProgPoW 0.9.4 light verification for firopow: own seed and final hash,
// mix loop from the shared core (progpow/core.hpp).
// Based on cpp-ethash's progpow.cpp, Copyright 2018-2019 Pawel Bylica,
// and Andrea Lanfranchi's 0.9.4 upgrade. Licensed under the Apache License, Version 2.0.

#include <algos/progpow/firopow/include/progpow.hpp>

#include <algos/progpow/lib/ethash/endianness.hpp>
#include <algos/progpow/lib/ethash/ethash-internal.hpp>
#include <algos/progpow/include/ethash/keccak.hpp>
#include <algos/progpow/firopow/overrides.h>
#include <algos/progpow/core.hpp>

#include <cstring>

namespace firopow
{
namespace
{
using P = progpow_core::params<num_regs, num_cache_accesses, num_math_operations, num_rounds>;
}  // namespace

hash256 hash_seed(const hash256 *header_hash_ptr, uint64_t nonce) noexcept
{
    uint32_t state[25] = {0x0};
    nonce = le::uint64(nonce);

    for (int i = 0; i < 8; ++i) {
        state[i] = le::uint32(header_hash_ptr->word32s[i]);
    }
    std::memcpy(&state[8], &nonce, sizeof(uint64_t));
    state[10] = 0x00000001;
    state[18] = 0x80008081;

    ethash_keccakf800(state);

    hash256 output;
    for (int i = 0; i < 8; ++i)
        output.word32s[i] = le::uint32(state[i]);
    return output;
}

hash256 hash_final(const hash256& seed_hash, const hash256 *mix_hash_ptr) noexcept
{
    uint32_t state[25] = {0x0};
    std::memcpy(&state[0], seed_hash.bytes, sizeof(hash256));
    std::memcpy(&state[8], mix_hash_ptr, sizeof(hash256));
    state[17] = 0x00000001;
    state[24] = 0x80008081;

    ethash_keccakf800(state);

    hash256 output;
    std::memcpy(output.bytes, &state[0], sizeof(hash256));
    return output;
}

result hash(const epoch_context& context, int block_number, const hash256& header_hash, uint64_t nonce) noexcept
{
    const hash256 seed_hash = hash_seed(&header_hash, nonce);
    const uint64_t seed = seed_hash.word64s[0];
    const uint64_t period = uint64_t(block_number / get_ethash_period_length());
    const auto mix = progpow_core::hash_mix<P>(context, period, static_cast<uint32_t>(seed),
        static_cast<uint32_t>(seed >> 32), calculate_dataset_item_2048);
    hash256 mix_hash;
    std::memcpy(mix_hash.word32s, mix.data(), sizeof(mix_hash));
    const hash256 final_hash = hash_final(seed_hash, &mix_hash);
    return {final_hash, mix_hash};
}

}  // namespace firopow
