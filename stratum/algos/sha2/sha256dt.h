#ifndef SHA256DT_H
#define SHA256DT_H

#ifdef __cplusplus
extern "C" {
#endif

#include <stdint.h>

void sha256dt_hash(const char* input, char* output, uint32_t len);

#ifdef __cplusplus
}
#endif

#endif