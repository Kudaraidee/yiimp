#include <stdio.h>
#include <stdlib.h>
#include <string.h>

#define HASH_FUNC_BASE_TIMESTAMP_1 1370079299 // Megacoin Block 1
#define HASH_FUNC_COUNT_1 8
#define HASH_FUNC_COUNT_2 8
#define HASH_FUNC_COUNT_3 7
#define HASH_FUNC_VAR_1 2100
#define HASH_FUNC_VAR_2 2100
#define HASH_FUNC_COUNT_PERMUTATIONS_7 5040
#define HASH_FUNC_COUNT_PERMUTATIONS 40320

#include <third_party/sph/sph_haval.h>
#include "algos/common/sph512.h"

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

void megamec_hash(const char* input, char* output, uint32_t len)
{
	uint32_t _ALIGN(64) hash[23];
	uint32_t *work_data = (uint32_t *)input;
	const uint32_t timestamp = work_data[17];

	sph_haval256_5_context   ctx_haval;

    uint32_t permutation_1[HASH_FUNC_COUNT_1];
	uint32_t permutation_2[HASH_FUNC_COUNT_2 + HASH_FUNC_COUNT_1];
	uint32_t permutation_3[HASH_FUNC_COUNT_3 + HASH_FUNC_COUNT_2 + HASH_FUNC_COUNT_1];

    //Init1
	for (uint32_t i = 1; i < HASH_FUNC_COUNT_1; i++) {
		permutation_1[i] = i;
        }

 //Init2
	for (uint32_t i = HASH_FUNC_COUNT_1; i < HASH_FUNC_COUNT_2 + HASH_FUNC_COUNT_1; i++) {
    permutation_2[i] = i;
    }

    //Init3
     for (uint32_t i = HASH_FUNC_COUNT_1 + HASH_FUNC_COUNT_2; i < HASH_FUNC_COUNT_3 + HASH_FUNC_COUNT_2 + HASH_FUNC_COUNT_1; i++) {
         permutation_3[i] = i;
    }

            uint32_t steps_1 = (timestamp - HASH_FUNC_BASE_TIMESTAMP_1) % HASH_FUNC_COUNT_PERMUTATIONS_7;
            for (uint32_t i = 0; i < steps_1; i++) {
                next_permutation(permutation_1, permutation_1 + HASH_FUNC_COUNT_1);
            }

            uint32_t steps_2 = (timestamp+ HASH_FUNC_VAR_1 - HASH_FUNC_BASE_TIMESTAMP_1) % HASH_FUNC_COUNT_PERMUTATIONS;
            for (uint32_t i = 0; i < steps_2; i++) {
                next_permutation(permutation_2 + HASH_FUNC_COUNT_1, permutation_2 + HASH_FUNC_COUNT_1 + HASH_FUNC_COUNT_2);
            }

            uint32_t steps_3 = (timestamp+ HASH_FUNC_VAR_2 - HASH_FUNC_BASE_TIMESTAMP_1) % HASH_FUNC_COUNT_PERMUTATIONS_7;
            for (uint32_t i = 0; i < steps_3; i++) {
                next_permutation(permutation_3 + HASH_FUNC_COUNT_1 + HASH_FUNC_COUNT_2, permutation_3 + HASH_FUNC_COUNT_1 + HASH_FUNC_COUNT_2 + HASH_FUNC_COUNT_3);
            }

	int lenToHash = 64;
	
	sph512(SPH_BLAKE512, input, len, hash);
	
	for (int i = 1; i < HASH_FUNC_COUNT_1; i++) {
		switch (permutation_1[i]) {
                case 1:
                    // 3000 + 700
                    sph512(SPH_ECHO512, hash, lenToHash, hash);

                    sph512(SPH_BLAKE512, hash, 64, hash);
                    break;
                case 2:
                    // 700 +3500
                    sph512(SPH_SIMD512, hash, lenToHash, hash);

                    sph512(SPH_BMW512, hash, 64, hash);
                    break;
                case 3:
                    // 4000
                    sph512(SPH_GROESTL512, hash, lenToHash, hash);
                    break;
                case 4:
                    // 2000 + 2100
                    sph512(SPH_WHIRLPOOL, hash, lenToHash, hash);

                    sph512(SPH_JH512, hash, 64, hash);
                    break;
                case 5:
                    // 1000 + 700
                    sph512(SPH_GOST512, hash, lenToHash, hash);

                    sph512(SPH_KECCAK512, hash, 64, hash);
                    break;
                case 6:
                    // 1000 + 4000
                    sph512(SPH_FUGUE512, hash, lenToHash, hash);

                    sph512(SPH_SKEIN512, hash, 64, hash);
                    break;
                case 7:
                    // 1800 + 2000
                    sph512(SPH_SHAVITE512, hash, lenToHash, hash);

                    sph512(SPH_LUFFA512, hash, 64, hash);
                    break;
                }
            }
            	for (int i = HASH_FUNC_COUNT_1; i < HASH_FUNC_COUNT_1 + HASH_FUNC_COUNT_2; i++) {
            	switch (permutation_2[i]) {
                case 8:
                    // 2100 +2000
                    sph512(SPH_WHIRLPOOL, hash, lenToHash, hash);

                    sph512(SPH_CUBEHASH512, hash, 64, hash);
                    break;
                case 9:
                    // 1800 + 2100
                    sph512(SPH_JH512, hash, lenToHash, hash);

                    sph512(SPH_SHAVITE512, hash, 64, hash);
                    break;
                case 10:
                    // 3500 + 700
                    sph512(SPH_BLAKE512, hash, lenToHash, hash);

                    sph512(SPH_SIMD512, hash, 64, hash);
                    break;
                case 11:
                    // 3000 + 1000
                    sph512(SPH_SHABAL512, hash, lenToHash, hash);

                    sph512(SPH_ECHO512, hash, 64, hash);
                    break;
                case 12:
                    // 5000
                    sph512(SPH_HAMSI512, hash, lenToHash, hash);
                    break;
                case 13:
                    // 4000 + 700
                    sph512(SPH_BMW512, hash, lenToHash, hash);

                    sph512(SPH_FUGUE512, hash, 64, hash);
                    break;
                case 14:
                    // 1000 +1000
                    sph512(SPH_KECCAK512, hash, lenToHash, hash);

                    sph512(SPH_SHABAL512, hash, 64, hash);
                    break;
                case 15:
                    // 2000 + 2000
                    sph512(SPH_LUFFA512, hash, lenToHash, hash);

                    sph512(SPH_WHIRLPOOL, hash, 64, hash);
                    break;
                }
            }
            	for (int i = HASH_FUNC_COUNT_2; i < HASH_FUNC_COUNT_1 + HASH_FUNC_COUNT_2 + HASH_FUNC_COUNT_3; i++) {
		switch (permutation_3[i]) {
                case 16:
                    // 700 + 2000
                    sph512(SPH_SHA512, hash, lenToHash, hash);

                    sph_haval256_5_init(&ctx_haval);
                    sph_haval256_5 (&ctx_haval, hash, 64);
                    sph_haval256_5_close(&ctx_haval, hash);
                    break;
                case 17:
                    // 4000 + 700
                    sph512(SPH_SKEIN512, hash, lenToHash, hash);

                    sph512(SPH_GROESTL512, hash, 64, hash);
                    break;
                case 18:
                    // 700 + 5000
                    sph512(SPH_SIMD512, hash, lenToHash, hash);

                    sph512(SPH_HAMSI512, hash, 64, hash);
                    break;
                case 19:
                    // 1000 + 2000
                    sph512(SPH_GOST512, hash, lenToHash, hash);

                    sph_haval256_5_init(&ctx_haval);
                    sph_haval256_5 (&ctx_haval, hash, 64);
                    sph_haval256_5_close(&ctx_haval, hash);
                    break;
                case 20:
                    // 2100 + 700
                    sph512(SPH_CUBEHASH512, hash, lenToHash, hash);

                    sph512(SPH_SHA512, hash, 64, hash);
                    break;
                case 21:
                    // 1800 + 3000
                    sph512(SPH_ECHO512, hash, lenToHash, hash);

                    sph512(SPH_SHAVITE512, hash, 64, hash);
                    break;
                case 22:
                    // 2000 + 1000
                    sph512(SPH_LUFFA512, hash, lenToHash, hash);

                    sph512(SPH_SHABAL512, hash, 64, hash);;
                    break;
                }
            }

	memcpy(output, hash, 32);
}
