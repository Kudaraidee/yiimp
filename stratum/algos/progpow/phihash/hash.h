#ifndef PHIHASH_HASH_H
#define PHIHASH_HASH_H

uint256 phihash_fullhash(uint256& header_hash, uint64_t& header_nonce, uint256& mix_hash, int& coinid);
uint256 phihash_hash(std::string& header_hash, std::string& header_nonce, std::string& mix_real, int& coinid);

#endif // PHIHASH_HASH_H
