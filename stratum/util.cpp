
#include "stratum.h"
#include <math.h>
#include <limits.h>

////////////////////////////////////////////////////////////////////////////////

void cbin2hex(char *out, const char *in, size_t len)
{
	if (out) {
		unsigned int i;
		for (i = 0; i < len; i++)
			sprintf(out + (i * 2), "%02x", (uint8_t)in[i]);
	}
}

char *bin2hex(const uchar *in, size_t len)
{
	char *s = (char*)malloc((len * 2) + 1);
	if (!s)
		return NULL;

	cbin2hex(s, (const char *) in, len);

	return s;
}

bool json_get_bool(json_value *json, const char *name)
{
	if (!json) return false;

	for(int i=0; i<json->u.object.length; i++)
	{
		if(!strcmp(json->u.object.values[i].name, name))
			return json->u.object.values[i].value->u.boolean;
	}

	return false;
}

json_int_t json_get_int(json_value *json, const char *name)
{
	if (!json) return 0;

	for(int i=0; i<json->u.object.length; i++)
	{
		if(!strcmp(json->u.object.values[i].name, name))
			return json->u.object.values[i].value->u.integer;
	}

	return 0;
}

double json_get_double(json_value *json, const char *name)
{
	if (!json) return 0;

	for(int i=0; i<json->u.object.length; i++)
	{
		if(!strcmp(json->u.object.values[i].name, name))
			return json->u.object.values[i].value->u.dbl;
	}

	return 0;
}

const char *json_get_string(json_value *json, const char *name)
{
	if (!json) return NULL;

	for(int i=0; i<json->u.object.length; i++)
	{
		if(!strcmp(json->u.object.values[i].name, name))
			return json->u.object.values[i].value->u.string.ptr;
	}

	return NULL;
}

json_value *json_get_array(json_value *json, const char *name)
{
	if (!json) return NULL;

	for(int i=0; i<json->u.object.length; i++)
	{
//		if(json->u.object.values[i].value->type == json_array && !strcmp(json->u.object.values[i].name, name))
		if(!strcmp(json->u.object.values[i].name, name))
			return json->u.object.values[i].value;
	}

	return NULL;
}

//json_value *json_get_array_from_array(json_value *json, const char *name)
//{
//	for(int i=0; i<json->u.array.length; i++)
//	{
//		if(!strcmp(json->u.array.values[i].name, name))
//			return json->u.array.values[i].value;
//	}
//
//	return NULL;
//}

json_value *json_get_object(json_value *json, const char *name)
{
	if (!json) return NULL;

	for(int i=0; i<json->u.object.length; i++)
	{
		if(!strcmp(json->u.object.values[i].name, name))
			return json->u.object.values[i].value;
	}

	return NULL;
}

///////////////////////////////////////////////////////////////////////////////////////////////

FILE *g_debuglog = NULL;
FILE *g_stratumlog = NULL;
FILE *g_clientlog = NULL;
FILE *g_rejectlog = NULL;

void initlog(const char *algo)
{
	char debugfile[1024];

	if (algo != NULL) {
		sprintf(debugfile, "%sstratum-%s.log", g_log_directory ,algo);
		g_debuglog = fopen(debugfile, "a");
	}

	sprintf(debugfile, "%sstratum.log", g_log_directory);
	g_stratumlog = fopen(debugfile, "a");
	sprintf(debugfile, "%sstratum-client.log", g_log_directory);
	g_clientlog = fopen(debugfile, "a");
	sprintf(debugfile, "%sstratum-reject.log", g_log_directory);
	g_rejectlog = fopen(debugfile, "a");
}

void closelogs()
{
	if (g_debuglog) {
		fflush(g_debuglog); fclose(g_debuglog);
	}
	if (g_stratumlog) {
		fflush(g_stratumlog); fclose(g_stratumlog);
	}
	if (g_clientlog) {
		fflush(g_clientlog); fclose(g_clientlog);
	}
	if (g_rejectlog) {
		fflush(g_rejectlog); fclose(g_rejectlog);
	}
}

void clientlog(YAAMP_CLIENT *client, const char *format, ...)
{
	char buffer[YAAMP_SMALLBUFSIZE];
	va_list args;

	va_start(args, format);
	vsprintf(buffer, format, args);
	va_end(args);

	time_t rawtime;
	struct tm * timeinfo;
	char buffer2[80];

	time(&rawtime);
	timeinfo = localtime(&rawtime);

	strftime(buffer2, 80, "%Y-%m-%d %H:%M:%S", timeinfo);

	char buffer3[YAAMP_SMALLBUFSIZE];
	sprintf(buffer3, "%s [%s] %s, %s, %s\n", buffer2, client->sock->ip, client->username, g_current_algo->name, buffer);

	printf("%s", buffer3);
	if(g_debuglog)
	{
		fprintf(g_debuglog, "%s", buffer3);
		fflush(g_debuglog);
	}

	if(g_clientlog)
	{
		fprintf(g_clientlog, "%s", buffer3);
		if (fflush(g_clientlog) == EOF) {
			// reopen if wiped
			fclose(g_clientlog);
			g_clientlog = fopen("client.log", "a");
		}
	}
}

void debuglog(const char *format, ...)
{
	char buffer[YAAMP_SMALLBUFSIZE];
	va_list args;

	va_start(args, format);
	vsprintf(buffer, format, args);
	va_end(args);

	time_t rawtime;
	struct tm * timeinfo;
	char buffer2[80];

	time(&rawtime);
	timeinfo = localtime(&rawtime);

	strftime(buffer2, 80, "%H:%M:%S", timeinfo);
	printf("%s: %s", buffer2, buffer);

	if(g_debuglog)
	{
		fprintf(g_debuglog, "%s: %s", buffer2, buffer);
		fflush(g_debuglog);
	}
}

void debuglog_hex(void *data, int len)
{
	uint8_t* const bin = (uint8_t*) data;
	char *hex = (char*) calloc(1, len*2 + 2);
	if (!hex) return;
	for(int i=0; i < len; i++)
		sprintf(hex+strlen(hex), "%02x", bin[i]);
	strcpy(hex+strlen(hex), "\n");
	debuglog(hex);
	free(hex);
}

void stratumlog(const char *format, ...)
{
	char buffer[YAAMP_SMALLBUFSIZE];
	va_list args;

	va_start(args, format);
	vsprintf(buffer, format, args);
	va_end(args);

	time_t rawtime;
	struct tm * timeinfo;
	char buffer2[80];

	time(&rawtime);
	timeinfo = localtime(&rawtime);

	strftime(buffer2, 80, "%H:%M:%S", timeinfo);
	printf("%s: %s", buffer2, buffer);

	if(g_debuglog)
	{
		fprintf(g_debuglog, "%s: %s", buffer2, buffer);
		fflush(g_debuglog);
	}

	if(g_stratumlog)
	{
		fprintf(g_stratumlog, "%s: %s", buffer2, buffer);
		if (fflush(g_stratumlog) == EOF) {
			fclose(g_stratumlog);
			g_stratumlog = fopen("stratum.log", "a");
		}
	}
}

void stratumlogdate(const char *format, ...)
{
	char buffer[YAAMP_SMALLBUFSIZE];
	char date[16];
	va_list args;
	time_t rawtime;
	struct tm * timeinfo;

	time(&rawtime);
	timeinfo = localtime(&rawtime);
	strftime(date, 16, "%Y-%m-%d", timeinfo);

	va_start(args, format);
	vsprintf(buffer, format, args);
	va_end(args);

	stratumlog("%s %s", date, buffer);
}

void rejectlog(const char *format, ...)
{
	char buffer[YAAMP_SMALLBUFSIZE];
	va_list args;

	va_start(args, format);
	vsnprintf(buffer, YAAMP_SMALLBUFSIZE-1, format, args);
	va_end(args);

	time_t rawtime;
	struct tm * timeinfo;
	char buffer2[80];

	time(&rawtime);
	timeinfo = localtime(&rawtime);

	strftime(buffer2, 80, "%Y-%m-%d %H:%M:%S", timeinfo);
	printf("%s: %s", buffer2, buffer);

	if(g_rejectlog)
	{
		fprintf(g_rejectlog, "%s: %s", buffer2, buffer);
		if (fflush(g_rejectlog) == EOF) {
			fclose(g_rejectlog);
			g_rejectlog = fopen("reject.log", "a");
		}
	}
}


bool yaamp_error(char const *message)
{
	debuglog("ERROR: %d %s\n", errno, message);
	closelogs();
	exit(1);
}

void yaamp_create_mutex(pthread_mutex_t *mutex)
{
	pthread_mutexattr_t attr;
	pthread_mutexattr_init(&attr);

	pthread_mutexattr_settype(&attr, PTHREAD_MUTEX_RECURSIVE);
	pthread_mutex_init(mutex, &attr);

	pthread_mutexattr_destroy(&attr);
}

const char *header_value(const char *data, const char *search, char *value)
{
	value[0] = 0;

	char *p = (char *)strstr(data, search);
	if(!p) return value;

	p += strlen(search);
	while(*p == ' ' || *p == ':') p++;

	char *p2 = (char *)strstr(p, "\r\n");
	if(!p2)
	{
		strncpy(value, p, 1024);
		return value;
	}

	strncpy(value, p, std::min<size_t>(1024, p2 - p));
	value[std::min<size_t>(1023, p2 - p)] = 0;

	return value;
}

////////////////////////////////////////////////////////////////////////////////////////////

const unsigned char g_base64_tab[] = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/";

void base64_encode(char *base64, const char *normal)
{
	int cb = strlen((char *)normal);
	while(cb >= 3)
	{
		unsigned char b0 = ((normal[0] >> 2) & 0x3F);
		unsigned char b1 = ((normal[0] & 0x03) << 4) | ((normal[1] >> 4) & 0x0F);
		unsigned char b2 = ((normal[1] & 0x0F) << 2) | ((normal[2] >> 6) & 0x03);
		unsigned char b3 = ((normal[2] & 0x3F));

		*base64++ = g_base64_tab[b0];
		*base64++ = g_base64_tab[b1];
		*base64++ = g_base64_tab[b2];
		*base64++ = g_base64_tab[b3];

		normal += 3;
		cb -= 3;
	}

	if(cb == 1)
	{
		unsigned char b0 = ((normal[0] >> 2) & 0x3F);
		unsigned char b1 = ((normal[0] & 0x03) << 4) | 0;

		*base64++ = g_base64_tab[b0];
		*base64++ = g_base64_tab[b1];

		*base64++ = '=';
		*base64++ = '=';
	}
	else if(cb == 2)
	{
		unsigned char b0 = ((normal[0] >> 2) & 0x3F);
		unsigned char b1 = ((normal[0] & 0x03) << 4) | ((normal[1] >> 4) & 0x0F);
		unsigned char b2 = ((normal[1] & 0x0F) << 2) | 0;

		*base64++ = g_base64_tab[b0];
		*base64++ = g_base64_tab[b1];
		*base64++ = g_base64_tab[b2];
		*base64++ = '=';
	}

	*base64 = 0;
}

void base64_decode(char *normal, const char *base64)
{
	int i;

	unsigned char decoding_tab[256];
	memset(decoding_tab, 255, 256);

	for(i = 0; i < 64; i++)
		decoding_tab[g_base64_tab[i]] = i;

	unsigned long current = 0;
	int bit_filled = 0;

	for(i = 0; base64[i]; i++)
	{
		if(base64[i] == 0x0A || base64[i] == 0x0D || base64[i] == 0x20 || base64[i] == 0x09)
			continue;

		if(base64[i] == '=')
			break;

		unsigned char digit = decoding_tab[base64[i]];

		current <<= 6;
		current |= digit;
		bit_filled += 6;

		if(bit_filled >= 8)
		{
			unsigned long b = (current >> (bit_filled - 8));

			*normal++ = (unsigned char)(b & 0xFF);
			bit_filled -= 8;
		}
	}

	*normal = 0;
}

///////////////////////////////////////////////////////////////////////////////////////////////

void hexlify(char *hex, const unsigned char *bin, int len)
{
	hex[0] = 0;
	for(int i=0; i < len; i++)
		sprintf(hex+strlen(hex), "%02x", bin[i]);
}

bool ishexa(char *hex, int len)
{
	for(int i=0; i<len; i++) {
		if (!isxdigit(hex[i])) return false;
	}
	return true;
}

unsigned char binvalue(const char v)
{
	if(v >= '0' && v <= '9')
		return v-'0';

	if(v >= 'a' && v <= 'f')
		return v-'a'+10;

	if(v >= 'A' && v <= 'F')
		return v-'A'+10;

	return 0;
}

void binlify(unsigned char *bin, const char *hex)
{
	int len = strlen(hex);
	for(int i=0; i<len/2; i++)
		bin[i] = binvalue(hex[i*2])<<4 | binvalue(hex[i*2+1]);
}

void strprecatchar(char *buffer, char c)
{
	char tmp[64];
	sprintf(tmp, "%02x%s", c, buffer);
	strcpy(buffer, tmp);
}

////////////////////////////////////////////////////////////////////////////////

void ser_number(int n, char *a)
{
	unsigned char s[32];
	memset(s, 0, 32);
	memset(a, 0, 32);

	s[0] = 1;
	while(n > 127)
	{
		s[s[0]] = n % 256;
		n /= 256;
		s[0]++;
	}

	s[s[0]] = n;
	a[0] = 0;

	for(int i=0; i<=s[0]; i++)
	{
		char tmp[32];
		sprintf(tmp, "%02x", s[i]);
		strcat(a, tmp);
	}

//	printf("ser_number %d, %s\n", n, a);
}

void ser_compactsize(uint64_t nSize, char *a)
{
	if (nSize < 253)
	{
		sprintf(a, "%02lx", nSize);
	}
	else if (nSize <= (unsigned short)-1)
	{
		sprintf(a, "%02x%04lx", 253, nSize);
	}
	else if (nSize <= (unsigned int)-1)
	{
		sprintf(a, "%02x%08lx", 254, nSize);
	}
	else
	{
		sprintf(a, "%02x%016lx", 255, nSize);
	}
}

void ser_string_be(const char *input, char *output, int len)
{
	for(int i=0; i<len; i++)
		for(int j=0; j<8; j+=2)
			memcpy(output + i*8 + (6-j), input + i*8 + j, 2);
}

void ser_string_be2(const char *input, char *output, int len)
{
	for(int i=0; i<len; i++)
		memcpy(output + i*8, input + (len-i-1)*8, 8);
}

void string_be(const char *input, char *output)
{
	int len = strlen(input)/2;

	for(int i=0; i<len; i++)
		memcpy(output + (len-i-1)*2, input + i*2, 2);
}

void string_be1(char *s)
{
	char s2[1024];
	strcpy(s2, s);

	int len = strlen(s2)/2;

	for(int i=0; i<len; i++)
		memcpy(s + (len-i-1)*2, s2 + i*2, 2);
}

uint64_t diff_to_target(double difficulty)
{
	if(!difficulty) return 0;

	uint64_t t = 0x0000ffff00000000*g_current_algo->diff_multiplier/difficulty;
	return t;
}

void diff_to_target(uint32_t *target, double diff)
{
        uint64_t m;
        int k;

        for (k = 6; k > 0 && diff > 1.0; k--)
                diff /= 4294967296.0;
        m = (uint64_t)(4294901760.0 / diff);
        if (m == 0 && k == 6)
                memset(target, 0xff, 32);
        else {
                memset(target, 0, 32);
                target[k] = (uint32_t)m;
                target[k + 1] = (uint32_t)(m >> 32);
        }
}

void diff_to_target(uint256& target, double diff)
{
        diff_to_target((uint32_t*)&target, diff);
}

uint64_t diff_to_target_coin(double difficulty, int powlimit_bits) {

	uint64_t powlimit = (0xffffffffffffffff >> (powlimit_bits));

	uint64_t target = (uint64_t) (powlimit / difficulty);

	return target;
}

double target_to_diff(uint64_t target)
{
	if(!target) return 0;

	double d = (double)0x0000ffff00000000/target;
	return d;
}

double target_to_diff(uint256& target)
{
	if(target.IsNull()) return 0;

	arith_uint256 base_hash;
	base_hash.SetHex("00000000ffff0000000000000000000000000000000000000000000000000000");

	return ( base_hash.getdouble() / UintToArith256(target).getdouble() );
}

void diff_to_target_equi(uint32_t* target, double diff) {
	uint64_t m;
	int k;

//	if (!diff) return;
//	diff = g_current_algo->diff_multiplier/diff;

	for (k = 6; k > 0 && diff > 1.0; k--)
		diff /= 4294967296.0;
	m = (uint64_t)(4294901760.0 / diff);
	if (m == 0 && k == 6)
		memset(target, 0xff, 32);
	else {
		memset(target, 0, 32);
		target[k + 1] = (uint32_t)(m >> 8);
		target[k + 2] = (uint32_t)(m >> 40);
		//memset(target, 0xff, 6*sizeof(uint32_t));
		for (k = 0; k < 28 && ((uint8_t*)target)[k] == 0; k++)
			((uint8_t*)target)[k] = 0x00;
	}
}

double target_to_diff_coin(uint64_t target, int powlimit_bits)
{
	if(!target) return 0;

	uint64_t powlimit = (0xffffffffffffffff >> (powlimit_bits));

	double d = (double) powlimit / (double) target;
	return d;
}

// shiftcount: 19 equihash , 25 bitcoin-clones
uint64_t decode_compact(const char *input, int shiftdiff)
{
	uint64_t c = htoi64(input);

	int nShift = (c >> 24) & 0xff;

	nShift -= shiftdiff;
	uint64_t v = (c & 0xFFFFFF) << (8 * nShift);

	return v;
}

uint64_t sharetotarg(double diff)
{
        int i, shift = 29;
        unsigned char targ[32];
        for (i=0; i<32; i++)
            targ[i]=0;
        double ftarg = (double)0x0000ffff / diff;
        while (ftarg < (double)0x00008000) {
            shift--;
            ftarg *= 256.0;
        }
        while (ftarg >= (double)0x00800000) {
            shift++;
            ftarg /= 256.0;
        }
        uint32_t nBits = (int)ftarg + (shift << 24);
        shift = (nBits >> 24) & 0x00ff;
        nBits &= 0x00FFFFFF;
        targ[shift - 1] = nBits >> 16;
        targ[shift - 2] = nBits >> 8;
        targ[shift - 3] = nBits;
        uint64_t starget = * (uint64_t *) &targ[24];
        return (starget);
}

//def uint256_from_compact(c):
//    c = int(c)
//    nbytes = (c >> 24) & 0xFF
//    v = (c & 0xFFFFFFL) << (8 * (nbytes - 3))
//    return v

uint64_t get_hash_difficulty(unsigned char *input)
{
	unsigned char *p = (unsigned char *)input;

	uint64_t v =
		(uint64_t)p[29] << 56 |
		(uint64_t)p[28] << 48 |
		(uint64_t)p[27] << 40 |
		(uint64_t)p[26] << 32 |
		(uint64_t)p[25] << 24 |
		(uint64_t)p[24] << 16 |
		(uint64_t)p[23] << 8 |
		(uint64_t)p[22] << 0;

//	char toto[1024];
//	hexlify(toto, input, 32);
//	debuglog("hash diff %s %016llx\n", toto, v);
	return v;
}

uint64_t get_equihash_difficulty(unsigned char *input)
{
	unsigned char *p = (unsigned char *)input;

	uint64_t v =
		(uint64_t)p[31] << 56 |
		(uint64_t)p[30] << 48 |
		(uint64_t)p[29] << 40 |
		(uint64_t)p[28] << 32 |
		(uint64_t)p[27] << 24 |
		(uint64_t)p[26] << 16 |
		(uint64_t)p[25] << 8 |
		(uint64_t)p[24] << 0;

//	char toto[1024];
//	hexlify(toto, input, 32);
//	debuglog("hash diff %s %016llx\n", toto, v);
	return v;
}

double target_to_diff_equi(uint32_t* target)
{
	uchar* tgt = (uchar*) target;
	uint64_t m =
		(uint64_t)tgt[30] << 24 |
		(uint64_t)tgt[29] << 16 |
		(uint64_t)tgt[28] << 8  |
		(uint64_t)tgt[27] << 0;

	if (!m)
		return 0.;
	else
		return (double)0xffff0000UL/m;
}

/* compute nbits to get the network diff */
double equi_network_diff(uint32_t *work_data)
{
	//KMD bits: "1e 015971",
	//KMD target: "00 00 015971000000000000000000000000000000000000000000000000000000",
	//KMD bits: "1d 686aaf",
	//KMD target: "00 0000 686aaf0000000000000000000000000000000000000000000000000000",
	uint32_t nbits = work_data[26];
	uint32_t bits = (nbits & 0xffffff);
	int16_t shift = (swab32(nbits) & 0xff);
	shift = (31 - shift) * 8; // 8 bits shift for 0x1e, 16 for 0x1d
	uint64_t tgt64 = swab32(bits);
	tgt64 = tgt64 << shift;
	// applog_hex(&tgt64, 8);
	uint8_t net_target[32] = { 0 };
	for (int b=0; b<8; b++)
		net_target[31-b] = ((uint8_t*)&tgt64)[b];
	// applog_hex(net_target, 32);
	double d = target_to_diff_equi((uint32_t*)net_target);
	return d;
}

unsigned int htoi(const char *s)
{
    unsigned int val = 0;
    int x = 0;

    if(s[x] == '0' && (s[x+1] == 'x' || s[x+1] == 'X'))
    	x += 2;

    while(s[x])
    {
       if(val > UINT_MAX)
    	   return 0;

       else if(s[x] >= '0' && s[x] <='9')
          val = val * 16 + s[x] - '0';

       else if(s[x]>='A' && s[x] <='F')
          val = val * 16 + s[x] - 'A' + 10;

       else if(s[x]>='a' && s[x] <='f')
          val = val * 16 + s[x] - 'a' + 10;

       else
    	   return 0;

       x++;
    }

    return val;
}

uint64_t htoi64(const char *s)
{
	uint64_t val = 0;
    int x = 0;

    if(s[x] == '0' && (s[x+1] == 'x' || s[x+1] == 'X'))
    	x += 2;

    while(s[x])
    {
       if(val > ULLONG_MAX)
    	   return 0;

       else if(s[x] >= '0' && s[x] <='9')
          val = val * 16 + s[x] - '0';

       else if(s[x]>='A' && s[x] <='F')
          val = val * 16 + s[x] - 'A' + 10;

       else if(s[x]>='a' && s[x] <='f')
          val = val * 16 + s[x] - 'a' + 10;

       else
    	   return 0;

       x++;
    }

    return val;
}

#if 0
// gettimeofday seems deprecated in POSIX
long long current_timestamp()
{
	long long milliseconds;
	struct timeval te;

	gettimeofday(&te, NULL);

	milliseconds = te.tv_sec*1000LL + te.tv_usec/1000;
	return milliseconds;
}
#else
long long current_timestamp()
{
	long long milliseconds;
	struct timespec te;

	clock_gettime(CLOCK_REALTIME, &te);

	milliseconds = 1000LL*te.tv_sec + round(te.tv_nsec/1e6);
	return milliseconds;
}
#endif

long long current_timestamp_dms() // allow 0.1 ms time
{
	long long dms;
	struct timespec te;

	clock_gettime(CLOCK_REALTIME, &te);

	dms = 10000LL*te.tv_sec + round(te.tv_nsec/1e5);
	return dms;
}

int opened_files()
{
	int fds = 0;
	DIR *d = opendir("/proc/self/fd");
	if (d) {
		while (readdir(d)) fds++;
		closedir(d);
	}
	return fds;
}

int resident_size()
{
	int sz, res = 0;
	FILE *fp = fopen("/proc/self/statm", "r");
	if (fp) {
		int p = fscanf(fp, "%d", &sz);
		if (p) p += fscanf(fp, "%d", &res);
		fclose(fp);
	}
	return res;
}

void string_lower(char *s)
{
	for(int i = 0; s[i]; i++)
	  s[i] = tolower(s[i]);
}

void string_upper(char *s)
{
	for(int i = 0; s[i]; i++)
	  s[i] = toupper(s[i]);
}

bool valid_string_params(json_value *json_params)
{
        for(int p=0; p < json_params->u.array.length; p++) {
                if (!json_is_string(json_params->u.array.values[p]))
                        return false;
        }
        return true;
}

void decode_nbits(uint256& target_, unsigned int nbits)
{
    unsigned char target[32];
    memset(target, 0, sizeof(target));

    unsigned int exp = nbits >> 24;
    unsigned int mant = nbits & 0xffffff;

    unsigned int shift = 8 * (exp - 3);
    unsigned int sb = shift / 8;
    unsigned int rb = shift % 8;

    // little-endian
    target[sb] = (mant << rb);
    target[sb + 1] = (mant >> (8-rb));
    target[sb + 2] = (mant >> (16-rb));
    target[sb + 3] = (mant >> (24-rb));

    memcpy(&target_, target, 32);
}

int string_tokenize(std::string const &input_string, const char delimiter, std::vector<std::string> &string_array) {
    size_t start;
    size_t end = 0;
    int parts_counter = 0;

    while ((start = input_string.find_first_not_of(delimiter, end)) != std::string::npos)
    {
        end = input_string.find(delimiter, start);
        string_array.push_back(input_string.substr(start, end - start));
        parts_counter++;
    }

    return parts_counter;
}

//////////////////////////////////////////////////////////////////////////////////////

int getblocheight(const char *coinb1)
{
	unsigned char coinb1_bin[1024];
	binlify(coinb1_bin, coinb1);

	int height = 0;
	uint8_t hlen = 0, *p, *m;

	// find 0xffff tag
	p = (uint8_t*)coinb1_bin + 32;
	m = p + 128;
	while (*p != 0xff && p < m) p++;
	while (*p == 0xff && p < m) p++;

	if (*(p-1) == 0xff && *(p-2) == 0xff)
	{
		p++; hlen = *p;
		p++; height = le16dec(p);
		p += 2;
		switch (hlen)
		{
			case 4:
				height += 0x10000UL * le16dec(p);
				break;
			case 3:
				height += 0x10000UL * (*p);
				break;
		}
	}

	return height;
}

void sha256_double_hash_hex(const char *input, char *output, unsigned int len)
{
	char output1[32];

	sha256_double_hash(input, output1, len);
	hexlify(output, (unsigned char *)output1, 32);
}

void sha256_hash_hex(const char *input, char *output, unsigned int len)
{
	char output1[32];

	sha256_hash(input, output1, len);
	hexlify(output, (unsigned char *)output1, 32);
}

unsigned char *SHA256(const unsigned char *d, size_t n, unsigned char *md) {
	sha256_hash((const char *)d, (char *)md, n);
}

void sha3d_hash_hex(const char *input, char *output, unsigned int len)
{
	char output1[32];

	sha3d_hash(input, output1, len);
	hexlify(output, (unsigned char *)output1, 32);
}

uint64_t share_to_target(double diff)
{
        int i, shift = 29;
        unsigned char targ[32];
        for (i=0; i<32; i++)
            targ[i]=0;
        double ftarg = (double)0x0000ffff / diff;
        while (ftarg < (double)0x00008000) {
            shift--;
            ftarg *= 256.0;
        }
        while (ftarg >= (double)0x00800000) {
            shift++;
            ftarg /= 256.0;
        }
        uint32_t nBits = (int)ftarg + (shift << 24);
        shift = (nBits >> 24) & 0x00ff;
        nBits &= 0x00FFFFFF;
        targ[shift - 1] = nBits >> 16;
        targ[shift - 2] = nBits >> 8;
        targ[shift - 3] = nBits;
        uint64_t starget = * (uint64_t *) &targ[24];
        return (starget);
}
