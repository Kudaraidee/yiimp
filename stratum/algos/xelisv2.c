#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <stdint.h>
#include <inttypes.h>

#include "xelisv2-pepew/blake3.h"
#include "xelisv2-pepew/chacha20.h"
#include "xelisv2-pepew/aes.h"
#include "sha3/sph_blake.h"
#include "sha3/sph_cubehash.h"
#include "sha3/sph_shavite.h"
#include "sha3/sph_simd.h"
#include "sha3/sph_echo.h"
#include "sha3/sph_sha2.h"

#if defined(NO_AES_NI)
	#undef __x86_64__
#endif

#if defined(__x86_64__)
  #include <emmintrin.h>
  #include <immintrin.h>
#elif defined(__aarch64__)
  #include <arm_neon.h>
#endif

#define INPUT_LEN (112)
#define MEMSIZE (429 * 128)
#define ITERS (3)
#define HASHSIZE (32)

static inline void blake3(const uint8_t *input, int len, uint8_t *output)
{
	blake3_hasher hasher;
	blake3_hasher_init(&hasher);
	blake3_hasher_update(&hasher, input, len);
	blake3_hasher_finalize(&hasher, output, BLAKE3_OUT_LEN);
}

#define HASH_SIZE (32)
#define CHUNK_SIZE (32)
#define NONCE_SIZE (12)
#define OUTPUT_SIZE (MEMSIZE * 8)
#define CHUNKS (4)


void xel_stage_1(const uint8_t *input, size_t input_len, uint8_t scratch_pad[OUTPUT_SIZE])
{
	uint8_t key[CHUNK_SIZE * CHUNKS] = {0};
	uint8_t input_hash[HASH_SIZE];
	uint8_t buffer[CHUNK_SIZE * 2];
	memcpy(key, input, INPUT_LEN);
	// memcpy(key, input, sizeof(input));
	blake3(input, input_len, buffer);

	uint8_t *t = scratch_pad;

	memcpy(buffer + CHUNK_SIZE, key + 0 * CHUNK_SIZE, CHUNK_SIZE);
	blake3(buffer, CHUNK_SIZE * 2, input_hash);
	chacha_encrypt(input_hash, buffer, NULL, t, OUTPUT_SIZE / CHUNKS, 8);

	t += OUTPUT_SIZE / CHUNKS;
	memcpy(buffer, input_hash, CHUNK_SIZE);
	memcpy(buffer + CHUNK_SIZE, key + 1 * CHUNK_SIZE, CHUNK_SIZE);
	blake3(buffer, CHUNK_SIZE * 2, input_hash);
	chacha_encrypt(input_hash, t - NONCE_SIZE, NULL, t, OUTPUT_SIZE / CHUNKS, 8);

	t += OUTPUT_SIZE / CHUNKS;
	memcpy(buffer, input_hash, CHUNK_SIZE);
	memcpy(buffer + CHUNK_SIZE, key + 2 * CHUNK_SIZE, CHUNK_SIZE);
	blake3(buffer, CHUNK_SIZE * 2, input_hash);
	chacha_encrypt(input_hash, t - NONCE_SIZE, NULL, t, OUTPUT_SIZE / CHUNKS, 8);

	t += OUTPUT_SIZE / CHUNKS;
	memcpy(buffer, input_hash, CHUNK_SIZE);
	memcpy(buffer + CHUNK_SIZE, key + 3 * CHUNK_SIZE, CHUNK_SIZE);
	blake3(buffer, CHUNK_SIZE * 2, input_hash);
	chacha_encrypt(input_hash, t - NONCE_SIZE, NULL, t, OUTPUT_SIZE / CHUNKS, 8);
}


#define KEY "xelishash-pow-v2"
#define BUFSIZE (MEMSIZE / 2)

// https://danlark.org/2020/06/14/128-bit-division
static inline uint64_t div128(__uint128_t dividend, __uint128_t divisor) {
    return dividend / divisor;
}
static inline uint64_t Divide128Div64To64(uint64_t high, uint64_t low, uint64_t divisor, uint64_t *remainder)
{
    // Combine high and low into a 128-bit dividend
    __uint128_t dividend = ((__uint128_t)high << 64) | low;

    // Perform division using built-in compiler functions
    *remainder = dividend % divisor;
    return dividend / divisor;
}

static inline uint64_t udiv(uint64_t high, uint64_t low, uint64_t divisor)
{
	uint64_t remainder;

	if (high < divisor)
	{
		return Divide128Div64To64(high, low, divisor, &remainder);
	}
	else
	{
		uint64_t qhi = Divide128Div64To64(0, high, divisor, &high);
		return Divide128Div64To64(high, low, divisor, &remainder);
	}
}



static inline __uint128_t combine_uint64(uint64_t high, uint64_t low)
{
	return ((__uint128_t)high << 64) | low;
}

// aarch64




static inline uint64_t ROTR(uint64_t x, uint32_t r)
{
    r %= 64;  // Ensure r is within the range [0, 63] for a 64-bit rotate
    return (x >> r) | (x << (64 - r));
}

static inline uint64_t ROTL(uint64_t x, uint32_t r)
{
    r %= 64;  // Ensure r is within the range [0, 63] for a 64-bit rotate
    return (x << r) | (x >> (64 - r));
}

/*
uint64_t isqrt(uint64_t n) {
	if (n < 2)
		return n;

	uint64_t x = n;
	uint64_t y = (x + 1) >> 1;

	while (y < x) {
		x = y;
		y = (x + n / x) >> 1;
	}

	return x;
}
*/

uint64_t isqrt(uint64_t n)
{
	if (n < 2)
		return n;

	uint64_t x = n;
	uint64_t result = 0;
	uint64_t bit = (uint64_t)1 << 62; // The second-to-top bit is set

	// "bit" starts at the highest power of four <= the argument.
	while (bit > x)
		bit >>= 2;

	while (bit != 0)
	{
		if (x >= result + bit)
		{
			x -= result + bit;
			result = (result >> 1) + bit;
		}
		else
		{
			result >>= 1;
		}
		bit >>= 2;
	}

	return result;
}

void static inline uint64_to_le_bytes(uint64_t value, uint8_t *bytes)
{
	for (int i = 0; i < 8; i++)
	{
		bytes[i] = value & 0xFF;
		value >>= 8;
	}
}

uint64_t static inline le_bytes_to_uint64(const uint8_t *bytes)
{
	uint64_t value = 0;
	for (int i = 7; i >= 0; i--)
		value = (value << 8) | bytes[i];
	return value;
}

void static inline aes_single_round(uint8_t *block, const uint8_t *key)
{
    #if defined(__x86_64__)
	__m128i block_vec = _mm_loadu_si128((const __m128i *)block);
	__m128i key_vec = _mm_loadu_si128((const __m128i *)key);

	// Perform single AES encryption round
	block_vec = _mm_aesenc_si128(block_vec, key_vec);

	_mm_storeu_si128((__m128i *)block, block_vec);
   #else
	aes_single_round_no_intrinsics(block, key);
   #endif

}

void xel_stage_3(uint64_t *scratch)
{
	uint64_t *mem_buffer_a = scratch;
	uint64_t *mem_buffer_b = &scratch[BUFSIZE];

	uint64_t addr_a = mem_buffer_b[BUFSIZE - 1];
	uint64_t addr_b = mem_buffer_a[BUFSIZE - 1] >> 32;
	uint32_t r = 0;

	for (uint32_t i = 0; i < ITERS; i++)
	{
		uint64_t mem_a = mem_buffer_a[addr_a % BUFSIZE];
		uint64_t mem_b = mem_buffer_b[addr_b % BUFSIZE];

		uint8_t block[16];
		uint64_to_le_bytes(mem_b, block);
		uint64_to_le_bytes(mem_a, block + 8);
		aes_single_round(block, KEY);

		uint64_t hash1 = le_bytes_to_uint64(block);
		uint64_t hash2 = mem_a ^ mem_b;
		uint64_t result = ~(hash1 ^ hash2);

		for (uint32_t j = 0; j < BUFSIZE; j++)
		{
			uint64_t a = mem_buffer_a[result % BUFSIZE];
			uint64_t b = mem_buffer_b[~ROTR(result, r) % BUFSIZE];
			uint64_t c = (r < BUFSIZE) ? mem_buffer_a[r] : mem_buffer_b[r - BUFSIZE];
			r = (r < MEMSIZE - 1) ? r + 1 : 0;

			uint64_t v;
			__uint128_t t1, t2;
			switch (ROTL(result, (uint32_t)c) & 0xf)
			{
			case 0:
				v = ROTL(c, i * j) ^ b;
				break;
			case 1:
				v = ROTR(c, i * j) ^ a;
				break;
			case 2:
				v = a ^ b ^ c;
				break;
			case 3:
				v = ((a + b) * c);
				break;
			case 4:
				v = ((b - c) * a);
				break;
			case 5:
				v = (c - a + b);
				break;
			case 6:
				v = (a - b + c);
				break;
			case 7:
				v = (b * c + a);
				break;
			case 8:
				v = (c * a + b);
				break;
			case 9:
				v = (a * b * c);
				break;
			case 10:
			{
				t1 = combine_uint64(a, b);
				uint64_t t2 = c | 1;
				v = t1 % t2;
			}
			break;
			case 11:
			{
				t1 = combine_uint64(b, c);
				t2 = combine_uint64(ROTL(result, r), a | 2);
				v = (t2 > t1) ? c : t1 % t2;
			}
			break;
			case 12:
				v = udiv(c, a, b | 4);
				break;
			case 13:
			{
				t1 = combine_uint64(ROTL(result, r), b);
				t2 = combine_uint64(a, c | 8);
				v = (t1 > t2) ? t1 / t2 : a ^ b;
			}
			break;
			case 14:
			{
				t1 = combine_uint64(b, a);
				uint64_t t2 = c;
				v = (t1 * t2) >> 64;
			}
			break;
			case 15:
			{
				t1 = combine_uint64(a, c);
				t2 = combine_uint64(ROTR(result, r), b);
				v = (t1 * t2) >> 64;
			}
			break;
			}
			result = ROTL(result ^ v, 1);

			uint64_t t = mem_buffer_a[BUFSIZE - j - 1] ^ result;
			mem_buffer_a[BUFSIZE - j - 1] = t;
			mem_buffer_b[j] ^= ROTR(t, result);
		}
		addr_a = result;
		addr_b = isqrt(result);
	}
}

#define XEL_INPUT_LEN (112)
// void meme_hash(const char* input, char* output, uint32_t len)
// void xelisv2_hash(const char* input, char* output, uint32_t len)
void xelisv2_hash(const uint32_t* input, uint32_t* output, uint32_t len)
{

	uint8_t *xel_input = (uint8_t *)calloc(INPUT_LEN, sizeof(uint8_t));
        uint64_t *scratch = (uint64_t *)calloc(MEMSIZE, sizeof(uint64_t));
        uint8_t *scratch_uint8 = (uint8_t *)scratch;
	uint8_t hash[HASHSIZE];
	// int len=80; // #FixMe

			memcpy(xel_input, input, len);
			// printf("Xel 2 input of %u\n",xel_input);
                        xel_stage_1(xel_input, XEL_INPUT_LEN, scratch_uint8);
                        xel_stage_3(scratch);
                        blake3(scratch_uint8, OUTPUT_SIZE, hash);

			memcpy(output,hash,HASHSIZE*sizeof(char)); // Back to char
		        free(scratch);	
		        free(xel_input);	
			// printf("Xelisv2 output: %s and %s\n",hash,output);
			// printf("Xelisv2 output: %s and %s\n",*hash,*output);
                        return;
}

/*
int scanhash_xelisv2(int thr_id, struct work *work, uint32_t max_nonce, uint64_t *hashes_done)
{
        uint32_t _ALIGN(128) hash[8];
        uint32_t _ALIGN(128) endiandata[20];
        uint32_t *pdata = work->data;
        uint32_t *ptarget = work->target;

        const uint32_t Htarg = ptarget[7];
        const uint32_t first_nonce = pdata[19];
        uint32_t nonce = first_nonce;
        volatile uint8_t *restart = &(work_restart[thr_id].restart);



        if (opt_benchmark)
                ptarget[7] = 0x0cff;

        for (int k=0; k < 19; k++)
                be32enc(&endiandata[k], pdata[k]);

        do {
                be32enc(&endiandata[19], nonce);
                xelisv2_hash(endiandata, hash);

                if (hash[7] <= Htarg && fulltest(hash, ptarget)) {
                        work_set_target_ratio(work, hash);
                        pdata[19] = nonce;
                        *hashes_done = pdata[19] - first_nonce;
                        return 1;
                }
                nonce++;

        } while (nonce < max_nonce && !(*restart));

        pdata[19] = nonce;
        *hashes_done = pdata[19] - first_nonce + 1;
        return 0;
}*/
