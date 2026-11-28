#ifndef KAWPOW_KAWPOW_H
#define KAWPOW_KAWPOW_H

void get_nonce_prefix(char *extraonce1);
bool kawpow_send_nonceprefix(YAAMP_CLIENT* client);
void kawpow_target_diff(double difficulty, uint256 target);
uint256 get_kawpow_seed(int height);
void kawpow_job_mining_notify_buffer(YAAMP_JOB* job, YAAMP_CLIENT* client, char* buffer, int gen = 0);
uint256 build_header_hash(YAAMP_JOB_TEMPLATE *templ);
bool kawpow_submit(YAAMP_CLIENT* client, json_value* json_params);
bool kawpow_check_nonceprefix(YAAMP_CLIENT* client, std::string nonce);

std::string kawpow_build_block(YAAMP_JOB_TEMPLATE *templ, const char *nonce64, const char *mixhash);

#endif // KAWPOW_KAWPOW_H
