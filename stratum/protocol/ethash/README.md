# Ethash / Etchash stratum setup

How to install and enable the `ethash` and `etchash` algos on this yiimp fork.
Thai version: [README.thai.md](README.thai.md).

Parallax (LAX) uses the same work module with the `xhash` engine; see
[README.xhash.md](README.xhash.md) for the chain-specific RPC, epoch and
payout details.

## 1. Requirements

- A PoW node only: core-geth / Besu / Erigon still running Ethash/Etchash.
  **Post-Merge geth does not work**: it has no `eth_getWork`.
- The RPC must allow these methods:
  - `eth_getWork`
  - `eth_blockNumber`
  - `eth_submitWork`
- The pool's beneficiary/reward address is set on the node itself; the
  stratum only calls `eth_submitWork` to submit solved blocks.
- All stratum build dependencies installed (`mysqlclient`, `libsodium`,
  `curl`, `pthread`, `gmp`, …).

## 2. Build

```bash
make -C stratum buildonly
# or
make -C stratum
```

Check that `stratum/stratum` was built.

## 3. SQL migration

```bash
mysql -u root -p yaamp < sql/2026-11-19-add_algo_ethash_etchash.sql
```

It adds two algos:

| algo | port | color |
|---|---:|---|
| `ethash`  | 3639 | `#e2d0d2` |
| `etchash` | 3640 | `#e2d0d2` |

Keep the ports in line with `[TCP] port` in the stratum config.

After the insert, flush these memcache keys, otherwise the web may still show
the old algo list:

```bash
printf 'delete yaamp_algo_list\r\ndelete yaamp_visible_algos\r\ndelete yaamp_unvisible_algos\r\ndelete yaamp_algo_ports\r\ndelete yaamp_algo_colors\r\n' | nc 127.0.0.1 11211
```

or `flush_all` if that is acceptable.

## 4. Add the coin

Add the coin through the admin UI (or insert into `coins` directly) with at
least:

- `algo` = `ethash` or `etchash`
- `rpcencoding` = `GETH`
- `enable` = 1
- `auto_ready` = 1
- `installed` = 1 — with 0, `BackendCoinsUpdate()` skips the coin and
  `difficulty`, `block_height`, `connections` stay stale
- RPC host/port/user/pass pointing at a node with Ethash/Etchash
- `master_wallet` = the address the node uses as beneficiary
- `block_time` / `network_ttf` = the chain's real average (e.g. ~11 s for
  Larissa)
- `powlimit_bits` = 0 — no Bitcoin compact target; the stratum uses the ethash
  target 2^224 / difficulty (set it on the coin or rely on the `algos` value
  from the migration)
- `reward` = the real block reward; check it on the node/explorer and update
  it regularly (Larissa, for example, lowers it every week)
- symbol/contract/info as needed, e.g. `ETC` for etchash

The web has no hardcoded algo allow-list: the algo dropdown reads the `algos`
table, so the new algos show up after the migration.

### GETH block explorer

`WalletRPC` (both legacy `web/` and `yiimp2/`) maps `eth_getBlockBy*` /
`eth_getTransactionByHash` results into the Bitcoin-shaped structure the
explorer pages use (`height`, `time`, `tx`, `difficulty`, `confirmations`,
`txid`, `vout`, …) while keeping the original Ethereum fields, so
`/explorer/<SYMBOL>` works for GETH coins such as LRS without setting
`no_explorer=1`.

Lookups accept `?height=`, `?hash=0x...` and `?q=0x...` (also with the `0x`
prefix). The difficulty graph is limited to 40 blocks for GETH coins because
the RPC has no batch calls.

If the market is quoted in another currency (e.g. `LRS/USDT` on NonKyc), keep
`markets.price` in that currency as reported by the exchange; the backend
fetches `USDT/BTC` (or `USDC/BTC`) into `settings` as `usdt_btc` / `usdc_btc`
and converts it to `coins.price`. Both the legacy web and yiimp2 show the
market price in BTC using the same rate.

## 5. Stratum config

Samples:

- `stratum/config.sample/ethash.conf`
- `stratum/config.sample/etchash.conf`

Copy them to their real place, e.g.:

```bash
cp stratum/config.sample/ethash.conf  /etc/yiimp/stratum/ethash.conf
cp stratum/config.sample/etchash.conf /etc/yiimp/stratum/etchash.conf
```

Adjust `[TCP]`, `[SQL]`, `[STRATUM]`; `STRATUM:algo` must be exactly
`ethash` / `etchash`.

### `[ETHASH]` (optional)

```ini
[ETHASH]
epoch_length = 30000
seed_epoch_length = 30000
etchash_fork_block = 0
etchash_fork_epoch = 0
epoch_offset = 0
chain_id = 0
dag_init_size = 1073741824
```

| key | meaning | default ethash | default etchash |
|---|---|---:|---:|
| `epoch_length` | blocks per epoch after the fork | 30000 | 60000 |
| `seed_epoch_length` | old seed divisor (ECIP-1099 always uses 30000) | 30000 | 30000 |
| `etchash_fork_block` | block where Etchash starts | 0 | 11700000 |
| `etchash_fork_epoch` | epoch at fork_block (0 = `fork_block / seed_epoch_length`) | 0 | 0 → 390 |
| `epoch_offset` | added to the computed epoch | 0 | 0 |
| `chain_id` | informational only; standard Ethash PoW doesn't commit to it | 0 | 61 |
| `dag_init_size` | dataset item count for light verification | 1 GiB | 1 GiB |

Another chain, for example:

```ini
; Mordor Etchash testnet
[ETHASH]
epoch_length = 60000
etchash_fork_block = 2520000
etchash_fork_epoch = 84
chain_id = 63
```

The stratum asks the node for `eth_getWork` and **uses the seed hash the node
sends to determine the actual epoch**; `eth_blockNumber` only fills in the
height.

## 6. Supervisord

The blocks are already in `config/supervisord.conf`:

- `[program:stratum-ethash]`
- `[program:stratum-etchash]`

They default to `autostart=false`. Enable them with:

```bash
supervisorctl reread
supervisorctl update
supervisorctl start stratum-ethash stratum-etchash
supervisorctl status
```

or set `autostart=true` to start them with supervisord.

## 7. Connect a miner

Use Ethereum Stratum (`EthereumStratum/1.0.0`), not yiimp's Bitcoin-style
stratum.

Examples:

```bash
ethminer -U -S pool.example.com:3639 -O WALLET.WORKER:x
# Etchash on its own port
ethminer -U -S pool.example.com:3640 -O WALLET.WORKER:x
```

Rigel:

```bash
./rigel -a ethash -o ethstratum+tcp://pool.example.com:3639 -u WALLET -p c=LRS,mc=LRS
```

Miners using NiceHash EthereumStratum/1.0.0 (PhoenixMiner, lolMiner, GMiner,
NBMiner, ethminer):

```bash
./PhoenixMiner -pool ethstratum+tcp://pool.example.com:3639 -wal WALLET -worker WORKER -pass c=LRS,mc=LRS
./lolMiner --pool ethstratum+tcp://pool.example.com:3639 --wallet WALLET --worker WORKER --pass c=LRS,mc=LRS
```

> The pool detects NiceHash by the second `mining.subscribe` parameter
> (`EthereumStratum/1.0.0`) and answers with the array from the NiceHash
> spec. Rigel sends the same string but keeps its object-shaped response
> (detected by the `Rigel` user agent).

T-Rex 0.19/0.26 over ethproxy (plain scheme):

```bash
./t-rex -a ethash -o stratum+tcp://pool.example.com:3639 \
  -u WALLET.WORKER -p c=LRS,mc=LRS --intensity 16 --kernel 0
```

GMiner 3.43 over ethproxy (no scheme → `--proto proxy`) and over NiceHash
ethstratum:

```bash
./miner --proto proxy --server pool.example.com --port 3639 \
  --user WALLET.WORKER --pass c=LRS,mc=LRS --cuda 1
./miner --proto stratum --server stratum+tcp://pool.example.com:3639 \
  --user WALLET.WORKER --pass c=LRS,mc=LRS --cuda 1
```

> GMiner 3.43 does not always pick NiceHash ethstratum from the scheme in
> `--server`. For the `mining.subscribe`/`mining.notify` path pass
> `--proto stratum`; without `--proto` it usually ends up on ethproxy
> (`eth_submitLogin`/`eth_getWork`).

T-Rex with NiceHash `stratum2` (the NiceHash/MiningPoolHub protocol):

```bash
./t-rex -a ethash -o stratum2+tcp://pool.example.com:3639 \
  -u WALLET.WORKER -p c=LRS,mc=LRS --intensity 16 --kernel 0
```

> **Important:** T-Rex **does not speak EthereumStratum
> (`mining.subscribe`/`mining.submit`)** with `stratum+tcp://`,
> `ethstratum+tcp://` or `stratum+http://` for `-a ethash`, in both v0.19.14
> and v0.26.8, even though it logs `Using protocol: stratum1`. It still sends
> `eth_submitLogin` / `eth_getWork` / `eth_submitWork` (ethproxy).
>
> Only `stratum2+tcp://` / `stratum2+ssl://` use real NiceHash
> EthereumStratum/1.0.0 (`mining.subscribe` / `mining.set_difficulty` /
> `mining.notify` / `mining.submit` + `mining.extranonce.subscribe`), which
> the pool supports as described above.
>
> This stratum supports ethproxy directly:
>
> - `eth_submitLogin` → same username/password as `mining.authorize`
> - `eth_getWork` → `[headerHash, seedHash, shareTarget, noncePrefix]` in the
>   Claymore ethproxy format (with `jsonrpc:"2.0"`). The target is the first
>   58 hex digits of `floor(2^224 / difficulty)` on the standard ethash scale
>   (raw difficulty = `difficulty × 2^32`); `noncePrefix` is the 6-hex session
>   prefix the miner prepends to its nonce on submit. Rigel takes the
>   difficulty as a number and shows `34.36G (8.00)` for `d=8`; T-Rex reads
>   the target and shows the raw `34.36G` — both mine at the same difficulty.
>   Fractional difficulties such as `d=0.1` or `d=8.5` work; the target is
>   computed from the exact value. The config `difficulty` is the value after
>   dividing by 2^32, e.g. `0.1` → raw 429.5M, suitable for a 1050 Ti-class
>   card (~1 share/min).
> - `-p d=...` → sets the starting difficulty; with `allow_static_diff = true`
>   in the config it is **fixed for the session** (vardiff doesn't adjust it,
>   except the share-flood protection can still raise it). Without it (the
>   default) it behaves like `sd=`.
> - `-p sd=...` → sets the starting difficulty the same way, but **not fixed**:
>   vardiff adjusts it with the share rate like for any miner. With both `d=`
>   and `sd=`, `d=` wins.
>
> `d=` and `sd=` must be comma-separated tokens, e.g. `c=LRS,mc=LRS,sd=0.1`
> (not a substring of `sd=`, `xd=` etc.); an existing `decred=` still disables
> this override.
> - `eth_submitHashrate` → answers `true`
> - `eth_submitWork` → the pool checks the mix digest with `ethash_hash()`
>   itself, records the share and submits found blocks to the node with
>   `eth_submitWork`.
>
> ethproxy clients **don't get** `mining.set_difficulty` / `mining.notify`
> (T-Rex would log `Unregistered stratum method`). New work is pushed as
> `{"id":0,"jsonrpc":"2.0","result":[...]}`; `eth_getWork` is answered with
> the request's id and the same 4-field result.
>
> `stratum+tcp://`, `ethstratum+tcp://` or `stratum+http://` all work with
> T-Rex, as they all end up on ethproxy; `stratum2+tcp://` / `stratum2+ssl://`
> end up on NiceHash ethstratum (supported as well).
>
> GMiner 3.43 decides mainly by `--proto`: `--proto proxy` or no proto →
> ethproxy with the 4-field result above; `--proto stratum` → NiceHash
> EthereumStratum/1.0.0 (subscribe array, `mining.set_difficulty`,
> `mining.notify`), even with a `stratum+tcp://` scheme in `--server`.

### Vardiff for Ethash/Etchash

The same vardiff as the other algos. Set the share rate with `spm_min` /
`spm_max` (shares/min) in `[STRATUM]`; the samples use `0.5`–`2` (about 1
share/min), as too many small shares load the DB and network without making
the hashrate more accurate.

- Set `diff_min` / `diff_max` wide (e.g. `0.01`–`100`): the default
  `difficulty/2` is too high for ETH on the `2^32` scale.
- When the difficulty changes mid-job, the pool resends the current job
  under a new job id so the miner applies the new difficulty right away;
  ethproxy gets the new target in the work push.
- `d=X` fixes the difficulty only with `allow_static_diff = true`; `sd=X` is
  only the starting value.

> **4 GB VRAM cards (e.g. GTX 1050 Ti):** the current DAG epoch (~231) needs
> ~2.8 GB. If T-Rex reports `not enough free memory to mine ethash`, lower the
> workload with `--intensity 16 --kernel 0` or `--dag-build-mode 1` and close
> other programs using VRAM (Rigel usually fits even at lower intensity).

**Important:** with several coins on the same `algo='ethash'`, always pass
`mc=SYMBOL` (or set `autoexchange = 0` in `[STRATUM]`). Otherwise the stratum
sends jobs from every chain to the miner → the seed hash changes → Rigel has
to rebuild a multi-GB DAG on every job switch and may disconnect.

What the stratum sends to EthereumStratum miners:

- `mining.subscribe` in two shapes, by miner:
  - **NiceHash / ethminer / PhoenixMiner / lolMiner, …** →
    `[["mining.notify","<sessionId>","EthereumStratum/1.0.0"],"<extranonce1>"]`
    per the NiceHash spec (no extranonce2; the first job follows
    `mining.authorize`)
  - **Rigel** →
    `{"mining_notify":{"job_id":"72","seed_hash":"0x...","header_hash":"0x...","_clean_jobs":"true"},"extranonce":"..."}`
    (Rigel decodes the result into a struct with the fields `mining_notify`
    and `extranonce`; `mining_notify` is a single object whose `job_id`,
    `seed_hash`, `header_hash`, `_clean_jobs` are **all strings** — not an
    array of commands, and `_clean_jobs` is not a boolean. With the wrong
    shape Rigel logs `data did not match any variant of untagged enum
    ServerMessage`, but keeps mining from the notifications that follow.)
- `mining.extranonce.subscribe` → `true` (NiceHash extension; the pool never
  changes the extranonce mid-session)
- `mining.set_difficulty` → difficulty as `2^224 / target` (raw = difficulty ×
  2^32) as a **JSON number** (e.g. `[0.1]`); a string `["0.1"]` makes Rigel
  reject the notification and reconnect
- `mining.notify` → `[jobId, seedHash, headerHash, true]`; for NiceHash the
  pool strips `0x` from seed/header, Rigel keeps the `0x` form
- `mining.submit` → `[worker, jobId, nonce]`; the pool accepts the full
  16-hex nonce (extranonce already included) and the 12-hex form (NiceHash
  2-byte extranonce → 6-byte miner nonce) that gets `extranonce1` prepended

The pool computes the mix digest itself with `ethash_hash()` and sends
`eth_submitWork(nonce, header, mix)` to the node.

## 8. Checking

Watch the stratum log:

```bash
tail -f /var/log/yiimp/ethash/*.log
tail -f /var/log/yiimp/etchash/*.log
```

What you should see:

- a new job when the node has a new block
- `client ... sent diff ... powhash ...` for accepted shares
- `*** ACCEPTED <coin> <height> ...` when a block was submitted
- `*** REJECTED ...` in the reject log if the node rejects a block

## 9. Notes

- Share verification uses the light cache, not the full DAG; a new epoch
  takes a moment to build its cache.
- firopow/Kiiro are not affected; firopow's epoch values are unchanged.
- If the node's seed hash does not match the epoch computed from the height,
  the log shows `seed/epoch mismatch`; the stratum keeps using the seed hash,
  which is what the miner sees.
