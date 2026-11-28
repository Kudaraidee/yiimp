#include "beenode.h"
#include <stdlib.h>
#include <stdint.h>
#include <string.h>
#include <stdio.h>


#include "algos/honeycomb/facet_one.h"
#include "algos/honeycomb/facet_two.h"
#include "algos/honeycomb/facet_three.h"
#include "algos/honeycomb/facet_four.h"
#include "algos/honeycomb/facet_five.h"
#include "algos/honeycomb/facet_six.h"



void HoneyBee( const unsigned char *in, unsigned int sz, unsigned char *out ){
	memcpy( &out[ 0], &in[0],     36 );
	memcpy( &out[36], &in[sz-28], 28 );
}

void xor64byte( unsigned char *a, unsigned char *b, unsigned char *out ){
	for( int i = 0; i < 64; i++){		
		out[i] = a[i] ^ b[i];         
	}
}

void beenode_hash(const char* input, char* output, unsigned int len){
    
	facet_one_context		ctx_one;
	facet_two_context		ctx_two;
	facet_three_context     ctx_three;
    facet_four_context		ctx_four;
	facet_five_context		ctx_five;
	facet_six_context     	ctx_six;
    
	unsigned char honey[64];
	
    unsigned char hash0[64];
	unsigned char hash1[64];
	unsigned char hash2[64];
	unsigned char hash3[64];
	unsigned char hash4[64];
	unsigned char hash5[64];
	unsigned char hash6[64];
	unsigned char hash7[64];
	unsigned char hash8[64];
	unsigned char hash9[64];
	unsigned char hash10[64];
	unsigned char hash11[64];
	
	HoneyBee( (const unsigned char*)input, len, honey );
    facet_one_init(&ctx_one);
    facet_one(&ctx_one, input, len );
    facet_one_close(&ctx_one, hash0 );
    facet_four_init(&ctx_four);
    facet_four(&ctx_four, input, len );
    facet_four_close(&ctx_four, hash1 );
	xor64byte( honey, hash1, hash2 );
	xor64byte( hash0, hash2, hash3 );
    facet_two_init( &ctx_two );
    facet_two( &ctx_two, hash3, 64 );
    facet_two_close( &ctx_two, hash4 );
    facet_five_init(&ctx_five);
    facet_five (&ctx_five, input, len );
    facet_five_close(&ctx_five, hash5 );
	xor64byte( honey, hash5, hash6 );
	xor64byte( hash4, hash6, hash7 );
	facet_three_init(  &ctx_three  );
    facet_three (  &ctx_three, hash7, 64   );
    facet_three_close(   &ctx_three, hash8    );
    facet_six_init(&ctx_six);
    facet_six( &ctx_six, input, len );
    facet_six_close(&ctx_six, hash9);
	xor64byte( honey, hash9, hash10 );	
	xor64byte( hash8, hash10, hash11 );

    memcpy(output, hash11, 32);
}

