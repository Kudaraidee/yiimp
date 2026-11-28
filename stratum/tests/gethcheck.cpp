// Focused tests for GETH-family support in the stratum:
//  - EVM address validation (no validateaddress RPC on Parallax/geth)
//  - JSON-RPC 2.0 request framing (Parallax rejects requests without it)

#include "../stratum.h"

#include <cstdio>
#include <cstring>
#include <string>

namespace
{

int failures = 0;

void check(bool ok, const char *label)
{
	if (!ok) {
		fprintf(stderr, "FAIL %s\n", label);
		failures++;
	}
}

void test_geth_address()
{
	// Lowercase and mixed-case are both accepted.
	check(geth_valid_address("0x72b546804152cdcd3756c694722b27d3cc4eefc3"),
	      "valid lowercase address");
	check(geth_valid_address("0x72B546804152CDCD3756C694722B27D3CC4EEFC3"),
	      "valid uppercase hex digits");
	check(geth_valid_address("0X72b546804152cdcd3756c694722b27d3cc4eefc3"),
	      "valid uppercase 0X prefix");

	// Wrong length / missing prefix / non-hex must be rejected.
	check(!geth_valid_address(""), "empty address");
	check(!geth_valid_address(NULL), "null address");
	check(!geth_valid_address("72b546804152cdcd3756c694722b27d3cc4eefc3"),
	      "bare address without 0x");
	check(!geth_valid_address("0x72b546804152cdcd3756c694722b27d3cc4eef"),
	      "address too short");
	check(!geth_valid_address("0x72b546804152cdcd3756c694722b27d3cc4eefc3ff"),
	      "address too long");
	check(!geth_valid_address("0x72b546804152cdcd3756c694722b27d3cc4eefg3"),
	      "non-hex letter");
	check(!geth_valid_address("0x72b546804152cdcd3756c694722b27d3cc4eefc-"),
	      "non-hex punctuation");
	check(!geth_valid_address("neb1qqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqq"),
	      "bech32 address is not an EVM address");
}

void test_rpc_request()
{
	char msg[512];

	rpc_build_request(msg, sizeof(msg), "eth_blockNumber", NULL, 7);
	check(strcmp(msg, "{\"jsonrpc\":\"2.0\",\"method\":\"eth_blockNumber\",\"id\":\"7\"}") == 0,
	      "request without params");

	rpc_build_request(msg, sizeof(msg), "xhash_getWork", "[]", 8);
	check(strcmp(msg, "{\"jsonrpc\":\"2.0\",\"method\":\"xhash_getWork\",\"params\":[],\"id\":\"8\"}") == 0,
	      "request with params");

	rpc_build_request(msg, sizeof(msg), "validateaddress", "[\"0xabc\"]", 9);
	check(strstr(msg, "\"jsonrpc\":\"2.0\"") != NULL, "jsonrpc field present");
	check(strstr(msg, "\"params\":[\"0xabc\"]") != NULL, "params preserved");
	check(strstr(msg, "\"id\":\"9\"") != NULL, "id preserved");
}

} // namespace

int main(int argc, char **argv)
{
	test_geth_address();
	test_rpc_request();

	if (failures) {
		fprintf(stderr, "gethcheck: %d failure(s)\n", failures);
		return 1;
	}
	printf("gethcheck: address and RPC framing ok\n");
	return 0;
}
