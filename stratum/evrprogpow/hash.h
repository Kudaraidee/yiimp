#ifndef EVRPROGPOW_HASH_H
#define EVRPROGPOW_HASH_H

uint256 evrprogpow_fullhash(uint256& header_hash, uint64_t& header_nonce, uint256& mix_hash, int& coinid);
uint256 evrprogpow_hash(std::string& header_hash, std::string& header_nonce, std::string& mix_real, int& coinid);

#endif // EVRPROGPOW_HASH_H
