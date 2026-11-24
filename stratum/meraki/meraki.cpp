// Meraki (Telestai) Progpow stratum integration.
// Protocol, header layout and block submission follow the Kawpow/Progpow
// family already used by Yiimp; the PoW verifier is the isolated libmeraki
// port under stratum/meraki/lib.

#include "stratum.h"
#include "meraki/hash.h"

extern void kawpow_block(YAAMP_CLIENT* client, YAAMP_JOB* job, YAAMP_JOB_TEMPLATE* templ,
    const char* nonce64, const char* mixhash, uint256& blockhash);

bool meraki_submit(YAAMP_CLIENT* client, json_value* json_params)
{
    // submit(worker_name, jobid, nonce, header, mixhash):
    if (json_params->u.array.length < 5 || !valid_string_params(json_params)) {
        debuglog("%s - %s bad message\n", client->username, client->sock->ip);
        client->submit_bad++;
        return false;
    }

    char nonce[128], header[128], mixhash[128];
    memset(nonce, 0, sizeof(nonce));
    memset(header, 0, sizeof(header));
    memset(mixhash, 0, sizeof(mixhash));

    if (strlen(json_params->u.array.values[1]->u.string.ptr) > 32) {
        clientlog(client, "bad json, wrong jobid len");
        client->submit_bad++;
        return false;
    }

    int jobid = htoi(json_params->u.array.values[1]->u.string.ptr);

    int offset = 0;
    if (!memcmp(json_params->u.array.values[2]->u.string.ptr, "0x", 2))
        offset = 2;

    strncpy(nonce, json_params->u.array.values[2]->u.string.ptr + offset, 16);
    strncpy(header, json_params->u.array.values[3]->u.string.ptr + offset, 64);
    strncpy(mixhash, json_params->u.array.values[4]->u.string.ptr + offset, 64);

    string_lower(nonce);
    string_lower(header);
    string_lower(mixhash);

    char* submit_ntime = (char*)"";

    YAAMP_JOB* job = (YAAMP_JOB*)object_find(&g_list_job, jobid, true);
    if (!job) {
        client_submit_error(client, NULL, 21, "Invalid job id", header, submit_ntime, nonce);
        return true;
    } else if (job->deleted) {
        client_send_result(client, "true");
        object_unlock(job);
        return true;
    }

    if (!job->coind) {
        object_unlock(job);
        return false;
    }

    int coinid = job->coind->id;
    YAAMP_JOB_TEMPLATE* templ = job->templ;
    if (templ) submit_ntime = templ->ntime;

    if (strlen(nonce) != YAAMP_KAWPOW_NONCE_SIZE * 2 || !ishexa(nonce, YAAMP_KAWPOW_NONCE_SIZE * 2)) {
        client_submit_error(client, job, 20, "Invalid nonce size", header, submit_ntime, nonce);
        object_unlock(job);
        return true;
    }

    if (!kawpow_check_nonceprefix(client, nonce)) {
        client_submit_error(client, job, 20, "Invalid nonce prefix", header, submit_ntime, nonce);
        object_unlock(job);
        return true;
    }

    if ((strlen(header) != 64 || !ishexa(header, 64)) || (strlen(mixhash) != 64 || !ishexa(mixhash, 64))) {
        client_submit_error(client, job, 20, "Invalid header/mixhash", header, submit_ntime, nonce);
        object_unlock(job);
        return true;
    }

    // Telestai's miner must echo the exact header hash from mining.notify.
    if (templ) {
        char expected_header[65] = { 0 };
        snprintf(expected_header, sizeof(expected_header), "%s",
            templ->header_hash.ToString().c_str());
        string_lower(expected_header);
        if (strcmp(header, expected_header)) {
            client_submit_error(client, job, 20, "Invalid header hash", header, submit_ntime, nonce);
            object_unlock(job);
            return true;
        }
    }

    YAAMP_SHARE* share = share_find(job->id, header, mixhash, nonce, client->username);
    if (share) {
        client_submit_error(client, job, 22, "Duplicate share", header, submit_ntime, nonce);
        object_unlock(job);
        return true;
    }

    const std::string header_str(header);
    const std::string mixhash_str(mixhash);
    const std::string nonce_str(nonce);

    std::string mixhash_calc;
    uint256 hash = meraki_hash(header_str, nonce_str, mixhash_calc, coinid);

    if (mixhash_calc != mixhash_str) {
        client_submit_error(client, job, 25, "bad mix-hash", header, submit_ntime, nonce);
        object_unlock(job);
        return true;
    }

    double share_diff = meraki_target_to_diff(hash);

    stratumlog("client %d on job %d sent diff %0.3f powhash %s\n",
        client->userid, job->id, share_diff, hash.ToString().c_str());

    if (hash > client->share_target) {
        client_submit_error(client, job, 26, "Low difficulty share", header, submit_ntime, nonce);
        object_unlock(job);
        return true;
    }

    uint256 coin_target;
    decode_nbits(coin_target, (uint32_t)strtoul(templ->nbits, NULL, 16));

    if (hash <= coin_target) {
        kawpow_block(client, job, templ, nonce, mixhash, hash);
        job_signal();
    }

    client_send_result(client, "true");
    client_record_difficulty(client);
    client->submit_bad = 0;
    client->shares++;

    share_add(client, job, true, header, submit_ntime, nonce, share_diff, 0, templ->height);

    object_unlock(job);
    return true;
}
