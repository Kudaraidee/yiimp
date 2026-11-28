#include "x11k.h"
#include <stdlib.h>
#include <stdint.h>
#include <string.h>
#include <stdio.h>
#include "algos/common/sph512.h"

void *Blake512(void *oHash, const void *iHash, uint32_t len)
{
        sph512(SPH_BLAKE512, iHash, len, oHash);
}

void *Bmw512(void *oHash, const void *iHash, uint32_t len)
{
        sph512(SPH_BMW512, iHash, len, oHash);
}

void *Groestl512(void *oHash, const void *iHash, uint32_t len)
{
        sph512(SPH_GROESTL512, iHash, len, oHash);
}

void *Skein512(void *oHash, const void *iHash, uint32_t len)
{
        sph512(SPH_SKEIN512, iHash, len, oHash);
}

void *Jh512(void *oHash, const void *iHash, uint32_t len)
{
        sph512(SPH_JH512, iHash, len, oHash);
}

void *Keccak512(void *oHash, const void *iHash, uint32_t len)
{
        sph512(SPH_KECCAK512, iHash, len, oHash);
}

void *Luffa512(void *oHash, const void *iHash, uint32_t len)
{
        sph512(SPH_LUFFA512, iHash, len, oHash);
}

void *Cubehash512(void *oHash, const void *iHash, uint32_t len)
{
        sph512(SPH_CUBEHASH512, iHash, len, oHash);
}

void *Shavite512(void *oHash, const void *iHash, uint32_t len)
{
        sph512(SPH_SHAVITE512, iHash, len, oHash);
}

void *Simd512(void *oHash, const void *iHash, uint32_t len)
{
        sph512(SPH_SIMD512, iHash, len, oHash);
}

void *Echo512(void *oHash, const void *iHash, uint32_t len)
{
        sph512(SPH_ECHO512, iHash, len, oHash);
}

void *fnHashX11K[] = {
        Blake512,
        Bmw512,
        Groestl512,
        Skein512,
        Jh512,
        Keccak512,
        Luffa512,
        Cubehash512,
        Shavite512,
        Simd512,
        Echo512,
};

void processHash(void *oHash, const void *iHash, int index, uint32_t len)
{
        void (*hashX11k)(void *oHash, const void *iHash, uint32_t len);

        hashX11k = fnHashX11K[index];
	(*hashX11k)(oHash, iHash, len);
}

void x11k_hash(const char* input, char* output,  uint32_t len)
{
        const int HASHX11K_NUMBER_ITERATIONS = 64;
	const int HASHX11K_NUMBER_ALGOS = 11;

	void* hashA = (void *) malloc(64);
	void* hashB = (void *) malloc(64);

        // Iteration 0
        processHash(hashA, input, 0, len);

        for(int i = 1; i < HASHX11K_NUMBER_ITERATIONS; i++) {
                unsigned char * p = hashA;
                processHash(hashB, hashA, p[i] % HASHX11K_NUMBER_ALGOS, 64);

                void* t = hashA;
                hashA = hashB;
                hashB = t;
        }

        memcpy(output, hashA, 32);

	free(hashA);
	free(hashB);
}

