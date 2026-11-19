// Light (cache-based) Ethash epoch context cache.
// Distributed under the MIT software license.

#include "stratum.h"
#include "context.h"

#include <map>

static pthread_mutex_t g_ethash_context_mutex = PTHREAD_MUTEX_INITIALIZER;
static std::map<int, ethash_context_ptr> g_ethash_contexts;

ethash_context_ptr ethash_get_context(int epoch)
{
	{
		CommonLock(&g_ethash_context_mutex);
		std::map<int, ethash_context_ptr>::iterator it = g_ethash_contexts.find(epoch);
		if (it != g_ethash_contexts.end())
		{
			ethash_context_ptr context = it->second;
			CommonUnlock(&g_ethash_context_mutex);
			return context;
		}
		CommonUnlock(&g_ethash_context_mutex);
	}

	// Building the light cache can take a while; never do it under the lock.
	ethash_epoch_context *raw = ethash_create_epoch_context(epoch);
	if (!raw)
	{
		debuglog("ethash: unable to create light cache for epoch %d\n", epoch);
		return ethash_context_ptr();
	}

	ethash_context_ptr context(raw, ethash_destroy_epoch_context);

	CommonLock(&g_ethash_context_mutex);
	g_ethash_contexts[epoch] = context;

	// Keep only the two most recent epochs. Existing shared_ptr holders keep
	// older contexts alive until they are done verifying.
	while (g_ethash_contexts.size() > 2)
		g_ethash_contexts.erase(g_ethash_contexts.begin());

	CommonUnlock(&g_ethash_context_mutex);

	return context;
}
