#ifndef X11K_H
#define X11K_H

#ifdef __cplusplus
extern "C" {
#endif

#include <stdint.h>

void x11k_hash(const char* input, char* output, uint32_t len);

#ifdef __cplusplus
}
#endif

#endif