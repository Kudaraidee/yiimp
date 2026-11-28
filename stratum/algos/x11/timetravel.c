#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include "algos/common/sph512.h"

#define HASH_FUNC_BASE_TIMESTAMP 1389040865 // Machinecoin: Genesis Timestamp
#define HASH_FUNC_COUNT 8                   // Machinecoin: HASH_FUNC_COUNT of 11
#define HASH_FUNC_COUNT_PERMUTATIONS 40320  // Machinecoin: HASH_FUNC_COUNT!

#if HASH_FUNC_COUNT > 8
#endif

#define _ALIGN(x) __attribute__ ((aligned(x)))

// helpers
inline void swap(int *a, int *b) {
	int c = *a;
	*a = *b;
	*b = c;
}

static void reverse(int *pbegin, int *pend) {
	while ( (pbegin != pend) && (pbegin != --pend) )
		swap(pbegin++, pend);
}

static void next_permutation(int *pbegin, int *pend) {
	if (pbegin == pend)
		return;

	int *i = pbegin;
	++i;
	if (i == pend)
		return;

	i = pend;
	--i;

	while (1) {
		int *j = i;
		--i;

		if (*i < *j) {
			int *k = pend;

			while (!(*i < *--k))
				/* pass */;

			swap(i, k);
			reverse(j, pend);
			return; // true
		}

		if (i == pbegin) {
			reverse(pbegin, pend);
			return; // false
		}
	}
}
// helpers

void timetravel_hash(const char* input, char* output, uint32_t len)
{
	uint32_t _ALIGN(64) hash[16 * HASH_FUNC_COUNT];
	uint32_t *hashA, *hashB;
	uint32_t dataLen = 64;
	uint32_t *work_data = (uint32_t *)input;
	const uint32_t timestamp = work_data[17];

#if HASH_FUNC_COUNT > 8
#endif
	// We want to permute algorithms. To get started we
	// initialize an array with a sorted sequence of unique
	// integers where every integer represents its own algorithm.
	uint32_t permutation[HASH_FUNC_COUNT];
	for (uint32_t i = 0; i < HASH_FUNC_COUNT; i++) {
			permutation[i]=i;
	}

	// Compute the next permuation
	uint32_t steps = (timestamp - HASH_FUNC_BASE_TIMESTAMP) % HASH_FUNC_COUNT_PERMUTATIONS;
	for (uint32_t i = 0; i < steps; i++) {
		next_permutation(permutation, permutation + HASH_FUNC_COUNT);
	}

	for (uint32_t i = 0; i < HASH_FUNC_COUNT; i++) {
		if (i == 0) {
			dataLen = len;
			hashA = work_data;
		} else {
			dataLen = 64;
			hashA = &hash[16 * (i - 1)];
		}
		hashB = &hash[16 * i];

		switch(permutation[i]) {
			case 0:
				sph512(SPH_BLAKE512, hashA, dataLen, hashB);
				break;
			case 1:
				sph512(SPH_BMW512, hashA, dataLen, hashB);
				break;
			case 2:
				sph512(SPH_GROESTL512, hashA, dataLen, hashB);
				break;
			case 3:
				sph512(SPH_SKEIN512, hashA, dataLen, hashB);
				break;
			case 4:
				sph512(SPH_JH512, hashA, dataLen, hashB);
				break;
			case 5:
				sph512(SPH_KECCAK512, hashA, dataLen, hashB);
				break;
			case 6:
				sph512(SPH_LUFFA512, hashA, dataLen, hashB);
				break;
			case 7:
				sph512(SPH_CUBEHASH512, hashA, dataLen, hashB);
				break;
#if HASH_FUNC_COUNT > 8
			case 8:
				sph512(SPH_SHAVITE512, hashA, dataLen, hashB);
				break;
			case 9:
				sph512(SPH_SIMD512, hashA, dataLen, hashB);
				break;
			case 10:
				sph512(SPH_ECHO512, hashA, dataLen, hashB);
				break;
#endif
			default:
				break;
		}
	}

	memcpy(output, &hash[16 * (HASH_FUNC_COUNT - 1)], 32);
}

