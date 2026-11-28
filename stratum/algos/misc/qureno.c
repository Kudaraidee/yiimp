#include "qureno.h"
#include "algos/sha2/sha256.h"
#include <stdlib.h>
#include <stdint.h>
#include <string.h>
#include <stdio.h>


#include <third_party/sph/sph_blake.h>
#include <third_party/sph/sph_bmw.h>
#include <third_party/sph/sph_groestl.h>
#include <third_party/sph/sph_jh.h>
#include <third_party/sph/sph_keccak.h>
#include <third_party/sph/sph_skein.h>
#include <third_party/sph/sph_luffa.h>
#include <third_party/sph/sph_cubehash.h>
#include <third_party/sph/sph_shavite.h>
#include <third_party/sph/sph_simd.h>
#include <third_party/sph/sph_echo.h>
#include <third_party/sph/sph_hamsi.h>
#include <third_party/sph/sph_fugue.h>
#include <third_party/sph/sph_shabal.h>
#include <third_party/sph/sph_whirlpool.h>
#include <third_party/sph/sph_sha2.h>
#include <third_party/sph/sph_haval.h>


void qureno_hash(const char* input, char* output, uint32_t len)
{

    uint32_t *work_data = (uint32_t *)input;
    const char hashChar = work_data[17];
    const uint32_t nTime = work_data[17];

    sph_blake512_context     ctx_blake;
    sph_bmw512_context       ctx_bmw;
    sph_groestl512_context   ctx_groestl;
    sph_jh512_context        ctx_jh;
    sph_keccak512_context    ctx_keccak;
    sph_skein512_context     ctx_skein;
    sph_luffa512_context     ctx_luffa;
    sph_cubehash512_context  ctx_cubehash;
    sph_shavite512_context   ctx_shavite;
    sph_simd512_context      ctx_simd;
    sph_echo512_context      ctx_echo;
    sph_hamsi512_context     ctx_hamsi;
    sph_fugue512_context     ctx_fugue;
    sph_shabal512_context    ctx_shabal;
    sph_whirlpool_context    ctx_whirlpool;
    sph_sha512_context       ctx_sha2;
    sph_haval256_5_context   ctx_haval;
    
    //these uint512 in the c++ source of the client are backed by an array of uint32    

    if(nTime >= 1601510400) 
	{
		if(hashChar == 48) 
		{
    			uint32_t hash[34];

   			sph_blake512_init(&ctx_blake);
    			sph_blake512 (&ctx_blake, input, len);
    			sph_blake512_close(&ctx_blake, &hash[0]);

    			sph_bmw512_init(&ctx_bmw);
    			sph_bmw512 (&ctx_bmw, &hash[0], 64);
    			sph_bmw512_close(&ctx_bmw, &hash[1]);

    			sph_groestl512_init(&ctx_groestl);
   			sph_groestl512 (&ctx_groestl, &hash[1], 64);
    			sph_groestl512_close(&ctx_groestl, &hash[2]);

    			sph_skein512_init(&ctx_skein);
    			sph_skein512 (&ctx_skein, &hash[2], 64);
    			sph_skein512_close(&ctx_skein, &hash[3]);

    			sph_jh512_init(&ctx_jh);
    			sph_jh512 (&ctx_jh, &hash[3], 64);
    			sph_jh512_close(&ctx_jh, &hash[4]);

    			sph_keccak512_init(&ctx_keccak);
    			sph_keccak512 (&ctx_keccak, &hash[4], 64);
    			sph_keccak512_close(&ctx_keccak, &hash[5]);

    			sph_luffa512_init(&ctx_luffa);
    			sph_luffa512 (&ctx_luffa, &hash[5], 64);
    			sph_luffa512_close(&ctx_luffa, &hash[6]);

    			sph_cubehash512_init(&ctx_cubehash);
    			sph_cubehash512 (&ctx_cubehash, &hash[6], 64);
    			sph_cubehash512_close(&ctx_cubehash, &hash[7]);

    			sph_shavite512_init(&ctx_shavite);
    			sph_shavite512(&ctx_shavite, &hash[7], 64);
    			sph_shavite512_close(&ctx_shavite, &hash[8]);

    			sph_simd512_init(&ctx_simd);
    			sph_simd512 (&ctx_simd, &hash[8], 64);
    			sph_simd512_close(&ctx_simd, &hash[9]);

    			sph_echo512_init(&ctx_echo);
    			sph_echo512 (&ctx_echo, &hash[9], 64);
    			sph_echo512_close(&ctx_echo, &hash[10]);

    			sph_hamsi512_init(&ctx_hamsi);
    			sph_hamsi512 (&ctx_hamsi, &hash[10], 64);
    			sph_hamsi512_close(&ctx_hamsi, &hash[11]);

    			sph_fugue512_init(&ctx_fugue);
    			sph_fugue512 (&ctx_fugue, &hash[11], 64);
    			sph_fugue512_close(&ctx_fugue, &hash[12]);

    			sph_shabal512_init(&ctx_shabal);
    			sph_shabal512 (&ctx_shabal, &hash[12], 64);
    			sph_shabal512_close(&ctx_shabal, &hash[13]);

    			sph_whirlpool_init(&ctx_whirlpool);
    			sph_whirlpool (&ctx_whirlpool, &hash[13], 64);
    			sph_whirlpool_close(&ctx_whirlpool, &hash[14]);

    			sph_sha512_init(&ctx_sha2);
    			sph_sha512 (&ctx_sha2, &hash[14], 64);
    			sph_sha512_close(&ctx_sha2, &hash[15]);

    			sph_haval256_5_init(&ctx_haval);
    			sph_haval256_5 (&ctx_haval, &hash[15], 64);
    			sph_haval256_5_close(&ctx_haval, &hash[16]);

    			sph_bmw512_init(&ctx_bmw);
    			sph_bmw512 (&ctx_bmw, &hash[16], 64);
    			sph_bmw512_close(&ctx_bmw, &hash[17]);

    			sph_groestl512_init(&ctx_groestl);
    			sph_groestl512 (&ctx_groestl, &hash[17], 64);
    			sph_groestl512_close(&ctx_groestl, &hash[18]);

    			sph_skein512_init(&ctx_skein);
    			sph_skein512 (&ctx_skein, &hash[18], 64);
    			sph_skein512_close(&ctx_skein, &hash[19]);

    			sph_jh512_init(&ctx_jh);
    			sph_jh512 (&ctx_jh, &hash[19], 64);
    			sph_jh512_close(&ctx_jh, &hash[20]);

    			sph_keccak512_init(&ctx_keccak);
    			sph_keccak512 (&ctx_keccak, &hash[20], 64);
    			sph_keccak512_close(&ctx_keccak, &hash[21]);

    			sph_luffa512_init(&ctx_luffa);
    			sph_luffa512 (&ctx_luffa, &hash[21], 64);
    			sph_luffa512_close(&ctx_luffa, &hash[22]);

    			sph_cubehash512_init(&ctx_cubehash);
    			sph_cubehash512 (&ctx_cubehash, &hash[22], 64);
    			sph_cubehash512_close(&ctx_cubehash, &hash[23]);

    			sph_shavite512_init(&ctx_shavite);
    			sph_shavite512(&ctx_shavite, &hash[23], 64);
    			sph_shavite512_close(&ctx_shavite, &hash[24]);

    			sph_simd512_init(&ctx_simd);
    			sph_simd512 (&ctx_simd, &hash[24], 64);
    			sph_simd512_close(&ctx_simd, &hash[25]);

    			sph_echo512_init(&ctx_echo);
    			sph_echo512 (&ctx_echo, &hash[25], 64);
    			sph_echo512_close(&ctx_echo, &hash[26]);

    			sph_hamsi512_init(&ctx_hamsi);
    			sph_hamsi512 (&ctx_hamsi, &hash[26], 64);
    			sph_hamsi512_close(&ctx_hamsi, &hash[27]);

    			sph_fugue512_init(&ctx_fugue);
    			sph_fugue512 (&ctx_fugue, &hash[27], 64);
    			sph_fugue512_close(&ctx_fugue, &hash[28]);

    			sph_shabal512_init(&ctx_shabal);
			sph_shabal512 (&ctx_shabal, &hash[28], 64);
    			sph_shabal512_close(&ctx_shabal, &hash[29]);

    			sph_whirlpool_init(&ctx_whirlpool);
    			sph_whirlpool (&ctx_whirlpool, &hash[29], 64);
    			sph_whirlpool_close(&ctx_whirlpool, &hash[30]);

    			sph_sha512_init(&ctx_sha2);
    			sph_sha512 (&ctx_sha2, &hash[30], 64);
    			sph_sha512_close(&ctx_sha2, &hash[31]);

    			sph_haval256_5_init(&ctx_haval);
    			sph_haval256_5 (&ctx_haval, &hash[31], 64);
    			sph_haval256_5_close(&ctx_haval, &hash[32]);

			sph_cubehash512_init(&ctx_cubehash);
		    	sph_cubehash512 (&ctx_cubehash, &hash[32], 64);
	    		sph_cubehash512_close(&ctx_cubehash, &hash[33]);

    			memcpy(output, &hash[33], 32);

		} 
		else if(hashChar == 49) 
		{
            		uint32_t hash[35];

	    		sph_blake512_init(&ctx_blake);
	    		sph_blake512 (&ctx_blake, input, len);
	    		sph_blake512_close(&ctx_blake, &hash[0]);

			sph_bmw512_init(&ctx_bmw);
	    		sph_bmw512 (&ctx_bmw, (&hash[0]), 64);
	    		sph_bmw512_close(&ctx_bmw, (&hash[1]));

	    		sph_groestl512_init(&ctx_groestl);
	    		sph_groestl512 (&ctx_groestl, (&hash[1]), 64);
	    		sph_groestl512_close(&ctx_groestl, (&hash[2]));

	    		sph_skein512_init(&ctx_skein);
	    		sph_skein512 (&ctx_skein, &hash[2], 64);
	    		sph_skein512_close(&ctx_skein, &hash[3]);

	    		sph_jh512_init(&ctx_jh);
	    		sph_jh512 (&ctx_jh, &hash[3], 64);
	    		sph_jh512_close(&ctx_jh, &hash[4]);

	    		sph_keccak512_init(&ctx_keccak);
	    		sph_keccak512 (&ctx_keccak, &hash[4], 64);
	    		sph_keccak512_close(&ctx_keccak, &hash[5]);

	    		sph_luffa512_init(&ctx_luffa);
	    		sph_luffa512 (&ctx_luffa, &hash[5], 64);
	    		sph_luffa512_close(&ctx_luffa, &hash[6]);

	    		sph_cubehash512_init(&ctx_cubehash);
	    		sph_cubehash512 (&ctx_cubehash, &hash[6], 64);
	    		sph_cubehash512_close(&ctx_cubehash, &hash[7]);

	    		sph_shavite512_init(&ctx_shavite);
	    		sph_shavite512(&ctx_shavite, (&hash[7]), 64);
	    		sph_shavite512_close(&ctx_shavite, (&hash[8]));

	    		sph_simd512_init(&ctx_simd);
	    		sph_simd512 (&ctx_simd, &hash[8], 64);
	    		sph_simd512_close(&ctx_simd, &hash[9]);

	    		sph_echo512_init(&ctx_echo);
	    		sph_echo512 (&ctx_echo, &hash[9], 64);
	    		sph_echo512_close(&ctx_echo, &hash[10]);

	    		sph_hamsi512_init(&ctx_hamsi);
	    		sph_hamsi512 (&ctx_hamsi, &hash[10], 64);
	    		sph_hamsi512_close(&ctx_hamsi, &hash[11]);

	    		sph_fugue512_init(&ctx_fugue);
	    		sph_fugue512 (&ctx_fugue, &hash[11], 64);
	    		sph_fugue512_close(&ctx_fugue, &hash[12]);

	    		sph_shabal512_init(&ctx_shabal);
	    		sph_shabal512 (&ctx_shabal, &hash[12], 64);
	    		sph_shabal512_close(&ctx_shabal, &hash[13]);

	   		sph_whirlpool_init(&ctx_whirlpool);
	    		sph_whirlpool (&ctx_whirlpool, &hash[13], 64);
	    		sph_whirlpool_close(&ctx_whirlpool, &hash[14]);

	    		sph_sha512_init(&ctx_sha2);
	    		sph_sha512 (&ctx_sha2, &hash[14], 64);
	    		sph_sha512_close(&ctx_sha2, &hash[15]);

	    		sph_haval256_5_init(&ctx_haval);
	    		sph_haval256_5 (&ctx_haval, &hash[15], 64);
	    		sph_haval256_5_close(&ctx_haval, &hash[16]);

	    		sph_bmw512_init(&ctx_bmw);
	    		sph_bmw512 (&ctx_bmw, &hash[16], 64);
	    		sph_bmw512_close(&ctx_bmw, &hash[17]);

	    		sph_groestl512_init(&ctx_groestl);
	    		sph_groestl512 (&ctx_groestl, &hash[17], 64);
	    		sph_groestl512_close(&ctx_groestl, &hash[18]);

	    		sph_skein512_init(&ctx_skein);
	    		sph_skein512 (&ctx_skein, &hash[18], 64);
	    		sph_skein512_close(&ctx_skein, &hash[19]);

	    		sph_jh512_init(&ctx_jh);
	    		sph_jh512 (&ctx_jh, &hash[19], 64);
	    		sph_jh512_close(&ctx_jh, &hash[20]);

	    		sph_keccak512_init(&ctx_keccak);
	    		sph_keccak512 (&ctx_keccak, &hash[20], 64);
	    		sph_keccak512_close(&ctx_keccak, &hash[21]);

	    		sph_luffa512_init(&ctx_luffa);
	    		sph_luffa512 (&ctx_luffa, &hash[21], 64);
	    		sph_luffa512_close(&ctx_luffa, &hash[22]);

	    		sph_cubehash512_init(&ctx_cubehash);
	    		sph_cubehash512 (&ctx_cubehash, &hash[22], 64);
	    		sph_cubehash512_close(&ctx_cubehash, &hash[23]);

	    		sph_shavite512_init(&ctx_shavite);
	    		sph_shavite512(&ctx_shavite, &hash[23], 64);
	    		sph_shavite512_close(&ctx_shavite, &hash[24]);

	    		sph_simd512_init(&ctx_simd);
	    		sph_simd512 (&ctx_simd, &hash[24], 64);
	    		sph_simd512_close(&ctx_simd, &hash[25]);

	    		sph_echo512_init(&ctx_echo);
	    		sph_echo512 (&ctx_echo, &hash[25], 64);
	    		sph_echo512_close(&ctx_echo, &hash[26]);

	    		sph_hamsi512_init(&ctx_hamsi);
	    		sph_hamsi512 (&ctx_hamsi, &hash[26], 64);
	    		sph_hamsi512_close(&ctx_hamsi, &hash[27]);

	    		sph_fugue512_init(&ctx_fugue);
	    		sph_fugue512 (&ctx_fugue, &hash[27], 64);
	    		sph_fugue512_close(&ctx_fugue, &hash[28]);

	    		sph_shabal512_init(&ctx_shabal);
	    		sph_shabal512 (&ctx_shabal, &hash[28], 64);
	    		sph_shabal512_close(&ctx_shabal, &hash[29]);

	    		sph_whirlpool_init(&ctx_whirlpool);
	    		sph_whirlpool (&ctx_whirlpool, &hash[29], 64);
	    		sph_whirlpool_close(&ctx_whirlpool, &hash[30]);

	    		sph_sha512_init(&ctx_sha2);
	    		sph_sha512 (&ctx_sha2, &hash[30], 64);
	    		sph_sha512_close(&ctx_sha2, &hash[31]);

	    		sph_haval256_5_init(&ctx_haval);
	    		sph_haval256_5 (&ctx_haval, &hash[31], 64);
	    		sph_haval256_5_close(&ctx_haval, &hash[32]);

	    		sph_shabal512_init(&ctx_shabal);
	    		sph_shabal512 (&ctx_shabal, &hash[32], 64);
	    		sph_shabal512_close(&ctx_shabal, &hash[33]);

	    		sph_whirlpool_init(&ctx_whirlpool);
	    		sph_whirlpool (&ctx_whirlpool, &hash[33], 64);
	    		sph_whirlpool_close(&ctx_whirlpool, &hash[34]);

			memcpy(output, &hash[34], 32);
        	}
		else if(hashChar == 50) 
		{
            		uint32_t hash[36];

	    		sph_blake512_init(&ctx_blake);
	    		sph_blake512 (&ctx_blake, input, len);
	    		sph_blake512_close(&ctx_blake, (&hash[0]));

	    		sph_bmw512_init(&ctx_bmw);
	    		sph_bmw512 (&ctx_bmw, (&hash[0]), 64);
	    		sph_bmw512_close(&ctx_bmw, (&hash[1]));

	    		sph_groestl512_init(&ctx_groestl);
	    		sph_groestl512 (&ctx_groestl, (&hash[1]), 64);
	    		sph_groestl512_close(&ctx_groestl, (&hash[2]));

	    		sph_skein512_init(&ctx_skein);
	    		sph_skein512 (&ctx_skein, (&hash[2]), 64);
	    		sph_skein512_close(&ctx_skein, (&hash[3]));

	    		sph_jh512_init(&ctx_jh);
	    		sph_jh512 (&ctx_jh, (&hash[3]), 64);
			sph_jh512_close(&ctx_jh, (&hash[4]));

	    		sph_keccak512_init(&ctx_keccak);
	    		sph_keccak512 (&ctx_keccak, (&hash[4]), 64);
	    		sph_keccak512_close(&ctx_keccak, (&hash[5]));

	    		sph_luffa512_init(&ctx_luffa);
	    		sph_luffa512 (&ctx_luffa, (&hash[5]), 64);
	    		sph_luffa512_close(&ctx_luffa, (&hash[6]));

	    		sph_cubehash512_init(&ctx_cubehash);
	    		sph_cubehash512 (&ctx_cubehash, (&hash[6]), 64);
	    		sph_cubehash512_close(&ctx_cubehash, (&hash[7]));

	    		sph_shavite512_init(&ctx_shavite);
	    		sph_shavite512(&ctx_shavite, (&hash[7]), 64);
	    		sph_shavite512_close(&ctx_shavite, (&hash[8]));

	    		sph_simd512_init(&ctx_simd);
	    		sph_simd512 (&ctx_simd, (&hash[8]), 64);
	    		sph_simd512_close(&ctx_simd, (&hash[9]));

	    		sph_echo512_init(&ctx_echo);
	    		sph_echo512 (&ctx_echo, (&hash[9]), 64);
	    		sph_echo512_close(&ctx_echo, (&hash[10]));

	    		sph_hamsi512_init(&ctx_hamsi);
	    		sph_hamsi512 (&ctx_hamsi, (&hash[10]), 64);
	    		sph_hamsi512_close(&ctx_hamsi, (&hash[11]));

	    		sph_fugue512_init(&ctx_fugue);
	    		sph_fugue512 (&ctx_fugue, (&hash[11]), 64);
	    		sph_fugue512_close(&ctx_fugue, (&hash[12]));

	    		sph_shabal512_init(&ctx_shabal);
	    		sph_shabal512 (&ctx_shabal, (&hash[12]), 64);
	    		sph_shabal512_close(&ctx_shabal, (&hash[13]));

	    		sph_whirlpool_init(&ctx_whirlpool);
	    		sph_whirlpool (&ctx_whirlpool, (&hash[13]), 64);
	    		sph_whirlpool_close(&ctx_whirlpool, (&hash[14]));

	    		sph_sha512_init(&ctx_sha2);
	    		sph_sha512 (&ctx_sha2, (&hash[14]), 64);
	    		sph_sha512_close(&ctx_sha2, (&hash[15]));

	    		sph_haval256_5_init(&ctx_haval);
	    		sph_haval256_5 (&ctx_haval, (&hash[15]), 64);
	    		sph_haval256_5_close(&ctx_haval, (&hash[16]));

	    		sph_bmw512_init(&ctx_bmw);
	    		sph_bmw512 (&ctx_bmw, (&hash[16]), 64);
	    		sph_bmw512_close(&ctx_bmw, (&hash[17]));

	    		sph_groestl512_init(&ctx_groestl);
	    		sph_groestl512 (&ctx_groestl, (&hash[17]), 64);
	    		sph_groestl512_close(&ctx_groestl, (&hash[18]));

	    		sph_skein512_init(&ctx_skein);
	    		sph_skein512 (&ctx_skein, (&hash[18]), 64);
	    		sph_skein512_close(&ctx_skein, (&hash[19]));

	    		sph_jh512_init(&ctx_jh);
	    		sph_jh512 (&ctx_jh, (&hash[19]), 64);
	    		sph_jh512_close(&ctx_jh, (&hash[20]));

	    		sph_keccak512_init(&ctx_keccak);
	    		sph_keccak512 (&ctx_keccak, (&hash[20]), 64);
	    		sph_keccak512_close(&ctx_keccak, (&hash[21]));

	    		sph_luffa512_init(&ctx_luffa);
	    		sph_luffa512 (&ctx_luffa, (&hash[21]), 64);
	    		sph_luffa512_close(&ctx_luffa, (&hash[22]));

	    		sph_cubehash512_init(&ctx_cubehash);
	    		sph_cubehash512 (&ctx_cubehash, (&hash[22]), 64);
	    		sph_cubehash512_close(&ctx_cubehash, (&hash[23]));

	    		sph_shavite512_init(&ctx_shavite);
	    		sph_shavite512(&ctx_shavite, (&hash[23]), 64);
	    		sph_shavite512_close(&ctx_shavite, (&hash[24]));

	    		sph_simd512_init(&ctx_simd);
	    		sph_simd512 (&ctx_simd, (&hash[24]), 64);
	    		sph_simd512_close(&ctx_simd, (&hash[25]));

	    		sph_echo512_init(&ctx_echo);
	    		sph_echo512 (&ctx_echo, (&hash[25]), 64);
	    		sph_echo512_close(&ctx_echo, (&hash[26]));

	    		sph_hamsi512_init(&ctx_hamsi);
	    		sph_hamsi512 (&ctx_hamsi, (&hash[26]), 64);
	    		sph_hamsi512_close(&ctx_hamsi, (&hash[27]));

	    		sph_fugue512_init(&ctx_fugue);
	    		sph_fugue512 (&ctx_fugue, (&hash[27]), 64);
	    		sph_fugue512_close(&ctx_fugue, (&hash[28]));

	    		sph_shabal512_init(&ctx_shabal);
	    		sph_shabal512 (&ctx_shabal, (&hash[28]), 64);
	    		sph_shabal512_close(&ctx_shabal, (&hash[29]));

	    		sph_whirlpool_init(&ctx_whirlpool);
	    		sph_whirlpool (&ctx_whirlpool, (&hash[29]), 64);
	    		sph_whirlpool_close(&ctx_whirlpool, (&hash[30]));

	    		sph_sha512_init(&ctx_sha2);
	    		sph_sha512 (&ctx_sha2, (&hash[30]), 64);
	    		sph_sha512_close(&ctx_sha2, (&hash[31]));

	    		sph_haval256_5_init(&ctx_haval);
	    		sph_haval256_5 (&ctx_haval, (&hash[31]), 64);
	    		sph_haval256_5_close(&ctx_haval, (&hash[32]));

	    		sph_simd512_init(&ctx_simd);
	    		sph_simd512 (&ctx_simd, (&hash[32]), 64);
	    		sph_simd512_close(&ctx_simd, (&hash[33]));

	    		sph_echo512_init(&ctx_echo);
	    		sph_echo512 (&ctx_echo, (&hash[33]), 64);
	    		sph_echo512_close(&ctx_echo, (&hash[34]));

	    		sph_hamsi512_init(&ctx_hamsi);
	    		sph_hamsi512 (&ctx_hamsi, (&hash[34]), 64);
	    		sph_hamsi512_close(&ctx_hamsi, (&hash[35]));

			memcpy(output, &hash[35], 32);
            	}
		else if(hashChar == 51) 
		{
            		uint32_t hash[37];

	    		sph_blake512_init(&ctx_blake);
	    		sph_blake512 (&ctx_blake, input, len);
	    		sph_blake512_close(&ctx_blake, (&hash[0]));

	    		sph_bmw512_init(&ctx_bmw);
	    		sph_bmw512 (&ctx_bmw, (&hash[0]), 64);
	    		sph_bmw512_close(&ctx_bmw, (&hash[1]));

	    		sph_groestl512_init(&ctx_groestl);
	    		sph_groestl512 (&ctx_groestl, (&hash[1]), 64);
	    		sph_groestl512_close(&ctx_groestl, (&hash[2]));

	    		sph_skein512_init(&ctx_skein);
	    		sph_skein512 (&ctx_skein, (&hash[2]), 64);
	    		sph_skein512_close(&ctx_skein, (&hash[3]));

	    		sph_jh512_init(&ctx_jh);
	    		sph_jh512 (&ctx_jh, (&hash[3]), 64);
	    		sph_jh512_close(&ctx_jh, (&hash[4]));

	    		sph_keccak512_init(&ctx_keccak);
	    		sph_keccak512 (&ctx_keccak, (&hash[4]), 64);
	    		sph_keccak512_close(&ctx_keccak, (&hash[5]));

	    		sph_luffa512_init(&ctx_luffa);
	    		sph_luffa512 (&ctx_luffa, (&hash[5]), 64);
	    		sph_luffa512_close(&ctx_luffa, (&hash[6]));

	    		sph_cubehash512_init(&ctx_cubehash);
	    		sph_cubehash512 (&ctx_cubehash, (&hash[6]), 64);
	    		sph_cubehash512_close(&ctx_cubehash, (&hash[7]));

	    		sph_shavite512_init(&ctx_shavite);
	    		sph_shavite512(&ctx_shavite, (&hash[7]), 64);
	    		sph_shavite512_close(&ctx_shavite, (&hash[8]));

	    		sph_simd512_init(&ctx_simd);
	    		sph_simd512 (&ctx_simd, (&hash[8]), 64);
	    		sph_simd512_close(&ctx_simd, (&hash[9]));

	    		sph_echo512_init(&ctx_echo);
	    		sph_echo512 (&ctx_echo, (&hash[9]), 64);
	    		sph_echo512_close(&ctx_echo, (&hash[10]));

	    		sph_hamsi512_init(&ctx_hamsi);
	    		sph_hamsi512 (&ctx_hamsi, (&hash[10]), 64);
	    		sph_hamsi512_close(&ctx_hamsi, (&hash[11]));

	    		sph_fugue512_init(&ctx_fugue);
	    		sph_fugue512 (&ctx_fugue, (&hash[11]), 64);
	    		sph_fugue512_close(&ctx_fugue, (&hash[12]));

	    		sph_shabal512_init(&ctx_shabal);
	    		sph_shabal512 (&ctx_shabal, (&hash[12]), 64);
	    		sph_shabal512_close(&ctx_shabal, (&hash[13]));
	    
			sph_whirlpool_init(&ctx_whirlpool);
	    		sph_whirlpool (&ctx_whirlpool, (&hash[13]), 64);
	   	 	sph_whirlpool_close(&ctx_whirlpool, (&hash[14]));

	    		sph_sha512_init(&ctx_sha2);
	    		sph_sha512 (&ctx_sha2, (&hash[14]), 64);
	    		sph_sha512_close(&ctx_sha2, (&hash[15]));

	    		sph_haval256_5_init(&ctx_haval);
	    		sph_haval256_5 (&ctx_haval, (&hash[15]), 64);
	    		sph_haval256_5_close(&ctx_haval, (&hash[16]));

	    		sph_bmw512_init(&ctx_bmw);
	    		sph_bmw512 (&ctx_bmw, (&hash[16]), 64);
	    		sph_bmw512_close(&ctx_bmw, (&hash[17]));

	    		sph_groestl512_init(&ctx_groestl);
	    		sph_groestl512 (&ctx_groestl, (&hash[17]), 64);
	    		sph_groestl512_close(&ctx_groestl, (&hash[18]));

	    		sph_skein512_init(&ctx_skein);
	    		sph_skein512 (&ctx_skein, (&hash[18]), 64);
	    		sph_skein512_close(&ctx_skein, (&hash[19]));

	    		sph_jh512_init(&ctx_jh);
	    		sph_jh512 (&ctx_jh, (&hash[19]), 64);
	    		sph_jh512_close(&ctx_jh, (&hash[20]));

	    		sph_keccak512_init(&ctx_keccak);
	    		sph_keccak512 (&ctx_keccak, (&hash[20]), 64);
	    		sph_keccak512_close(&ctx_keccak, (&hash[21]));

	    		sph_luffa512_init(&ctx_luffa);
	    		sph_luffa512 (&ctx_luffa, (&hash[21]), 64);
	    		sph_luffa512_close(&ctx_luffa, (&hash[22]));

	    		sph_cubehash512_init(&ctx_cubehash);
	    		sph_cubehash512 (&ctx_cubehash, (&hash[22]), 64);
	    		sph_cubehash512_close(&ctx_cubehash, (&hash[23]));

	    sph_shavite512_init(&ctx_shavite);
	    sph_shavite512(&ctx_shavite, (&hash[23]), 64);
	    sph_shavite512_close(&ctx_shavite, (&hash[24]));
	    sph_simd512_init(&ctx_simd);
	    sph_simd512 (&ctx_simd, (&hash[24]), 64);
	    sph_simd512_close(&ctx_simd, (&hash[25]));
	    sph_echo512_init(&ctx_echo);
	    sph_echo512 (&ctx_echo, (&hash[25]), 64);
	    sph_echo512_close(&ctx_echo, (&hash[26]));
	    sph_hamsi512_init(&ctx_hamsi);
	    sph_hamsi512 (&ctx_hamsi, (&hash[26]), 64);
	    sph_hamsi512_close(&ctx_hamsi, (&hash[27]));
	    sph_fugue512_init(&ctx_fugue);
	    sph_fugue512 (&ctx_fugue, (&hash[27]), 64);
	    sph_fugue512_close(&ctx_fugue, (&hash[28]));
	    sph_shabal512_init(&ctx_shabal);
	    sph_shabal512 (&ctx_shabal, (&hash[28]), 64);
	    sph_shabal512_close(&ctx_shabal, (&hash[29]));
	    sph_whirlpool_init(&ctx_whirlpool);
	    sph_whirlpool (&ctx_whirlpool, (&hash[29]), 64);
	    sph_whirlpool_close(&ctx_whirlpool, (&hash[30]));
	    sph_sha512_init(&ctx_sha2);
	    sph_sha512 (&ctx_sha2, (&hash[30]), 64);
	    sph_sha512_close(&ctx_sha2, (&hash[31]));
	    sph_haval256_5_init(&ctx_haval);
	    sph_haval256_5 (&ctx_haval, (&hash[31]), 64);
	    		sph_haval256_5_close(&ctx_haval, (&hash[32]));
	    		sph_bmw512_init(&ctx_bmw);
	    		sph_bmw512 (&ctx_bmw, (&hash[32]), 64);
	    		sph_bmw512_close(&ctx_bmw, (&hash[33]));
	    		sph_groestl512_init(&ctx_groestl);
	    		sph_groestl512 (&ctx_groestl, (&hash[33]), 64);
	    		sph_groestl512_close(&ctx_groestl, (&hash[34]));
	    		sph_skein512_init(&ctx_skein);
	    		sph_skein512 (&ctx_skein, (&hash[34]), 64);
	    		sph_skein512_close(&ctx_skein, (&hash[35]));
	    		sph_jh512_init(&ctx_jh);
	    		sph_jh512 (&ctx_jh, (&hash[35]), 64);
	    		sph_jh512_close(&ctx_jh, (&hash[36]));
            		memcpy(output, &hash[36], 32);
		} 
		else if(hashChar == 52) 
		{
            		uint32_t hash[38];

	    		sph_blake512_init(&ctx_blake);
	    		sph_blake512 (&ctx_blake, input, len);
	    		sph_blake512_close(&ctx_blake, (&hash[0]));

	    		sph_bmw512_init(&ctx_bmw);
	    		sph_bmw512 (&ctx_bmw, (&hash[0]), 64);
	    		sph_bmw512_close(&ctx_bmw, (&hash[1]));
	    sph_groestl512_init(&ctx_groestl);
	    sph_groestl512 (&ctx_groestl, (&hash[1]), 64);
	    sph_groestl512_close(&ctx_groestl, (&hash[2]));
	    sph_skein512_init(&ctx_skein);
	    sph_skein512 (&ctx_skein, (&hash[2]), 64);
	    sph_skein512_close(&ctx_skein, (&hash[3]));
	    sph_jh512_init(&ctx_jh);
	    sph_jh512 (&ctx_jh, (&hash[3]), 64);
	    sph_jh512_close(&ctx_jh, (&hash[4]));
	    sph_keccak512_init(&ctx_keccak);
	    sph_keccak512 (&ctx_keccak, (&hash[4]), 64);
	    sph_keccak512_close(&ctx_keccak, (&hash[5]));
	    sph_luffa512_init(&ctx_luffa);
	    sph_luffa512 (&ctx_luffa, (&hash[5]), 64);
	    sph_luffa512_close(&ctx_luffa, (&hash[6]));
	    sph_cubehash512_init(&ctx_cubehash);
	    sph_cubehash512 (&ctx_cubehash, (&hash[6]), 64);
	    sph_cubehash512_close(&ctx_cubehash, (&hash[7]));
	    sph_shavite512_init(&ctx_shavite);
	    sph_shavite512(&ctx_shavite, (&hash[7]), 64);
	    sph_shavite512_close(&ctx_shavite, (&hash[8]));
	    sph_simd512_init(&ctx_simd);
	    sph_simd512 (&ctx_simd, (&hash[8]), 64);
	    sph_simd512_close(&ctx_simd, (&hash[9]));
	    sph_echo512_init(&ctx_echo);
	    sph_echo512 (&ctx_echo, (&hash[9]), 64);
	    sph_echo512_close(&ctx_echo, (&hash[10]));
	    sph_hamsi512_init(&ctx_hamsi);
	    sph_hamsi512 (&ctx_hamsi, (&hash[10]), 64);
	    sph_hamsi512_close(&ctx_hamsi, (&hash[11]));
	    sph_fugue512_init(&ctx_fugue);
	    sph_fugue512 (&ctx_fugue, (&hash[11]), 64);
	    sph_fugue512_close(&ctx_fugue, (&hash[12]));
	    sph_shabal512_init(&ctx_shabal);
	    sph_shabal512 (&ctx_shabal, (&hash[12]), 64);
	    sph_shabal512_close(&ctx_shabal, (&hash[13]));
	    sph_whirlpool_init(&ctx_whirlpool);
	    sph_whirlpool (&ctx_whirlpool, (&hash[13]), 64);
	    sph_whirlpool_close(&ctx_whirlpool, (&hash[14]));
	    sph_sha512_init(&ctx_sha2);
	    sph_sha512 (&ctx_sha2, (&hash[14]), 64);
	    sph_sha512_close(&ctx_sha2, (&hash[15]));
	    sph_haval256_5_init(&ctx_haval);
	    sph_haval256_5 (&ctx_haval, (&hash[15]), 64);
	    sph_haval256_5_close(&ctx_haval, (&hash[16]));
	    sph_bmw512_init(&ctx_bmw);
	    sph_bmw512 (&ctx_bmw, (&hash[16]), 64);
	    sph_bmw512_close(&ctx_bmw, (&hash[17]));
	    sph_groestl512_init(&ctx_groestl);
	    sph_groestl512 (&ctx_groestl, (&hash[17]), 64);
	    sph_groestl512_close(&ctx_groestl, (&hash[18]));
	    sph_skein512_init(&ctx_skein);
	    sph_skein512 (&ctx_skein, (&hash[18]), 64);
	    sph_skein512_close(&ctx_skein, (&hash[19]));
	    sph_jh512_init(&ctx_jh);
	    sph_jh512 (&ctx_jh, (&hash[19]), 64);
	    sph_jh512_close(&ctx_jh, (&hash[20]));
	    sph_keccak512_init(&ctx_keccak);
	    sph_keccak512 (&ctx_keccak, (&hash[20]), 64);
	    sph_keccak512_close(&ctx_keccak, (&hash[21]));
	    sph_luffa512_init(&ctx_luffa);
	    sph_luffa512 (&ctx_luffa, (&hash[21]), 64);
	    sph_luffa512_close(&ctx_luffa, (&hash[22]));
	    sph_cubehash512_init(&ctx_cubehash);
	    sph_cubehash512 (&ctx_cubehash, (&hash[22]), 64);
	    sph_cubehash512_close(&ctx_cubehash, (&hash[23]));
	    sph_shavite512_init(&ctx_shavite);
	    sph_shavite512(&ctx_shavite, (&hash[23]), 64);
	    sph_shavite512_close(&ctx_shavite, (&hash[24]));
	    sph_simd512_init(&ctx_simd);
	    sph_simd512 (&ctx_simd, (&hash[24]), 64);
	    sph_simd512_close(&ctx_simd, (&hash[25]));
	    sph_echo512_init(&ctx_echo);
	    sph_echo512 (&ctx_echo, (&hash[25]), 64);
	    sph_echo512_close(&ctx_echo, (&hash[26]));
	    sph_hamsi512_init(&ctx_hamsi);
	    sph_hamsi512 (&ctx_hamsi, (&hash[26]), 64);
	    sph_hamsi512_close(&ctx_hamsi, (&hash[27]));
	    sph_fugue512_init(&ctx_fugue);
	    sph_fugue512 (&ctx_fugue, (&hash[27]), 64);
	    sph_fugue512_close(&ctx_fugue, (&hash[28]));
	    sph_shabal512_init(&ctx_shabal);
	    sph_shabal512 (&ctx_shabal, (&hash[28]), 64);
	    sph_shabal512_close(&ctx_shabal, (&hash[29]));
	    sph_whirlpool_init(&ctx_whirlpool);
	    sph_whirlpool (&ctx_whirlpool, (&hash[29]), 64);
	    sph_whirlpool_close(&ctx_whirlpool, (&hash[30]));
	    sph_sha512_init(&ctx_sha2);
	    sph_sha512 (&ctx_sha2, (&hash[30]), 64);
	    sph_sha512_close(&ctx_sha2, (&hash[31]));
	    sph_haval256_5_init(&ctx_haval);
	    sph_haval256_5 (&ctx_haval, (&hash[31]), 64);
	    sph_haval256_5_close(&ctx_haval, (&hash[32]));
	    sph_sha512_init(&ctx_sha2);
	    sph_sha512 (&ctx_sha2, (&hash[32]), 64);
	    sph_sha512_close(&ctx_sha2, (&hash[33]));
	    sph_haval256_5_init(&ctx_haval);
	    sph_haval256_5 (&ctx_haval, (&hash[33]), 64);
	    sph_haval256_5_close(&ctx_haval, (&hash[34]));
	    sph_bmw512_init(&ctx_bmw);
	    sph_bmw512 (&ctx_bmw, (&hash[34]), 64);
	    		sph_bmw512_close(&ctx_bmw, (&hash[35]));
	    		sph_groestl512_init(&ctx_groestl);
	    		sph_groestl512 (&ctx_groestl, (&hash[35]), 64);
	    		sph_groestl512_close(&ctx_groestl, (&hash[36]));
	    		sph_skein512_init(&ctx_skein);
	    		sph_skein512 (&ctx_skein, (&hash[36]), 64);
	    		sph_skein512_close(&ctx_skein, (&hash[37]));
	    		memcpy(output, &hash[37], 32);
		} 
		else if(hashChar == 53) 
		{
            		uint32_t hash[39];
	    		sph_blake512_init(&ctx_blake);
	    		sph_blake512 (&ctx_blake, input, len);
	    		sph_blake512_close(&ctx_blake, (&hash[0]));
	    sph_bmw512_init(&ctx_bmw);
	    sph_bmw512 (&ctx_bmw, (&hash[0]), 64);
	    sph_bmw512_close(&ctx_bmw, (&hash[1]));
	    sph_groestl512_init(&ctx_groestl);
	    sph_groestl512 (&ctx_groestl, (&hash[1]), 64);
	    sph_groestl512_close(&ctx_groestl, (&hash[2]));
	    sph_skein512_init(&ctx_skein);
	    sph_skein512 (&ctx_skein, (&hash[2]), 64);
	    sph_skein512_close(&ctx_skein, (&hash[3]));
	    sph_jh512_init(&ctx_jh);
	    sph_jh512 (&ctx_jh, (&hash[3]), 64);
	    sph_jh512_close(&ctx_jh, (&hash[4]));
	    sph_keccak512_init(&ctx_keccak);
	    sph_keccak512 (&ctx_keccak, (&hash[4]), 64);
	    sph_keccak512_close(&ctx_keccak, (&hash[5]));
	    sph_luffa512_init(&ctx_luffa);
	    sph_luffa512 (&ctx_luffa, (&hash[5]), 64);
	    sph_luffa512_close(&ctx_luffa, (&hash[6]));
	    sph_cubehash512_init(&ctx_cubehash);
	    sph_cubehash512 (&ctx_cubehash, (&hash[6]), 64);
	    sph_cubehash512_close(&ctx_cubehash, (&hash[7]));
	    sph_shavite512_init(&ctx_shavite);
	    sph_shavite512(&ctx_shavite, (&hash[7]), 64);
	    sph_shavite512_close(&ctx_shavite, (&hash[8]));
	    sph_simd512_init(&ctx_simd);
	    sph_simd512 (&ctx_simd, (&hash[8]), 64);
	    sph_simd512_close(&ctx_simd, (&hash[9]));
	    sph_echo512_init(&ctx_echo);
	    sph_echo512 (&ctx_echo, (&hash[9]), 64);
	    sph_echo512_close(&ctx_echo, (&hash[10]));
	    sph_hamsi512_init(&ctx_hamsi);
	    sph_hamsi512 (&ctx_hamsi, (&hash[10]), 64);
	    sph_hamsi512_close(&ctx_hamsi, (&hash[11]));
	    sph_fugue512_init(&ctx_fugue);
	    sph_fugue512 (&ctx_fugue, (&hash[11]), 64);
	    sph_fugue512_close(&ctx_fugue, (&hash[12]));
	    sph_shabal512_init(&ctx_shabal);
	    sph_shabal512 (&ctx_shabal, (&hash[12]), 64);
	    sph_shabal512_close(&ctx_shabal, (&hash[13]));
	    sph_whirlpool_init(&ctx_whirlpool);
	    sph_whirlpool (&ctx_whirlpool, (&hash[13]), 64);
	    sph_whirlpool_close(&ctx_whirlpool, (&hash[14]));
	    sph_sha512_init(&ctx_sha2);
	    sph_sha512 (&ctx_sha2, (&hash[14]), 64);
	    sph_sha512_close(&ctx_sha2, (&hash[15]));
	    sph_haval256_5_init(&ctx_haval);
	    sph_haval256_5 (&ctx_haval, (&hash[15]), 64);
	    sph_haval256_5_close(&ctx_haval, (&hash[16]));
	    sph_bmw512_init(&ctx_bmw);
	    sph_bmw512 (&ctx_bmw, (&hash[16]), 64);
	    sph_bmw512_close(&ctx_bmw, (&hash[17]));
	    sph_groestl512_init(&ctx_groestl);
	    sph_groestl512 (&ctx_groestl, (&hash[17]), 64);
	    sph_groestl512_close(&ctx_groestl, (&hash[18]));
	    sph_skein512_init(&ctx_skein);
	    sph_skein512 (&ctx_skein, (&hash[18]), 64);
	    sph_skein512_close(&ctx_skein, (&hash[19]));
	    sph_jh512_init(&ctx_jh);
	    sph_jh512 (&ctx_jh, (&hash[19]), 64);
	    sph_jh512_close(&ctx_jh, (&hash[20]));
	    sph_keccak512_init(&ctx_keccak);
	    sph_keccak512 (&ctx_keccak, (&hash[20]), 64);
	    sph_keccak512_close(&ctx_keccak, (&hash[21]));
	    sph_luffa512_init(&ctx_luffa);
	    sph_luffa512 (&ctx_luffa, (&hash[21]), 64);
	    sph_luffa512_close(&ctx_luffa, (&hash[22]));
	    sph_cubehash512_init(&ctx_cubehash);
	    sph_cubehash512 (&ctx_cubehash, (&hash[22]), 64);
	    sph_cubehash512_close(&ctx_cubehash, (&hash[23]));
	    sph_shavite512_init(&ctx_shavite);
	    sph_shavite512(&ctx_shavite, (&hash[23]), 64);
	    sph_shavite512_close(&ctx_shavite, (&hash[24]));
	    sph_simd512_init(&ctx_simd);
	    sph_simd512 (&ctx_simd, (&hash[24]), 64);
	    sph_simd512_close(&ctx_simd, (&hash[25]));
	    sph_echo512_init(&ctx_echo);
	    sph_echo512 (&ctx_echo, (&hash[25]), 64);
	    sph_echo512_close(&ctx_echo, (&hash[26]));
	    sph_hamsi512_init(&ctx_hamsi);
	    sph_hamsi512 (&ctx_hamsi, (&hash[26]), 64);
	    sph_hamsi512_close(&ctx_hamsi, (&hash[27]));
	    sph_fugue512_init(&ctx_fugue);
	    sph_fugue512 (&ctx_fugue, (&hash[27]), 64);
	    sph_fugue512_close(&ctx_fugue, (&hash[28]));
	    sph_shabal512_init(&ctx_shabal);
	    sph_shabal512 (&ctx_shabal, (&hash[28]), 64);
	    sph_shabal512_close(&ctx_shabal, (&hash[29]));
	    sph_whirlpool_init(&ctx_whirlpool);
	    sph_whirlpool (&ctx_whirlpool, (&hash[29]), 64);
	    sph_whirlpool_close(&ctx_whirlpool, (&hash[30]));
	    sph_sha512_init(&ctx_sha2);
	    sph_sha512 (&ctx_sha2, (&hash[30]), 64);
	    sph_sha512_close(&ctx_sha2, (&hash[31]));
	    sph_haval256_5_init(&ctx_haval);
	    sph_haval256_5 (&ctx_haval, (&hash[31]), 64);
	    sph_haval256_5_close(&ctx_haval, (&hash[32]));
	    sph_jh512_init(&ctx_jh);
	    sph_jh512 (&ctx_jh, (&hash[32]), 64);
	    sph_jh512_close(&ctx_jh, (&hash[33]));
	    sph_keccak512_init(&ctx_keccak);
	    sph_keccak512 (&ctx_keccak, (&hash[33]), 64);
	    sph_keccak512_close(&ctx_keccak, (&hash[34]));
	    sph_luffa512_init(&ctx_luffa);
	    sph_luffa512 (&ctx_luffa, (&hash[34]), 64);
	    sph_luffa512_close(&ctx_luffa, (&hash[35]));
	    sph_cubehash512_init(&ctx_cubehash);
	    sph_cubehash512 (&ctx_cubehash, (&hash[35]), 64);
	    sph_cubehash512_close(&ctx_cubehash, (&hash[36]));
	    sph_shavite512_init(&ctx_shavite);
	    sph_shavite512(&ctx_shavite, (&hash[36]), 64);
	    sph_shavite512_close(&ctx_shavite, (&hash[37]));

	    		sph_simd512_init(&ctx_simd);
	    		sph_simd512 (&ctx_simd, (&hash[37]), 64);
	    		sph_simd512_close(&ctx_simd, (&hash[38]));

	    		memcpy(output, &hash[38], 32);
		} 
		else if(hashChar == 54) 
		{
            		uint32_t hash[40];

	    		sph_blake512_init(&ctx_blake);
	    		sph_blake512 (&ctx_blake, input, len);
	    		sph_blake512_close(&ctx_blake, (&hash[0]));

	    sph_bmw512_init(&ctx_bmw);
	    sph_bmw512 (&ctx_bmw, (&hash[0]), 64);
	    sph_bmw512_close(&ctx_bmw, (&hash[1]));
	    sph_groestl512_init(&ctx_groestl);
	    sph_groestl512 (&ctx_groestl, (&hash[1]), 64);
	    sph_groestl512_close(&ctx_groestl, (&hash[2]));
	    sph_skein512_init(&ctx_skein);
	    sph_skein512 (&ctx_skein, (&hash[2]), 64);
	    sph_skein512_close(&ctx_skein, (&hash[3]));
	    sph_jh512_init(&ctx_jh);
	    sph_jh512 (&ctx_jh, (&hash[3]), 64);
	    sph_jh512_close(&ctx_jh, (&hash[4]));
	    sph_keccak512_init(&ctx_keccak);
	    sph_keccak512 (&ctx_keccak, (&hash[4]), 64);
	    sph_keccak512_close(&ctx_keccak, (&hash[5]));
	    sph_luffa512_init(&ctx_luffa);
	    sph_luffa512 (&ctx_luffa, (&hash[5]), 64);
	    sph_luffa512_close(&ctx_luffa, (&hash[6]));
	    sph_cubehash512_init(&ctx_cubehash);
	    sph_cubehash512 (&ctx_cubehash, (&hash[6]), 64);
	    sph_cubehash512_close(&ctx_cubehash, (&hash[7]));
	    sph_shavite512_init(&ctx_shavite);
	    sph_shavite512(&ctx_shavite, (&hash[7]), 64);
	    sph_shavite512_close(&ctx_shavite, (&hash[8]));
	    sph_simd512_init(&ctx_simd);
	    sph_simd512 (&ctx_simd, (&hash[8]), 64);
	    sph_simd512_close(&ctx_simd, (&hash[9]));
	    sph_echo512_init(&ctx_echo);
	    sph_echo512 (&ctx_echo, (&hash[9]), 64);
	    sph_echo512_close(&ctx_echo, (&hash[10]));
	    sph_hamsi512_init(&ctx_hamsi);
	    sph_hamsi512 (&ctx_hamsi, (&hash[10]), 64);
	    sph_hamsi512_close(&ctx_hamsi, (&hash[11]));
	    sph_fugue512_init(&ctx_fugue);
	    sph_fugue512 (&ctx_fugue, (&hash[11]), 64);
	    sph_fugue512_close(&ctx_fugue, (&hash[12]));
	    sph_shabal512_init(&ctx_shabal);
	    sph_shabal512 (&ctx_shabal, (&hash[12]), 64);
	    sph_shabal512_close(&ctx_shabal, (&hash[13]));
	    sph_whirlpool_init(&ctx_whirlpool);
	    sph_whirlpool (&ctx_whirlpool, (&hash[13]), 64);
	    sph_whirlpool_close(&ctx_whirlpool, (&hash[14]));
	    sph_sha512_init(&ctx_sha2);
	    sph_sha512 (&ctx_sha2, (&hash[14]), 64);
	    sph_sha512_close(&ctx_sha2, (&hash[15]));
	    sph_haval256_5_init(&ctx_haval);
	    sph_haval256_5 (&ctx_haval, (&hash[15]), 64);
	    sph_haval256_5_close(&ctx_haval, (&hash[16]));
	    sph_bmw512_init(&ctx_bmw);
	    sph_bmw512 (&ctx_bmw, (&hash[16]), 64);
	    sph_bmw512_close(&ctx_bmw, (&hash[17]));
	    sph_groestl512_init(&ctx_groestl);
	    sph_groestl512 (&ctx_groestl, (&hash[17]), 64);
	    sph_groestl512_close(&ctx_groestl, (&hash[18]));
	    sph_skein512_init(&ctx_skein);
	    sph_skein512 (&ctx_skein, (&hash[18]), 64);
	    sph_skein512_close(&ctx_skein, (&hash[19]));
	    sph_jh512_init(&ctx_jh);
	    sph_jh512 (&ctx_jh, (&hash[19]), 64);
	    sph_jh512_close(&ctx_jh, (&hash[20]));
	    sph_keccak512_init(&ctx_keccak);
	    sph_keccak512 (&ctx_keccak, (&hash[20]), 64);
	    sph_keccak512_close(&ctx_keccak, (&hash[21]));
	    sph_luffa512_init(&ctx_luffa);
	    sph_luffa512 (&ctx_luffa, (&hash[21]), 64);
	    sph_luffa512_close(&ctx_luffa, (&hash[22]));
	    sph_cubehash512_init(&ctx_cubehash);
	    sph_cubehash512 (&ctx_cubehash, (&hash[22]), 64);
	    sph_cubehash512_close(&ctx_cubehash, (&hash[23]));
	    sph_shavite512_init(&ctx_shavite);
	    sph_shavite512(&ctx_shavite, (&hash[23]), 64);
	    sph_shavite512_close(&ctx_shavite, (&hash[24]));
	    sph_simd512_init(&ctx_simd);
	    sph_simd512 (&ctx_simd, (&hash[24]), 64);
	    sph_simd512_close(&ctx_simd, (&hash[25]));
	    sph_echo512_init(&ctx_echo);
	    sph_echo512 (&ctx_echo, (&hash[25]), 64);
	    sph_echo512_close(&ctx_echo, (&hash[26]));
	    sph_hamsi512_init(&ctx_hamsi);
	    sph_hamsi512 (&ctx_hamsi, (&hash[26]), 64);
	    sph_hamsi512_close(&ctx_hamsi, (&hash[27]));
	    sph_fugue512_init(&ctx_fugue);
	    sph_fugue512 (&ctx_fugue, (&hash[27]), 64);
	    sph_fugue512_close(&ctx_fugue, (&hash[28]));
	    sph_shabal512_init(&ctx_shabal);
	    sph_shabal512 (&ctx_shabal, (&hash[28]), 64);
	    sph_shabal512_close(&ctx_shabal, (&hash[29]));
	    sph_whirlpool_init(&ctx_whirlpool);
	    sph_whirlpool (&ctx_whirlpool, (&hash[29]), 64);
	    sph_whirlpool_close(&ctx_whirlpool, (&hash[30]));
	    sph_sha512_init(&ctx_sha2);
	    sph_sha512 (&ctx_sha2, (&hash[30]), 64);
	    sph_sha512_close(&ctx_sha2, (&hash[31]));
	    sph_haval256_5_init(&ctx_haval);
	    sph_haval256_5 (&ctx_haval, (&hash[31]), 64);
	    sph_haval256_5_close(&ctx_haval, (&hash[32]));
	    sph_shabal512_init(&ctx_shabal);
	    sph_shabal512 (&ctx_shabal, (&hash[32]), 64);
	    sph_shabal512_close(&ctx_shabal, (&hash[33]));
	    sph_whirlpool_init(&ctx_whirlpool);
	    sph_whirlpool (&ctx_whirlpool, (&hash[33]), 64);
	    sph_whirlpool_close(&ctx_whirlpool, (&hash[34]));
	    sph_sha512_init(&ctx_sha2);
	    sph_sha512 (&ctx_sha2, (&hash[34]), 64);
	    sph_sha512_close(&ctx_sha2, (&hash[35]));
	    sph_haval256_5_init(&ctx_haval);
	    sph_haval256_5 (&ctx_haval, (&hash[35]), 64);
	    sph_haval256_5_close(&ctx_haval, (&hash[36]));
	    sph_bmw512_init(&ctx_bmw);
	    sph_bmw512 (&ctx_bmw, (&hash[36]), 64);
	    sph_bmw512_close(&ctx_bmw, (&hash[37]));
	    sph_groestl512_init(&ctx_groestl);
	    sph_groestl512 (&ctx_groestl, (&hash[37]), 64);
	    sph_groestl512_close(&ctx_groestl, (&hash[38]));
	    sph_skein512_init(&ctx_skein);
	    sph_skein512 (&ctx_skein, (&hash[38]), 64);
	    sph_skein512_close(&ctx_skein, (&hash[39]));

	    		memcpy(output, &hash[39], 32);
		} 
		else if(hashChar == 55) 
		{
            		uint32_t hash[41];

	    		sph_blake512_init(&ctx_blake);
	   	 	sph_blake512 (&ctx_blake, input, len);
	    		sph_blake512_close(&ctx_blake, (&hash[0]));

	    sph_bmw512_init(&ctx_bmw);
	    sph_bmw512 (&ctx_bmw, (&hash[0]), 64);
	    sph_bmw512_close(&ctx_bmw, (&hash[1]));
	    sph_groestl512_init(&ctx_groestl);
	    sph_groestl512 (&ctx_groestl, (&hash[1]), 64);
	    sph_groestl512_close(&ctx_groestl, (&hash[2]));
	    sph_skein512_init(&ctx_skein);
	    sph_skein512 (&ctx_skein, (&hash[2]), 64);
	    sph_skein512_close(&ctx_skein, (&hash[3]));
	    sph_jh512_init(&ctx_jh);
	    sph_jh512 (&ctx_jh, (&hash[3]), 64);
	    sph_jh512_close(&ctx_jh, (&hash[4]));
	    sph_keccak512_init(&ctx_keccak);
	    sph_keccak512 (&ctx_keccak, (&hash[4]), 64);
	    sph_keccak512_close(&ctx_keccak, (&hash[5]));
	    sph_luffa512_init(&ctx_luffa);
	    sph_luffa512 (&ctx_luffa, (&hash[5]), 64);
	    sph_luffa512_close(&ctx_luffa, (&hash[6]));
	    sph_cubehash512_init(&ctx_cubehash);
	    sph_cubehash512 (&ctx_cubehash, (&hash[6]), 64);
	    sph_cubehash512_close(&ctx_cubehash, (&hash[7]));
	    sph_shavite512_init(&ctx_shavite);
	    sph_shavite512(&ctx_shavite, (&hash[7]), 64);
	    sph_shavite512_close(&ctx_shavite, (&hash[8]));
	    sph_simd512_init(&ctx_simd);
	    sph_simd512 (&ctx_simd, (&hash[8]), 64);
	    sph_simd512_close(&ctx_simd, (&hash[9]));
	    sph_echo512_init(&ctx_echo);
	    sph_echo512 (&ctx_echo, (&hash[9]), 64);
	    sph_echo512_close(&ctx_echo, (&hash[10]));
	    sph_hamsi512_init(&ctx_hamsi);
	    sph_hamsi512 (&ctx_hamsi, (&hash[10]), 64);
	    sph_hamsi512_close(&ctx_hamsi, (&hash[11]));
	    sph_fugue512_init(&ctx_fugue);
	    sph_fugue512 (&ctx_fugue, (&hash[11]), 64);
	    sph_fugue512_close(&ctx_fugue, (&hash[12]));
	    sph_shabal512_init(&ctx_shabal);
	    sph_shabal512 (&ctx_shabal, (&hash[12]), 64);
	    sph_shabal512_close(&ctx_shabal, (&hash[13]));
	    sph_whirlpool_init(&ctx_whirlpool);
	    sph_whirlpool (&ctx_whirlpool, (&hash[13]), 64);
	    sph_whirlpool_close(&ctx_whirlpool, (&hash[14]));
	    sph_sha512_init(&ctx_sha2);
	    sph_sha512 (&ctx_sha2, (&hash[14]), 64);
	    sph_sha512_close(&ctx_sha2, (&hash[15]));
	    sph_haval256_5_init(&ctx_haval);
	    sph_haval256_5 (&ctx_haval, (&hash[15]), 64);
	    sph_haval256_5_close(&ctx_haval, (&hash[16]));
	    sph_bmw512_init(&ctx_bmw);
	    sph_bmw512 (&ctx_bmw, (&hash[16]), 64);
	    sph_bmw512_close(&ctx_bmw, (&hash[17]));
	    sph_groestl512_init(&ctx_groestl);
	    sph_groestl512 (&ctx_groestl, (&hash[17]), 64);
	    sph_groestl512_close(&ctx_groestl, (&hash[18]));
	    sph_skein512_init(&ctx_skein);
	    sph_skein512 (&ctx_skein, (&hash[18]), 64);
	    sph_skein512_close(&ctx_skein, (&hash[19]));
	    sph_jh512_init(&ctx_jh);
	    sph_jh512 (&ctx_jh, (&hash[19]), 64);
	    sph_jh512_close(&ctx_jh, (&hash[20]));
	    sph_keccak512_init(&ctx_keccak);
	    sph_keccak512 (&ctx_keccak, (&hash[20]), 64);
	    sph_keccak512_close(&ctx_keccak, (&hash[21]));
	    sph_luffa512_init(&ctx_luffa);
	    sph_luffa512 (&ctx_luffa, (&hash[21]), 64);
	    sph_luffa512_close(&ctx_luffa, (&hash[22]));
	    sph_cubehash512_init(&ctx_cubehash);
	    sph_cubehash512 (&ctx_cubehash, (&hash[22]), 64);
	    sph_cubehash512_close(&ctx_cubehash, (&hash[23]));
	    sph_shavite512_init(&ctx_shavite);
	    sph_shavite512(&ctx_shavite, (&hash[23]), 64);
	    sph_shavite512_close(&ctx_shavite, (&hash[24]));
	    sph_simd512_init(&ctx_simd);
	    sph_simd512 (&ctx_simd, (&hash[24]), 64);
	    sph_simd512_close(&ctx_simd, (&hash[25]));
	    sph_echo512_init(&ctx_echo);
	    sph_echo512 (&ctx_echo, (&hash[25]), 64);
	    sph_echo512_close(&ctx_echo, (&hash[26]));
	    sph_hamsi512_init(&ctx_hamsi);
	    sph_hamsi512 (&ctx_hamsi, (&hash[26]), 64);
	    sph_hamsi512_close(&ctx_hamsi, (&hash[27]));
	    sph_fugue512_init(&ctx_fugue);
	    sph_fugue512 (&ctx_fugue, (&hash[27]), 64);
	    sph_fugue512_close(&ctx_fugue, (&hash[28]));
	    sph_shabal512_init(&ctx_shabal);
	    sph_shabal512 (&ctx_shabal, (&hash[28]), 64);
	    sph_shabal512_close(&ctx_shabal, (&hash[29]));
	    sph_whirlpool_init(&ctx_whirlpool);
	    sph_whirlpool (&ctx_whirlpool, (&hash[29]), 64);
	    sph_whirlpool_close(&ctx_whirlpool, (&hash[30]));
	    sph_sha512_init(&ctx_sha2);
	    sph_sha512 (&ctx_sha2, (&hash[30]), 64);
	    sph_sha512_close(&ctx_sha2, (&hash[31]));
	    sph_haval256_5_init(&ctx_haval);
	    sph_haval256_5 (&ctx_haval, (&hash[31]), 64);
	    sph_haval256_5_close(&ctx_haval, (&hash[32]));
	    sph_bmw512_init(&ctx_bmw);
	    sph_bmw512 (&ctx_bmw, (&hash[32]), 64);
	    sph_bmw512_close(&ctx_bmw, (&hash[33]));
	    sph_groestl512_init(&ctx_groestl);
	    sph_groestl512 (&ctx_groestl, (&hash[33]), 64);
	    sph_groestl512_close(&ctx_groestl, (&hash[34]));
	    sph_skein512_init(&ctx_skein);
	    sph_skein512 (&ctx_skein, (&hash[34]), 64);
	    sph_skein512_close(&ctx_skein, (&hash[35]));
	    sph_jh512_init(&ctx_jh);
	    sph_jh512 (&ctx_jh, (&hash[35]), 64);
	    sph_jh512_close(&ctx_jh, (&hash[36]));
	    sph_keccak512_init(&ctx_keccak);
	    sph_keccak512 (&ctx_keccak, (&hash[36]), 64);
	    sph_keccak512_close(&ctx_keccak, (&hash[37]));
	    sph_luffa512_init(&ctx_luffa);
	    sph_luffa512 (&ctx_luffa, (&hash[37]), 64);
	    sph_luffa512_close(&ctx_luffa, (&hash[38]));
	    sph_cubehash512_init(&ctx_cubehash);
	    sph_cubehash512 (&ctx_cubehash, (&hash[38]), 64);
	    sph_cubehash512_close(&ctx_cubehash, (&hash[39]));
	    sph_shavite512_init(&ctx_shavite);
	    sph_shavite512(&ctx_shavite, (&hash[39]), 64);
	    sph_shavite512_close(&ctx_shavite, (&hash[40]));
	    memcpy(output, &hash[40], 32);
	} else if(hashChar == 56) {
            uint32_t hash[42];
	    sph_blake512_init(&ctx_blake);
	    sph_blake512 (&ctx_blake, input, len);
	    sph_blake512_close(&ctx_blake, (&hash[0]));
	    sph_bmw512_init(&ctx_bmw);
	    sph_bmw512 (&ctx_bmw, (&hash[0]), 64);
	    sph_bmw512_close(&ctx_bmw, (&hash[1]));
	    sph_groestl512_init(&ctx_groestl);
	    sph_groestl512 (&ctx_groestl, (&hash[1]), 64);
	    sph_groestl512_close(&ctx_groestl, (&hash[2]));
	    sph_skein512_init(&ctx_skein);
	    sph_skein512 (&ctx_skein, (&hash[2]), 64);
	    sph_skein512_close(&ctx_skein, (&hash[3]));
	    sph_jh512_init(&ctx_jh);
	    sph_jh512 (&ctx_jh, (&hash[3]), 64);
	    sph_jh512_close(&ctx_jh, (&hash[4]));
	    sph_keccak512_init(&ctx_keccak);
	    sph_keccak512 (&ctx_keccak, (&hash[4]), 64);
	    sph_keccak512_close(&ctx_keccak, (&hash[5]));
	    sph_luffa512_init(&ctx_luffa);
	    sph_luffa512 (&ctx_luffa, (&hash[5]), 64);
	    sph_luffa512_close(&ctx_luffa, (&hash[6]));
	    sph_cubehash512_init(&ctx_cubehash);
	    sph_cubehash512 (&ctx_cubehash, (&hash[6]), 64);
	    sph_cubehash512_close(&ctx_cubehash, (&hash[7]));
	    sph_shavite512_init(&ctx_shavite);
	    sph_shavite512(&ctx_shavite, (&hash[7]), 64);
	    sph_shavite512_close(&ctx_shavite, (&hash[8]));
	    sph_simd512_init(&ctx_simd);
	    sph_simd512 (&ctx_simd, (&hash[8]), 64);
	    sph_simd512_close(&ctx_simd, (&hash[9]));
	    sph_echo512_init(&ctx_echo);
	    sph_echo512 (&ctx_echo, (&hash[9]), 64);
	    sph_echo512_close(&ctx_echo, (&hash[10]));
	    sph_hamsi512_init(&ctx_hamsi);
	    sph_hamsi512 (&ctx_hamsi, (&hash[10]), 64);
	    sph_hamsi512_close(&ctx_hamsi, (&hash[11]));
	    sph_fugue512_init(&ctx_fugue);
	    sph_fugue512 (&ctx_fugue, (&hash[11]), 64);
	    sph_fugue512_close(&ctx_fugue, (&hash[12]));
	    sph_shabal512_init(&ctx_shabal);
	    sph_shabal512 (&ctx_shabal, (&hash[12]), 64);
	    sph_shabal512_close(&ctx_shabal, (&hash[13]));
	    sph_whirlpool_init(&ctx_whirlpool);
	    sph_whirlpool (&ctx_whirlpool, (&hash[13]), 64);
	    sph_whirlpool_close(&ctx_whirlpool, (&hash[14]));
	    sph_sha512_init(&ctx_sha2);
	    sph_sha512 (&ctx_sha2, (&hash[14]), 64);
	    sph_sha512_close(&ctx_sha2, (&hash[15]));
	    sph_haval256_5_init(&ctx_haval);
	    sph_haval256_5 (&ctx_haval, (&hash[15]), 64);
	    sph_haval256_5_close(&ctx_haval, (&hash[16]));
	    sph_bmw512_init(&ctx_bmw);
	    sph_bmw512 (&ctx_bmw, (&hash[16]), 64);
	    sph_bmw512_close(&ctx_bmw, (&hash[17]));
	    sph_groestl512_init(&ctx_groestl);
	    sph_groestl512 (&ctx_groestl, (&hash[17]), 64);
	    sph_groestl512_close(&ctx_groestl, (&hash[18]));
	    sph_skein512_init(&ctx_skein);
	    sph_skein512 (&ctx_skein, (&hash[18]), 64);
	    sph_skein512_close(&ctx_skein, (&hash[19]));
	    sph_jh512_init(&ctx_jh);
	    sph_jh512 (&ctx_jh, (&hash[19]), 64);
	    sph_jh512_close(&ctx_jh, (&hash[20]));
	    sph_keccak512_init(&ctx_keccak);
	    sph_keccak512 (&ctx_keccak, (&hash[20]), 64);
	    sph_keccak512_close(&ctx_keccak, (&hash[21]));
	    sph_luffa512_init(&ctx_luffa);
	    sph_luffa512 (&ctx_luffa, (&hash[21]), 64);
	    sph_luffa512_close(&ctx_luffa, (&hash[22]));
	    sph_cubehash512_init(&ctx_cubehash);
	    sph_cubehash512 (&ctx_cubehash, (&hash[22]), 64);
	    sph_cubehash512_close(&ctx_cubehash, (&hash[23]));
	    sph_shavite512_init(&ctx_shavite);
	    sph_shavite512(&ctx_shavite, (&hash[23]), 64);
	    sph_shavite512_close(&ctx_shavite, (&hash[24]));
	    sph_simd512_init(&ctx_simd);
	    sph_simd512 (&ctx_simd, (&hash[24]), 64);
	    sph_simd512_close(&ctx_simd, (&hash[25]));
	    sph_echo512_init(&ctx_echo);
	    sph_echo512 (&ctx_echo, (&hash[25]), 64);
	    sph_echo512_close(&ctx_echo, (&hash[26]));
	    sph_hamsi512_init(&ctx_hamsi);
	    sph_hamsi512 (&ctx_hamsi, (&hash[26]), 64);
	    sph_hamsi512_close(&ctx_hamsi, (&hash[27]));
	    sph_fugue512_init(&ctx_fugue);
	    sph_fugue512 (&ctx_fugue, (&hash[27]), 64);
	    sph_fugue512_close(&ctx_fugue, (&hash[28]));
	    sph_shabal512_init(&ctx_shabal);
	    sph_shabal512 (&ctx_shabal, (&hash[28]), 64);
	    sph_shabal512_close(&ctx_shabal, (&hash[29]));
	    sph_whirlpool_init(&ctx_whirlpool);
	    sph_whirlpool (&ctx_whirlpool, (&hash[29]), 64);
	    sph_whirlpool_close(&ctx_whirlpool, (&hash[30]));
	    sph_sha512_init(&ctx_sha2);
	    sph_sha512 (&ctx_sha2, (&hash[30]), 64);
	    sph_sha512_close(&ctx_sha2, (&hash[31]));
	    sph_haval256_5_init(&ctx_haval);
	    sph_haval256_5 (&ctx_haval, (&hash[31]), 64);
	    sph_haval256_5_close(&ctx_haval, (&hash[32]));
	    sph_shavite512_init(&ctx_shavite);
	    sph_shavite512(&ctx_shavite, (&hash[32]), 64);
	    sph_shavite512_close(&ctx_shavite, (&hash[33]));
	    sph_simd512_init(&ctx_simd);
	    sph_simd512 (&ctx_simd, (&hash[33]), 64);
	    sph_simd512_close(&ctx_simd, (&hash[34]));
	    sph_echo512_init(&ctx_echo);
	    sph_echo512 (&ctx_echo, (&hash[34]), 64);
	    sph_echo512_close(&ctx_echo, (&hash[35]));
	    sph_hamsi512_init(&ctx_hamsi);
	    sph_hamsi512 (&ctx_hamsi, (&hash[35]), 64);
	    sph_hamsi512_close(&ctx_hamsi, (&hash[36]));
	    sph_fugue512_init(&ctx_fugue);
	    sph_fugue512 (&ctx_fugue, (&hash[36]), 64);
	    sph_fugue512_close(&ctx_fugue, (&hash[37]));
	    sph_shabal512_init(&ctx_shabal);
	    sph_shabal512 (&ctx_shabal, (&hash[37]), 64);
	    sph_shabal512_close(&ctx_shabal, (&hash[38]));
	    sph_whirlpool_init(&ctx_whirlpool);
	    sph_whirlpool (&ctx_whirlpool, (&hash[38]), 64);
	    sph_whirlpool_close(&ctx_whirlpool, (&hash[39]));
	    sph_sha512_init(&ctx_sha2);
	    sph_sha512 (&ctx_sha2, (&hash[39]), 64);
	    sph_sha512_close(&ctx_sha2, (&hash[40]));

	    sph_haval256_5_init(&ctx_haval);
	    sph_haval256_5 (&ctx_haval, (&hash[40]), 64);
	    sph_haval256_5_close(&ctx_haval, (&hash[41]));

	    		memcpy(output, &hash[41], 32);
		} 
		else if(hashChar == 57) 
		{
            		uint32_t hash[43];

	    sph_blake512_init(&ctx_blake);
	    sph_blake512 (&ctx_blake, input, len);
	    sph_blake512_close(&ctx_blake, (&hash[0]));
	    sph_bmw512_init(&ctx_bmw);
	    sph_bmw512 (&ctx_bmw, (&hash[0]), 64);
	    sph_bmw512_close(&ctx_bmw, (&hash[1]));
	    sph_groestl512_init(&ctx_groestl);
	    sph_groestl512 (&ctx_groestl, (&hash[1]), 64);
	    sph_groestl512_close(&ctx_groestl, (&hash[2]));
	    sph_skein512_init(&ctx_skein);
	    sph_skein512 (&ctx_skein, (&hash[2]), 64);
	    sph_skein512_close(&ctx_skein, (&hash[3]));
	    sph_jh512_init(&ctx_jh);
	    sph_jh512 (&ctx_jh, (&hash[3]), 64);
	    sph_jh512_close(&ctx_jh, (&hash[4]));
	    sph_keccak512_init(&ctx_keccak);
	    sph_keccak512 (&ctx_keccak, (&hash[4]), 64);
	    sph_keccak512_close(&ctx_keccak, (&hash[5]));
	    sph_luffa512_init(&ctx_luffa);
	    sph_luffa512 (&ctx_luffa, (&hash[5]), 64);
	    sph_luffa512_close(&ctx_luffa, (&hash[6]));
	    sph_cubehash512_init(&ctx_cubehash);
	    sph_cubehash512 (&ctx_cubehash, (&hash[6]), 64);
	    sph_cubehash512_close(&ctx_cubehash, (&hash[7]));
	    sph_shavite512_init(&ctx_shavite);
	    sph_shavite512(&ctx_shavite, (&hash[7]), 64);
	    sph_shavite512_close(&ctx_shavite, (&hash[8]));
	    sph_simd512_init(&ctx_simd);
	    sph_simd512 (&ctx_simd, (&hash[8]), 64);
	    sph_simd512_close(&ctx_simd, (&hash[9]));
	    sph_echo512_init(&ctx_echo);
	    sph_echo512 (&ctx_echo, (&hash[9]), 64);
	    sph_echo512_close(&ctx_echo, (&hash[10]));
	    sph_hamsi512_init(&ctx_hamsi);
	    sph_hamsi512 (&ctx_hamsi, (&hash[10]), 64);
	    sph_hamsi512_close(&ctx_hamsi, (&hash[11]));
	    sph_fugue512_init(&ctx_fugue);
	    sph_fugue512 (&ctx_fugue, (&hash[11]), 64);
	    sph_fugue512_close(&ctx_fugue, (&hash[12]));
	    sph_shabal512_init(&ctx_shabal);
	    sph_shabal512 (&ctx_shabal, (&hash[12]), 64);
	    sph_shabal512_close(&ctx_shabal, (&hash[13]));
	    sph_whirlpool_init(&ctx_whirlpool);
	    sph_whirlpool (&ctx_whirlpool, (&hash[13]), 64);
	    sph_whirlpool_close(&ctx_whirlpool, (&hash[14]));
	    sph_sha512_init(&ctx_sha2);
	    sph_sha512 (&ctx_sha2, (&hash[14]), 64);
	    sph_sha512_close(&ctx_sha2, (&hash[15]));
	    sph_haval256_5_init(&ctx_haval);
	    sph_haval256_5 (&ctx_haval, (&hash[15]), 64);
	    sph_haval256_5_close(&ctx_haval, (&hash[16]));
	    sph_bmw512_init(&ctx_bmw);
	    sph_bmw512 (&ctx_bmw, (&hash[16]), 64);
	    sph_bmw512_close(&ctx_bmw, (&hash[17]));
	    sph_groestl512_init(&ctx_groestl);
	    sph_groestl512 (&ctx_groestl, (&hash[17]), 64);
	    sph_groestl512_close(&ctx_groestl, (&hash[18]));
	    sph_skein512_init(&ctx_skein);
	    sph_skein512 (&ctx_skein, (&hash[18]), 64);
	    sph_skein512_close(&ctx_skein, (&hash[19]));
	    sph_jh512_init(&ctx_jh);
	    sph_jh512 (&ctx_jh, (&hash[19]), 64);
	    sph_jh512_close(&ctx_jh, (&hash[20]));
	    sph_keccak512_init(&ctx_keccak);
	    sph_keccak512 (&ctx_keccak, (&hash[20]), 64);
	    sph_keccak512_close(&ctx_keccak, (&hash[21]));
	    sph_luffa512_init(&ctx_luffa);
	    sph_luffa512 (&ctx_luffa, (&hash[21]), 64);
	    sph_luffa512_close(&ctx_luffa, (&hash[22]));
	    sph_cubehash512_init(&ctx_cubehash);
	    sph_cubehash512 (&ctx_cubehash, (&hash[22]), 64);
	    sph_cubehash512_close(&ctx_cubehash, (&hash[23]));
	    sph_shavite512_init(&ctx_shavite);
	    sph_shavite512(&ctx_shavite, (&hash[23]), 64);
	    sph_shavite512_close(&ctx_shavite, (&hash[24]));
	    sph_simd512_init(&ctx_simd);
	    sph_simd512 (&ctx_simd, (&hash[24]), 64);
	    sph_simd512_close(&ctx_simd, (&hash[25]));
	    sph_echo512_init(&ctx_echo);
	    sph_echo512 (&ctx_echo, (&hash[25]), 64);
	    sph_echo512_close(&ctx_echo, (&hash[26]));
	    sph_hamsi512_init(&ctx_hamsi);
	    sph_hamsi512 (&ctx_hamsi, (&hash[26]), 64);
	    sph_hamsi512_close(&ctx_hamsi, (&hash[27]));
	    sph_fugue512_init(&ctx_fugue);
	    sph_fugue512 (&ctx_fugue, (&hash[27]), 64);
	    sph_fugue512_close(&ctx_fugue, (&hash[28]));
	    sph_shabal512_init(&ctx_shabal);
	    sph_shabal512 (&ctx_shabal, (&hash[28]), 64);
	    sph_shabal512_close(&ctx_shabal, (&hash[29]));
	    sph_whirlpool_init(&ctx_whirlpool);
	    sph_whirlpool (&ctx_whirlpool, (&hash[29]), 64);
	    sph_whirlpool_close(&ctx_whirlpool, (&hash[30]));
	    sph_sha512_init(&ctx_sha2);
	    sph_sha512 (&ctx_sha2, (&hash[30]), 64);
	    sph_sha512_close(&ctx_sha2, (&hash[31]));
	    sph_haval256_5_init(&ctx_haval);
	    sph_haval256_5 (&ctx_haval, (&hash[31]), 64);
	    sph_haval256_5_close(&ctx_haval, (&hash[32]));
	    sph_sha512_init(&ctx_sha2);
	    sph_sha512 (&ctx_sha2, (&hash[32]), 64);
	    sph_sha512_close(&ctx_sha2, (&hash[33]));
	    sph_haval256_5_init(&ctx_haval);
	    sph_haval256_5 (&ctx_haval, (&hash[33]), 64);
	    sph_haval256_5_close(&ctx_haval, (&hash[34]));
	    sph_bmw512_init(&ctx_bmw);
	    sph_bmw512 (&ctx_bmw, (&hash[34]), 64);
	    sph_bmw512_close(&ctx_bmw, (&hash[35]));
	    sph_groestl512_init(&ctx_groestl);
	    sph_groestl512 (&ctx_groestl, (&hash[35]), 64);
	    sph_groestl512_close(&ctx_groestl, (&hash[36]));
	    sph_skein512_init(&ctx_skein);
	    sph_skein512 (&ctx_skein, (&hash[36]), 64);
	    sph_skein512_close(&ctx_skein, (&hash[37]));
	    sph_sha512_init(&ctx_sha2);
	    sph_sha512 (&ctx_sha2, (&hash[37]), 64);
	    sph_sha512_close(&ctx_sha2, (&hash[38]));
	    sph_haval256_5_init(&ctx_haval);
	    sph_haval256_5 (&ctx_haval, (&hash[38]), 64);
	    sph_haval256_5_close(&ctx_haval, (&hash[39]));
	    sph_bmw512_init(&ctx_bmw);
	    sph_bmw512 (&ctx_bmw, (&hash[39]), 64);
	    sph_bmw512_close(&ctx_bmw, (&hash[40]));
	    sph_groestl512_init(&ctx_groestl);
	    sph_groestl512 (&ctx_groestl, (&hash[40]), 64);
	    sph_groestl512_close(&ctx_groestl, (&hash[41]));

	    		sph_skein512_init(&ctx_skein);
	    		sph_skein512 (&ctx_skein, (&hash[41]), 64);
	    		sph_skein512_close(&ctx_skein, (&hash[42]));

	    		memcpy(output, &hash[42], 32);
		} 
		else if(hashChar == 97) 
		{
            		uint32_t hash[44];

	    		sph_blake512_init(&ctx_blake);
	    		sph_blake512 (&ctx_blake, input, len);
	    		sph_blake512_close(&ctx_blake, (&hash[0]));

	    sph_bmw512_init(&ctx_bmw);
	    sph_bmw512 (&ctx_bmw, (&hash[0]), 64);
	    sph_bmw512_close(&ctx_bmw, (&hash[1]));
	    sph_groestl512_init(&ctx_groestl);
	    sph_groestl512 (&ctx_groestl, (&hash[1]), 64);
	    sph_groestl512_close(&ctx_groestl, (&hash[2]));
	    sph_skein512_init(&ctx_skein);
	    sph_skein512 (&ctx_skein, (&hash[2]), 64);
	    sph_skein512_close(&ctx_skein, (&hash[3]));
	    sph_jh512_init(&ctx_jh);
	    sph_jh512 (&ctx_jh, (&hash[3]), 64);
	    sph_jh512_close(&ctx_jh, (&hash[4]));
	    sph_keccak512_init(&ctx_keccak);
	    sph_keccak512 (&ctx_keccak, (&hash[4]), 64);
	    sph_keccak512_close(&ctx_keccak, (&hash[5]));
	    sph_luffa512_init(&ctx_luffa);
	    sph_luffa512 (&ctx_luffa, (&hash[5]), 64);
	    sph_luffa512_close(&ctx_luffa, (&hash[6]));
	    sph_cubehash512_init(&ctx_cubehash);
	    sph_cubehash512 (&ctx_cubehash, (&hash[6]), 64);
	    sph_cubehash512_close(&ctx_cubehash, (&hash[7]));
	    sph_shavite512_init(&ctx_shavite);
	    sph_shavite512(&ctx_shavite, (&hash[7]), 64);
	    sph_shavite512_close(&ctx_shavite, (&hash[8]));
	    sph_simd512_init(&ctx_simd);
	    sph_simd512 (&ctx_simd, (&hash[8]), 64);
	    sph_simd512_close(&ctx_simd, (&hash[9]));
	    sph_echo512_init(&ctx_echo);
	    sph_echo512 (&ctx_echo, (&hash[9]), 64);
	    sph_echo512_close(&ctx_echo, (&hash[10]));
	    sph_hamsi512_init(&ctx_hamsi);
	    sph_hamsi512 (&ctx_hamsi, (&hash[10]), 64);
	    sph_hamsi512_close(&ctx_hamsi, (&hash[11]));
	    sph_fugue512_init(&ctx_fugue);
	    sph_fugue512 (&ctx_fugue, (&hash[11]), 64);
	    sph_fugue512_close(&ctx_fugue, (&hash[12]));
	    sph_shabal512_init(&ctx_shabal);
	    sph_shabal512 (&ctx_shabal, (&hash[12]), 64);
	    sph_shabal512_close(&ctx_shabal, (&hash[13]));
	    sph_whirlpool_init(&ctx_whirlpool);
	    sph_whirlpool (&ctx_whirlpool, (&hash[13]), 64);
	    sph_whirlpool_close(&ctx_whirlpool, (&hash[14]));
	    sph_sha512_init(&ctx_sha2);
	    sph_sha512 (&ctx_sha2, (&hash[14]), 64);
	    sph_sha512_close(&ctx_sha2, (&hash[15]));
	    sph_haval256_5_init(&ctx_haval);
	    sph_haval256_5 (&ctx_haval, (&hash[15]), 64);
	    sph_haval256_5_close(&ctx_haval, (&hash[16]));
	    sph_bmw512_init(&ctx_bmw);
	    sph_bmw512 (&ctx_bmw, (&hash[16]), 64);
	    sph_bmw512_close(&ctx_bmw, (&hash[17]));
	    sph_groestl512_init(&ctx_groestl);
	    sph_groestl512 (&ctx_groestl, (&hash[17]), 64);
	    sph_groestl512_close(&ctx_groestl, (&hash[18]));
	    sph_skein512_init(&ctx_skein);
	    sph_skein512 (&ctx_skein, (&hash[18]), 64);
	    sph_skein512_close(&ctx_skein, (&hash[19]));
	    sph_jh512_init(&ctx_jh);
	    sph_jh512 (&ctx_jh, (&hash[19]), 64);
	    sph_jh512_close(&ctx_jh, (&hash[20]));
	    sph_keccak512_init(&ctx_keccak);
	    sph_keccak512 (&ctx_keccak, (&hash[20]), 64);
	    sph_keccak512_close(&ctx_keccak, (&hash[21]));
	    sph_luffa512_init(&ctx_luffa);
	    sph_luffa512 (&ctx_luffa, (&hash[21]), 64);
	    sph_luffa512_close(&ctx_luffa, (&hash[22]));
	    sph_cubehash512_init(&ctx_cubehash);
	    sph_cubehash512 (&ctx_cubehash, (&hash[22]), 64);
	    sph_cubehash512_close(&ctx_cubehash, (&hash[23]));
	    sph_shavite512_init(&ctx_shavite);
	    sph_shavite512(&ctx_shavite, (&hash[23]), 64);
	    sph_shavite512_close(&ctx_shavite, (&hash[24]));
	    sph_simd512_init(&ctx_simd);
	    sph_simd512 (&ctx_simd, (&hash[24]), 64);
	    sph_simd512_close(&ctx_simd, (&hash[25]));
	    sph_echo512_init(&ctx_echo);
	    sph_echo512 (&ctx_echo, (&hash[25]), 64);
	    sph_echo512_close(&ctx_echo, (&hash[26]));
	    sph_hamsi512_init(&ctx_hamsi);
	    sph_hamsi512 (&ctx_hamsi, (&hash[26]), 64);
	    sph_hamsi512_close(&ctx_hamsi, (&hash[27]));
	    sph_fugue512_init(&ctx_fugue);
	    sph_fugue512 (&ctx_fugue, (&hash[27]), 64);
	    sph_fugue512_close(&ctx_fugue, (&hash[28]));
	    sph_shabal512_init(&ctx_shabal);
	    sph_shabal512 (&ctx_shabal, (&hash[28]), 64);
	    sph_shabal512_close(&ctx_shabal, (&hash[29]));
	    sph_whirlpool_init(&ctx_whirlpool);
	    sph_whirlpool (&ctx_whirlpool, (&hash[29]), 64);
	    sph_whirlpool_close(&ctx_whirlpool, (&hash[30]));
	    sph_sha512_init(&ctx_sha2);
	    sph_sha512 (&ctx_sha2, (&hash[30]), 64);
	    sph_sha512_close(&ctx_sha2, (&hash[31]));
	    sph_haval256_5_init(&ctx_haval);
	    sph_haval256_5 (&ctx_haval, (&hash[31]), 64);
	    sph_haval256_5_close(&ctx_haval, (&hash[32]));
	    sph_sha512_init(&ctx_sha2);
	    sph_sha512 (&ctx_sha2, (&hash[32]), 64);
	    sph_sha512_close(&ctx_sha2, (&hash[33]));
	    sph_haval256_5_init(&ctx_haval);
	    sph_haval256_5 (&ctx_haval, (&hash[33]), 64);
	    sph_haval256_5_close(&ctx_haval, (&hash[34]));
	    sph_bmw512_init(&ctx_bmw);
	    sph_bmw512 (&ctx_bmw, (&hash[34]), 64);
	    sph_bmw512_close(&ctx_bmw, (&hash[35]));
	    sph_groestl512_init(&ctx_groestl);
	    sph_groestl512 (&ctx_groestl, (&hash[35]), 64);
	    sph_groestl512_close(&ctx_groestl, (&hash[36]));
	    sph_skein512_init(&ctx_skein);
	    sph_skein512 (&ctx_skein, (&hash[36]), 64);
	    sph_skein512_close(&ctx_skein, (&hash[37]));
	    sph_sha512_init(&ctx_sha2);
	    sph_sha512 (&ctx_sha2, (&hash[37]), 64);
	    sph_sha512_close(&ctx_sha2, (&hash[38]));
	    sph_haval256_5_init(&ctx_haval);
	    sph_haval256_5 (&ctx_haval, (&hash[38]), 64);
	    sph_haval256_5_close(&ctx_haval, (&hash[39]));
	    sph_bmw512_init(&ctx_bmw);
	    sph_bmw512 (&ctx_bmw, (&hash[39]), 64);
	    sph_bmw512_close(&ctx_bmw, (&hash[40]));
	    sph_groestl512_init(&ctx_groestl);
	    sph_groestl512 (&ctx_groestl, (&hash[40]), 64);
	    sph_groestl512_close(&ctx_groestl, (&hash[41]));
	    sph_skein512_init(&ctx_skein);
	    sph_skein512 (&ctx_skein, (&hash[41]), 64);
	    sph_skein512_close(&ctx_skein, (&hash[42]));

	    		sph_fugue512_init(&ctx_fugue);
	    		sph_fugue512 (&ctx_fugue, (&hash[42]), 64);
	    		sph_fugue512_close(&ctx_fugue, (&hash[43]));

	    		memcpy(output, &hash[43], 32);
		} 
		else if(hashChar == 98) 
		{
            		uint32_t hash[45];

	    		sph_blake512_init(&ctx_blake);
	    		sph_blake512 (&ctx_blake, input, len);
	    		sph_blake512_close(&ctx_blake, (&hash[0]));

	    sph_bmw512_init(&ctx_bmw);
	    sph_bmw512 (&ctx_bmw, (&hash[0]), 64);
	    sph_bmw512_close(&ctx_bmw, (&hash[1]));
	    sph_groestl512_init(&ctx_groestl);
	    sph_groestl512 (&ctx_groestl, (&hash[1]), 64);
	    sph_groestl512_close(&ctx_groestl, (&hash[2]));
	    sph_skein512_init(&ctx_skein);
	    sph_skein512 (&ctx_skein, (&hash[2]), 64);
	    sph_skein512_close(&ctx_skein, (&hash[3]));
	    sph_jh512_init(&ctx_jh);
	    sph_jh512 (&ctx_jh, (&hash[3]), 64);
	    sph_jh512_close(&ctx_jh, (&hash[4]));
	    sph_keccak512_init(&ctx_keccak);
	    sph_keccak512 (&ctx_keccak, (&hash[4]), 64);
	    sph_keccak512_close(&ctx_keccak, (&hash[5]));
	    sph_luffa512_init(&ctx_luffa);
	    sph_luffa512 (&ctx_luffa, (&hash[5]), 64);
	    sph_luffa512_close(&ctx_luffa, (&hash[6]));
	    sph_cubehash512_init(&ctx_cubehash);
	    sph_cubehash512 (&ctx_cubehash, (&hash[6]), 64);
	    sph_cubehash512_close(&ctx_cubehash, (&hash[7]));
	    sph_shavite512_init(&ctx_shavite);
	    sph_shavite512(&ctx_shavite, (&hash[7]), 64);
	    sph_shavite512_close(&ctx_shavite, (&hash[8]));
	    sph_simd512_init(&ctx_simd);
	    sph_simd512 (&ctx_simd, (&hash[8]), 64);
	    sph_simd512_close(&ctx_simd, (&hash[9]));
	    sph_echo512_init(&ctx_echo);
	    sph_echo512 (&ctx_echo, (&hash[9]), 64);
	    sph_echo512_close(&ctx_echo, (&hash[10]));
	    sph_hamsi512_init(&ctx_hamsi);
	    sph_hamsi512 (&ctx_hamsi, (&hash[10]), 64);
	    sph_hamsi512_close(&ctx_hamsi, (&hash[11]));
	    sph_fugue512_init(&ctx_fugue);
	    sph_fugue512 (&ctx_fugue, (&hash[11]), 64);
	    sph_fugue512_close(&ctx_fugue, (&hash[12]));
	    sph_shabal512_init(&ctx_shabal);
	    sph_shabal512 (&ctx_shabal, (&hash[12]), 64);
	    sph_shabal512_close(&ctx_shabal, (&hash[13]));
	    sph_whirlpool_init(&ctx_whirlpool);
	    sph_whirlpool (&ctx_whirlpool, (&hash[13]), 64);
	    sph_whirlpool_close(&ctx_whirlpool, (&hash[14]));
	    sph_sha512_init(&ctx_sha2);
	    sph_sha512 (&ctx_sha2, (&hash[14]), 64);
	    sph_sha512_close(&ctx_sha2, (&hash[15]));
	    sph_haval256_5_init(&ctx_haval);
	    sph_haval256_5 (&ctx_haval, (&hash[15]), 64);
	    sph_haval256_5_close(&ctx_haval, (&hash[16]));
	    sph_bmw512_init(&ctx_bmw);
	    sph_bmw512 (&ctx_bmw, (&hash[16]), 64);
	    sph_bmw512_close(&ctx_bmw, (&hash[17]));
	    sph_groestl512_init(&ctx_groestl);
	    sph_groestl512 (&ctx_groestl, (&hash[17]), 64);
	    sph_groestl512_close(&ctx_groestl, (&hash[18]));
	    sph_skein512_init(&ctx_skein);
	    sph_skein512 (&ctx_skein, (&hash[18]), 64);
	    sph_skein512_close(&ctx_skein, (&hash[19]));
	    sph_jh512_init(&ctx_jh);
	    sph_jh512 (&ctx_jh, (&hash[19]), 64);
	    sph_jh512_close(&ctx_jh, (&hash[20]));
	    sph_keccak512_init(&ctx_keccak);
	    sph_keccak512 (&ctx_keccak, (&hash[20]), 64);
	    sph_keccak512_close(&ctx_keccak, (&hash[21]));
	    sph_luffa512_init(&ctx_luffa);
	    sph_luffa512 (&ctx_luffa, (&hash[21]), 64);
	    sph_luffa512_close(&ctx_luffa, (&hash[22]));
	    sph_cubehash512_init(&ctx_cubehash);
	    sph_cubehash512 (&ctx_cubehash, (&hash[22]), 64);
	    sph_cubehash512_close(&ctx_cubehash, (&hash[23]));
	    sph_shavite512_init(&ctx_shavite);
	    sph_shavite512(&ctx_shavite, (&hash[23]), 64);
	    sph_shavite512_close(&ctx_shavite, (&hash[24]));
	    sph_simd512_init(&ctx_simd);
	    sph_simd512 (&ctx_simd, (&hash[24]), 64);
	    sph_simd512_close(&ctx_simd, (&hash[25]));
	    sph_echo512_init(&ctx_echo);
	    sph_echo512 (&ctx_echo, (&hash[25]), 64);
	    sph_echo512_close(&ctx_echo, (&hash[26]));
	    sph_hamsi512_init(&ctx_hamsi);
	    sph_hamsi512 (&ctx_hamsi, (&hash[26]), 64);
	    sph_hamsi512_close(&ctx_hamsi, (&hash[27]));
	    sph_fugue512_init(&ctx_fugue);
	    sph_fugue512 (&ctx_fugue, (&hash[27]), 64);
	    sph_fugue512_close(&ctx_fugue, (&hash[28]));
	    sph_shabal512_init(&ctx_shabal);
	    sph_shabal512 (&ctx_shabal, (&hash[28]), 64);
	    sph_shabal512_close(&ctx_shabal, (&hash[29]));
	    sph_whirlpool_init(&ctx_whirlpool);
	    sph_whirlpool (&ctx_whirlpool, (&hash[29]), 64);
	    sph_whirlpool_close(&ctx_whirlpool, (&hash[30]));
	    sph_sha512_init(&ctx_sha2);
	    sph_sha512 (&ctx_sha2, (&hash[30]), 64);
	    sph_sha512_close(&ctx_sha2, (&hash[31]));
	    sph_haval256_5_init(&ctx_haval);
	    sph_haval256_5 (&ctx_haval, (&hash[31]), 64);
	    sph_haval256_5_close(&ctx_haval, (&hash[32]));
	    sph_shavite512_init(&ctx_shavite);
	    sph_shavite512(&ctx_shavite, (&hash[32]), 64);
	    sph_shavite512_close(&ctx_shavite, (&hash[33]));
	    sph_simd512_init(&ctx_simd);
	    sph_simd512 (&ctx_simd, (&hash[33]), 64);
	    sph_simd512_close(&ctx_simd, (&hash[34]));
	    sph_echo512_init(&ctx_echo);
	    sph_echo512 (&ctx_echo, (&hash[34]), 64);
	    sph_echo512_close(&ctx_echo, (&hash[35]));
	    sph_hamsi512_init(&ctx_hamsi);
	    sph_hamsi512 (&ctx_hamsi, (&hash[35]), 64);
	    sph_hamsi512_close(&ctx_hamsi, (&hash[36]));
	    sph_fugue512_init(&ctx_fugue);
	    sph_fugue512 (&ctx_fugue, (&hash[36]), 64);
	    sph_fugue512_close(&ctx_fugue, (&hash[37]));
	    sph_shabal512_init(&ctx_shabal);
	    sph_shabal512 (&ctx_shabal, (&hash[37]), 64);
	    sph_shabal512_close(&ctx_shabal, (&hash[38]));
	    sph_whirlpool_init(&ctx_whirlpool);
	    sph_whirlpool (&ctx_whirlpool, (&hash[38]), 64);
	    sph_whirlpool_close(&ctx_whirlpool, (&hash[39]));
	    sph_sha512_init(&ctx_sha2);
	    sph_sha512 (&ctx_sha2, (&hash[39]), 64);
	    sph_sha512_close(&ctx_sha2, (&hash[40]));
	    sph_haval256_5_init(&ctx_haval);
	    sph_haval256_5 (&ctx_haval, (&hash[40]), 64);
	    sph_haval256_5_close(&ctx_haval, (&hash[41]));
	    sph_bmw512_init(&ctx_bmw);
	    sph_bmw512 (&ctx_bmw, (&hash[41]), 64);
	    sph_bmw512_close(&ctx_bmw, (&hash[42]));
	    sph_groestl512_init(&ctx_groestl);
	    sph_groestl512 (&ctx_groestl, (&hash[42]), 64);
	    sph_groestl512_close(&ctx_groestl, (&hash[43]));

	    sph_skein512_init(&ctx_skein);
	    sph_skein512 (&ctx_skein, (&hash[43]), 64);
	    sph_skein512_close(&ctx_skein, (&hash[44]));

	   		 memcpy(output, &hash[44], 32);
		} 
		else if(hashChar == 99) 
		{
            		uint32_t hash[46];

	    sph_blake512_init(&ctx_blake);
	    sph_blake512 (&ctx_blake, input, len);
	    sph_blake512_close(&ctx_blake, (&hash[0]));

	    sph_bmw512_init(&ctx_bmw);
	    sph_bmw512 (&ctx_bmw, (&hash[0]), 64);
	    sph_bmw512_close(&ctx_bmw, (&hash[1]));
	    sph_groestl512_init(&ctx_groestl);
	    sph_groestl512 (&ctx_groestl, (&hash[1]), 64);
	    sph_groestl512_close(&ctx_groestl, (&hash[2]));
	    sph_skein512_init(&ctx_skein);
	    sph_skein512 (&ctx_skein, (&hash[2]), 64);
	    sph_skein512_close(&ctx_skein, (&hash[3]));
	    sph_jh512_init(&ctx_jh);
	    sph_jh512 (&ctx_jh, (&hash[3]), 64);
	    sph_jh512_close(&ctx_jh, (&hash[4]));
	    sph_keccak512_init(&ctx_keccak);
	    sph_keccak512 (&ctx_keccak, (&hash[4]), 64);
	    sph_keccak512_close(&ctx_keccak, (&hash[5]));
	    sph_luffa512_init(&ctx_luffa);
	    sph_luffa512 (&ctx_luffa, (&hash[5]), 64);
	    sph_luffa512_close(&ctx_luffa, (&hash[6]));
	    sph_cubehash512_init(&ctx_cubehash);
	    sph_cubehash512 (&ctx_cubehash, (&hash[6]), 64);
	    sph_cubehash512_close(&ctx_cubehash, (&hash[7]));
	    sph_shavite512_init(&ctx_shavite);
	    sph_shavite512(&ctx_shavite, (&hash[7]), 64);
	    sph_shavite512_close(&ctx_shavite, (&hash[8]));
	    sph_simd512_init(&ctx_simd);
	    sph_simd512 (&ctx_simd, (&hash[8]), 64);
	    sph_simd512_close(&ctx_simd, (&hash[9]));
	    sph_echo512_init(&ctx_echo);
	    sph_echo512 (&ctx_echo, (&hash[9]), 64);
	    sph_echo512_close(&ctx_echo, (&hash[10]));
	    sph_hamsi512_init(&ctx_hamsi);
	    sph_hamsi512 (&ctx_hamsi, (&hash[10]), 64);
	    sph_hamsi512_close(&ctx_hamsi, (&hash[11]));
	    sph_fugue512_init(&ctx_fugue);
	    sph_fugue512 (&ctx_fugue, (&hash[11]), 64);
	    sph_fugue512_close(&ctx_fugue, (&hash[12]));
	    sph_shabal512_init(&ctx_shabal);
	    sph_shabal512 (&ctx_shabal, (&hash[12]), 64);
	    sph_shabal512_close(&ctx_shabal, (&hash[13]));
	    sph_whirlpool_init(&ctx_whirlpool);
	    sph_whirlpool (&ctx_whirlpool, (&hash[13]), 64);
	    sph_whirlpool_close(&ctx_whirlpool, (&hash[14]));
	    sph_sha512_init(&ctx_sha2);
	    sph_sha512 (&ctx_sha2, (&hash[14]), 64);
	    sph_sha512_close(&ctx_sha2, (&hash[15]));
	    sph_haval256_5_init(&ctx_haval);
	    sph_haval256_5 (&ctx_haval, (&hash[15]), 64);
	    sph_haval256_5_close(&ctx_haval, (&hash[16]));
	    sph_bmw512_init(&ctx_bmw);
	    sph_bmw512 (&ctx_bmw, (&hash[16]), 64);
	    sph_bmw512_close(&ctx_bmw, (&hash[17]));
	    sph_groestl512_init(&ctx_groestl);
	    sph_groestl512 (&ctx_groestl, (&hash[17]), 64);
	    sph_groestl512_close(&ctx_groestl, (&hash[18]));
	    sph_skein512_init(&ctx_skein);
	    sph_skein512 (&ctx_skein, (&hash[18]), 64);
	    sph_skein512_close(&ctx_skein, (&hash[19]));
	    sph_jh512_init(&ctx_jh);
	    sph_jh512 (&ctx_jh, (&hash[19]), 64);
	    sph_jh512_close(&ctx_jh, (&hash[20]));
	    sph_keccak512_init(&ctx_keccak);
	    sph_keccak512 (&ctx_keccak, (&hash[20]), 64);
	    sph_keccak512_close(&ctx_keccak, (&hash[21]));
	    sph_luffa512_init(&ctx_luffa);
	    sph_luffa512 (&ctx_luffa, (&hash[21]), 64);
	    sph_luffa512_close(&ctx_luffa, (&hash[22]));
	    sph_cubehash512_init(&ctx_cubehash);
	    sph_cubehash512 (&ctx_cubehash, (&hash[22]), 64);
	    sph_cubehash512_close(&ctx_cubehash, (&hash[23]));
	    sph_shavite512_init(&ctx_shavite);
	    sph_shavite512(&ctx_shavite, (&hash[23]), 64);
	    sph_shavite512_close(&ctx_shavite, (&hash[24]));
	    sph_simd512_init(&ctx_simd);
	    sph_simd512 (&ctx_simd, (&hash[24]), 64);
	    sph_simd512_close(&ctx_simd, (&hash[25]));
	    sph_echo512_init(&ctx_echo);
	    sph_echo512 (&ctx_echo, (&hash[25]), 64);
	    sph_echo512_close(&ctx_echo, (&hash[26]));
	    sph_hamsi512_init(&ctx_hamsi);
	    sph_hamsi512 (&ctx_hamsi, (&hash[26]), 64);
	    sph_hamsi512_close(&ctx_hamsi, (&hash[27]));
	    sph_fugue512_init(&ctx_fugue);
	    sph_fugue512 (&ctx_fugue, (&hash[27]), 64);
	    sph_fugue512_close(&ctx_fugue, (&hash[28]));
	    sph_shabal512_init(&ctx_shabal);
	    sph_shabal512 (&ctx_shabal, (&hash[28]), 64);
	    sph_shabal512_close(&ctx_shabal, (&hash[29]));
	    sph_whirlpool_init(&ctx_whirlpool);
	    sph_whirlpool (&ctx_whirlpool, (&hash[29]), 64);
	    sph_whirlpool_close(&ctx_whirlpool, (&hash[30]));
	    sph_sha512_init(&ctx_sha2);
	    sph_sha512 (&ctx_sha2, (&hash[30]), 64);
	    sph_sha512_close(&ctx_sha2, (&hash[31]));
	    sph_haval256_5_init(&ctx_haval);
	    sph_haval256_5 (&ctx_haval, (&hash[31]), 64);
	    sph_haval256_5_close(&ctx_haval, (&hash[32]));
	    sph_shabal512_init(&ctx_shabal);
	    sph_shabal512 (&ctx_shabal, (&hash[32]), 64);
	    sph_shabal512_close(&ctx_shabal, (&hash[33]));
	    sph_whirlpool_init(&ctx_whirlpool);
	    sph_whirlpool (&ctx_whirlpool, (&hash[33]), 64);
	    sph_whirlpool_close(&ctx_whirlpool, (&hash[34]));
	    sph_sha512_init(&ctx_sha2);
	    sph_sha512 (&ctx_sha2, (&hash[34]), 64);
	    sph_sha512_close(&ctx_sha2, (&hash[35]));
	    sph_haval256_5_init(&ctx_haval);
	    sph_haval256_5 (&ctx_haval, (&hash[35]), 64);
	    sph_haval256_5_close(&ctx_haval, (&hash[36]));
	    sph_bmw512_init(&ctx_bmw);
	    sph_bmw512 (&ctx_bmw, (&hash[36]), 64);
	    sph_bmw512_close(&ctx_bmw, (&hash[37]));
	    sph_groestl512_init(&ctx_groestl);
	    sph_groestl512 (&ctx_groestl, (&hash[37]), 64);
	    sph_groestl512_close(&ctx_groestl, (&hash[38]));
	    sph_skein512_init(&ctx_skein);
	    sph_skein512 (&ctx_skein, (&hash[38]), 64);
	    sph_skein512_close(&ctx_skein, (&hash[39]));

	    		sph_shabal512_init(&ctx_shabal);
	    		sph_shabal512 (&ctx_shabal, (&hash[39]), 64);
	    		sph_shabal512_close(&ctx_shabal, (&hash[40]));

	    		sph_whirlpool_init(&ctx_whirlpool);
	    		sph_whirlpool (&ctx_whirlpool, (&hash[40]), 64);
	    		sph_whirlpool_close(&ctx_whirlpool, (&hash[41]));

	    		sph_sha512_init(&ctx_sha2);
	    		sph_sha512 (&ctx_sha2, (&hash[41]), 64);
	    		sph_sha512_close(&ctx_sha2, (&hash[42]));

	    		sph_haval256_5_init(&ctx_haval);
	    		sph_haval256_5 (&ctx_haval, (&hash[42]), 64);
	    		sph_haval256_5_close(&ctx_haval, (&hash[43]));

	    		sph_bmw512_init(&ctx_bmw);
	    		sph_bmw512 (&ctx_bmw, (&hash[43]), 64);
	    		sph_bmw512_close(&ctx_bmw, (&hash[44]));

	    		sph_groestl512_init(&ctx_groestl);
	    		sph_groestl512 (&ctx_groestl, (&hash[44]), 64);
	    		sph_groestl512_close(&ctx_groestl, (&hash[45]));

	    		memcpy(output, &hash[45], 32);
		} 
		else if(hashChar == 100) 
		{
            		uint32_t hash[47];

	    		sph_blake512_init(&ctx_blake);
	    		sph_blake512 (&ctx_blake, input, len);
	    		sph_blake512_close(&ctx_blake, (&hash[0]));

	    		sph_bmw512_init(&ctx_bmw);
	    		sph_bmw512 (&ctx_bmw, (&hash[0]), 64);
	    		sph_bmw512_close(&ctx_bmw, (&hash[1]));

	    		sph_groestl512_init(&ctx_groestl);
	    		sph_groestl512 (&ctx_groestl, (&hash[1]), 64);
	    		sph_groestl512_close(&ctx_groestl, (&hash[2]));

	    		sph_skein512_init(&ctx_skein);
	    		sph_skein512 (&ctx_skein, (&hash[2]), 64);
	    		sph_skein512_close(&ctx_skein, (&hash[3]));

	    		sph_jh512_init(&ctx_jh);
	    		sph_jh512 (&ctx_jh, (&hash[3]), 64);
	    		sph_jh512_close(&ctx_jh, (&hash[4]));

	    		sph_keccak512_init(&ctx_keccak);
	    		sph_keccak512 (&ctx_keccak, (&hash[4]), 64);
	    		sph_keccak512_close(&ctx_keccak, (&hash[5]));

	    		sph_luffa512_init(&ctx_luffa);
	    		sph_luffa512 (&ctx_luffa, (&hash[5]), 64);
	    		sph_luffa512_close(&ctx_luffa, (&hash[6]));

	    		sph_cubehash512_init(&ctx_cubehash);
	    		sph_cubehash512 (&ctx_cubehash, (&hash[6]), 64);
	    		sph_cubehash512_close(&ctx_cubehash, (&hash[7]));

	    		sph_shavite512_init(&ctx_shavite);
	    		sph_shavite512(&ctx_shavite, (&hash[7]), 64);
	    		sph_shavite512_close(&ctx_shavite, (&hash[8]));

	    		sph_simd512_init(&ctx_simd);
	    		sph_simd512 (&ctx_simd, (&hash[8]), 64);
	    		sph_simd512_close(&ctx_simd, (&hash[9]));

	    		sph_echo512_init(&ctx_echo);
	    		sph_echo512 (&ctx_echo, (&hash[9]), 64);
	    		sph_echo512_close(&ctx_echo, (&hash[10]));

	    		sph_hamsi512_init(&ctx_hamsi);
	    		sph_hamsi512 (&ctx_hamsi, (&hash[10]), 64);
	    		sph_hamsi512_close(&ctx_hamsi, (&hash[11]));

	    		sph_fugue512_init(&ctx_fugue);
	    		sph_fugue512 (&ctx_fugue, (&hash[11]), 64);
	    		sph_fugue512_close(&ctx_fugue, (&hash[12]));

	    		sph_shabal512_init(&ctx_shabal);
	    		sph_shabal512 (&ctx_shabal, (&hash[12]), 64);
	    		sph_shabal512_close(&ctx_shabal, (&hash[13]));

	    		sph_whirlpool_init(&ctx_whirlpool);
	    		sph_whirlpool (&ctx_whirlpool, (&hash[13]), 64);
	    		sph_whirlpool_close(&ctx_whirlpool, (&hash[14]));

	    		sph_sha512_init(&ctx_sha2);
	    		sph_sha512 (&ctx_sha2, (&hash[14]), 64);
	    		sph_sha512_close(&ctx_sha2, (&hash[15]));

	    		sph_haval256_5_init(&ctx_haval);
	    		sph_haval256_5 (&ctx_haval, (&hash[15]), 64);
	    		sph_haval256_5_close(&ctx_haval, (&hash[16]));

	    		sph_bmw512_init(&ctx_bmw);
	    		sph_bmw512 (&ctx_bmw, (&hash[16]), 64);
	    		sph_bmw512_close(&ctx_bmw, (&hash[17]));

	    		sph_groestl512_init(&ctx_groestl);
	    		sph_groestl512 (&ctx_groestl, (&hash[17]), 64);
	    		sph_groestl512_close(&ctx_groestl, (&hash[18]));

	    		sph_skein512_init(&ctx_skein);
	    		sph_skein512 (&ctx_skein, (&hash[18]), 64);
	    		sph_skein512_close(&ctx_skein, (&hash[19]));

	    		sph_jh512_init(&ctx_jh);
	    		sph_jh512 (&ctx_jh, (&hash[19]), 64);
	    		sph_jh512_close(&ctx_jh, (&hash[20]));

	    		sph_keccak512_init(&ctx_keccak);
	    		sph_keccak512 (&ctx_keccak, (&hash[20]), 64);
	    		sph_keccak512_close(&ctx_keccak, (&hash[21]));

	    		sph_luffa512_init(&ctx_luffa);
	    		sph_luffa512 (&ctx_luffa, (&hash[21]), 64);
	    		sph_luffa512_close(&ctx_luffa, (&hash[22]));

	    		sph_cubehash512_init(&ctx_cubehash);
	    		sph_cubehash512 (&ctx_cubehash, (&hash[22]), 64);
	    		sph_cubehash512_close(&ctx_cubehash, (&hash[23]));

	    		sph_shavite512_init(&ctx_shavite);
	    		sph_shavite512(&ctx_shavite, (&hash[23]), 64);
	    		sph_shavite512_close(&ctx_shavite, (&hash[24]));

	    		sph_simd512_init(&ctx_simd);
	    		sph_simd512 (&ctx_simd, (&hash[24]), 64);
	    		sph_simd512_close(&ctx_simd, (&hash[25]));

	    		sph_echo512_init(&ctx_echo);
	    		sph_echo512 (&ctx_echo, (&hash[25]), 64);
	    		sph_echo512_close(&ctx_echo, (&hash[26]));

	    		sph_hamsi512_init(&ctx_hamsi);
	    		sph_hamsi512 (&ctx_hamsi, (&hash[26]), 64);
	    		sph_hamsi512_close(&ctx_hamsi, (&hash[27]));

	    		sph_fugue512_init(&ctx_fugue);
	    		sph_fugue512 (&ctx_fugue, (&hash[27]), 64);
	    		sph_fugue512_close(&ctx_fugue, (&hash[28]));

	    		sph_shabal512_init(&ctx_shabal);
	    		sph_shabal512 (&ctx_shabal, (&hash[28]), 64);
	    		sph_shabal512_close(&ctx_shabal, (&hash[29]));

	    		sph_whirlpool_init(&ctx_whirlpool);
	    		sph_whirlpool (&ctx_whirlpool, (&hash[29]), 64);
	    		sph_whirlpool_close(&ctx_whirlpool, (&hash[30]));

	    		sph_sha512_init(&ctx_sha2);
	    		sph_sha512 (&ctx_sha2, (&hash[30]), 64);
	    		sph_sha512_close(&ctx_sha2, (&hash[31]));

	    		sph_haval256_5_init(&ctx_haval);
	    		sph_haval256_5 (&ctx_haval, (&hash[31]), 64);
	    		sph_haval256_5_close(&ctx_haval, (&hash[32]));

	    		sph_shabal512_init(&ctx_shabal);
	    		sph_shabal512 (&ctx_shabal, (&hash[32]), 64);
	    		sph_shabal512_close(&ctx_shabal, (&hash[33]));
	
	    		sph_whirlpool_init(&ctx_whirlpool);
	    		sph_whirlpool (&ctx_whirlpool, (&hash[33]), 64);
	    		sph_whirlpool_close(&ctx_whirlpool, (&hash[34]));

	    		sph_sha512_init(&ctx_sha2);
	    		sph_sha512 (&ctx_sha2, (&hash[34]), 64);
	    		sph_sha512_close(&ctx_sha2, (&hash[35]));

	    		sph_haval256_5_init(&ctx_haval);
	    		sph_haval256_5 (&ctx_haval, (&hash[35]), 64);
	    		sph_haval256_5_close(&ctx_haval, (&hash[36]));

	    		sph_bmw512_init(&ctx_bmw);
	    		sph_bmw512 (&ctx_bmw, (&hash[36]), 64);
	    		sph_bmw512_close(&ctx_bmw, (&hash[37]));

	    		sph_groestl512_init(&ctx_groestl);
	    		sph_groestl512 (&ctx_groestl, (&hash[37]), 64);
	    		sph_groestl512_close(&ctx_groestl, (&hash[38]));

	    		sph_skein512_init(&ctx_skein);
	    		sph_skein512 (&ctx_skein, (&hash[38]), 64);
	    		sph_skein512_close(&ctx_skein, (&hash[39]));

	    		sph_shabal512_init(&ctx_shabal);
	    		sph_shabal512 (&ctx_shabal, (&hash[39]), 64);
	    		sph_shabal512_close(&ctx_shabal, (&hash[40]));

	    		sph_whirlpool_init(&ctx_whirlpool);
	    		sph_whirlpool (&ctx_whirlpool, (&hash[40]), 64);
	    		sph_whirlpool_close(&ctx_whirlpool, (&hash[41]));

	    		sph_sha512_init(&ctx_sha2);
	    		sph_sha512 (&ctx_sha2, (&hash[41]), 64);
	    		sph_sha512_close(&ctx_sha2, (&hash[42]));

	    		sph_haval256_5_init(&ctx_haval);
	    		sph_haval256_5 (&ctx_haval, (&hash[42]), 64);
	    		sph_haval256_5_close(&ctx_haval, (&hash[43]));

	    		sph_bmw512_init(&ctx_bmw);
	    		sph_bmw512 (&ctx_bmw, (&hash[43]), 64);
	    		sph_bmw512_close(&ctx_bmw, (&hash[44]));

	    		sph_groestl512_init(&ctx_groestl);
	    		sph_groestl512 (&ctx_groestl, (&hash[44]), 64);
	    		sph_groestl512_close(&ctx_groestl, (&hash[45]));

	    		sph_skein512_init(&ctx_skein);
	    		sph_skein512 (&ctx_skein, (&hash[45]), 64);
	    		sph_skein512_close(&ctx_skein, (&hash[46]));

	    		memcpy(output, &hash[46], 32);
		} 
		else if(hashChar == 101) 
		{
            		uint32_t hash[48];

	    		sph_blake512_init(&ctx_blake);
	    		sph_blake512 (&ctx_blake, input, len);
	    		sph_blake512_close(&ctx_blake, (&hash[0]));

	    		sph_bmw512_init(&ctx_bmw);
	    		sph_bmw512 (&ctx_bmw, (&hash[0]), 64);
	    		sph_bmw512_close(&ctx_bmw, (&hash[1]));

	    		sph_groestl512_init(&ctx_groestl);
	    		sph_groestl512 (&ctx_groestl, (&hash[1]), 64);
	    		sph_groestl512_close(&ctx_groestl, (&hash[2]));

	    		sph_skein512_init(&ctx_skein);
	    		sph_skein512 (&ctx_skein, (&hash[2]), 64);
	    		sph_skein512_close(&ctx_skein, (&hash[3]));

	    		sph_jh512_init(&ctx_jh);
	    		sph_jh512 (&ctx_jh, (&hash[3]), 64);
	    		sph_jh512_close(&ctx_jh, (&hash[4]));

	    		sph_keccak512_init(&ctx_keccak);
	    		sph_keccak512 (&ctx_keccak, (&hash[4]), 64);
	    		sph_keccak512_close(&ctx_keccak, (&hash[5]));

	    		sph_luffa512_init(&ctx_luffa);
	    		sph_luffa512 (&ctx_luffa, (&hash[5]), 64);
	    		sph_luffa512_close(&ctx_luffa, (&hash[6]));

	    		sph_cubehash512_init(&ctx_cubehash);
	    		sph_cubehash512 (&ctx_cubehash, (&hash[6]), 64);
	    		sph_cubehash512_close(&ctx_cubehash, (&hash[7]));

	    		sph_shavite512_init(&ctx_shavite);
	    		sph_shavite512(&ctx_shavite, (&hash[7]), 64);
	    		sph_shavite512_close(&ctx_shavite, (&hash[8]));

	    		sph_simd512_init(&ctx_simd);
	    		sph_simd512 (&ctx_simd, (&hash[8]), 64);
	    		sph_simd512_close(&ctx_simd, (&hash[9]));

	    		sph_echo512_init(&ctx_echo);
	    		sph_echo512 (&ctx_echo, (&hash[9]), 64);
	    		sph_echo512_close(&ctx_echo, (&hash[10]));

	    		sph_hamsi512_init(&ctx_hamsi);
	    		sph_hamsi512 (&ctx_hamsi, (&hash[10]), 64);
	    		sph_hamsi512_close(&ctx_hamsi, (&hash[11]));

	    		sph_fugue512_init(&ctx_fugue);
	    		sph_fugue512 (&ctx_fugue, (&hash[11]), 64);
	    		sph_fugue512_close(&ctx_fugue, (&hash[12]));

	    		sph_shabal512_init(&ctx_shabal);
	    		sph_shabal512 (&ctx_shabal, (&hash[12]), 64);
	    		sph_shabal512_close(&ctx_shabal, (&hash[13]));

	    		sph_whirlpool_init(&ctx_whirlpool);
	    		sph_whirlpool (&ctx_whirlpool, (&hash[13]), 64);
	    		sph_whirlpool_close(&ctx_whirlpool, (&hash[14]));

	    		sph_sha512_init(&ctx_sha2);
	    		sph_sha512 (&ctx_sha2, (&hash[14]), 64);
	    		sph_sha512_close(&ctx_sha2, (&hash[15]));

	    		sph_haval256_5_init(&ctx_haval);
	    		sph_haval256_5 (&ctx_haval, (&hash[15]), 64);
	    		sph_haval256_5_close(&ctx_haval, (&hash[16]));

	    		sph_bmw512_init(&ctx_bmw);
	    		sph_bmw512 (&ctx_bmw, (&hash[16]), 64);
	    		sph_bmw512_close(&ctx_bmw, (&hash[17]));

	    		sph_groestl512_init(&ctx_groestl);
	    		sph_groestl512 (&ctx_groestl, (&hash[17]), 64);
	    		sph_groestl512_close(&ctx_groestl, (&hash[18]));

	    		sph_skein512_init(&ctx_skein);
	    		sph_skein512 (&ctx_skein, (&hash[18]), 64);
	    		sph_skein512_close(&ctx_skein, (&hash[19]));

	    		sph_jh512_init(&ctx_jh);
	    		sph_jh512 (&ctx_jh, (&hash[19]), 64);
	    		sph_jh512_close(&ctx_jh, (&hash[20]));

	    		sph_keccak512_init(&ctx_keccak);
	    		sph_keccak512 (&ctx_keccak, (&hash[20]), 64);
	    		sph_keccak512_close(&ctx_keccak, (&hash[21]));

	    		sph_luffa512_init(&ctx_luffa);
	    		sph_luffa512 (&ctx_luffa, (&hash[21]), 64);
	    		sph_luffa512_close(&ctx_luffa, (&hash[22]));

	    		sph_cubehash512_init(&ctx_cubehash);
	   		sph_cubehash512 (&ctx_cubehash, (&hash[22]), 64);
	    		sph_cubehash512_close(&ctx_cubehash, (&hash[23]));

	    		sph_shavite512_init(&ctx_shavite);
	    		sph_shavite512(&ctx_shavite, (&hash[23]), 64);
	    		sph_shavite512_close(&ctx_shavite, (&hash[24]));

	    		sph_simd512_init(&ctx_simd);
	    		sph_simd512 (&ctx_simd, (&hash[24]), 64);
	    		sph_simd512_close(&ctx_simd, (&hash[25]));

	    		sph_echo512_init(&ctx_echo);
	    		sph_echo512 (&ctx_echo, (&hash[25]), 64);
	    		sph_echo512_close(&ctx_echo, (&hash[26]));

	    		sph_hamsi512_init(&ctx_hamsi);
	    		sph_hamsi512 (&ctx_hamsi, (&hash[26]), 64);
	    		sph_hamsi512_close(&ctx_hamsi, (&hash[27]));

	    		sph_fugue512_init(&ctx_fugue);
	    		sph_fugue512 (&ctx_fugue, &hash[27], 64);
	    		sph_fugue512_close(&ctx_fugue, &hash[28]);

	    		sph_shabal512_init(&ctx_shabal);
	    		sph_shabal512 (&ctx_shabal, &hash[28], 64);
	    		sph_shabal512_close(&ctx_shabal, &hash[29]);

	    		sph_whirlpool_init(&ctx_whirlpool);
	    		sph_whirlpool (&ctx_whirlpool, (&hash[29]), 64);
	    		sph_whirlpool_close(&ctx_whirlpool, (&hash[30]));

	    		sph_sha512_init(&ctx_sha2);
	    		sph_sha512 (&ctx_sha2, (&hash[30]), 64);
	    		sph_sha512_close(&ctx_sha2, (&hash[31]));

	    		sph_haval256_5_init(&ctx_haval);
	    		sph_haval256_5 (&ctx_haval, (&hash[31]), 64);
	    		sph_haval256_5_close(&ctx_haval, (&hash[32]));

	    		sph_shabal512_init(&ctx_shabal);
	    		sph_shabal512 (&ctx_shabal, (&hash[32]), 64);
	    		sph_shabal512_close(&ctx_shabal, (&hash[33]));

	    		sph_whirlpool_init(&ctx_whirlpool);
	    		sph_whirlpool (&ctx_whirlpool, (&hash[33]), 64);
	    		sph_whirlpool_close(&ctx_whirlpool, (&hash[34]));

	    		sph_sha512_init(&ctx_sha2);
	    		sph_sha512 (&ctx_sha2, (&hash[34]), 64);
	    		sph_sha512_close(&ctx_sha2, (&hash[35]));

	    		sph_haval256_5_init(&ctx_haval);
	    		sph_haval256_5 (&ctx_haval, (&hash[35]), 64);
	    		sph_haval256_5_close(&ctx_haval, (&hash[36]));

	    		sph_bmw512_init(&ctx_bmw);
	    		sph_bmw512 (&ctx_bmw, (&hash[36]), 64);
	    		sph_bmw512_close(&ctx_bmw, (&hash[37]));

	    		sph_groestl512_init(&ctx_groestl);
	    		sph_groestl512 (&ctx_groestl, (&hash[37]), 64);
	    		sph_groestl512_close(&ctx_groestl, (&hash[38]));

	    		sph_skein512_init(&ctx_skein);
	    		sph_skein512 (&ctx_skein, (&hash[38]), 64);
	    		sph_skein512_close(&ctx_skein, (&hash[39]));

	    		sph_shabal512_init(&ctx_shabal);
	    		sph_shabal512 (&ctx_shabal, (&hash[39]), 64);
	    		sph_shabal512_close(&ctx_shabal, (&hash[40]));

	    		sph_whirlpool_init(&ctx_whirlpool);
	    		sph_whirlpool (&ctx_whirlpool, (&hash[40]), 64);
	    		sph_whirlpool_close(&ctx_whirlpool, (&hash[41]));

	    		sph_sha512_init(&ctx_sha2);
	    		sph_sha512 (&ctx_sha2, (&hash[41]), 64);
	    		sph_sha512_close(&ctx_sha2, (&hash[42]));

	    		sph_haval256_5_init(&ctx_haval);
	    		sph_haval256_5 (&ctx_haval, (&hash[42]), 64);
	    		sph_haval256_5_close(&ctx_haval, (&hash[43]));

	    		sph_bmw512_init(&ctx_bmw);
	    		sph_bmw512 (&ctx_bmw, (&hash[43]), 64);
	    		sph_bmw512_close(&ctx_bmw, (&hash[44]));

	    		sph_groestl512_init(&ctx_groestl);
	    		sph_groestl512 (&ctx_groestl, (&hash[44]), 64);
	    		sph_groestl512_close(&ctx_groestl, (&hash[45]));

	    		sph_skein512_init(&ctx_skein);
	    		sph_skein512 (&ctx_skein, (&hash[45]), 64);
	    		sph_skein512_close(&ctx_skein, (&hash[46]));

	    		sph_groestl512_init(&ctx_groestl);
	    		sph_groestl512 (&ctx_groestl, (&hash[46]), 64);
	    		sph_groestl512_close(&ctx_groestl, (&hash[47]));

	    		memcpy(output, &hash[47], 32);
		} 
		else if(hashChar == 102) 
		{
            		uint32_t hash[49];

	    		sph_blake512_init(&ctx_blake);
	    		sph_blake512 (&ctx_blake, input, len);
	    		sph_blake512_close(&ctx_blake, (&hash[0]));

	    		sph_bmw512_init(&ctx_bmw);
	    		sph_bmw512 (&ctx_bmw, (&hash[0]), 64);
	    		sph_bmw512_close(&ctx_bmw, (&hash[1]));

	    		sph_groestl512_init(&ctx_groestl);
	    		sph_groestl512 (&ctx_groestl, (&hash[1]), 64);
	    		sph_groestl512_close(&ctx_groestl, (&hash[2]));

	    		sph_skein512_init(&ctx_skein);
	    		sph_skein512 (&ctx_skein, (&hash[2]), 64);
	    		sph_skein512_close(&ctx_skein, (&hash[3]));

	    		sph_jh512_init(&ctx_jh);
	    		sph_jh512 (&ctx_jh, (&hash[3]), 64);
	    		sph_jh512_close(&ctx_jh, (&hash[4]));

	    		sph_keccak512_init(&ctx_keccak);
	    		sph_keccak512 (&ctx_keccak, (&hash[4]), 64);
	    		sph_keccak512_close(&ctx_keccak, (&hash[5]));

	    		sph_luffa512_init(&ctx_luffa);
	    		sph_luffa512 (&ctx_luffa, (&hash[5]), 64);
	    		sph_luffa512_close(&ctx_luffa, (&hash[6]));

	    		sph_cubehash512_init(&ctx_cubehash);
	    		sph_cubehash512 (&ctx_cubehash, (&hash[6]), 64);
	    		sph_cubehash512_close(&ctx_cubehash, (&hash[7]));

	    		sph_shavite512_init(&ctx_shavite);
	    		sph_shavite512(&ctx_shavite, (&hash[7]), 64);
	    		sph_shavite512_close(&ctx_shavite, (&hash[8]));

	    		sph_simd512_init(&ctx_simd);
	    		sph_simd512 (&ctx_simd, (&hash[8]), 64);
	    		sph_simd512_close(&ctx_simd, (&hash[9]));

	    		sph_echo512_init(&ctx_echo);
	    		sph_echo512 (&ctx_echo, (&hash[9]), 64);
	    		sph_echo512_close(&ctx_echo, (&hash[10]));

	    		sph_hamsi512_init(&ctx_hamsi);
	    		sph_hamsi512 (&ctx_hamsi, (&hash[10]), 64);
	    		sph_hamsi512_close(&ctx_hamsi, (&hash[11]));

	    		sph_fugue512_init(&ctx_fugue);
	    		sph_fugue512 (&ctx_fugue, (&hash[11]), 64);
	    		sph_fugue512_close(&ctx_fugue, (&hash[12]));

	    		sph_shabal512_init(&ctx_shabal);
	    		sph_shabal512 (&ctx_shabal, (&hash[12]), 64);
	    		sph_shabal512_close(&ctx_shabal, (&hash[13]));

	    		sph_whirlpool_init(&ctx_whirlpool);
	    		sph_whirlpool (&ctx_whirlpool, (&hash[13]), 64);
	    		sph_whirlpool_close(&ctx_whirlpool, (&hash[14]));

	    		sph_sha512_init(&ctx_sha2);
	    		sph_sha512 (&ctx_sha2, (&hash[14]), 64);
	    		sph_sha512_close(&ctx_sha2, (&hash[15]));

	    		sph_haval256_5_init(&ctx_haval);
	    		sph_haval256_5 (&ctx_haval, (&hash[15]), 64);
	    		sph_haval256_5_close(&ctx_haval, (&hash[16]));

	    		sph_bmw512_init(&ctx_bmw);
	    		sph_bmw512 (&ctx_bmw, (&hash[16]), 64);
	    		sph_bmw512_close(&ctx_bmw, (&hash[17]));

	    		sph_groestl512_init(&ctx_groestl);
	    		sph_groestl512 (&ctx_groestl, (&hash[17]), 64);
	    		sph_groestl512_close(&ctx_groestl, (&hash[18]));

	    		sph_skein512_init(&ctx_skein);
	    		sph_skein512 (&ctx_skein, (&hash[18]), 64);
	    		sph_skein512_close(&ctx_skein, (&hash[19]));

	    		sph_jh512_init(&ctx_jh);
	    		sph_jh512 (&ctx_jh, (&hash[19]), 64);
	    		sph_jh512_close(&ctx_jh, (&hash[20]));

	    		sph_keccak512_init(&ctx_keccak);
	    		sph_keccak512 (&ctx_keccak, (&hash[20]), 64);
	    		sph_keccak512_close(&ctx_keccak, (&hash[21]));

	    		sph_luffa512_init(&ctx_luffa);
	    		sph_luffa512 (&ctx_luffa, (&hash[21]), 64);
	    		sph_luffa512_close(&ctx_luffa, (&hash[22]));

	    		sph_cubehash512_init(&ctx_cubehash);
	    		sph_cubehash512 (&ctx_cubehash, (&hash[22]), 64);
	    		sph_cubehash512_close(&ctx_cubehash, (&hash[23]));

	    		sph_shavite512_init(&ctx_shavite);
	    		sph_shavite512(&ctx_shavite, (&hash[23]), 64);
	    		sph_shavite512_close(&ctx_shavite, (&hash[24]));

	    		sph_simd512_init(&ctx_simd);
	   		sph_simd512 (&ctx_simd, (&hash[24]), 64);
	    		sph_simd512_close(&ctx_simd, (&hash[25]));

	    		sph_echo512_init(&ctx_echo);
	    		sph_echo512 (&ctx_echo, (&hash[25]), 64);
	    		sph_echo512_close(&ctx_echo, (&hash[26]));

	    		sph_hamsi512_init(&ctx_hamsi);
	    		sph_hamsi512 (&ctx_hamsi, (&hash[26]), 64);
	    		sph_hamsi512_close(&ctx_hamsi, (&hash[27]));

	    		sph_fugue512_init(&ctx_fugue);
	    		sph_fugue512 (&ctx_fugue, (&hash[27]), 64);
	    		sph_fugue512_close(&ctx_fugue, (&hash[28]));

	    		sph_shabal512_init(&ctx_shabal);
	    		sph_shabal512 (&ctx_shabal, (&hash[28]), 64);
	    		sph_shabal512_close(&ctx_shabal, (&hash[29]));

	    		sph_whirlpool_init(&ctx_whirlpool);
	    		sph_whirlpool (&ctx_whirlpool, (&hash[29]), 64);
	    		sph_whirlpool_close(&ctx_whirlpool, (&hash[30]));

	    		sph_sha512_init(&ctx_sha2);
	    		sph_sha512 (&ctx_sha2, (&hash[30]), 64);
	    		sph_sha512_close(&ctx_sha2, (&hash[31]));

	    		sph_haval256_5_init(&ctx_haval);
	    		sph_haval256_5 (&ctx_haval, (&hash[31]), 64);
	    		sph_haval256_5_close(&ctx_haval, (&hash[32]));

	    		sph_shabal512_init(&ctx_shabal);
	    		sph_shabal512 (&ctx_shabal, (&hash[32]), 64);
	    		sph_shabal512_close(&ctx_shabal, (&hash[33]));

	    		sph_whirlpool_init(&ctx_whirlpool);
	    		sph_whirlpool (&ctx_whirlpool, (&hash[33]), 64);
	    		sph_whirlpool_close(&ctx_whirlpool, (&hash[34]));

	    		sph_sha512_init(&ctx_sha2);
	    		sph_sha512 (&ctx_sha2, (&hash[34]), 64);
	    		sph_sha512_close(&ctx_sha2, (&hash[35]));

	    		sph_haval256_5_init(&ctx_haval);
	    		sph_haval256_5 (&ctx_haval, (&hash[35]), 64);
	    		sph_haval256_5_close(&ctx_haval, (&hash[36]));

	    		sph_bmw512_init(&ctx_bmw);
	    		sph_bmw512 (&ctx_bmw, (&hash[36]), 64);
	    		sph_bmw512_close(&ctx_bmw, (&hash[37]));

	    		sph_groestl512_init(&ctx_groestl);
	    		sph_groestl512 (&ctx_groestl, (&hash[37]), 64);
	    		sph_groestl512_close(&ctx_groestl, (&hash[38]));

	    		sph_skein512_init(&ctx_skein);
	    		sph_skein512 (&ctx_skein, (&hash[38]), 64);
	    		sph_skein512_close(&ctx_skein, (&hash[39]));

	    		sph_shabal512_init(&ctx_shabal);
	    		sph_shabal512 (&ctx_shabal, (&hash[39]), 64);
	    		sph_shabal512_close(&ctx_shabal, (&hash[40]));

	    		sph_whirlpool_init(&ctx_whirlpool);
	    		sph_whirlpool (&ctx_whirlpool, (&hash[40]), 64);
	    		sph_whirlpool_close(&ctx_whirlpool, (&hash[41]));

	    		sph_sha512_init(&ctx_sha2);
	    		sph_sha512 (&ctx_sha2, (&hash[41]), 64);
	    		sph_sha512_close(&ctx_sha2, (&hash[42]));

	    		sph_haval256_5_init(&ctx_haval);
	    		sph_haval256_5 (&ctx_haval, (&hash[42]), 64);
	    		sph_haval256_5_close(&ctx_haval, (&hash[43]));

	    		sph_bmw512_init(&ctx_bmw);
	    		sph_bmw512 (&ctx_bmw, (&hash[43]), 64);
	    		sph_bmw512_close(&ctx_bmw, (&hash[44]));

	    		sph_groestl512_init(&ctx_groestl);
	    		sph_groestl512 (&ctx_groestl, (&hash[44]), 64);
	    		sph_groestl512_close(&ctx_groestl, (&hash[45]));

	    		sph_skein512_init(&ctx_skein);
	    		sph_skein512 (&ctx_skein, (&hash[45]), 64);
	    		sph_skein512_close(&ctx_skein, (&hash[46]));

	    		sph_groestl512_init(&ctx_groestl);
	    		sph_groestl512 (&ctx_groestl, (&hash[46]), 64);
	   	 	sph_groestl512_close(&ctx_groestl, (&hash[47]));

	    		sph_skein512_init(&ctx_skein);
	    		sph_skein512 (&ctx_skein, (&hash[47]), 64);
	    		sph_skein512_close(&ctx_skein, (&hash[48]));

	    		memcpy(output, &hash[48], 32);
        	} 
		else 
		{
            		uint32_t hash[33];

	    		sph_blake512_init(&ctx_blake);
	    		sph_blake512 (&ctx_blake, input, len);
	    		sph_blake512_close(&ctx_blake, (&hash[0]));

	    		sph_bmw512_init(&ctx_bmw);
	    		sph_bmw512 (&ctx_bmw, (&hash[0]), 64);
	    		sph_bmw512_close(&ctx_bmw, (&hash[1]));

	    		sph_groestl512_init(&ctx_groestl);
	    		sph_groestl512 (&ctx_groestl, (&hash[1]), 64);
	    		sph_groestl512_close(&ctx_groestl, (&hash[2]));

	    		sph_skein512_init(&ctx_skein);
	    		sph_skein512 (&ctx_skein, (&hash[2]), 64);
	    		sph_skein512_close(&ctx_skein, (&hash[3]));

	    		sph_jh512_init(&ctx_jh);
	    		sph_jh512 (&ctx_jh, (&hash[3]), 64);
	    		sph_jh512_close(&ctx_jh, (&hash[4]));

	    		sph_keccak512_init(&ctx_keccak);
	    		sph_keccak512 (&ctx_keccak, (&hash[4]), 64);
	    		sph_keccak512_close(&ctx_keccak, (&hash[5]));

	    		sph_luffa512_init(&ctx_luffa);
	    		sph_luffa512 (&ctx_luffa, (&hash[5]), 64);
	    		sph_luffa512_close(&ctx_luffa, (&hash[6]));

	    		sph_cubehash512_init(&ctx_cubehash);
	    		sph_cubehash512 (&ctx_cubehash, (&hash[6]), 64);
	    		sph_cubehash512_close(&ctx_cubehash, (&hash[7]));

	    		sph_shavite512_init(&ctx_shavite);
	    		sph_shavite512(&ctx_shavite, (&hash[7]), 64);
	    		sph_shavite512_close(&ctx_shavite, (&hash[8]));

	    		sph_simd512_init(&ctx_simd);
	    		sph_simd512 (&ctx_simd, (&hash[8]), 64);
	    		sph_simd512_close(&ctx_simd, (&hash[9]));

	    		sph_echo512_init(&ctx_echo);
	    		sph_echo512 (&ctx_echo, (&hash[9]), 64);
	    		sph_echo512_close(&ctx_echo, (&hash[10]));

	    		sph_hamsi512_init(&ctx_hamsi);
	    		sph_hamsi512 (&ctx_hamsi, (&hash[10]), 64);
	    		sph_hamsi512_close(&ctx_hamsi, (&hash[11]));

	    		sph_fugue512_init(&ctx_fugue);
	    		sph_fugue512 (&ctx_fugue, (&hash[11]), 64);
	    		sph_fugue512_close(&ctx_fugue, (&hash[12]));

	    		sph_shabal512_init(&ctx_shabal);
	    		sph_shabal512 (&ctx_shabal, (&hash[12]), 64);
	    		sph_shabal512_close(&ctx_shabal, (&hash[13]));

	    		sph_whirlpool_init(&ctx_whirlpool);
	    		sph_whirlpool (&ctx_whirlpool, (&hash[13]), 64);
	    		sph_whirlpool_close(&ctx_whirlpool, (&hash[14]));

	    		sph_sha512_init(&ctx_sha2);
	    		sph_sha512 (&ctx_sha2, (&hash[14]), 64);
	    		sph_sha512_close(&ctx_sha2, (&hash[15]));

	    		sph_haval256_5_init(&ctx_haval);
	    		sph_haval256_5 (&ctx_haval, (&hash[15]), 64);
	    		sph_haval256_5_close(&ctx_haval, (&hash[16]));

	    		sph_bmw512_init(&ctx_bmw);
	    		sph_bmw512 (&ctx_bmw, (&hash[16]), 64);
	    		sph_bmw512_close(&ctx_bmw, (&hash[17]));

	    		sph_groestl512_init(&ctx_groestl);
	    		sph_groestl512 (&ctx_groestl, &hash[17], 64);
	    		sph_groestl512_close(&ctx_groestl, &hash[18]);

	    		sph_skein512_init(&ctx_skein);
	    		sph_skein512 (&ctx_skein, &hash[18], 64);
	    		sph_skein512_close(&ctx_skein, &hash[19]);

	    		sph_jh512_init(&ctx_jh);
	    		sph_jh512 (&ctx_jh, &hash[19], 64);
	    		sph_jh512_close(&ctx_jh, &hash[20]);

	    		sph_keccak512_init(&ctx_keccak);
	    		sph_keccak512 (&ctx_keccak, &hash[20], 64);
	    		sph_keccak512_close(&ctx_keccak, &hash[21]);

	    		sph_luffa512_init(&ctx_luffa);
	    		sph_luffa512 (&ctx_luffa, &hash[21], 64);
	    		sph_luffa512_close(&ctx_luffa, &hash[22]);

	    		sph_cubehash512_init(&ctx_cubehash);
	    		sph_cubehash512 (&ctx_cubehash, &hash[22], 64);
	    		sph_cubehash512_close(&ctx_cubehash, &hash[23]);

	    		sph_shavite512_init(&ctx_shavite);
	    		sph_shavite512(&ctx_shavite, &hash[23], 64);
	    		sph_shavite512_close(&ctx_shavite, &hash[24]);

	    		sph_simd512_init(&ctx_simd);
	    		sph_simd512 (&ctx_simd, &hash[24], 64);
	    		sph_simd512_close(&ctx_simd, &hash[25]);

	    		sph_echo512_init(&ctx_echo);
	    		sph_echo512 (&ctx_echo, &hash[25], 64);
	    		sph_echo512_close(&ctx_echo, &hash[26]);

	    		sph_hamsi512_init(&ctx_hamsi);
	    		sph_hamsi512 (&ctx_hamsi, &hash[26], 64);
	    		sph_hamsi512_close(&ctx_hamsi, &hash[27]);

	   		sph_fugue512_init(&ctx_fugue);
	    		sph_fugue512 (&ctx_fugue, &hash[27], 64);
	    		sph_fugue512_close(&ctx_fugue, &hash[28]);

	    		sph_shabal512_init(&ctx_shabal);
	    		sph_shabal512 (&ctx_shabal, &hash[28], 64);
	    		sph_shabal512_close(&ctx_shabal, &hash[29]);

	    		sph_whirlpool_init(&ctx_whirlpool);
	    		sph_whirlpool (&ctx_whirlpool, &hash[29], 64);
	    		sph_whirlpool_close(&ctx_whirlpool, &hash[30]);

	    		sph_sha512_init(&ctx_sha2);
	    		sph_sha512 (&ctx_sha2, &hash[30], 64);
	    		sph_sha512_close(&ctx_sha2, &hash[31]);

	    		sph_haval256_5_init(&ctx_haval);
	    		sph_haval256_5 (&ctx_haval, &hash[31], 64);
	    		sph_haval256_5_close(&ctx_haval, &hash[32]);

	    		memcpy(output, &hash[32], 32);
        	}
	}
	else 
	{
		uint32_t hash[33];

		sph_blake512_init(&ctx_blake);
		sph_blake512 (&ctx_blake, input, len);
		sph_blake512_close(&ctx_blake, &hash[0]);

		sph_bmw512_init(&ctx_bmw);
		sph_bmw512 (&ctx_bmw, &hash[0], 64);
		sph_bmw512_close(&ctx_bmw, &hash[1]);

		sph_groestl512_init(&ctx_groestl);
		sph_groestl512 (&ctx_groestl, &hash[1], 64);
		sph_groestl512_close(&ctx_groestl, &hash[2]);

		sph_skein512_init(&ctx_skein);
		sph_skein512 (&ctx_skein, (&hash[2]), 64);
		sph_skein512_close(&ctx_skein, (&hash[3]));

		sph_jh512_init(&ctx_jh);
		sph_jh512 (&ctx_jh, (&hash[3]), 64);
		sph_jh512_close(&ctx_jh, (&hash[4]));

		sph_keccak512_init(&ctx_keccak);
		sph_keccak512 (&ctx_keccak, (&hash[4]), 64);
		sph_keccak512_close(&ctx_keccak, (&hash[5]));

		sph_luffa512_init(&ctx_luffa);
		sph_luffa512 (&ctx_luffa, (&hash[5]), 64);
		sph_luffa512_close(&ctx_luffa, (&hash[6]));

		sph_cubehash512_init(&ctx_cubehash);
		sph_cubehash512 (&ctx_cubehash, (&hash[6]), 64);
		sph_cubehash512_close(&ctx_cubehash, (&hash[7]));

		sph_shavite512_init(&ctx_shavite);
		sph_shavite512(&ctx_shavite, (&hash[7]), 64);
		sph_shavite512_close(&ctx_shavite, (&hash[8]));

		sph_simd512_init(&ctx_simd);
		sph_simd512 (&ctx_simd, (&hash[8]), 64);
		sph_simd512_close(&ctx_simd, (&hash[9]));

		sph_echo512_init(&ctx_echo);
		sph_echo512 (&ctx_echo, (&hash[9]), 64);
		sph_echo512_close(&ctx_echo, (&hash[10]));

		sph_hamsi512_init(&ctx_hamsi);
		sph_hamsi512 (&ctx_hamsi, (&hash[10]), 64);
		sph_hamsi512_close(&ctx_hamsi, (&hash[11]));

		sph_fugue512_init(&ctx_fugue);
		sph_fugue512 (&ctx_fugue, (&hash[11]), 64);
		sph_fugue512_close(&ctx_fugue, (&hash[12]));

		sph_shabal512_init(&ctx_shabal);
		sph_shabal512 (&ctx_shabal, (&hash[12]), 64);
		sph_shabal512_close(&ctx_shabal, (&hash[13]));

		sph_whirlpool_init(&ctx_whirlpool);
		sph_whirlpool (&ctx_whirlpool, (&hash[13]), 64);
		sph_whirlpool_close(&ctx_whirlpool, (&hash[14]));

		sph_sha512_init(&ctx_sha2);
		sph_sha512 (&ctx_sha2, &hash[14], 64);
		sph_sha512_close(&ctx_sha2, &hash[15]);

		sph_haval256_5_init(&ctx_haval);
		sph_haval256_5 (&ctx_haval, &hash[15], 64);
		sph_haval256_5_close(&ctx_haval, &hash[16]);

		sph_bmw512_init(&ctx_bmw);
		sph_bmw512 (&ctx_bmw, &hash[16], 64);
		sph_bmw512_close(&ctx_bmw, &hash[17]);

		sph_groestl512_init(&ctx_groestl);
		sph_groestl512 (&ctx_groestl, &hash[17], 64);
		sph_groestl512_close(&ctx_groestl, &hash[18]);

		sph_skein512_init(&ctx_skein);
		sph_skein512 (&ctx_skein, &hash[18], 64);
		sph_skein512_close(&ctx_skein, &hash[19]);

		sph_jh512_init(&ctx_jh);
		sph_jh512 (&ctx_jh, &hash[19], 64);
		sph_jh512_close(&ctx_jh, &hash[20]);

		sph_keccak512_init(&ctx_keccak);
		sph_keccak512 (&ctx_keccak, &hash[20], 64);
		sph_keccak512_close(&ctx_keccak, &hash[21]);

		sph_luffa512_init(&ctx_luffa);
		sph_luffa512 (&ctx_luffa, &hash[21], 64);
		sph_luffa512_close(&ctx_luffa, &hash[22]);

		sph_cubehash512_init(&ctx_cubehash);
		sph_cubehash512 (&ctx_cubehash, &hash[22], 64);
		sph_cubehash512_close(&ctx_cubehash, &hash[23]);

		sph_shavite512_init(&ctx_shavite);
		sph_shavite512(&ctx_shavite, &hash[23], 64);
		sph_shavite512_close(&ctx_shavite, &hash[24]);

		sph_simd512_init(&ctx_simd);
		sph_simd512 (&ctx_simd, &hash[24], 64);
		sph_simd512_close(&ctx_simd, &hash[25]);

		sph_echo512_init(&ctx_echo);
		sph_echo512 (&ctx_echo, &hash[25], 64);
		sph_echo512_close(&ctx_echo, &hash[26]);

		sph_hamsi512_init(&ctx_hamsi);
		sph_hamsi512 (&ctx_hamsi, &hash[26], 64);
		sph_hamsi512_close(&ctx_hamsi, &hash[27]);

		sph_fugue512_init(&ctx_fugue);
		sph_fugue512 (&ctx_fugue, &hash[27], 64);
		sph_fugue512_close(&ctx_fugue, &hash[28]);

		sph_shabal512_init(&ctx_shabal);
		sph_shabal512 (&ctx_shabal, &hash[28], 64);
		sph_shabal512_close(&ctx_shabal, &hash[29]);

		sph_whirlpool_init(&ctx_whirlpool);
		sph_whirlpool (&ctx_whirlpool, &hash[29], 64);
		sph_whirlpool_close(&ctx_whirlpool, &hash[30]);

		sph_sha512_init(&ctx_sha2);
		sph_sha512 (&ctx_sha2, &hash[30], 64);
		sph_sha512_close(&ctx_sha2, &hash[31]);

		sph_haval256_5_init(&ctx_haval);
		sph_haval256_5 (&ctx_haval, &hash[31], 64);
		sph_haval256_5_close(&ctx_haval, &hash[32]);

		memcpy(output, &hash[32], 32);
    	}
}