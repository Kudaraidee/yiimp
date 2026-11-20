
// The OpenSSL implementation lives in tls.cpp so that <openssl/ssl.h> (which
// pulls in openssl/sha.h and collides with algos/sha256.h) is never included
// in a translation unit that also includes stratum.h.
#include "tls.h"

#define YAAMP_SOCKET_BUFSIZE	(2*1024)

// TLS modes passed from the accept loop to client_thread(). YAAMP_TLS_NONE
// keeps the original plaintext-only behaviour. YAAMP_TLS_SNIFF is used on the
// shared main port (peek the first byte and pick JSON vs TLS). YAAMP_TLS_FORCE
// is used by the optional dedicated TLS listener.
#define YAAMP_TLS_NONE		0
#define YAAMP_TLS_SNIFF		1
#define YAAMP_TLS_FORCE		2

struct YAAMP_SOCKET
{
	char ip[64];
	int port;

//	pthread_mutex_t mutex;
	int sock;

	// NULL for a plaintext connection; set after a successful SSL_accept().
	SSL *ssl;

	int buflen;
	char buffer[YAAMP_SOCKET_BUFSIZE];

	int last_read;
	int total_read;
};

// One of these is allocated by the accept loop and freed by client_thread().
struct YAAMP_CLIENT_START
{
	int sock;
	int tls_mode;
};

bool socket_connected(YAAMP_SOCKET *s);

void socket_real_ip(YAAMP_SOCKET *s);

// Loads the server certificate once at startup. Returns false (and leaves
// g_tls_ctx NULL) when TLS is not configured or the files cannot be read;
// callers then simply run plaintext listeners.
bool socket_tls_setup(const char *cert, const char *key);
void socket_tls_shutdown();

// Peeks at the first application byte after any PROXY header was consumed.
bool socket_looks_like_tls(YAAMP_SOCKET *s);

// Performs the server-side TLS handshake on an accepted socket.
bool socket_tls_enable(YAAMP_SOCKET *s);

YAAMP_SOCKET *socket_initialize(int sock);
void socket_close(YAAMP_SOCKET *s);

json_value *socket_nextjson(YAAMP_SOCKET *s, YAAMP_CLIENT *client=NULL);
int socket_send(YAAMP_SOCKET *s, const char *format, ...);

int socket_send_raw(YAAMP_SOCKET *s, const char *buffer, int size);

static union {
	struct {
		char line[108];
	} v1;
	struct {
		uint8_t sig[12];
		uint8_t ver_cmd;
		uint8_t fam;
		uint16_t len;
		union {
			struct {  /* for TCP/UDP over IPv4, len = 12 */
				uint32_t src_addr;
				uint32_t dst_addr;
				uint16_t src_port;
				uint16_t dst_port;
			} ip4;
			struct {  /* for TCP/UDP over IPv6, len = 36 */
				uint8_t  src_addr[16];
				uint8_t  dst_addr[16];
				uint16_t src_port;
				uint16_t dst_port;
			} ip6;
			struct {  /* for AF_UNIX sockets, len = 216 */
				uint8_t src_addr[108];
				uint8_t dst_addr[108];
			} unx;
		} addr;
	} v2;
} hdr;
