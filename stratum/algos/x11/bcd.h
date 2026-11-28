#ifndef BCD_H
#define BCD_H

#ifdef __cplusplus
extern "C" {
#endif

#include <stdint.h>

void bcd_hash(const char* input, char* output, uint32_t len);

#ifdef __cplusplus
}
#endif

#endif