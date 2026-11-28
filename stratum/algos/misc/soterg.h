#ifndef SOTERG_H
#define SOTERG_H

#ifdef __cplusplus
extern "C" {
#endif

#include <stdint.h>

void soterg_hash(const char* input, char* output, uint32_t len);
void sha256d(unsigned char *hash, const unsigned char *data, int len);

#ifdef __cplusplus
}
#endif

#endif
