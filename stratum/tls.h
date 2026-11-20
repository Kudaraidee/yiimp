
// Minimal, self-contained interface to the OpenSSL implementation in tls.cpp.
// This header deliberately does NOT include <openssl/ssl.h>: that header pulls
// in openssl/sha.h whose SHA256_CTX / SHA256_Init clash with the project's own
// algos/sha256.h when both land in the same translation unit.
#ifndef YAAMP_TLS_H
#define YAAMP_TLS_H

#include <stddef.h>

typedef struct ssl_st SSL;

// Loads the certificate/private key once. Returns true on success; on failure
// errbuf receives a human-readable reason for the stratum log.
bool stratum_tls_init(const char *cert, const char *key, char *errbuf, size_t errlen);
void stratum_tls_free();

// Server-side handshake on an already accepted socket. The returned SSL is
// owned by the caller and must be released with stratum_tls_close().
bool stratum_tls_accept(int fd, SSL **out, char *errbuf, size_t errlen);

int  stratum_tls_read(SSL *ssl, void *buf, int num);
int  stratum_tls_write(SSL *ssl, const void *buf, int num);
void stratum_tls_close(SSL *ssl);

#endif
