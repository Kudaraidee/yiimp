
#include "stratum.h"

static int g_extraonce1_counter = 0;

void get_next_extraonce1(char *extraonce1)
{
	CommonLock(&g_nonce1_mutex);

	g_extraonce1_counter++;
	if (!strcmp(g_stratum_algo, "neoscrypt-xaya")) sprintf(extraonce1, "%08x", g_extraonce1_counter | 0x00008100);
	else sprintf(extraonce1, "%08x", g_extraonce1_counter|0x81000000);

	CommonUnlock(&g_nonce1_mutex);
}

void get_random_key(char *key)
{
	int i1 = rand();
	int i2 = rand();
	int i3 = rand();
	int i4 = rand();
	sprintf(key, "%08x%08x%08x%08x", i1, i2, i3, i4);
}

YAAMP_CLIENT *client_find_notify_id(const char *notify_id, bool reconnecting)
{
	if (!notify_id || !strlen(notify_id))
		return NULL;

	g_list_client.Enter();
	for(CLI li = g_list_client.first; li; li = li->next)
	{
		YAAMP_CLIENT *client = (YAAMP_CLIENT *)li->data;
		if(client->reconnecting == reconnecting && !strcmp(client->notify_id, notify_id))
		{
			g_list_client.Leave();
			return client;
		}
	}

	g_list_client.Leave();
	return NULL;
}

// shift the window to now; slots older than the window drop out
static void client_speed_roll(YAAMP_CLIENT *client, int now)
{
	int slot = now / g_stratum_speed_slot_secs;
	int gap = slot - client->speed_slot;
	if(gap <= 0) return;

	if(gap >= YAAMP_SPEED_SLOTS)
	{
		memset(client->speed_slots, 0, sizeof(client->speed_slots));
		memset(client->speed_share_slots, 0, sizeof(client->speed_share_slots));
		memset(client->speed_sq_slots, 0, sizeof(client->speed_sq_slots));
	}
	else
	{
		memmove(&client->speed_slots[gap], &client->speed_slots[0], (YAAMP_SPEED_SLOTS - gap) * sizeof(double));
		memset(client->speed_slots, 0, gap * sizeof(double));
		memmove(&client->speed_share_slots[gap], &client->speed_share_slots[0], (YAAMP_SPEED_SLOTS - gap) * sizeof(int));
		memset(client->speed_share_slots, 0, gap * sizeof(int));
		memmove(&client->speed_sq_slots[gap], &client->speed_sq_slots[0], (YAAMP_SPEED_SLOTS - gap) * sizeof(double));
		memset(client->speed_sq_slots, 0, gap * sizeof(double));
	}
	client->speed_slot = slot;
}

static void client_speed_calc(YAAMP_CLIENT *client, int now)
{
	int secs = g_stratum_speed_slot_secs;
	int short_slots = std::max(1, std::min(YAAMP_SPEED_SLOTS, (YAAMP_SPAM_WINDOW_SECS + secs - 1) / secs));

	double work = 0, sq = 0, work_short = 0;
	int shares = 0, shares_short = 0;
	for(int i = 0; i < YAAMP_SPEED_SLOTS; i++)
	{
		work += client->speed_slots[i];
		shares += client->speed_share_slots[i];
		sq += client->speed_sq_slots[i];
		if(i < short_slots)
		{
			work_short += client->speed_slots[i];
			shares_short += client->speed_share_slots[i];
		}
	}

	// a window = its full older slots + the elapsed part of the current one;
	// a young client has not filled it yet
	int age = now - client->speed_since;
	int part = now % secs;
	int span = std::max(std::min(age, (YAAMP_SPEED_SLOTS - 1) * secs + part), secs);
	int span_short = std::max(std::min(age, (short_slots - 1) * secs + part), secs);

	client->speed = work / span;
	client->speed_shares = shares;
	client->speed_neff = sq > 0 ? work * work / sq : 0;
	client->speed_span = span;
	client->speed_short = work_short / span_short;
	client->speed_short_shares = shares_short;
}

// difficulty 1 = 2^32 hashes, same scale as coind_nethash()
void client_speed_add(YAAMP_CLIENT *client, double difficulty)
{
	int now = time(NULL);
	client_speed_roll(client, now);
	double hashes = difficulty / g_current_algo->diff_multiplier * 4294967296.0;
	client->speed_slots[0] += hashes;
	client->speed_share_slots[0]++;
	client->speed_sq_slots[0] += hashes * hashes;
	client->last_share = now;
	client_speed_calc(client, now);
}

void client_speed_update(YAAMP_CLIENT *client)
{
	int now = time(NULL);
	client_speed_roll(client, now);
	client_speed_calc(client, now);
}

// "#<request id> <ip> <wallet>.<worker>" for debug logs; username is empty before authorize
void client_debug_who(YAAMP_CLIENT *client, char *out, int size)
{
	char reqid[64];
	if(client->id_str) snprintf(reqid, sizeof(reqid), "%s", client->id_str);
	else snprintf(reqid, sizeof(reqid), "%d", client->id_int);

	snprintf(out, size, "#%s %s%s%s%s%s", reqid, client->sock->ip,
		client->username[0]? " ": "", client->username,
		client->worker[0]? ".": "", client->worker);
}

// one line per submit: share difficulty / difficulty it was judged at, and the result
void client_log_submit(YAAMP_CLIENT *client, const char *result)
{
	if(!g_debuglog_client) return;

	char who[1200];
	client_debug_who(client, who, sizeof(who));

	if(client->submit_share_diff >= 0)
		debuglog("client mining.submit %s: diff %.6f / %g %s\n", who,
			client->submit_share_diff, client->share_difficulty, result);
	else
		debuglog("client mining.submit %s: %s\n", who, result);
}

void client_sort()
{
	for(CLI li = g_list_client.first; li && li->next; li = li->next)
	{
		YAAMP_CLIENT *client1 = (YAAMP_CLIENT *)li->data;
		YAAMP_CLIENT *client2 = (YAAMP_CLIENT *)li->next->data;

//		if(client2->difficulty_actual > client1->difficulty_actual)
		if(client2->speed > client1->speed*1.5)
		{
			g_list_client.Swap(li, li->next);
			client_sort();

			return;
		}
	}
}

int client_send_error(YAAMP_CLIENT *client, int error, const char *string)
{
	char buffer3[1024];

	if(client->id_str)
		sprintf(buffer3, "\"%s\"", client->id_str);
	else
		sprintf(buffer3, "%d", client->id_int);

	return socket_send(client->sock, "{\"id\":%s,\"result\":false,\"error\":[%d,\"%s\",null]}\n", buffer3, error, string);
}

int client_send_result(YAAMP_CLIENT *client, const char *format, ...)
{
	char buffer[YAAMP_SMALLBUFSIZE];
	va_list args;

	va_start(args, format);
	vsprintf(buffer, format, args);
	va_end(args);

	char buffer3[1024];

	if(client->id_str)
		sprintf(buffer3, "\"%s\"", client->id_str);
	else
		sprintf(buffer3, "%d", client->id_int);

	return socket_send(client->sock, "{\"id\":%s,\"result\":%s,\"error\":null}\n", buffer3, buffer);
}

int client_call(YAAMP_CLIENT *client, const char *method, const char *format, ...)
{
	char buffer[YAAMP_SMALLBUFSIZE];
	va_list args;

	va_start(args, format);
	vsprintf(buffer, format, args);
	va_end(args);

	return socket_send(client->sock, "{\"id\":null,\"method\":\"%s\",\"params\":%s}\n", method, buffer);
}

int client_ask(YAAMP_CLIENT *client, const char *method, const char *format, ...)
{
	char buffer[YAAMP_SMALLBUFSIZE];
	va_list args;
	int64_t id = client->shares;

	va_start(args, format);
	vsprintf(buffer, format, args);
	va_end(args);

	int ret = socket_send(client->sock, "{\"id\":%d,\"method\":\"%s\",\"params\":%s}\n", id, method, buffer);
	if (ret == -1) {
		debuglog("unable to ask %s\n", method);
		return 0; // -errno
	}
	client->reqid = id;
	return id;
}

void client_block_ip(YAAMP_CLIENT *client, const char *reason)
{
	char buffer[1024];
	sprintf(buffer, "iptables -A INPUT -s %s -p tcp --dport %d -j REJECT", client->sock->ip, g_tcp_port);
	if(strcmp("0.0.0.0", client->sock->ip) == 0) return;
	if(strstr(client->sock->ip, "192.168.")) return;
	if(strstr(client->sock->ip, "127.0.0.")) return;

	int s = system(buffer);
	stratumlog("%s: %s blocked (%s)\n", g_stratum_algo, client->sock->ip, reason);
}

void client_block_ipset(YAAMP_CLIENT *client, const char *ipset_name)
{
	char buffer[1024];
	sprintf(buffer, "ipset -q -A %s %s", ipset_name, client->sock->ip);
	if(strcmp("0.0.0.0", client->sock->ip) == 0) return;
	if(strstr(client->sock->ip, "192.168.")) return;
	if(strstr(client->sock->ip, "127.0.0.")) return;

	int s = system(buffer);
	stratumlog("%s: %s blocked via ipset %s %s\n", g_stratum_algo, client->sock->ip, ipset_name, client->username);
}

bool client_reset_multialgo(YAAMP_CLIENT *client, bool first)
{
//	return false;
	if(!client->algos_subscribed[0].algo) return false;
//	debuglog("client_reset_multialgo\n");

	YAAMP_CLIENT_ALGO *best = NULL;
	YAAMP_CLIENT_ALGO *current = NULL;

	for(int i=0; g_algos[i].name[0]; i++)
	{
		YAAMP_ALGO *algo = &g_algos[i];
		for(int j=0; client->algos_subscribed[j].algo; j++)
		{
			YAAMP_CLIENT_ALGO *candidate = &client->algos_subscribed[j];
			if(candidate->algo == algo)
			{
				if(!best || algo->profit*candidate->factor > best->algo->profit*best->factor)
					best = candidate;
			}

			if(!current && candidate->algo == g_current_algo)
				current = candidate;
		}
	}

	if(!best || !current || best == current)
	{
		client->last_best = time(NULL);
		return false;
	}

	if(!first)
	{
		int e = time(NULL) - client->last_best;
		double d = best->algo->profit*best->factor - current->algo->profit*current->factor;
		double p = d/best->algo->profit/best->factor;
#ifdef DEBUG_BEST_MULTI
		debuglog("current %s %f\n", current->algo->name, current->algo->profit*current->factor);
		debuglog("best    %s %f\n", best->algo->name, best->algo->profit*best->factor);
		debuglog(" %d * %f = %f --- percent %f %f\n", e, d, e*d, p, e*p);
#endif
		if(p < 0.02) return false;
		if(e*p < 100) return false;
	}

	shutdown(client->sock->sock, SHUT_RDWR);
	return true;
}

bool client_initialize_multialgo(YAAMP_CLIENT *client)
{
	char *p = strstr(client->password, "p=");
	if(p)
	{
		double profit = atof(p+2);
		if(profit > g_current_algo->profit)
			return true;
	}

	char tmp[1024];
	memset(tmp, 0, 1024);
	strncpy(tmp, client->password, 1023);

	p = tmp;
	while(p)
	{
		double value = 0;

		char *p1 = strchr(p, ',');
		if(p1) *p1 = 0;

		char *p2 = strchr(p, '=');
		if(p2)
		{
			*p2 = 0;
			value = atof(p2+1);
		}

		for(int i=0; g_algos[i].name[0]; i++)
		{
			YAAMP_ALGO *algo = &g_algos[i];
			if(!strcmp(algo->name, p))
			{
				int i=0;
				for(; i<YAAMP_MAXALGOS-1 && client->algos_subscribed[i].algo; i++);

				client->algos_subscribed[i].algo = algo;
				client->algos_subscribed[i].factor = value? value: algo->factor;
			}
		}

		p = p1? p1+1: p1;
	}

	bool reset = client_reset_multialgo(client, true);
	return reset;
}

void client_add_job_history(YAAMP_CLIENT *client, int jobid)
{
	if(!jobid)
	{
		debuglog("trying to add jobid 0\n");
		return;
	}

	bool b = client_find_job_history(client, jobid, 0);
	if(b)
	{
//		debuglog("ERROR history already added job %x\n", jobid);
		return;
	}

	for(int i=YAAMP_JOB_MAXHISTORY-1; i>0; i--)
		client->job_history[i] = client->job_history[i-1];

	client->job_history[0] = jobid;
}

bool client_find_job_history(YAAMP_CLIENT *client, int jobid, int startat)
{
	for(int i=startat; i<YAAMP_JOB_MAXHISTORY; i++)
	{
		if(client->job_history[i] == jobid)
		{
//			if(!startat)
//				debuglog("job %x already sent, index %d\n", jobid, i);

			return true;
		}
	}

	return false;
}

int hostname_to_ip(const char *hostname , char* ip)
{
    struct hostent *he;
    struct in_addr **addr_list;
    int i;

    if(hostname[0]>='0' && hostname[0]<='9')
    {
    	strcpy(ip, hostname);
    	return 0;
    }

    if ( (he = gethostbyname( hostname ) ) == NULL)
    {
        // get the host info
        herror("gethostbyname");
        return 1;
    }

    addr_list = (struct in_addr **) he->h_addr_list;

    for(i = 0; addr_list[i] != NULL; i++)
    {
        //Return the first one;
        strcpy(ip, inet_ntoa(*addr_list[i]));
        return 0;
    }

    return 1;
}

bool client_find_my_ip(const char *name)
{
//	return false;
	char ip[1024] = "";

	hostname_to_ip(name, ip);
	if(!ip[0]) return false;

	char host[NI_MAXHOST];
	for(struct ifaddrs *ifa = g_ifaddr; ifa != NULL; ifa = ifa->ifa_next)
	{
		if(ifa->ifa_addr == NULL) continue;
		host[0] = 0;

		getnameinfo(ifa->ifa_addr, sizeof(struct sockaddr_in), host, NI_MAXHOST, NULL, 0, NI_NUMERICHOST);
		if(!host[0]) continue;

		if(!strcmp(host, ip))
		{
			debuglog("found my ip %s\n", ip);
			return true;
		}
	}

	return false;
}







