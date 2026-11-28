
class YAAMP_COIND;

// Build a JSON-RPC 2.0 request. GETH-family daemons (ethash, xhash, ...)
// reject requests that omit the jsonrpc field; Bitcoin-style daemons accept
// the explicit version as well.
inline void rpc_build_request(char *out, size_t outsz, const char *method,
                              const char *params, int id)
{
	if (params)
		snprintf(out, outsz, "{\"jsonrpc\":\"2.0\",\"method\":\"%s\",\"params\":%s,\"id\":\"%d\"}",
		         method, params, id);
	else
		snprintf(out, outsz, "{\"jsonrpc\":\"2.0\",\"method\":\"%s\",\"id\":\"%d\"}",
		         method, id);
}

struct YAAMP_RPC
{
	YAAMP_COIND *coind;
	int port;

	char host[1024];
	char credential[1024];
	char cert[1024];

	int ssl;
	int curl;
	int sock;
	int id;

	int bufpos;
	char buffer[YAAMP_SMALLBUFSIZE];

	pthread_mutex_t mutex;

	void* CURL;
};

//////////////////////////////////////////////////////////////////////////

bool rpc_connected(YAAMP_RPC *rpc);
bool rpc_connect(YAAMP_RPC *rpc);
void rpc_close(YAAMP_RPC *rpc);

int rpc_send_raw(YAAMP_RPC *rpc, const char *buffer, int bytes);
int rpc_send(YAAMP_RPC *rpc, const char *format, ...);
int rpc_flush(YAAMP_RPC *rpc);

json_value *rpc_call(YAAMP_RPC *rpc, char const *method, char const *params=NULL, YAAMP_COIND *coind=NULL);

json_value *rpc_curl_call(YAAMP_RPC *rpc, char const *method, char const *params);
void rpc_curl_get_lasterr(char* buffer, int buflen);
void rpc_curl_close(YAAMP_RPC *rpc);
