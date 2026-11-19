// Ethash / Etchash parameters for the yiimp stratum.
// Distributed under the MIT software license.

#ifndef YAAMP_ETHASH_PARAMS_H
#define YAAMP_ETHASH_PARAMS_H

#include <stdint.h>

struct ETHASH_PARAMS
{
	// Length of an epoch in blocks. Classic Ethash is 30000, Etchash (ECIP-1099)
	// switches to 60000 at the fork.
	int epoch_length;

	// ECIP-1099 keeps the original epoch numbering for the fork epoch itself.
	int seed_epoch_length;

	// Etchash fork. fork_epoch is the epoch number at fork_block using the old
	// 30000 blocks/epoch numbering. 0 means "derive from fork_block".
	int etchash_fork_block;
	int etchash_fork_epoch;

	// Some chains start their DAG at a non-zero epoch.
	int epoch_offset;

	// Informational only (standard Ethash does not commit to chain id in PoW).
	int chain_id;

	// Size of the initial full dataset, used by the vendored ethash library to
	// derive the dataset item count (light verification only).
	unsigned int dag_init_size;
};

extern ETHASH_PARAMS g_ethash_params;

void ethash_params_load(dictionary *ini);
int ethash_epoch_for_height(int height);
void ethash_seed_for_epoch(int epoch, char *hex66);
bool ethash_parse_hex_target(const char *hex, uint8_t be[32]);
void ethash_diff_to_target(double diff, uint8_t be[32]);
void ethash_target_to_diff(const uint8_t be[32], double *diff);

// Ethereum hashes/targets are big endian, uint256 is little endian internally.
inline void ethash_uint256_to_be(const uint256 &value, uint8_t be[32])
{
	for (int i = 0; i < 32; i++)
		be[i] = value.begin()[31 - i];
}

inline uint256 ethash_be_to_uint256(const uint8_t be[32])
{
	uint256 value;
	for (int i = 0; i < 32; i++)
		value.begin()[31 - i] = be[i];
	return value;
}

#endif // YAAMP_ETHASH_PARAMS_H
