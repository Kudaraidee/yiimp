
#include "stratum.h"
#include "protocol/nebhash/work.h"

//void check_job(YAAMP_JOB *job)
//{
//	if(job->coind && job->remote)
//	{
//		debuglog("error memory\n");
//	}
//}

static YAAMP_WORKER *share_find_worker(YAAMP_CLIENT *client, YAAMP_JOB *job, bool valid, int height, int coinid)
{
	int current_coinid = coinid;

	if ((!current_coinid) && (job) && (job->coind)) {
		current_coinid =  job->coind->id;
	}

	for(CLI li = g_list_worker.first; li; li = li->next)
	{
		YAAMP_WORKER *worker = (YAAMP_WORKER *)li->data;
		if(worker->deleted) continue;

		if(	worker->userid == client->userid &&
			worker->workerid == client->workerid &&
			worker->valid == valid)
		{
			if(!job && !worker->coinid && !worker->remoteid)
				return worker;

			else if(!job)
				continue;

			else if(((worker->coinid == current_coinid) && (worker->height == height)) ||
				(job->remote && worker->remoteid == job->remote->id))
				return worker;
		}
	}

	return NULL;
}

static void share_add_worker(YAAMP_CLIENT *client, YAAMP_JOB *job, bool valid, char *ntime, double share_diff, int error_number, int height, int coinid, bool isaux)
{
//	check_job(job);
	g_list_worker.Enter();

	YAAMP_WORKER *worker = share_find_worker(client, job, valid, height, coinid);
	if(!worker)
	{
		worker = new YAAMP_WORKER();

		worker->userid = client->userid;
		worker->workerid = client->workerid;
		worker->coinid = coinid;
		worker->remoteid = job? (job->remote? job->remote->id: 0): 0;
		worker->valid = valid;
		worker->height = height;
		worker->error_number = error_number;
		sscanf(ntime, "%x", &worker->ntime);
		worker->share_diff = share_diff;

		if(g_stratum_reconnect)
			worker->extranonce1 = !client->reconnecting && (client->reconnectable || client->extranonce_subscribe);
		else
			worker->extranonce1 = client->extranonce_subscribe;

		g_list_worker.AddTail(worker);
	}

	if(valid)
	{
		// set by the submit from the share's job (see client_share_difficulty)
		double difficulty = client->share_difficulty > 0 ? client->share_difficulty : client->difficulty_actual;
		worker->difficulty += difficulty / g_current_algo->diff_multiplier;
		if (!isaux) {
			if (is_nebhash) {
				// Use the share's expected work (target difficulty) in a 600s
				// exponential window. A single interval made the log jump to
				// 84 Mh/s, while the heavy-tailed ticket score read 18 Mh/s.
				nebhash_client_note_share_speed(client, client->difficulty_actual);
			} else {
				client_speed_add(client, difficulty);
			}
		}
		// log best share_diff
		if (worker->share_diff < share_diff) worker->share_diff = share_diff;
	//	client->source->speed += client->difficulty_actual / g_current_algo->diff_multiplier * 42;
	}

	worker->solo = client->solo;

	g_list_worker.Leave();
}

/////////////////////////////////////////////////////////////////////////

void share_add(YAAMP_CLIENT *client, YAAMP_JOB *job, bool valid, char *extranonce2, char *ntime, char *nonce, double share_diff, int error_number, int height)
{
	int current_coinid = 0;
	g_shares_counter++;
	if ((job) && (job->coind)) {
		current_coinid =  job->coind->id;
	}
	share_add_worker(client, job, valid, ntime, share_diff, error_number, height, current_coinid, false);
	// add valid aux shares
	if ((!error_number) && (job)) {
		YAAMP_JOB_TEMPLATE *templ = job->templ;
		int auxcoinid = 0;
		int auxcoinheight = 0;
		if ((templ) && (templ->auxs_size>0)) {
			for(int i=0; i<templ->auxs_size; i++) {
				if ((!templ->auxs[i]) || (!templ->auxs[i]->coind)) continue;
				auxcoinid = templ->auxs[i]->coind->id;
				auxcoinheight = templ->auxs[i]->coind->height;

				share_add_worker(client, job, valid, ntime, share_diff, error_number, auxcoinheight, auxcoinid, true);
			}
		}
	}

	YAAMP_SHARE *share = new YAAMP_SHARE();

	share->jobid = job? job->id: 0;
	STRSET(share->extranonce2, extranonce2);
	STRSET(share->ntime, ntime);
	STRSET(share->nonce, nonce);
	STRSET(share->nonce1, client->extranonce1);

	g_list_share.AddHead(share);
}

YAAMP_SHARE *share_find(int jobid, char *extranonce2, char *ntime, char *nonce, char *nonce1)
{
	g_list_share.Enter();
	for(CLI li = g_list_share.first; li; li = li->next)
	{
		YAAMP_SHARE *share = (YAAMP_SHARE *)li->data;
		if(share->deleted) continue;

		if(	share->jobid == jobid &&
			!strcmp(share->extranonce2, extranonce2) && !strcmp(share->ntime, ntime) &&
			!strcmp(share->nonce, nonce) && !strcmp(share->nonce1, nonce1))
		{
			g_list_share.Leave();
			return share;
		}
	}

	g_list_share.Leave();
	return NULL;
}

void share_write(YAAMP_DB *db)
{
	int pid = getpid();
	int count = 0;
	int now = time(NULL);

	char buffer[1024*1024] = "insert into shares (userid, workerid, coinid, jobid, pid, valid, extranonce1, difficulty, share_diff, time, algo, error, solo, blocknumber) values ";
	g_list_worker.Enter();

	for(CLI li = g_list_worker.first; li; li = li->next)
	{
		YAAMP_WORKER *worker = (YAAMP_WORKER *)li->data;
		if(worker->deleted) continue;

		if(!worker->workerid) {
			object_delete(worker);
			continue;
		}

		if(count) strcat(buffer, ",");
		sprintf(buffer+strlen(buffer), "(%d, %d, %d, %d, %d, %d, %d, %f, %f, %d, '%s', %d, %d, %d)",
			worker->userid, worker->workerid, worker->coinid, worker->remoteid, pid,
			worker->valid, worker->extranonce1, worker->difficulty, worker->share_diff, now, g_stratum_algo, worker->error_number, worker->solo, worker->height);

		// todo: link max_ttf ?
		if((now - worker->ntime) > 15*60 || worker->ntime > now) {
			debuglog("ntime warning: value %d (%08x) offset %d secs from uid %d\n", worker->ntime, worker->ntime, (now - worker->ntime), worker->userid);
		}

		if(++count >= 1000)
		{
			db_query(db, buffer);

			strcpy(buffer, "insert into shares (userid, workerid, coinid, jobid, pid, valid, extranonce1, difficulty, share_diff, time, algo, error, solo, blocknumber) values ");
			count = 0;
		}

		object_delete(worker);
	}

	g_list_worker.Leave();
	if(count) db_query(db, buffer);
}

void share_prune(YAAMP_DB *db)
{
	g_list_share.Enter();
	for(CLI li = g_list_share.first; li; li = li->next)
	{
		YAAMP_SHARE *share = (YAAMP_SHARE *)li->data;
		if(share->deleted) continue;

		YAAMP_JOB *job = (YAAMP_JOB *)object_find(&g_list_job, share->jobid);
		if(job) continue;

		object_delete(share);
	}

	g_list_share.Leave();
}

/////////////////////////////////////////////////////////////////////////////////////////////////////////////

void block_prune(YAAMP_DB *db)
{
	int count = 0;
	char buffer[128*1024] = "insert into blocks (height, blockhash, coin_id, userid, workerid, category, difficulty, difficulty_user, time, algo, segwit,solo) values ";

	g_list_block.Enter();
	for(CLI li = g_list_block.first; li; li = li->next)
	{
		YAAMP_BLOCK *block = (YAAMP_BLOCK *)li->data;
		if(!block->confirmed)
		{
			int elapsed = 30;
			// slow block time...
			if(g_stratum_algo && !strcmp(g_stratum_algo, "decred")) elapsed = 60 * 15; // 15mn

			if((block->created + elapsed) < time(NULL))
				object_delete(block);

			continue;
		}

		if(count) strcat(buffer, ",");
		sprintf(buffer+strlen(buffer), "(%d, '%s', %d, %d, %d, 'new', %f, %f, %d, '%s', %d, %d)",
			block->height, block->hash, block->coinid, block->userid, block->workerid,
			block->difficulty, block->difficulty_user, (int)block->created, g_stratum_algo, block->segwit?1:0, block->solo?1:0);

		object_delete(block);
		count++;
	}

	g_list_block.Leave();
	if(count) db_query(db, buffer);
}

void block_add(int userid, int workerid, int coinid, int height, double diff, double diff_user, const char *h1, const char *h2, int segwit, bool solo)
{
	YAAMP_BLOCK *block = new YAAMP_BLOCK();

	block->created = time(NULL);
	block->userid = userid;
	block->workerid = workerid;
	block->coinid = coinid;
	block->height = height;
	block->difficulty = diff;
	block->difficulty_user = diff_user;
	block->segwit = segwit;
	block->solo = solo;

	STRSET(block->hash1, h1);
	STRSET(block->hash2, h2);

	g_list_block.AddTail(block);
}

// called from blocknotify tool
bool block_confirm(int coinid, const char *blockhash)
{
	char hash[192];
	if(strlen(blockhash) < 64) return false;

	snprintf(hash, 161, "%s", blockhash);

	// required for multi algos wallets where pow hash is not the blockhash
	g_list_coind.Enter();
	for(CLI li = g_list_coind.first; li ; li = li->next)
	{
		YAAMP_COIND *coind = (YAAMP_COIND *)li->data;
		if(coind->id != coinid || coind->deleted) continue;

		if(coind->multialgos) {
			char params[192];
			STRFMT(params, "[\"%s\"]", blockhash);
			json_value *json = rpc_call(&coind->rpc, "getblock", params);
			if(!json) {
				debuglog("%s: error getblock, no answer\n", __func__);
				break;
			}
			json_value *json_res = json_get_object(json, "result");
			if(!json_res) {
				debuglog("%s: error getblock, no result\n", __func__);
				break;
			}
			const char *h1 = json_get_string(json_res, "pow_hash"); // DGB, MYR, J
			const char *h2 = json_get_string(json_res, "mined_hash"); // XVG
			const char *h3 = json_get_string(json_res, "phash"); // XSH
			if (h1) snprintf(hash, 161, "%s", h1);
			else if (h2) snprintf(hash, 161, "%s", h2);
			else if (h3) snprintf(hash, 161, "%s", h3);
			//debuglog("%s: getblock %s -> pow %s\n", __func__, blockhash, hash);
			json_value_free(json);
			break;
		} else if (strcmp(coind->symbol,"ORB") == 0) {
			char params[192];
			STRFMT(params, "[\"%s\"]", blockhash);
			json_value *json = rpc_call(&coind->rpc, "getblock", params);
			if(!json) {
				debuglog("%s: error getblock, no answer\n", __func__);
				break;
			}
			json_value *json_res = json_get_object(json, "result");
			if(!json_res) {
				debuglog("%s: error getblock, no result\n", __func__);
				break;
			}
			const char *h = json_get_string(json_res, "proofhash");
			if (h) snprintf(hash, 161, "%s", h);
			json_value_free(json);
			break;
		}
	}
	g_list_coind.Leave();

	for(CLI li = g_list_block.first; li; li = li->next)
	{
		YAAMP_BLOCK *block = (YAAMP_BLOCK *)li->data;
		if(block->coinid == coinid && !block->deleted)
		{
			if(strcmp(block->hash1, hash) && strcmp(block->hash2, hash)) continue;
			if (!block->confirmed) {
				debuglog("*** CONFIRMED %d : %s\n", block->height, block->hash2);
				// Ethereum block hashes are 0x-prefixed (66 chars); do not truncate.
				snprintf(block->hash, sizeof(block->hash), "%s", blockhash);
				block->confirmed = true;
			}
			return true;
		}
	}
	return false;
}

//////////////////////////////////////////////////////////////////////////////////////////

YAAMP_SUBMIT *submit_add(int remoteid, double difficulty)
{
	YAAMP_SUBMIT *submit = new YAAMP_SUBMIT();

	submit->created = time(NULL);
	submit->valid = true;
	submit->remoteid = remoteid;
	submit->difficulty = difficulty / g_current_algo->diff_multiplier;

	g_list_submit.AddTail(submit);
	return submit;
}

void submit_prune(YAAMP_DB *db)
{
	int count = 0;
	char buffer[128*1024] = "insert into jobsubmits (jobid, valid, difficulty, time, algo, status) values ";

	g_list_submit.Enter();
	for(CLI li = g_list_submit.first; li; li = li->next)
	{
		YAAMP_SUBMIT *submit = (YAAMP_SUBMIT *)li->data;

		if(count) strcat(buffer, ",");
		sprintf(buffer+strlen(buffer), "(%d, %d, %f, %d, '%s', 0)", submit->remoteid, submit->valid,
			submit->difficulty, (int)submit->created, g_stratum_algo);

		if(++count >= 1000)
		{
			db_query(db, buffer);

			strcpy(buffer, "insert into jobsubmits (jobid, valid, difficulty, time, algo, status) values ");
			count = 0;
		}

		object_delete(submit);
	}

	g_list_submit.Leave();
	if(count) db_query(db, buffer);
}


