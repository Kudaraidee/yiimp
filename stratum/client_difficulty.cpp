
#include "stratum.h"

double client_normalize_difficulty(double difficulty, YAAMP_CLIENT *client)
{
	if (client->is_nicehash) {
		if(difficulty < g_stratum_nicehash_min_diff) difficulty = g_stratum_nicehash_min_diff;
		else if(difficulty < 1) difficulty = floor(difficulty*1000/2)/1000*2;
		else if(difficulty > 1) difficulty = std::max(1.0, floor(difficulty/2)*2);	// 1..2 must not become 0
		if(difficulty > g_stratum_nicehash_max_diff) difficulty = g_stratum_nicehash_max_diff;
	}
	else {
		if(difficulty < g_stratum_min_diff) difficulty = g_stratum_min_diff;
		else if(difficulty < 1) difficulty = floor(difficulty*1000/2)/1000*2;
		else if(difficulty > 1) difficulty = std::max(1.0, floor(difficulty/2)*2);	// 1..2 must not become 0
		if(difficulty > g_stratum_max_diff) difficulty = g_stratum_max_diff;
	}
	return difficulty;
}

// resend generation of a submitted job id = its leading zeros (see job_notify_id)
int client_jobid_gen(const char *jobid)
{
	int gen = 0;
	while(jobid[gen] == '0' && jobid[gen+1]) gen++;
	return gen;
}

// The miner applies a new difficulty with the next job it receives, so shares on
// jobs sent up to the change were mined at the previous difficulty. A job resent
// after the change (generation gen) counts at the new one.
double client_share_difficulty(YAAMP_CLIENT *client, YAAMP_JOB *job, int gen)
{
	if(!job || client->difficulty_prev <= 0 || job->id > client->difficulty_prev_jobid)
		return client->difficulty_actual;

	if(client->difficulty_new_gen && job->id == client->difficulty_prev_jobid && gen >= client->difficulty_new_gen)
		return client->difficulty_actual;

	return client->difficulty_prev;
}

void client_change_difficulty(YAAMP_CLIENT *client, double difficulty)
{
	if(difficulty <= 0) return;

	difficulty = client_normalize_difficulty(difficulty, client);
	if(difficulty <= 0) return;

	if(difficulty == client->difficulty_actual) return;

	// several changes before the next job: the miner still works at the first one
	// (after a resend the miner already works at the current one)
	if(client->difficulty_prev_jobid != client->jobid_sent || client->difficulty_prev <= 0 || client->difficulty_new_gen)
	{
		client->difficulty_prev = client->difficulty_actual;
		client->difficulty_prev_jobid = client->jobid_sent;
		client->difficulty_new_gen = 0;
		client->difficulty_confirmed = false;
	}

	client->difficulty_actual = difficulty;
	client->difficulty_changed = time(NULL);
	client_send_difficulty(client, difficulty);
}

////////////////////////////////////////////////////////////////////////////////
// vardiff: keep the share rate inside [spm_min, spm_max], retarget to the middle.
// The rate at the current difficulty comes from the window's work (speed), so
// shares found at an earlier difficulty still count with their own weight.

// shares per minute a speed (h/s) gives at the current difficulty
static double client_spm(YAAMP_CLIENT *client, double speed)
{
	double share_hashes = client->difficulty_actual / g_current_algo->diff_multiplier * 4294967296.0;
	if(share_hashes <= 0) return 0;
	return speed * 60 / share_hashes;
}

static double client_spm(YAAMP_CLIENT *client)
{
	return client_spm(client, client->speed);
}

// a change reaches the miner with the next job or a resend of the current one
static bool client_vardiff_undelivered(YAAMP_CLIENT *client)
{
	return client->difficulty_prev > 0 && client->jobid_sent <= client->difficulty_prev_jobid
		&& !client->difficulty_new_gen;
}

// spam waits further, until a share at the new difficulty came back: shares
// still in flight from before the resend must not trigger another step
static bool client_vardiff_pending(YAAMP_CLIENT *client)
{
	if(client_vardiff_undelivered(client)) return true;

	return client->difficulty_new_gen && !client->difficulty_confirmed
		&& client->jobid_sent <= client->difficulty_prev_jobid;
}

// resend the current job under a new id (more leading zeros): the miner
// switches to it, and to the new difficulty, right away
void client_vardiff_resend(YAAMP_CLIENT *client, YAAMP_JOB *job)
{
	if(!job || job->id != client->jobid_sent) return;

	if(client->resend_jobid != job->id)
	{
		client->resend_jobid = job->id;
		client->resend_gen = 0;
	}
	if(client->resend_gen >= YAAMP_RESEND_MAX) return;

	int gen = ++client->resend_gen;
	client->difficulty_new_gen = gen;
	client->difficulty_confirmed = false;
	job_resend(client, job, gen);
}

static void client_vardiff_apply(YAAMP_CLIENT *client, const char *trigger, double spm, double factor)
{
	if(g_debuglog_client)
		debuglog("vardiff %s %s: %d shares (%.1f eff) in %d s, %.2f spm at diff %g, x%.2f\n",
			trigger, client->sock->ip, client->speed_shares, client->speed_neff, client->speed_span,
			spm, client->difficulty_actual, factor);

	client_change_difficulty(client, client->difficulty_actual * factor);
}

// band check, on each job sent to the client
void client_adjust_difficulty(YAAMP_CLIENT *client)
{
	if(client->difficulty_remote) {
		client_change_difficulty(client, client->difficulty_remote);
		return;
	}

	if(client_vardiff_undelivered(client)) return;

	client_speed_update(client);
	double n = client->speed_neff;
	if(n < YAAMP_VARDIFF_MIN_SHARES) return;	// silence handles slow clients

	// the rate estimate is only good to about 1/sqrt(n): widen the band by that
	double tolerance = 1 / sqrt(n);
	double spm = client_spm(client);
	if(spm >= g_stratum_spm_min * (1 - tolerance) && spm <= g_stratum_spm_max * (1 + tolerance)) return;

	double spm_target = (g_stratum_spm_min + g_stratum_spm_max) / 2;
	// damped: move halfway (in log scale) toward the target, so a lucky or unlucky
	// run shifts the difficulty less; a real change converges over a few jobs
	double factor = std::min(std::max(sqrt(spm / spm_target), 0.5), 2.0);
	client_vardiff_apply(client, "band", spm, factor);
}

// share spam, on each valid share; judged on the last minute so a long
// speed window does not delay it
void client_vardiff_share(YAAMP_CLIENT *client, YAAMP_JOB *job, int gen)
{
	// a share from the resent job: the miner works at the new difficulty now
	if(client->difficulty_new_gen && job && job->id == client->difficulty_prev_jobid && gen >= client->difficulty_new_gen)
		client->difficulty_confirmed = true;

	if(client->difficulty_remote) return;
	if(client_vardiff_pending(client)) return;
	if(client->speed_short_shares < YAAMP_VARDIFF_MIN_SHARES) return;

	double spm = client_spm(client, client->speed_short);
	if(spm <= g_stratum_spm_spam) return;

	double spm_target = (g_stratum_spm_min + g_stratum_spm_max) / 2;
	double factor = std::min(spm / spm_target, 16.0);
	double before = client->difficulty_actual;
	client_vardiff_apply(client, "spam", spm, factor);

	// don't let the flood go on until the next job
	if(client->difficulty_actual != before)
		client_vardiff_resend(client, job);
}

// silence can't go lower: the difficulty is at diff_min (after rounding)
bool client_vardiff_at_floor(YAAMP_CLIENT *client)
{
	return client_normalize_difficulty(client->difficulty_actual * 0.5, client) >= client->difficulty_actual;
}

// A connection that never delivers shares: silence first lowers the difficulty
// to the floor; no valid share for idle_timeout after that (or 3x idle_timeout
// in any case, e.g. remote difficulty) means it's not mining.
bool client_idle(YAAMP_CLIENT *client, int *idle)
{
	int now = time(NULL);
	int last = std::max(client->last_share, client->speed_since);
	*idle = now - last;

	if(*idle > 3 * g_stratum_idle_timeout) return true;
	if(!client_vardiff_at_floor(client)) return false;

	return now - std::max(last, client->difficulty_changed) > g_stratum_idle_timeout;
}

// share silence, from the job thread (job_update) with the client's current job
void client_vardiff_silence(YAAMP_CLIENT *client, YAAMP_JOB *job)
{
	if(client->difficulty_remote) return;
	if(client_vardiff_undelivered(client)) return;
	if(client_vardiff_at_floor(client)) return;	// idle handling (client_idle) takes over

	int since = std::max(std::max(client->last_share, client->difficulty_changed), client->speed_since);
	if(time(NULL) - since <= g_stratum_share_timeout) return;

	double before = client->difficulty_actual;
	client_vardiff_apply(client, "silence", client_spm(client), 0.5);

	// a too high difficulty must not wait for the next block
	if(client->difficulty_actual != before)
		client_vardiff_resend(client, job);
}

////////////////////////////////////////////////////////////////////////////////

int client_send_difficulty(YAAMP_CLIENT *client, double difficulty)
{
//	debuglog("%s diff %f\n", client->sock->ip, difficulty);

	bool is_equihash = (strstr(g_current_algo->name, "equihash") == g_current_algo->name);
	if (is_kawpow || is_firopow || is_phihash || is_meowpow)
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

// d=<diff> in the password sets the starting difficulty, vardiff takes over from there
void client_initialize_difficulty(YAAMP_CLIENT *client)
{
	char *p = strstr(client->password, "d=");
	char *p2 = strstr(client->password, "decred=");
	if(!p || p2) return;

	if (is_kawpow || is_firopow || is_phihash || is_meowpow)
	{
		client->difficulty_actual = g_stratum_difficulty;
		diff_to_target(client->share_target, client->difficulty_actual);
	}
	else {
		double diff = client_normalize_difficulty(atof(p+2), client);
		uint64_t user_target = diff_to_target(diff);

	//	debuglog("%016llx target\n", user_target);
		if(user_target >= YAAMP_MINDIFF && user_target <= YAAMP_MAXDIFF)
			client->difficulty_actual = diff;
	}

}
