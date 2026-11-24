// Meraki byte/string helpers, isolated from kawpow/helpers.hpp so both
// vendored Progpow variants can be linked into the same stratum binary.

#pragma once

#include "meraki/lib/meraki/meraki.hpp"

#include <cstdint>
#include <cstring>
#include <string>

template <typename Hash>
inline std::string meraki_to_hex(const Hash& h)
{
    static const auto hex_chars = "0123456789abcdef";
    std::string str;
    str.reserve(sizeof(h) * 2);
    for (auto b : h.bytes)
    {
        str.push_back(hex_chars[uint8_t(b) >> 4]);
        str.push_back(hex_chars[uint8_t(b) & 0xf]);
    }
    return str;
}

inline meraki::hash256 to_meraki_hash256(const std::string& hex)
{
    auto parse_digit = [](char d) -> int { return d <= '9' ? (d - '0') : (d - 'a' + 10); };

    meraki::hash256 hash = {};
    for (size_t i = 1; i < hex.size(); i += 2)
    {
        int h = parse_digit(hex[i - 1]);
        int l = parse_digit(hex[i]);
        hash.bytes[i / 2] = uint8_t((h << 4) | l);
    }
    return hash;
}

inline bool operator==(const meraki::hash256& a, const meraki::hash256& b) noexcept
{
    return std::memcmp(a.bytes, b.bytes, sizeof(a)) == 0;
}

inline bool operator!=(const meraki::hash256& a, const meraki::hash256& b) noexcept
{
    return !(a == b);
}
