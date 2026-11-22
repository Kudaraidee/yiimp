#ifndef YAAMP_HOMESCRYPT_H
#define YAAMP_HOMESCRYPT_H

#include <stddef.h>

// HomeScrypt v1.2 consensus hash. The input is the serialized 80-byte
// Bitcoin-style block header; the output is the 32-byte digest in the same
// byte order produced by Lumenite Core's CSHA256::Finalize().
void homescrypt_v12(const unsigned char *input, unsigned char *output);

// Size of the per-hash scratchpad, exposed for diagnostics.
size_t homescrypt_v12_scratchpad_bytes();

#endif
