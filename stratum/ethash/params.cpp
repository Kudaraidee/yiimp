// Ethash / Etchash parameters for the yiimp stratum.
// Distributed under the MIT software license.

#include "stratum.h"

#include <kawpow/helpers.hpp>
#include <kawpow/include/ethash/ethash.hpp>

#include <math.h>
#include <string.h>

ETHASH_PARAMS g_ethash_params;

void ethash_params_load(dictionary *ini)
{
	memset(&g_ethash_params, 0, sizeof(g_ethash_params));

	// Etchash (ECIP-1099) uses 60000 blocks per epoch after the fork, classic
	// Ethash uses 30000. The seed hash always follows the original 30000
	// numbering (the fork epoch itself is derived with the old divisor).
	const bool etchash = !strcmp(g_stratum_algo, "etchash");
	const int default_epoch_length = etchash ? 60000 : 30000;
	const int default_fork_block = etchash ? 11700000 : 0; // ETC mainnet

	g_ethash_params.epoch_length = iniparser_getint(ini, "ETHASH:epoch_length", default_epoch_length);
	g_ethash_params.seed_epoch_length = iniparser_getint(ini, "ETHASH:seed_epoch_length", 30000);
	g_ethash_params.etchash_fork_block = iniparser_getint(ini, "ETHASH:etchash_fork_block", default_fork_block);
	g_ethash_params.etchash_fork_epoch = iniparser_getint(ini, "ETHASH:etchash_fork_epoch", 0);
	g_ethash_params.epoch_offset = iniparser_getint(ini, "ETHASH:epoch_offset", 0);
	g_ethash_params.chain_id = iniparser_getint(ini, "ETHASH:chain_id", etchash ? 61 : 0);
	g_ethash_params.dag_init_size = (unsigned int) iniparser_getint(
		ini, "ETHASH:dag_init_size", 1 << 30);

	if (g_ethash_params.seed_epoch_length <= 0)
		g_ethash_params.seed_epoch_length = 30000;
	if (g_ethash_params.epoch_length <= 0)
		g_ethash_params.epoch_length = g_ethash_params.seed_epoch_length;
	if (g_ethash_params.dag_init_size < (1U << 20))
		g_ethash_params.dag_init_size = 1U << 30;
}

int ethash_epoch_for_height(int height)
{
	if (height < 0)
		height = 0;

	int epoch;
	const int seed_length = g_ethash_params.seed_epoch_length > 0 ?
		g_ethash_params.seed_epoch_length : 30000;
	const int fork_block = g_ethash_params.etchash_fork_block;

	if (fork_block > 0 && height >= fork_block)
	{
		int fork_epoch = g_ethash_params.etchash_fork_epoch;
		if (fork_epoch <= 0)
			fork_epoch = fork_block / seed_length;

		const int epoch_length = g_ethash_params.epoch_length > 0 ?
			g_ethash_params.epoch_length : seed_length;
		epoch = fork_epoch + (height - fork_block) / epoch_length;
	}
	else
	{
		epoch = height / seed_length;
	}

	return epoch + g_ethash_params.epoch_offset;
}

void ethash_seed_for_epoch(int epoch, char *hex66)
{
	const ethash::hash256 seed = ethash::calculate_epoch_seed(epoch);
	const std::string hex = to_hex(seed);
	snprintf(hex66, 67, "0x%s", hex.c_str());
}

// Parse a 0x-prefixed (or bare) 32-byte big endian hash.
bool ethash_parse_hex_target(const char *hex, uint8_t be[32])
{
	if (!hex)
		return false;
	if (!strncmp(hex, "0x", 2) || !strncmp(hex, "0X", 2))
		hex += 2;
	if (strlen(hex) != 64 || !ishexa((char *) hex, 64))
		return false;

	for (int i = 0; i < 32; i++)
	{
		char pair[3] = { hex[i * 2], hex[i * 2 + 1], 0 };
		be[i] = (uint8_t) strtoul(pair, NULL, 16);
	}
	return true;
}

// floor((2^256-1) / difficulty), exact even for fractional difficulty.
//
// Ethproxy miners such as t-rex convert the target back with
// (2^256-1) / target, so using the same dividend here makes the pool and the
// miner agree. The double is decomposed into mantissa * 2^exponent and divided
// with 128-bit long division; rounding it to an integer first would make
// d=8.5 produce a different target than the miner's.
static void ethash_shift_max_target(uint8_t digits[40], int shift, int *length)
{
	memset(digits, 0, 40);

	if (shift >= 0)
	{
		// (2^256-1) >> shift
		if (shift >= 256)
		{
			*length = 0;
			return;
		}

		const int byte_shift = shift / 8;
		const int bit_shift = shift % 8;
		for (int i = 0; i < 32; i++)
		{
			unsigned int word = 0;
			if (i + byte_shift < 32)
				word |= 0xffu;
			if (i + byte_shift + 1 < 32)
				word |= 0xffu << 8;
			digits[i] = (uint8_t) (word >> bit_shift);
		}
		*length = 32;
	}
	else
	{
		// (2^256-1) << -shift
		const int byte_shift = (-shift) / 8;
		const int bit_shift = (-shift) % 8;
		const unsigned int word = 0xffu << bit_shift;

		for (int i = 31; i >= 0; i--)
		{
			digits[i + byte_shift] |= (uint8_t) word;
			digits[i + byte_shift + 1] |= (uint8_t) (word >> 8);
		}
		*length = 32 + byte_shift + 1;
	}
}

void ethash_diff_to_target(double diff, uint8_t be[32])
{
	memset(be, 0xff, 32);
	if (!(diff > 0) || !isfinite(diff) || diff <= 1.0)
		return;

	int exponent = 0;
	const double fraction = frexp(diff, &exponent);
	uint64_t mantissa = (uint64_t) ldexp(fraction, 53);
	int shift = exponent - 53;

	// Remove powers of two so the divisor is an odd 53-bit integer and any
	// remaining power of two can be applied as a byte/bit shift to 2^256-1.
	while (!(mantissa & 1))
	{
		mantissa >>= 1;
		shift++;
	}

	uint8_t dividend[40];
	int length = 0;
	ethash_shift_max_target(dividend, shift, &length);
	if (!length)
	{
		memset(be, 0, 32); // difficulty too large for a 256-bit target
		return;
	}

	uint8_t quotient[40] = { 0 };
	__uint128_t remainder = 0;
	for (int i = length - 1; i >= 0; i--)
	{
		remainder = (remainder << 8) | dividend[i];
		quotient[i] = (uint8_t) (remainder / mantissa);
		remainder %= mantissa;
	}

	for (int i = 32; i < 40; i++)
	{
		if (quotient[i])
		{
			memset(be, 0xff, 32); // should only happen for difficulty <= 1
			return;
		}
	}

	for (int i = 0; i < 32; i++)
		be[i] = quotient[31 - i];
}

void ethash_target_to_diff(const uint8_t be[32], double *diff)
{
	long double value = 0;
	for (int i = 0; i < 32; i++)
	{
		value *= 256;
		value += be[i];
	}

	// Same dividend as ethash_diff_to_target() and ethproxy miners.
	long double max_target = 0;
	for (int i = 0; i < 32; i++)
		max_target = max_target * 256 + 255;

	if (value <= 0)
		*diff = 0;
	else
		*diff = (double) (max_target / value);
}
