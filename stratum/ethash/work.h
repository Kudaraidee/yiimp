// Ethash / Etchash work source, job template, protocol and share handling.
// Distributed under the MIT software license.

#ifndef YAAMP_ETHASH_WORK_H
#define YAAMP_ETHASH_WORK_H

bool ethash_build_template(YAAMP_COIND *coind, YAAMP_JOB_TEMPLATE *templ);
void ethash_job_mining_notify_buffer(YAAMP_JOB *job, YAAMP_CLIENT *client, char *buffer);

// ethproxy compatibility (T-Rex uses eth_submitLogin/eth_getWork/
// eth_submitWork for both ethstratum+tcp and stratum+tcp on ethash).
void ethash_ethproxy_work_buffer(YAAMP_JOB *job, YAAMP_CLIENT *client, char *buffer);
bool ethash_ethproxy_send_work(YAAMP_CLIENT *client);
bool ethash_ethproxy_get_work(YAAMP_CLIENT *client);
bool ethash_ethproxy_submit_work(YAAMP_CLIENT *client, json_value *json_params);

bool ethash_submit(YAAMP_CLIENT *client, json_value *json_params);

#endif // YAAMP_ETHASH_WORK_H
