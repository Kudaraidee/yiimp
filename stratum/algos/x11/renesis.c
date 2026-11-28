// Copyright (c) 2009-2010 Satoshi Nakamoto
// Copyright (c) 2009-2012 The Bitcoin developers
// Distributed under the MIT/X11 software license, see the accompanying
// file COPYING or http://www.opensource.org/licenses/mit-license.php.
// Copyright (c) 2018, hav0k Renesis Developers & Renesis Group
// This is the pre-fork hash for Renesis.
// http://renesis.io

#include <stdlib.h>
#include <stdint.h>
#include <string.h>
#include <stdio.h>
#include "algos/common/sph512.h"

void renesis_hash(const char* input, char* output, uint32_t len)
{
	uint32_t hash[64];
	
	sph512(SPH_SKEIN512, input, len, hash);

	sph512(SPH_KECCAK512, hash, 64, hash);

	sph512(SPH_SIMD512, hash, 64, hash);

	sph512(SPH_SHAVITE512, hash, 64, hash);

	sph512(SPH_JH512, hash, 64, hash);

	sph512(SPH_CUBEHASH512, hash, 64, hash);

	sph512(SPH_FUGUE512, hash, 64, hash);

	sph512(SPH_GOST512, hash, 64, hash);

	memcpy(output, hash, 32);
}