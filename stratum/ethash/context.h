// Light (cache-based) Ethash epoch context cache.
// Distributed under the MIT software license.

#ifndef YAAMP_ETHASH_CONTEXT_H
#define YAAMP_ETHASH_CONTEXT_H

#include <memory>
#include <kawpow/include/ethash/ethash.hpp>

typedef std::shared_ptr<ethash::epoch_context> ethash_context_ptr;

// Returns a shared light epoch context. The context stays alive while the
// caller holds the returned pointer, even if it is evicted from the cache.
ethash_context_ptr ethash_get_context(int epoch);

#endif // YAAMP_ETHASH_CONTEXT_H
