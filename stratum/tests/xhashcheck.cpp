// Known-answer tests for the vendored Parallax XHash library.
//
// Vectors come from Miningcore.Tests/Crypto/XhashTests.cs in parallax-pool,
// which generated them from the Parallax reference implementation. They pin
// the chain-specific seed magic and the light-client hash independently of
// the yiimp protocol code.
//
// Distributed under the MIT software license.

#include <xhash/xhash.h>

#include <cstdio>
#include <cstring>
#include <string>

namespace
{

int hex_value(char c)
{
	if (c >= '0' && c <= '9') return c - '0';
	if (c >= 'a' && c <= 'f') return c - 'a' + 10;
	if (c >= 'A' && c <= 'F') return c - 'A' + 10;
	return -1;
}

bool parse_hex(const char *src, uint8_t out[32])
{
	if (!src || strlen(src) != 64) return false;
	for (int i = 0; i < 32; i++)
	{
		const int hi = hex_value(src[i * 2]);
		const int lo = hex_value(src[i * 2 + 1]);
		if (hi < 0 || lo < 0) return false;
		out[i] = (uint8_t) ((hi << 4) | lo);
	}
	return true;
}

std::string to_hex(const uint8_t bytes[32])
{
	static const char digits[] = "0123456789abcdef";
	std::string result;
	result.reserve(64);
	for (int i = 0; i < 32; i++)
	{
		result.push_back(digits[bytes[i] >> 4]);
		result.push_back(digits[bytes[i] & 0xf]);
	}
	return result;
}

bool check_seed(int epoch, const char *expected)
{
	const xhash_hash256 seed = xhash_calculate_epoch_seed(epoch);
	const std::string actual = to_hex(seed.bytes);
	if (actual == expected) return true;

	fprintf(stderr, "XHASH SEED FAIL epoch %d: got %s want %s\n",
		epoch, actual.c_str(), expected);
	return false;
}

bool check_hash(xhash_epoch_context *context, const char *header_hex,
	uint64_t nonce, const char *want_mix, const char *want_final)
{
	uint8_t header[32];
	if (!parse_hex(header_hex, header))
	{
		fprintf(stderr, "XHASH KAT bad header %s\n", header_hex);
		return false;
	}

	xhash_hash256 hash_header;
	memcpy(hash_header.bytes, header, 32);

	const xhash_result result = xhash_hash(context, &hash_header, nonce);
	const std::string mix = to_hex(result.mix_hash.bytes);
	const std::string final = to_hex(result.final_hash.bytes);

	if (mix == want_mix && final == want_final) return true;

	fprintf(stderr, "XHASH KAT FAIL header %s nonce %016llx\n"
		"  mix   got %s want %s\n"
		"  final got %s want %s\n",
		header_hex, (unsigned long long) nonce,
		mix.c_str(), want_mix, final.c_str(), want_final);
	return false;
}

} // namespace

int main()
{
	bool ok = true;

	// Parallax seeds SHA3-256("PARALLAX\x01" ++ previous seed), 720 per epoch.
	ok = check_seed(0, "0000000000000000000000000000000000000000000000000000000000000000") && ok;
	ok = check_seed(1, "98ad90833060d50f880b5eb465849d897ccfaebc9416082f97900ddd539a7e53") && ok;
	ok = check_seed(171, "b16823d6fe8aa7e87a22f25763227e8d1e8dfd064f668d6f5027d5b2e3d7a4e0") && ok;

	xhash_epoch_context *context = xhash_create_epoch_context(0);
	if (!context)
	{
		fprintf(stderr, "XHASH KAT unable to create epoch 0 context\n");
		return 2;
	}

	// Both vectors are block numbers < 720, so they use the epoch 0 cache.
	ok = check_hash(context,
		"2a8de2adf89af77358250bf908bf04ba94a6e8c3ba87775564a41d269a05e4ce",
		0x4242424242424242ull,
		"761b72420f69ac3ee622e35f6a6aeb5caff5fe3c41b0a002cc1504107dcfec24",
		"4c95f631745bdf9f26299d9f49749e8fd2e420edcc36cfcb2b6eb0d2ccfbceca") && ok;
	ok = check_hash(context,
		"100cbec5e5ef82991290d0d93d758f19082e71f234cf479192a8b94df6da6bfe",
		0x307692cf71b12f6dull,
		"2d12a4555976e410f9989672f6d7b38d2f20733d8d2d8b36a1863fc51568a0b3",
		"7913bb30f11bbf36a162338dbee4ccc51894d43792d9234a1336e3dee9ca803e") && ok;

	xhash_destroy_epoch_context(context);

	printf("%s\n", ok ? "XHASH KAT ok" : "XHASH KAT FAIL");
	return ok ? 0 : 1;
}
