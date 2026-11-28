// HomeScrypt v1.2 consensus hash for Lumenite Core.
//
// Ported from Lumenite Core v1.2.0 src/crypto/homescrypt.cpp (commit
// a9855c7f61024f481ce869f8b94e447da671f14b). Keep this in lockstep with the
// node: the domain separators, round counts and FP32 chaining are consensus
// critical. The FP chain must use true IEEE-754 fused multiply-add; this file
// is compiled with -ffp-contract=off and must never be built with -ffast-math
// or -Ofast.

#include "homescrypt.h"

#include <array>
#include <cmath>
#include <cstdint>
#include <cstring>
#include <vector>

extern "C"
{
void sha256_hash(const char *input, char *output, unsigned int len);
void scrypt_1024_1_1_256(const unsigned char *input, unsigned char *output);
}

namespace {

constexpr std::size_t HEADER_SIZE = 80;
constexpr std::size_t SCRATCHPAD_BYTES = 16 * 1024 * 1024;
constexpr std::size_t WORDS = SCRATCHPAD_BYTES / sizeof(std::uint64_t);
constexpr std::size_t RANDOM_MIX_ROUNDS = WORDS / 32;
constexpr std::size_t FP_PASSES = 6;
constexpr int FP_INNER = 8;

constexpr unsigned char DOMAIN_SEED[] = {
	'H','O','M','E','S','C','R','Y','P','T','-','V','1','.','2','-','S','E','E','D'
};
constexpr unsigned char DOMAIN_FINAL[] = {
	'H','O','M','E','S','C','R','Y','P','T','-','V','1','.','2','-','F','I','N','A','L'
};

inline std::uint64_t rotl64(std::uint64_t x, unsigned int r)
{
	return (x << r) | (x >> (64 - r));
}

inline std::uint64_t load64le(const unsigned char *p)
{
	return (std::uint64_t)p[0]
		| ((std::uint64_t)p[1] << 8)
		| ((std::uint64_t)p[2] << 16)
		| ((std::uint64_t)p[3] << 24)
		| ((std::uint64_t)p[4] << 32)
		| ((std::uint64_t)p[5] << 40)
		| ((std::uint64_t)p[6] << 48)
		| ((std::uint64_t)p[7] << 56);
}

inline void store64le(unsigned char *p, std::uint64_t x)
{
	p[0] = static_cast<unsigned char>(x);
	p[1] = static_cast<unsigned char>(x >> 8);
	p[2] = static_cast<unsigned char>(x >> 16);
	p[3] = static_cast<unsigned char>(x >> 24);
	p[4] = static_cast<unsigned char>(x >> 32);
	p[5] = static_cast<unsigned char>(x >> 40);
	p[6] = static_cast<unsigned char>(x >> 48);
	p[7] = static_cast<unsigned char>(x >> 56);
}

inline std::uint64_t avalanche(std::uint64_t x)
{
	x ^= x >> 30;
	x *= UINT64_C(0xbf58476d1ce4e5b9);
	x ^= x >> 27;
	x *= UINT64_C(0x94d049bb133111eb);
	x ^= x >> 31;
	return x;
}

inline std::uint32_t f32_bits(float f)
{
	std::uint32_t w;
	std::memcpy(&w, &f, sizeof(w));
	return w;
}

inline float bits_f32(std::uint32_t w)
{
	float f;
	std::memcpy(&f, &w, sizeof(f));
	return f;
}

// Positive normal float in [2^-9, 2^7), never NaN/infinity/denormal.
inline float safe_f32(std::uint32_t w)
{
	const std::uint32_t exp = 118u + ((w >> 23) & 0x0Fu);
	return bits_f32((exp << 23) | (w & 0x007FFFFFu));
}

void sha256(const unsigned char *data, std::size_t len, unsigned char *out)
{
	sha256_hash(reinterpret_cast<const char *>(data), reinterpret_cast<char *>(out),
		static_cast<unsigned int>(len));
}

} // namespace

size_t homescrypt_v12_scratchpad_bytes()
{
	return SCRATCHPAD_BYTES;
}

void homescrypt_v12(const unsigned char *input, unsigned char *output)
{
	std::array<unsigned char, 32> scrypt_seed{};
	scrypt_1024_1_1_256(input, scrypt_seed.data());

	std::array<unsigned char, sizeof(DOMAIN_SEED) + HEADER_SIZE + 32> seed_input{};
	std::memcpy(seed_input.data(), DOMAIN_SEED, sizeof(DOMAIN_SEED));
	std::memcpy(seed_input.data() + sizeof(DOMAIN_SEED), input, HEADER_SIZE);
	std::memcpy(seed_input.data() + sizeof(DOMAIN_SEED) + HEADER_SIZE,
		scrypt_seed.data(), scrypt_seed.size());

	std::array<unsigned char, 32> seed{};
	sha256(seed_input.data(), seed_input.size(), seed.data());

	std::array<std::uint64_t, 4> state{{
		load64le(seed.data()),
		load64le(seed.data() + 8),
		load64le(seed.data() + 16),
		load64le(seed.data() + 24)
	}};

	std::vector<std::uint64_t> scratch(WORDS);
	std::uint64_t x = state[0] ^ rotl64(state[1], 17)
		^ rotl64(state[2], 31) ^ rotl64(state[3], 47);

	for (std::size_t i = 0; i < WORDS; ++i) {
		x += UINT64_C(0x9e3779b97f4a7c15) + static_cast<std::uint64_t>(i);
		x ^= state[i & 3] + rotl64(x, static_cast<unsigned int>((i & 31) + 1));
		x = avalanche(x);
		scratch[i] = x;
		state[i & 3] ^= x + rotl64(state[(i + 1) & 3], 23);
	}

	for (std::size_t round = 0; round < RANDOM_MIX_ROUNDS; ++round) {
		const std::size_t lane = round & 3;

		const std::size_t idx1 = static_cast<std::size_t>(
			state[lane] ^ x ^ static_cast<std::uint64_t>(round)) & (WORDS - 1);
		const std::uint64_t a = scratch[idx1];

		const std::size_t idx2 = static_cast<std::size_t>(
			a ^ rotl64(state[(lane + 1) & 3], 17)) & (WORDS - 1);
		const std::uint64_t b = scratch[idx2];

		const std::size_t idx3 = static_cast<std::size_t>(
			b ^ rotl64(a, 31) ^ state[(lane + 2) & 3]) & (WORDS - 1);
		const std::uint64_t c = scratch[idx3];

		const std::uint64_t mixed = a + rotl64(b, 23) + rotl64(c, 41)
			+ state[(lane + 3) & 3] + static_cast<std::uint64_t>(round);

		float m0 = safe_f32(static_cast<std::uint32_t>(c >> 32));
		float m1 = safe_f32(static_cast<std::uint32_t>(c));
		float m2 = safe_f32(static_cast<std::uint32_t>(mixed >> 32));
		float m3 = safe_f32(static_cast<std::uint32_t>(mixed));

		float g0 = safe_f32(static_cast<std::uint32_t>(a >> 32));
		float g1 = safe_f32(static_cast<std::uint32_t>(a));
		float g2 = safe_f32(static_cast<std::uint32_t>(b >> 32));
		float g3 = safe_f32(static_cast<std::uint32_t>(b));

		for (std::size_t pass = 0; pass < FP_PASSES; ++pass) {
			for (int j = 0; j < FP_INNER; ++j) {
				// Explicit fmaf: round once, independent of contraction flags.
				g0 = std::fmaf(g0, m0, g3);
				g1 = std::fmaf(g1, m1, g0);
				g2 = std::fmaf(g2, m2, g1);
				g3 = std::fmaf(g3, m3, g2);
			}

			const std::uint32_t w0 = f32_bits(g0);
			const std::uint32_t w1 = f32_bits(g1);
			const std::uint32_t w2 = f32_bits(g2);
			const std::uint32_t w3 = f32_bits(g3);

			g0 = safe_f32(w0);
			g1 = safe_f32(w1);
			g2 = safe_f32(w2);
			g3 = safe_f32(w3);

			const float t = m0;
			m0 = safe_f32(f32_bits(m1) ^ w0);
			m1 = safe_f32(f32_bits(m2) ^ w1);
			m2 = safe_f32(f32_bits(m3) ^ w2);
			m3 = safe_f32(f32_bits(t) ^ w3);
		}

		const std::uint64_t fp =
			((static_cast<std::uint64_t>(f32_bits(g0)) << 32) | f32_bits(g1))
			^ rotl64((static_cast<std::uint64_t>(f32_bits(g2)) << 32) | f32_bits(g3), 29);

		state[lane] ^= mixed ^ fp;
		state[lane] = rotl64(state[lane], static_cast<unsigned int>((mixed & 31) + 1));
		state[(lane + 1) & 3] += a ^ rotl64(c, 13);

		scratch[idx1] = a ^ state[lane] ^ rotl64(c, 7);
		scratch[idx2] = b + state[(lane + 1) & 3] + rotl64(a, 19);
		scratch[idx3] = c ^ mixed ^ rotl64(b, 37);
		x = rotl64(x ^ mixed ^ fp ^ scratch[idx3], 29);
	}

	std::uint64_t fold0 = state[0];
	std::uint64_t fold1 = state[1];
	std::uint64_t fold2 = state[2];
	std::uint64_t fold3 = state[3];
	for (std::size_t i = 0; i < WORDS; i += 4) {
		fold0 = avalanche(fold0 ^ scratch[i]);
		fold1 = avalanche(fold1 + scratch[i + 1]);
		fold2 = avalanche(fold2 ^ rotl64(scratch[i + 2], 17));
		fold3 = avalanche(fold3 + rotl64(scratch[i + 3], 41));
	}

	std::array<unsigned char, 32> folded{};
	store64le(folded.data(), fold0);
	store64le(folded.data() + 8, fold1);
	store64le(folded.data() + 16, fold2);
	store64le(folded.data() + 24, fold3);

	std::array<unsigned char, sizeof(DOMAIN_FINAL) + 32 + 32 + HEADER_SIZE> final_input{};
	std::memcpy(final_input.data(), DOMAIN_FINAL, sizeof(DOMAIN_FINAL));
	std::memcpy(final_input.data() + sizeof(DOMAIN_FINAL), seed.data(), seed.size());
	std::memcpy(final_input.data() + sizeof(DOMAIN_FINAL) + seed.size(),
		folded.data(), folded.size());
	std::memcpy(final_input.data() + sizeof(DOMAIN_FINAL) + seed.size() + folded.size(),
		input, HEADER_SIZE);

	sha256(final_input.data(), final_input.size(), output);
}
