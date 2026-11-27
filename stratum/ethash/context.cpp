// Light (cache-based) epoch context cache for the Ethereum-style engines.
// Distributed under the MIT software license.

#include "stratum.h"
#include "context.h"
#include "engine.h"

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

	const ETHASH_ENGINE *engine = ethash_engine();
	void *raw = engine->create_context(epoch);
	if (!raw)
	{
		debuglog("%s: unable to create light cache for epoch %d\n", engine->name, epoch);
		return ethash_context_ptr();
	}

	// Bind the deleter that belongs to the engine which created this context.
	ethash_context_ptr context(raw, [engine](void *p) { engine->destroy_context(p); });

	CommonLock(&g_ethash_context_mutex);
	g_ethash_contexts[epoch] = context;

	// Keep a few epochs: miners may still submit late shares for a previous
	// job, and pools can host more than one chain on the same algo port.
	// Existing shared_ptr holders keep older contexts alive until done.
	while (g_ethash_contexts.size() > 4)
		g_ethash_contexts.erase(g_ethash_contexts.begin());

	CommonUnlock(&g_ethash_context_mutex);

	return context;
}
