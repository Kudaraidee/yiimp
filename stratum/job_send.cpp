
#include "stratum.h"
#include "humanize_number.h"

static int g_job_next_id = 0;

int job_get_jobid()
{
	CommonLock(&g_job_create_mutex);
	int jobid = ++g_job_next_id;

	CommonUnlock(&g_job_create_mutex);
	return jobid;
}

// Track last prevhash to detect new blocks
static char g_last_prevhash[512] = "";

static void job_mining_notify_buffer(YAAMP_JOB *job, char *buffer)
{
	YAAMP_JOB_TEMPLATE *templ = job->templ;
	
	// Check if this is a new block (prevhash changed)
	bool clean_jobs = (strcmp(g_last_prevhash, templ->prevhash_be) != 0);
	if (clean_jobs) {
		strncpy(g_last_prevhash, templ->prevhash_be, sizeof(g_last_prevhash) - 1);
		g_last_prevhash[sizeof(g_last_prevhash) - 1] = '\0';
	}

	if (!strcmp(g_stratum_algo, "lbry")) {
		sprintf(buffer, "{\"id\":null,\"method\":\"mining.notify\",\"params\":["
			"\"%x\",\"%s\",\"%s\",\"%s\",\"%s\",[%s],\"%s\",\"%s\",\"%s\",%s]}\n",
			job->id, templ->prevhash_be, templ->claim_be, templ->coinb1, templ->coinb2,
			templ->txmerkles, templ->version, templ->nbits, templ->ntime, clean_jobs ? "true" : "false");
		return;
	} else if (strlen(templ->extradata_hex) == 128) {
		// LUX smart contract state hashes (like lbry extra field, here the 2 root hashes in one)
		sprintf(buffer, "{\"id\":null,\"method\":\"mining.notify\",\"params\":["
			"\"%x\",\"%s\",\"%s\",\"%s\",\"%s\",[%s],\"%s\",\"%s\",\"%s\",%s]}\n",
			job->id, templ->prevhash_be, templ->extradata_be, templ->coinb1, templ->coinb2,
			templ->txmerkles, templ->version, templ->nbits, templ->ntime, clean_jobs ? "true" : "false");
		return;
	} else if (!strcmp(g_stratum_algo,"neoscrypt-xaya")) {
		sprintf(buffer, "{\"id\":null,\"method\":\"mining.notify\",\"params\":["
				"\"%x\",\"%s\",\"%s\",\"%s\",[%s],\"%s\",\"%s\",\"%s\",%s]}\n",
			job->id, "0000000000000000000000000000000000000000000000000000000000000000", templ->xaya_header,
			"", "", "20000000", templ->nbits, templ->ntime, clean_jobs ? "true" : "false");
		return;
	}

	if (strstr(g_current_algo->name, "equihash") == g_current_algo->name)
	{
		static char version_reversed[1024];
		static char prev_hash_reversed[1024];
		static char merkleroot_reversed[1024];
		static char finalsaplingroot_reversed[1024];
		static char time_reversed[1024];
		static char bits_reversed[1024];

		static char equihash_params[1024];
		static char equihash_personalization[1024];

		sprintf(equihash_params, "%i_%i", g_equihash_wn, g_equihash_wk);

		strcpy(equihash_personalization,
				((strlen(job->coind->personalization)>0)?job->coind->personalization:"ZcashPoW"));

		string_be(templ->version, version_reversed);
		string_be(templ->prevhash_hex, prev_hash_reversed);
//		string_be(templ->merkleroot, merkleroot_reversed);
		sprintf(merkleroot_reversed, "%s", templ->merkleroot);
		string_be(templ->saplingroothash, finalsaplingroot_reversed);
		string_be(templ->ntime, time_reversed);
		string_be(templ->nbits, bits_reversed);


		char job_message[1024];
		sprintf(job_message,"{\"id\":null,\"method\":\"client.show_message\",\"params\":[\"equihash %s block %i\"]}\n",
				(job->coind)?"unknown":job->coind->symbol,
				templ->height);

		//[2017-12-07 13:53:12] < {"id":null,"method":"client.show_message","params":["equihash KMD block 611840"]}

		sprintf(buffer, "%s{\"id\":null,\"method\":\"mining.notify\",\"params\":["
				"\"%x\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",%s,\"%s\",\"%s\"]}\n", job_message,
				job->id, version_reversed, prev_hash_reversed, merkleroot_reversed,
				finalsaplingroot_reversed, time_reversed, bits_reversed,
				clean_jobs ? "true" : "false", equihash_params, equihash_personalization);
		return;
	}

	// standard stratum
	sprintf(buffer, "{\"id\":null,\"method\":\"mining.notify\",\"params\":[\"%x\",\"%s\",\"%s\",\"%s\",[%s],\"%s\",\"%s\",\"%s\",%s]}\n",
		job->id, templ->prevhash_be, templ->coinb1, templ->coinb2, templ->txmerkles, templ->version, templ->nbits, templ->ntime,
		clean_jobs ? "true" : "false");
}

static YAAMP_JOB *job_get_last(int coinid)
{
	g_list_job.Enter();
	for(CLI li = g_list_job.first; li; li = li->prev)
	{
		YAAMP_JOB *job = (YAAMP_JOB *)li->data;
		if(!job_can_mine(job)) continue;
		if(!job->coind) continue;
		if(coinid > 0 && job->coind->id != coinid) continue;

		g_list_job.Leave();
		return job;
	}

	g_list_job.Leave();
	return NULL;
}

////////////////////////////////////////////////////////////////////////////////////////////////////////

void job_send_last(YAAMP_CLIENT *client)
{
	YAAMP_JOB *job = NULL;

	if (!g_autoexchange)
	{
		// prefer user coin first (if available)
		job = job_get_last(client->coinid);
	}

	if(!job) job = job_get_last(0);

	if(!job) return;

	YAAMP_JOB_TEMPLATE *templ = job->templ;
	client->jobid_sent = job->id;

	char buffer[YAAMP_SMALLBUFSIZE];
	if (is_kawpow || is_firopow || is_phihash)
	{
		kawpow_job_mining_notify_buffer(job, client, buffer);
	}
	else {
		job_mining_notify_buffer(job, buffer);
	}

	socket_send_raw(client->sock, buffer, strlen(buffer));
}

void job_send_jobid(YAAMP_CLIENT *client, int jobid)
{
	YAAMP_JOB *job = (YAAMP_JOB *)object_find(&g_list_job, jobid, true);
	if(!job)
	{
		job_send_last(client);
		return;
	}

	char buffer[YAAMP_SMALLBUFSIZE];
	if (is_kawpow || is_firopow || is_phihash)
	{
		kawpow_job_mining_notify_buffer(job, client, buffer);
	}
	else {
		job_mining_notify_buffer(job, buffer);
	}

	YAAMP_JOB_TEMPLATE *templ = job->templ;
	client->jobid_sent = job->id;

	socket_send_raw(client->sock, buffer, strlen(buffer));
	object_unlock(job);
}

////////////////////////////////////////////////////////////////////////////////////////////////////////

void job_broadcast(YAAMP_JOB *job)
{
	char formated_jobspeed[64];
	char formated_coinspeed[64];
	int s1 = current_timestamp_dms();
	int count = 0;
	struct timeval timeout;
	timeout.tv_sec = 0;
	timeout.tv_usec = 100000; // max time to push to a socket (very fast)

	YAAMP_JOB_TEMPLATE *templ = job->templ;

	g_list_client.Enter();
	for(CLI li = g_list_client.first; li; li = li->next)
	{
		YAAMP_CLIENT *client = (YAAMP_CLIENT *)li->data;
		if(client->deleted) continue;
		if(!client->sock) continue;
	//	if(client->reconnecting && client->locked) continue;

		if(client->jobid_next != job->id) continue;
		if(client->jobid_sent == job->id) continue;

		char buffer[YAAMP_SMALLBUFSIZE];
		memset(buffer, 0, sizeof(buffer));
		if (is_kawpow || is_firopow || is_phihash)
		{
			kawpow_job_mining_notify_buffer(job, client, buffer);
		}
		else {
			job_mining_notify_buffer(job, buffer);
		}
	
		client->jobid_sent = job->id;
		client_add_job_history(client, job->id);

		client_adjust_difficulty(client);

		setsockopt(client->sock->sock, SOL_SOCKET, SO_SNDTIMEO, &timeout, sizeof(timeout));

		if (socket_send_raw(client->sock, buffer, strlen(buffer)) == -1) {
			int err = errno;
			client->broadcast_timeouts++;
			// too much timeouts, disconnect him
			if (client->broadcast_timeouts >= 3) {
				shutdown(client->sock->sock, SHUT_RDWR);
				clientlog(client, "unable to send job, sock err %d (%d times)", err, client->broadcast_timeouts);
				if(client->workerid && !client->reconnecting) {
				//	CommonLock(&g_db_mutex);
					db_clear_worker(g_db, client);
				//	CommonUnlock(&g_db_mutex);
				}
				object_delete(client);
			}
		}
		count++;
	}

	g_list_client.Leave();
	g_last_broadcasted = time(NULL);

	int s2 = current_timestamp_dms();
	if(!count) return;

	///////////////////////

	bool is_equihash = (strstr(g_current_algo->name, "equihash") == g_current_algo->name);

	uint64_t coin_target = decode_compact(templ->nbits, (is_equihash)? 19 : 25);

	if (templ->nbits && !coin_target) coin_target = 0xFFFF000000000000ULL; // under decode_compact min diff
	double coin_diff = target_to_diff(coin_target);

	humanize_double(formated_jobspeed, sizeof("-XXX.YPh/s"), job->speed, "h/s",
					HN_AUTOSCALE, HN_NOSPACE | HN_DECIMAL);
	humanize_double(formated_coinspeed, sizeof("-XXX.YPh/s"), job->maxspeed, "h/s",
					HN_AUTOSCALE, HN_NOSPACE | HN_DECIMAL);

	debuglog("%s %d - diff %.9f job %x to %d/%d/%d clients, hash %s / %s in %.1f ms\n", job->name,
		templ->height, coin_diff, job->id, count, job->count, g_list_client.count, formated_jobspeed, formated_coinspeed, 0.1*(s2-s1));

//	for(int i=0; i<templ->auxs_size; i++)
//	{
//		if(!templ->auxs[i]) continue;
//		YAAMP_COIND *coind_aux = templ->auxs[i]->coind;
//
//		unsigned char target_aux[1024];
//		binlify(target_aux, coind_aux->aux.target);
//
//		uint64_t coin_target = get_hash_difficulty(target_aux);
//		double coin_diff = target_to_diff(coin_target);
//
//		debuglog("%s %d - diff %.9f chainid %d [%d]\n", coind_aux->symbol, coind_aux->height, coin_diff,
//				coind_aux->aux.chainid, coind_aux->aux.index);
//	}

}







//	double maxhash = 0;
//	if(job->remote)
//	{
//		sprintf(name, "JOB%d%s (%.3f)", job->remote->id, job->remote->nonce2size == 2? "*": "", job->remote->speed_avg);
//		maxhash = job->remote->speed;
//	}
//	else
//	{
//		strcpy(name, job->coind->symbol);
//		for(int i=0; i<templ->auxs_size; i++)
//		{
//			if(!templ->auxs[i]) continue;
//			YAAMP_COIND *coind_aux = templ->auxs[i]->coind;
//
//			sprintf(name_auxs+strlen(name_auxs), ", %s %d", coind_aux->symbol, templ->auxs[i]->height);
//		}
//
//		maxhash = coind_nethash(job->coind)*coind_profitability(job->coind)/(g_current_algo->profit? g_current_algo->profit: 1);
//	}

