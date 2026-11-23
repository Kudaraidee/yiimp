
#include "stratum.h"

// Ethash/Etchash vardiff targets about one share per minute per small GPU.
// The generic Bitcoin-style vardiff aims for 10 shares/min, which floods the
// pool with tiny shares on Ethash and makes share accounting noisy. Both the
// window and the target rate are configurable per pool.
static long long eth_vardiff_window_ms()
{
	return (long long) g_stratum_eth_vardiff_window * 1000LL;
}

// Ethash/Etchash and the Progpow family all use a full 256-bit target via
// mining.set_target (or EthereumStratum), so they share the same GPU-style
// vardiff: fractional difficulties, fixed window, ~1 share/min.
static bool is_gpu_vardiff()
{
	return is_ethash || is_etchash || is_kawpow || is_firopow || is_phihash || is_meowpow || is_evrprogpow;
}

// HomeScrypt's real network difficulty is ~3e-4, far below the 0.002 step of
// the legacy Bitcoin-style rounding: floor(diff*500)/500 collapses every such
// value to zero and vardiff stops working. Any algo configured with a sub-0.002
// min diff hits the same bug, so keep full precision for all of them.
static bool is_low_diff_vardiff()
{
	if (is_homescrypt) return true;
	return g_stratum_min_diff > 0 && g_stratum_min_diff < 0.002;
}

bool client_uses_precise_difficulty()
{
	return is_gpu_vardiff() || is_low_diff_vardiff();
}

bool client_low_difficulty_algo()
{
	return is_low_diff_vardiff();
}

double client_normalize_difficulty(double difficulty, YAAMP_CLIENT *client)
{
	if (client_uses_precise_difficulty())
	{
		// These miners accept fractional difficulty and derive their target
		// from the exact value. Keep it instead of the even-integer rounding
		// used by the Bitcoin-style algorithms (which also collapses 1<x<2
		// to zero and makes vardiff stall).
		if (difficulty < g_stratum_min_diff) difficulty = g_stratum_min_diff;
		if (difficulty > g_stratum_max_diff) difficulty = g_stratum_max_diff;
		return difficulty;
	}

	if (client->is_nicehash) {
		if(difficulty < g_stratum_nicehash_min_diff) difficulty = g_stratum_nicehash_min_diff;
		else if(difficulty < 1) difficulty = floor(difficulty*1000/2)/1000*2;
		else if(difficulty > 1) difficulty = floor(difficulty/2)*2;
		if(difficulty > g_stratum_nicehash_max_diff) difficulty = g_stratum_nicehash_max_diff;
	}
	else {
		if(difficulty < g_stratum_min_diff) difficulty = g_stratum_min_diff;
		else if(difficulty < 1) difficulty = floor(difficulty*1000/2)/1000*2;
		else if(difficulty > 1) difficulty = floor(difficulty/2)*2;
		if(difficulty > g_stratum_max_diff) difficulty = g_stratum_max_diff;
	}
	return difficulty;
}

void client_record_difficulty(YAAMP_CLIENT *client)
{
	if(client->difficulty_remote)
	{
		client->last_submit_time = current_timestamp();
		return;
	}

	int e = current_timestamp() - client->last_submit_time;
	if(e < 500) e = 500;
	int p = 5;

	client->shares_per_minute = (client->shares_per_minute * (100 - p) + 60*1000*p/e) / 100;
	client->last_submit_time = current_timestamp();
	client->vardiff_shares++;

//	debuglog("client->shares_per_minute %f\n", client->shares_per_minute);
}

void client_change_difficulty(YAAMP_CLIENT *client, double difficulty)
{
	if(difficulty <= 0) return;

	difficulty = client_normalize_difficulty(difficulty, client);
	if(difficulty <= 0) return;

//	debuglog("change diff to %f %f\n", difficulty, client->difficulty_actual);
	if(difficulty == client->difficulty_actual) return;

	client->difficulty_actual = difficulty;
	client_send_difficulty(client, difficulty);
}

void client_adjust_difficulty(YAAMP_CLIENT *client)
{
	if(client->difficulty_remote) {
		client_change_difficulty(client, client->difficulty_remote);
		return;
	}

	if (client_uses_precise_difficulty())
	{
		// Fixed-window rate is much more stable than the generic EMA here:
		// the EMA is only refreshed when a share arrives, so a slow GPU
		// either never triggered a change or took ~30 minutes to settle.
		long long now = current_timestamp();
		if (!client->vardiff_window_start) client->vardiff_window_start = now;
		long long elapsed = now - client->vardiff_window_start;
		if (elapsed < eth_vardiff_window_ms()) return;

		double target_rate = g_stratum_eth_vardiff_target;
		double rate = client->vardiff_shares * 60000.0 / (double) elapsed;
		double new_diff = client->difficulty_actual;

		if (client->difficulty_fixed)
		{
			// Keep flood protection for pinned difficulty, but do not
			// otherwise vardiff a miner that explicitly asked for d=X.
			if (rate > 100) new_diff *= 4.0;
			else {
				client->vardiff_shares = 0;
				client->vardiff_window_start = now;
				return;
			}
		}
		else if (rate >= target_rate * 4.0)
			new_diff *= 2.0;
		else if (rate >= target_rate * 2.0)
			new_diff *= 1.5;
		else if (rate <= target_rate * 0.25)
			new_diff *= 0.5;
		else if (rate <= target_rate * 0.5)
			new_diff *= 0.75;

		client->vardiff_shares = 0;
		client->vardiff_window_start = now;

		if (new_diff != client->difficulty_actual)
			client_change_difficulty(client, new_diff);
		return;
	}

	if(client->shares_per_minute > 100)
		client_change_difficulty(client, client->difficulty_actual*4);

	else if(client->difficulty_fixed)
		return;

	else if(client->shares_per_minute > 75)
		client_change_difficulty(client, client->difficulty_actual*3.5);

	else if(client->shares_per_minute > 50)
		client_change_difficulty(client, client->difficulty_actual*3);

	else if(client->shares_per_minute > 25)
		client_change_difficulty(client, client->difficulty_actual*2);

	else if(client->shares_per_minute > 20)
		client_change_difficulty(client, client->difficulty_actual*1.5);

	else if(client->shares_per_minute <  5)
		client_change_difficulty(client, client->difficulty_actual/2);
}

int client_send_difficulty(YAAMP_CLIENT *client, double difficulty)
{
//	debuglog("%s diff %f\n", client->sock->ip, difficulty);
	if (client_uses_precise_difficulty())
	{
		client->shares_per_minute = g_stratum_eth_vardiff_target;
		client->vardiff_shares = 0;
		client->vardiff_window_start = current_timestamp();
	}
	else
		client->shares_per_minute = YAAMP_SHAREPERSEC;

	bool is_equihash = (strstr(g_current_algo->name, "equihash") == g_current_algo->name);
	if (is_ethash || is_etchash)
	{
		// Ethereum Stratum uses the ethash difficulty scale: target is
		// 2^224 / difficulty (i.e. 2^256 / (difficulty * 2^32)), matching
		// Rigel and miningcore. Not the Bitcoin-style compact target.
		uint8_t target_be[32];
		ethash_diff_to_target(difficulty, target_be);
		client->share_target = ethash_be_to_uint256(target_be);
		client->next_target = client->share_target;

		if (client->is_ethproxy)
		{
			// ethproxy miners read the target from eth_getWork / work pushes.
			// mining.set_difficulty would only make T-Rex log "Unregistered
			// stratum method" and report diff 0.00. The initial job (and every
			// later job broadcast) already carries the updated target.
			return 0;
		}

		// Rigel's SetDifficultyParams deserializes difficulty as a JSON
		// number. A quoted string does not match ServerMessage and leaves
		// the miner waiting for a job until it reconnects.
		client_call(client, "mining.set_difficulty", "[%.8g]", difficulty);
	}
	else if (is_kawpow || is_firopow || is_phihash || is_meowpow || is_evrprogpow)
	{
		uint256 share_target;
		diff_to_target(share_target, difficulty);
	
		client->share_target = share_target;
		client_call(client, "mining.set_target", "[\"%s\"]", client->share_target.ToString().c_str());
		client->next_target = share_target;
	
	}
	else if(is_equihash) {
		uint32_t user_target[32];
		diff_to_target_equi(user_target, difficulty);
		char user_target_hex[128]; memset(user_target_hex,0,128);
		char user_target_hex_reversed[128]; memset(user_target_hex_reversed,0,128);
		hexlify(user_target_hex, (uchar*)user_target, 32);
		string_be(user_target_hex, user_target_hex_reversed);

		client_call(client, "mining.set_target", "[\"%s\"]", user_target_hex_reversed);
	}
	else {
		double wire_difficulty = homescrypt_wire_difficulty(client, difficulty);
		if(wire_difficulty >= 1)
			client_call(client, "mining.set_difficulty", "[%.0f]", wire_difficulty);
		else
			client_call(client, "mining.set_difficulty", "[%0.8f]", wire_difficulty);
	}
	return 0;
}

// Match a comma-separated password token such as "d=2" or "sd=0.1".
// A plain strstr("d=") also matches "sd=", "xd=", "decred=", etc.; keeping
// the tokens explicit lets d= and sd= have different meanings.
static const char *client_password_param(const char *password, const char *key, size_t keylen)
{
	if (!password) return NULL;

	const char *p = password;
	while (*p)
	{
		while (*p == ' ' || *p == ',') p++;
		if (!*p) break;

		if (!strncmp(p, key, keylen) && p[keylen] == '=')
			return p + keylen + 1;

		p = strchr(p, ',');
		if (!p) break;
	}
	return NULL;
}

void client_initialize_difficulty(YAAMP_CLIENT *client)
{
	// Keep the old Decred behavior: an explicit decred= token disables the
	// generic difficulty overrides. The exact token parser no longer needs
	// this to stop "sd=" from being mistaken for "d=", but other password
	// formats can still contain both.
	if (client_password_param(client->password, "decred", 6)) return;

	// d= pins the difficulty for the whole session. sd= only seeds the initial
	// difficulty; vardiff is still free to adjust it later. d= wins if both
	// are present.
	const char *value = client_password_param(client->password, "d", 1);
	bool difficulty_fixed = true;
	if (!value)
	{
		value = client_password_param(client->password, "sd", 2);
		difficulty_fixed = false;
	}
	if (!value) return;

	if (client_uses_precise_difficulty())
	{
		// Fractional and sub-0.002 difficulties must be used as-is. The
		// legacy Bitcoin-style rounding clamped d=2 to 8 with difficulty=16
		// and rounded every low-diff value down to zero.
		double diff = atof(value);
		if (diff > 0 && isfinite(diff))
		{
			client->difficulty_actual = diff;
			if (is_gpu_vardiff())
			{
				if (is_ethash || is_etchash)
				{
					uint8_t be[32];
					ethash_diff_to_target(diff, be);
					client->share_target = ethash_be_to_uint256(be);
				}
				else
					diff_to_target(client->share_target, diff);
			}

			client->difficulty_fixed = difficulty_fixed;
		}
	}
	else {
		double diff = client_normalize_difficulty(atof(value), client);
		uint64_t user_target = diff_to_target(diff);

	//	debuglog("%016llx target\n", user_target);
		if(user_target >= YAAMP_MINDIFF && user_target <= YAAMP_MAXDIFF)
		{
			client->difficulty_actual = diff;
			client->difficulty_fixed = difficulty_fixed;
		}
	}

}
