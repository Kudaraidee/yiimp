
#include "stratum.h"

double client_normalize_difficulty(double difficulty, YAAMP_CLIENT *client)
{
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

	if(client->difficulty_fixed)
		return;

	// More aggressive difficulty adjustment for high hashrate (ASICs)
	// Target: ~10 shares per minute (YAAMP_SHAREPERSEC * 60)
	if(client->shares_per_minute > 100)
		client_change_difficulty(client, client->difficulty_actual*4);

	else if(client->shares_per_minute > 75)
		client_change_difficulty(client, client->difficulty_actual*3.5);

	else if(client->shares_per_minute > 50)
		client_change_difficulty(client, client->difficulty_actual*3);

	else if(client->shares_per_minute > 30)
		client_change_difficulty(client, client->difficulty_actual*2);

	else if(client->shares_per_minute > 20)
		client_change_difficulty(client, client->difficulty_actual*1.5);

	// Slower decrease for ASICs (they need time to adjust)
	else if(client->shares_per_minute < 3)
		client_change_difficulty(client, client->difficulty_actual/2);
	
	else if(client->shares_per_minute < 5)
		client_change_difficulty(client, client->difficulty_actual/1.5);
}

int client_send_difficulty(YAAMP_CLIENT *client, double difficulty)
{
//	debuglog("%s diff %f\n", client->sock->ip, difficulty);
	client->shares_per_minute = YAAMP_SHAREPERSEC;

	bool is_equihash = (strstr(g_current_algo->name, "equihash") == g_current_algo->name);
	if (is_kawpow || is_firopow || is_phihash)
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

void client_initialize_difficulty(YAAMP_CLIENT *client)
{
	char *p = strstr(client->password, "d=");
	char *p2 = strstr(client->password, "decred=");
	if(!p || p2) return;

	if (is_kawpow || is_firopow || is_phihash)
	{
		client->difficulty_actual = g_stratum_difficulty;
		diff_to_target(client->share_target, client->difficulty_actual);
	}
	else {
		double diff = client_normalize_difficulty(atof(p+2), client);
		uint64_t user_target = diff_to_target(diff);

	//	debuglog("%016llx target\n", user_target);
		if(user_target >= YAAMP_MINDIFF && user_target <= YAAMP_MAXDIFF)
		{
			client->difficulty_actual = diff;
			client->difficulty_fixed = true;
		}
	}

}
