#ifndef GR_H
#define GR_H
#ifdef __cplusplus
extern "C" {
#endif

#include <stdint.h>

void gr_hash(const char* input, char* output, uint32_t len);

#ifdef __cplusplus
}
#endif
#endif
