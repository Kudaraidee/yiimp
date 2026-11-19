
struct YAAMP_CLIENT;

struct COMMONLISTITEM
{
	void *data;

	struct COMMONLISTITEM *next;
	struct COMMONLISTITEM *prev;
};

typedef COMMONLISTITEM *CLI;

typedef void (*LISTFREEPARAM)(void *);

class CommonList
{
public:
	CommonList();
	~CommonList();

	CLI AddHead(void *data);
	CLI AddTail(void *data);

	void Delete(CLI item);
	void Delete(void *data);

	void DeleteAll(LISTFREEPARAM freeparam);

	CLI Find(void *data);
	void Swap(CLI i1, CLI i2);

	void Enter();
	void Leave();

	pthread_mutex_t mutex;
	int count;

	CLI first;
	CLI last;
};

void CommonLock(pthread_mutex_t *mutex);
void CommonUnlock(pthread_mutex_t *mutex);

//////////////////////////////////////////////////////////////////////////

bool json_get_bool(json_value *json, const char *name);
json_int_t json_get_int(json_value *json, const char *name);
double json_get_double(json_value *json, const char *name);
const char *json_get_string(json_value *json, const char *name);
json_value *json_get_array(json_value *json, const char *name);
json_value *json_get_object(json_value *json, const char *name);

void yaamp_create_mutex(pthread_mutex_t *mutex);
bool yaamp_error(char const *message);

const char *header_value(const char *data, const char *search, char *value);

void initlog(const char *algo);
void closelogs();

void debuglog(const char *format, ...);
void stratumlog(const char *format, ...);
void stratumlogdate(const char *format, ...);
void clientlog(YAAMP_CLIENT *client, const char *format, ...);
void rejectlog(const char *format, ...);

//////////////////////////////////////////////////////////////////////////

vector<string> merkle_steps(vector<string> input);
string merkle_with_first(vector<string> steps, string f);

//////////////////////////////////////////////////////////////////////////

typedef unsigned char uchar;
void cbin2hex(char *out, const char *in, size_t len);
char *bin2hex(const uchar *in, size_t len);

bool base58_decode(const char *input, char *output);
bool is_base58(char *input);

void base64_encode(char *base64, const char *normal);
void base64_decode(char *normal, const char *base64);

void ser_number(int n, char *s);
void ser_compactsize(uint64_t nSize, char *a);

void ser_string_be(const char *input, char *output, int len);
void ser_string_be2(const char *input, char *output, int len);

void string_be(const char *input, char *output);
void string_be1(char *s);

bool ishexa(char *hex, int len);

void hexlify(char *hex, const unsigned char *bin, int len);
void binlify(unsigned char *bin, const char *hex);

unsigned int htoi(const char *s);
uint64_t htoi64(const char *s);

uint64_t decode_compact(const char *input, int shiftdiff = 19);
uint64_t sharetotarg(double diff);

uint64_t diff_to_target(double difficulty);
void diff_to_target(uint32_t *target, double diff);
void diff_to_target(uint256& target, double diff);
uint64_t diff_to_target_coin(double difficulty, int powlimit_bits);
void diff_to_target_equi(uint32_t* target, double diff);
double target_to_diff(uint64_t target);
double target_to_diff(uint256& target);
double target_to_diff_coin(uint64_t target, int powlimit_bits);

uint64_t get_hash_difficulty(unsigned char *input);

double equi_network_diff(uint32_t *work_data);
uint64_t get_equihash_difficulty(unsigned char *input);

long long current_timestamp();
long long current_timestamp_dms();

int opened_files();
int resident_size();

void string_lower(char *s);
void string_upper(char *s);
int string_tokenize(std::string const &input_string, const char delimiter, std::vector<std::string> &string_array);

int getblocheight(const char *coinb1);

bool valid_string_params(json_value *json_params);
void decode_nbits(uint256& target_, unsigned int nbits);

//////////////////////////////////////////////////////////////////////////

#include <algorithm>
#include <string>
#include <vector>

//////////////////////////////////////////////////////////////////////////

#if !HAVE_DECL_LE16DEC
static inline uint16_t le16dec(const void *pp)
{
	const uint8_t *p = (uint8_t const *)pp;
	return ((uint16_t)(p[0]) + ((uint16_t)(p[1]) << 8));
}
#endif

static inline uint32_t bswap32(uint32_t x) {
	__asm__ __volatile__ ("bswapl %0" : "=r" (x) : "0" (x));
	return x;
}

uint64_t share_to_target(double diff);

unsigned char *SHA256(const unsigned char *d, size_t n, unsigned char *md);