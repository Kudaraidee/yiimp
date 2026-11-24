/* meraki: C/C++ implementation of Ethash, the Ethereum Proof of Work algorithm.
 * Copyright 2018-2019 Pawel Bylica.
 * Licensed under the Apache License, Version 2.0.
 */

#pragma once

#include "hash_types.h"

#include <stdbool.h>
#include <stdint.h>

#if defined(_MSC_VER)
//  Microsoft
#define EXPORT __declspec(dllexport)
#define IMPORT __declspec(dllimport)
#elif defined(__GNUC__)
//  GCC
#define EXPORT __attribute__((visibility("default")))
#define IMPORT
#else
//  do nothing and hope for the best?
#define EXPORT
#define IMPORT
#pragma warning Unknown dynamic link import / export semantics.
#endif

#ifdef __cplusplus
#define NOEXCEPT noexcept
#else
#define NOEXCEPT
#endif

#ifdef __cplusplus
extern "C"
{
#endif

/**
 * The Ethash algorithm revision implemented as specified in the Ethash spec
 * https://github.com/ethereum/wiki/wiki/Ethash.
 */
#define MERAKI_REVISION "23"

#define MERAKI_EPOCH_LENGTH 27500
#define MERAKI_LIGHT_CACHE_ITEM_SIZE 64
#define MERAKI_FULL_DATASET_ITEM_SIZE 128
#define MERAKI_NUM_DATASET_ACCESSES 32

    struct meraki_epoch_context
    {
        const int epoch_number;
        const int light_cache_num_items;
        const union meraki_hash512 *const light_cache;
        const uint32_t *const l1_cache;
        const int full_dataset_num_items;
    };

    struct meraki_epoch_context_full;

    struct meraki_result
    {
        union meraki_hash256 final_hash;
        union meraki_hash256 mix_hash;
    };

    /**
     * Calculates the number of items in the light cache for given epoch.
     *
     * This function will search for a prime number matching the criteria given
     * by the Ethash so the execution time is not constant. It takes ~ 0.01 ms.
     *
     * @param epoch_number  The epoch number.
     * @return              The number items in the light cache.
     */
    int meraki_calculate_light_cache_num_items(int epoch_number) NOEXCEPT;

    /**
     * Calculates the number of items in the full dataset for given epoch.
     *
     * This function will search for a prime number matching the criteria given
     * by the Ethash so the execution time is not constant. It takes ~ 0.05 ms.
     *
     * @param epoch_number  The epoch number.
     * @return              The number items in the full dataset.
     */
    int meraki_calculate_full_dataset_num_items(int epoch_number) NOEXCEPT;

    /**
     * Calculates the epoch seed hash.
     * @param epoch_number  The epoch number.
     * @return              The epoch seed hash.
     */
    EXPORT union meraki_hash256 meraki_calculate_epoch_seed(int epoch_number) NOEXCEPT;

    EXPORT struct meraki_epoch_context *meraki_create_epoch_context(int epoch_number) NOEXCEPT;

    /**
     * Creates the epoch context with the full dataset initialized.
     *
     * The memory for the full dataset is only allocated and marked as "not-generated".
     * The items of the full dataset are generated on the fly when hit for the first time.
     *
     * The memory allocated in the context MUST be freed with meraki_destroy_epoch_context_full().
     *
     * @param epoch_number  The epoch number.
     * @return  Pointer to the context or null in case of memory allocation failure.
     */
    EXPORT struct meraki_epoch_context_full *meraki_create_epoch_context_full(int epoch_number) NOEXCEPT;

    EXPORT void meraki_destroy_epoch_context(struct meraki_epoch_context *context) NOEXCEPT;

    EXPORT void meraki_destroy_epoch_context_full(struct meraki_epoch_context_full *context) NOEXCEPT;

    /**
     * Get global shared epoch context.
     */
    const struct meraki_epoch_context *meraki_get_global_epoch_context(int epoch_number) NOEXCEPT;

    /**
     * Get global shared epoch context with full dataset initialized.
     */
    const struct meraki_epoch_context_full *meraki_get_global_epoch_context_full(
        int epoch_number) NOEXCEPT;

    struct meraki_result meraki_hash(const struct meraki_epoch_context *context,
                                     const union meraki_hash256 *header_hash, uint64_t nonce) NOEXCEPT;

    bool meraki_verify(const struct meraki_epoch_context *context,
                       const union meraki_hash256 *header_hash, const union meraki_hash256 *mix_hash, uint64_t nonce,
                       const union meraki_hash256 *boundary) NOEXCEPT;
    bool meraki_verify_final_hash(const union meraki_hash256 *header_hash,
                                  const union meraki_hash256 *mix_hash, uint64_t nonce,
                                  const union meraki_hash256 *boundary) NOEXCEPT;

#ifdef __cplusplus
}
#endif
