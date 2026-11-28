#include "x11k.h"
#include <stdlib.h>
#include <stdint.h>
#include <string.h>
#include <stdio.h>

#include "third_party/sph/sph_blake.h"
#include "third_party/sph/sph_bmw.h"
#include "third_party/sph/sph_groestl.h"
#include "third_party/sph/sph_jh.h"
#include "third_party/sph/sph_keccak.h"
#include "third_party/sph/sph_skein.h"
#include "third_party/sph/sph_luffa.h"
#include "third_party/sph/sph_cubehash.h"
#include "third_party/sph/sph_shavite.h"
#include "third_party/sph/sph_simd.h"
#include "third_party/sph/sph_echo.h"

// Use functions defined in x11k.c
extern void *Blake512(void *oHash, const void *iHash, const size_t len);
extern void *Bmw512(void *oHash, const void *iHash, const size_t len);
extern void *Groestl512(void *oHash, const void *iHash, const size_t len);
extern void *Skein512(void *oHash, const void *iHash, const size_t len);
extern void *Jh512(void *oHash, const void *iHash, const size_t len);
extern void *Keccak512(void *oHash, const void *iHash, const size_t len);
extern void *Luffa512(void *oHash, const void *iHash, const size_t len);
extern void *Cubehash512(void *oHash, const void *iHash, const size_t len);
extern void *Shavite512(void *oHash, const void *iHash, const size_t len);
extern void *Simd512(void *oHash, const void *iHash, const size_t len);
extern void *Echo512(void *oHash, const void *iHash, const size_t len);
extern void *fnHashX11K[];
extern void processHash(void *oHash, const void *iHash, const int index, const size_t len);

extern void sha256_double_hash(const char *input, char *output, unsigned int len);

/* ----------- Sapphire 2.0 Hash X11KVS ------------------------------------ */
/* - X11, from the original 11 algos used on DASH -------------------------- */
/* - K, from Kyanite ------------------------------------------------------- */
/* - V, from Variable, variation of the number iterations on the X11K algo - */
/* - S, from Sapphire ------------------------------------------------------ */

#if !HAVE_DECL_LE32DEC
static inline uint32_t le32dec(const void *pp)
{
	const uint8_t *p = (uint8_t const *)pp;
	return ((uint32_t)(p[0]) + ((uint32_t)(p[1]) << 8) +
	    ((uint32_t)(p[2]) << 16) + ((uint32_t)(p[3]) << 24));
}
#endif

#if !HAVE_DECL_LE32ENC
static inline void le32enc(void *pp, uint32_t x)
{
	uint8_t *p = (uint8_t *)pp;
	p[0] = x & 0xff;
	p[1] = (x >> 8) & 0xff;
	p[2] = (x >> 16) & 0xff;
	p[3] = (x >> 24) & 0xff;
}
#endif


const unsigned int HASHX11KV_MIN_NUMBER_ITERATIONS  = 2;
const unsigned int HASHX11KV_MAX_NUMBER_ITERATIONS  = 6;
const unsigned int HASHX11KV_NUMBER_ALGOS           = 11;

void x11kv(void *output, const void *input)
{
	void *hashA = malloc(64);
	void *hashB = malloc(64);

	unsigned char *p;

	// Iteration 0
	processHash(hashA, input, 0, 80);
	p = hashA;
	unsigned int n = HASHX11KV_MIN_NUMBER_ITERATIONS + (p[63] % (HASHX11KV_MAX_NUMBER_ITERATIONS - HASHX11KV_MIN_NUMBER_ITERATIONS + 1));

	for(int i = 1; i < n; i++) {
		p = (unsigned char *) hashA;

		processHash(hashB, hashA, p[i % 64] % HASHX11KV_NUMBER_ALGOS, 64);
       
		memcpy(hashA, hashB, 64);
	    	void* t = hashA;
		hashA = hashB;
		hashB = t;
	}

	memcpy(output, hashA, 32);

	free(hashA);
	free(hashB);
}

const unsigned int HASHX11KVS_MAX_LEVEL = 7;
const unsigned int HASHX11KVS_MIN_LEVEL = 1;
const unsigned int HASHX11KVS_MAX_DRIFT = 0xFFFF;

// Buffers on the stack: the heap version leaked hashConcated at every level
// and hash at the last one (about 8 KB per hash at level 7).
void x11kvshash(char *output, const char *input, unsigned int level)
{
	uint8_t hash[32];
	x11kv(hash, input);

	if (level == HASHX11KVS_MIN_LEVEL)
	{
		memcpy(output, hash, 32);
		return;
	}

	uint32_t nonce = le32dec(input + 76);

	uint8_t nextheader1[80];
	uint8_t nextheader2[80];

	uint32_t nextnonce1 = nonce + (le32dec(hash + 24) % HASHX11KVS_MAX_DRIFT);
	uint32_t nextnonce2 = nonce + (le32dec(hash + 28) % HASHX11KVS_MAX_DRIFT);

	memcpy(nextheader1, input, 76);
	le32enc(nextheader1 + 76, nextnonce1);

	memcpy(nextheader2, input, 76);
	le32enc(nextheader2 + 76, nextnonce2);

	// hash, hash of next header 1, hash of next header 2
	uint8_t concat[32 + 32 + 32];
	memcpy(concat, hash, 32);
	x11kvshash((char *) concat + 32, (const char *) nextheader1, level - 1);
	x11kvshash((char *) concat + 64, (const char *) nextheader2, level - 1);

	sha256_double_hash((const char *) concat, output, 96);
}

void x11kvs_hash(const char* input, char* output,  uint32_t len)
{
	x11kvshash(output, input, HASHX11KVS_MAX_LEVEL);
}
