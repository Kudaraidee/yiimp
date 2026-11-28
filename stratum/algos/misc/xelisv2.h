#ifndef XEL2_H
#define XEL2_H

#ifdef __cplusplus
extern "C" {
#endif

#include <stdint.h>

void xelisv2_hash(const char* input, char* output, uint32_t len);

#ifdef __cplusplus
}
#endif

#endif