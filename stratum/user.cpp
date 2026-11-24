
#include "stratum.h"
#include "nebhash/work.h"

// sql injection security, unwanted chars
void db_check_user_input(char* input)
{
	char *p = NULL;
	if (input && input[0]) {
		p = strpbrk(input, " \"'\\");
		if(p) *p = '\0';
	}
}

void db_check_coin_symbol(YAAMP_DB *db, char* symbol)
{
	if (!symbol) return;
	size_t len = strlen(symbol);
	if (len >= 2 && len <= 12) {
		// An explicit quote payout is not a mineable coin, but it is a valid
		// c= token even on dedicated autoexchange=0 ports.
		if (!strcasecmp(symbol, g_quote_symbol)) {
			strcpy(symbol, g_quote_symbol);
			return;
		}
		if (!g_autoexchange)
			db_query(db, "SELECT symbol FROM coins WHERE installed AND algo='%s' AND symbol='%s'", g_stratum_algo, symbol);
		else
			db_query(db, "SELECT symbol FROM coins WHERE installed AND (symbol='%s' OR symbol2='%s')", symbol, symbol);

		MYSQL_RES *result = mysql_store_result(&db->mysql);
		*symbol = '\0';
		if (!result) return;
		MYSQL_ROW row = mysql_fetch_row(result);
		if (row) {
			strcpy(symbol, row[0]);
		}
		mysql_free_result(result);
	} else {
		*symbol = '\0';
	}
}

void db_add_user(YAAMP_DB *db, YAAMP_CLIENT *client)
{
	db_clean_string(db, client->username);
	db_clean_string(db, client->password);
	db_clean_string(db, client->version);
	db_clean_string(db, client->notify_id);
	db_clean_string(db, client->worker);

	bool guest = false;
	int gift = -1;
	client->solo = false;

	std::string symbol;
	std::vector<std::string> commandlist;
	std::string passwordstring(client->password);

	string_tokenize(passwordstring, ',' , commandlist);

	for (auto &command_pairs: commandlist) {
		std::vector<std::string> command;
		string_tokenize(command_pairs, '=', command);

		if (command.size() > 1) {
			// set payout symbol
			if (command.at(0) == "c") {
				symbol = command.at(1);
				if (!strcasecmp(symbol.c_str(), g_quote_symbol)) client->pay_quote = true;
			}
			else if (command.at(0) == "s") {
				symbol = command.at(1);
				if (!strcasecmp(symbol.c_str(), g_quote_symbol)) client->pay_quote = true;
			}
			else if (command.at(0) == "m") {
				if (command.at(1) == "solo") client->solo = true;
			}
			// set list of specific coins to mine only
			else if (command.at(0) == "mc") {
				string_tokenize(command.at(1), '/', client->coins_mining_list);
			}
			// set list of specific coins to skip in selection
			else if (command.at(0) == "nc") {
				string_tokenize(command.at(1), '/', client->coins_ignore_list);
			}
#ifdef ALLOW_CUSTOM_DONATIONS
			else if (command.at(0) == "g") {
				gift = atoi(command.at(1).c_str());
				if(gift > 100) gift = 100;
			}
#endif

		}

	}

	db_check_user_input(client->username);
	if(strlen(client->username) < MIN_ADDRESS_LEN) {
		// allow benchmark / test / donate usernames
		if (!strcmp(client->username, "benchmark") || !strcmp(client->username, "donate") || !strcmp(client->username, "test")) {
			guest = true;
			if (g_list_coind.first) {
				CLI li = g_list_coind.first;
				YAAMP_COIND *coind = (YAAMP_COIND *)li->data;
				if (!strlen(client->worker)) STRSET(client->worker, client->username);
				STRSET(client->username, coind->wallet);
				if (!strcmp(client->username, "benchmark")) STRADD(client->password, ",stats");
				if (!strcmp(client->username, "donate")) gift = 100;
			}
		}
		if (!guest) {
			debuglog("Invalid user address '%s'\n", client->username);
			return;
		}
	}

	// debuglog("user %s %s gives %d %\n", client->username, symbol, gift);
	db_query(db, "SELECT id, is_locked, logtraffic, coinid, donation FROM accounts WHERE username='%s'", client->username);

	MYSQL_RES *result = mysql_store_result(&db->mysql);
	if(!result) return;

	MYSQL_ROW row = mysql_fetch_row(result);
	if(row)
	{
		if(row[1] && atoi(row[1])) client->userid = -1;
		else client->userid = atoi(row[0]);

		client->logtraffic = row[2] && atoi(row[2]);
		client->coinid = row[3] ? atoi(row[3]) : 0;
		if (gift == -1) gift = row[4] ? atoi(row[4]) : 0; // keep current
	}

	mysql_free_result(result);

	char symbol_buf[16] = { 0 };
	strncpy(symbol_buf, symbol.c_str(), sizeof(symbol_buf) - 1);
	db_check_user_input(symbol_buf);
	db_check_coin_symbol(db, symbol_buf);
	symbol = symbol_buf;

	if (gift < 0) gift = 0;
	client->donation = gift;

	if(client->userid == -1)
		return;

	else if(client->userid == 0 && strlen(client->username) >= MIN_ADDRESS_LEN)
	{
		db_query(db, "INSERT INTO accounts (username, coinsymbol, balance, donation, hostaddr) values ('%s', '%s', 0, %d, '%s')",
			client->username, symbol.c_str(), gift, client->sock->ip);
		client->userid = (int)mysql_insert_id(&db->mysql);
	}

	else {
		db_query(db, "UPDATE accounts SET coinsymbol='%s', swap_time=%u, donation=%d, hostaddr='%s' WHERE id=%d AND balance = 0"
			" AND (SELECT COUNT(id) FROM payouts WHERE account_id=%d AND tx IS NULL) = 0" // failed balance
			" AND IFNULL((SELECT pending FROM balanceuser WHERE userid=%d ORDER by time DESC LIMIT 1), 0) = 0" // pending balance
			, symbol.c_str(), (uint) time(NULL), gift, client->sock->ip, client->userid, client->userid, client->userid);
		if (mysql_affected_rows(&db->mysql) > 0 && (symbol.size() > 0)) {
			debuglog("%s: %s coinsymbol set to %s ip %s uid (%d)\n",
				g_current_algo->name, client->username, symbol.c_str(), client->sock->ip, client->userid);
		}
	}
}

//////////////////////////////////////////////////////////////////////////////////////

void db_clear_worker(YAAMP_DB *db, YAAMP_CLIENT *client)
{
	if(!client->workerid)
		return;

	db_query(db, "DELETE FROM workers WHERE id=%d", client->workerid);
	client->workerid = 0;
}

void db_add_worker(YAAMP_DB *db, YAAMP_CLIENT *client)
{
	char password[128] = { 0 };
	char version[128] = { 0 };
	char worker[128] = { 0 };
	int now = time(NULL);

	db_clear_worker(db, client);

	db_check_user_input(client->username);
	db_check_user_input(client->version);
	db_check_user_input(client->password);
	db_check_user_input(client->worker);

	// strip for recent mysql defaults (error if fields are too long)
	if (strlen(client->password) > 64)
		clientlog(client, "password too long truncated: %s", client->password);
	if (strlen(client->version) > 64)
		clientlog(client, "version too long truncated: %s", client->version);
	if (strlen(client->worker) > 64)
		clientlog(client, "worker too long truncated: %s", client->worker);

	strncpy(password, client->password, 64);
	strncpy(version, client->version, 64);
	strncpy(worker, client->worker, 64);

	db_query(db, "INSERT INTO workers (userid, ip, name, difficulty, version, password, worker, algo, tls, time, pid) "\
		"VALUES (%d, '%s', '%s', %f, '%s', '%s', '%s', '%s', %d, %d, %d)",
		client->userid, client->sock->ip, client->username, client->difficulty_actual,
		version, password, worker, g_stratum_algo, client->tls ? 1 : 0, now, getpid());

	client->workerid = (int)mysql_insert_id(&db->mysql);
}

void db_update_workers(YAAMP_DB *db)
{
	g_list_client.Enter();
	for(CLI li = g_list_client.first; li; li = li->next)
	{
		YAAMP_CLIENT *client = (YAAMP_CLIENT *)li->data;
		if(client->deleted) continue;
		if(!client->workerid) continue;

		// Low-difficulty coins need far longer between shares, so the rough
		// speed estimate stays below YAAMP_CLIENT_MINSPEED and would kick a
		// healthy miner (and his reconnect inherits the same low estimate).
		// NEB shares are even rarer (one every ~10^10 hashes here) and its
		// speed is not measured by yiimp's generic share formula at all.
		if(!is_nebhash && !client_low_difficulty_algo() && client->speed < YAAMP_CLIENT_MINSPEED)
		{
			clientlog(client, "speed %f", client->speed);
			shutdown(client->sock->sock, SHUT_RDWR);
			db_clear_worker(db, client);
			object_delete(client);
			continue;
		}

		// Idle NEB sessions still need vardiff: a miner that never finds a
		// share cannot trigger the submit-driven adjustment itself.
		if(is_nebhash && nebhash_vardiff_tick(client))
			job_send_last(client);

		if (is_nebhash) {
			// Decay the 600s share-work window every second, even between
			// shares, so the job log never freezes a lucky burst on screen.
			nebhash_client_speed(client);
			if (client->neb_last_share_time &&
				(long long) time(NULL) - client->neb_last_share_time > 600)
				client->speed = 0;
		} else {
			client->speed *= 0.8;
		}
		if(client->difficulty_written == client->difficulty_actual) continue;

		db_query(db, "UPDATE workers SET difficulty=%f, subscribe=%d, tls=%d WHERE id=%d",
			client->difficulty_actual, client->extranonce_subscribe,
			client->tls ? 1 : 0, client->workerid);
		client->difficulty_written = client->difficulty_actual;
	}

	//client_sort();
	g_list_client.Leave();
}

void db_init_user_coinid(YAAMP_DB *db, YAAMP_CLIENT *client)
{
	if (!client->userid)
		return;

	if (!client->coinid)
		db_query(db, "UPDATE accounts SET coinid=NULL WHERE id=%d", client->userid);
	else
		db_query(db, "UPDATE accounts SET coinid=%d WHERE id=%d AND IFNULL(coinid,0) = 0",
			client->coinid, client->userid);
}

