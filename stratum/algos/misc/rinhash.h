#ifndef RIN_H
#define RIN_H

#ifdef __cplusplus
extern "C" {
#endif

#include <stdint.h>

void rinhash_hash(const char* input, char* output, uint32_t len);

#ifdef __cplusplus
}
#endif

#endif // RIN_H
