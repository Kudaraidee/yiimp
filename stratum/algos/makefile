
CC=gcc -g -ggdb

#CFLAGS=-c -g -I /usr/include/mysql
#LDFLAGS=-g

CXXFLAGS = -O2 -I.. -march=native -DNO_AES_NI -DBLAKE3_NO_SSE2 -DBLAKE3_NO_SSE41 -DXKCP_has_KeccakP1600
CFLAGS= $(CXXFLAGS) -std=gnu99
LDFLAGS=-O2 -lgmp

SOURCES=lyra2re.c lyra2v2.c Lyra2.c lyra2z.c lyra2z330.c Lyra2-z.c Sponge.c allium.c SWIFFTX/SWIFFTX.c lyra2vc0ban.c lyra2v3.c \
	c11.c x11.c x12.c x13.c hsr14.c x14.c x15.c x17.c x17r.c x18.c x11k.c x11kvs.c x22.c \
	blake.c blakecoin.c blake2s.c jha.c keccak.c lbry.c tribus.c \
	deep.c fresh.c groestl.c neoscrypt.c nist5.c quark.c qubit.c skein.c skein2.c bmw512.c \
	bitcore.c timetravel.c x11evo.c x16r.c x16rv2.c x16s.c xevan.c bastion.c hmq17.c sonoa.c \
	bmw.c luffa.c pentablake.c vitalium.c whirlpool.c whirlpoolx.c zr5.c \
	scrypt.c scryptn.c sha256.c sha256t.c sha256csm.c \
	x16rt.c sha256-d.c minotaur.c minotaurx.c curvehash.c megabtx.c megamec.c memehash.c \
	yescrypt.c yescrypt-opt.c sha256_Y.c \
	yespower/yespower-platform.c yespower/yespower-combined.c sha256-P.c yespower/yespower-blake2b.c yespower/crypto/blake2b-yp.c \
	a5a.c a5amath.c m7m.c magimath.cpp velvet.c \
	blake2/blake2b.c blake2-ref/blake2b.c blake2-ref/blake2s.c ar2/argon2.c ar2/core.c ar2/encoding.c ar2/opt.c ar2/thread.c \
	hive.c pomelo.c hex.c argon2d.c geek.c bcd.c balloon.c \
	phi.c phi2.c phi5.c polytimos.c rainforest.c renesis.c skunk.c sib.c veltor.c gost.c aergo.c x22i.c x21s.c lbk3.c pipehash.c dedal.c x20r.c gltalgos.c lane.c x25x.c \
	beenode.c honeycomb/facet_one.c honeycomb/facet_two.c honeycomb/facet_three.c honeycomb/facet_four.c honeycomb/facet_five.c honeycomb/facet_six.c honeycomb/facets_helper.c \
	gr.c cryptonote/crypto/aesb.c  cryptonote/crypto/c_blake256.c  cryptonote/crypto/c_groestl.c  cryptonote/crypto/c_jh.c  cryptonote/crypto/c_keccak.c  cryptonote/crypto/c_skein.c  cryptonote/crypto/hash.c  cryptonote/crypto/oaes_lib.c \
	cryptonote/cryptonight.c \
	cryptonote/cryptonight_dark.c cryptonote/cryptonight_dark_lite.c cryptonote/cryptonight_fast.c \
	cryptonote/cryptonight_lite.c cryptonote/cryptonight_soft_shell.c cryptonote/cryptonight_turtle.c \
	cryptonote/cryptonight_turtle_lite.c anime.c aurum.c \
	equihash.cpp \
	heavyhash/heavyhash.c heavyhash/keccak_tiny.c cosa.c 0x10.c mike.c sha512_256.c sha3d.c sha256dt.c skydoge.c flex.c \
	xelisv2.c blake3/blake3.c blake3/blake3_dispatch.c blake3/blake3_portable.c xelisv2-pepew/chacha20.c xelisv2-pepew/chacha20_dispatch.c \
	rinhash.c sha3/KeccakP-1600-reference.c sha3/KeccakSponge.c sha3/SimpleFIPS202.c
	
OBJECTS=$(SOURCES:%.c=%.o) $(SOURCES:%.cpp=%.o) $(SOURCES:%.cc=%.o)
OUTPUT=libalgos.a

all: $(SOURCES) $(OUTPUT)

$(OUTPUT): $(OBJECTS)
	ar rc $@ $(OBJECTS)
	touch ../stratum.cpp

.cpp.o:
	$(CC) $(CXXFLAGS) -c $< -o $@

.c.o:
	$(CC) $(CFLAGS) -c $< -o $@

#	$(CC) $(CFLAGS) -std=gnu99 -Wno-pointer-sign -Wno-pointer-to-int-cast -funroll-loops -fvariable-expansion-in-unroller -fmerge-all-constants -fbranch-target-load-optimize2 -fsched2-use-superblocks -falign-loops=16 -falign-functions=16 -falign-jumps=16 -falign-labels=16 -Ofast -flto -fuse-linker-plugin -ftree-loop-if-convert-stores -DUSE_ASM -pg $<

clean:
	rm -f *.o
	rm -f ar2/*.o
	rm -f blake2/*.o	
	rm -f blake2-ref/*.o	
	rm -f blake3/*.o
	rm -f cryptonote/*.o
	rm -f heavyhash/*.o	
	rm -f honeycomb/*.o	
	rm -f SWIFFTX/*.o	
	rm -f yespower/*.o	
	rm -f xelisv2-pepew/*.o
