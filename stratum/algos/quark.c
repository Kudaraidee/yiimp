/*-
 * Copyright 2009 Colin Percival, 2011 ArtForz, 2013 Neisklar,
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 * 1. Redistributions of source code must retain the above copyright
 *    notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED BY THE AUTHOR AND CONTRIBUTORS ``AS IS'' AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 * ARE DISCLAIMED.  IN NO EVENT SHALL THE AUTHOR OR CONTRIBUTORS BE LIABLE
 * FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
 * DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS
 * OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 * HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY
 * OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF
 * SUCH DAMAGE.
 *
 * This file was originally written by Colin Percival as part of the Tarsnap
 * online backup system.
 */

#include "quark.h"
#include <stdlib.h>
#include <stdint.h>
#include <string.h>
#include <stdio.h>
#include "algos/common/sph512.h"

#if 0
static __inline uint32_t
be32dec(const void *pp)
{
	const uint8_t *p = (uint8_t const *)pp;

	return ((uint32_t)(p[3]) + ((uint32_t)(p[2]) << 8) +
	    ((uint32_t)(p[1]) << 16) + ((uint32_t)(p[0]) << 24));
}

static __inline void
be32enc(void *pp, uint32_t x)
{
	uint8_t * p = (uint8_t *)pp;

	p[3] = x & 0xff;
	p[2] = (x >> 8) & 0xff;
	p[1] = (x >> 16) & 0xff;
	p[0] = (x >> 24) & 0xff;
}

static __inline uint32_t
le32dec(const void *pp)
{
	const uint8_t *p = (uint8_t const *)pp;

	return ((uint32_t)(p[0]) + ((uint32_t)(p[1]) << 8) +
	    ((uint32_t)(p[2]) << 16) + ((uint32_t)(p[3]) << 24));
}

static __inline void
le32enc(void *pp, uint32_t x)
{
	uint8_t * p = (uint8_t *)pp;

	p[0] = x & 0xff;
	p[1] = (x >> 8) & 0xff;
	p[2] = (x >> 16) & 0xff;
	p[3] = (x >> 24) & 0xff;
}

/*
 * Encode a length len/4 vector of (uint32_t) into a length len vector of
 * (unsigned char) in big-endian form.  Assumes len is a multiple of 4.
 */
static void
be32enc_vect(unsigned char *dst, const uint32_t *src, uint32_t len)
{
	size_t i;

	for (i = 0; i < len / 4; i++)
		be32enc(dst + i * 4, src[i]);
}

/*
 * Decode a big-endian length len vector of (unsigned char) into a length
 * len/4 vector of (uint32_t).  Assumes len is a multiple of 4.
 */
static void
be32dec_vect(uint32_t *dst, const unsigned char *src, uint32_t len)
{
	size_t i;

	for (i = 0; i < len / 4; i++)
		dst[i] = be32dec(src + i * 4);
}
#endif

void quark_hash(const char* input, char* output, uint32_t len)
{

    uint32_t mask = 8;
    uint32_t zero = 0;

    uint32_t hashA[16], hashB[16];

    sph512(SPH_BLAKE512, input, len, hashA);	 //0

    sph512(SPH_BMW512, hashA, 64, hashB);   //1

    if ((hashB[0] & mask) != zero)   //1
    {
        sph512(SPH_GROESTL512, hashB, 64, hashA); //2
    }
    else
    {
        sph512(SPH_SKEIN512, hashB, 64, hashA); //2
    }

    sph512(SPH_GROESTL512, hashA, 64, hashB); //3

    sph512(SPH_JH512, hashB, 64, hashA); //4

    if ((hashA[0] & mask) != zero) //4
    {
        sph512(SPH_BLAKE512, hashA, 64, hashB); //5
    }
    else
    {
        sph512(SPH_BMW512, hashA, 64, hashB);   //5
    }

    sph512(SPH_KECCAK512, hashB, 64, hashA); //6

    sph512(SPH_SKEIN512, hashA, 64, hashB); //7

    if ((hashB[0] & mask) != zero) //7
    {
        sph512(SPH_KECCAK512, hashB, 64, hashA); //8
    }
    else
    {
        sph512(SPH_JH512, hashB, 64, hashA); //8
    }

	memcpy(output, hashA, 32);

/*
	printf("result: ");
	for (ii=0; ii < 32; ii++)
	{
		printf ("%.2x",((uint8_t*)output)[ii]);
	}
	printf ("\n");
*/
}

