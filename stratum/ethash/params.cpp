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

// Ethash difficulty is the number of hashes per share multiplied by 2^32:
//
//     target  = 2^224 / difficulty
//     diff    = 2^224 / target
//
// This is the scale used by EthereumStratum miners (Rigel displays d=8 as
// "34.36G (8.00)") and by miningcore. It is also what ethproxy miners expect
// in the third field of eth_getWork. Using 2^256 / diff here instead made
// t-rex mine at 8 hashes per share while Rigel mined at 8 * 2^32, and made
// the network difficulty / hashrate display off by 2^32.
//
// The double is decomposed into mantissa * 2^shift so the conversion stays
// exact for fractional difficulty (d=8.5) and needs only 128-bit long
// division. Rounding difficulty to an integer first would change the target.
static void ethash_set_dividend(int bit, uint8_t digits[40])
{
	memset(digits, 0, 40);
	digits[bit / 8] = (uint8_t) (1u << (bit % 8));
}

void ethash_diff_to_target(double diff, uint8_t be[32])
{
	memset(be, 0, 32);
	if (!(diff > 0) || !isfinite(diff))
		return;

	int exponent = 0;
	const double fraction = frexp(diff, &exponent);
	uint64_t mantissa = (uint64_t) ldexp(fraction, 53);
	int shift = exponent - 53;

	// Remove powers of two so the divisor is an odd 53-bit integer and the
	// remaining power of two can be folded into the 2^224 dividend.
	while (!(mantissa & 1))
	{
		mantissa >>= 1;
		shift++;
	}

	const int bit = 224 - shift;
	if (bit < 0)
		return; // difficulty too large for a 256-bit target
	if (bit >= 40 * 8)
	{
		memset(be, 0xff, 32); // difficulty too small: every hash qualifies
		return;
	}

	uint8_t dividend[40];
	ethash_set_dividend(bit, dividend);

	uint8_t quotient[40] = { 0 };
	__uint128_t remainder = 0;
	for (int i = bit / 8; i >= 0; i--)
	{
		remainder = (remainder << 8) | dividend[i];
		quotient[i] = (uint8_t) (remainder / mantissa);
		remainder %= mantissa;
	}

	for (int i = 32; i < 40; i++)
	{
		if (quotient[i])
		{
			memset(be, 0xff, 32);
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

	// 2^224, the dividend used by ethash_diff_to_target().
	const long double max_target = ldexpl(1.0L, 224);

	if (value <= 0)
		*diff = 0;
	else
		*diff = (double) (max_target / value);
}
