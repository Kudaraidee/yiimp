# Stratum safety net (phase 0 of docs/stratum_rework_plan.md).
#
# Self-contained on purpose: object list, flags and libraries are read from
# ../Makefile at make time, so ../Makefile stays untouched (no merge conflicts
# with upstream) and sources upstream adds there are picked up automatically.
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
# Needs the normal stratum build first for the algo libraries:
#   make -C .. buildonly   (done automatically by the targets above)
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

# --- read settings from ../Makefile ------------------------------------------
topvar = $(shell $(MAKE) -s --no-print-directory -C $(TOP) --eval 'print-var-: ; @echo $$($(1))' print-var-)

TOP_CC      := $(call topvar,CC)
TOP_CFLAGS  := $(call topvar,CFLAGS)
TOP_SQL     := $(call topvar,SQLFLAGS)
TOP_OBJECTS := $(call topvar,OBJECTS)
TOP_LDLIBS  := $(call topvar,LDLIBS)

# ../Makefile lists .c files of some subdirs in OBJECTS unconverted; normalise
CORE_OBJS := $(patsubst %.c,%.o,$(TOP_OBJECTS))
# stratum.o is rebuilt without main(); rpc is replaced by rpc_mock in templcheck
MAIN_OBJ  := stratum.o
RPC_OBJS  := rpc.o rpc_curl.o

# library paths in LDLIBS are relative to ..; flags stay as they are
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
  # LeakSanitizer does not work in containers without ptrace; leaks are not
  # what phase 0 is after anyway
  export ASAN_OPTIONS ?= detect_leaks=0
else
  $(error unknown VARIANT $(VARIANT))
endif

CXXFLAGS_ALL := $(TOP_CFLAGS) $(TOP_SQL) $(XFLAGS) -I$(TOP) -MMD -MP
CFLAGS_ALL   := $(TOP_CFLAGS) $(XFLAGS) -I$(TOP) -MMD -MP

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
	$(MAKE) -C $(TOP) buildonly

$(OUT)/%.o: $(TOP)/%.cpp
	@mkdir -p $(dir $@)
	$(TOP_CC) $(CXXFLAGS_ALL) -c $< -o $@

$(OUT)/%.o: $(TOP)/%.c
	@mkdir -p $(dir $@)
	$(TOP_CC) $(CFLAGS_ALL) -c $< -o $@

$(OUT)/stratum_nomain.o: $(TOP)/stratum.cpp
	@mkdir -p $(dir $@)
	$(TOP_CC) $(CXXFLAGS_ALL) -Dmain=stratum_main -c $< -o $@

$(OUT)/tests/%.o: %.cpp
	@mkdir -p $(dir $@)
	$(TOP_CC) $(CXXFLAGS_ALL) -c $< -o $@

$(HASHCHECK): $(OUT)/tests/hashcheck.o $(OBJ_NOMAIN) $(OBJ_CORE) | libs
	$(TOP_CC) $^ $(LIBS) $(XLDFLAGS) -o $@

$(TEMPLCHECK): $(OUT)/tests/templcheck.o $(OUT)/tests/rpc_mock.o $(OBJ_NOMAIN) $(filter-out $(OBJ_RPC),$(OBJ_CORE)) | libs
	$(TOP_CC) $^ $(LIBS) $(XLDFLAGS) -o $@

$(STRATUM): $(OBJ_MAIN) $(OBJ_CORE) | libs
	$(TOP_CC) $^ $(LIBS) $(XLDFLAGS) -o $@

# --- runs --------------------------------------------------------------------
FIXTURES := $(sort $(dir $(wildcard fixtures/*/coind.conf)))
OVERFLOW := $(sort $(dir $(wildcard overflow/*/coind.conf)))

check: check-ref check-hash check-templ

check-ref: $(HASHCHECK)
	$(HASHCHECK) --reference vectors/reference.txt

check-hash: $(HASHCHECK)
	$(HASHCHECK) --check vectors/hash.txt

check-templ: $(TEMPLCHECK)
	@fail=0; for d in $(FIXTURES); do \
	  if $(TEMPLCHECK) $$d > $(OUT)/templ.out 2>&1 && diff -u $${d}expected.txt $(OUT)/templ.out > $(OUT)/templ.diff; then \
	    echo "TEMPL ok   $$d"; \
	  else echo "TEMPL FAIL $$d"; cat $(OUT)/templ.diff; fail=1; fi; \
	done; exit $$fail

# golden masters from the current code; review `git diff` before committing
vectors: $(HASHCHECK) $(TEMPLCHECK)
	$(HASHCHECK) --generate > vectors/hash.txt
	@for d in $(FIXTURES); do $(TEMPLCHECK) $$d > $${d}expected.txt; echo "wrote $${d}expected.txt"; done

# overflow cases: inputs larger than the fixed buffers. Not pass/fail yet;
# shows how each build reacts (silent corruption, FORTIFY abort, ASan report).
overflow: $(TEMPLCHECK)
	@for d in $(OVERFLOW); do \
	  echo "=== $$d ($(VARIANT))"; \
	  $(TEMPLCHECK) $$d > $(OUT)/overflow.out 2> $(OUT)/overflow.err; rc=$$?; \
	  echo "exit=$$rc"; head -c 2000 $(OUT)/overflow.err; \
	  grep -E '^(template|coinbase|coinb2|merkleroot)=' $(OUT)/overflow.out | cut -c1-120; \
	done

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
