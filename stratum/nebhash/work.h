// NEB work-service client, neb-pool/1 protocol and share verification.
// The PoW engine in this directory is the verbatim C reference from the NEB
// repository (Apache-2.0); this file is the Yiimp integration around it.

#ifndef YAAMP_NEBHASH_WORK_H
#define YAAMP_NEBHASH_WORK_H

#include <stdint.h>

// Start the work-service reader and load the pinned dataset file. Safe to call
// only after job_init() (new jobs call job_signal()).
bool nebhash_init();

// A subscribe without a node job is rejected, as in the reference pool.
bool nebhash_current_job_ready();

// Initial session target is share_target << start_k, never the generic Yiimp
// default difficulty of 16. The bytes are lower-endian as on the NEB wire.
void nebhash_initial_target(uint8_t le[32]);
double nebhash_initial_difficulty();

// Per-session vardiff state and target publication.
void nebhash_client_init(YAAMP_CLIENT *client);

// Extranonce2 length advertised by the current node job (extranonce_size - 4).
int nebhash_extranonce2_size();

// neb-pool/1 allows up to 8 logins per session (dev-fee miners use a second).
// Only '.' separates an optional worker suffix; empty workers map to "default".
void nebhash_split_login(const char *login, char address[1088], char worker[1088]);
// Returns false for an invalid address/worker or too many logins.
bool nebhash_add_login(YAAMP_CLIENT *client, const char *login, const char *password);
bool nebhash_login_authorized(YAAMP_CLIENT *client, const char *login);
// Switch session accounting to an already-authorized login (dev-fee shares).
bool nebhash_select_login(YAAMP_CLIENT *client, const char *login);
void nebhash_clear_workers(YAAMP_CLIENT *client);

// NEB's JSON-RPC error shape: result null and a two-element error array.
int nebhash_send_error(YAAMP_CLIENT *client, int code, const char *message);
void nebhash_client_set_target_bytes(YAAMP_CLIENT *client, const uint8_t le[32]);
// Store the target without sending mining.set_target (used while subscribing).
void nebhash_client_set_target_silent(YAAMP_CLIENT *client, const uint8_t le[32]);
// Publish the exact 256-bit target already stored on the client.
void nebhash_client_publish_target(YAAMP_CLIENT *client);
// Returns true when a vardiff change needs the current job pushed again.
bool nebhash_vardiff(YAAMP_CLIENT *client, YAAMP_JOB *job);
// Same 60 s window, called from the periodic client loop for sessions that
// have not submitted anything yet, so a too-hard initial target can recover.
bool nebhash_vardiff_tick(YAAMP_CLIENT *client);

// Rate limiting and consecutive-bad-submit tracking from the reference pool.
// The caller sends the NEB error and closes the socket when either returns a
// fatal result; objects are never deleted underneath the client thread.
bool nebhash_submit_rate_ok(YAAMP_CLIENT *client);
bool nebhash_note_bad_submit(YAAMP_CLIENT *client);
void nebhash_note_good_submit(YAAMP_CLIENT *client);

// Copy the newest work-service job into a Yiimp template.
bool nebhash_build_template(YAAMP_COIND *coind, YAAMP_JOB_TEMPLATE *templ);

// mining.submit from a neb-pool/1 client.
bool nebhash_submit(YAAMP_CLIENT *client, json_value *json_params);

// Subscribe result and notifications.
void nebhash_subscribe_result(YAAMP_CLIENT *client, char *out, size_t size);
// Builds the complete notification (including target/model pushes). The string
// form avoids truncating large branch lists in yiimp's fixed 32 KiB buffers.
string nebhash_job_mining_notify(YAAMP_JOB *job, YAAMP_CLIENT *client);
void nebhash_job_mining_notify_buffer(YAAMP_JOB *job, YAAMP_CLIENT *client, char *buffer, size_t size);

// 256-bit LE ticket targets. Yiimp difficulties are work ratios,
// work = 2^256 / (target + 1), matching the node's Target::work().
void nebhash_diff_to_target(double diff, uint8_t le[32]);
uint256 nebhash_diff_to_uint256(double diff);
double nebhash_target_to_diff(const uint8_t le[32]);
double nebhash_ticket_to_diff(const uint8_t ticket[32]);
void nebhash_target_to_hex(const uint8_t le[32], char hex[65]);

// Parse a 64-character little-endian target and return its work ratio.
double nebhash_target_hex_to_diff(const char *hex);

// Convenience used by client_difficulty.cpp.
void nebhash_client_set_target(YAAMP_CLIENT *client, double difficulty);

// bech32m address validation for neb1... wallet logins.
bool nebhash_valid_address(const char *address);

// Cached chain id from the node's getinfo RPC (hex, no 0x).
void nebhash_get_chain_id(char hex[65]);

#endif // YAAMP_NEBHASH_WORK_H
