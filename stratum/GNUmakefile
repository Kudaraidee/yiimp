# Stratum build. GNU make reads this file before Makefile.
#
# Makefile, algos/makefile and sha3/makefile stay as they are: they are the
# legacy build (make -f Makefile) and the source lists. Sources, defines and
# libraries are read from them, so files added there are built here as well.
#
#   make                  release build -> ./stratum, objects in build/
#   make buildonly        same without updating git submodules
#   make -j$(nproc)       parallel build
#   make SANITIZE=1       ASan + UBSan build -> build/asan/stratum
#   make ARCH=native      tune for this CPU (never for images)
#   make HARDEN=0         without -D_FORTIFY_SOURCE=3 -fstack-protector-strong
#   make V=1              print full commands
#   make check            hash vectors and template tests (tests/Makefile)
#   make clean | install | print-VAR

include arch.mk

# --- read the legacy makefiles ----------------------------------------------
# (MAKEFLAGS of a parent make would add "Entering directory" lines to the output)
legacy = $(shell MAKEFLAGS= $(MAKE) -s --no-print-directory -C $(1) -f $(2) --eval 'print-var-: ; @echo $$($(3))' print-var- 2>/dev/null)

LEGACY_OBJS    := $(call legacy,.,Makefile,OBJECTS)
LEGACY_CFLAGS  := $(call legacy,.,Makefile,CFLAGS)
LEGACY_LDLIBS  := $(call legacy,.,Makefile,LDLIBS)
ALGO_SRCS      := $(addprefix algos/,$(call legacy,algos,makefile,SOURCES))
ALGO_LCFLAGS   := $(call legacy,algos,makefile,CFLAGS)
ALGO_LCXXFLAGS := $(call legacy,algos,makefile,CXXFLAGS)
SHA3_SRCS      := $(addprefix sha3/,$(call legacy,sha3,makefile,SOURCES))
SHA3_LCFLAGS   := $(call legacy,sha3,makefile,CFLAGS)
INI_SRCS       := $(addprefix iniparser/,$(call legacy,iniparser,Makefile,SRCS))

ifeq ($(strip $(LEGACY_OBJS)),)
  $(error cannot read OBJECTS from Makefile)
endif

# OBJECTS mixes .o names with unconverted .c names
CORE_SRCS := $(foreach o,$(LEGACY_OBJS),$(if $(filter %.o,$(o)),$(firstword $(wildcard $(o:.o=.cpp) $(o:.o=.c)) $(o:.o=.cpp)),$(o)))

# --- flags -------------------------------------------------------------------
CC  := gcc
CXX := g++
AR  := ar

ifeq ($(SANITIZE),1)
  B      := build/asan
  OUTPUT := $(B)/stratum
  OPT    := -O1 -g -fno-omit-frame-pointer -fsanitize=address,undefined
  LDFLAGS += -fsanitize=address,undefined
  # FORTIFY aborts before ASan can report the location
  HARDEN := 0
else
  B      := build
  OUTPUT := stratum
  OPT    := -O2 -g
endif
ifneq ($(HARDEN),0)
  OPT += -U_FORTIFY_SOURCE -D_FORTIFY_SOURCE=3 -fstack-protector-strong
endif

# consensus code must not depend on FMA contraction (hoohash, homescrypt)
COMMON := -march=$(ARCH) -ffp-contract=off -MMD -MP

MYSQL_CFLAGS := $(shell mysql_config --cflags 2>/dev/null)

# per part: includes, defines and language flags as the legacy makefiles have them
CORE_CPPFLAGS := $(filter -I% -D%,$(LEGACY_CFLAGS)) $(MYSQL_CFLAGS)
# algos and sha3 keep their own -O level, except in the sanitizer build
PART_OPT      := $(if $(filter 1,$(SANITIZE)),$(OPT),$(filter-out -O%,$(OPT)))
# the core was built at -O0 until now: no strict-aliasing or overflow assumptions
CORE_OPT      := $(OPT) -march=$(ARCH) -ffp-contract=off -fno-strict-aliasing -fwrapv
ALGO_CFLAGS   := $(patsubst -I%,-Ialgos/%,$(filter-out -march=% -g%,$(ALGO_LCFLAGS))) $(PART_OPT)
ALGO_CXXFLAGS := $(patsubst -I%,-Ialgos/%,$(filter-out -march=% -g%,$(ALGO_LCXXFLAGS))) $(PART_OPT)
SHA3_CFLAGS   := $(filter-out -march=%,$(SHA3_LCFLAGS)) $(PART_OPT)
INI_CFLAGS    := -std=c99 -fPIC $(OPT)

ifeq ($(V),1)
  Q :=
  msg :=
else
  Q := @
  msg = @printf '  %-6s %s\n' $(1) $(2);
endif

# --- objects and libraries ---------------------------------------------------
obj = $(patsubst %,$(B)/%.o,$(basename $(1)))

CORE_OBJS := $(call obj,$(CORE_SRCS))
ALGO_OBJS := $(call obj,$(ALGO_SRCS))
SHA3_OBJS := $(call obj,$(SHA3_SRCS))
INI_OBJS  := $(call obj,$(INI_SRCS))
ALL_OBJS  := $(CORE_OBJS) $(ALGO_OBJS) $(SHA3_OBJS) $(INI_OBJS)

LIBALGOS  := $(B)/libalgos.a
LIBHASH   := $(B)/libhash.a
LIBINI    := $(B)/libiniparser.a
SECP256K1 := secp256k1/.libs/libsecp256k1.a

# Makefile's LDLIBS in its order (duplicate symbols resolve by link order),
# our archives in place of its in-tree ones. A library that only Makefile
# knows how to build (an upstream addition) fails with "No rule to make
# target": add a rule for it below.
LINK_LIBS := $(patsubst iniparser/libiniparser.a,$(LIBINI),$(patsubst algos/libalgos.a,$(LIBALGOS),\
	$(patsubst sha3/libhash.a,$(LIBHASH),$(filter-out -I%,$(LEGACY_LDLIBS)))))
LINK_DEPS := $(filter-out -%,$(LINK_LIBS))

# --- targets -----------------------------------------------------------------
.PHONY: all buildonly libs gitsubmodules check install clean
.DEFAULT_GOAL := all

all: gitsubmodules
	+$(MAKE) buildonly

buildonly: $(OUTPUT)

libs: $(LINK_DEPS)

gitsubmodules:
	git submodule init && git submodule update

$(OUTPUT): $(CORE_OBJS) $(LINK_DEPS)
	$(call msg,LINK,$@)
	$(Q)$(CXX) -no-pie $(LDFLAGS) $(CORE_OBJS) $(LINK_LIBS) -o $@

# one ar call into a new archive: blake2s.o exists twice by basename
$(LIBALGOS): $(ALGO_OBJS)
$(LIBHASH): $(SHA3_OBJS)
$(LIBINI): $(INI_OBJS)
$(LIBALGOS) $(LIBHASH) $(LIBINI):
	$(call msg,AR,$@)
	$(Q)rm -f $@ && $(AR) rcs $@ $^

$(SECP256K1):
	cd secp256k1 && chmod +x autogen.sh && ./autogen.sh && ./configure --enable-experimental \
		--enable-module-ecdh --with-bignum=no --enable-endomorphism && $(MAKE)

# the most specific pattern (shortest stem) wins
$(B)/algos/%.o: algos/%.c
	$(call msg,CC,$<)
	$(Q)mkdir -p $(@D) && $(CC) $(ALGO_CFLAGS) $(COMMON) -c $< -o $@
$(B)/algos/%.o: algos/%.cpp
	$(call msg,CXX,$<)
	$(Q)mkdir -p $(@D) && $(CXX) $(ALGO_CXXFLAGS) $(COMMON) -c $< -o $@
$(B)/sha3/%.o: sha3/%.c
	$(call msg,CC,$<)
	$(Q)mkdir -p $(@D) && $(CC) $(SHA3_CFLAGS) $(COMMON) -c $< -o $@
$(B)/sha3/blake2s.o: SHA3_CFLAGS += -std=gnu99
$(B)/iniparser/%.o: iniparser/%.c
	$(call msg,CC,$<)
	$(Q)mkdir -p $(@D) && $(CC) $(INI_CFLAGS) $(COMMON) -c $< -o $@
$(B)/%.o: %.cpp
	$(call msg,CXX,$<)
	$(Q)mkdir -p $(@D) && $(CXX) $(CORE_CPPFLAGS) $(CORE_OPT) -MMD -MP -c $< -o $@
$(B)/%.o: %.c
	$(call msg,CC,$<)
	$(Q)mkdir -p $(@D) && $(CC) $(CORE_CPPFLAGS) $(CORE_OPT) -MMD -MP -c $< -o $@

check:
	+$(MAKE) -C tests check

install:
	+$(MAKE) clean
	+$(MAKE) all
	strip -s stratum
	cp stratum /usr/local/bin/
	cp stratum ../bin/

clean:
	rm -rf build stratum

print-%:
	@echo $($*)

-include $(ALL_OBJS:.o=.d)
