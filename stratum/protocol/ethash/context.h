// Light (cache-based) epoch context cache for the Ethereum-style engines.
// Distributed under the MIT software license.

#ifndef YAAMP_ETHASH_CONTEXT_H
#define YAAMP_ETHASH_CONTEXT_H

#include <memory>
#include <algos/progpow/include/ethash/ethash.hpp>

// Opaque engine-specific epoch context (ethash_epoch_context or
// xhash_epoch_context). The shared deleter keeps the right destroy function
// alive even if an entry is evicted while a submit still holds it.
typedef std::shared_ptr<void> ethash_context_ptr;

// Returns a shared light epoch context. Building the cache can take a while,
// so it is never done while the cache mutex is held.
ethash_context_ptr ethash_get_context(int epoch);

#endif // YAAMP_ETHASH_CONTEXT_H
