#ifndef STRSAFE_H
#define STRSAFE_H

// Bounded writes into fixed-size char buffers.
// A write that does not fit is refused: the destination is cleared, the
// overflow is logged and counted in str_overflows. Whatever is built from
// that buffer must then be discarded, never used truncated.

#include <stddef.h>

extern thread_local int str_overflows;

// no size limit, only for legacy callers that pass a bare pointer
#define STR_UNBOUNDED	((size_t)-1 >> 1)

bool str_copy(char *dst, size_t cap, const char *src, const char *what);
bool str_append(char *dst, size_t cap, const char *src, const char *what);
bool str_format(char *dst, size_t cap, bool append, const char *what, const char *fmt, ...)
	__attribute__((format(printf, 5, 6)));
bool str_fail(const char *what, size_t need, size_t cap);
// src if it fits into cap bytes (or is NULL), else "" after logging
const char *str_fits(const char *src, size_t cap, const char *what);

// array-only forms: passing a pointer does not compile
template <size_t N> static inline char *str_array(char (&a)[N]) { return a; }

#define STRSET(dst, src)	str_copy(str_array(dst), sizeof(dst), (src), #dst)
#define STRADD(dst, src)	str_append(str_array(dst), sizeof(dst), (src), #dst)
#define STRFMT(dst, ...)	str_format(str_array(dst), sizeof(dst), false, #dst, __VA_ARGS__)
#define STRADDF(dst, ...)	str_format(str_array(dst), sizeof(dst), true, #dst, __VA_ARGS__)

#endif
