// ProgPoW core shared by kawpow, firopow, phihash, meowpow, evrprogpow and
// meraki: the mix loop of cpp-ethash's progpow.cpp (Pawel Bylica,
// Apache-2.0), with the variant constants as template parameters.

#pragma once

#include <algorithm>
#include <array>
#include <cstddef>
#include <cstdint>
#include <utility>

#include <algos/progpow/lib/ethash/bit_manipulation.h>
#include <algos/progpow/lib/ethash/kiss99.hpp>

namespace progpow_core
{

template <uint32_t Regs, int CacheAccesses, int MathOperations, uint32_t Rounds>
struct params
{
    static constexpr uint32_t num_regs = Regs;
    static constexpr int num_cache_accesses = CacheAccesses;
    static constexpr int num_math_operations = MathOperations;
    static constexpr uint32_t num_rounds = Rounds;
};

constexpr size_t num_lanes = 16;
constexpr size_t l1_cache_num_items = 16 * 1024 / sizeof(uint32_t);

using words256 = std::array<uint32_t, 8>;

inline uint32_t le32(uint32_t x) noexcept
{
#if defined(__BYTE_ORDER__) && __BYTE_ORDER__ == __ORDER_BIG_ENDIAN__
    return __builtin_bswap32(x);
#else
    return x;
#endif
}

#if defined(__clang__)
#define PROGPOW_NO_SANITIZE __attribute__((no_sanitize("unsigned-integer-overflow")))
#else
#define PROGPOW_NO_SANITIZE
#endif

/// KISS99 state and the random permutations of mix destinations / sources.
template <uint32_t Regs>
class mix_rng_state
{
public:
    mix_rng_state(uint32_t seed_lo, uint32_t seed_hi) noexcept
    {
        const auto z = fnv1a(fnv_offset_basis, seed_lo);
        const auto w = fnv1a(z, seed_hi);
        const auto jsr = fnv1a(w, seed_lo);
        const auto jcong = fnv1a(jsr, seed_hi);
        rng = kiss99{z, w, jsr, jcong};

        // Fisher-Yates shuffle
        for (uint32_t i = 0; i < Regs; ++i)
        {
            dst_seq[i] = i;
            src_seq[i] = i;
        }
        for (uint32_t i = Regs; i > 1; --i)
        {
            std::swap(dst_seq[i - 1], dst_seq[rng() % i]);
            std::swap(src_seq[i - 1], src_seq[rng() % i]);
        }
    }

    uint32_t next_dst() noexcept { return dst_seq[(dst_counter++) % Regs]; }
    uint32_t next_src() noexcept { return src_seq[(src_counter++) % Regs]; }

    kiss99 rng;

private:
    size_t dst_counter = 0;
    std::array<uint32_t, Regs> dst_seq;
    size_t src_counter = 0;
    std::array<uint32_t, Regs> src_seq;
};

PROGPOW_NO_SANITIZE
inline uint32_t random_math(uint32_t a, uint32_t b, uint32_t selector) noexcept
{
    switch (selector % 11)
    {
    default:
    case 0: return a + b;
    case 1: return a * b;
    case 2: return mul_hi32(a, b);
    case 3: return std::min(a, b);
    case 4: return rotl32(a, b);
    case 5: return rotr32(a, b);
    case 6: return a & b;
    case 7: return a | b;
    case 8: return a ^ b;
    case 9: return clz32(a) + clz32(b);
    case 10: return popcount32(a) + popcount32(b);
    }
}

/// Merge data from `b` into `a`; only ops that keep the entropy of `a`.
PROGPOW_NO_SANITIZE
inline void random_merge(uint32_t& a, uint32_t b, uint32_t selector) noexcept
{
    const auto x = (selector >> 16) % 31 + 1;  // non-zero selector from higher bits
    switch (selector % 4)
    {
    case 0: a = (a * 33) + b; break;
    case 1: a = (a ^ b) * 33; break;
    case 2: a = rotl32(a, x) ^ b; break;
    case 3: a = rotr32(a, x) ^ b; break;
    }
}

template <uint32_t Regs>
using mix_array = std::array<std::array<uint32_t, Regs>, num_lanes>;

// The RNG state is taken by value: every round starts from the same state.
template <class P, class Context, class Lookup>
void round(const Context& context, uint32_t r, mix_array<P::num_regs>& mix,
    mix_rng_state<P::num_regs> state, Lookup lookup)
{
    constexpr uint32_t num_regs = P::num_regs;
    const uint32_t num_items = static_cast<uint32_t>(context.full_dataset_num_items / 2);
    const uint32_t item_index = mix[r % num_lanes][0] % num_items;
    const auto item = lookup(context, item_index);

    constexpr size_t num_words_per_lane = sizeof(item) / (sizeof(uint32_t) * num_lanes);
    constexpr int max_operations = P::num_cache_accesses > P::num_math_operations ?
                                       P::num_cache_accesses : P::num_math_operations;

    for (int i = 0; i < max_operations; ++i)
    {
        if (i < P::num_cache_accesses)  // random access to cached memory
        {
            const auto src = state.next_src();
            const auto dst = state.next_dst();
            const auto sel = state.rng();
            for (size_t l = 0; l < num_lanes; ++l)
            {
                const size_t offset = mix[l][src] % l1_cache_num_items;
                random_merge(mix[l][dst], le32(context.l1_cache[offset]), sel);
            }
        }
        if (i < P::num_math_operations)  // random math
        {
            // 2 unique source indexes
            const auto src_rnd = state.rng() % (num_regs * (num_regs - 1));
            const auto src1 = src_rnd % num_regs;
            auto src2 = src_rnd / num_regs;
            if (src2 >= src1)
                ++src2;

            const auto sel1 = state.rng();
            const auto dst = state.next_dst();
            const auto sel2 = state.rng();
            for (size_t l = 0; l < num_lanes; ++l)
            {
                const uint32_t data = random_math(mix[l][src1], mix[l][src2], sel1);
                random_merge(mix[l][dst], data, sel2);
            }
        }
    }

    // DAG access pattern
    uint32_t dsts[num_words_per_lane];
    uint32_t sels[num_words_per_lane];
    for (size_t i = 0; i < num_words_per_lane; ++i)
    {
        dsts[i] = i == 0 ? 0 : state.next_dst();
        sels[i] = state.rng();
    }

    // DAG access
    for (size_t l = 0; l < num_lanes; ++l)
    {
        const auto offset = ((l ^ r) % num_lanes) * num_words_per_lane;
        for (size_t i = 0; i < num_words_per_lane; ++i)
        {
            const auto word = le32(item.word32s[offset + i]);
            random_merge(mix[l][dsts[i]], word, sels[i]);
        }
    }
}

template <uint32_t Regs>
mix_array<Regs> init_mix(uint32_t seed_lo, uint32_t seed_hi)
{
    const uint32_t z = fnv1a(fnv_offset_basis, seed_lo);
    const uint32_t w = fnv1a(z, seed_hi);

    mix_array<Regs> mix;
    for (uint32_t l = 0; l < mix.size(); ++l)
    {
        const uint32_t jsr = fnv1a(w, l);
        const uint32_t jcong = fnv1a(jsr, l);
        kiss99 rng{z, w, jsr, jcong};
        for (auto& row : mix[l])
            row = rng();
    }
    return mix;
}

/// Mix digest (as little-endian words) for the 64-bit KISS99 seed and the
/// period number (block_number / period_length).
template <class P, class Context, class Lookup>
words256 hash_mix(const Context& context, uint64_t period, uint32_t seed_lo, uint32_t seed_hi,
    Lookup lookup) noexcept
{
    auto mix = init_mix<P::num_regs>(seed_lo, seed_hi);
    const mix_rng_state<P::num_regs> state{
        static_cast<uint32_t>(period), static_cast<uint32_t>(period >> 32)};

    for (uint32_t i = 0; i < P::num_rounds; ++i)
        round<P>(context, i, mix, state, lookup);

    // reduce mix data to a single per-lane result
    uint32_t lane_hash[num_lanes];
    for (size_t l = 0; l < num_lanes; ++l)
    {
        lane_hash[l] = fnv_offset_basis;
        for (uint32_t i = 0; i < P::num_regs; ++i)
            lane_hash[l] = fnv1a(lane_hash[l], mix[l][i]);
    }

    // reduce all lanes to a single 256-bit result
    words256 mix_hash;
    mix_hash.fill(fnv_offset_basis);
    for (size_t l = 0; l < num_lanes; ++l)
        mix_hash[l % 8] = fnv1a(mix_hash[l % 8], lane_hash[l]);
    for (auto& w : mix_hash)
        w = le32(w);
    return mix_hash;
}

/// KawPoW-style seed and final hash: Keccak-f[800] over header, nonce and the
/// coin's 15 seed words (kawpow, phihash, meowpow, evrprogpow, meraki).
struct output
{
    words256 final_hash;
    words256 mix_hash;
};

template <class P, class Context, class Lookup>
output hash_kawpow_style(const Context& context, uint64_t period, const uint32_t header[8],
    uint64_t nonce, const uint32_t (&seed_words)[15], void (*keccakf800)(uint32_t*),
    Lookup lookup) noexcept
{
    uint32_t seed[8];
    {
        uint32_t state[25];
        for (int i = 0; i < 8; i++)
            state[i] = header[i];
        state[8] = static_cast<uint32_t>(nonce);
        state[9] = static_cast<uint32_t>(nonce >> 32);
        for (int i = 10; i < 25; i++)
            state[i] = seed_words[i - 10];
        keccakf800(state);
        for (int i = 0; i < 8; i++)
            seed[i] = state[i];
    }

    output out;
    out.mix_hash = hash_mix<P>(context, period, seed[0], seed[1], lookup);

    uint32_t state[25];
    for (int i = 0; i < 8; i++)
        state[i] = seed[i];
    for (int i = 8; i < 16; i++)
        state[i] = out.mix_hash[i - 8];
    for (int i = 16; i < 25; i++)
        state[i] = seed_words[i - 16];
    keccakf800(state);

    for (int i = 0; i < 8; ++i)
        out.final_hash[i] = le32(state[i]);
    return out;
}

}  // namespace progpow_core
