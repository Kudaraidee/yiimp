#include "stratum.h"

#include <pthread.h>
#include <stdio.h>
#include <string.h>

#include <randomx.h>

// One light VM is enough for share verification: RandomX key material changes
// only once per epoch, and using the cache-backed VM avoids keeping the full
// multi-gigabyte dataset resident just to validate pool shares.
static pthread_mutex_t g_rx_mutex = PTHREAD_MUTEX_INITIALIZER;
static randomx_cache *g_rx_cache = NULL;
static randomx_vm *g_rx_vm = NULL;
static uint32_t g_rx_epoch = 0;
static uint64_t g_rx_epoch_duration = 0;
static randomx_flags g_rx_flags = RANDOMX_FLAG_DEFAULT;
static bool g_rx_flags_set = false;

static randomx_flags randomscash_flags()
{
	if (!g_rx_flags_set)
	{
		// Share verification uses the cache-backed light VM: allocating the
		// full dataset on every pool host would waste several GB for no gain.
		const randomx_flags detected = randomx_get_flags();
		g_rx_flags = (randomx_flags)((int)detected & ~(int)RANDOMX_FLAG_FULL_MEM);
		g_rx_flags_set = true;
	}
	return g_rx_flags;
}

static void randomscash_release_vm()
{
	if (g_rx_vm)
	{
		randomx_destroy_vm(g_rx_vm);
		g_rx_vm = NULL;
	}
	if (g_rx_cache)
	{
		randomx_release_cache(g_rx_cache);
		g_rx_cache = NULL;
	}
}

static bool randomscash_prepare(uint32_t epoch, uint64_t epoch_duration)
{
	if (g_rx_vm && epoch == g_rx_epoch && epoch_duration == g_rx_epoch_duration)
		return true;

	randomscash_release_vm();

	char seed_string[128];
	snprintf(seed_string, sizeof(seed_string), "Scash/RandomX/Epoch/%u", epoch);

	unsigned char key_seed[32];
	unsigned char epoch_key[32];
	// Use the stratum's bundled SHA256 to avoid ABI conflicts with OpenSSL's
	// SHA256 declarations pulled in by the generic algo headers.
	sha256_hash(seed_string, (char *)key_seed, strlen(seed_string));
	sha256_hash((const char *)key_seed, (char *)epoch_key, sizeof(key_seed));

	const randomx_flags flags = randomscash_flags();
	g_rx_cache = randomx_alloc_cache(flags);
	if (!g_rx_cache && flags != RANDOMX_FLAG_DEFAULT)
		g_rx_cache = randomx_alloc_cache(RANDOMX_FLAG_DEFAULT);
	if (!g_rx_cache)
	{
		debuglog("randomscash: unable to allocate RandomX cache\n");
		return false;
	}

	randomx_init_cache(g_rx_cache, epoch_key, sizeof(epoch_key));

	g_rx_vm = randomx_create_vm(flags, g_rx_cache, NULL);
	if (!g_rx_vm && flags != RANDOMX_FLAG_DEFAULT)
		g_rx_vm = randomx_create_vm(RANDOMX_FLAG_DEFAULT, g_rx_cache, NULL);
	if (!g_rx_vm)
	{
		debuglog("randomscash: unable to create RandomX VM\n");
		randomscash_release_vm();
		return false;
	}

	g_rx_epoch = epoch;
	g_rx_epoch_duration = epoch_duration;
	return true;
}

bool randomscash_calculate(const unsigned char *header112, uint32_t ntime,
	uint64_t rx_epoch_duration, unsigned char rx_hash[32], unsigned char cm[32])
{
	if (!header112 || !rx_hash || !cm || !rx_epoch_duration)
		return false;

	const uint32_t epoch = (uint32_t)(ntime / rx_epoch_duration);

	unsigned char input[112];
	memcpy(input, header112, sizeof(input));
	// Scash zeroes hashRandomX before both R and the commitment are derived.
	memset(input + 80, 0, 32);

	pthread_mutex_lock(&g_rx_mutex);
	bool ok = randomscash_prepare(epoch, rx_epoch_duration);
	if (ok)
	{
		randomx_calculate_hash(g_rx_vm, input, sizeof(input), rx_hash);
		randomx_calculate_commitment(input, sizeof(input), rx_hash, cm);
	}
	pthread_mutex_unlock(&g_rx_mutex);

	return ok;
}
