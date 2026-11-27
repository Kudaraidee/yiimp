
//struct YAAMP_SOURCE
//{
//public:
//	int count;
//	double speed;
//
//	char ip[64];
//};

// stratum protocol family of an algorithm (algos/registry.def)
enum YAAMP_ALGO_FAMILY
{
	ALGO_FAMILY_STD,	// bitcoin-style stratum
	ALGO_FAMILY_PROGPOW,	// kawpow-style header/mix stratum
	ALGO_FAMILY_ETHASH,	// EthereumStratum / ethproxy
	ALGO_FAMILY_EQUIHASH,
	ALGO_FAMILY_NEB,	// neb-pool/1
};

#define ALGO_NO_ROLLTIME	0x1	// miners must not change ntime

struct YAAMP_ALGO
{
	char name[64];
	YAAMP_HASH_FUNCTION hash_function;

	double diff_multiplier;
	double factor;
	YAAMP_HASH_FUNCTION merkle_func;
	double speedfactor;
	int powlimit_bits;

	double profit;
	double rent;

	bool overflow;

	int family;	// YAAMP_ALGO_FAMILY
	unsigned flags;	// ALGO_*
};

struct YAAMP_CLIENT_ALGO
{
	double factor;
	YAAMP_ALGO *algo;
};

#define YAAMP_JOB_MAXHISTORY	16

// client->speed (h/s) and share rate over a sliding window of valid shares
// slot length = STRATUM:speed_window / YAAMP_SPEED_SLOTS (g_stratum_speed_slot_secs)
#define YAAMP_SPEED_SLOTS	60
// share spam is judged on the last minute only
#define YAAMP_SPAM_WINDOW_SECS	60
// a resent job gets this many leading zeros in its id at most
#define YAAMP_RESEND_MAX	16
// vardiff needs this many effective shares in the window (see speed_neff)
#define YAAMP_VARDIFF_MIN_SHARES	6

#define MIN_ADDRESS_LEN 3 /* BTC len can be as few as 26 chars, but gen. 33 or 34 */
#define MAX_ADDRESS_LEN 98 /* BITC */

class YAAMP_CLIENT: public YAAMP_OBJECT
{
public:
	YAAMP_SOCKET *sock;
//	YAAMP_SOURCE *source;

	char notify_id[1024];
	int64_t reqid; // ask request id

	int created;
	int last_best;

	bool reconnectable;
	bool reconnecting;

	bool is_nicehash;

	// T-Rex and other ethproxy miners never send mining.subscribe; they log
	// in with eth_submitLogin and poll eth_getWork. Such clients must not
	// receive mining.set_difficulty / mining.notify.
	bool is_ethproxy;

	// NiceHash EthereumStratum/1.0.0 subscribers expect the classic array
	// subscribe result and 0x-less notify fields. Rigel advertises the same
	// version but uses its own object-shaped response, so keep them separate.
	bool is_nicehash_ethstratum;

	int userid;
	int workerid;
	int coinid;
	// Explicit c=<quote symbol> login: bind to the quote coin id (USDT=9) and
	// skip coin-specific address validation so earnings go to the quote wallet.
	bool pay_quote;
	bool logtraffic;

	int id_int;
	const char *id_str;

	char version[1024];
	char username[1024];
	char password[1024];
	char worker[1024];

	std::vector<std::string> coins_mining_list;
	std::vector<std::string> coins_ignore_list;

	double difficulty_actual;
	double difficulty_remote;
	double difficulty_written;
	bool difficulty_fixed;		// password d= pinned the difficulty for the session
	double difficulty_prev;		// in effect for jobs up to difficulty_prev_jobid
	int difficulty_prev_jobid;
	int difficulty_changed;		// time of the last change
	int difficulty_new_gen;		// shares on difficulty_prev_jobid with this resend generation or later count new, 0 = none
	bool difficulty_confirmed;	// a share at the new difficulty came back after a resend
	int resend_jobid;		// job the resend generations belong to
	int resend_gen;			// last generation (leading zeros of the job id) sent for resend_jobid
	double share_difficulty;	// difficulty of the share being processed (set on submit)
	double submit_share_diff;	// hash difficulty of the share being processed, -1 = not computed


	// ETH vardiff counts shares in a fixed window instead of the generic
	// 10 shares/min EMA, which is far too aggressive for Ethash GPUs.
	long long vardiff_window_start;
	int vardiff_shares;

	char extranonce1[32];
	int extranonce2size;

	char extranonce1_default[32];
	int extranonce2size_default;

	char extranonce1_last[32];
	int extranonce2size_last;

	char extranonce1_reconnect[32];
	int extranonce2size_reconnect;

	bool extranonce_subscribe;
	// Set after a successful TLS handshake and persisted in db_workers so the
	// wallet page can tag workers that are actually connected over TLS.
	bool tls;
	int submit_bad;

	double speed;
	double speed_slots[YAAMP_SPEED_SLOTS];	// work in hashes, [0] = current slot
	int speed_share_slots[YAAMP_SPEED_SLOTS];	// share count per slot
	double speed_sq_slots[YAAMP_SPEED_SLOTS];	// sum of squared share difficulties per slot
	int speed_shares;	// shares in the window
	double speed_neff;	// effective share count: (sum d)^2 / sum d^2, few big shares weigh more than many small
	int speed_span;		// seconds the window covers
	double speed_short;	// h/s over the last YAAMP_SPAM_WINDOW_SECS
	int speed_short_shares;
	int speed_slot;		// time slot of speed_slots[0]
	int speed_since;	// start of measurement, kept across reconnect
	int last_share;		// time of last valid share
	int extranonce1_id;

	int jobid_next;
	int jobid_sent;
	int jobid_locked;

	YAAMP_CLIENT_ALGO algos_subscribed[YAAMP_MAXALGOS];
	int job_history[YAAMP_JOB_MAXHISTORY];
	// Target published with each mining.notify. Progpow miners keep working on
	// old jobs after a vardiff update, so a share must stay valid against the
	// target that job was actually sent with.
	int job_target_ids[YAAMP_JOB_MAXHISTORY];
	uint256 job_targets[YAAMP_JOB_MAXHISTORY];

	int64_t shares;
	int stats;

	int donation;
	int broadcast_timeouts;

	//////////////////////////////////////////////////
	uint256 share_target;
	uint256 next_target;

	// NEB (neb-pool/1) session state. The k-values are the reference pool's
	// vardiff window: target = job share_target << k, with the previous,
	// easier target accepted briefly after a change.
	bool neb_subscribed;
	bool neb_authorized;
	uint64_t neb_session;
	// A neb-pool/1 session may authorize several logins (dev-fee miners do).
	char neb_logins[8][1088];
	int neb_login_userid[8];
	int neb_login_workerid[8];
	bool neb_login_solo[8];
	int neb_login_count;
	uint32_t neb_k;
	uint32_t neb_k_prev;
	long long neb_k_changed;
	// Non-zero when d=/sd= selected a miner-visible difficulty instead of a
	// legacy integer k shift. The pool target is derived from
	// neb_diff * 10^10 and clamped to the job's share target.
	double neb_diff;
	double neb_diff_prev;
	long long neb_vardiff_start;
	int neb_vardiff_shares;
	long long neb_second_start;
	int neb_second_shares;
	// Last accepted share, used only to expire a stale speed estimate.
	long long neb_last_share_time;
	// Exponential 600s rolling window of expected share work (the session's
	// target difficulty). NEB shares are poisson-distributed, so a single
	// share interval is far too noisy to use as the displayed hashrate (a
	// lucky 4s interval showed 43 Mh/s). neb_speed_start is the last decay
	// timestamp; displayed speed is neb_speed_diff / 600.
	long long neb_speed_start;
	double neb_speed_diff;
	int neb_bad_in_a_row;
	int neb_models_count;
	char neb_models[8][65];
	bool neb_first_job;
	bool neb_vardiff_repush;

	bool solo;
};

inline void client_delete(YAAMP_OBJECT *object)
{
	YAAMP_CLIENT *client = (YAAMP_CLIENT *)object;
	if (object == NULL) return;

	socket_close(client->sock);
	delete client;

	object = NULL;
}

//////////////////////////////////////////////////////////////////////////

YAAMP_CLIENT *client_find_notify_id(const char *notify_id, bool reconnecting);

void get_next_extraonce1(char *extraonce1);
void get_random_key(char *key);

void client_sort();
void client_debug_who(YAAMP_CLIENT *client, char *out, int size);
void client_log_submit(YAAMP_CLIENT *client, const char *result);
void client_speed_add(YAAMP_CLIENT *client, double difficulty);
void client_speed_update(YAAMP_CLIENT *client);
void client_block_ip(YAAMP_CLIENT *client, const char *reason);
void client_block_ipset(YAAMP_CLIENT *client, const char *ipset_name);

bool client_reset_multialgo(YAAMP_CLIENT *client, bool first);
bool client_initialize_multialgo(YAAMP_CLIENT *client);

void client_add_job_history(YAAMP_CLIENT *client, int jobid);
bool client_find_job_history(YAAMP_CLIENT *client, int jobid, int startat=1);
void client_record_job_target(YAAMP_CLIENT *client, int jobid, const uint256& target);
bool client_find_job_target(YAAMP_CLIENT *client, int jobid, uint256& target);

bool client_find_my_ip(const char *ip);

//////////////////////////////////////////////////////////////////////////

int client_send_difficulty(YAAMP_CLIENT *client, double difficulty);
double client_normalize_difficulty(double difficulty, YAAMP_CLIENT *client);
void client_record_difficulty(YAAMP_CLIENT *client);
bool client_uses_precise_difficulty();
bool client_low_difficulty_algo();

void client_change_difficulty(YAAMP_CLIENT *client, double difficulty);
void client_adjust_difficulty(YAAMP_CLIENT *client);
void client_vardiff_share(YAAMP_CLIENT *client, YAAMP_JOB *job, int gen);
void client_vardiff_silence(YAAMP_CLIENT *client, YAAMP_JOB *job);
void client_vardiff_resend(YAAMP_CLIENT *client, YAAMP_JOB *job);
bool client_vardiff_at_floor(YAAMP_CLIENT *client);
bool client_idle(YAAMP_CLIENT *client, int *idle);
double client_share_difficulty(YAAMP_CLIENT *client, YAAMP_JOB *job, int gen);
int client_jobid_gen(const char *jobid);

void client_initialize_difficulty(YAAMP_CLIENT *client);
const char *client_password_param(const char *password, const char *key, size_t keylen);
bool client_configure(YAAMP_CLIENT *client, json_value *json_params);

//////////////////////////////////////////////////////////////////////////

int client_call(YAAMP_CLIENT *client, const char *method, const char *format, ...);
int client_ask(YAAMP_CLIENT *client, const char *method, const char *format, ...);

void client_dump_all();

int client_send_result(YAAMP_CLIENT *client, const char *format, ...);
int client_send_error(YAAMP_CLIENT *client, int error, const char *string);

bool client_ask_stats(YAAMP_CLIENT *client);

bool client_submit(YAAMP_CLIENT *client, json_value *json_params);
void client_submit_error(YAAMP_CLIENT *client, YAAMP_JOB *job, int id, const char *message, char *extranonce2, char *ntime, char *nonce);

int client_workers_count(YAAMP_CLIENT *client);
int client_workers_byaddress(const char *username);
bool client_auth_by_workers(YAAMP_CLIENT *client);

void *client_thread(void *p);

void db_check_user_input(char* input);

//void source_prune();

