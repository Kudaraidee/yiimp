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
	if (is_phihash)
		return 30000;
	return 0;
}

const int get_ethash_period_length()
{
	if (is_firopow)
		return 1;
	if (is_kawpow)
		return 3;
	if (is_phihash)
		return 3;
	return 0;
}

const int get_full_dataset_init_size()
{
	if (is_firopow)
		return (1 << 30) + (1 << 29);
	if (is_kawpow)
		return (1 << 30);
	if (is_phihash)
		return (1 << 30);
	return 0;
}

const int get_epoch_number(int block_number)
{
    return block_number ? block_number / get_ethash_epoch_length() : 0;
}
