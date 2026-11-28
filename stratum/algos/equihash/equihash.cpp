/**
 * Equihash solver interface for ccminer (compatible with linux and windows)
 * Solver taken from nheqminer, by djeZo (and NiceHash)
 * tpruvot - 2017 (GPL v3)
 */
#include <stdio.h>
#include <unistd.h>
#include <assert.h>
#include <string.h>
#include <stdint.h>
#include <stdbool.h>

#include "algos/sha2/sha256.h"

#include "equihash.h" // equi_verify()

#include <algorithm>
#include <iostream>
#include <stdexcept>
#include <vector>

#define USE_LIBSODIUM

//#define EQNONCE_OFFSET 30 /* 27:34 */
#define NONCE_OFT EQNONCE_OFFSET

#ifndef htobe32
#define htobe32(x) swab32(x)
#endif

// https://github.com/str4d/zcash/blob/standalone-miner/src/crypto/equihash.cpp
#ifdef USE_LIBSODIUM
#include "sodium.h"
#define blake2b_state crypto_generichash_blake2b_state
#else
#include "algos/argon2/blake2/blake2.h"
#define be32toh(x) swab32(x)
#define htole32(x) (x)
#define HASHOUT 50
#endif

extern "C" void sha256_double_hash(const char *input, char *output, unsigned int len) ;

// All solutions (BLOCK_HEADER_LEN + SOLSIZE_LEN + SOL_LEN) sha256d should be under the target
void equi_hash(const char* input, char* output, uint32_t len)
{
	sha256_double_hash(input, output, len);
}

bool verifyEH(const char *hdr, const char *soln, int n, int k, char *pstring){

	size_t cBitLen { n /(k+1)};
	eh_HashState state;
	EhInitialiseState(n, k, state, pstring);

	//Update header and nonce in same call
	crypto_generichash_blake2b_update(&state, (unsigned char*)&hdr[0], 140);

	int sollength;
	if (n==144 && k==5) sollength = 100;
	else if (n==96 && k==5) sollength = 68;
	else if (n==125 && k==4) sollength = 52;
	else if (n==48 && k==5) sollength = 36;
	else if (n==192 && k==7) sollength = 400;
	else sollength = 1344; // default equihash n=200; k=9

	std::vector<unsigned char> minimal(soln, soln + sollength);

	bool isValid;
	EhIsValidSolution(n,k,state, minimal, isValid);
	return isValid;
}

template<unsigned int N, unsigned int K>
int Equihash<N,K>::InitialiseState(eh_HashState& base_state, char *personalization_string)
{
    uint32_t le_N = htole32(N);
    uint32_t le_K = htole32(K);
    unsigned char personalization[crypto_generichash_blake2b_PERSONALBYTES] = {};
    if ((personalization_string == NULL) || (strlen(personalization_string)<8)) {
    	memcpy(personalization, "ZcashPoW", 8);
    }
    else {
    	memcpy(personalization, personalization_string, 8);
	}
    memcpy(personalization+8,  &le_N, 4);
    memcpy(personalization+12, &le_K, 4);

    bool isZelHash = ((N == 125) && (K == 4));

    if (isZelHash)
    	return crypto_generichash_blake2b_init_salt_personal(&base_state,
                                                         NULL, 0, // No key.
                                                         (512/N)*((N+7)/8),
                                                         NULL,    // No salt.
                                                         personalization);
    else
    	return crypto_generichash_blake2b_init_salt_personal(&base_state,
                                                         NULL, 0, // No key.
														 (512/N)*N/8,
                                                         NULL,    // No salt.
                                                         personalization);
}

void GenerateHash(const eh_HashState& base_state, eh_index g,
                  unsigned char* hash, size_t hLen, bool isZelHash)
{
	if (!isZelHash) {
		eh_HashState state;
		state = base_state;
		eh_index lei = htole32(g);
		crypto_generichash_blake2b_update(&state, (const unsigned char*) &lei,
									  sizeof(eh_index));
		crypto_generichash_blake2b_final(&state, hash, hLen);
	} else {
		uint32_t myHash[16] = {0};
		uint32_t startIndex = g & 0xFFFFFFF0;

		for (uint32_t g2 = startIndex; g2 <= g; g2++) {
			uint32_t tmpHash[16] = {0};

			eh_HashState state;
			state = base_state;
			eh_index lei = htole32(g2);
			crypto_generichash_blake2b_update(&state, (const unsigned char*) &lei,
									  sizeof(eh_index));

			crypto_generichash_blake2b_final(&state, (unsigned char*)&tmpHash[0], hLen);

			for (uint32_t idx = 0; idx < 16; idx++) myHash[idx] += tmpHash[idx];
		}

		uint8_t * hashBytes = (uint8_t *) &myHash[0];

		for (uint32_t i=15; i<hLen; i+=16) hashBytes[i] &= 0xF8;

		memcpy(hash, &myHash[0], hLen);
	}
}

void ExpandArray(const unsigned char* in, size_t in_len,
                 unsigned char* out, size_t out_len,
                 size_t bit_len, size_t byte_pad)
{
    assert(bit_len >= 8);
    assert(8*sizeof(uint32_t) >= bit_len);

    size_t out_width { (bit_len+7)/8 + byte_pad };
    assert(out_len == 8*out_width*in_len/bit_len);

    uint32_t bit_len_mask { ((uint32_t)1 << bit_len) - 1 };

    // The acc_bits least-significant bits of acc_value represent a bit sequence
    // in big-endian order.
    size_t acc_bits = 0;
    uint32_t acc_value = 0;

    size_t j = 0;
    for (size_t i = 0; i < in_len; i++) {
        acc_value = (acc_value << 8) | in[i];
        acc_bits += 8;

        // When we have bit_len or more bits in the accumulator, write the next
        // output element.
        if (acc_bits >= bit_len) {
            acc_bits -= bit_len;
            for (size_t x = 0; x < byte_pad; x++) {
                out[j+x] = 0;
            }
            for (size_t x = byte_pad; x < out_width; x++) {
                out[j+x] = (
                    // Big-endian
                    acc_value >> (acc_bits+(8*(out_width-x-1)))
                ) & (
                    // Apply bit_len_mask across byte boundaries
                    (bit_len_mask >> (8*(out_width-x-1))) & 0xFF
                );
            }
            j += out_width;
        }
    }
}

void CompressArray(const unsigned char* in, size_t in_len,
                   unsigned char* out, size_t out_len,
                   size_t bit_len, size_t byte_pad)
{
    assert(bit_len >= 8);
    assert(8*sizeof(uint32_t) >= bit_len);

    size_t in_width { (bit_len+7)/8 + byte_pad };
    assert(out_len == bit_len*in_len/(8*in_width));

    uint32_t bit_len_mask { ((uint32_t)1 << bit_len) - 1 };

    // The acc_bits least-significant bits of acc_value represent a bit sequence
    // in big-endian order.
    size_t acc_bits = 0;
    uint32_t acc_value = 0;

    size_t j = 0;
    for (size_t i = 0; i < out_len; i++) {
        // When we have fewer than 8 bits left in the accumulator, read the next
        // input element.
        if (acc_bits < 8) {
            acc_value = acc_value << bit_len;
            for (size_t x = byte_pad; x < in_width; x++) {
                acc_value = acc_value | (
                    (
                        // Apply bit_len_mask across byte boundaries
                        in[j+x] & ((bit_len_mask >> (8*(in_width-x-1))) & 0xFF)
                    ) << (8*(in_width-x-1))); // Big-endian
            }
            j += in_width;
            acc_bits += bit_len;
        }

        acc_bits -= 8;
        out[i] = (acc_value >> acc_bits) & 0xFF;
    }
}

// Big-endian so that lexicographic array comparison is equivalent to integer
// comparison
void EhIndexToArray(const eh_index i, unsigned char* array)
{
    assert(sizeof(eh_index) == 4);
    eh_index bei = htobe32(i);
    memcpy(array, &bei, sizeof(eh_index));
}

// Big-endian so that lexicographic array comparison is equivalent to integer
// comparison
eh_index ArrayToEhIndex(const unsigned char* array)
{
    assert(sizeof(eh_index) == 4);
    eh_index bei;
    memcpy(&bei, array, sizeof(eh_index));
    return be32toh(bei);
}

eh_trunc TruncateIndex(const eh_index i, const unsigned int ilen)
{
    // Truncate to 8 bits
    assert(sizeof(eh_trunc) == 1);
    return (i >> (ilen - 8)) & 0xff;
}

eh_index UntruncateIndex(const eh_trunc t, const eh_index r, const unsigned int ilen)
{
    eh_index i{t};
    return (i << (ilen - 8)) | r;
}

std::vector<eh_index> GetIndicesFromMinimal(std::vector<unsigned char> minimal,
                                            size_t cBitLen)
{
    assert(((cBitLen+1)+7)/8 <= sizeof(eh_index));
    size_t lenIndices { 8*sizeof(eh_index)*minimal.size()/(cBitLen+1) };
    size_t bytePad { sizeof(eh_index) - ((cBitLen+1)+7)/8 };
    std::vector<unsigned char> array(lenIndices);
    ExpandArray(minimal.data(), minimal.size(),
                array.data(), lenIndices, cBitLen+1, bytePad);
    std::vector<eh_index> ret;
    for (int i = 0; i < lenIndices; i += sizeof(eh_index)) {
        ret.push_back(ArrayToEhIndex(array.data()+i));
    }
    return ret;
}

std::vector<unsigned char> GetMinimalFromIndices(std::vector<eh_index> indices,
                                                 size_t cBitLen)
{
    assert(((cBitLen+1)+7)/8 <= sizeof(eh_index));
    size_t lenIndices { indices.size()*sizeof(eh_index) };
    size_t minLen { (cBitLen+1)*lenIndices/(8*sizeof(eh_index)) };
    size_t bytePad { sizeof(eh_index) - ((cBitLen+1)+7)/8 };
    std::vector<unsigned char> array(lenIndices);
    for (int i = 0; i < indices.size(); i++) {
        EhIndexToArray(indices[i], array.data()+(i*sizeof(eh_index)));
    }
    std::vector<unsigned char> ret(minLen);
    CompressArray(array.data(), lenIndices,
                  ret.data(), minLen, cBitLen+1, bytePad);
    return ret;
}

template<size_t WIDTH>
StepRow<WIDTH>::StepRow(const unsigned char* hashIn, size_t hInLen,
                        size_t hLen, size_t cBitLen)
{
    assert(hLen <= WIDTH);
    ExpandArray(hashIn, hInLen, hash, hLen, cBitLen);
}

template<size_t WIDTH> template<size_t W>
StepRow<WIDTH>::StepRow(const StepRow<W>& a)
{
    assert(W <= WIDTH);
    std::copy(a.hash, a.hash+W, hash);
}

template<size_t WIDTH>
FullStepRow<WIDTH>::FullStepRow(const unsigned char* hashIn, size_t hInLen,
                                size_t hLen, size_t cBitLen, eh_index i) :
        StepRow<WIDTH> {hashIn, hInLen, hLen, cBitLen}
{
    EhIndexToArray(i, hash+hLen);
}

template<size_t WIDTH> template<size_t W>
FullStepRow<WIDTH>::FullStepRow(const FullStepRow<W>& a, const FullStepRow<W>& b, size_t len, size_t lenIndices, int trim) :
        StepRow<WIDTH> {a}
{
    assert(len+lenIndices <= W);
    assert(len-trim+(2*lenIndices) <= WIDTH);
    for (int i = trim; i < len; i++)
        hash[i-trim] = a.hash[i] ^ b.hash[i];
    if (a.IndicesBefore(b, len, lenIndices)) {
        std::copy(a.hash+len, a.hash+len+lenIndices, hash+len-trim);
        std::copy(b.hash+len, b.hash+len+lenIndices, hash+len-trim+lenIndices);
    } else {
        std::copy(b.hash+len, b.hash+len+lenIndices, hash+len-trim);
        std::copy(a.hash+len, a.hash+len+lenIndices, hash+len-trim+lenIndices);
    }
}

template<size_t WIDTH>
FullStepRow<WIDTH>& FullStepRow<WIDTH>::operator=(const FullStepRow<WIDTH>& a)
{
    std::copy(a.hash, a.hash+WIDTH, hash);
    return *this;
}

template<size_t WIDTH>
bool StepRow<WIDTH>::IsZero(size_t len)
{
    // This doesn't need to be constant time.
    for (int i = 0; i < len; i++) {
        if (hash[i] != 0)
            return false;
    }
    return true;
}

template<size_t WIDTH>
std::vector<unsigned char> FullStepRow<WIDTH>::GetIndices(size_t len, size_t lenIndices,
                                                          size_t cBitLen) const
{
    assert(((cBitLen+1)+7)/8 <= sizeof(eh_index));
    size_t minLen { (cBitLen+1)*lenIndices/(8*sizeof(eh_index)) };
    size_t bytePad { sizeof(eh_index) - ((cBitLen+1)+7)/8 };
    std::vector<unsigned char> ret(minLen);
    CompressArray(hash+len, lenIndices, ret.data(), minLen, cBitLen+1, bytePad);
    return ret;
}

template<size_t WIDTH>
bool HasCollision(StepRow<WIDTH>& a, StepRow<WIDTH>& b, int l)
{
    // This doesn't need to be constant time.
    for (int j = 0; j < l; j++) {
        if (a.hash[j] != b.hash[j])
            return false;
    }
    return true;
}

template<size_t WIDTH>
TruncatedStepRow<WIDTH>::TruncatedStepRow(const unsigned char* hashIn, size_t hInLen,
                                          size_t hLen, size_t cBitLen,
                                          eh_index i, unsigned int ilen) :
        StepRow<WIDTH> {hashIn, hInLen, hLen, cBitLen}
{
    hash[hLen] = TruncateIndex(i, ilen);
}

template<size_t WIDTH> template<size_t W>
TruncatedStepRow<WIDTH>::TruncatedStepRow(const TruncatedStepRow<W>& a, const TruncatedStepRow<W>& b, size_t len, size_t lenIndices, int trim) :
        StepRow<WIDTH> {a}
{
    assert(len+lenIndices <= W);
    assert(len-trim+(2*lenIndices) <= WIDTH);
    for (int i = trim; i < len; i++)
        hash[i-trim] = a.hash[i] ^ b.hash[i];
    if (a.IndicesBefore(b, len, lenIndices)) {
        std::copy(a.hash+len, a.hash+len+lenIndices, hash+len-trim);
        std::copy(b.hash+len, b.hash+len+lenIndices, hash+len-trim+lenIndices);
    } else {
        std::copy(b.hash+len, b.hash+len+lenIndices, hash+len-trim);
        std::copy(a.hash+len, a.hash+len+lenIndices, hash+len-trim+lenIndices);
    }
}

template<size_t WIDTH>
TruncatedStepRow<WIDTH>& TruncatedStepRow<WIDTH>::operator=(const TruncatedStepRow<WIDTH>& a)
{
    std::copy(a.hash, a.hash+WIDTH, hash);
    return *this;
}

template<size_t WIDTH>
std::shared_ptr<eh_trunc> TruncatedStepRow<WIDTH>::GetTruncatedIndices(size_t len, size_t lenIndices) const
{
    std::shared_ptr<eh_trunc> p (new eh_trunc[lenIndices], std::default_delete<eh_trunc[]>());
    std::copy(hash+len, hash+len+lenIndices, p.get());
    return p;
}


template<unsigned int N, unsigned int K>
bool Equihash<N,K>::IsValidSolution(const eh_HashState& base_state, std::vector<unsigned char> soln)
{
    if (soln.size() != SolutionWidth) {
        printf( "Invalid solution length: %d (expected %d)\n",
                 soln.size(), SolutionWidth);
        return false;
    }

    bool isZelHash = ((N == 125) && (K == 4));

    std::vector<FullStepRow<FinalFullWidth>> X;
    X.reserve(1 << K);
    unsigned char tmpHash[HashOutput];
    for (eh_index i : GetIndicesFromMinimal(soln, CollisionBitLength)) {
        GenerateHash(base_state, i/IndicesPerHashOutput, tmpHash, HashOutput, isZelHash);
        X.emplace_back(tmpHash+((i % IndicesPerHashOutput) * ((N+7)/8)),
        			   ((N+7)/8), HashLength, CollisionBitLength, i);
    }

    size_t hashLen = HashLength;
    size_t lenIndices = sizeof(eh_index);
    while (X.size() > 1) {
        std::vector<FullStepRow<FinalFullWidth>> Xc;
        for (int i = 0; i < X.size(); i += 2) {
            if (!HasCollision(X[i], X[i+1], CollisionByteLength)) {
                printf( "Invalid solution: invalid collision length between StepRows\n");
                return false;
            }
            if (X[i+1].IndicesBefore(X[i], hashLen, lenIndices)) {
                printf( "Invalid solution: Index tree incorrectly ordered\n");
                return false;
            }
            if (!DistinctIndices(X[i], X[i+1], hashLen, lenIndices)) {
                printf( "Invalid solution: duplicate indices\n");
                return false;
            }
            Xc.emplace_back(X[i], X[i+1], hashLen, lenIndices, CollisionByteLength);
        }
        X = Xc;
        hashLen -= CollisionByteLength;
        lenIndices *= 2;
    }

    assert(X.size() == 1);
    return X[0].IsZero(hashLen);
}

// Explicit instantiations for Equihash<96,3>
template int Equihash<96,3>::InitialiseState(eh_HashState& base_state, char *personalization_string);
template bool Equihash<96,3>::IsValidSolution(const eh_HashState& base_state, std::vector<unsigned char> soln);

// Explicit instantiations for Equihash<200,9>
template int Equihash<200,9>::InitialiseState(eh_HashState& base_state, char *personalization_string);
template bool Equihash<200,9>::IsValidSolution(const eh_HashState& base_state, std::vector<unsigned char> soln);

// Explicit instantiations for Equihash<96,5>
template int Equihash<96,5>::InitialiseState(eh_HashState& base_state, char *personalization_string);
template bool Equihash<96,5>::IsValidSolution(const eh_HashState& base_state, std::vector<unsigned char> soln);

// Explicit instantiations for Equihash<48,5>
template int Equihash<48,5>::InitialiseState(eh_HashState& base_state, char *personalization_string);
template bool Equihash<48,5>::IsValidSolution(const eh_HashState& base_state, std::vector<unsigned char> soln);

// Explicit instantiations for Equihash<144,5>
template int Equihash<144,5>::InitialiseState(eh_HashState& base_state, char *personalization_string);
template bool Equihash<144,5>::IsValidSolution(const eh_HashState& base_state, std::vector<unsigned char> soln);

// Explicit instantiations for Equihash<125,4>
template int Equihash<125,4>::InitialiseState(eh_HashState& base_state, char *personalization_string);
template bool Equihash<125,4>::IsValidSolution(const eh_HashState& base_state, std::vector<unsigned char> soln);

// Explicit instantiations for Equihash<192,7>
template int Equihash<192,7>::InitialiseState(eh_HashState& base_state, char *personalization_string);
template bool Equihash<192,7>::IsValidSolution(const eh_HashState& base_state, std::vector<unsigned char> soln);
