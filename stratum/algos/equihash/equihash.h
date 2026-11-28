#ifndef EQUIHASH_H
#define EQUIHASH_H

#define USE_LIBSODIUM

#include <stdint.h>
#include "sodium.h"

#include <cstring>
#include <exception>
#include <functional>
#include <iostream>
#include <memory>
#include <set>
#include <stdexcept>
#include <vector>

// miner nonce "cursor" unique for each thread
#define EQNONCE_OFFSET 30 /* 27:34 */

#define EQUIHASH48_5_WN 48
#define EQUIHASH48_5_WK 5
#define EQUIHASH96_3_WN 96
#define EQUIHASH96_3_WK 3
#define EQUIHASH96_5_WN 96
#define EQUIHASH96_5_WK 5
#define EQUIHASH125_4_WN 125
#define EQUIHASH125_4_WK 4
#define EQUIHASH144_5_WN 144
#define EQUIHASH144_5_WK 5
#define EQUIHASH192_7_WN 192
#define EQUIHASH192_7_WK 7
#define EQUIHASH200_9_WN 200
#define EQUIHASH200_9_WK 9

#if ((__GNUC__ > 4) || (__GNUC__ == 4 && __GNUC_MINOR__ >= 3))
#define WANT_BUILTIN_BSWAP
#else
#define bswap_32(x) ((((x) << 24) & 0xff000000u) | (((x) << 8) & 0x00ff0000u) \
                   | (((x) >> 8) & 0x0000ff00u) | (((x) >> 24) & 0x000000ffu))
#define bswap_64(x) (((uint64_t) bswap_32((uint32_t)((x) & 0xffffffffu)) << 32) \
                   | (uint64_t) bswap_32((uint32_t)((x) >> 32)))
#endif

static inline uint32_t swab32(uint32_t v)
{
#ifdef WANT_BUILTIN_BSWAP
	return __builtin_bswap32(v);
#else
	return bswap_32(v);
#endif
}

void equi_hash(const char* input, char* output, uint32_t len);
typedef crypto_generichash_blake2b_state eh_HashState;
typedef uint32_t eh_index;
typedef uint8_t eh_trunc;

bool verifyEH(const char *hdr, const char *soln, int n, int k, char *pstring);

void ExpandArray(const unsigned char* in, size_t in_len,
                 unsigned char* out, size_t out_len,
                 size_t bit_len, size_t byte_pad=0);
void CompressArray(const unsigned char* in, size_t in_len,
                   unsigned char* out, size_t out_len,
                   size_t bit_len, size_t byte_pad=0);

eh_index ArrayToEhIndex(const unsigned char* array);
eh_trunc TruncateIndex(const eh_index i, const unsigned int ilen);

std::vector<eh_index> GetIndicesFromMinimal(std::vector<unsigned char> minimal,
                                            size_t cBitLen);
std::vector<unsigned char> GetMinimalFromIndices(std::vector<eh_index> indices,
                                                 size_t cBitLen);

template<size_t WIDTH>
class StepRow
{
    template<size_t W>
    friend class StepRow;
    friend class CompareSR;

protected:
    unsigned char hash[WIDTH];

public:
    StepRow(const unsigned char* hashIn, size_t hInLen,
            size_t hLen, size_t cBitLen);
    ~StepRow() { }

    template<size_t W>
    StepRow(const StepRow<W>& a);

    bool IsZero(size_t len);

    template<size_t W>
    friend bool HasCollision(StepRow<W>& a, StepRow<W>& b, int l);
};

class CompareSR
{
private:
    size_t len;

public:
    CompareSR(size_t l) : len {l} { }

    template<size_t W>
    inline bool operator()(const StepRow<W>& a, const StepRow<W>& b) { return memcmp(a.hash, b.hash, len) < 0; }
};

template<size_t WIDTH>
bool HasCollision(StepRow<WIDTH>& a, StepRow<WIDTH>& b, int l);

template<size_t WIDTH>
class FullStepRow : public StepRow<WIDTH>
{
    template<size_t W>
    friend class FullStepRow;

    using StepRow<WIDTH>::hash;

public:
    FullStepRow(const unsigned char* hashIn, size_t hInLen,
                size_t hLen, size_t cBitLen, eh_index i);
    ~FullStepRow() { }

    FullStepRow(const FullStepRow<WIDTH>& a) : StepRow<WIDTH> {a} { }
    template<size_t W>
    FullStepRow(const FullStepRow<W>& a, const FullStepRow<W>& b, size_t len, size_t lenIndices, int trim);
    FullStepRow& operator=(const FullStepRow<WIDTH>& a);

    inline bool IndicesBefore(const FullStepRow<WIDTH>& a, size_t len, size_t lenIndices) const { return memcmp(hash+len, a.hash+len, lenIndices) < 0; }
    std::vector<unsigned char> GetIndices(size_t len, size_t lenIndices,
                                          size_t cBitLen) const;

    template<size_t W>
    friend bool DistinctIndices(const FullStepRow<W>& a, const FullStepRow<W>& b,
                                size_t len, size_t lenIndices);
    template<size_t W>
    friend bool IsValidBranch(const FullStepRow<W>& a, const size_t len, const unsigned int ilen, const eh_trunc t);
};

template<size_t WIDTH>
class TruncatedStepRow : public StepRow<WIDTH>
{
    template<size_t W>
    friend class TruncatedStepRow;

    using StepRow<WIDTH>::hash;

public:
    TruncatedStepRow(const unsigned char* hashIn, size_t hInLen,
                     size_t hLen, size_t cBitLen,
                     eh_index i, unsigned int ilen);
    ~TruncatedStepRow() { }

    TruncatedStepRow(const TruncatedStepRow<WIDTH>& a) : StepRow<WIDTH> {a} { }
    template<size_t W>
    TruncatedStepRow(const TruncatedStepRow<W>& a, const TruncatedStepRow<W>& b, size_t len, size_t lenIndices, int trim);
    TruncatedStepRow& operator=(const TruncatedStepRow<WIDTH>& a);

    inline bool IndicesBefore(const TruncatedStepRow<WIDTH>& a, size_t len, size_t lenIndices) const { return memcmp(hash+len, a.hash+len, lenIndices) < 0; }
    std::shared_ptr<eh_trunc> GetTruncatedIndices(size_t len, size_t lenIndices) const;
};

enum EhSolverCancelCheck
{
    ListGeneration,
    ListSorting,
    ListColliding,
    RoundEnd,
    FinalSorting,
    FinalColliding,
    PartialGeneration,
    PartialSorting,
    PartialSubtreeEnd,
    PartialIndexEnd,
    PartialEnd
};

inline constexpr const size_t maxEH(const size_t A, const size_t B) { return A > B ? A : B; }

inline constexpr size_t equihash_solution_size(unsigned int N, unsigned int K) {
    return (1 << K)*(N/(K+1)+1)/8;
}

template<unsigned int N, unsigned int K>
class Equihash
{
private:
//    assert(K < N);
//    assert(N % 8 == 0);
//    assert((N/(K+1)) + 1 < 8*sizeof(eh_index));

public:
    enum : size_t { IndicesPerHashOutput=512/N };
    enum : size_t { HashOutput= (N == 125)? (IndicesPerHashOutput*((N+7)/8)) : (IndicesPerHashOutput*N/8) };
    enum : size_t { CollisionBitLength=N/(K+1) };
    enum : size_t { CollisionByteLength=(CollisionBitLength+7)/8 };
    enum : size_t { HashLength=(K+1)*CollisionByteLength };
    enum : size_t { FullWidth=2*CollisionByteLength+sizeof(eh_index)*(1 << (K-1)) };
    enum : size_t { FinalFullWidth=2*CollisionByteLength+sizeof(eh_index)*(1 << (K)) };
    enum : size_t { TruncatedWidth=maxEH(HashLength+sizeof(eh_trunc), 2*CollisionByteLength+sizeof(eh_trunc)*(1 << (K-1))) };
    enum : size_t { FinalTruncatedWidth=maxEH(HashLength+sizeof(eh_trunc), 2*CollisionByteLength+sizeof(eh_trunc)*(1 << (K))) };
    enum : size_t { SolutionWidth=(1 << K)*(CollisionBitLength+1)/8 };

    Equihash() { }

    int InitialiseState(eh_HashState& base_state, char *personalization_string);
    bool IsValidSolution(const eh_HashState& base_state, std::vector<unsigned char> soln);
};

#include "equihash.tcc"

static Equihash<96,3> Eh96_3;
static Equihash<200,9> Eh200_9;
static Equihash<96,5> Eh96_5;
static Equihash<48,5> Eh48_5;
static Equihash<144,5> Eh144_5;
static Equihash<125,4> Eh125_4;
static Equihash<192,7> Eh192_7;

#define EhInitialiseState(n, k, base_state, personalization_string)  \
    if (n == 96 && k == 3) {                 \
        Eh96_3.InitialiseState(base_state, personalization_string);  \
    } else if (n == 200 && k == 9) {         \
        Eh200_9.InitialiseState(base_state, personalization_string); \
    } else if (n == 96 && k == 5) {          \
        Eh96_5.InitialiseState(base_state, personalization_string);  \
    } else if (n == 48 && k == 5) {          \
        Eh48_5.InitialiseState(base_state, personalization_string);  \
    } else if (n == 144 && k == 5) {          \
        Eh144_5.InitialiseState(base_state, personalization_string);  \
    } else if (n == 125 && k == 4) {          \
        Eh125_4.InitialiseState(base_state, personalization_string);  \
    } else if (n == 192 && k == 7) {          \
        Eh192_7.InitialiseState(base_state, personalization_string);  \
    } else {                                 \
        throw std::invalid_argument("Unsupported Equihash parameters"); \
    }

#define EhIsValidSolution(n, k, base_state, soln, ret)   \
    if (n == 96 && k == 3) {                             \
        ret = Eh96_3.IsValidSolution(base_state, soln);  \
    } else if (n == 200 && k == 9) {                     \
        ret = Eh200_9.IsValidSolution(base_state, soln); \
    } else if (n == 96 && k == 5) {                      \
        ret = Eh96_5.IsValidSolution(base_state, soln);  \
    } else if (n == 48 && k == 5) {                      \
        ret = Eh48_5.IsValidSolution(base_state, soln);  \
    } else if (n == 144 && k == 5) {                      \
        ret = Eh144_5.IsValidSolution(base_state, soln);  \
    } else if (n == 125 && k == 4) {                      \
        ret = Eh125_4.IsValidSolution(base_state, soln);  \
    } else if (n == 192 && k == 7) {                      \
        ret = Eh192_7.IsValidSolution(base_state, soln);  \
    } else {                                             \
        throw std::invalid_argument("Unsupported Equihash parameters"); \
    }

#endif // BITCOIN_EQUIHASH_H
