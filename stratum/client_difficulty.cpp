
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
	return is_ethash || is_etchash || is_kawpow || is_firopow || is_phihash || is_meowpow || is_evrprogpow || is_meraki;
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

// Keep the fixed-window share counter used by client_uses_precise_difficulty().
// dev's sliding-window speed tracking replaces the old EMA (shares_per_minute),
// so callers only need to record that a valid share arrived.
void client_record_difficulty(YAAMP_CLIENT *client)
{
	if(client->difficulty_remote) return;
	client->vardiff_shares++;
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
	// NEB uses the reference pool's k-window vardiff and publishes exact
	// 256-bit targets from nebhash_submit(); never run yiimp's second vardiff.
	if (is_nebhash) return;

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
	// NEB has its own node-driven vardiff and target; never run yiimp's on it.
	if (is_nebhash) return;

	// The GPU/precise protocols use the fixed-window band check above. Running
	// dev's sliding-window spam step here would fight that target and could
	// resend a job id format those miners do not parse as a generation.
	if (client_uses_precise_difficulty()) return;

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
	// NEB jobs/targets come from the work service; a generic silence-driven
	// difficulty change would be invisible to the miner and to its verifier.
	if (is_nebhash) return;
	// Precise-difficulty miners are handled by the fixed-window band check on
	// each job; a generic resend would not carry a generation for their format.
	if (client_uses_precise_difficulty()) return;
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
	// NEB targets are 256-bit little-endian and travel with each job. The
	// generic set_difficulty/set_target paths use incompatible byte order.
	if (is_nebhash) return 0;

//	debuglog("%s diff %f\n", client->sock->ip, difficulty);
	if (client_uses_precise_difficulty())
	{
		client->vardiff_shares = 0;
		client->vardiff_window_start = current_timestamp();
	}

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
	else if (is_meraki)
	{
		client->share_target = meraki_diff_to_target(difficulty);
		client_call(client, "mining.set_target", "[\"%s\"]", client->share_target.ToString().c_str());
		client->next_target = client->share_target;
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
		if(homescrypt_uses_gpuminer_job(client))
			client_call(client, "mining.set_difficulty", "[%.8g]", wire_difficulty);
		else if(wire_difficulty >= 1)
			client_call(client, "mining.set_difficulty", "[%.0f]", wire_difficulty);
		else
			client_call(client, "mining.set_difficulty", "[%0.8f]", wire_difficulty);
	}
	return 0;
}

// Match a comma-separated password token such as "d=2" or "sd=0.1".
// A plain strstr("d=") also matches "sd=", "xd=", "decred=", etc.; keeping
// the tokens explicit lets d= and sd= have different meanings.
const char *client_password_param(const char *password, const char *key, size_t keylen)
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

// d=<diff> pins the difficulty in the password; sd=<diff> only seeds it.
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
				else if (is_meraki)
					client->share_target = meraki_diff_to_target(diff);
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
