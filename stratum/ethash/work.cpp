// Ethash / Etchash work source, job template, protocol and share handling.
// Distributed under the MIT software license.

#include "stratum.h"
#include "params.h"
#include "context.h"
#include "engine.h"
#include "work.h"

#include <kawpow/include/ethash/ethash.hpp>
#include <kawpow/helpers.hpp>

#include <math.h>
#include <string.h>
#include <strings.h>

bool ethash_submit_work(YAAMP_COIND *coind, int height, const char *nonce,
	const char *header, const char *mix, char *blockhash, size_t blockhash_size);

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

// geth requires every eth_submitWork argument to be 0x-prefixed. Internally the
// nonce is kept bare for mining/duplicate keys, so add the prefix only here.
static const char *ethash_rpc_hex(const char *src, char *dst, size_t size)
{
	if (!src) src = "";
	if (src[0] == '0' && (src[1] == 'x' || src[1] == 'X'))
		snprintf(dst, size, "%s", src);
	else
		snprintf(dst, size, "0x%s", src);
	return dst;
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

	const ETHASH_ENGINE *engine = ethash_engine();
	json_value *json = rpc_call(&coind->rpc, engine->getwork_method, "[]", coind);
	if (!json)
	{
		debuglog("%s: %s %s failed\n", engine->name, coind->symbol, engine->getwork_method);
		return false;
	}

	json_value *work = json_get_object(json, "result");
	if (!work || work->type != json_array || work->u.array.length < 3)
	{
		debuglog("%s: %s %s returned no work\n", engine->name, coind->symbol, engine->getwork_method);
		json_value_free(json);
		return false;
	}

	const int work_fields = engine->work_includes_height ? 4 : 3;
	if (work->u.array.length < (size_t) work_fields)
	{
		debuglog("%s: %s %s returned short work\n", engine->name, coind->symbol, engine->getwork_method);
		json_value_free(json);
		return false;
	}

	for (int i = 0; i < work_fields; i++)
	{
		json_value *v = work->u.array.values[i];
		if (!v || v->type != json_string || !v->u.string.ptr)
		{
			json_value_free(json);
			return false;
		}
	}

	char header_str[80] = { 0 }, seed_str[80] = { 0 }, target_str[80] = { 0 };
	char height_str[64] = { 0 };
	snprintf(header_str, sizeof(header_str), "%s", work->u.array.values[0]->u.string.ptr);
	snprintf(seed_str, sizeof(seed_str), "%s", work->u.array.values[1]->u.string.ptr);
	snprintf(target_str, sizeof(target_str), "%s", work->u.array.values[2]->u.string.ptr);
	if (engine->work_includes_height)
		snprintf(height_str, sizeof(height_str), "%s", work->u.array.values[3]->u.string.ptr);
	json_value_free(json);

	uint8_t header_be[32], seed_be[32], target_be[32];
	const char *header = ethash_strip_prefix(header_str);
	const char *seed = ethash_strip_prefix(seed_str);
	const char *target = ethash_strip_prefix(target_str);

	if (!ethash_parse_hex(header, header_be) ||
		!ethash_parse_hex(seed, seed_be) ||
		!ethash_parse_hex(target, target_be))
	{
		debuglog("%s: %s %s returned malformed work\n", engine->name, coind->symbol, engine->getwork_method);
		return false;
	}

	// xhash_getWork carries the mined block number directly. Classic
	// eth_getWork has no height, so use the pending block number instead.
	int height = 0;
	if (engine->work_includes_height && height_str[0])
		height = (int) strtoul(ethash_strip_prefix(height_str), NULL, 16);
	else
	{
		char number[64] = { 0 };
		if (ethash_rpc_hex("eth_blockNumber", coind, number, sizeof(number)))
		{
			const char *p = ethash_strip_prefix(number);
			if (*p)
				height = (int) strtoul(p, NULL, 16) + 1;
		}
	}

	// The seed hash identifies the epoch the node is actually mining on.
	// Classic Ethash can reverse it; XHash derives the epoch from the work
	// height and verifies the advertised seed against that epoch below.
	int epoch = engine->epoch_from_work(height, seed_be);

	uint8_t expected_seed[32];
	engine->seed_for_epoch(epoch, expected_seed);
	if (memcmp(expected_seed, seed_be, 32) != 0)
	{
		// The seed hash is what the miner sees in mining.notify, so it must
		// match the verification context: a mismatch would reject every
		// share against a different cache. XHash has no reverse seed lookup,
		// so its height-derived epoch and the node seed have to agree.
		stratumlog("%s: seed/epoch mismatch for %s (epoch %d, height %d)\n",
			engine->name, coind->symbol, epoch, height);
		if (engine->require_exact_seed)
			return false;
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

// XHash miners built on ccminer-tpfuemp use the KawPoW-family wire layout:
// [job_id, header_hash, seed_hash, target, clean, height, nbits]. The job id
// is 16 hex digits so the miner can strip an 8-digit prefix; the suffix stays
// parseable by htoi(). Only ccminer-kudaraidee sessions opt into this shape.
void ethash_kawpow_job_mining_notify_buffer(YAAMP_JOB *job, YAAMP_CLIENT *client, char *buffer, int gen)
{
	YAAMP_JOB_TEMPLATE *templ = job->templ;
	char jobid[32];
	snprintf(jobid, sizeof(jobid), "%08x%08x",
		gen > 0 && gen < YAAMP_RESEND_MAX ? gen : 0, job->id);

	std::string target = client && !client->share_target.IsNull() ?
		client->share_target.ToString() :
		"ffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffff";
	const char *nbits = templ->nbits[0] ? templ->nbits : "";

	sprintf(buffer,
		"{\"id\":null,\"method\":\"mining.notify\",\"params\":"
		"[\"%s\",\"%s\",\"%s\",\"%s\",true,%d,\"%s\"]}\n",
		jobid, templ->eth_header, templ->eth_seed, target.c_str(), templ->height, nbits);
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

// Claymore-style ethproxy advertises the low 232 bits of the target and a
// 24-bit nonce prefix. This is the shape used by BTC.com and expected by
// GMiner when it is started with a bare --server address (no scheme).
static void ethash_ethproxy_claymore_target(char *out, const char *hex66)
{
	snprintf(out, 64, "0x%.58s", hex66 + 8);
}

static void ethash_ethproxy_nonce_prefix(YAAMP_CLIENT *client, char *out)
{
	const char *prefix = (client && client->extranonce1[0]) ? client->extranonce1 : "000000";
	size_t len = strlen(prefix);
	if (len > 6) prefix += len - 6;
	snprintf(out, 16, "%06x", (unsigned int) strtoul(prefix, NULL, 16) & 0xffffff);
}

static void ethash_ethproxy_work_result(YAAMP_JOB *job, YAAMP_CLIENT *client, char *result)
{
	char full_target[80] = { 0 };
	char target[64] = { 0 };
	char nonce_prefix[16] = { 0 };

	ethash_ethproxy_target_hex(client, full_target);
	ethash_ethproxy_claymore_target(target, full_target);
	ethash_ethproxy_nonce_prefix(client, nonce_prefix);

	snprintf(result, 512, "[\"%s\",\"%s\",\"%s\",\"%s\"]",
		job->templ->eth_header, job->templ->eth_seed, target, nonce_prefix);
}

void ethash_ethproxy_work_buffer(YAAMP_JOB *job, YAAMP_CLIENT *client, char *buffer)
{
	char result[512] = { 0 };
	ethash_ethproxy_work_result(job, client, result);

	// Unsolicited work push. id 0 marks it as a notification, matching the
	// ETHPROXY jobs sent by production pools.
	snprintf(buffer, 1024, "{\"id\":0,\"jsonrpc\":\"2.0\",\"result\":%s}\n", result);
}

static int ethash_ethproxy_send_result(YAAMP_CLIENT *client, const char *result)
{
	char id[64] = { 0 };
	if (client->id_str)
		snprintf(id, sizeof(id), "\"%s\"", client->id_str);
	else
		snprintf(id, sizeof(id), "%d", client->id_int);

	return socket_send(client->sock,
		"{\"id\":%s,\"jsonrpc\":\"2.0\",\"result\":%s}\n", id, result);
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
	ethash_ethproxy_send_result(client, result);

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

	uint8_t header_be[32];
	const uint64_t nonce64 = strtoull(nonce, NULL, 16);
	uint8_t final_be[32], mix_be[32];
	if (!ethash_parse_hex(templ->eth_header, header_be))
	{
		reject_share("invalid job header", 20);
		return true;
	}
	ethash_engine()->hash(context.get(), header_be, nonce64, final_be, mix_be);

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
		char blockhash[67] = { 0 };
		if (coind && ethash_submit_work(coind, templ->height, nonce,
			templ->eth_header, mixhex, blockhash, sizeof(blockhash)))
		{
			job->block_found = true;
			double network_diff = 0;
			ethash_target_to_diff(network_be, &network_diff);
			double block_share_diff = 0;
			ethash_target_to_diff(final_be, &block_share_diff);

			stratumlog("*** ACCEPTED %s %d by %s (id: %d)\n",
				coind->name, templ->height, client->sock->ip, client->userid);

			if (blockhash[0]) {
				block_add(client->userid, client->workerid, coind->id, templ->height,
					network_diff, block_share_diff, blockhash, blockhash, 0, client->solo);
				block_confirm(coind->id, blockhash);
			} else {
				debuglog("*** ACCEPTED %s %d but no canonical block (uncle?)\n",
					coind->symbol, templ->height);
			}
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

// After a successful submit, find our block at this height. eth_getBlockByNumber
// only returns the canonical chain, so a mismatch means we lost the race and the
// block became an uncle (which the pool must not account as a won block).
static bool ethash_canonical_block_hash(YAAMP_COIND *coind, int height,
	const char *nonce, char *out, size_t size)
{
	char height_hex[32];
	char params[64];
	snprintf(height_hex, sizeof(height_hex), "0x%x", height);
	snprintf(params, sizeof(params), "[\"%s\",false]", height_hex);

	json_value *json = rpc_call(&coind->rpc, "eth_getBlockByNumber", params, coind);
	if (!json) return false;

	bool found = false;
	json_value *res = json_get_object(json, "result");
	if (res && res->type == json_object)
	{
		const char *node_nonce = json_get_string(res, "nonce");
		const char *hash = json_get_string(res, "hash");
		if (node_nonce && hash && strlen(hash) < size &&
			!strcasecmp(ethash_strip_prefix(node_nonce), ethash_strip_prefix(nonce)))
		{
			snprintf(out, size, "%s", hash);
			found = true;
		}
	}
	json_value_free(json);
	return found;
}

bool ethash_submit_work(YAAMP_COIND *coind, int height, const char *nonce,
	const char *header, const char *mix, char *blockhash, size_t blockhash_size)
{
	char nonce_hex[24], header_hex[68], mix_hex[68];
	char params[512];
	snprintf(params, sizeof(params), "[\"%s\",\"%s\",\"%s\"]",
		ethash_rpc_hex(nonce, nonce_hex, sizeof(nonce_hex)),
		ethash_rpc_hex(header, header_hex, sizeof(header_hex)),
		ethash_rpc_hex(mix, mix_hex, sizeof(mix_hex)));

	json_value *json = rpc_call(&coind->rpc, ethash_engine()->submitwork_method, params, coind);
	if (!json)
		return false;

	json_value *res = json_get_object(json, "result");
	bool accepted = res && res->type == json_boolean && res->u.boolean;
	json_value_free(json);

	if (accepted && blockhash && blockhash_size)
		ethash_canonical_block_hash(coind, height, nonce, blockhash, blockhash_size);

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

	uint8_t header_be[32];
	uint8_t final_be[32], mix_be[32];
	if (!ethash_parse_hex(templ->eth_header, header_be))
	{
		object_unlock(job);
		client_send_error(client, 20, "Invalid job header");
		return true;
	}
	ethash_engine()->hash(context.get(), header_be, nonce64, final_be, mix_be);

	uint256 final_hash = ethash_be_to_uint256(final_be);
	uint8_t share_be[32];
	uint256 share_target;

	// ccminer-kudaraidee mines against the target published with each job.
	// Keep that target for shares sent after a vardiff change so the verifier
	// agrees with what the miner actually saw.
	if (client->is_xhash_kawpow && client_find_job_target(client, jobid, share_target))
	{
		// recorded job target
	}
	else
	{
		ethash_diff_to_target(client->difficulty_actual, share_be);
		share_target = ethash_be_to_uint256(share_be);
	}

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
		char blockhash[67] = { 0 };
		if (coind && ethash_submit_work(coind, templ->height, nonce,
			templ->eth_header, mixhex, blockhash, sizeof(blockhash)))
		{
			job->block_found = true;
			double network_diff = 0;
			ethash_target_to_diff(network_be, &network_diff);
			double share_diff = 0;
			ethash_target_to_diff(final_be, &share_diff);

			stratumlog("*** ACCEPTED %s %d by %s (id: %d)\n",
				coind->name, templ->height, client->sock->ip, client->userid);

			if (blockhash[0]) {
				block_add(client->userid, client->workerid, coind->id, templ->height,
					network_diff, share_diff, blockhash, blockhash, 0, client->solo);
				block_confirm(coind->id, blockhash);
			} else {
				debuglog("*** ACCEPTED %s %d but no canonical block (uncle?)\n",
					coind->symbol, templ->height);
			}
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

bool ethash_kawpow_submit(YAAMP_CLIENT *client, json_value *json_params)
{
	// [worker, job_id, 0x<nonce16>, 0x<header32>, 0x<mix32>]
	if (!json_params || json_params->type != json_array ||
		json_params->u.array.length < 5 || !valid_string_params(json_params))
	{
		debuglog("%s - %s xhash kawpow: bad submit\n",
			client->username, client->sock->ip);
		client->submit_bad++;
		return false;
	}

	const char *nonce = ethash_strip_prefix(
		json_params->u.array.values[2]->u.string.ptr);
	const char *mix = ethash_strip_prefix(
		json_params->u.array.values[4]->u.string.ptr);
	const int jobid = htoi(json_params->u.array.values[1]->u.string.ptr);

	if (jobid <= 0 || strlen(nonce) != 16 || !ishexa((char *) nonce, 16) ||
		strlen(mix) != 64 || !ishexa((char *) mix, 64))
	{
		client_send_error(client, 20, "Invalid XHash submit");
		client->submit_bad++;
		return true;
	}

	// The miner strips an 8-digit job-id prefix, so normalize back to the
	// canonical id before the common verifier records vardiff generation.
	char canonical_jobid[32] = { 0 };
	snprintf(canonical_jobid, sizeof(canonical_jobid), "%x", jobid);

	char normalized[512] = { 0 };
	snprintf(normalized, sizeof(normalized), "[\"xhash\",\"%s\",\"%s\",\"%s\"]",
		canonical_jobid, nonce, mix);

	json_value *params = json_parse(normalized, strlen(normalized));
	if (!params)
	{
		client_send_error(client, 20, "Invalid XHash submit");
		client->submit_bad++;
		return true;
	}

	bool ok = ethash_submit(client, params);
	json_value_free(params);
	return ok;
}
