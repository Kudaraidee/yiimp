// Copyright (c) 2023 barrystyle
// Distributed under the MIT software license, see the accompanying
// file COPYING or http://www.opensource.org/licenses/mit-license.php.

#include "stratum.h"

const int get_ethash_epoch_length()
{
	if (is_firopow)
		return 1300;
	if (is_kawpow)
		return 7500;
	if (is_meowpow)
		return 7500;
	if (is_evrprogpow)
		return 12000;
	if (is_meraki)
		return 27500;
	if (is_phihash)
		return 30000;
	if (is_ethash)
		return g_ethash_params.seed_epoch_length;
	if (is_etchash)
		return g_ethash_params.epoch_length;
	return 0;
}

const int get_ethash_period_length()
{
	if (is_firopow)
		return 1;
	if (is_kawpow)
		return 3;
	if (is_meowpow)
		return 6;
	if (is_evrprogpow)
		return 3;
	if (is_meraki)
		return 3;
	if (is_phihash)
		return 3;
	if (is_eth)
		return 1;
	return 0;
}

const unsigned int get_full_dataset_init_size()
{
	if (is_firopow)
		return (1 << 30) + (1 << 29);
	if (is_kawpow)
		return (1 << 30);
	if (is_meowpow)
		return (1U << 30);
	if (is_evrprogpow)
		return (1U << 30) * 3U;
	if (is_meraki)
		return (1U << 30);
	if (is_phihash)
		return (1 << 30);
	if (is_eth)
		return g_ethash_params.dag_init_size ? g_ethash_params.dag_init_size : (1U << 30);
	return 0;
}

// Ethash/Etchash use 256 FNV rounds per DAG item. Kawpow and its derivatives
// increased this to 512; sharing that value with ETH mode makes the light
// verifier generate a different mix digest than real miners.
const int get_full_dataset_item_parents()
{
	if (is_eth)
		return 256;
	return 512;
}

const int get_epoch_number(int block_number)
{
	if (is_eth)
		return ethash_epoch_for_height(block_number);
	return block_number ? block_number / get_ethash_epoch_length() : 0;
}
