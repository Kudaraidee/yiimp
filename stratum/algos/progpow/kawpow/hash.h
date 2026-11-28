#ifndef KAWPOW_HASH_H
#define KAWPOW_HASH_H

#include "helpers.hpp"

struct coin_context {
    int id;
    int height;
    ethash::epoch_context_ptr context{nullptr,nullptr};
};

extern std::vector<coin_context*> coin_contexts;

const uint256 badhash = uint256S("ffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffff");

coin_context* get_coin_context(const int& coinid);
void update_epoch(const int& coinid, int& height);
uint256 kawpow_fullhash(uint256& header_hash, uint64_t& header_nonce, uint256& mix_hash, int& coinid);
uint256 kawpow_hash(std::string& header_hash, std::string& header_nonce, std::string& mix_real, int& coinid);

#endif // KAWPOW_HASH_H
