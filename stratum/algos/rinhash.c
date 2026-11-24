#include "rinhash.h"
#include <string.h>
#include <stdint.h>
#include <stdio.h>
#include <malloc.h>  // _aligned_malloc, _aligned_free
#include "blake3/blake3.h"
#include "blake3/blake3_impl.h"
#include "sha3/SimpleFIPS202.h"
#include "ar2/argon2.h"  // Update path to argon2d header

void rinhash_hash(const char* input, char* output, uint32_t len)
{
    uint8_t blake3_out[32];
	blake3_hasher hasher;
	blake3_hasher_init(&hasher);
	blake3_hasher_update(&hasher, input, len);
	blake3_hasher_finalize(&hasher, blake3_out, 32);

    // Argon2d parameters
    const char* salt_str = "RinCoinSalt";
    uint8_t argon2_out[32];
    argon2_context context = {0};
    context.out = argon2_out;
    context.outlen = 32;
    context.pwd = blake3_out;
    context.pwdlen = 32;
    context.salt = (uint8_t*)salt_str;
    context.saltlen = strlen(salt_str);
    context.t_cost = 2;
    context.m_cost = 64;
    context.lanes = 1;
    context.threads = 1;
    context.version = ARGON2_VERSION_13;
    context.allocate_cbk = NULL;
    context.free_cbk = NULL;
    context.flags = ARGON2_DEFAULT_FLAGS;

    if (argon2d_ctx(&context) != ARGON2_OK) {
        fprintf(stderr, "Argon2d failed!\n");
        memset(output, 0xff, 32);	// fail every target
        return;
    }

    // SHA3-256
    uint8_t sha3_out[32];
    SHA3_256(sha3_out, (const uint8_t *)argon2_out, 32);
    
    memcpy(output, sha3_out, 32);
}
