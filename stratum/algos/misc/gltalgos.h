#ifndef GLTALGOS_H
#define GLTALGOS_H

#ifdef __cplusplus
extern "C" {
#endif

#include <stdint.h>

void pawelhash_hash(const char* input, char* output, uint32_t len);
void astralhash_hash(const char* input, char* output, uint32_t len);
void jeonghash_hash(const char* input, char* output, uint32_t len);
void padihash_hash(const char* input, char* output, uint32_t len);
void globalhash_hash(const char* input, char* output, uint32_t len);

#ifdef __cplusplus
}
#endif

#endif