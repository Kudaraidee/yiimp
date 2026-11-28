// Chain-specific backend for the Ethereum-style work protocol.
// Distributed under the MIT software license.
//
// ethash, etchash and xhash share the same wire protocol (EthereumStratum,
// ethproxy) and the same work/template/submit handling. The parts that differ
// are isolated here: RPC method names, epoch derivation and the PoW core.

#ifndef YAAMP_ETHASH_ENGINE_H
#define YAAMP_ETHASH_ENGINE_H

#include <stdint.h>

struct ETHASH_ENGINE
{
	const char *name;

	// Mining RPC. Block/height queries stay in the eth_ namespace on both
	// classic Ethereum nodes and Parallax.
	const char *getwork_method;
	const char *submitwork_method;

	// Does the work array carry the mined block height as its 4th element?
	// Classic eth_getWork returns three fields; xhash_getWork returns four.
	bool work_includes_height;

	// If the advertised seed does not match the seed derived from the work
	// epoch, reject the work instead of sending jobs miners cannot solve.
	// Classic ethash keeps the old log-and-continue behaviour so custom epoch
	// chains with a non-reversible seed still work.
	bool require_exact_seed;

	// Epoch of a work package. The seed is the 32-byte big-endian seed hash
	// advertised by the node; it is authoritative when it can identify an
	// epoch (classic Ethash). XHash has no reverse seed lookup, so its epoch
	// comes from the block height and the seed is verified afterwards.
	int (*epoch_from_work)(int height, const uint8_t seed[32]);

	// Seed hash for an epoch, in the big-endian byte order used on the wire.
	void (*seed_for_epoch)(int epoch, uint8_t out[32]);

	void *(*create_context)(int epoch);
	void (*destroy_context)(void *context);

	// header is the 32-byte big-endian seal hash. final_out and mix_out are
	// the big-endian hashes miners compare and submit.
	void (*hash)(void *context, const uint8_t header[32], uint64_t nonce,
		uint8_t final_out[32], uint8_t mix_out[32]);
};

const ETHASH_ENGINE *ethash_engine(void);
void ethash_engine_select(const char *algo);

#endif // YAAMP_ETHASH_ENGINE_H
