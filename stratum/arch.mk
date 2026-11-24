# Target CPU for -march, shared by all stratum makefiles.
# The default is a portable baseline so a binary or image built on one host
# runs on older ones; pass ARCH=native for a build that stays on this host.
ifndef ARCH
  UNAME_M := $(shell uname -m)
  ifeq ($(UNAME_M),x86_64)
    ARCH := x86-64-v2
  else ifeq ($(UNAME_M),aarch64)
    ARCH := armv8-a
  else
    ARCH := native
  endif
endif
export ARCH
