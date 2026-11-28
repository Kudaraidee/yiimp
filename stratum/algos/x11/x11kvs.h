#ifndef X11KVS_H
#define X11KVS_H

#ifdef __cplusplus
extern "C" {
#endif

#include <stdint.h>

void x11kvs_hash(const char* input, char* output, uint32_t len);

#ifdef __cplusplus
}
#endif

#endif
