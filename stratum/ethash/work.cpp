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
#include <strings.h>

bool ethash_submit_work(YAAMP_COIND *coind, const char *nonce,
	const char *header, const char *mix);

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

const char *ethash_strip_prefix(const char *src)
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
		// The seed hash is what the miner sees in mining.notify, so it is the
		// authoritative source for the verification context. Falling back to
		// the height-derived epoch here would verify against a different DAG
		// and reject every share on chains with custom epoch timing.
		stratumlog("ethash: seed/epoch mismatch for %s (seed epoch %d, height %d)\n",
			coind->symbol, epoch, ethash_epoch_for_height(height));
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
	// NiceHash's spec omits the 0x prefix; Rigel accepts the prefixed form
	// used by its object-shaped subscribe response.
	const char *seed = templ->eth_seed;
	const char *header = templ->eth_header;
	if (client && client->is_nicehash_ethstratum)
	{
		seed = ethash_strip_prefix(seed);
		header = ethash_strip_prefix(header);
	}

	sprintf(buffer,
		"{\"id\":null,\"method\":\"mining.notify\",\"params\":"
		"[\"%x\",\"%s\",\"%s\",true]}\n",
		job->id, seed, header);
}

// ---------------------------------------------------------------------------
// ethproxy compatibility
//
// T-Rex identifies its EthereumStratum connections with "stratum1" but still
// speaks ethproxy on the wire: eth_submitLogin / eth_getWork / eth_submitWork.
// Rigel uses mining.subscribe/mining.submit and keeps the existing path above.
// ---------------------------------------------------------------------------

static void ethash_ethproxy_target_hex(YAAMP_CLIENT *client, char *hex66)
{
	uint8_t be[32];
	if (client && !client->share_target.IsNull())
		ethash_uint256_to_be(client->share_target, be);
	else
		ethash_diff_to_target(client ? client->difficulty_actual : 0, be);

	ethash_bin_to_hex(be, hex66);
}

static void ethash_ethproxy_work_result(YAAMP_JOB *job, YAAMP_CLIENT *client, char *result)
{
	char target[80] = { 0 };
	ethash_ethproxy_target_hex(client, target);

	snprintf(result, 512, "[\"%s\",\"%s\",\"%s\"]",
		job->templ->eth_header, job->templ->eth_seed, target);
}

void ethash_ethproxy_work_buffer(YAAMP_JOB *job, YAAMP_CLIENT *client, char *buffer)
{
	char result[512] = { 0 };
	ethash_ethproxy_work_result(job, client, result);

	// Unsolicited work push, matching the shape geth's ethproxy uses.
	sprintf(buffer, "{\"jsonrpc\":\"2.0\",\"result\":%s}\n", result);
}

bool ethash_ethproxy_send_work(YAAMP_CLIENT *client)
{
	YAAMP_JOB *job = job_get_last_for_ethash(client);
	if (!job) return false;

	char buffer[1024] = { 0 };
	ethash_ethproxy_work_buffer(job, client, buffer);
	socket_send_raw(client->sock, buffer, strlen(buffer));

	client->jobid_sent = job->id;
	return true;
}

bool ethash_ethproxy_get_work(YAAMP_CLIENT *client)
{
	if (!g_list_client.Find(client))
	{
		client_send_error(client, 20, "Not authorized");
		return true;
	}

	YAAMP_JOB *job = job_get_last_for_ethash(client);
	if (!job || !job->templ || !job->templ->eth_header[0])
	{
		client_send_error(client, 20, "No work available");
		return true;
	}

	char result[512] = { 0 };
	ethash_ethproxy_work_result(job, client, result);
	client_send_result(client, "%s", result);

	client->jobid_sent = job->id;
	return true;
}

bool ethash_ethproxy_submit_work(YAAMP_CLIENT *client, json_value *json_params)
{
	// eth_submitWork(nonce, powHash, mixDigest), all 0x-prefixed hex strings.
	if (!json_params || json_params->type != json_array ||
		json_params->u.array.length < 3 || !valid_string_params(json_params))
	{
		debuglog("%s - %s ethproxy: bad submit\n", client->username, client->sock->ip);
		client->submit_bad++;
		client_send_result(client, "false");
		return true;
	}

	const char *nonce_part = ethash_strip_prefix(
		json_params->u.array.values[0]->u.string.ptr);
	const char *header = json_params->u.array.values[1]->u.string.ptr;
	const char *mix_part = ethash_strip_prefix(
		json_params->u.array.values[2]->u.string.ptr);

	char nonce[20] = { 0 };
	int written = -1;
	const size_t part_len = strlen(nonce_part);
	const int prefix_len = strlen(client->extranonce1);

	if (part_len == 16)
		written = snprintf(nonce, sizeof(nonce), "%s", nonce_part);
	else if (prefix_len > 0 && part_len + (size_t) prefix_len == 16)
		written = snprintf(nonce, sizeof(nonce), "%s%s", client->extranonce1, nonce_part);

	uint8_t submitted_mix_be[32];
	if (written < 0 || written >= (int) sizeof(nonce) || !ishexa(nonce, 16) ||
		!ethash_parse_hex(mix_part, submitted_mix_be))
	{
		debuglog("%s - %s ethproxy: invalid submit params\n", client->username, client->sock->ip);
		client->submit_bad++;
		client_send_result(client, "false");
		return true;
	}

	// ethproxy has no job id: match the submitted PoW hash to a live template.
	const char *header_cmp = ethash_strip_prefix(header);
	YAAMP_JOB *job = NULL;
	g_list_job.Enter();
	for (CLI li = g_list_job.first; li; li = li->next)
	{
		YAAMP_JOB *candidate = (YAAMP_JOB *) li->data;
		if (!candidate->templ || !candidate->templ->eth_header[0]) continue;
		if (strcasecmp(ethash_strip_prefix(candidate->templ->eth_header), header_cmp)) continue;

		object_lock(candidate);
		job = candidate;
		break;
	}
	g_list_job.Leave();

	if (!job)
	{
		debuglog("%s - %s ethproxy: unknown job header %s\n",
			client->username, client->sock->ip, header);
		client->submit_bad++;
		client_send_result(client, "false");
		return true;
	}

	YAAMP_JOB_TEMPLATE *templ = job->templ;

	auto reject_share = [&](const char *message, int error_number)
	{
		debuglog("%s - %s ethproxy: %s\n", client->username, client->sock->ip, message);
		client_send_result(client, "false");
		share_add(client, job, false, templ->eth_header, templ->ntime,
			nonce, 0, error_number, templ->height);
		client->submit_bad++;
		object_unlock(job);
	};

	ethash_context_ptr context = ethash_get_context(templ->eth_epoch);
	if (!context)
	{
		reject_share("no epoch context", 20);
		return true;
	}

	const ethash::hash256 header_hash = ethash_to_hash256(templ->eth_header);
	const uint64_t nonce64 = strtoull(nonce, NULL, 16);
	const ethash::result result = ethash::hash(*context, header_hash, nonce64);

	uint8_t final_be[32], mix_be[32];
	memcpy(final_be, result.final_hash.bytes, 32);
	memcpy(mix_be, result.mix_hash.bytes, 32);

	if (memcmp(submitted_mix_be, mix_be, 32) != 0)
	{
		reject_share("invalid mix digest", 27);
		return true;
	}

	uint256 final_hash = ethash_be_to_uint256(final_be);
	uint8_t share_be[32];
	ethash_diff_to_target(client->difficulty_actual, share_be);
	const uint256 share_target = ethash_be_to_uint256(share_be);

	if (final_hash > share_target)
	{
		reject_share("low difficulty share", 26);
		return true;
	}

	char mixhex[80] = { 0 };
	ethash_bin_to_hex(mix_be, mixhex);

	if (share_find(job->id, templ->eth_header, mixhex, nonce, client->username))
	{
		reject_share("duplicate share", 22);
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
			double block_share_diff = 0;
			ethash_target_to_diff(final_be, &block_share_diff);

			stratumlog("*** ACCEPTED %s %d by %s (id: %d)\n",
				coind->name, templ->height, client->sock->ip, client->userid);

			block_add(client->userid, client->workerid, coind->id, templ->height,
				network_diff, block_share_diff, final_hash.ToString().c_str(),
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
	// EthereumStratum/1.0.0: [worker, jobId, nonce] (usually the full nonce,
	// including the extranonce from mining.subscribe).
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
	const size_t part_len = strlen(nonce_part);

	// EthereumStratum miners normally send the complete 8-byte nonce: the
	// extranonce1 from mining.subscribe is already included. Accept the older
	// form where only the miner-controlled part is submitted as well.
	char nonce[20] = { 0 };
	int written = -1;
	if (part_len == 16)
		written = snprintf(nonce, sizeof(nonce), "%s", nonce_part);
	else if (prefix_len > 0 && part_len + (size_t) prefix_len == 16)
		written = snprintf(nonce, sizeof(nonce), "%s%s", client->extranonce1, nonce_part);

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
