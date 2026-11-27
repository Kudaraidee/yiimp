// xhash: C/C++ implementation of XHash proof-of-work.
// SHA-3 (NIST) C++ wrappers matching keccak.hpp shape.
// Apache-2.0

#pragma once

#include <xhash/hash_types.hpp>
#include <xhash/sha3.h>

namespace xhash
{
inline hash256 sha3_256(const uint8_t* data, size_t size) noexcept
{
    return xhash_sha3_256(data, size);
}

inline hash256 sha3_256(const hash256& input) noexcept
{
    return xhash_sha3_256_32(input.bytes);
}

inline hash512 sha3_512(const uint8_t* data, size_t size) noexcept
{
    return xhash_sha3_512(data, size);
}

inline hash512 sha3_512(const hash512& input) noexcept
{
    return xhash_sha3_512_64(input.bytes);
}

// Convenience aliases (same pattern as keccak.hpp)
static constexpr auto sha3_256_32 = xhash_sha3_256_32;
static constexpr auto sha3_512_64 = xhash_sha3_512_64;

}  // namespace xhash
