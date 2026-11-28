#ifndef MIKE_H
#define MIKE_H
#ifdef __cplusplus
extern "C" {
#endif

#include <stdint.h>

void mike_hash(const char* input, char* output, uint32_t len);

#ifdef __cplusplus
}
#endif
#endif
