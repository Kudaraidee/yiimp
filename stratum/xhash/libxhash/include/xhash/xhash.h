/* xhash: C/C++ implementation of XHash, the Ethereum Proof of Work algorithm.
 * Copyright 2018-2019 Pawel Bylica.
 * Licensed under the Apache License, Version 2.0.
 */

#pragma once

#include <stdbool.h>
#include <stdint.h>
#include <xhash/hash_types.h>

#ifndef __has_cpp_attribute
#define __has_cpp_attribute(X) 0
#endif

#ifndef __has_attribute
#define __has_attribute(X) 0
#endif

#ifndef __cplusplus
#define noexcept  // Ignore noexcept in C code.
#endif

#ifdef __cplusplus
extern "C" {
#endif

#define XHASH_REVISION "23"

#define XHASH_EPOCH_LENGTH 720
#define XHASH_LIGHT_CACHE_ITEM_SIZE 64
#define XHASH_FULL_DATASET_ITEM_SIZE 128
#define XHASH_NUM_DATASET_ACCESSES 64

/// The maximum epoch number supported by this implementation.
///
/// The value represents the last epoch where the light cache size fits 4GB size limit.
/// It also allows to make some assumptions about the max values of datasets indices.
/// The DAG size in the last epoch is 252GB and the block number is above 979M
/// which gives over 60 years of blocks assuming 2s block times.
#define XHASH_MAX_EPOCH_NUMBER 32639

/** XHash error codes. */
enum xhash_errc
{
    XHASH_SUCCESS = 0,
    XHASH_INVALID_FINAL_HASH = 1,
    XHASH_INVALID_MIX_HASH = 2
};
typedef enum xhash_errc xhash_errc;


struct xhash_epoch_context
{
    const int epoch_number;
    const int light_cache_num_items;
    const union xhash_hash512* const light_cache;
    const int full_dataset_num_items;
};


struct xhash_epoch_context_full;


struct xhash_result
{
    union xhash_hash256 final_hash;
    union xhash_hash256 mix_hash;
};


/**
 * Calculates the number of items in the light cache for given epoch.
 *
 * This function will search for a prime number matching the criteria given
 * by the XHash so the execution time is not constant. It takes ~ 0.01 ms.
 *
 * @param epoch_number  The epoch number.
 * @return              The number items in the light cache.
 */
int xhash_calculate_light_cache_num_items(int epoch_number) noexcept;


/**
 * Calculates the number of items in the full dataset for given epoch.
 *
 * This function will search for a prime number matching the criteria given
 * by the XHash so the execution time is not constant. It takes ~ 0.05 ms.
 *
 * @param epoch_number  The epoch number.
 * @return              The number items in the full dataset.
 */
int xhash_calculate_full_dataset_num_items(int epoch_number) noexcept;

/**
 * Calculates the epoch seed hash.
 * @param epoch_number  The epoch number.
 * @return              The epoch seed hash.
 */
union xhash_hash256 xhash_calculate_epoch_seed(int epoch_number) noexcept;


struct xhash_epoch_context* xhash_create_epoch_context(int epoch_number) noexcept;

/**
 * Creates the epoch context with the full dataset initialized.
 *
 * The memory for the full dataset is only allocated and marked as "not-generated".
 * The items of the full dataset are generated on the fly when hit for the first time.
 *
 * The memory allocated in the context MUST be freed with xhash_destroy_epoch_context_full().
 *
 * @param epoch_number  The epoch number.
 * @return  Pointer to the context or null in case of memory allocation failure.
 */
struct xhash_epoch_context_full* xhash_create_epoch_context_full(int epoch_number) noexcept;

void xhash_destroy_epoch_context(struct xhash_epoch_context* context) noexcept;

void xhash_destroy_epoch_context_full(struct xhash_epoch_context_full* context) noexcept;


struct xhash_result xhash_hash(const struct xhash_epoch_context* context,
    const union xhash_hash256* header_hash, uint64_t nonce) noexcept;

/**
 * Verify XHash validity of a header hash against given boundary.
 *
 * This checks if final hash header_hash is within difficulty boundary header_hash <= boundary,
 * where boundary = 2^256 / difficulty.
 * It also checks if the XHash result produced out of (header_hash, nonce) matches the provided
 * mix_hash.
 *
 * In most use-cases users should have access to the difficulty value directly therefore
 * usage of xhash_verify_against_difficulty() is recommended instead.
 *
 * @return  Error code: ::XHASH_SUCCESS if valid, ::XHASH_INVALID_FINAL_HASH if the final hash is
 *          not within provided boundary, ::XHASH_INVALID_MIX_HASH if the provided mix hash
 *          mismatches the computed one.
 */
xhash_errc xhash_verify_against_boundary(const struct xhash_epoch_context* context,
    const union xhash_hash256* header_hash, const union xhash_hash256* mix_hash, uint64_t nonce,
    const union xhash_hash256* boundary) noexcept;

/**
 * Verify XHash validity of a header hash against given difficulty.
 *
 * This checks if final hash header_hash satisfies difficulty requirement
 * header_hash <= (2^256 / difficulty). The checks is implemented by multiplication
 * header_hash * difficulty <= 2^256 therefore big-number division is not needed.
 * It also checks if the XHash result produced out of (header_hash, nonce) matches the provided
 * mix_hash.
 *
 * @param difficulty  The difficulty number as big-endian 256-bit value. The encoding matches the
 *                    way the difficulty is stored in block headers.
 *
 * @return  Error code: ::XHASH_SUCCESS if valid, ::XHASH_INVALID_FINAL_HASH if the final hash
 *          does not satisfy difficulty, ::XHASH_INVALID_MIX_HASH if the provided mix hash
 *          mismatches the computed one.
 */
xhash_errc xhash_verify_against_difficulty(const struct xhash_epoch_context* context,
    const union xhash_hash256* header_hash, const union xhash_hash256* mix_hash, uint64_t nonce,
    const union xhash_hash256* difficulty) noexcept;


/**
 * Verify only the final hash. This can be performed quickly without accessing XHash context.
 *
 * @return  Error code: ::XHASH_SUCCESS if valid, ::XHASH_INVALID_FINAL_HASH if the final hash
 *          does not satisfy difficulty.
 */
xhash_errc xhash_verify_final_hash_against_difficulty(const union xhash_hash256* header_hash,
    const union xhash_hash256* mix_hash, uint64_t nonce,
    const union xhash_hash256* difficulty) noexcept;

#ifdef __cplusplus
}
#endif
