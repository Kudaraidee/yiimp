// Ethash / Etchash work source, job template, protocol and share handling.
// Distributed under the MIT software license.

#include "stratum.h"
#include "params.h"
#include "context.h"
#include "work.h"

#include <kawpow/include/ethash/ethash.hpp>
#include <kawpow/helpers.hpp>

#include <math.h>
#include <string.h>

static bool ethash_parse_hex(const char *src, uint8_t be[32])
{
	return ethash_parse_hex_target(src, be);
}

static void ethash_bin_to_hex(const uint8_t be[32], char *hex66)
{
	static const char *digits = "0123456789abcdef";
	hex66[0] = '0';
	hex66[1] = 'x';
	for (int i = 0; i < 32; i++)
	{
		hex66[2 + i * 2] = digits[be[i] >> 4];
		hex66[3 + i * 2] = digits[be[i] & 0xf];
	}
	hex66[66] = 0;
}

static const char *ethash_strip_prefix(const char *src)
{
	if (src && (!strncmp(src, "0x", 2) || !strncmp(src, "0X", 2)))
		return src + 2;
	return src ? src : "";
}

static ethash::hash256 ethash_to_hash256(const char *hex)
{
	ethash::hash256 h;
	memset(&h, 0, sizeof(h));
	uint8_t be[32];
	if (ethash_parse_hex(hex, be))
		memcpy(h.bytes, be, 32);
	return h;
}

static bool ethash_rpc_hex(const char *method, YAAMP_COIND *coind, char *out, size_t outlen)
{
	bool result = false;
	json_value *json = rpc_call(&coind->rpc, method, "[]", coind);
	if (!json)
		return false;

	json_value *res = json_get_object(json, "result");
	if (res && res->type == json_string && res->u.string.ptr)
	{
		snprintf(out, outlen, "%s", res->u.string.ptr);
		result = true;
	}
	json_value_free(json);
	return result;
}

bool ethash_build_template(YAAMP_COIND *coind, YAAMP_JOB_TEMPLATE *templ)
{
	if (!coind || !templ)
		return false;

	json_value *json = rpc_call(&coind->rpc, "eth_getWork", "[]", coind);
	if (!json)
	{
		debuglog("ethash: %s eth_getWork failed\n", coind->symbol);
		return false;
	}

	json_value *work = json_get_object(json, "result");
	if (!work || work->type != json_array || work->u.array.length < 3)
	{
		debuglog("ethash: %s eth_getWork returned no work\n", coind->symbol);
		json_value_free(json);
		return false;
	}

	for (int i = 0; i < 3; i++)
	{
		json_value *v = work->u.array.values[i];
		if (!v || v->type != json_string || !v->u.string.ptr)
		{
			json_value_free(json);
			return false;
		}
	}

	char header_str[80] = { 0 }, seed_str[80] = { 0 }, target_str[80] = { 0 };
	snprintf(header_str, sizeof(header_str), "%s", work->u.array.values[0]->u.string.ptr);
	snprintf(seed_str, sizeof(seed_str), "%s", work->u.array.values[1]->u.string.ptr);
	snprintf(target_str, sizeof(target_str), "%s", work->u.array.values[2]->u.string.ptr);
	json_value_free(json);

	uint8_t header_be[32], seed_be[32], target_be[32];
	const char *header = ethash_strip_prefix(header_str);
	const char *seed = ethash_strip_prefix(seed_str);
	const char *target = ethash_strip_prefix(target_str);

	if (!ethash_parse_hex(header, header_be) ||
		!ethash_parse_hex(seed, seed_be) ||
		!ethash_parse_hex(target, target_be))
	{
		debuglog("ethash: %s eth_getWork returned malformed work\n", coind->symbol);
		return false;
	}

	// eth_getWork has no height; use the pending block number when available.
	int height = 0;
	char number[64] = { 0 };
	if (ethash_rpc_hex("eth_blockNumber", coind, number, sizeof(number)))
	{
		const char *p = ethash_strip_prefix(number);
		if (*p)
			height = (int) strtoul(p, NULL, 16) + 1;
	}

	// The seed hash is authoritative: it identifies the epoch the node is
	// actually mining on, including across ECIP-1099 forks.
	int epoch = ethash::find_epoch_number(ethash_to_hash256(seed));
	if (epoch < 0)
		epoch = ethash_epoch_for_height(height);

	if (strcmp(to_hex(ethash::calculate_epoch_seed(epoch)).c_str(), seed) != 0)
	{
		const int from_height = ethash_epoch_for_height(height);
		stratumlog("ethash: seed/epoch mismatch for %s (seed %d, height %d)\n",
			coind->symbol, epoch, from_height);
		epoch = from_height;
	}

	ethash_bin_to_hex(header_be, templ->eth_header);
	ethash_bin_to_hex(seed_be, templ->eth_seed);
	ethash_bin_to_hex(target_be, templ->eth_target);
	templ->eth_epoch = epoch;
	templ->height = height;
	templ->txcount = 0;
	templ->created = time(NULL);

	templ->header_hash = uint256S(std::string(header, 64));
	templ->header_seed = uint256S(std::string(seed, 64));

	double network_diff = 0;
	ethash_target_to_diff(target_be, &network_diff);
	coind->difficulty = network_diff;

	return true;
}

void ethash_job_mining_notify_buffer(YAAMP_JOB *job, YAAMP_CLIENT *client, char *buffer)
{
	YAAMP_JOB_TEMPLATE *templ = job->templ;

	// EthereumStratum/1.0.0: [jobId, seedHash, headerHash, cleanJobs]
	// Extra fields (height/target) are intentionally omitted; difficulty is
	// delivered through mining.set_difficulty.
	sprintf(buffer,
		"{\"id\":null,\"method\":\"mining.notify\",\"params\":"
		"[\"%x\",\"%s\",\"%s\",true]}\n",
		job->id, templ->eth_seed, templ->eth_header);
}

bool ethash_submit_work(YAAMP_COIND *coind, const char *nonce,
	const char *header, const char *mix)
{
	char params[512];
	snprintf(params, sizeof(params), "[\"%s\",\"%s\",\"%s\"]", nonce, header, mix);

	json_value *json = rpc_call(&coind->rpc, "eth_submitWork", params, coind);
	if (!json)
		return false;

	json_value *res = json_get_object(json, "result");
	bool accepted = res && res->type == json_boolean && res->u.boolean;
	json_value_free(json);
	return accepted;
}

bool ethash_submit(YAAMP_CLIENT *client, json_value *json_params)
{
	// EthereumStratum/1.0.0: [worker, jobId, nonce] (nonce without our prefix)
	// Some miners also send a fourth parameter with the mix digest.
	if (!json_params || json_params->type != json_array ||
		json_params->u.array.length < 3 || json_params->u.array.length > 4 ||
		!valid_string_params(json_params))
	{
		debuglog("%s - %s ethash: bad submit\n", client->username, client->sock->ip);
		client->submit_bad++;
		return false;
	}

	const char *nonce_part = ethash_strip_prefix(
		json_params->u.array.values[2]->u.string.ptr);
	const int jobid = htoi(json_params->u.array.values[1]->u.string.ptr);
	const int prefix_len = strlen(client->extranonce1);

	if (prefix_len <= 0 || strlen(nonce_part) + prefix_len != 16 ||
		!ishexa((char *) nonce_part, strlen(nonce_part)))
	{
		client_send_error(client, 20, "Invalid nonce");
		client->submit_bad++;
		return true;
	}

	char nonce[20] = { 0 };
	const int written = snprintf(nonce, sizeof(nonce), "%s%s", client->extranonce1, nonce_part);
	if (written < 0 || written >= (int) sizeof(nonce) || !ishexa(nonce, 16))
	{
		client_send_error(client, 20, "Invalid nonce");
		client->submit_bad++;
		return true;
	}
	const uint64_t nonce64 = strtoull(nonce, NULL, 16);

	YAAMP_JOB *job = (YAAMP_JOB *) object_find(&g_list_job, jobid, true);
	if (!job)
	{
		client_send_error(client, 20, "Invalid job id");
		client->submit_bad++;
		return true;
	}

	YAAMP_JOB_TEMPLATE *templ = job->templ;
	if (!templ || !templ->eth_header[0])
	{
		object_unlock(job);
		client_send_error(client, 20, "Invalid job");
		return true;
	}

	ethash_context_ptr context = ethash_get_context(templ->eth_epoch);
	if (!context)
	{
		object_unlock(job);
		client_send_error(client, 20, "No epoch context");
		return true;
	}

	const ethash::hash256 header = ethash_to_hash256(templ->eth_header);
	const ethash::result result = ethash::hash(*context, header, nonce64);

	uint8_t final_be[32], mix_be[32];
	memcpy(final_be, result.final_hash.bytes, 32);
	memcpy(mix_be, result.mix_hash.bytes, 32);

	uint256 final_hash = ethash_be_to_uint256(final_be);
	uint8_t share_be[32];
	ethash_diff_to_target(client->difficulty_actual, share_be);
	const uint256 share_target = ethash_be_to_uint256(share_be);

	char mixhex[80] = { 0 };
	ethash_bin_to_hex(mix_be, mixhex);

	if (json_params->u.array.length >= 4)
	{
		const ethash::hash256 submitted_mix = ethash_to_hash256(
			json_params->u.array.values[3]->u.string.ptr);
		if (memcmp(submitted_mix.bytes, mix_be, 32) != 0)
		{
			object_unlock(job);
			client_submit_error(client, job, 27, "Invalid mix digest",
				templ->eth_header, mixhex, nonce);
			return true;
		}
	}

	if (final_hash > share_target)
	{
		object_unlock(job);
		client_submit_error(client, job, 26, "Low difficulty share",
			templ->eth_header, mixhex, nonce);
		return true;
	}

	if (share_find(job->id, templ->eth_header, mixhex, nonce, client->username))
	{
		object_unlock(job);
		client_submit_error(client, job, 22, "Duplicate share",
			templ->eth_header, mixhex, nonce);
		return true;
	}

	uint8_t network_be[32];
	if (!ethash_parse_hex(templ->eth_target, network_be))
		memset(network_be, 0xff, 32);
	const uint256 network_target = ethash_be_to_uint256(network_be);

	if (final_hash <= network_target && !job->block_found)
	{
		YAAMP_COIND *coind = job->coind;
		if (coind && ethash_submit_work(coind, nonce, templ->eth_header, mixhex))
		{
			job->block_found = true;
			double network_diff = 0;
			ethash_target_to_diff(network_be, &network_diff);
			double share_diff = 0;
			ethash_target_to_diff(final_be, &share_diff);

			stratumlog("*** ACCEPTED %s %d by %s (id: %d)\n",
				coind->name, templ->height, client->sock->ip, client->userid);

			block_add(client->userid, client->workerid, coind->id, templ->height,
				network_diff, share_diff, final_hash.ToString().c_str(),
				final_hash.ToString().c_str(), 0, client->solo);
		}
		else
		{
			debuglog("*** REJECTED %s block %d\n",
				coind ? coind->symbol : "?", templ->height);
			rejectlog("REJECTED %s block %d\n", coind ? coind->symbol : "?", templ->height);
		}
		job_signal();
	}

	double share_diff = 0;
	ethash_target_to_diff(final_be, &share_diff);
	stratumlog("client %d on job %d sent diff %0.3f powhash %s\n",
		client->userid, job->id, share_diff, final_hash.ToString().c_str());

	client_send_result(client, "true");
	client_record_difficulty(client);
	client->submit_bad = 0;
	client->shares++;

	share_add(client, job, true, templ->eth_header, templ->ntime, nonce,
		share_diff, 0, templ->height);

	object_unlock(job);
	return true;
}
