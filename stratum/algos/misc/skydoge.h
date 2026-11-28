#ifndef SKYDOGE_H
#define SKYDOGE_H

#ifdef __cplusplus
extern "C" {
#endif

#include <stdint.h>

void skydoge_hash(const char* input, char* output, uint32_t len);

#ifdef __cplusplus
}
#endif

#endif