// Meraki (Telestai Progpow) light-verifier integration for Yiimp stratum.

#ifndef YAAMP_MERAKI_HASH_H
#define YAAMP_MERAKI_HASH_H

#include "meraki/lib/meraki/meraki.hpp"

#include <vector>

// Telestai's Meraki epoch is fixed at 27500 blocks (Miningcore
// TelestaiConstants.EpochLength and libmeraki MERAKI_EPOCH_LENGTH).
const int MERAKI_EPOCH_LENGTH = 27500;

struct meraki_coin_context {
    int id;
    int height;
    meraki::epoch_context_ptr context{nullptr, nullptr};
};

extern std::vector<meraki_coin_context*> meraki_contexts;

int meraki_epoch_for_height(int height);
uint256 meraki_epoch_seed(int height);
// Telestai uses Diff1 = 0x00000000ff0000... (1/256 of the Firo/Yiimp scale).
uint256 meraki_diff_to_target(double difficulty);
double meraki_target_to_diff(uint256& target);
void meraki_update_epoch(const int coinid, int& height);
meraki_coin_context* meraki_get_coin_context(const int coinid);
uint256 meraki_fullhash(uint256& header_hash, uint64_t& header_nonce,
    uint256& mix_hash, int& coinid);
uint256 meraki_hash(std::string& header_hash, std::string& header_nonce,
    std::string& mix_real, int& coinid);

#endif // YAAMP_MERAKI_HASH_H
