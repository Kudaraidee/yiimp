# Stratum test harness: hash vectors, KATs and template checks.
#
# Sources, flags and libraries are read from ../GNUmakefile at make time,
# so new algo sources are picked up without editing this file.
#
#   make -C tests check        hash vectors + reference KATs + templates (plain build)
#   make -C tests vectors      re-generate the golden masters (review the diff!)
#   make -C tests hardened     same tools + stratum, -O2 -D_FORTIFY_SOURCE=3
#                              -fstack-protector-strong; then runs overflow cases
#   make -C tests asan         same with AddressSanitizer + UBSan
#   make -C tests warnings     overflow-related compiler warnings of the core
#                              -> build/warnings.txt
#   make -C tests clean
#
# Needs the algo libraries of the normal stratum build:
#   make -C .. libs        (done automatically by the targets above)
#
# Build environment = the image's baseimage stage, e.g. with podman:
#   podman build --target baseimage -t yiimp-stratum-build -f Dockerfile.yiimp .
#   podman run --rm -v ./stratum:/src -w /src/tests yiimp-stratum-build make check
#
# Inputs: vectors/reference.txt (known answers from chains, pools and miner
# KATs), vectors/hash.txt + fixtures/*/expected.txt (golden masters of today's
# behaviour), overflow/* (oversized wallet answers). gen_fixtures.py writes
# fixtures/ and overflow/.

TOP     := ..
B       := build
VARIANT ?= plain
OUT     := $(B)/$(VARIANT)

# --- read settings from ../GNUmakefile ---------------------------------------
# under a parent make, MAKEFLAGS can add "make[N]: Entering directory" lines
topvar = $(shell $(MAKE) -s --no-print-directory -C $(TOP) print-$(1) 2>/dev/null | grep -v '^make')

TOP_CC       := $(call topvar,CC)
TOP_CXX      := $(call topvar,CXX)
TOP_CPPFLAGS := $(call topvar,CORE_CPPFLAGS)
TOP_OPT      := $(call topvar,CORE_OPT)
TOP_SRCS     := $(call topvar,CORE_SRCS)
TOP_LDLIBS   := $(call topvar,LINK_LIBS)

CORE_OBJS := $(addsuffix .o,$(basename $(TOP_SRCS)))
# stratum.o is rebuilt without main(); rpc is replaced by rpc_mock in templcheck
MAIN_OBJ  := stratum.o
RPC_OBJS  := rpc.o rpc_curl.o

# include and library paths are relative to ..; flags stay as they are
INCS := $(foreach f,$(TOP_CPPFLAGS),$(if $(filter -I/%,$(f)),$(f),$(patsubst -I%,-I$(TOP)/%,$(f))))
LIBS := $(foreach l,$(TOP_LDLIBS),$(if $(filter -%,$(l)),$(l),$(TOP)/$(l)))

# --- variants ----------------------------------------------------------------
HARDEN_FLAGS := -O2 -D_FORTIFY_SOURCE=3 -fstack-protector-strong
WARN_FLAGS   := -Wall -Wno-unused -Wno-sign-compare -Wformat-overflow=2 \
                -Wstringop-overflow=4 -Wstringop-truncation -Wformat-truncation=2 \
                -Warray-bounds=2
# FORTIFY off: Ubuntu enables it by default and it aborts before ASan can
# report the exact location
ASAN_FLAGS   := -O1 -g -fno-omit-frame-pointer -fsanitize=address,undefined \
                -U_FORTIFY_SOURCE -D_FORTIFY_SOURCE=0

ifeq ($(VARIANT),plain)
  XFLAGS  :=
  XLDFLAGS :=
else ifeq ($(VARIANT),hardened)
  XFLAGS  := $(HARDEN_FLAGS)
  XLDFLAGS :=
else ifeq ($(VARIANT),warnings)
  XFLAGS  := $(HARDEN_FLAGS) $(WARN_FLAGS)
  XLDFLAGS :=
else ifeq ($(VARIANT),asan)
  XFLAGS  := $(ASAN_FLAGS)
  XLDFLAGS := -fsanitize=address,undefined
  # LeakSanitizer needs ptrace, unavailable in containers
  export ASAN_OPTIONS ?= detect_leaks=0
else
  $(error unknown VARIANT $(VARIANT))
endif

CFLAGS_ALL := $(INCS) $(TOP_OPT) $(XFLAGS) -I$(TOP) -MMD -MP

# --- objects -----------------------------------------------------------------
OBJ_CORE   := $(addprefix $(OUT)/,$(filter-out $(MAIN_OBJ),$(CORE_OBJS)))
OBJ_RPC    := $(addprefix $(OUT)/,$(RPC_OBJS))
OBJ_NOMAIN := $(OUT)/stratum_nomain.o
OBJ_MAIN   := $(OUT)/stratum.o

HASHCHECK  := $(OUT)/hashcheck
TEMPLCHECK := $(OUT)/templcheck
STRATUM    := $(OUT)/stratum

.PHONY: all tools libs check vectors hardened asan warnings clean \
        check-hash check-ref check-templ overflow
.DEFAULT_GOAL := check

all tools: $(HASHCHECK) $(TEMPLCHECK)

libs:
	$(MAKE) -C $(TOP) libs

$(OUT)/%.o: $(TOP)/%.cpp
	@mkdir -p $(dir $@)
	$(TOP_CXX) $(CFLAGS_ALL) -c $< -o $@

$(OUT)/%.o: $(TOP)/%.c
	@mkdir -p $(dir $@)
	$(TOP_CC) $(CFLAGS_ALL) -c $< -o $@

$(OUT)/stratum_nomain.o: $(TOP)/stratum.cpp
	@mkdir -p $(dir $@)
	$(TOP_CXX) $(CFLAGS_ALL) -Dmain=stratum_main -c $< -o $@

$(OUT)/tests/%.o: %.cpp
	@mkdir -p $(dir $@)
	$(TOP_CXX) $(CFLAGS_ALL) -c $< -o $@

$(HASHCHECK): $(OUT)/tests/hashcheck.o $(OBJ_NOMAIN) $(OBJ_CORE) | libs
	$(TOP_CXX) -no-pie $^ $(LIBS) $(XLDFLAGS) -o $@

$(TEMPLCHECK): $(OUT)/tests/templcheck.o $(OUT)/tests/rpc_mock.o $(OBJ_NOMAIN) $(filter-out $(OBJ_RPC),$(OBJ_CORE)) | libs
	$(TOP_CXX) -no-pie $^ $(LIBS) $(XLDFLAGS) -o $@

$(STRATUM): $(OBJ_MAIN) $(OBJ_CORE) | libs
	$(TOP_CXX) -no-pie $^ $(LIBS) $(XLDFLAGS) -o $@

# --- runs --------------------------------------------------------------------
FIXTURES := $(sort $(dir $(wildcard fixtures/*/coind.conf)))
OVERFLOW := $(sort $(dir $(wildcard overflow/*/coind.conf)))

check: check-ref check-hash check-templ overflow

check-ref: $(HASHCHECK)
	$(HASHCHECK) --reference vectors/reference.txt

check-hash: $(HASHCHECK)
	$(HASHCHECK) --check vectors/hash.txt

check-templ: $(TEMPLCHECK)
	@fail=0; for d in $(FIXTURES); do \
	  if $(TEMPLCHECK) $$d > $(OUT)/templ.out 2>&1 && ! grep -q '^template=' $(OUT)/templ.out \
	     && diff -u $${d}expected.txt $(OUT)/templ.out > $(OUT)/templ.diff; then \
	    echo "TEMPL ok   $$d"; \
	  else echo "TEMPL FAIL $$d"; grep '^template=' $(OUT)/templ.out || cat $(OUT)/templ.diff; fail=1; fi; \
	done; exit $$fail

# golden masters from the current code; review `git diff` before committing
vectors: $(HASHCHECK) $(TEMPLCHECK)
	$(HASHCHECK) --generate > vectors/hash.txt
	@for d in $(FIXTURES); do $(TEMPLCHECK) $$d > $${d}expected.txt; echo "wrote $${d}expected.txt"; done

# overflow cases: inputs larger than the fixed buffers must be rejected
# cleanly, without a crash, FORTIFY abort or ASan report
overflow: $(TEMPLCHECK)
	@fail=0; for d in $(OVERFLOW); do \
	  $(TEMPLCHECK) $$d > $(OUT)/overflow.out 2> $(OUT)/overflow.err; rc=$$?; \
	  if [ $$rc -eq 0 ] && grep -q '^template=REJECTED' $(OUT)/overflow.out && ! grep -q . $(OUT)/overflow.err; then \
	    echo "OVERFLOW ok   $$d"; \
	  else echo "OVERFLOW FAIL $$d (exit=$$rc)"; head -c 2000 $(OUT)/overflow.err; \
	    grep -E '^(template|ERROR)|ERROR:' $(OUT)/overflow.out | cut -c1-120; fail=1; fi; \
	done; exit $$fail

hardened:
	$(MAKE) VARIANT=hardened tools $(B)/hardened/stratum overflow

asan:
	$(MAKE) VARIANT=asan tools overflow

warnings:
	@mkdir -p $(B)
	$(MAKE) -k VARIANT=warnings $(addprefix $(B)/warnings/,$(filter-out $(MAIN_OBJ),$(CORE_OBJS))) 2> $(B)/warnings.txt || true
	@echo "warnings per type:"; grep -oE '\[-W[a-z0-9=-]+\]' $(B)/warnings.txt | sort | uniq -c | sort -rn
	@echo "warnings per file:"; grep -oE '^\.\./[a-z_0-9/]+\.(cpp|c):' $(B)/warnings.txt | sort | uniq -c | sort -rn | head -20

clean:
	rm -rf $(B)

-include $(shell find $(OUT) -name '*.d' 2>/dev/null)
