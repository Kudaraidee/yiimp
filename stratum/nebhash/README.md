# NEB `nebhash` pool support

This directory adds a `neb-pool/1` stratum listener for NEB v1.0.4 mainnet.

## Files

- `blake3_min.c/.h`, `chacha8.c/.h`, `nebhash2.c/.h`, `shares.c/.h` — verbatim
  C reference PoW engine from the NEB node (Apache-2.0, see `LICENSE`).
- `work.cpp/.h` — work-service TCP client, template/job builder, submit
  verification, reference k-window vardiff and chain-share forwarding.
- `tests/test_neb_pool1.py` — protocol smoke test (subscribe/authorize/notify,
  multi-login, NEB error shapes). It does not mine a share.

## Runtime requirements

- NEB node v1.0.5 (1.0.2+ compatible) with work service on `127.0.0.1:3441`
  and JSON-RPC on `127.0.0.1:8432`.
- Pinned dataset `/home/codex/lrs-nodes/neb-node/neb-datasets.bin`
  (`NMDS||LE32(2)||NMD2 arch=1 N=13404||NMD2 arch=1 N=7089`). Slot is
  `height mod 2`.
- Stratum config `/home/codex/yiimp-runtime/config/stratum/nebhash.conf`
  (port `3645`, TLS-only port `4645` intentionally commented).
- SQL migration `sql/2026-11-23-add_algo_nebhash.sql` (algo id `60`, coin id
  `13`). Expire web memcache keys after applying it.

## Protocol notes

- Subscribe result is an object: `session`, `extranonce1`,
  `extranonce2_size`, `chain_id`, `dataset_hash`. `extranonce1` is 4 bytes and
  `extranonce2_size` comes from the current node job.
- Every subscribe must send `"neb-pool/1"` as parameter 2. The generic
  notify-id reconnect path is skipped for this protocol.
- Every job push sends `mining.set_target` (exact 256-bit little-endian target
  bytes), `mining.set_model` when the model changes, then `mining.notify`.
  There is no generic `mining.set_difficulty` or default Yiimp difficulty 16.
- `mining.submit` has five parameters:
  `[login, job_id, extranonce2, time, nonce]`. A session may authorize up to
  eight `neb1` logins; shares and solo mode are attributed per login.
- Chain shares are sent to the node with JSON-RPC `submitshares`. P2P share
  forwarding is intentionally not used. Block credit is applied only after
  the node returns an accepted two-byte `Result` frame.
- V1 model frames are not supported. The loader accepts only `NMD2` /
  `neb2_model` datasets from node v1.0.5.
- Password tokens `d=` and `sd=` select the session target before the first
  job. An integer `1..48` keeps the reference pool's `k` shift
  (`target = share_target << k`). Any other positive number is the miner's
  display difficulty (`d=0.1` means `10^9` expected hashes); it is clamped so
  the pool target is never harder than the node's `share_target`. `d=` pins
  the value, `sd=` only seeds it and leaves vardiff active, and `d=` wins if
  both are present. Invalid tokens are ignored.

## Smoke test

With the node replay complete and the stratum unit running:

```sh
python3 stratum/nebhash/tests/test_neb_pool1.py --host 127.0.0.1 --port 3645
```
