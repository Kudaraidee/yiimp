// Chain-specific backend implementation for the Ethereum-style stratum.
// Distributed under the MIT software license.

#include "stratum.h"
#include "engine.h"
#include "params.h"

#include <algos/progpow/include/ethash/ethash.hpp>
#include <xhash/xhash.h>

#include <string.h>

// ---------------------------------------------------------------------------
// Classic Ethash / Etchash
// ---------------------------------------------------------------------------

static int ethash_engine_epoch_from_work(int height, const uint8_t seed[32])
{
	ethash::hash256 seed_hash;
	memcpy(seed_hash.bytes, seed, 32);
	const int epoch = ethash::find_epoch_number(seed_hash);
	if (epoch >= 0)
		return epoch;

	// A seed we cannot reverse (custom chain, or a transient bad value) still
	// has to produce a job. The height-derived epoch is the same fallback the
	// module used before the engine split.
	return ethash_epoch_for_height(height);
}

static void ethash_engine_seed_for_epoch(int epoch, uint8_t out[32])
{
	const ethash::hash256 seed = ethash::calculate_epoch_seed(epoch);
	memcpy(out, seed.bytes, 32);
}

static void *ethash_context_create(int epoch)
{
	return ethash_create_epoch_context(epoch);
}

static void ethash_context_destroy(void *context)
{
	ethash_destroy_epoch_context(static_cast<ethash_epoch_context *>(context));
}

static void ethash_hash(void *context, const uint8_t header[32], uint64_t nonce,
	uint8_t final_out[32], uint8_t mix_out[32])
{
	ethash::hash256 header_hash;
	memcpy(header_hash.bytes, header, 32);

	const ethash::result result = ethash::hash(
		*static_cast<ethash_epoch_context *>(context), header_hash, nonce);
	memcpy(final_out, result.final_hash.bytes, 32);
	memcpy(mix_out, result.mix_hash.bytes, 32);
}

static const ETHASH_ENGINE g_ethash_engine =
{
	"ethash",
	"eth_getWork",
	"eth_submitWork",
	false,
	false,
	ethash_engine_epoch_from_work,
	ethash_engine_seed_for_epoch,
	ethash_context_create,
	ethash_context_destroy,
	ethash_hash,
};

// ---------------------------------------------------------------------------
// Parallax XHash
//
// The vendored library fixes the consensus parameters (epoch 720, PARALLAX
// seed magic, revision 23). There is no reverse "seed -> epoch" API, but
// xhash_getWork carries the mined block number, which is authoritative.
// ---------------------------------------------------------------------------

static int xhash_epoch_from_work(int height, const uint8_t seed[32])
{
	(void) seed;
	if (height < 0) height = 0;
	return height / XHASH_EPOCH_LENGTH;
}

static void xhash_seed_for_epoch(int epoch, uint8_t out[32])
{
	const xhash_hash256 seed = xhash_calculate_epoch_seed(epoch);
	memcpy(out, seed.bytes, 32);
}

static void *xhash_context_create(int epoch)
{
	return xhash_create_epoch_context(epoch);
}

static void xhash_context_destroy(void *context)
{
	xhash_destroy_epoch_context(static_cast<xhash_epoch_context *>(context));
}

static void xhash_hash(void *context, const uint8_t header[32], uint64_t nonce,
	uint8_t final_out[32], uint8_t mix_out[32])
{
	xhash_hash256 header_hash;
	memcpy(header_hash.bytes, header, 32);

	const xhash_result result = xhash_hash(
		static_cast<const xhash_epoch_context *>(context), &header_hash, nonce);
	memcpy(final_out, result.final_hash.bytes, 32);
	memcpy(mix_out, result.mix_hash.bytes, 32);
}

static const ETHASH_ENGINE g_xhash_engine =
{
	"xhash",
	"xhash_getWork",
	"xhash_submitWork",
	true,
	true,
	xhash_epoch_from_work,
	xhash_seed_for_epoch,
	xhash_context_create,
	xhash_context_destroy,
	xhash_hash,
};

// ---------------------------------------------------------------------------

static const ETHASH_ENGINE *g_engine = &g_ethash_engine;

const ETHASH_ENGINE *ethash_engine(void)
{
	return g_engine;
}

void ethash_engine_select(const char *algo)
{
	g_engine = (algo && !strcmp(algo, "xhash")) ? &g_xhash_engine : &g_ethash_engine;
}
