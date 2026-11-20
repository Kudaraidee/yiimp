
#include "stratum.h"
#include <poll.h>

// OpenSSL lives in tls.cpp behind an opaque interface. Keeping <openssl/ssl.h>
// out of this file avoids the SHA256_CTX name clash with algos/sha256.h.
bool socket_tls_setup(const char *cert, const char *key)
{
	char err[512];
	if (!stratum_tls_init(cert, key, err, sizeof(err)))
	{
		stratumlogdate("TLS: %s\n", err);
		return false;
	}
	return true;
}

void socket_tls_shutdown()
{
	stratum_tls_free();
}

bool socket_looks_like_tls(YAAMP_SOCKET *s)
{
	// A TLS ClientHello starts with 0x16 (handshake); 0x80 is the obsolete
	// SSLv2 format still sent by a handful of old miners. Stratum JSON always
	// starts with '{' (0x7B). Wait briefly for the first bytes so an ordinary
	// connect-without-send does not block a worker thread in the sniffer.
	struct pollfd pfd;
	pfd.fd = s->sock;
	pfd.events = POLLIN;
	pfd.revents = 0;

	int pr = poll(&pfd, 1, 5000);
	if (pr <= 0)
		return false;

	unsigned char first = 0;
	int len;
	do {
		len = recv(s->sock, &first, 1, MSG_PEEK);
	} while (len == -1 && errno == EINTR);

	if (len != 1)
		return false;

	return first == 0x16 || first == 0x80;
}

bool socket_tls_enable(YAAMP_SOCKET *s)
{
	if (!s || s->ssl)
		return false;

	SSL *ssl = NULL;
	char err[512];
	if (!stratum_tls_accept(s->sock, &ssl, err, sizeof(err)))
	{
		stratumlog("TLS handshake failed for %s: %s\n", s->ip, err);
		return false;
	}

	s->ssl = ssl;
	return true;
}

bool socket_connected(YAAMP_SOCKET *s)
{
	return s->sock > 0;
}

void socket_real_ip(YAAMP_SOCKET *s)
{
	// get real ip if we are using haproxy or similar that use PROXY protocol
	// https://www.haproxy.org/download/1.8/doc/proxy-protocol.txt
	int size, ret;
	const char v2sig[] = "\x0D\x0A\x0D\x0A\x00\x0D\x0A\x51\x55\x49\x54\x0A";

	do {
		ret = recv(s->sock, &hdr, sizeof(hdr), MSG_PEEK);
	} while (ret == -1 && errno == EINTR);

	if (ret >= (16 + ntohs(hdr.v2.len)) &&
		memcmp(&hdr.v2, v2sig, 12) == 0 &&
		((hdr.v2.ver_cmd & 0xF0) == 0x20) &&
		hdr.v2.fam == 0x11) {
		// we received a proxy v2 header
		inet_ntop(AF_INET, &hdr.v2.addr.ip4.src_addr, s->ip, 64);
		s->port = ntohs(hdr.v2.addr.ip4.src_port);

		// we need to consume the appropriate amount of data from the socket
		// read the buffer without PEEK'ing so that we begin at the real data later in socket_nextjson
		size = 16 + ntohs(hdr.v2.len);
		do {
			ret = recv(s->sock, &hdr, size, 0);
		} while (ret == -1 && errno == EINTR);
		return;
	}
	else {
		// not received any proxy header
		struct sockaddr_in name;
		socklen_t len = sizeof(name);
		memset(&name, 0, len);

		int res = getpeername(s->sock, (struct sockaddr *)&name, &len);
		inet_ntop(AF_INET, &name.sin_addr, s->ip, 64);

		res = getsockname(s->sock, (struct sockaddr *)&name, &len);
		s->port = ntohs(name.sin_port);
		return;
	}
}

YAAMP_SOCKET *socket_initialize(int sock)
{
	struct timeval timeout;
	timeout.tv_sec = g_socket_recv_timeout;
	timeout.tv_usec = 0;
	YAAMP_SOCKET *s = new YAAMP_SOCKET;
	memset(s, 0, sizeof(YAAMP_SOCKET));

	s->buflen = 0;
	s->sock = sock;

	setsockopt(s->sock, SOL_SOCKET, SO_RCVTIMEO, &timeout, sizeof(timeout));

//	yaamp_create_mutex(&s->mutex);
//	pthread_mutex_lock(&s->mutex);
	if (!g_handle_haproxy_ips) {
		int res = 0;
		struct sockaddr_in name;
		socklen_t len = sizeof(name);
		memset(&name, 0, len);

		res = getpeername(s->sock, (struct sockaddr *)&name, &len);
		inet_ntop(AF_INET, &name.sin_addr, s->ip, 64);

		res = getsockname(s->sock, (struct sockaddr *)&name, &len);
		s->port = ntohs(name.sin_port);
	} else {
		socket_real_ip(s);
	}

	return s;
}

void socket_close(YAAMP_SOCKET *s)
{
	if (g_debuglog_socket) {
		debuglog("socket_close\n");
	}

	if(!s) return;

	if(s->ssl)
	{
		stratum_tls_close(s->ssl);
		s->ssl = NULL;
	}

	if(s->sock) close(s->sock);

//	pthread_mutex_unlock(&s->mutex);
//	pthread_mutex_destroy(&s->mutex);

	s->sock = 0;
	delete s;
}

json_value *socket_nextjson(YAAMP_SOCKET *s, YAAMP_CLIENT *client)
{
	while(!strchr(s->buffer, '}') && s->buflen<YAAMP_SOCKET_BUFSIZE-1)
	{
	//	pthread_mutex_unlock(&s->mutex);

		int want = YAAMP_SOCKET_BUFSIZE-s->buflen-1;
		int len = s->ssl ? stratum_tls_read(s->ssl, s->buffer+s->buflen, want)
		                 : recv(s->sock, s->buffer+s->buflen, want, 0);
		if(len <= 0) return NULL;

		s->last_read = time(NULL);
		s->total_read += len;

		s->buflen += len;
		s->buffer[s->buflen] = 0;

		if(client && client->logtraffic)
			stratumlog("recv: %d\n", s->buflen);

	//	pthread_mutex_lock(&s->mutex);
	}

	char *b = strchr(s->buffer, '{');
	if(!b)
	{
		if(client)
			clientlog(client, "bad json");

		debuglog("%s\n", s->buffer);
		return NULL;
	}

	char *p = strchr(b, '}');
	if (p) {
		// buffer can contain multiple queries
		if(!strchr(p, '{')) p = strrchr(b, '}');
		else { p = strchr(p, '{'); p--; };
	}

	if(!p)
	{
		if(client)
			clientlog(client, "bad json end");

		debuglog("%s\n", b);
		return NULL;
	}

	p++;

	char saved = *p;
	*p = 0;

	if(client && client->logtraffic)
		stratumlog("%s, %s, %s, %s, recv: %s\n", client->sock->ip, client->username, client->password, g_current_algo->name, s->buffer);

	int bytes = strlen(b);

	json_value *json = json_parse(b, bytes);
	if(!json)
	{
		if(client)
			clientlog(client, "bad json parse");

		debuglog("%s\n", b);
		return NULL;
	}

	*p = saved;
	while(*p && *p != '{')
		p++;

	if(*p == '{')
	{
		memmove(s->buffer, p, s->buflen - (p - s->buffer));

		s->buflen = s->buflen - (p - s->buffer);
		s->buffer[s->buflen] = 0;

//		if(client && client->logtraffic)
//			stratumlog("still: %s\n", s->buffer);
	}
	else
	{
		memset(s->buffer, 0, YAAMP_SOCKET_BUFSIZE);
		s->buflen = 0;
	}

	return json;
}

int socket_send_raw(YAAMP_SOCKET *s, const char *buffer, int size)
{
	if (g_debuglog_socket) {
		debuglog("socket send: %s", buffer);
	}

	if (!s->ssl)
		return send(s->sock, buffer, size, MSG_NOSIGNAL);

	// SSL_write can perform a partial write; push the whole JSON line out so
	// miners never see a truncated notify/result message.
	int sent = 0;
	while (sent < size)
	{
		int n = stratum_tls_write(s->ssl, buffer+sent, size-sent);
		if (n <= 0)
			return sent ? sent : n;
		sent += n;
	}
	return sent;
}

int socket_send(YAAMP_SOCKET *s, const char *format, ...)
{
	char buffer[YAAMP_SMALLBUFSIZE];
	va_list args;

	va_start(args, format);
	vsprintf(buffer, format, args);
	va_end(args);

	if(!s) {
		errno = EINVAL;
		return -1;
	}

//	json_value *json = json_parse(buffer, strlen(buffer));
//	if(!json)
//		debuglog("sending bad json message: %s\n", buffer);
//	else
//		json_value_free(json);

//	pthread_mutex_lock(&s->mutex);
	int res = socket_send_raw(s, buffer, strlen(buffer));
//	pthread_mutex_unlock(&s->mutex);
	return res;
}




