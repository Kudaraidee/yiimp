
// http://www.righto.com/2014/02/bitcoin-mining-hard-way-algorithms.html

// https://en.bitcoin.it/wiki/Merged_mining_specification#Merged_mining_coinbase

#include "stratum.h"

#define TX_VALUE(v, s)	((unsigned int)(v>>s)&0xff)

static void encode_tx_value(char *encoded, json_int_t value)
{
	sprintf(encoded, "%02x%02x%02x%02x%02x%02x%02x%02x",
		TX_VALUE(value, 0), TX_VALUE(value, 8), TX_VALUE(value, 16), TX_VALUE(value, 24),
		TX_VALUE(value, 32), TX_VALUE(value, 40), TX_VALUE(value, 48), TX_VALUE(value, 56));
}

static void p2wpkh_pack_tx(YAAMP_COIND *coind, char *data, json_int_t amount, char *payee, bool add_witness = false)
{
	char evalue[32];
	char payee_len[4];

	encode_tx_value(evalue, amount);
	sprintf(payee_len, "%02x", (unsigned int)(strlen(payee) >> 1) & 0xFF);

	strcat(data, evalue);
	strcat(data, payee_len);
	strcat(data, payee);

	if (add_witness) {
		// add for segwit txs count =1, length = 32, witness '00..00'
		strcat(data, "01200000000000000000000000000000000000000000000000000000000000000000");
	}
}
 
static void p2sh_pack_tx(YAAMP_COIND *coind, char *data, json_int_t amount, char *payee)
{
	char evalue[32];
	char coinb2_part[256];
	char coinb2_len[4];
	sprintf(coinb2_part, "a9%02x%s87", (unsigned int)(strlen(payee) >> 1) & 0xFF, payee);
	sprintf(coinb2_len, "%02x", (unsigned int)(strlen(coinb2_part) >> 1) & 0xFF);
	encode_tx_value(evalue, amount);
	strcat(data, evalue);
	strcat(data, coinb2_len);
	strcat(data, coinb2_part);
}

static void script_pack_tx(YAAMP_COIND *coind, char *data, json_int_t amount, const char *script)
{
	char evalue[32];
	char coinb2_part[256];
	char coinb2_len[4];
	encode_tx_value(evalue, amount);
	sprintf(coinb2_part, "%s", script);
	sprintf(coinb2_len, "%02x", (unsigned int)(strlen(coinb2_part) >> 1) & 0xFF);
	strcat(data, evalue);
	strcat(data, coinb2_len);
	strcat(data, coinb2_part);
}

static void job_pack_tx(YAAMP_COIND *coind, char *data, json_int_t amount, char *key, bool add_witness = false)
{
	int ol = strlen(data);
	char evalue[32];

	if(coind->p2wpkh) {
		p2wpkh_pack_tx(coind, data, amount, coind->script_pubkey, add_witness);
		return;
	}

	if(coind->p2sh_address && !key) {
		p2sh_pack_tx(coind, data, amount, coind->script_pubkey);
		return;
	}

	encode_tx_value(evalue, amount);
	sprintf(data+strlen(data), "%s", evalue);

	if(coind->pos && !key)
		sprintf(data+strlen(data), "2321%sac", coind->pubkey);

	else
		sprintf(data+strlen(data), "1976a914%s88ac", key? key: coind->script_pubkey);

//	debuglog("pack tx %s\n", data+ol);
//	debuglog("pack tx %lld\n", amount);
}

void coinbase_aux(YAAMP_JOB_TEMPLATE *templ, char *aux_script)
{
	vector<string> hashlist = coind_aux_hashlist(templ->auxs, templ->auxs_size);
	while(hashlist.size() > 1)
	{
		vector<string> l;
		for(int i = 0; i < hashlist.size()/2; i++)
		{
			string s = hashlist[i*2] + hashlist[i*2+1];

			char bin[YAAMP_HASHLEN_BIN*2];
			char out[YAAMP_HASHLEN_STR];

			binlify((unsigned char *)bin, s.c_str());
			sha256_double_hash_hex(bin, out, YAAMP_HASHLEN_BIN*2);

			l.push_back(out);
		}

		hashlist = l;
	}

	char merkle_hash[4*1024];
	memset(merkle_hash, 0, 4*1024);
	string_be(hashlist[0].c_str(), merkle_hash);

	// DASH based aux coins like OSMI use different magic code
	if (!strcmp(g_stratum_algo, "x11")) {
		sprintf(aux_script+strlen(aux_script), "4f534d41%s%02x00000000000000", merkle_hash, templ->auxs_size);
    } else {
		sprintf(aux_script+strlen(aux_script), "fabe6d6d%s%02x00000000000000", merkle_hash, templ->auxs_size);
	}
}

void coinbase_create(YAAMP_COIND *coind, YAAMP_JOB_TEMPLATE *templ, json_value *json_result)
{
	//================= Alien coins/algos/encodings

	templ->isbitcash = false;
	if(!strcmp(coind->symbol, "BITC")) 
	{
		char *params = (char *)malloc(4096);
		if (params) {
			unsigned char price_bin[1024];
			unsigned char pricehash_bin[1024];
			char pricehash_hex[1024];
			char pricehash_be[1024];

			if (templ->needpriceinfo && strlen(templ->priceinfo) > 0 && strlen(templ->priceinfo) < 1000) {
				binlify(price_bin, templ->priceinfo);

				int price_len = strlen(templ->priceinfo)/2;
				sha256_double_hash((char *)price_bin, (char *)pricehash_bin, price_len);

				hexlify(pricehash_hex, pricehash_bin, 32);
				string_be(pricehash_hex, pricehash_be);

				sprintf(params, "[\"%s\", %i, \"%s\"]", coind->wallet, templ->height, pricehash_be);
			} else {
				sprintf(params, "[\"%s\", %i]", coind->wallet, templ->height);
			}
			//std::cout << "Params:" << params << std::endl;
			json_value *json = rpc_call(&coind->rpc, "createcoinbaseforaddress", params);

			free(params);
			if (json) {
				json_value *json_result = json_get_object(json, "result");
				if (json_result) {
					sprintf(templ->coinb1, "%s", json_get_string(json_result, "coinbaseforhashpart1"));
					templ->coinb1[strlen(templ->coinb1) - 16] = '\0';
					sprintf(templ->coinb2, "%s", json_get_string(json_result, "coinbaseforhashpart2"));

					sprintf(templ->coinforsubmitb1, "%s", json_get_string(json_result, "coinbasepart1"));
					templ->coinforsubmitb1[strlen(templ->coinforsubmitb1) - 16] = '\0';
					sprintf(templ->coinforsubmitb2, "%s", json_get_string(json_result, "coinbasepart2"));
					templ->isbitcash = true;
				}
			}
		}
		return;
	}

	char eheight[32], etime[32];
	char entime[32] = { 0 };
	char commitment[128] = { 0 };

	ser_number(templ->height, eheight);
	ser_number(time(NULL), etime);
	if(coind->pos) ser_string_be(templ->ntime, entime, 1);

	char eversion1[32] = "01000000";
	if(coind->txmessage)
		strcpy(eversion1, "02000000");

	if(!strcmp(g_stratum_algo,"flex")) strcpy(eversion1, "08000000");

	const char *coinbase_payload = json_get_string(json_result, "coinbase_payload");

	if(coinbase_payload && strlen(coinbase_payload) > 0)
		strcpy(eversion1, "03000500");

	char script1[4*1024];
	char script2[32];
	if (is_kawpow || is_firopow || is_phihash)
	{
		// coinbase v_in only current height
		sprintf(script1, "%s", eheight);
		int script_len = strlen(script1)/2;
		sprintf(templ->coinb1, "%s%s01"
			"0000000000000000000000000000000000000000000000000000000000000000"
			"ffffffff%02x%s", eversion1, entime, script_len, script1);
	
		sprintf(templ->coinb2, "ffffffff");
	}
	else {
		sprintf(script1, "%s%s%s08", eheight, templ->flags, etime);

		sprintf(script2, "067969696d7000"); // "yiimp\0" in hex ascii //"506f6f6c4d696e652e78797a" -- PoolMine.xyz

		if(!coind->pos && !coind->isaux && templ->auxs_size)
			coinbase_aux(templ, script2);

		int script_len = strlen(script1)/2 + strlen(script2)/2 + 8;
		if(!strcmp(g_stratum_algo,"neoscrypt-xaya")) script_len -= 8;
		sprintf(templ->coinb1, "%s%s01"
			"0000000000000000000000000000000000000000000000000000000000000000"
			"ffffffff%02x%s", eversion1, entime, script_len, script1);

		sprintf(templ->coinb2, "%s00000000", script2);
	}

	// segwit commitment, if needed
	if (templ->has_segwit_txs)
		sprintf(commitment, "0000000000000000%02x%s", (int) (strlen(coind->commitment)/2), coind->commitment);

	json_int_t available = templ->value;

	//================= Semi-alien coins/algos/encodings go below. Synax hasn't changed. End 'em with 'return'. For btc forks use it for the wildest of coin devs' fantasies - I've left some examples.

	if (!strcmp(coind->rpcencoding, "ZEC")) {
	// init saplingroothash for equihash
		templ->saplingroothash[0] = 0;

		int n = templ->height; int s;
		for(int i=0; i<32; i++) {
			char tmp[32];
			s = n % 256; n /= 256;
			sprintf(tmp, "%02x", s);
			strcat(templ->saplingroothash, tmp);
		}
		string_be1(templ->saplingroothash);

		char eversion3[64] = "0400008085202f89";

		sprintf(script1, "%s%s%s00", eheight, templ->flags, etime);
		// nonce not included , set length to 0

		// Pool identstring in coinbase-TX
		sprintf(script2,"7969696d7000"); // "yiimp\0" in hex ascii //"506f6f6c4d696e652e78797a" -- PoolMine.xyz

		if(!coind->pos && !coind->isaux && templ->auxs_size)
			coinbase_aux(templ, script2);

		// nonce not included in coinbase of equihash-coins
		bool is_equihash = (strstr(g_current_algo->name, "equihash") == g_current_algo->name);
		int script_len = strlen(script1)/2 + strlen(script2)/2 + (is_equihash?0:8);
		sprintf(templ->coinb1, "%s%s01"
			"0000000000000000000000000000000000000000000000000000000000000000"
			"ffffffff%02x%s", eversion3, entime, script_len, script1);

		sprintf(templ->coinb2, "%s00000000", script2);

		// segwit commitment, if needed
		if (templ->has_segwit_txs)
			sprintf(commitment, "0000000000000000%02x%s", (int) (strlen(coind->commitment)/2), coind->commitment);

		// add coinbase-tx
		job_pack_tx(coind, templ->coinb2, available, NULL);

		const char *finalsaplingroothash = json_get_string(json_result, "finalsaplingroothash");
		if (finalsaplingroothash)
			sprintf(templ->saplingroothash, "%s", finalsaplingroothash);
		else
			sprintf(templ->saplingroothash, "0000000000000000000000000000000000000000000000000000000000000000");

		json_value* template_coinbase = json_get_array(json_result, "coinbasetxn");
		if (template_coinbase) {
			const char *coinbasetxn_data = json_get_string(template_coinbase, "data");
			sprintf(templ->coinbase, "%s", coinbasetxn_data);
			//debuglog("coinbase %s\n",templ->coinbase);
		}

		// temp. analyze template without creating coinbase tx - using precreated tx
		json_int_t masternode_amount = json_get_int(json_result, "payee_amount");

		bool masternode_payments = json_get_bool(json_result, "masternode_payments");
		bool masternode_enforce = json_get_bool(json_result, "enforce_masternode_payments");

		if (masternode_payments && masternode_enforce && masternode_amount) {
			available -= masternode_amount;
		}

		coind->reward = (double)available/100000000*coind->reward_mul;
		//debuglog("%s %d dests %s\n", coind->symbol, npayees, script_dests);
		return;
	}

	if(!strcmp(g_stratum_algo,"neoscrypt-xaya")) {
       	sprintf(templ->xaya_coinbase, "%s%s", templ->coinb1, templ->coinb2);

		int coinbase_len = strlen(templ->xaya_coinbase);

		unsigned char coinbase_bin[1024];
		memset(coinbase_bin, 0, 1024);
		binlify(coinbase_bin, templ->xaya_coinbase);

		char doublehash[128];
		memset(doublehash, 0, 128);

		sha256_double_hash_hex((char *)coinbase_bin, doublehash, coinbase_len/2);

		string merkleroot = merkle_with_first(templ->txsteps, doublehash);

		ser_string_be(merkleroot.c_str(), templ->xaya_merkleroothash, 8);

		char prevhash[128];
		memset(prevhash, 0, 128);

		string_be(templ->prevhash_hex, prevhash);
		ser_string_be(prevhash, templ->prevhash_be, 8);

		char xaya_header[1024];
		memset(xaya_header, 0, 1024);

		sprintf(xaya_header, "%s%s%s%s", templ->version, templ->prevhash_be, templ->xaya_merkleroothash, templ->ntime);

		ser_string_be(xaya_header, templ->xaya_header, 20);
		strcat(templ->xaya_header, "0000");
		return;
	}

	// sample coins using mandatory dev/foundation fees
	if (!strcmp(coind->symbol, "EGC")) //hardcoded everything
	{
		if (coind->charity_percent <= 0)
			coind->charity_percent = 2;
		if (strlen(coind->charity_address) == 0)
			sprintf(coind->charity_address, "EdFwYw4Mo2Zq6CFM2yNJgXvE2DTJxgdBRX");
	}

	else if(!strcmp(coind->symbol, "LTCR")) //hardcoded everything
	{
		if (coind->charity_percent <= 0)
			coind->charity_percent = 10;
		if (strlen(coind->charity_address) == 0)
			sprintf(coind->charity_address, "BCDrF1hWdKTmrjXXVFTezPjKBmGigmaXg5");
	}

	/* else if(!strcmp("DCR", coind->rpcencoding)) //afaik this doen't work any more, left for history
	{
		coind->reward_mul = 6;  // coinbase value is wrong, reward_mul should be 6
		coind->charity_percent = 0;
		coind->charity_amount = available;
		available *= coind->reward_mul;
		if (strlen(coind->charity_address) == 0 && !strcmp(coind->symbol, "DCR"))
			sprintf(coind->charity_address, "Dcur2mcGjmENx4DhNqDctW5wJCVyT3Qeqkx");
	}
	if(coind->charity_amount && !strcmp("DCR", coind->rpcencoding))	{
		stratumlog("ERROR %s should not use coinbase (getwork only)!\n", coind->symbol);
		coind->reward = (double)available/100000000;
		return;
	}*/

	else if(!strcmp(coind->symbol, "GXX")) //hardcoded everything
	{
		char script_payee[1024];
		bool znode_masternode_enabled = json_get_bool(json_result, "xnode_payments_started");
		
		if (znode_masternode_enabled == true) {
			json_value* znode_masternode = json_get_object(json_result, "xnode");
			const char *payee = json_get_string(znode_masternode, "payee");
			json_int_t amount = json_get_int(znode_masternode, "amount");
			
			if (payee && amount) {
				//debuglog("xnode payee: %s\n", payee);
				strcat(templ->coinb2, "04");
				job_pack_tx(coind, templ->coinb2, available, NULL);

				base58_decode(payee, script_payee);
				job_pack_tx(coind, templ->coinb2, amount, script_payee);
			}
		} 
		else {
			strcat(templ->coinb2, "03");
			job_pack_tx(coind, templ->coinb2, available, NULL);
		}

		base58_decode("HU9t1QEp5J8udekCqFUEajD5TeigPqtfDZ", script_payee);
		job_pack_tx(coind, templ->coinb2, 2 * 100000000, script_payee);

		base58_decode("HLFmojjH6qLBTh5EXtbY9j9v4BCkNpmt95", script_payee);
		job_pack_tx(coind, templ->coinb2, 1.5 * 100000000, script_payee);

		strcat(templ->coinb2, "00000000"); // locktime
		coind->reward = (double)available/100000000*coind->reward_mul;

		return;
	}

	else if(!strcmp(coind->symbol, "FIRO")) //hardcoded everything
	{
        char script_payee[1024];
        bool znode_masternode_enabled = json_get_bool(json_result, "znode_payments_started");
        if (znode_masternode_enabled == true) {
            json_value* znode_masternode = json_get_object(json_result, "znode");
            const char *payee = json_get_string(znode_masternode, "payee");
            json_int_t amount = json_get_int(znode_masternode, "amount");
            if (payee && amount) {
                //debuglog("znode payee: %s\n", payee);
                strcat(templ->coinb2, "04");
                job_pack_tx(coind, templ->coinb2, available, NULL);
                base58_decode(payee, script_payee);
                job_pack_tx(coind, templ->coinb2, amount, script_payee);
            }
        } else {
            strcat(templ->coinb2, "03");
            job_pack_tx(coind, templ->coinb2, available, NULL);
        }
        base58_decode("aLgRaYSFk6iVw2FqY1oei8Tdn2aTsGPVmP", script_payee);
        job_pack_tx(coind, templ->coinb2, 0.9375 * 100000000, script_payee);
        base58_decode("aFA2TbqG9cnhhzX5Yny2pBJRK5EaEqLCH7", script_payee);
        job_pack_tx(coind, templ->coinb2, 0.625 * 100000000, script_payee);
        strcat(templ->coinb2, "00000000"); // locktime
        coind->reward = (double)available/100000000*coind->reward_mul;
        return;
    }

	else if(!strcmp(coind->symbol, "HXX")) //hardcoded everything
	{
        char script_payee[1024];
        bool znode_masternode_enabled = json_get_bool(json_result, "xnode_payments_started");
        if (znode_masternode_enabled == true) {
            json_value* znode_masternode = json_get_object(json_result, "xnode");
            const char *payee = json_get_string(znode_masternode, "payee");
            json_int_t amount = json_get_int(znode_masternode, "amount");
            if (payee && amount) {
                //debuglog("bznode payee: %s\n", payee);
                strcat(templ->coinb2, "06");
                job_pack_tx(coind, templ->coinb2, available, NULL);
                base58_decode(payee, script_payee);
                job_pack_tx(coind, templ->coinb2, amount, script_payee);
            }
        } else {
            strcat(templ->coinb2, "05");
            job_pack_tx(coind, templ->coinb2, available, NULL);
        }
        base58_decode("HE7NSv3jevUAPjwsLGpoYSz9ftzV9S36Xq", script_payee);
        job_pack_tx(coind, templ->coinb2, 0.1 * 100000000, script_payee);
        base58_decode("HNdzbEtifr2nTd3VBvUWqJLc35ZFXr2EYo", script_payee);
        job_pack_tx(coind, templ->coinb2, 0.1 * 100000000, script_payee);
        base58_decode("HG1utYiVhkgBNz5ezrVpsjABxmMdVdcQe5", script_payee);
        job_pack_tx(coind, templ->coinb2, 0.1 * 100000000, script_payee);
        base58_decode("H94j1zMAbWwHWcEq8hUogAMALpVzj34M6Q", script_payee);
        job_pack_tx(coind, templ->coinb2, 0.3 * 100000000, script_payee);
        strcat(templ->coinb2, "00000000"); // locktime
        coind->reward = (double)available/100000000*coind->reward_mul;
        return;
    }

	else if(!strcmp(coind->symbol, "BZX")) //hardcoded everything
	{
		char script_payee[1024];
		bool znode_masternode_enabled = json_get_bool(json_result, "bznode_payments_started");
		if (znode_masternode_enabled == true) {
			json_value* znode_masternode = json_get_object(json_result, "bznode");
			const char *payee = json_get_string(znode_masternode, "payee");
			json_int_t amount = json_get_int(znode_masternode, "amount");
			if (payee && amount) {
				//debuglog("bznode payee: %s\n", payee);
				strcat(templ->coinb2, "04");
				job_pack_tx(coind, templ->coinb2, available, NULL);
				base58_decode(payee, script_payee);
				job_pack_tx(coind, templ->coinb2, amount, script_payee);
			}
		} else {
			strcat(templ->coinb2, "03");
			job_pack_tx(coind, templ->coinb2, available, NULL);
		}
		base58_decode("XWfdnGbXnBxeegrPJEvnYaNuwf6DXCruMX", script_payee);
		job_pack_tx(coind, templ->coinb2, 6.75 * 100000000, script_payee);
		base58_decode("XQ4WEZTFP83gVhhLBKavwopz7U84JucR8w", script_payee);
		job_pack_tx(coind, templ->coinb2, 2.25 * 100000000, script_payee);
		strcat(templ->coinb2, "00000000"); // locktime
		coind->reward = (double)available/100000000*coind->reward_mul;
		return;
	}

	else if(!strcmp(coind->symbol, "STAK")) //hardcoded everything
	{
		char script_payee[512] = { 0 };
		char payees[4];
		int npayees = (templ->has_segwit_txs) ? 2 : 1;
		bool masternode_payments = json_get_bool(json_result, "masternode_payments");
		bool masternodes_enabled = json_get_bool(json_result, "enforce_masternode_payments");

		if (masternodes_enabled && masternode_payments) {
			const char *payee = json_get_string(json_result, "payee");
			json_int_t amount = json_get_int(json_result, "payee_amount");
			if (payee && amount)
				++npayees;
		}

		//treasury 5% @ 10 STAK per block
		json_int_t charity_amount = 50000000;
		//testnet
		//sprintf(coind->charity_address, "93ASJtDuVYVdKXemH9BrtSMscznvsp9stD");
		switch (templ->height % 4) {
			case 0: sprintf(coind->charity_address, "3K3bPrW5h7DYEMp2RcXawTCXajcm4ZU9Zh");
			break;
			case 1: sprintf(coind->charity_address, "33Ssxmn3ehVMgyxgegXhpLGSBpubPjLZQ6");
			break;
			case 2: sprintf(coind->charity_address, "3HFPNAjesiBY5sSVUmuBFnMEGut69R49ca");
			break;
			case 3: sprintf(coind->charity_address, "37jLjjfUXQU4bdqVzvpUXyzAqPQSmxyByi");
			break;
		}
		++npayees;
		available -= charity_amount;
		base58_decode(coind->charity_address, script_payee);
		sprintf(payees, "%02x", npayees);
		strcat(templ->coinb2, payees);
		if (templ->has_segwit_txs) strcat(templ->coinb2, commitment);
		char echarity_amount[32];
		encode_tx_value(echarity_amount, charity_amount);
		strcat(templ->coinb2, echarity_amount);
		char coinb2_part[1024] = { 0 };
		char coinb2_len[3] = { 0 };
		sprintf(coinb2_part, "a9%02x%s87", (unsigned int)(strlen(script_payee) >> 1) & 0xFF, script_payee);
		sprintf(coinb2_len, "%02x", (unsigned int)(strlen(coinb2_part) >> 1) & 0xFF);
		strcat(templ->coinb2, coinb2_len);
		strcat(templ->coinb2, coinb2_part);
		if (masternodes_enabled && masternode_payments) {
			//duplicated: revisit ++todo
			const char *payee = json_get_string(json_result, "payee");
			json_int_t amount = json_get_int(json_result, "payee_amount");
			if (payee && amount) {
				available -= amount;
				base58_decode(payee, script_payee);
				job_pack_tx(coind, templ->coinb2, amount, script_payee);
			}
		}
		job_pack_tx(coind, templ->coinb2, available, NULL);
		strcat(templ->coinb2, "00000000"); // locktime

		coind->reward = (double)available / 100000000 * coind->reward_mul;
		return;
	}

	else if(!strcmp(coind->symbol, "GLT")) //multiple payee names
	{
		char script_dests[2048] = { 0 };
		char script_payee[128] = { 0 };
        char script_treasury[128] = { 0 };
		char payees[4];
		int npayees = 1;
		bool masternode_enabled = json_get_bool(json_result, "masternode_payments_enforced");
		json_value* masternode = json_get_object(json_result, "masternode");
        json_value* treasury = json_get_object(json_result, "treasury");
        bool treasury_enabled = true;
		if(treasury_enabled && treasury) {
				const char *scriptPubKey = json_get_string(treasury, "scriptPubKey");
				json_int_t amount = json_get_int(treasury, "amount");
				if (scriptPubKey && amount) {
					npayees++;
					available -= amount;
                    base58_decode(scriptPubKey, script_treasury);
					job_pack_tx(coind, script_dests, amount, script_treasury);
					//debuglog("%s treasury %u\n", coind->symbol, amount);
				}
		}
		if (masternode_enabled && masternode) {
			const char *payee = json_get_string(masternode, "payee");
			json_int_t amount = json_get_int(masternode, "amount");
			if (payee && amount) {
				npayees++;
				available -= amount;
				base58_decode(payee, script_payee);
				job_pack_tx(coind, script_dests, amount, script_payee);
			}
		}
		sprintf(payees, "%02x", npayees);
		strcat(templ->coinb2, payees);
		strcat(templ->coinb2, script_dests);
		job_pack_tx(coind, templ->coinb2, available, NULL);
		strcat(templ->coinb2, "00000000"); // locktime
		coind->reward = (double)available/100000000*coind->reward_mul;
		//debuglog("%s %d dests %s\n", coind->symbol, npayees, script_dests);
		return;
	}

    else if(!strcmp(coind->symbol, "XDN")) //hardcoded rewards
	{
		// make sure we pay both mn and devops
		bool founder_enabled = json_get_bool(json_result, "founder_reward_enforced");
		bool masternode_enabled = json_get_bool(json_result, "enforce_masternode_payments");
		if (!founder_enabled || !masternode_enabled)
			return;

 			// founder/masternode vars
			char founder_script[1024] = { 0};
			char masternode_script[1024] = { 0};
			char founder_payee[256] = { 0};
			char masternode_payee[256] = { 0};
			json_int_t part_amount = (5000000000);
			json_int_t pool_amount = (5000000000*4);
			json_value* founder = json_get_object(json_result, "founderreward");
			const char *payee1 = json_get_string(json_result, "payee");
			const char *payee2 = json_get_string(founder, "payee");

 			// mn script
			snprintf(masternode_payee, 255, "%s", payee1);
			base58_decode(masternode_payee, masternode_script);
			available -= part_amount;

 			// payee script
			snprintf(founder_payee, 255, "%s", payee2);
			base58_decode(founder_payee, founder_script);
			available -= part_amount;

 			// total outputs
			strcat(templ->coinb2, "03");

 			// pack the tx
			job_pack_tx(coind, templ->coinb2, available, NULL);
			job_pack_tx(coind, templ->coinb2, part_amount, founder_script);
			job_pack_tx(coind, templ->coinb2, part_amount, masternode_script);
			strcat(templ->coinb2, "00000000");
			coind->reward = (double)available/100000000*coind->reward_mul;
			return;
    }

	else if (!strcmp(coind->symbol, "ADVC"))
	{
		char payees[4];
		int npayees = 2;
		char script_dests[4096] = { 0 };

		json_int_t coinbase_value = json_get_int(json_result, "coinbasevalue");
		json_value *developer = json_get_object(json_result, "developer");

		const char *developer_payee = json_get_string(developer, "payee");
		json_int_t developer_amount = json_get_int(developer, "amount");
		json_int_t miner_amount = coinbase_value;


		char developer_script_payee[128] = { 0 };
		base58_decode(developer_payee, developer_script_payee);

		job_pack_tx(coind, script_dests, miner_amount, NULL);
		job_pack_tx(coind, script_dests, developer_amount, developer_script_payee);

		sprintf(payees, "%02x", npayees);
		strcat(templ->coinb2, payees);
		strcat(templ->coinb2, script_dests);
		strcat(templ->coinb2, "00000000");

		if (coinbase_payload && strlen(coinbase_payload) > 0)
		{
   			char coinbase_payload_size[18];
	        ser_compactsize((unsigned int)(strlen(coinbase_payload) >> 1), coinbase_payload_size);
	        strcat(templ->coinb2, coinbase_payload_size);
	        strcat(templ->coinb2, coinbase_payload);
		}

		coind->reward = ((double)(miner_amount + developer_amount)) / 100000000 * coind->reward_mul;
		return;
	}

    else if(!strcmp(coind->symbol, "KCN") || !strcmp(coind->symbol, "LCN"))
	{
		char outputs_count[4] = { 0 };
        char devscript2[256] = { 0 };
        char devscript_len[3] = { 0 };
        unsigned int outputs = 2;
        char dev_amount[32];
        json_value *devreward = json_get_object(json_result, "coinbasedevreward");
        json_int_t devvalue = json_get_int(devreward, "value");
        const char *devscript = json_get_string(devreward, "scriptpubkey");
        encode_tx_value(dev_amount, devvalue);
        snprintf(devscript2, 255, "%s", devscript);
        sprintf(devscript_len, "%02x", strlen(devscript2) >> 1);
        if (templ->has_segwit_txs) outputs++;
        sprintf(outputs_count, "%02x", outputs);
        strcat(templ->coinb2, outputs_count);
        job_pack_tx(coind, templ->coinb2, available, NULL);
        coind->reward = (double)available/100000000*coind->reward_mul;
        strcat(templ->coinb2, dev_amount);
        strcat(templ->coinb2, devscript_len);
        strcat(templ->coinb2, devscript2);
        if (templ->has_segwit_txs) strcat(templ->coinb2, commitment);
        strcat(templ->coinb2, "00000000");
        return;
    }

	//=================================================================================================================
	//If allowed, start 'full auto' processing. Covers old and new masternodes, founder/charity and superblocks. Manual charity % from SQL is enabled.
	//Successfully tested with: ROGU (smartnode+founder), VIVO (new MN), GBX (new MN), FTC (none), PXC (none)
	//Should cover theese: SMLY, DYN, VGC(oldMN), RTM, WATC, BUTK, IDX, ADOT, ENT, ARC, SIN(oldMN). Also (with strstr): IFX, GTM, GWAY, AGM, BMN, CRW

	string scriptName = "script";
	string payeeName = "payee";
	string amountName = "amount";
	string payeeNameOld = "payee";
	string amountNameOld = "payee_amount";

	if (!strcmp(coind->symbol, "TUX")) {
		payeeNameOld = "donation_payee";
		amountNameOld = "donation_amount";
	}

	if (!strcmp(coind->symbol, "XVC")) {
		payeeName = "address";
	}

	struct pay
	{
		const char *name;
		const char *script;
		json_int_t amount {0};
		const char *payee;
	};
	pay paysOldMN;
	paysOldMN.name = "masternode";
	struct enabl {const char *name; bool sw;};
	vector <pay> pays;	
	vector <enabl> enables;
	
	//const char *coinbase_payload;
	for (unsigned int i = 0; i < json_result->u.object.length; i++) {
		/* if (!strcmp(json_result->u.object.values[i].name, "coinbase_payload")) {
			coinbase_payload = json_string_value(json_result->u.object.values[i].value);
			continue;
		} */
			
		 //if (json_result->u.object.values[i].value->type == json_object and json_result->u.object.values[i].value->u.object.length > 0 and json_result->u.object.values[i].value->u.object.length < 4  ) {
		if (json_result->u.object.values[i].value->type == json_object and (json_result->u.object.values[i].value->u.object.length == 3 or json_result->u.object.values[i].value->u.object.length == 2)) {
			json_value* obj = json_result->u.object.values[i].value;
			pay tmpPay;
			for (unsigned int k = 0; k < obj->u.object.length; k++) {
				//debuglog("");
				//if (obj->u.object.values[k].value->type == json_string and !strcmp(obj->u.object.values[k].name, "payee")) {
				if (obj->u.object.values[k].value->type == json_string and strstr(obj->u.object.values[k].name, payeeName.c_str())) {
					tmpPay.payee = obj->u.object.values[k].value->u.string.ptr;
					//tmpPay.payee = json_string_value(obj->u.object.values[k].value);
					continue;
				}
				if (obj->u.object.values[k].value->type == json_string and strstr(obj->u.object.values[k].name, scriptName.c_str())) {
					tmpPay.script = obj->u.object.values[k].value->u.string.ptr;
					continue;
				}
				if (obj->u.object.values[k].value->type == json_integer and strstr(obj->u.object.values[k].name, amountName.c_str())) {
					tmpPay.amount = obj->u.object.values[k].value->u.integer;
				}
			}
			if (tmpPay.amount and (tmpPay.script or tmpPay.payee)) {
				//debuglog("%s -- found pay in object ""%s"" sized %i! payee: %s, script %s, amount %ld\n", coind->symbol, json_result->u.object.values[i].name, obj->u.object.length, tmpPay.payee, tmpPay.script, tmpPay.amount);
				tmpPay.name = json_result->u.object.values[i].name;
				pays.push_back (tmpPay);
			}
			continue;
		}
		//json_int_t amount = json_get_int(masternode->u.array.values[i], "amount");

		//"capabilities" "transactions" "mutable" "rules"
		if (json_result->u.object.values[i].value->type == json_array and json_result->u.object.values[i].value->u.array.length > 0 \
			and strcmp(json_result->u.object.values[i].name, "transactions") and strcmp(json_result->u.object.values[i].name, "capabilities") and strcmp(json_result->u.object.values[i].name, "mutable") and strcmp(json_result->u.object.values[i].name, "rules")) 
		{
			json_value* arr = json_result->u.object.values[i].value;
			// debuglog("Coinbase -- %s arr0!\n", coind->symbol);

			if (!arr) continue;
			// debuglog("Coinbase -- %s arr 1! -- %s\n", coind->symbol, json_result->u.object.values[i].name);
			for (unsigned int j = 0; j < arr->u.array.length; j++) {
				if (arr->u.array.values[j]->type != json_object) continue;
				//pay tmpPay; tmpPay.amount=1;
				// debuglog("Coinbase -- %s arr 2!\n", coind->symbol);
				json_value* obj = arr->u.array.values[j];
				if (!obj or obj->u.object.length > 3 or obj->u.object.length < 2) continue;
				pay tmpPay;
				// debuglog("Coinbase -- %s arr 3!\n", coind->symbol);
				for (unsigned int k = 0; k < obj->u.object.length; k++) {
					if (obj->u.object.values[k].value->type == json_string and strstr(obj->u.object.values[k].name, payeeName.c_str())) {
						tmpPay.payee = obj->u.object.values[k].value->u.string.ptr;
						continue;
					}
					if (obj->u.object.values[k].value->type == json_string and strstr(obj->u.object.values[k].name, scriptName.c_str())) {
						tmpPay.script = obj->u.object.values[k].value->u.string.ptr;
						continue;
					}
					if (obj->u.object.values[k].value->type == json_integer and strstr(obj->u.object.values[k].name, amountName.c_str())) {
						tmpPay.amount = obj->u.object.values[k].value->u.integer;
					}
				}
				if (tmpPay.amount and (tmpPay.script or tmpPay.payee)) {
					//debuglog("%s -- found pay in array ""%s""! payee: %s, script %s, amount %ld\n", coind->symbol, json_result->u.object.values[i].name, tmpPay.payee, tmpPay.script, tmpPay.amount);
					tmpPay.name = json_result->u.object.values[i].name;
					pays.push_back (tmpPay);
				}
			}
			continue;
		}

		if (json_result->u.object.values[i].value->type == json_boolean) {
			enabl tmpEnabl;
			tmpEnabl.name = json_result->u.object.values[i].name;
			tmpEnabl.sw = json_result->u.object.values[i].value->u.boolean;
			enables.push_back(tmpEnabl);
			continue;
		}

		if (json_result->u.object.values[i].value->type == json_string and strstr(json_result->u.object.values[i].name , payeeName.c_str())) {
			paysOldMN.payee = json_result->u.object.values[i].value->u.string.ptr;
			continue;
		}
		if (json_result->u.object.values[i].value->type == json_integer and strstr(json_result->u.object.values[i].name , amountNameOld.c_str())) {
			paysOldMN.amount = json_result->u.object.values[i].value->u.integer;
		}
	}

	if (paysOldMN.amount and (paysOldMN.script or paysOldMN.payee))
		pays.push_back(paysOldMN);

	if (coind->charity_percent > 0 and strlen(coind->charity_address) > 10) {
		pay tmpPay;
		tmpPay.amount = (available * coind->charity_percent) / 100;
		tmpPay.payee = coind->charity_address;
		pays.push_back(tmpPay);
	}

	char payees[4]; // addresses count
	int npayees = (templ->has_segwit_txs) ? 2 : 1;
	char script_dests[4096] = { 0 };

	if (!pays.empty()) {
		for (const auto & p : pays) {
			if (p.script) {
				npayees++;
				available -= p.amount;
				script_pack_tx(coind, script_dests, p.amount, p.script);
			}
			else if (p.payee) {
				char script_payee[128] = { 0 };
				npayees++;
				available -= p.amount;
				base58_decode(p.payee, script_payee);
				job_pack_tx(coind, script_dests, p.amount, script_payee);
			}
		}
	}

	/* if (coind->charity_percent > 0 and strlen(coind->charity_address) > 10) {
		char charity_payee[256] = { 0 };
		char script_payee[128] = { 0 };
		snprintf(charity_payee, 255, "%s", coind->charity_address);
		json_int_t charity_amount = (available * coind->charity_percent) / 100;
		npayees++;
		available -= charity_amount;
		coind->charity_amount = charity_amount;
		base58_decode(charity_payee, script_payee);
		job_pack_tx(coind, script_dests, charity_amount, script_payee);
	} */

	sprintf(payees, "%02x", npayees);
	strcat(templ->coinb2, payees);
	if (templ->has_segwit_txs) strcat(templ->coinb2, commitment);
	if (!pays.empty()) strcat(templ->coinb2, script_dests);
	if (templ->is_p2wpkh) {
		strcpy(templ->coinb2_p2wpkh, templ->coinb2);
		job_pack_tx(coind, templ->coinb2_p2wpkh, available, NULL, (coind->p2wpkh));
	}

	job_pack_tx(coind, templ->coinb2, available, NULL);
	if(strcmp(coind->symbol, "DEM") == 0){if(coind->txmessage){strcat(templ->coinb2, "00");}} //fixes eMark. Also GIO-GravioCoin and FLO-FlorinCoin, but they are pos/dead

	strcat(templ->coinb2, "00000000"); // locktime
	if(!pays.empty() and coinbase_payload and strlen(coinbase_payload) > 0) {
		char coinbase_payload_size[18];
		ser_compactsize((unsigned int)(strlen(coinbase_payload) >> 1), coinbase_payload_size);
		strcat(templ->coinb2, coinbase_payload_size);
		strcat(templ->coinb2, coinbase_payload);
	}
	//debuglog("Coinbase -- %s proc 10!\n", coind->symbol);

	coind->reward = (double)available/100000000*coind->reward_mul;

//	debuglog("coinbase %f\n", coind->reward);
//	debuglog("coinbase %s: version %s, nbits %s, time %s\n", coind->symbol, templ->version, templ->nbits, templ->ntime);
//	debuglog("coinb1 %s\n", templ->coinb1);
//	debuglog("coinb2 %s\n", templ->coinb2);
}
