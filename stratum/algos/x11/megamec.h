#ifndef MEGAMEC_H
#define MEGAMEC_H

#ifdef __cplusplus
extern "C" {
#endif

#include <stdint.h>

void megamec_hash(const char* input, char* output, uint32_t len);

#ifdef __cplusplus
}
#endif

#endif
