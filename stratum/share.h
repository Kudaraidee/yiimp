
class YAAMP_WORKER: public YAAMP_OBJECT
{
public:
	int userid;
	int workerid;
	int coinid;
	int remoteid;
	int height;

	bool valid;
	bool extranonce1;
	int32_t error_number;

	uint32_t ntime;
	double difficulty;
	double share_diff; /* submitted hash diff */

	bool solo;
};

inline void worker_delete(YAAMP_OBJECT *object)
{
	YAAMP_WORKER *worker = (YAAMP_WORKER *)object;
	delete worker;
}

//////////////////////////////////////////////////////////////////////////////////////////////////

class YAAMP_SHARE: public YAAMP_OBJECT
{
public:
	int jobid;
	// Progpow/Firo submits reuse these fields for the 64-char header hash and
	// mixhash, so keep enough room for the hex string plus NUL terminator.
	char extranonce2[128];
	char ntime[128];
	char nonce[128];
	char nonce1[128];
};

inline void share_delete(YAAMP_OBJECT *object)
{
	YAAMP_SHARE *share = (YAAMP_SHARE *)object;
	delete share;
}

//YAAMP_WORKER *share_find_worker(int userid, int workerid, int coinid, bool valid);
//void share_add_worker(int userid, int workerid, int coinid, bool valid, double difficulty);

///////////

YAAMP_SHARE *share_find(int jobid, char *extranonce2, char *ntime, char *nonce, char *nonce1);
void share_add(YAAMP_CLIENT *client, YAAMP_JOB *job, bool valid, char *extranonce2, char *ntime, char *nonce, double share_diff, int error_number, int height);

void share_write(YAAMP_DB *db);
void share_prune(YAAMP_DB *db);

////////////////////////////////////////////////////////////////////////////////

class YAAMP_BLOCK: public YAAMP_OBJECT
{
public:
	time_t created;
	bool confirmed;
	bool segwit;
	bool solo;

	int userid;
	int workerid;
	int coinid;
	int height;

	double difficulty;
	double difficulty_user;

	char hash[1024];
	char hash1[1024];
	char hash2[1024];
};

inline void block_delete(YAAMP_OBJECT *object)
{
	YAAMP_BLOCK *block = (YAAMP_BLOCK *)object;
	delete block;
}

////////////////////////////////////////////////////////////////////////////////////

class YAAMP_SUBMIT: public YAAMP_OBJECT
{
public:
	time_t created;
	bool valid;

	int remoteid;
	double difficulty;
};

inline void submit_delete(YAAMP_OBJECT *object)
{
	YAAMP_SUBMIT *submit = (YAAMP_SUBMIT *)object;
	delete submit;
}

void block_prune(YAAMP_DB *db);

void block_add(int userid, int workerid, int coinid, int height, double diff, double diff_user, const char *hash1, const char *h2, int segwit, bool solo);
bool block_confirm(int coinid, const char *hash);

YAAMP_SUBMIT *submit_add(int remoteid, double difficulty);
void submit_prune(YAAMP_DB *db);




