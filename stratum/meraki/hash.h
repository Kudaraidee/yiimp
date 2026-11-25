// Meraki (Telestai Progpow) light-verifier integration for Yiimp stratum.

#ifndef YAAMP_MERAKI_HASH_H
#define YAAMP_MERAKI_HASH_H

#include "meraki/lib/meraki/meraki.hpp"

#include <vector>

// Telestai's Meraki epoch is fixed at 27500 blocks; MERAKI_EPOCH_LENGTH is
// provided by meraki/lib/meraki/meraki.h included above.

struct meraki_coin_context {
    int id;
    int height;
    meraki::epoch_context_ptr context{nullptr, nullptr};
};

extern std::vector<meraki_coin_context*> meraki_contexts;

int meraki_epoch_for_height(int height);
uint256 meraki_epoch_seed(int height);
// Telestai uses Diff1 = 00000000ff00... (Ravencoin scale, not Firo scale).
uint256 meraki_diff_to_target(double difficulty);
double meraki_target_to_diff(uint256& target);
void meraki_update_epoch(const int coinid, int& height);
meraki_coin_context* meraki_get_coin_context(const int coinid);
uint256 meraki_fullhash(uint256& header_hash, uint64_t& header_nonce,
    uint256& mix_hash, int& coinid, int height);
uint256 meraki_hash(const std::string& header_hash, const std::string& header_nonce,
    std::string& mix_real, int& coinid, int height);

#endif // YAAMP_MERAKI_HASH_H
