#include "stratum.h"
#include <signal.h>
#include <sys/resource.h>

#include <string>
#include <vector>

CommonList g_list_coind;
CommonList g_list_client;
CommonList g_list_job;
CommonList g_list_remote;
CommonList g_list_renter;
CommonList g_list_share;
CommonList g_list_worker;
CommonList g_list_block;
CommonList g_list_submit;
CommonList g_list_source;

// NEB's work service can pause job frames for several minutes during chain
// resync/reorg while miners keep working on the last valid job. The generic
// two-minute watchdog would kill and restart the stratum in that case.
#define YAAMP_MAXJOBDELAY_NEB (15*60)

int g_tcp_port;

int g_tcp_tls_port = 0;
char g_tcp_tls_cert[1024] = "";
char g_tcp_tls_key[1024] = "";
bool g_tcp_tls_autodetect = true;
bool g_tls_enabled = false;

char g_tcp_server[1024];
char g_tcp_password[1024];

char g_sql_host[1024];
char g_sql_database[1024];
char g_sql_username[1024];
char g_sql_password[1024];
int g_sql_port = 3306;

char g_stratum_coin_include[256];
char g_stratum_coin_exclude[256];

char g_stratum_algo[256];
double g_stratum_difficulty;
double g_stratum_min_diff;
double g_stratum_max_diff;
int g_stratum_eth_vardiff_window = 180;
double g_stratum_eth_vardiff_target = 1.0;

char g_nebhash_work_host[256] = "127.0.0.1";
int g_nebhash_work_port = 3441;
char g_nebhash_dataset[1024] = "/home/codex/lrs-nodes/neb-node/neb-datasets.bin";
char g_nebhash_chain_id[65] = "da2f5af7bb4c5e2bcc8d558b2f4f01d4c88557a943e6cff9cd035638fe9439ae";
uint32_t g_nebhash_start_k = 10;

double g_stratum_spm_min;
double g_stratum_spm_max;
double g_stratum_spm_spam;
int g_stratum_share_timeout;
int g_stratum_speed_slot_secs;
int g_stratum_idle_timeout;
bool g_stratum_allow_static_diff;

double g_stratum_nicehash_difficulty;
double g_stratum_nicehash_min_diff;
double g_stratum_nicehash_max_diff;

int g_stratum_max_ttf;
int g_stratum_max_cons = 5000;
bool g_stratum_reconnect;
bool g_stratum_renting;
bool g_stratum_segwit = false;
bool g_stratum_mweb = false;

int g_limit_txs_per_block = 0;

bool g_handle_haproxy_ips = false;
int g_socket_recv_timeout = 600;

char g_log_directory[1024];
bool g_debuglog_client;
bool g_debuglog_hash;
bool g_debuglog_socket;
bool g_debuglog_rpc;
bool g_debuglog_list;
bool g_debuglog_remote;

bool g_autoexchange = true;
char g_quote_symbol[32] = "USDT";
int g_quote_coinid = 9;

uint64_t g_max_shares = 0;
uint64_t g_shares_counter = 0;
uint64_t g_shares_log = 0;

// Equihash default
uint32_t g_equihash_wn = EQUIHASH200_9_WN;
uint32_t g_equihash_wk = EQUIHASH200_9_WK;

bool g_allow_rolltime = true;
time_t g_last_broadcasted = 0;
YAAMP_DB *g_db = NULL;

pthread_mutex_t g_db_mutex;
pthread_mutex_t g_nonce1_mutex;
pthread_mutex_t g_context_mutex;
pthread_mutex_t g_job_create_mutex;

struct ifaddrs *g_ifaddr;

volatile bool g_exiting = false;

void *stratum_thread(void *p);
void *monitor_thread(void *p);

bool is_kawpow = false;
bool is_firopow = false;
bool is_phihash = false;
bool is_meowpow = false;
bool is_evrprogpow = false;
bool is_meraki = false;
bool is_ethash = false;
bool is_etchash = false;
bool is_randomscash = false;
bool is_homescrypt = false;
bool is_nebhash = false;
bool is_progpow = false;
bool is_eth = false;
bool is_equihash = false;

// is_* from the algo's registry family and name; once at startup
void stratum_set_algo_flags(const YAAMP_ALGO *algo)
{
	const char *n = algo->name;
	is_progpow  = algo->family == ALGO_FAMILY_PROGPOW;
	is_eth      = algo->family == ALGO_FAMILY_ETHASH;
	is_equihash = algo->family == ALGO_FAMILY_EQUIHASH;
	is_nebhash  = algo->family == ALGO_FAMILY_NEB;

	is_kawpow      = !strcmp(n, "kawpow");
	is_firopow     = !strcmp(n, "firopow");
	is_phihash     = !strcmp(n, "phihash");
	is_meowpow     = !strcmp(n, "meowpow");
	is_evrprogpow  = !strcmp(n, "evrprogpow");
	is_meraki      = !strcmp(n, "meraki");
	is_ethash      = !strcmp(n, "ethash");
	is_etchash     = !strcmp(n, "etchash");
	is_homescrypt  = !strcmp(n, "homescrypt");
	is_randomscash = !strcmp(n, "randomscash");
}

////////////////////////////////////////////////////////////////////////////////////////

static void scrypt_hash(const char* input, char* output, uint32_t len)
{
	scrypt_1024_1_1_256((unsigned char *)input, (unsigned char *)output);
}

static void homescrypt_hash(const char* input, char* output, uint32_t len)
{
	homescrypt_v12((const unsigned char *)input, (unsigned char *)output);
}

static void scryptn_hash(const char* input, char* output, uint32_t len)
{
	time_t time_table[][2] =
	{
		{2048, 1389306217},
		{4096, 1456415081},
		{8192, 1506746729},
		{16384, 1557078377},
		{32768, 1657741673},
		{65536, 1859068265},
		{131072, 2060394857},
		{262144, 1722307603},
		{524288, 1769642992},
		{0, 0},
	};

	for(int i=0; time_table[i][0]; i++)
		if(time(NULL) < time_table[i+1][1])
		{
			scrypt_N_R_1_256(input, output, time_table[i][0], 1, len);
			return;
		}
}

static void neoscrypt_hash(const char* input, char* output, uint32_t len)
{
	neoscrypt((unsigned char *)input, (unsigned char *)output, 0x80000620);
}

YAAMP_ALGO g_algos[] =
{
#define ALGO(name, hash, diff_multiplier, merkle, family, flags) \
	{ name, hash, diff_multiplier, 0, merkle, 0, 0, 0, 0, false, ALGO_FAMILY_##family, flags },
#include "algos/registry.def"
#undef ALGO
	{},	// end of table (empty name)
};

YAAMP_ALGO *g_current_algo = NULL;

YAAMP_ALGO *stratum_find_algo(const char *name)
{
	for(int i=0; g_algos[i].name[0]; i++)
		if(!strcmp(name, g_algos[i].name))
			return &g_algos[i];

	return NULL;
}

////////////////////////////////////////////////////////////////////////////////////////

int main(int argc, char **argv)
{
	if(argc < 2)
	{
		printf("usage: %s <config_file>\n", argv[0]);
		return 1;
	}

	srand(time(NULL));
	getifaddrs(&g_ifaddr);

	// init g_log_directory with static value until set by config
	sprintf(g_log_directory, "/var/log/yiimp/");
	initlog(NULL);

	char configfile[1024];
	sprintf(configfile, "%s", argv[1]);

	dictionary *ini = iniparser_load(configfile);
	if(!ini)
	{
		debuglog("cant load config file %s\n", configfile);
		return 1;
	}

	g_tcp_port = iniparser_getint(ini, "TCP:port", 3333);
	strcpy(g_tcp_server, iniparser_getstring(ini, "TCP:server", NULL));
	strcpy(g_tcp_password, iniparser_getstring(ini, "TCP:password", NULL));

	// Optional TLS. A certificate enables same-port auto-detection on the
	// main port; tls_port additionally opens a TLS-only listener.
	g_tcp_tls_port = iniparser_getint(ini, "TCP:tls_port", 0);
	const char *tls_cert = iniparser_getstring(ini, "TCP:tls_cert", NULL);
	const char *tls_key = iniparser_getstring(ini, "TCP:tls_key", NULL);
	snprintf(g_tcp_tls_cert, sizeof(g_tcp_tls_cert), "%s", tls_cert ? tls_cert : "");
	snprintf(g_tcp_tls_key, sizeof(g_tcp_tls_key), "%s", tls_key ? tls_key : "");
	g_tcp_tls_autodetect = iniparser_getint(ini, "TCP:tls_autodetect", 1) != 0;

	strcpy(g_sql_host, iniparser_getstring(ini, "SQL:host", NULL));
	strcpy(g_sql_database, iniparser_getstring(ini, "SQL:database", NULL));
	strcpy(g_sql_username, iniparser_getstring(ini, "SQL:username", NULL));
	strcpy(g_sql_password, iniparser_getstring(ini, "SQL:password", NULL));
	g_sql_port = iniparser_getint(ini, "SQL:port", 3306);

	if (iniparser_getint(ini, "STRATUM:autoexchange", 1) == 0)
		g_autoexchange = false;
	else
		g_autoexchange = true;

	strncpy(g_quote_symbol, iniparser_getstring(ini, "STRATUM:quote_symbol", "USDT"), sizeof(g_quote_symbol) - 1);
	g_quote_symbol[sizeof(g_quote_symbol) - 1] = '\0';
	g_quote_coinid = iniparser_getint(ini, "STRATUM:quote_coinid", 9);

	// optional coin filters (to mine only one on a special port or a test instance)
	char *coin_filter = iniparser_getstring(ini, "WALLETS:include", NULL);
	strcpy(g_stratum_coin_include, coin_filter ? coin_filter : "");
	coin_filter = iniparser_getstring(ini, "WALLETS:exclude", NULL);
	strcpy(g_stratum_coin_exclude, coin_filter ? coin_filter : "");

	strcpy(g_stratum_algo, iniparser_getstring(ini, "STRATUM:algo", NULL));
	g_stratum_difficulty = iniparser_getdouble(ini, "STRATUM:difficulty", 16);
	g_stratum_min_diff = iniparser_getdouble(ini, "STRATUM:diff_min", g_stratum_difficulty/2);
	g_stratum_max_diff = iniparser_getdouble(ini, "STRATUM:diff_max", g_stratum_difficulty*8192);

	// GPU vardiff (Ethash/Etchash and the Progpow family) uses a fixed window
	// and a much lower target share rate than the Bitcoin-style algorithm.
	// Both are tunable per pool.
	g_stratum_eth_vardiff_window = iniparser_getint(ini, "STRATUM:eth_vardiff_window", 180);
	if (g_stratum_eth_vardiff_window < 30) g_stratum_eth_vardiff_window = 30;
	g_stratum_eth_vardiff_target = iniparser_getdouble(ini, "STRATUM:eth_vardiff_target", 1.0);
	if (g_stratum_eth_vardiff_target < 0.1) g_stratum_eth_vardiff_target = 0.1;

	// vardiff keeps shares per minute inside [spm_min, spm_max], retargets to the middle
	g_stratum_spm_min = iniparser_getdouble(ini, "STRATUM:spm_min", 2);
	g_stratum_spm_max = iniparser_getdouble(ini, "STRATUM:spm_max", 4);
	if(g_stratum_spm_min <= 0 || g_stratum_spm_max <= g_stratum_spm_min)
	{
		stratumlog("invalid spm_min %g / spm_max %g, using 2 / 4\n", g_stratum_spm_min, g_stratum_spm_max);
		g_stratum_spm_min = 2;
		g_stratum_spm_max = 4;
	}
	// above this rate the difficulty is raised on the next share
	g_stratum_spm_spam = iniparser_getdouble(ini, "STRATUM:spm_spam", g_stratum_spm_max * 5);
	if(g_stratum_spm_spam <= g_stratum_spm_max) g_stratum_spm_spam = g_stratum_spm_max * 5;
	// no valid share for this many seconds halves the difficulty
	g_stratum_share_timeout = iniparser_getint(ini, "STRATUM:share_timeout", (int)(3 * 60 / g_stratum_spm_min));
	if(g_stratum_share_timeout < 10) g_stratum_share_timeout = 10;
	// client speed window in seconds, split into YAAMP_SPEED_SLOTS slots
	int speed_window = iniparser_getint(ini, "STRATUM:speed_window", 600);
	g_stratum_speed_slot_secs = std::max(1, speed_window / YAAMP_SPEED_SLOTS);
	// no valid share for this long at the lowest difficulty (or 3x as long in any case): disconnect
	g_stratum_idle_timeout = std::max(60, iniparser_getint(ini, "STRATUM:idle_timeout", 600));
	// d= in the password fixes the difficulty (only spam raises it); off: d= is a start value like sd=
	g_stratum_allow_static_diff = iniparser_getboolean(ini, "STRATUM:allow_static_diff", false);

	char *new_log_directory = iniparser_getstring(ini, "STRATUM:logdir", NULL);
	if (new_log_directory) { strcpy(g_log_directory, new_log_directory); }

	if (!strcmp(g_stratum_algo, "ethash") || !strcmp(g_stratum_algo, "etchash"))
		ethash_params_load(ini);

	if (!strcmp(g_stratum_algo, "nebhash"))
	{
		const char *value;
		value = iniparser_getstring(ini, "NEBHASH:work_host", NULL);
		if (value) snprintf(g_nebhash_work_host, sizeof(g_nebhash_work_host), "%s", value);
		g_nebhash_work_port = iniparser_getint(ini, "NEBHASH:work_port", g_nebhash_work_port);
		value = iniparser_getstring(ini, "NEBHASH:dataset", NULL);
		if (value) snprintf(g_nebhash_dataset, sizeof(g_nebhash_dataset), "%s", value);
		value = iniparser_getstring(ini, "NEBHASH:chain_id", NULL);
		if (value && strlen(value) == 64)
			snprintf(g_nebhash_chain_id, sizeof(g_nebhash_chain_id), "%s", value);
		g_nebhash_start_k = (uint32_t) iniparser_getint(ini, "NEBHASH:start_k", (int) g_nebhash_start_k);
		if (g_nebhash_start_k > 48) g_nebhash_start_k = 48;
	}

	g_stratum_nicehash_difficulty = iniparser_getdouble(ini, "STRATUM:nicehash", 16);
	g_stratum_nicehash_min_diff = iniparser_getdouble(ini, "STRATUM:nicehash_diff_min", g_stratum_nicehash_difficulty/2);
	g_stratum_nicehash_max_diff = iniparser_getdouble(ini, "STRATUM:nicehash_diff_max", g_stratum_nicehash_difficulty*8192);

	g_stratum_max_cons = iniparser_getint(ini, "STRATUM:max_cons", 5000);
	g_stratum_max_ttf = iniparser_getint(ini, "STRATUM:max_ttf", 0x70000000);
	g_stratum_reconnect = iniparser_getint(ini, "STRATUM:reconnect", true);
	g_stratum_renting = iniparser_getint(ini, "STRATUM:renting", true);
	g_handle_haproxy_ips = iniparser_getint(ini, "STRATUM:haproxy_ips", g_handle_haproxy_ips);
	g_socket_recv_timeout = iniparser_getint(ini, "STRATUM:recv_timeout", 600);

	g_max_shares = iniparser_getint(ini, "STRATUM:max_shares", g_max_shares);
	g_limit_txs_per_block = iniparser_getint(ini, "STRATUM:max_txs_per_block", 0);

	g_debuglog_client = iniparser_getint(ini, "DEBUGLOG:client", false);
	g_debuglog_hash = iniparser_getint(ini, "DEBUGLOG:hash", false);
	g_debuglog_socket = iniparser_getint(ini, "DEBUGLOG:socket", false);
	g_debuglog_rpc = iniparser_getint(ini, "DEBUGLOG:rpc", false);
	g_debuglog_list = iniparser_getint(ini, "DEBUGLOG:list", false);
	g_debuglog_remote = iniparser_getint(ini, "DEBUGLOG:remote", false);

	iniparser_freedict(ini);

	// re-init logfiles
	closelogs(); initlog(g_stratum_algo);

	// TLS is strictly optional: a bad path or unreadable key only disables
	// TLS, it must never stop the plaintext listener from starting.
	g_tls_enabled = socket_tls_setup(g_tcp_tls_cert, g_tcp_tls_key);
	if (g_tcp_tls_port && !g_tls_enabled)
		stratumlogdate("TLS: tls_port %d ignored because TLS could not be enabled\n", g_tcp_tls_port);
	if (g_tcp_tls_port == g_tcp_port && g_tcp_tls_port != 0)
		stratumlogdate("TLS: tls_port equals the main port; using shared-port auto-detection only\n");

	g_current_algo = stratum_find_algo(g_stratum_algo);

	if(!g_current_algo) yaamp_error("invalid algo");
	if(!g_current_algo->hash_function) yaamp_error("no hash function");

	stratum_set_algo_flags(g_current_algo);

//	struct rlimit rlim_files = {0x10000, 0x10000};
//	setrlimit(RLIMIT_NOFILE, &rlim_files);

	struct rlimit rlim_threads = {0x8000, 0x8000};
	setrlimit(RLIMIT_NPROC, &rlim_threads);

	stratumlogdate("starting stratum for %s on %s:%d (tls %s, shared-port %s, tls_port %d)\n",
		g_current_algo->name, g_tcp_server, g_tcp_port,
		g_tls_enabled ? "enabled" : "disabled",
		(g_tls_enabled && g_tcp_tls_autodetect) ? "yes" : "no",
		g_tls_enabled ? g_tcp_tls_port : 0);

	// init Equihash parameters
	if (!strcmp(g_current_algo->name,"equihash144")) {
		g_equihash_wn = EQUIHASH144_5_WN;
		g_equihash_wk = EQUIHASH144_5_WK;
	}
	else if (!strcmp(g_current_algo->name,"equihash192")) {
		g_equihash_wn = EQUIHASH192_7_WN;
		g_equihash_wk = EQUIHASH192_7_WK;
	}
	else if (!strcmp(g_current_algo->name,"equihash96")) {
		g_equihash_wn = EQUIHASH96_5_WN;
		g_equihash_wk = EQUIHASH96_5_WK;
	}
	else if (!strcmp(g_current_algo->name,"equihash125")) {
		g_equihash_wn = EQUIHASH125_4_WN;
		g_equihash_wk = EQUIHASH125_4_WK;
	}

	// ntime should not be changed by miners for these algos
	g_allow_rolltime = !(g_current_algo->flags & ALGO_NO_ROLLTIME);
	if (!g_allow_rolltime)
		stratumlog("note: time roll disallowed for %s algo\n", g_current_algo->name);

	g_db = db_connect();
	if(!g_db) yaamp_error("Cant connect database");

//	db_query(g_db, "update mining set stratumids='loading'");

	yaamp_create_mutex(&g_db_mutex);
	yaamp_create_mutex(&g_nonce1_mutex);
	yaamp_create_mutex(&g_context_mutex);
	yaamp_create_mutex(&g_job_create_mutex);

	YAAMP_DB *db = db_connect();
	if(!db) yaamp_error("Cant connect database");

	db_register_stratum(db);
	db_update_algos(db);
	db_update_coinds(db);

	sleep(2);
	job_init();

	if (is_nebhash && !nebhash_init())
		yaamp_error("cannot initialize nebhash work service");

//	job_signal();

	////////////////////////////////////////////////

	pthread_t thread1;
	pthread_create(&thread1, NULL, monitor_thread, NULL);

	// Main listener accepts plaintext, and TLS too when a certificate is
	// configured and shared-port auto-detection was not turned off.
	int main_tls_mode = (g_tls_enabled && g_tcp_tls_autodetect) ? YAAMP_TLS_SNIFF : YAAMP_TLS_NONE;
	pthread_t thread2;
	pthread_create(&thread2, NULL, stratum_thread, (void *)(long)main_tls_mode);

	// Optional TLS-only listener. If it equals the main port the shared-port
	// sniffer already covers it, so do not try to bind the port twice.
	pthread_t thread3 = 0;
	if (g_tls_enabled && g_tcp_tls_port > 0 && g_tcp_tls_port != g_tcp_port)
		pthread_create(&thread3, NULL, stratum_thread, (void *)(long)YAAMP_TLS_FORCE);

	sleep(20);

	while(!g_exiting)
	{
		db_register_stratum(db);
		db_update_workers(db);
		db_update_algos(db);
		db_update_coinds(db);

		if(g_stratum_renting)
		{
			db_update_renters(db);
			db_update_remotes(db);
		}

		share_write(db);
		share_prune(db);

		block_prune(db);
		submit_prune(db);

		sleep(1);
		job_signal();

		////////////////////////////////////

//		source_prune();
		job_check_status();

		object_prune(&g_list_coind, coind_delete);
		object_prune(&g_list_remote, remote_delete);
		object_prune(&g_list_job, job_delete);
		object_prune(&g_list_client, client_delete);
		object_prune(&g_list_block, block_delete);
		object_prune(&g_list_worker, worker_delete);
		object_prune(&g_list_share, share_delete);
		object_prune(&g_list_submit, submit_delete);
		debuglog("coin %i job %i cli %i block %i work %i share %i\n",
					g_list_coind.count, g_list_job.count,
					g_list_client.count, g_list_block.count,
					g_list_worker.count, g_list_share.count);
		if ( g_list_coind.count < g_list_job.count ) {
			job_log_statistic();
		}

		if (!g_exiting) sleep(20);
	}

	stratumlog("closing database...\n");
	db_close(db);

	pthread_join(thread2, NULL);
	if (thread3) pthread_join(thread3, NULL);
	db_close(g_db); // client threads (called by stratum one)

	socket_tls_shutdown();

	closelogs();

	return 0;
}

///////////////////////////////////////////////////////////////////////////////

void *monitor_thread(void *p)
{
	while(!g_exiting)
	{
		sleep(120);

		// A stratum instance with no configured coins, or with no connected
		// miners, is idle by design: it has nobody to broadcast jobs to, so the
		// stale-job watchdog must not restart it. Only treat a missing job as a
		// deadlock when coins are loaded and miners are actually connected.
		int maxjobdelay = !strcmp(g_stratum_algo, "nebhash") ? YAAMP_MAXJOBDELAY_NEB : YAAMP_MAXJOBDELAY;
		if(g_list_coind.count > 0 && g_list_client.count > 0 &&
		   g_last_broadcasted + maxjobdelay < time(NULL))
		{
			g_exiting = true;
			stratumlogdate("%s dead lock, exiting...\n", g_stratum_algo);
			exit(1);
		}
	}
	return NULL;
}

///////////////////////////////////////////////////////////////////////////////

void *stratum_thread(void *p)
{
	int tls_mode = (int)(long)p;
	int listen_port = (tls_mode == YAAMP_TLS_FORCE) ? g_tcp_tls_port : g_tcp_port;

	int listen_sock = socket(AF_INET, SOCK_STREAM, 0);
	if(listen_sock <= 0) yaamp_error("socket");

	int optval = 1;
	setsockopt(listen_sock, SOL_SOCKET, SO_REUSEADDR, &optval, sizeof optval);

	struct sockaddr_in serv;

	serv.sin_family = AF_INET;
	serv.sin_addr.s_addr = htonl(INADDR_ANY);
	serv.sin_port = htons(listen_port);

	int res = bind(listen_sock, (struct sockaddr*)&serv, sizeof(serv));
	if(res < 0) yaamp_error("bind");

	res = listen(listen_sock, 4096);
	if(res < 0) yaamp_error("listen");

	stratumlogdate("%s listening on port %d (%s)\n", g_stratum_algo, listen_port,
		tls_mode == YAAMP_TLS_FORCE ? "TLS only" :
		tls_mode == YAAMP_TLS_SNIFF ? "plaintext + TLS" : "plaintext");

	/////////////////////////////////////////////////////////////////////////

	int failcount = 0;
	while(!g_exiting)
	{
		int sock = accept(listen_sock, NULL, NULL);
		if(sock <= 0)
		{
			int error = errno;
			stratumlog("%s socket accept() error %d\n", g_stratum_algo, error);
			failcount++;
			usleep(50000);
			if (error == 24 && failcount > 5) {
				g_exiting = true; // happen when max open files is reached (see ulimit)
				stratumlogdate("%s too much socket failure, exiting...\n", g_stratum_algo);
				exit(error);
			}
			continue;
		}

		failcount = 0;
		YAAMP_CLIENT_START *start = new YAAMP_CLIENT_START;
		start->sock = sock;
		start->tls_mode = tls_mode;

		pthread_t thread;
		int res = pthread_create(&thread, NULL, client_thread, start);
		if(res != 0)
		{
			int error = errno;
			delete start;
			close(sock);
			g_exiting = true;
			stratumlog("%s pthread_create error %d %d\n", g_stratum_algo, res, error);
		}
		else
		{
			pthread_detach(thread);
		}
	}
	return NULL;
}
