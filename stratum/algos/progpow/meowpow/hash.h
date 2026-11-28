#ifndef MEOWPOW_HASH_H
#define MEOWPOW_HASH_H

uint256 meowpow_fullhash(uint256& header_hash, uint64_t& header_nonce, uint256& mix_hash, int& coinid);
uint256 meowpow_hash(std::string& header_hash, std::string& header_nonce, std::string& mix_real, int& coinid);

#endif // MEOWPOW_HASH_H
