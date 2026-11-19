
#include "stratum.h"

bool coind_submitwork(YAAMP_COIND *coind, const char *block)
{
	int paramlen = strlen(block);

	char *params = (char *)malloc(paramlen+1024);
	if(!params) {
		debuglog("%s: OOM!\n", __func__);
		return false;
	}

	sprintf(params, "[\"%s\"]", block);
	json_value *json = rpc_call(&coind->rpc, "getwork", params, coind);
	if(!json) {
		debuglog("%s: retry\n", __func__);
		usleep(500*YAAMP_MS);
		json = rpc_call(&coind->rpc, "getwork", params, coind);
	}
	free(params);

	if(!json) {
		debuglog("%s: error, no answer\n", __func__);
		return false;
	}

	json_value *json_res = json_get_object(json, "result");

	bool b = json_res && json_res->type == json_boolean && json_res->u.boolean;
	json_value_free(json_res);

	return b;
}

bool coind_pprpcsb(YAAMP_COIND *coind, const char* header_hash, const char* mix_hash, const char* nonce64)
{
	char *params = (char *)malloc(1024);
	if (!params) return false;

        sprintf(params, "[\"%s\",\"0x%s\",\"0x%s\"]", header_hash, mix_hash, nonce64);
        json_value *json = rpc_call(&coind->rpc, "pprpcsb", params);

        free(params);
        if(!json) return false;

        json_value *json_error = json_get_object(json, "error");
        if(json_error && json_error->type != json_null)
        {
                const char *p = json_get_string(json_error, "message");
                if(p) stratumlog("ERROR %s %s\n", coind->name, p);
                json_value_free(json);
                return false;
        }

        json_value *json_result = json_get_object(json, "result");

        bool b = json_result && json_result->type == json_null;
        json_value_free(json);

        return b;
}

// Resolve the canonical block hash after pprpcsb accepts a Progpow/Firo block.
// Progpow powhash is not the block hash, so ask the daemon which hash landed at
// the template height instead of inventing one locally.
bool coind_getblockhash(YAAMP_COIND *coind, int height, char *hash, size_t hashlen)
{
        if(!coind || !hash || hashlen < 65) return false;

        char params[64];
        snprintf(params, sizeof(params), "[%d]", height);

        for(int attempt = 0; attempt < 3; attempt++)
        {
                json_value *json = rpc_call(&coind->rpc, "getblockhash", params);
                if(json)
                {
                        json_value *json_result = json_get_object(json, "result");
                        if(json_result && json_result->type == json_string)
                        {
                                const char *s = json_result->u.string.ptr;
                                if(s && strlen(s) >= 64 && strlen(s) < hashlen)
                                {
                                        snprintf(hash, hashlen, "%s", s);
                                        json_value_free(json);
                                        return true;
                                }
                        }
                        json_value_free(json);
                }
                usleep(200*YAAMP_MS);
        }

        hash[0] = '\0';
        return false;
}

bool coind_submitblock(YAAMP_COIND *coind, const char *block)
{
	int paramlen = strlen(block);

	char *params = (char *)malloc(paramlen+1024);
	if(!params) return false;

	sprintf(params, "[\"%s\"]", block);
	json_value *json = rpc_call(&coind->rpc, "submitblock", params, coind);

	free(params);
	if(!json) return false;

	json_value *json_error = json_get_object(json, "error");
	if(json_error && json_error->type != json_null)
	{
		const char *p = json_get_string(json_error, "message");
		if(p) stratumlog("ERROR %s %s\n", coind->name, p);

	//	job_reset();
		json_value_free(json);

		return false;
	}

	json_value *json_result = json_get_object(json, "result");

	bool b = json_result && json_result->type == json_null;
	json_value_free(json);

	return b;
}

bool coind_submitblocktemplate(YAAMP_COIND *coind, const char *block)
{
	int paramlen = strlen(block);

	char *params = (char *)malloc(paramlen+1024);
	if(!params) return false;

	sprintf(params, "[{\"mode\": \"submit\", \"data\": \"%s\"}]", block);
	json_value *json = rpc_call(&coind->rpc, "getblocktemplate", params, coind);

	free(params);
	if(!json) return false;

	json_value *json_error = json_get_object(json, "error");
	if(json_error && json_error->type != json_null)
	{
		const char *p = json_get_string(json_error, "message");
		if(p) stratumlog("ERROR %s %s\n", coind->name, p);

	//	job_reset();
		json_value_free(json);

		return false;
	}

	json_value *json_result = json_get_object(json, "result");

	bool b = json_result && json_result->type == json_null;
	json_value_free(json);

	return b;
}

bool coind_submit(YAAMP_COIND *coind, const char *block)
{
	bool b;

	if(coind->usegetwork) // DCR
		b = coind_submitwork(coind, block);
	else if(coind->hassubmitblock)
		b = coind_submitblock(coind, block);
	else
		b = coind_submitblocktemplate(coind, block);

	return b;
}

bool coind_submitgetauxblock(YAAMP_COIND *coind, const char *hash, const char *block)
{
	int paramlen = strlen(block);

	char *params = (char *)malloc(paramlen+1024);
	if(!params) return false;

	sprintf(params, "[\"%s\",\"%s\"]", hash, block);
	json_value *json = NULL;
	if ((strcmp(coind->symbol, "XMY") == 0) || (strcmp(coind->symbol2, "XMY") == 0) ||
		(strcmp(coind->symbol, "QBC") == 0) || (strcmp(coind->symbol2, "QBC") == 0) ||
		(strcmp(coind->symbol, "LCN") == 0)
		)
		json = rpc_call(&coind->rpc, "submitauxblock", params, coind);
	else
		json = rpc_call(&coind->rpc, "getauxblock", params, coind);

	free(params);
	if(!json) return false;

	json_value *json_error = json_get_object(json, "error");
	if(json_error && json_error->type != json_null)
	{
		const char *p = json_get_string(json_error, "message");
		if(p) stratumlog("ERROR %s %s\n", coind->name, p);

	//	job_reset();
		json_value_free(json);

		return false;
	}

	json_value *json_result = json_get_object(json, "result");
	bool b = json_result && json_result->type == json_boolean && json_result->u.boolean;
	// some auxpow coins return error:null, result: null on success
	if(!b)
		b=json_result && json_result->type == json_null;

	json_value_free(json);
	return b;
}

