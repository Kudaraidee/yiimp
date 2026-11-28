#ifndef COSA_H
#define COSA_H

#ifdef __cplusplus
extern "C" {
#endif

#include <stdint.h>

void cosa_hash(const char* input, char* output, uint32_t len);

#ifdef __cplusplus
}
#endif

#endif
