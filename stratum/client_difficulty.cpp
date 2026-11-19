
#include "stratum.h"

double client_normalize_difficulty(double difficulty, YAAMP_CLIENT *client)
{
	if (is_ethash || is_etchash)
	{
		// EthereumStratum/ethproxy miners accept fractional difficulty and
		// derive their target from the exact value. Keep it instead of the
		// even-integer rounding used by the Bitcoin-style algorithms.
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
	else if (is_kawpow || is_firopow || is_phihash || is_meowpow)
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
		if(difficulty >= 1)
			client_call(client, "mining.set_difficulty", "[%.0f]", difficulty);
		else
			client_call(client, "mining.set_difficulty", "[%0.8f]", difficulty);
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

	if (is_kawpow || is_firopow || is_phihash || is_meowpow)
	{
		client->difficulty_actual = g_stratum_difficulty;
		diff_to_target(client->share_target, client->difficulty_actual);
	}
	else if (is_ethash || is_etchash)
	{
		// Ethash/ethproxy miners use the full 256-bit target. An explicit d=
		// or sd= must be used as-is: client_normalize_difficulty() clamps to
		// the Bitcoin-style g_stratum_min_diff (difficulty/2), which silently
		// turned d=2 into 8 on a pool configured with difficulty = 16.
		double diff = atof(value);
		if (diff > 0 && isfinite(diff))
		{
			uint8_t be[32];
			ethash_diff_to_target(diff, be);
			client->difficulty_actual = diff;
			client->share_target = ethash_be_to_uint256(be);
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
