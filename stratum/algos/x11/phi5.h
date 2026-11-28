#ifndef PHI5_H
#define PHI5_H

#ifdef __cplusplus
extern "C" {
#endif

#include <stdint.h>

void phi5_hash(const char* input, char* output, uint32_t len);

#ifdef __cplusplus
}
#endif

#endif
