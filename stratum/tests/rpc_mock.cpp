// rpc_mock: replaces rpc.cpp and rpc_curl.cpp for templcheck.
// rpc_call(method) answers with the JSON file <g_rpc_fixture_dir>/<method>.json
// (a full JSON-RPC response: {"result": ..., "error": null, "id": 1}).
// A missing file behaves like an unreachable wallet (NULL).

#include <string>

#include "../stratum.h"

char g_rpc_fixture_dir[1024] = ".";

bool rpc_connected(YAAMP_RPC *rpc) { return true; }
bool rpc_connect(YAAMP_RPC *rpc) { return true; }
void rpc_close(YAAMP_RPC *rpc) {}

int rpc_send_raw(YAAMP_RPC *rpc, const char *buffer, int bytes) { return bytes; }
int rpc_send(YAAMP_RPC *rpc, const char *format, ...) { return 0; }
int rpc_flush(YAAMP_RPC *rpc) { return 0; }

json_value *rpc_call(YAAMP_RPC *rpc, char const *method, char const *params, YAAMP_COIND *coind)
{
	char path[2048];
	snprintf(path, sizeof(path), "%s/%s.json", g_rpc_fixture_dir, method);

	FILE *f = fopen(path, "rb");
	if (!f) return NULL;

	std::string data;
	char buf[65536];
	size_t n;
	while ((n = fread(buf, 1, sizeof(buf), f)) > 0) data.append(buf, n);
	fclose(f);

	json_value *json = json_parse(data.c_str(), data.size());
	if (!json) fprintf(stderr, "rpc_mock: invalid JSON in %s\n", path);
	return json;
}

json_value *rpc_curl_call(YAAMP_RPC *rpc, char const *method, char const *params)
{
	return rpc_call(rpc, method, params, NULL);
}

void rpc_curl_get_lasterr(char *buffer, int buflen) { if (buflen > 0) buffer[0] = 0; }
void rpc_curl_close(YAAMP_RPC *rpc) {}
bool rpc_curl_connected(YAAMP_RPC *rpc) { return true; }
bool rpc_curl_connect(YAAMP_RPC *rpc) { return true; }
