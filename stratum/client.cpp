
#include "stratum.h"
#include <strings.h>

bool client_suggest_difficulty(YAAMP_CLIENT *client, json_value *json_params)
{
	// neb-pool/1 sends this as a no-op; vardiff is driven by the node's
	// share_target, not by a miner-supplied difficulty.
	if (is_nebhash) return client_send_result(client, "true");

	if(json_params->u.array.length>0)
	{
		double diff = client_normalize_difficulty(json_params->u.array.values[0]->u.dbl, client);

		// Low-difficulty algos derive their share target from difficulty_actual
		// directly; diff_to_target() would map a sub-0.00006 value above
		// YAAMP_MAXDIFF and silently drop a valid suggestion.
		if (client_uses_precise_difficulty())
		{
			if (diff > 0 && isfinite(diff))
				client->difficulty_actual = diff;
		}
		else
		{
			uint64_t user_target = diff_to_target(diff);

			if(user_target >= YAAMP_MINDIFF && user_target <= YAAMP_MAXDIFF)
				client->difficulty_actual = diff;
		}
	}

	client_send_result(client, "true");
	return true;
}

bool client_suggest_target(YAAMP_CLIENT *client, json_value *json_params)
{
	client_send_result(client, "true");
	return true;
}

bool client_subscribe(YAAMP_CLIENT *client, json_value *json_params)
{
	//if(client_find_my_ip(client->sock->ip)) return false;
	if (is_progpow || is_eth)
		get_nonce_prefix(client->extranonce1_default);
	else
		get_next_extraonce1(client->extranonce1_default);

	if (is_nebhash)
	{
		// neb-pool/1 has a 4-byte extranonce1 and derives extranonce2 length
		// from the current node job, not from yiimp's generic 4-byte default.
		if (!nebhash_current_job_ready())
		{
			nebhash_send_error(client, 20, "pool has no work from its node yet");
			return true;
		}
		client->extranonce2size_default = nebhash_extranonce2_size();
		nebhash_client_init(client);
	}
	else
	{
		if(!strcmp(g_stratum_algo,"neoscrypt-xaya")) client->extranonce2size_default = 2;
		else client->extranonce2size_default = YAAMP_EXTRANONCE2_SIZE;
		client->difficulty_actual = g_stratum_difficulty;
	}

	strcpy(client->extranonce1, client->extranonce1_default);
	client->extranonce2size = client->extranonce2size_default;

	// decred uses an extradata field in block header, 2 first uint32 are set by the miner
	if (g_current_algo->name && !strcmp(g_current_algo->name,"decred")) {
		memset(client->extranonce1, '0', sizeof(client->extranonce1));
		memcpy(&client->extranonce1[16], client->extranonce1_default, YAAMP_EXTRANONCE2_SIZE*2);
		client->extranonce1[24] = '\0';
		client->extranonce2size = client->extranonce2size_default = 12;
	}

	get_random_key(client->notify_id);

	char ethstratum_version[64] = { 0 };
	if(json_params->u.array.length>0)
	{
		if (json_params->u.array.values[0]->u.string.ptr)
			strncpy(client->version, json_params->u.array.values[0]->u.string.ptr, 1023);

		if (strstr(client->version, "NiceHash")) {
			client->is_nicehash = true;
			client->difficulty_actual = g_stratum_nicehash_difficulty;
		}

		// NiceHash EthereumStratum/1.0.0 advertises the protocol as the second
		// subscribe parameter. Rigel advertises the same string but uses its
		// own object-shaped subscribe reply, so exclude it explicitly.
		if (is_eth && json_params->u.array.length > 1 &&
			json_params->u.array.values[1]->u.string.ptr)
		{
			const char *v = json_params->u.array.values[1]->u.string.ptr;
			if (!strncmp(v, "EthereumStratum/", 16))
			{
				strncpy(ethstratum_version, v, sizeof(ethstratum_version)-1);
				if (!strstr(client->version, "Rigel"))
					client->is_nicehash_ethstratum = true;
			}
		}

		if(strstr(client->version, "proxy") || strstr(client->version, "/3."))
      client->reconnectable = false;

		if(strstr(client->version, "ccminer")) client->stats = true;
		if(strstr(client->version, "cpuminer-multi")) client->stats = true;
		if(strstr(client->version, "cpuminer-opt")) client->stats = true;
	}

	if (is_nebhash)
	{
		// Every neb-pool/1 client sends the protocol token as param[1]. It is
		// not a reconnect notify id, so never feed it to the generic notify-id
		// reuse logic below (which would leak another miner's session state).
		json_value *protocol = json_params->u.array.length > 1 ?
			json_params->u.array.values[1] : NULL;
		if (!json_is_string(protocol) || strcmp(json_string_value(protocol), "neb-pool/1"))
		{
			nebhash_send_error(client, 20, "unsupported stratum protocol");
			return true;
		}
	}
	else if(json_params->u.array.length>1 && !client->is_nicehash_ethstratum)
	{
		char notify_id[1024] = { 0 };
		if (json_params->u.array.values[1]->u.string.ptr)
			strncpy(notify_id, json_params->u.array.values[1]->u.string.ptr, 1023);

		YAAMP_CLIENT *client1 = client_find_notify_id(notify_id, true);
		if(client1)
		{
			strncpy(client->notify_id, notify_id, 1023);

			client->jobid_locked = client1->jobid_locked;
//			client->jobid_next = client1->jobid_next;
			client->difficulty_actual = client1->difficulty_actual;

			client->extranonce2size_default = client1->extranonce2size_default;
			strcpy(client->extranonce1_default, client1->extranonce1_default);

			client->extranonce2size = client1->extranonce2size_reconnect;
			strcpy(client->extranonce1, client1->extranonce1_reconnect);

			client->speed = client1->speed;
			memcpy(client->speed_slots, client1->speed_slots, sizeof(client->speed_slots));
			memcpy(client->speed_share_slots, client1->speed_share_slots, sizeof(client->speed_share_slots));
			memcpy(client->speed_sq_slots, client1->speed_sq_slots, sizeof(client->speed_sq_slots));
			client->speed_slot = client1->speed_slot;
			client->speed_since = client1->speed_since;
			client->last_share = client1->last_share;
			client->extranonce1_id = client1->extranonce1_id;

			client->userid = client1->userid;
			client->workerid = client1->workerid;

			memcpy(client->job_history, client1->job_history, sizeof(client->job_history));
			client1->lock_count = 0;

			if (g_debuglog_client) {
				debuglog("reconnecting client locked to %x\n", client->jobid_next);
			}
		}

		else
		{
			YAAMP_CLIENT *client1 = client_find_notify_id(notify_id, false);
			if(client1)
			{
				strncpy(client->notify_id, notify_id, 1023);

				client->difficulty_actual = client1->difficulty_actual;
				client->speed = client1->speed;
			memcpy(client->speed_slots, client1->speed_slots, sizeof(client->speed_slots));
			memcpy(client->speed_share_slots, client1->speed_share_slots, sizeof(client->speed_share_slots));
			memcpy(client->speed_sq_slots, client1->speed_sq_slots, sizeof(client->speed_sq_slots));
			client->speed_slot = client1->speed_slot;
			client->speed_since = client1->speed_since;
			client->last_share = client1->last_share;

				memcpy(client->job_history, client1->job_history, sizeof(client->job_history));
				client1->lock_count = 0;

				if (g_debuglog_client) {
					debuglog("reconnecting2 client\n");
				}
			}
		}
	}

	strcpy(client->extranonce1_last, client->extranonce1);
	client->extranonce2size_last = client->extranonce2size;

	if (g_debuglog_client) {
		debuglog("new client with nonce %s\n", client->extranonce1);
	}

	if (is_nebhash)
	{
		client->neb_subscribed = true;
		char result[YAAMP_SMALLBUFSIZE];
		nebhash_subscribe_result(client, result, sizeof(result));
		client_send_result(client, "%s", result);
	}
	else if (is_eth)
	{
		if (client->is_nicehash_ethstratum)
		{
			// EthereumStratum/1.0.0 (NiceHash spec): [[subscription], extranonce1].
			// There is no extranonce2 and the first job arrives after authorize.
			client_send_result(client,
				"[[\"mining.notify\",\"%s\",\"%s\"],\"%s\"]",
				client->notify_id,
				ethstratum_version[0] ? ethstratum_version : "EthereumStratum/1.0.0",
				client->extranonce1);
		}
		else
		{
			// Rigel's EthereumStratum decoder maps the subscribe result into a
			// struct with exactly two fields: mining_notify and extranonce.
			// mining_notify is a single MiningNotifyParams object whose four
			// fields (job_id, seed_hash, header_hash, _clean_jobs) are all
			// strings. It is not a list of command objects, and _clean_jobs is
			// not a JSON boolean; either mismatch makes the whole message fail
			// the untagged ServerMessage parse. Difficulty is delivered later
			// by the standalone mining.set_difficulty notification.
			//
			// Seed the miner with the current job so it can start hashing before
			// the first standalone mining.notify.
			YAAMP_JOB *job = job_get_last_for_ethash(client);
			const char *seed = "0x0000000000000000000000000000000000000000000000000000000000000000";
			const char *header = seed;
			int jobid = 0;

			if (job && job->templ && job->templ->eth_seed[0] && job->templ->eth_header[0])
			{
				seed = job->templ->eth_seed;
				header = job->templ->eth_header;
				jobid = job->id;
				client->jobid_sent = job->id;
			}

			client_send_result(client,
				"{\"mining_notify\":{\"job_id\":\"%x\",\"seed_hash\":\"%s\","
				"\"header_hash\":\"%s\",\"_clean_jobs\":\"true\"},"
				"\"extranonce\":\"%s\"}",
				jobid, seed, header, client->extranonce1);
		}
	}
	else if (is_progpow)
	{
		kawpow_send_nonceprefix(client);
	}
	else if (is_equihash) {
		// send extranonce
		client_send_result(	client, "[null,\"%s\"]",client->extranonce1);
	}
	else {
		double wire_difficulty = homescrypt_wire_difficulty(client, client->difficulty_actual);
		const char *format = homescrypt_uses_gpuminer_job(client) ?
			"[[[\"mining.set_difficulty\",\"%.8g\"],[\"mining.notify\",\"%s\"]],\"%s\",%d]" :
			"[[[\"mining.set_difficulty\",\"%.3g\"],[\"mining.notify\",\"%s\"]],\"%s\",%d]";
		client_send_result(client, format,
			wire_difficulty, client->notify_id, client->extranonce1, client->extranonce2size);
	}

	return true;
}

///////////////////////////////////////////////////////////////////////////////////////////
bool client_validate_user_address(YAAMP_CLIENT *client)
{
	// c=<quote symbol> explicitly asks for quote-wallet payouts. Bind the
	// account to the configured quote coin (USDT id=9 by default) and skip
	// coin-specific wallet validation, otherwise a dedicated coin port would
	// silently re-bind the account to the mined coin instead of the quote.
	if (client->pay_quote) {
		client->coinid = g_quote_coinid;
		if (client->userid > 0) {
			CommonLock(&g_db_mutex);
			db_query(g_db, "UPDATE accounts SET coinid=%d WHERE id=%d", g_quote_coinid, client->userid);
			CommonUnlock(&g_db_mutex);
		}
		return true;
	}

	// A dedicated port can force one coin for every login. This is needed when
	// two chains share an address format and the account is already bound to
	// the other chain: keep the global binding untouched and bind this session
	// only to the coin configured on this listener.
	if (g_stratum_forced_coinid > 0)
	{
		YAAMP_COIND *forced = (YAAMP_COIND *) object_find(&g_list_coind, g_stratum_forced_coinid);
		if (!forced || !coind_can_mine(forced) ||
			(strlen(g_current_algo->name) && strcmp(g_current_algo->name, forced->algo)) ||
			!coind_validate_user_address(forced, client->username))
		{
			clientlog(client, "unable to use forced coinid %d for %s", g_stratum_forced_coinid, client->username);
			return false;
		}

		client->coinid = forced->id;
		return true;
	}

	int client_workers = 0;
	if (client->userid == 0) {
		client_workers = client_workers_byaddress(client->username);
	} else {
		client_workers = client_workers_count(client);
	}

	// if already logged in this instance, reuse data from other workers (in memory)
	if (client_workers > 1) {
		if (client_workers > 100 && (client_workers%100 == 0)) {
			clientlog(client, "using %d workers", client_workers);
		}
		if (client_auth_by_workers(client)) {
			// client->coinid filled
			return true;
		}
	}

	if (!client->coinid) {
		for(CLI li = g_list_coind.first; li; li = li->next) {
			YAAMP_COIND *coind = (YAAMP_COIND *)li->data;
			// debuglog("user %s testing on coin %s ...\n", client->username, coind->symbol);
			if(!coind_can_mine(coind)) continue;
			if(strlen(g_current_algo->name) && strcmp(g_current_algo->name, coind->algo)) continue;
			if(coind_validate_user_address(coind, client->username)) {
				debuglog("new user %s for coin %s\n", client->username, coind->symbol);
				client->coinid = coind->id;
				// update the db now to prevent addresses conflicts
				CommonLock(&g_db_mutex);
				db_init_user_coinid(g_db, client);
				CommonUnlock(&g_db_mutex);
				return true;
			}
		}
	}

	if (!client->coinid) {
		return false;
	}

	YAAMP_COIND *coind = (YAAMP_COIND *)object_find(&g_list_coind, client->coinid);
	if (!coind) {
		clientlog(client, "unable to find the wallet for coinid %d...", client->coinid);
		return false;
	} else {
		if(g_current_algo && strlen(g_current_algo->name) && strcmp(g_current_algo->name, coind->algo)) {
			clientlog(client, "%s address is on the wrong coin %s, reset to auto...", client->username, coind->symbol);
			client->coinid = 0;
			CommonLock(&g_db_mutex);
			db_init_user_coinid(g_db, client);
			CommonUnlock(&g_db_mutex);
			return false;
		}
	}

	bool isvalid = coind_validate_user_address(coind, client->username);
	if (isvalid) {
		client->coinid = coind->id;
	} else {
		clientlog(client, "unable to verify %s address for user coinid %d...", coind->symbol, client->coinid);
	}
	return isvalid;
}

///////////////////////////////////////////////////////////////////////////////////////////

bool client_authorize(YAAMP_CLIENT *client, json_value *json_params)
{
	const char *neb_login = NULL;
	const char *neb_password = NULL;
	if (json_params->u.array.length > 0 && json_is_string(json_params->u.array.values[0]))
		neb_login = json_string_value(json_params->u.array.values[0]);
	if (json_params->u.array.length > 1 && json_is_string(json_params->u.array.values[1]))
		neb_password = json_string_value(json_params->u.array.values[1]);

	// The reference pool rejects authorize before subscribe without creating
	// an account or closing the socket.
	if (is_nebhash && !client->neb_subscribed)
	{
		nebhash_send_error(client, 25, "Session not subscribed");
		return true;
	}

	// neb-pool/1 lets one session authorize several neb1 accounts (dev-fee
	// miners do), so later authorizes are additions rather than a new login.
	if (is_nebhash && client->neb_subscribed && g_list_client.Find(client))
	{
		if (!neb_login || !nebhash_add_login(client, neb_login, neb_password))
		{
			nebhash_send_error(client, 24, "Invalid neb1 login");
			return true;
		}
		client_send_result(client, "true");
		if (!client->jobid_sent) job_send_last(client);
		return true;
	}

	if(g_list_client.Find(client)) {
		clientlog(client, "Already logged");
		if (is_nebhash)
		{
			nebhash_send_error(client, 20, "Already logged");
			return true;
		}
		client_send_error(client, 21, "Already logged");
		return false;
	}

	if(neb_password)
	{
		strncpy(client->password, neb_password, 1023);
		// NEB's password d=/sd= select the session's pool target rather than
		// a generic yiimp difficulty. Apply it before the first job is pushed.
		if (is_nebhash) nebhash_apply_password(client);
	}

	if (g_list_client.count >= g_stratum_max_cons) {
		// NEB code 21 is "job not found"; generic authorization failures use 20.
		if (is_nebhash)
		{
			nebhash_send_error(client, 20, "Server full");
			return true;
		}
		client_send_error(client, 21, "Server full");
		return false;
	}

	if(neb_login)
	{
		strncpy(client->username, neb_login, 1023);

		db_check_user_input(client->username);
		int len = strlen(client->username);
		if (!len)
			return false;

		if (is_nebhash)
		{
			// The node has no validateaddress RPC, so verify the bech32m
			// wallet address locally before it reaches the account table.
			if (!nebhash_add_login(client, neb_login, neb_password))
			{
				nebhash_send_error(client, 24, "Invalid neb1 login");
				return true;
			}

			// Use the same first-'.' split and worker rules as the reference
			// pool. nebhash_add_login() has already validated both parts.
			char neb_address[1088], neb_worker[1088];
			nebhash_split_login(neb_login, neb_address, neb_worker);
			db_check_user_input(neb_address);
			strncpy(client->username, neb_address, sizeof(client->username) - 1);
			client->username[sizeof(client->username) - 1] = '\0';
			strncpy(client->worker, neb_worker, sizeof(client->worker) - 1);
			client->worker[sizeof(client->worker) - 1] = '\0';
			if (strlen(client->username) > MAX_ADDRESS_LEN)
			{
				nebhash_send_error(client, 24, "Invalid neb1 login");
				return true;
			}
		}
		else
		{
			char *sep = strpbrk(client->username, ".,;:");
			if (sep) {
				*sep = '\0';
				strncpy(client->worker, sep+1, 1023-len);
				if (strlen(client->username) > MAX_ADDRESS_LEN) return false;
			} else if (len > MAX_ADDRESS_LEN) {
				return false;
			}
		}
	}
/*
	if (!is_base58(client->username)) 
	{
		clientlog(client, "bad mining address %s", client->username);
		return false;
	}
*/	
	bool reset = client_initialize_multialgo(client);
	if(reset) return false;

	// NEB's target comes from the node's share_target and its own reference
	// vardiff. Yiimp's password-driven difficulty/vardiff must not overwrite
	// the exact 256-bit target already seeded at subscribe.
	if (!is_nebhash) client_initialize_difficulty(client);

	if (g_debuglog_client) {
		debuglog("new client %s, %s, %s\n", client->username, client->password, client->version);
	}

	if(!client->userid || !client->workerid)
	{
		CommonLock(&g_db_mutex);
		db_add_user(g_db, client);

		if(client->userid == -1)
		{
			CommonUnlock(&g_db_mutex);
			client_block_ip(client, "account locked");
			clientlog(client, "account locked");

			return false;
		}

		db_add_worker(g_db, client);
		CommonUnlock(&g_db_mutex);
	}

	// db_add_user() clears solo while parsing password tokens; neb-pool/1
	// carries the solo option as a plain "solo" password word.
	if (is_nebhash && client->password && strcasestr(client->password, "solo"))
		client->solo = true;

	// when auto exchange is disabled, only authorize good wallet address...
	if (!g_autoexchange && !client_validate_user_address(client)) 
	{

		clientlog(client, "bad mining address %s", client->username);
		client_send_result(client, "false");

		CommonLock(&g_db_mutex);
		db_clear_worker(g_db, client);
		CommonUnlock(&g_db_mutex);

		return false;
	}
	
	client_send_result(client, "true");
	// The NEB job push publishes mining.set_target in the reference order.
	if (!is_nebhash) client_send_difficulty(client, client->difficulty_actual);

	if(client->jobid_locked)
		job_send_jobid(client, client->jobid_locked);
	else
		job_send_last(client);

	g_list_client.AddTail(client);
	// Cache the first NEB login's account/worker IDs without creating a
	// duplicate worker row; later dev-fee logins are resolved on first submit.
	if (is_nebhash && neb_login && !nebhash_select_login(client, neb_login))
	{
		g_list_client.Delete(client);
		return false;
	}
	return true;
}

///////////////////////////////////////////////////////////////////////////////////////////

bool client_update_block(YAAMP_CLIENT *client, json_value *json_params)
{
	// password, id, block hash
	if(json_params->u.array.length < 3 || !json_params->u.array.values[0]->u.string.ptr)
	{
		clientlog(client, "update block, bad params");
		return false;
	}

	if(strcmp(g_tcp_password, json_params->u.array.values[0]->u.string.ptr))
	{
		clientlog(client, "update block, bad password");
		return false;
	}

	int coinid = json_params->u.array.values[1]->u.integer;
	if(!coinid) return false;
	YAAMP_COIND *coind = (YAAMP_COIND *)object_find(&g_list_coind, coinid, true);
	if(!coind) return false;

	const char* hash = json_params->u.array.values[2]->u.string.ptr;

	if (g_debuglog_client) {
		debuglog("notify: new %s block %s\n", coind->symbol, hash);
	}

	snprintf(coind->lastnotifyhash, 161, "%s", hash);

	coind->newblock = true;
	coind->notreportingcounter = 0;

	if (!strcmp("DCR", coind->rpcencoding)) {
		usleep(300*YAAMP_MS);
	}

	block_confirm(coind->id, hash);

	coind_create_job(coind);
	object_unlock(coind);

	if(coind->isaux)
	{
		/*  only update job for DOGE ,
			other coins ignore intermediate updates to avoid fast job re-issue	*/
		if (strcmp(coind->symbol, "DOGE") == 0)
		{
			for(CLI li = g_list_coind.first; li; li = li->next)
			{
				YAAMP_COIND *coind = (YAAMP_COIND *)li->data;
				if(!coind_can_mine(coind)) continue;
				if(coind->pos) continue;

				coind_create_job(coind);
			}
		}
	}

	job_signal();
	return true;
}

///////////////////////////////////////////////////////////////////////////////////////////

bool client_ask_stats(YAAMP_CLIENT *client)
{
	int id;
	if (!client->stats) return false;
	id = client_ask(client, "client.get_stats", "[]");
	return true;
}

static bool client_store_stats(YAAMP_CLIENT *client, json_value *result)
{
	if (json_typeof(result) != json_object)
		return false;

	json_value *val = json_get_val(result, "type");
	if (val && json_is_string(val)) {
		debuglog("received stats of type %s\n", json_string_value(val));
		//if (!strcmp("gpu", json_string_value(val))) {
			CommonLock(&g_db_mutex);
			db_store_stats(g_db, client, result);
			CommonUnlock(&g_db_mutex);
		//}
		return true;
	}

	return false;
}

///////////////////////////////////////////////////////////////////////////////////////////

int client_workers_count(YAAMP_CLIENT *client)
{
	int count = 0;
	if (!client || client->userid <= 0)
		return count;

	g_list_client.Enter();
	for(CLI li = g_list_client.first; li; li = li->next)
	{
		YAAMP_CLIENT *cli = (YAAMP_CLIENT *)li->data;
		if (cli->deleted) continue;
		if (cli->userid == client->userid) count++;
	}
	g_list_client.Leave();

	return count;
}

int client_workers_byaddress(const char *username)
{
	int count = 0;
	if (!username || !strlen(username))
		return count;

	g_list_client.Enter();
	for(CLI li = g_list_client.first; li; li = li->next)
	{
		YAAMP_CLIENT *cli = (YAAMP_CLIENT *)li->data;
		if (cli->deleted) continue;
		if (strcmp(cli->username, username) == 0) count++;
	}
	g_list_client.Leave();

	return count;
}

bool client_auth_by_workers(YAAMP_CLIENT *client)
{
	if (!client || client->userid < 0)
		return false;

	g_list_client.Enter();
	for(CLI li = g_list_client.first; li; li = li->next)
	{
		YAAMP_CLIENT *cli = (YAAMP_CLIENT *)li->data;
		if (cli->deleted) continue;
		if (client->userid) {
			if(cli->userid == client->userid) {
				client->coinid = cli->coinid;
				break;
			}
		} else if (strcmp(cli->username, client->username) == 0) {
			client->coinid = cli->coinid;
			client->userid = cli->userid;
			break;
		}
	}
	g_list_client.Leave();

	return (client->coinid > 0 && client->userid > 0);
}

///////////////////////////////////////////////////////////////////////////////////////////

//YAAMP_SOURCE *source_init(YAAMP_CLIENT *client)
//{
//	YAAMP_SOURCE *source = NULL;
//	g_list_source.Enter();
//
//	for(CLI li = g_list_source.first; li; li = li->next)
//	{
//		YAAMP_SOURCE *source1 = (YAAMP_SOURCE *)li->data;
//		if(!strcmp(source1->ip, client->sock->ip))
//		{
//			source = source1;
//			break;
//		}
//	}
//
//	if(!source)
//	{
//		source = new YAAMP_SOURCE;
//		memset(source, 0, sizeof(YAAMP_SOURCE));
//
//		strncpy(source->ip, client->sock->ip, 64);
//		source->speed = 1;
//
//		g_list_source.AddTail(source);
//	}
//
//	source->count++;
//
//	g_list_source.Leave();
//	return source;
//}
//
//void source_close(YAAMP_SOURCE *source)
//{
//	g_list_source.Enter();
//	source->count--;
//
//	if(source->count <= 0)
//	{
//		g_list_source.Delete(source);
//		delete source;
//	}
//
//	g_list_source.Leave();
//}
//
//void source_prune()
//{
////	debuglog("source_prune() %d\n", g_list_source.count);
//	g_list_source.Enter();
//	for(CLI li = g_list_source.first; li; li = li->next)
//	{
//		YAAMP_SOURCE *source = (YAAMP_SOURCE *)li->data;
//		source->speed *= 0.8;
//
//		double idx = source->speed/source->count;
//		if(idx < 0.0005)
//		{
//			stratumlog("disconnect all ip %s, %s, count %d, %f, %f\n", source->ip, g_current_algo->name, source->count, source->speed, idx);
//			for(CLI li = g_list_client.first; li; li = li->next)
//			{
//				YAAMP_CLIENT *client = (YAAMP_CLIENT *)li->data;
//				if(client->deleted) continue;
//				if(!client->workerid) continue;
//
//				if(!strcmp(source->ip, client->sock->ip))
//					shutdown(client->sock->sock, SHUT_RDWR);
//			}
//		}
//
//		else if(source->count > 500)
//			stratumlog("over 500 ip %s, %s, %d, %f, %f\n", source->ip, g_current_algo->name, source->count, source->speed, idx);
//	}
//
//	g_list_source.Leave();
//}

///////////////////////////////////////////////////////////////////////////////////////////

void *client_thread(void *p)
{
	YAAMP_CLIENT *client = new YAAMP_CLIENT;
	if(!client) {
		stratumlog("client_thread OOM");
		pthread_exit(NULL);
		return NULL;
	}
	memset(client, 0, sizeof(YAAMP_CLIENT));

	client->reconnectable = true;
	client->created = time(NULL);
	client->speed_since = client->created;
	client->last_best = time(NULL);

	YAAMP_CLIENT_START *start = (YAAMP_CLIENT_START *)p;
	int client_sock = start->sock;
	int tls_mode = start->tls_mode;
	delete start;

	client->sock = socket_initialize(client_sock);
//	client->source = source_init(client);


	// socket_initialize() has already consumed any HAProxy PROXY header, so
	// the next byte is either a TLS ClientHello or the first JSON character.
	bool tls_ok = true;
	if (tls_mode == YAAMP_TLS_FORCE ||
	    (tls_mode == YAAMP_TLS_SNIFF && socket_looks_like_tls(client->sock)))
	{
		tls_ok = socket_tls_enable(client->sock);
		if (!tls_ok)
			clientlog(client, "TLS handshake failed");
	}
	client->tls = (tls_ok && client->sock->ssl != NULL);

//	usleep(g_list_client.count * 5000);

	while(tls_ok && !g_exiting)
	{
		if(client->submit_bad > 1024)
		{
			clientlog(client, "bad submits");
			break;
		}

		json_value *json = socket_nextjson(client->sock, client);
		if(!json)
		{
//			clientlog(client, "bad json");
			break;
		}

		client->id_int = json_get_int(json, "id");
		client->id_str = json_get_string(json, "id");
		if (client->id_str && strlen(client->id_str) > 32) {
			clientlog(client, "bad id");
			break;
		}

		const char *method = json_get_string(json, "method");

		if (!method && client->stats && client->id_int == client->reqid)
		{
			json_value *result = json_get_object(json, "result");
			if (result) client_store_stats(client, result);
			json_value_free(json);
			continue;
		}

		if(!method)
		{
			json_value_free(json);
			clientlog(client, "bad json, no method");
			break;
		}

		json_value *json_params = json_get_array(json, "params");
		if(!json_params)
		{
			json_value_free(json);
			clientlog(client, "bad json, no params");
			break;
		}

		// submits are logged with their result (client_log_submit)
		if (g_debuglog_client && strcmp(method, "mining.submit")) {
			char who[1200];
			client_debug_who(client, who, sizeof(who));
			debuglog("client %s %s\n", method, who);
		}

		bool b = false;
		if(!strcmp(method, "mining.subscribe"))
			b = client_subscribe(client, json_params);

		else if(!strcmp(method, "mining.authorize"))
			b = client_authorize(client, json_params);

		else if(!strcmp(method, "mining.ping"))
			b = client_send_result(client, "\"pong\"");

		else if(!strcmp(method, "mining.submit"))
			b = client_submit(client, json_params);

		else if(!strcmp(method, "mining.suggest_difficulty"))
			b = client_suggest_difficulty(client, json_params);

		else if(!strcmp(method, "mining.suggest_target"))
			b = client_suggest_target(client, json_params);

		else if(!strcmp(method, "mining.get_transactions"))
			b = client_send_result(client, "[]");

		else if(!strcmp(method, "mining.configure"))
			b = client_configure(client, json_params); // ASICBOOST

		else if(!strcmp(method, "mining.extranonce.subscribe"))
		{
			client->extranonce_subscribe = true;
			b = client_send_result(client, "true");
		}

		else if(!strcmp(method, "mining.update_block"))
			client_update_block(client, json_params);

		else if(!strcmp(method, "getwork"))
		{
			clientlog(client, "using getwork"); // client using http:// url
		}
		else if(is_eth && !strcmp(method, "eth_submitLogin"))
		{
			// T-Rex uses ethproxy-style calls even for stratum+tcp:// on the
			// ethash algorithm. Treat it as a first-class protocol here.
			// A repeated login on the same socket must not rotate the prefix.
			const bool first_ethproxy_login = !client->is_ethproxy;
			client->is_ethproxy = true;

			// ethproxy has no mining.subscribe, so allocate the session's
			// 24-bit nonce prefix here. Claymore/GMiners include this prefix in
			// eth_getWork and prepend it to the miner nonce on submit. If a
			// generic mining.subscribe arrived first, keep that prefix instead
			// of allocating a second one.
			if (first_ethproxy_login)
			{
				if (client->extranonce1[0])
				{
					snprintf(client->extranonce1_default, sizeof(client->extranonce1_default),
						"%06x", (unsigned int) strtoul(client->extranonce1, NULL, 16) & 0xffffff);
				}
				else
				{
					// get_nonce_prefix() emits 4 hex; ethproxy needs 6.
					get_nonce_prefix(client->extranonce1_default);
					snprintf(client->extranonce1_default, sizeof(client->extranonce1_default),
						"%06x", (unsigned int) strtoul(client->extranonce1_default, NULL, 16) & 0xffffff);
				}

				strcpy(client->extranonce1, client->extranonce1_default);
				strcpy(client->extranonce1_last, client->extranonce1);

				// ethproxy has no extranonce2. Keep every baseline field in
				// sync so job assignment never sees a change to reconnect on.
				client->extranonce2size_default = 0;
				client->extranonce2size = 0;
				client->extranonce2size_last = 0;

				debuglog("ethproxy login %s %s nonce %s\n",
					client->username, client->sock->ip, client->extranonce1);
			}

			// ethproxy clients never send mining.subscribe, so the difficulty
			// normally assigned in client_subscribe() is still zero here.
			// Set it before authorize() sends the initial work and records the
			// share target; otherwise eth_getWork advertises an all-ff target
			// and every share is accounted at difficulty zero.
			if (!(client->difficulty_actual > 0) && g_stratum_difficulty > 0)
				client->difficulty_actual = g_stratum_difficulty;

			b = client_authorize(client, json_params);
		}

		else if(is_eth && !strcmp(method, "eth_getWork"))
			b = ethash_ethproxy_get_work(client);

		else if(is_eth && !strcmp(method, "eth_submitWork"))
			b = ethash_ethproxy_submit_work(client, json_params);

		else if(!strcmp(method, "eth_submitHashrate"))
		{
			b = client_send_result(client, "true"); // skip eth_submitHashrate method
		}

		else
		{
			b = client_send_error(client, 20, "Not supported");
			client->submit_bad++;

			stratumlog("unknown method %s %s\n", method, client->sock->ip);
		}

		json_value_free(json);
		if(!b) break;
	}

//	source_close(client->source);

	if(!client) {
		pthread_exit(NULL);
	}

	else if(client->sock->total_read == 0)
		clientlog(client, "no data");

	if(client->sock->sock >= 0)
		shutdown(client->sock->sock, SHUT_RDWR);

	// NEB sessions can create worker rows for extra dev-fee logins on first
	// submit; clear all of them, not just the primary login's worker.
	if (is_nebhash) nebhash_clear_workers(client);

	// only authorized miners are in the list; blocknotify connections end silently
	if(g_list_client.Find(client))
	{
		if (g_debuglog_client)
			debuglog("client disconnect %s %s.%s\n", client->sock->ip, client->username, client->worker);

		if(client->workerid && !client->reconnecting)
		{
			CommonLock(&g_db_mutex);
			db_clear_worker(g_db, client);
			CommonUnlock(&g_db_mutex);
		}
		object_delete(client);
	} else {
		// only clients sockets in g_list_client are purged (if marked deleted)
		client_delete(client);
	}

	pthread_exit(NULL);
}

/*
	simple implementaion of asicboost of stratum protocol
	full spec: https://github.com/slushpool/stratumprotocol/blob/master/stratum-extensions.mediawiki
*/
bool client_configure(YAAMP_CLIENT *client, json_value *json_params)
{
	uint32_t version_mask = 0x1fffe000;
	bool version_rolling_enabled = false;

    if(json_params->u.array.length>1 && json_is_array(json_params->u.array.values[0]) && json_is_object(json_params->u.array.values[1]))
	{
		for (int index_array=0; index_array < json_params->u.array.values[0]->u.array.length; ++index_array) {
			if (json_is_string(json_params->u.array.values[0]->u.array.values[index_array]) && 
				!strcmp(json_params->u.array.values[0]->u.array.values[index_array]->u.string.ptr, "version-rolling"))
			{
				for (int index_object=0; index_object < json_params->u.array.values[1]->u.object.length; ++index_object) {
					if(!strcmp(json_params->u.array.values[1]->u.object.values[index_object].name, "version-rolling.mask") &&
						json_is_string(json_params->u.array.values[1]->u.object.values[index_object].value))
					{
						version_mask = strtoul(json_params->u.array.values[1]->u.object.values[index_object].value->u.string.ptr, NULL, 16);
						version_rolling_enabled = true;
					}
				}
			}
		}
	}

	if (version_rolling_enabled)
	{
		return client_send_result(client, "{\"version-rolling\":true,\"version-rolling.mask\":\"%08x\"}", version_mask);
	}
	else {
		return client_send_result(client, "{\"version-rolling\": false}");
	}
}
