
CC= gcc -no-pie 

CFLAGS= -g -march=native -I./ -Irandomx/src
SQLFLAGS= `mysql_config --cflags --libs`

#CFLAGS=-c -O2 -I /usr/include/mysql
#LDFLAGS=-O2 `mysql_config --libs`

LDLIBS=randomx/librandomx.a iniparser/libiniparser.a algos/libalgos.a sha3/libhash.a -Isecp256k1/include secp256k1/.libs/libsecp256k1.a -lpthread -lgmp -lm -lstdc++ -lssl -lcrypto -lsodium
LDLIBS+=-lmysqlclient -lcurl

SOURCES=stratum.cpp db.cpp coind.cpp coind_aux.cpp coind_template.cpp coind_submit.cpp util.cpp list.cpp uint256.cpp arith_uint256.cpp utilstrencodings.cpp \
	rpc.cpp job.cpp job_send.cpp job_core.cpp merkle.cpp share.cpp socket.cpp tls.cpp coinbase.cpp \
	client.cpp client_submit.cpp client_core.cpp client_difficulty.cpp remote.cpp remote_template.cpp \
	user.cpp object.cpp json.cpp base58.cpp humanize_number.cpp
SOURCES_KAWPOW=kawpow/lib/keccak/keccak.c kawpow/lib/keccak/keccakf800.c kawpow/lib/keccak/keccakf1600.c kawpow/lib/ethash/primes.c \
	kawpow/lib/ethash/ethash.cpp kawpow/lib/ethash/progpow.cpp kawpow/hash.cpp kawpow/kawpow.cpp \
	firopow/lib/progpow.cpp firopow/overrides.cpp firopow/hash.cpp \
	phihash/lib/progpow.cpp phihash/hash.cpp \
	meowpow/lib/progpow.cpp meowpow/hash.cpp \
	evrprogpow/lib/progpow.cpp evrprogpow/hash.cpp
SOURCES_MERAKI_C=meraki/lib/keccak/keccak.c meraki/lib/keccak/keccakf800.c meraki/lib/keccak/keccakf1600.c meraki/lib/meraki/primes.c
SOURCES_MERAKI_CPP=meraki/lib/meraki/meraki.cpp meraki/lib/meraki/managed.cpp meraki/lib/meraki/progpow.cpp meraki/hash.cpp meraki/meraki.cpp
SOURCES_MERAKI=$(SOURCES_MERAKI_C) $(SOURCES_MERAKI_CPP)
SOURCES_ETHASH=ethash/params.cpp ethash/context.cpp ethash/work.cpp
SOURCES_RANDOMSCASH=randomscash/randomscash.cpp
SOURCES_HOMESCRYPT=homescrypt/homescrypt.cpp
SOURCES_NEBHASH_C=nebhash/blake3_min.c nebhash/chacha8.c nebhash/nebhash2.c nebhash/shares.c
SOURCES_NEBHASH_CPP=nebhash/work.cpp
SOURCES_NEBHASH=$(SOURCES_NEBHASH_C) $(SOURCES_NEBHASH_CPP)

CFLAGS += -DHAVE_CURL
include arch.mk
CFLAGS := $(filter-out -march=native,$(CFLAGS)) -march=$(ARCH)
SOURCES += rpc_curl.cpp
#LDCURL = $(shell /usr/bin/pkg-config --static --libs libcurl)
#LDFLAGS += $(LDCURL)

OBJECTS=$(SOURCES:.cpp=.o) $(SOURCES_KAWPOW:.cpp=.o) $(SOURCES_MERAKI_C:.c=.o) $(SOURCES_MERAKI_CPP:.cpp=.o) $(SOURCES_ETHASH:.cpp=.o) $(SOURCES_RANDOMSCASH:.cpp=.o) $(SOURCES_HOMESCRYPT:.cpp=.o) $(SOURCES_NEBHASH_C:.c=.o) $(SOURCES_NEBHASH_CPP:.cpp=.o)
OUTPUT=stratum

CODEDIR1=algos
CODEDIR2=sha3
CODEDIR3=iniparser
CODEDIR4=secp256k1
CODEDIR5=randomx


.PHONY: projectcode1 projectcode2 projectcode3 projectcode4 projectcode5

all: gitsubmodules projectcode1 projectcode2 projectcode3 projectcode4 projectcode5 $(SOURCES) $(SOURCES_KAWPOW) $(SOURCES_MERAKI) $(SOURCES_ETHASH) $(SOURCES_RANDOMSCASH) $(SOURCES_HOMESCRYPT) $(SOURCES_NEBHASH) $(OUTPUT)
buildonly: projectcode1 projectcode2 projectcode3 projectcode4 projectcode5 $(SOURCES) $(SOURCES_KAWPOW) $(SOURCES_MERAKI) $(SOURCES_ETHASH) $(SOURCES_RANDOMSCASH) $(SOURCES_HOMESCRYPT) $(SOURCES_NEBHASH) $(OUTPUT)

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
stratum.o: algos/registry.def
$(SOURCES_MERAKI): stratum.h meraki/hash.h meraki/helpers.hpp meraki/meraki.h meraki/lib/meraki/meraki.hpp meraki/lib/meraki/progpow.hpp meraki/lib/attributes.h meraki/lib/support/attributes.h
$(SOURCES_ETHASH): stratum.h ethash/params.h ethash/context.h ethash/work.h
$(SOURCES_RANDOMSCASH): stratum.h randomscash/randomscash.h randomx/src/randomx.h
$(SOURCES_HOMESCRYPT): stratum.h homescrypt/homescrypt.h
$(SOURCES_NEBHASH): stratum.h nebhash/work.h nebhash/shares.h nebhash/nebhash2.h nebhash/blake3_min.h nebhash/chacha8.h

# HomeScrypt v1.2 consensus requires exact IEEE-754 fmaf rounding. Never build
# this translation unit with fast-math or FP contraction.
homescrypt/homescrypt.o: homescrypt/homescrypt.cpp stratum.h homescrypt/homescrypt.h
	$(CC) $(CFLAGS) $(SQLFLAGS) -ffp-contract=off -fno-fast-math -c homescrypt/homescrypt.cpp -o $@

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
	rm -f iniparser/*.o
	rm -f iniparser/src/*.o
	rm -f iniparser/*.a
	rm -f sha3/*.o
	rm -f sha3/*.a
	rm -f algos/ar2/*.o
	rm -f algos/blake2/*.o
	rm -f algos/blake2-ref/*.o
	rm -f algos/cryptonote/*.o
	rm -f algos/cryptonote/crypto/*.o
	rm -f algos/heavyhash/*.o
	rm -f algos/honeycomb/*.o
	rm -f algos/SWIFFTX/*.o
	rm -f algos/yespower/*.o
	rm -f algos/yespower/crypto/*.o
	rm -f algos/blake3/*.o
	rm -f algos/sha3/*.o
	rm -f algos/xelisv2-pepew/*.o
	rm -f kawpow/lib/keccak/*.o
	rm -f phihash/*.o phihash/lib/*.o
	rm -f meowpow/*.o meowpow/lib/*.o
	rm -f kawpow/*.o
	rm -f kawpow/lib/ethash/*.o
	rm -f firopow/*.o
	rm -f firopow/lib/*.o
	rm -f meraki/*.o
	rm -f meraki/lib/meraki/*.o
	rm -f meraki/lib/keccak/*.o
	rm -f randomscash/*.o
	rm -f homescrypt/*.o
	rm -f nebhash/*.o
	rm -rf randomx/build
	rm -f randomx/librandomx.a
	rm -f ethash/*.o

install: clean all
	strip -s stratum
	cp stratum /usr/local/bin/
	cp stratum ../bin/

