
CC=gcc -g -ggdb
CXX=g++ -g -ggdb
include ../arch.mk

#CFLAGS=-c -g -I /usr/include/mysql
#LDFLAGS=-g

CXXFLAGS = -O2 -I.. -march=$(ARCH) -DNO_AES_NI -DBLAKE3_NO_SSE2 -DBLAKE3_NO_SSE41 -DXKCP_has_KeccakP1600
CFLAGS= $(CXXFLAGS) -std=gnu99 -fno-strict-aliasing -fwrapv
LDFLAGS=-O2 -lgmp

SOURCES=lyra2/lyra2re.c lyra2/lyra2v2.c lyra2/Lyra2.c lyra2/lyra2z.c lyra2/lyra2z330.c lyra2/Lyra2-z.c lyra2/Sponge.c lyra2/allium.c ../third_party/SWIFFTX/SWIFFTX.c lyra2/lyra2vc0ban.c lyra2/lyra2v3.c \
	x11/c11.c x11/x11.c x11/x12.c x11/x13.c x11/hsr14.c x11/x14.c x11/x15.c x11/x17.c x11/x17r.c x11/x18.c x11/x11k.c x11/x11kvs.c \
	decred.c blake/blake.c blake/blakecoin.c blake/blake2s.c x11/jha.c misc/keccak.c misc/lbry.c x11/tribus.c \
	x11/deep.c x11/fresh.c misc/groestl.c scrypt/neoscrypt.c x11/nist5.c x11/quark.c x11/qubit.c misc/skein.c misc/skein2.c misc/bmw512.c \
	x11/bitcore.c x11/timetravel.c x11/x11evo.c x11/x16r.c x11/x16rv2.c x11/x16s.c x11/xevan.c x11/bastion.c x11/hmq17.c x11/sonoa.c \
	misc/bmw.c misc/luffa.c blake/pentablake.c misc/vitalium.c misc/whirlpool.c misc/whirlpoolx.c misc/zr5.c \
	scrypt/scrypt.c scrypt/scryptn.c sha2/sha256.c sha2/sha256t.c sha2/sha256csm.c \
	x11/x16rt.c misc/minotaur.c misc/minotaurx.c misc/curvehash.c x11/megabtx.c x11/megamec.c misc/memehash.c \
	yescrypt/yescrypt.c yescrypt/yescrypt-opt.c yescrypt/sha256_Y.c \
	yespower/yespower-platform.c yespower/yespower-combined.c sha2/sha256-P.c yespower/yespower-blake2b.c yespower/crypto/blake2b-yp.c \
	misc/a5a.c misc/a5amath.c misc/m7m.c misc/magimath.cpp misc/velvet.c \
	argon2/blake2/blake2b.c ../third_party/blake2-ref/blake2s.c argon2/ar2/argon2.c argon2/ar2/core.c argon2/ar2/encoding.c argon2/ar2/opt.c argon2/ar2/thread.c \
	misc/hive.c misc/pomelo.c x11/hex.c argon2/argon2d.c x11/geek.c x11/bcd.c misc/balloon.c misc/hoohash.c \
	x11/phi.c x11/phi2.c x11/phi5.c x11/polytimos.c misc/rainforest.c x11/renesis.c x11/skunk.c x11/sib.c x11/veltor.c common/sph512.c misc/aergo.c x11/x22i.c x11/x21s.c x11/lbk3.c misc/pipehash.c x11/dedal.c x11/x20r.c misc/gltalgos.c misc/lane.c x11/x25x.c \
	misc/beenode.c honeycomb/facet_one.c honeycomb/facet_two.c honeycomb/facet_three.c honeycomb/facet_four.c honeycomb/facet_five.c honeycomb/facet_six.c honeycomb/facets_helper.c \
	misc/gr.c cryptonight/crypto/aesb.c  cryptonight/crypto/c_blake256.c  cryptonight/crypto/c_groestl.c  cryptonight/crypto/c_jh.c \
	cryptonight/crypto/c_keccak.c  cryptonight/crypto/c_skein.c  cryptonight/crypto/hash.c  cryptonight/crypto/oaes_lib.c \
	cryptonight/cryptonight.c \
	cryptonight/cryptonight_dark.c cryptonight/cryptonight_dark_lite.c cryptonight/cryptonight_fast.c \
	cryptonight/cryptonight_lite.c cryptonight/cryptonight_soft_shell.c cryptonight/cryptonight_turtle.c \
	cryptonight/cryptonight_turtle_lite.c misc/anime.c misc/soterg.c \
	equihash/equihash.cpp \
	heavyhash/heavyhash.c heavyhash/keccak_tiny.c misc/cosa.c misc/0x10.c misc/mike.c sha2/sha512_256.c misc/sha3d.c sha2/sha256dt.c misc/skydoge.c misc/flex.c \
	misc/xelisv2.c ../third_party/blake3/blake3.c ../third_party/blake3/blake3_dispatch.c ../third_party/blake3/blake3_portable.c xelisv2-pepew/chacha20.c xelisv2-pepew/chacha20_dispatch.c \
	misc/rinhash.c ../third_party/keccak-xkcp/KeccakP-1600-reference.c ../third_party/keccak-xkcp/KeccakSponge.c ../third_party/keccak-xkcp/SimpleFIPS202.c
	
OBJECTS=$(patsubst %.cpp,%.o,$(SOURCES:.c=.o))
OUTPUT=libalgos.a

all: $(SOURCES) $(OUTPUT)

# rebuild from scratch: blake2s.o exists twice (algos/, blake2-ref/), and
# "ar r" into an existing archive would replace one with the other
$(OUTPUT): $(OBJECTS)
	rm -f $@
	ar rcs $@ $(OBJECTS)
	touch ../stratum.cpp

.cpp.o:
	$(CXX) $(CXXFLAGS) -c $< -o $@

.c.o:
	$(CC) $(CFLAGS) -c $< -o $@

# consensus FMAs are explicit fma() calls; no other contraction allowed
misc/hoohash.o: CFLAGS += -ffp-contract=off

#	$(CC) $(CFLAGS) -std=gnu99 -Wno-pointer-sign -Wno-pointer-to-int-cast -funroll-loops -fvariable-expansion-in-unroller -fmerge-all-constants -fbranch-target-load-optimize2 -fsched2-use-superblocks -falign-loops=16 -falign-functions=16 -falign-jumps=16 -falign-labels=16 -Ofast -flto -fuse-linker-plugin -ftree-loop-if-convert-stores -DUSE_ASM -pg $<

clean:
	find . -name '*.o' -delete
	find ../third_party/keccak-xkcp ../third_party/blake2-ref ../third_party/blake3 ../third_party/SWIFFTX -name '*.o' -delete
	rm -f $(OUTPUT)
