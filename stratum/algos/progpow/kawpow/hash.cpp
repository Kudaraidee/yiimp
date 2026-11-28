// Copyright (c) 2023 barrystyle
// Distributed under the MIT software license, see the accompanying
// file COPYING or http://www.opensource.org/licenses/mit-license.php.

#include "stratum.h"
#include "algos/progpow/kawpow/include/progpow.hpp"

std::vector<coin_context*> coin_contexts;

void update_epoch(const int& coinid, int& height)
{
    CommonLock(&g_context_mutex);

    for (auto& item : coin_contexts) {
        if (item->id == coinid) {
            if (item->height == height) {
                CommonUnlock(&g_context_mutex);
                return;
            }

            const auto epoch_number = get_epoch_number(height);
            if (item->context->epoch_number != epoch_number) {
                item->context = ethash::create_epoch_context(epoch_number);
            }

            item->height = height;
            CommonUnlock(&g_context_mutex);
            return;
        }
    }

    const auto epoch_number = get_epoch_number(height);
    coin_context* item = new coin_context {coinid, height, ethash::create_epoch_context(epoch_number)};
    coin_contexts.push_back(item);

    CommonUnlock(&g_context_mutex);
}

coin_context* get_coin_context(const int& coinid)
{
    for (auto& item : coin_contexts) {
        if (item->id == coinid) {
            return item;
        }
    }

    return nullptr;
}

uint256 kawpow_fullhash(uint256& header_hash, uint64_t& header_nonce, uint256& mix_hash, int& coinid)
{
    coin_context* context = get_coin_context(coinid);
    if (!context) {
        return badhash;
    }

    const auto hash = to_hash256(header_hash.ToString());
    const auto result = kawpow::hash(*context->context, context->height, hash, header_nonce);
    mix_hash = uint256S(to_hex(result.mix_hash));
    uint256 result_hash = uint256S(to_hex(result.final_hash));

    return result_hash;
}

uint256 kawpow_hash(std::string& header_hash, std::string& header_nonce, std::string& mix_real, int& coinid)
{
    uint256 mix_hash;

    uint256 header = uint256S(header_hash);
    uint64_t nonce = strtoull(header_nonce.c_str(), NULL, 16);
    uint256 result_hash = kawpow_fullhash(header, nonce, mix_hash, coinid);

    mix_real = mix_hash.ToString();
    return result_hash;
}
