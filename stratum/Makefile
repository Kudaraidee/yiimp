
CC= gcc -no-pie 

CFLAGS= -g -march=native -I./ -Ithird_party/randomx/src -Ixhash/libxhash/include
SQLFLAGS= `mysql_config --cflags --libs`

#CFLAGS=-c -O2 -I /usr/include/mysql
#LDFLAGS=-O2 `mysql_config --libs`

LDLIBS=randomx/librandomx.a third_party/iniparser/libiniparser.a algos/libalgos.a third_party/sph/libhash.a -Isecp256k1/include secp256k1/.libs/libsecp256k1.a -lpthread -lgmp -lm -lstdc++ -lssl -lcrypto -lsodium
LDLIBS+=-lmysqlclient -lcurl

SOURCES=stratum.cpp db.cpp coind.cpp coind_aux.cpp coind_template.cpp coind_submit.cpp util.cpp list.cpp uint256.cpp arith_uint256.cpp utilstrencodings.cpp \
	rpc.cpp job.cpp job_send.cpp job_core.cpp merkle.cpp share.cpp socket.cpp tls.cpp coinbase.cpp \
	client.cpp client_submit.cpp client_core.cpp client_difficulty.cpp remote.cpp remote_template.cpp \
	user.cpp object.cpp json.cpp base58.cpp humanize_number.cpp
PP=algos/progpow
SOURCES_KAWPOW=$(PP)/lib/keccak/keccak.c $(PP)/lib/keccak/keccakf800.c $(PP)/lib/keccak/keccakf1600.c $(PP)/lib/ethash/primes.c 	$(PP)/lib/ethash/ethash.cpp $(PP)/kawpow/lib/progpow.cpp $(PP)/kawpow/hash.cpp protocol/kawpow/kawpow.cpp 	$(PP)/firopow/lib/progpow.cpp $(PP)/firopow/overrides.cpp $(PP)/firopow/hash.cpp 	$(PP)/phihash/lib/progpow.cpp $(PP)/phihash/hash.cpp 	$(PP)/meowpow/lib/progpow.cpp $(PP)/meowpow/hash.cpp 	$(PP)/evrprogpow/lib/progpow.cpp $(PP)/evrprogpow/hash.cpp
SOURCES_MERAKI_C=$(PP)/meraki/lib/keccak/keccak.c
SOURCES_MERAKI_CPP=$(PP)/meraki/lib/meraki/meraki.cpp $(PP)/meraki/lib/meraki/managed.cpp $(PP)/meraki/lib/meraki/progpow.cpp $(PP)/meraki/hash.cpp protocol/meraki/meraki.cpp
SOURCES_MERAKI=$(SOURCES_MERAKI_C) $(SOURCES_MERAKI_CPP)
SOURCES_ETHASH=protocol/ethash/params.cpp protocol/ethash/context.cpp protocol/ethash/engine.cpp protocol/ethash/work.cpp
SOURCES_XHASH_C=xhash/libxhash/lib/keccak/keccak.c xhash/libxhash/lib/sha3/sha3.c xhash/libxhash/lib/xhash/primes.c
SOURCES_XHASH_CPP=xhash/libxhash/lib/xhash/xhash.cpp
SOURCES_XHASH=$(SOURCES_XHASH_C) $(SOURCES_XHASH_CPP)
SOURCES_RANDOMSCASH=algos/randomx/randomscash.cpp
SOURCES_HOMESCRYPT=algos/misc/homescrypt.cpp
SOURCES_NEBHASH_C=algos/nebhash/blake3_shared.c algos/nebhash/chacha8.c algos/nebhash/nebhash2.c algos/nebhash/shares.c
SOURCES_NEBHASH_CPP=protocol/nebhash/work.cpp
SOURCES_NEBHASH=$(SOURCES_NEBHASH_C) $(SOURCES_NEBHASH_CPP)

CFLAGS += -DHAVE_CURL
include arch.mk
CFLAGS := $(filter-out -march=native,$(CFLAGS)) -march=$(ARCH)
SOURCES += rpc_curl.cpp
#LDCURL = $(shell /usr/bin/pkg-config --static --libs libcurl)
#LDFLAGS += $(LDCURL)

OBJECTS=$(SOURCES:.cpp=.o) $(SOURCES_KAWPOW:.cpp=.o) $(SOURCES_MERAKI_C:.c=.o) $(SOURCES_MERAKI_CPP:.cpp=.o) $(SOURCES_ETHASH:.cpp=.o) $(SOURCES_XHASH_C:.c=.o) $(SOURCES_XHASH_CPP:.cpp=.o) $(SOURCES_RANDOMSCASH:.cpp=.o) $(SOURCES_HOMESCRYPT:.cpp=.o) $(SOURCES_NEBHASH_C:.c=.o) $(SOURCES_NEBHASH_CPP:.cpp=.o)
OUTPUT=stratum

CODEDIR1=algos
CODEDIR2=third_party/sph
CODEDIR3=third_party/iniparser
CODEDIR4=third_party/secp256k1
CODEDIR5=third_party/randomx


.PHONY: all buildonly gitsubmodules install clean projectcode1 projectcode2 projectcode3 projectcode4 projectcode5

# submodules first, then the build (safe with -j); SELF: GNU make would
# read GNUmakefile in a sub-make without -f
SELF := $(firstword $(MAKEFILE_LIST))
all: gitsubmodules
	$(MAKE) -f $(SELF) buildonly
buildonly: $(OUTPUT)

gitsubmodules:
	git submodule init && git submodule update
projectcode1:
	$(MAKE) -C $(CODEDIR1)

projectcode2:
	$(MAKE) -C $(CODEDIR2)
	
projectcode3:
	$(MAKE) -C $(CODEDIR3)

projectcode4:
ifeq ($(wildcard $(CODEDIR4)/.libs/libsecp256k1.a), )
	cd $(CODEDIR4) && chmod +x autogen.sh && ./autogen.sh && ./configure --enable-experimental --enable-module-ecdh --with-bignum=no --enable-endomorphism && $(MAKE)
endif

projectcode5:
ifeq ($(wildcard $(CODEDIR5)/librandomx.a), )
	mkdir -p $(CODEDIR5)/build
	cd $(CODEDIR5)/build && cmake -DCMAKE_BUILD_TYPE=Release -DARCH=native -DCMAKE_CXX_FLAGS="-include cstdint" .. && $(MAKE)
	cp $(CODEDIR5)/build/librandomx.a $(CODEDIR5)/librandomx.a
endif

$(SOURCES): stratum.h util.h
stratum.o: algos/registry.def algos/algos.h
$(SOURCES_KAWPOW) meraki/lib/meraki/progpow.cpp: progpow/core.hpp
$(SOURCES_MERAKI): stratum.h meraki/hash.h meraki/helpers.hpp meraki/meraki.h meraki/lib/meraki/meraki.hpp meraki/lib/meraki/progpow.hpp meraki/lib/attributes.h meraki/lib/support/attributes.h
$(SOURCES_ETHASH): stratum.h ethash/params.h ethash/context.h ethash/engine.h ethash/work.h
$(SOURCES_XHASH): stratum.h xhash/libxhash/include/xhash/xhash.h xhash/libxhash/include/xhash/xhash.hpp

# HomeScrypt v1.2 consensus needs exact IEEE-754 fmaf rounding: no FMA
# contraction or fast-math for it
algos/misc/homescrypt.o: algos/misc/homescrypt.cpp stratum.h algos/misc/homescrypt.h
	$(CC) $(CFLAGS) $(SQLFLAGS) -ffp-contract=off -fno-fast-math -c algos/misc/homescrypt.cpp -o $@

$(OUTPUT): $(OBJECTS)
	$(CC) $(OBJECTS) $(LDLIBS) $(LDFLAGS) -o $@

.cpp.o:
	$(CC) $(CFLAGS) $(SQLFLAGS) -c $< -o $@

.c.o:
	$(CC) $(CFLAGS) -c $< -o $@

clean:
	rm -f stratum
	rm -f *.o
	rm -f algos/*.o
	rm -f algos/*.a
	rm -f third_party/iniparser/*.o third_party/iniparser/src/*.o third_party/iniparser/*.a
	rm -f third_party/sph/*.o third_party/sph/*.a
	find algos third_party/keccak-xkcp third_party/blake2-ref third_party/blake3 third_party/SWIFFTX -name '*.o' -delete
	rm -f algos/xelisv2-pepew/*.o
	find protocol -name '*.o' -delete
	rm -rf third_party/randomx/build randomx/librandomx.a
	
install: clean all
	strip -s stratum
	cp stratum /usr/local/bin/
	cp stratum ../bin/

