
// OpenSSL implementation for stratum's optional TLS listeners.
//
// This file intentionally does not include stratum.h. OpenSSL's sha.h defines
// SHA256_CTX / SHA256_Init / SHA256_Final, which collide with the copy in
// algos/sha256.h used by the rest of the stratum sources.
#include "tls.h"

#include <openssl/ssl.h>
#include <openssl/err.h>
#include <sys/socket.h>
#include <sys/time.h>
#include <poll.h>
#include <stdio.h>
#include <string.h>

static SSL_CTX *g_ctx = NULL;

static void tls_errors(char *buf, size_t len)
{
	unsigned long err;
	char msg[256];

	buf[0] = 0;
	while ((err = ERR_get_error()))
	{
		ERR_error_string_n(err, msg, sizeof(msg));
		if (buf[0]) strncat(buf, "; ", len - strlen(buf) - 1);
		strncat(buf, msg, len - strlen(buf) - 1);
	}
	if (!buf[0]) snprintf(buf, len, "unknown OpenSSL error");
}

bool stratum_tls_init(const char *cert, const char *key, char *errbuf, size_t errlen)
{
	errbuf[0] = 0;
	if (!cert || !*cert)
	{
		snprintf(errbuf, errlen, "no certificate configured");
		return false;
	}

	SSL_library_init();
	SSL_load_error_strings();
	OpenSSL_add_all_algorithms();

#if OPENSSL_VERSION_NUMBER >= 0x10100000L
	g_ctx = SSL_CTX_new(TLS_server_method());
#else
	g_ctx = SSL_CTX_new(SSLv23_server_method());
#endif
	if (!g_ctx)
	{
		tls_errors(errbuf, errlen);
		return false;
	}

	// Modern TLS only. SSL_OP_ALL keeps compatibility with a few miner stacks
	// that depend on OpenSSL negotiation workarounds.
	SSL_CTX_set_options(g_ctx, SSL_OP_ALL | SSL_OP_NO_SSLv3 |
		SSL_OP_NO_TLSv1 | SSL_OP_NO_TLSv1_1);

	if (SSL_CTX_use_certificate_chain_file(g_ctx, cert) != 1)
	{
		tls_errors(errbuf, errlen);
		SSL_CTX_free(g_ctx);
		g_ctx = NULL;
		return false;
	}

	// When only a combined fullchain+key PEM is configured, load the key from
	// the same file.
	const char *keyfile = (key && *key) ? key : cert;
	int rc = SSL_CTX_use_PrivateKey_file(g_ctx, keyfile, SSL_FILETYPE_PEM);
	if (rc == 1)
	{
		rc = SSL_CTX_check_private_key(g_ctx);
		if (rc != 1)
			snprintf(errbuf, errlen, "certificate and private key do not match");
	}

	if (rc != 1)
	{
		if (!errbuf[0]) tls_errors(errbuf, errlen);
		SSL_CTX_free(g_ctx);
		g_ctx = NULL;
		return false;
	}

	return true;
}

void stratum_tls_free()
{
	if (g_ctx)
	{
		SSL_CTX_free(g_ctx);
		g_ctx = NULL;
	}
}

bool stratum_tls_accept(int fd, SSL **out, char *errbuf, size_t errlen)
{
	errbuf[0] = 0;
	*out = NULL;

	if (!g_ctx)
	{
		snprintf(errbuf, errlen, "TLS is not initialized");
		return false;
	}

	SSL *ssl = SSL_new(g_ctx);
	if (!ssl)
	{
		tls_errors(errbuf, errlen);
		return false;
	}
	if (SSL_set_fd(ssl, fd) != 1)
	{
		tls_errors(errbuf, errlen);
		SSL_free(ssl);
		return false;
	}

	// The normal 600s receive timeout is far too long for a handshake: a
	// client that connects without sending a ClientHello would pin a worker
	// thread for ten minutes. Use a short deadline, then restore it.
	struct timeval original_timeout, handshake_timeout;
	socklen_t tlen = sizeof(original_timeout);
	getsockopt(fd, SOL_SOCKET, SO_RCVTIMEO, &original_timeout, &tlen);
	handshake_timeout.tv_sec = 10;
	handshake_timeout.tv_usec = 0;
	setsockopt(fd, SOL_SOCKET, SO_RCVTIMEO, &handshake_timeout, sizeof(handshake_timeout));

	int res = SSL_accept(ssl);
	for (int retries = 0; res <= 0 && retries < 10; retries++)
	{
		int err = SSL_get_error(ssl, res);
		if (err != SSL_ERROR_WANT_READ && err != SSL_ERROR_WANT_WRITE)
			break;

		struct pollfd pfd;
		pfd.fd = fd;
		pfd.events = (err == SSL_ERROR_WANT_READ) ? POLLIN : POLLOUT;
		pfd.revents = 0;
		if (poll(&pfd, 1, 1000) <= 0)
			continue;
		res = SSL_accept(ssl);
	}

	setsockopt(fd, SOL_SOCKET, SO_RCVTIMEO, &original_timeout, sizeof(original_timeout));

	if (res != 1)
	{
		if (!errbuf[0]) tls_errors(errbuf, errlen);
		SSL_free(ssl);
		return false;
	}

	*out = ssl;
	return true;
}

int stratum_tls_read(SSL *ssl, void *buf, int num)
{
	return SSL_read(ssl, buf, num);
}

int stratum_tls_write(SSL *ssl, const void *buf, int num)
{
	return SSL_write(ssl, buf, num);
}

void stratum_tls_close(SSL *ssl)
{
	if (!ssl) return;
	SSL_shutdown(ssl);
	SSL_free(ssl);
}
