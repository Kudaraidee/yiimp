#ifndef YAAMP_RANDOMSCASH_H
#define YAAMP_RANDOMSCASH_H

#include <stddef.h>
#include <stdint.h>

// Verify/copy the Scash RandomX work for one 112-byte block header.
//
// The caller supplies the serialized header with the trailing 32-byte
// hashRandomX field zeroed. randomscash_calculate() derives the epoch key from
// ntime and rx_epoch_duration, computes R = RandomX(header) and then
// CM = Blake2b(header || R). The commitment is returned in cm; R is returned
// in rx_hash so the pool can rebuild the block submitted to the node.
bool randomscash_calculate(const unsigned char *header112, uint32_t ntime,
	uint64_t rx_epoch_duration, unsigned char rx_hash[32], unsigned char cm[32]);

#endif
