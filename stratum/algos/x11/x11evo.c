#include <stdlib.h>
#include <stdint.h>
#include <string.h>
#include <stdio.h>
#include "algos/common/sph512.h"

enum Algo {
	BLAKE = 0,
	BMW,
	GROESTL,
	SKEIN,
	JH,
	KECCAK,
	LUFFA,
	CUBEHASH,
	SHAVITE,
	SIMD,
	ECHO,
	HASH_FUNC_COUNT
};

static void swap8(uint8_t *a, uint8_t *b)
{
	uint8_t t = *a;
	*a = *b;
	*b = t;
}

static void initPerm(uint8_t n[], int count)
{
	for (int i = 0; i < count; i++)
		n[i] = i;
}

static int nextPerm(uint8_t n[], int count)
{
	int tail, i, j;

	if (count <= 1)
		return 0;

	for (i = count - 1; i>0 && n[i - 1] >= n[i]; i--);
	tail = i;

	if (tail > 0) {
		for (j = count - 1; j>tail && n[j] <= n[tail - 1]; j--);
		swap8(&n[tail - 1], &n[j]);
	}

	for (i = tail, j = count - 1; i<j; i++, j--)
		swap8(&n[i], &n[j]);

	return (tail != 0);
}

static void getAlgoString(char *str, int seq)
{
	uint8_t algoList[HASH_FUNC_COUNT];
	char *sptr;

	initPerm(algoList, HASH_FUNC_COUNT);

	for (int k = 0; k < seq; k++) {
		nextPerm(algoList, HASH_FUNC_COUNT);
	}

	sptr = str;
	for (int j = 0; j < HASH_FUNC_COUNT; j++) {
		if (algoList[j] >= 10)
			sprintf(sptr, "%c", 'A' + (algoList[j] - 10));
		else
			sprintf(sptr, "%u", (uint32_t) algoList[j]);
		sptr++;
	}
	*sptr = '\0';
}

static char hashOrder[HASH_FUNC_COUNT + 1] = { 0 };
static int  s_sequence = -1;

#define INITIAL_DATE 0x57254700
static inline int getCurrentAlgoSeq(uint32_t current_time)
{
	// change once per day
	return (int) (current_time - INITIAL_DATE) / (60 * 60 * 24);
}

static void evo_twisted_code(uint32_t ntime, char *permstr)
{
	int seq = getCurrentAlgoSeq(ntime);
	if (s_sequence != seq) {
		getAlgoString(permstr, seq);
		s_sequence = seq;
	}
}

void x11evo_hash(const char* input, char* output, uint32_t len)
{
	uint32_t hash[64/4];

	uint32_t ntime;
	memcpy(&ntime, &input[17*4], 4);
	evo_twisted_code(ntime, hashOrder);

	void *in = (void*) input;
	int size = len;

	const int hashes = (int) strlen(hashOrder);

	for (int i = 0; i < hashes; i++)
	{
		const char elem = hashOrder[i];
		uint8_t algo = elem >= 'A' ? elem - 'A' + 10 : elem - '0';

		if (i > 0) {
			in = (void*) hash;
			size = 64;
		}

		switch (algo) {
		case BLAKE:
			sph512(SPH_BLAKE512, in, size, hash);
			break;
		case BMW:
			sph512(SPH_BMW512, in, size, hash);
			break;
		case GROESTL:
			sph512(SPH_GROESTL512, in, size, hash);
			break;
		case SKEIN:
			sph512(SPH_SKEIN512, in, size, hash);
			break;
		case JH:
			sph512(SPH_JH512, in, size, hash);
			break;
		case KECCAK:
			sph512(SPH_KECCAK512, in, size, hash);
			break;
		case LUFFA:
			sph512(SPH_LUFFA512, in, size, hash);
			break;
		case CUBEHASH:
			sph512(SPH_CUBEHASH512, in, size, hash);
			break;
		case SHAVITE:
			sph512(SPH_SHAVITE512, in, size, hash);
			break;
		case SIMD:
			sph512(SPH_SIMD512, in, size, hash);
			break;
		case ECHO:
			sph512(SPH_ECHO512, in, size, hash);
			break;
		}
	}

	memcpy(output, hash, 32);
}

