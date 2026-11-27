# XHash (Parallax / LAX) in the ethash work module

`algo = xhash` reuses the Ethash/Etchash work package, EthereumStratum/1.0.0
protocol and ethproxy compatibility layer. Only the chain-specific parts are
swapped in through `ethash/engine.cpp`:

| | Ethash / Etchash | XHash |
|---|---|---|
| Mining RPC | `eth_getWork`, `eth_submitWork` | `xhash_getWork`, `xhash_submitWork` |
| Work fields | `[sealHash, seed, target]` | `[sealHash, seed, target, blockNumber]` |
| Epoch | reverse-mapped from seed / height | `blockNumber / 720` |
| PoW core | KawPow vendored ethash (Keccak-256) | `stratum/xhash/libxhash` (SHA3-512/256) |
| Share scale | `target = 2^224 / diff` | same |

Block/height queries stay in the `eth_` namespace on Parallax; only the
mining methods change. The 4th `xhash_getWork` field is the candidate block
number, so it is used directly (no `+1` like classic `eth_blockNumber`).

## Share verification

`mining.notify` carries the advertised seed hash and seal hash. The stratum
derives the epoch from the `xhash_getWork` height and requires the advertised
seed to equal `xhash_calculate_epoch_seed(epoch)`; a mismatch is treated as a
failed work poll so miners are never handed jobs that cannot be verified.
Submitted nonces are verified against that same light cache. The share target
and the block boundary both use big-endian 256-bit values, so
`mining.set_difficulty`/ethproxy targets and the verifier target always agree.
Difficulty changes therefore cannot invalidate already-issued shares.

The node boundary is `floor((2^256-1)/difficulty)`. Yiimp's `2^224/diff`
share target is the same scale Hashwarp uses (`2^256/(diff*2^32)`), so a
miner reporting `d=0.1` performs about `0.1 * 2^32` expected hashes per share.

## Setup

1. Apply `sql/2026-11-27-add_algo_xhash.sql` (algo id 62, coin id 15, port
   3647/TLS 4647, `rpcencoding=GETH`, `mature_blocks=100`, `powlimit_bits=0`).
2. Run a PoW-capable Parallax node with the pool beneficiary configured:
   - HTTP RPC `127.0.0.1:8555` (8545 is already used by LRS)
   - WebSocket `127.0.0.1:8556`, P2P `30333`
   - `eth` and `xhash` RPC namespaces enabled
3. Fill the `coins` row `rpcuser`/`rpcpasswd` with the node's generated
   credentials. The stratum reads host/port/credentials from the coin row.
4. Use `stratum/config.sample/xhash.conf` as the runtime config template.
5. Rebuild stratum from committed HEAD with
   `/home/codex/yiimp-runtime/scripts/rebuild-stratum.sh`, then enable
   `yiimp-stratum@xhash.service`.

## Tests

`make -C stratum/tests check-xhash` runs the vendored-library known-answer
tests from `parallax-pool` (seed hashes for epochs 0/1/171 and two light
hash/mix vectors). `make -C stratum/tests check` includes it in the full suite.

`stratum/xhash/README.md` records the upstream provenance and license.
