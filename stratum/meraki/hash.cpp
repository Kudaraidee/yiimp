// Meraki (Telestai Progpow) light-verifier integration for Yiimp stratum.
// The vendored C++ implementation lives in meraki/lib/ and is derived from
// Miningcore's Native/libmeraki. Its symbols are renamed so it can coexist
// with the existing Ethash/Kawpow/Firo objects in the same binary.

#include "stratum.h"
#include "meraki/hash.h"
#include "meraki/helpers.hpp"
#include "meraki/lib/meraki/progpow.hpp"

std::vector<meraki_coin_context*> meraki_contexts;

int meraki_epoch_for_height(int height)
{
    return height > 0 ? height / MERAKI_EPOCH_LENGTH : 0;
}

uint256 meraki_epoch_seed(int height)
{
    const auto seed = meraki::calculate_epoch_seed(meraki_epoch_for_height(height));
    return uint256S(meraki_to_hex(seed));
}

uint256 meraki_diff_to_target(double difficulty)
{
    // diff_to_target() uses Yiimp's Firo-scale Diff1 (0x0000ffff...).
    // Telestai's Diff1 is 0x00ff..., so shift the target down 8 bits.
    uint256 firo_target;
    diff_to_target(firo_target, difficulty);
    return ArithToUint256(UintToArith256(firo_target) >> 8);
}

double meraki_target_to_diff(uint256& target)
{
    if (target.IsNull()) return 0;

    arith_uint256 base_hash;
    base_hash.SetHex("00000000ff0000000000000000000000000000000000000000000000000000");
    return base_hash.getdouble() / UintToArith256(target).getdouble();
}

void meraki_update_epoch(const int coinid, int& height)
{
    CommonLock(&g_context_mutex);

    for (auto& item : meraki_contexts) {
        if (item->id == coinid) {
            if (item->height == height) {
                CommonUnlock(&g_context_mutex);
                return;
            }

            const auto epoch_number = meraki_epoch_for_height(height);
            if (item->context->epoch_number != epoch_number)
                item->context = meraki::create_epoch_context(epoch_number);

            item->height = height;
            CommonUnlock(&g_context_mutex);
            return;
        }
    }

    const auto epoch_number = meraki_epoch_for_height(height);
    auto* item = new meraki_coin_context {
        coinid, height, meraki::create_epoch_context(epoch_number)
    };
    meraki_contexts.push_back(item);

    CommonUnlock(&g_context_mutex);
}

meraki_coin_context* meraki_get_coin_context(const int coinid)
{
    for (auto& item : meraki_contexts) {
        if (item->id == coinid)
            return item;
    }

    return nullptr;
}

uint256 meraki_fullhash(uint256& header_hash, uint64_t& header_nonce,
    uint256& mix_hash, int& coinid)
{
    meraki_coin_context* context = meraki_get_coin_context(coinid);
    if (!context)
        return badhash;

    const auto hash = to_meraki_hash256(header_hash.ToString());
    const auto result = meraki_progpow::meraki_progpow_hash(
        *context->context, context->height, hash, header_nonce);
    mix_hash = uint256S(meraki_to_hex(result.mix_hash));

    return uint256S(meraki_to_hex(result.final_hash));
}

uint256 meraki_hash(std::string& header_hash, std::string& header_nonce,
    std::string& mix_real, int& coinid)
{
    uint256 mix_hash;
    uint256 header = uint256S(header_hash);
    uint64_t nonce = strtoull(header_nonce.c_str(), NULL, 16);
    uint256 result_hash = meraki_fullhash(header, nonce, mix_hash, coinid);

    mix_real = mix_hash.ToString();
    return result_hash;
}
