/* xhash: C/C++ implementation of XHash, the Ethereum Proof of Work algorithm.
 * Copyright 2019 Pawel Bylica.
 * Licensed under the Apache License, Version 2.0.
 */

#pragma once

/** The xhash library version. */
#define XHASH_VERSION "1.1.0"

#ifdef __cplusplus
namespace xhash
{
/// The xhash library version.
constexpr auto version = XHASH_VERSION;

}  // namespace xhash
#endif
