# Firopow / Firo-family setup

How to enable the `firopow` algo for Firo-family coins (`FIRO`, `KIIRO`).
Thai version: [README.thai.md](README.thai.md).

## 1. SQL

```bash
mysql -u root -p yaamp < sql/2026-11-19-add_algo_firopow.sql
```

This migration adds:

| algo | port |
|---|---:|
| `firopow` | 3641 |

Keep the port in line with `[TCP] port` in the config, then flush memcache:

```bash
printf 'delete yaamp_algo_list\r\ndelete yaamp_visible_algos\r\ndelete yaamp_unvisible_algos\r\ndelete yaamp_algo_ports\r\ndelete yaamp_algo_colors\r\n' | nc 127.0.0.1 11211
```

## 2. Coin

Add the coin through the admin UI (e.g. `FIRO` or `KIIRO`):

- `algo` = `firopow`
- RPC pointing at a Firo-family node with Firopow support
- the node must support Firopow `getblocktemplate` / `submitblock`
- `enable = 1`, `auto_ready = 1`

The stratum reads the `znode` / `community` / `developer` / `datamining`
payees from GBT dynamically (both `script` and `payee` are supported). For
older FIRO nodes that don't send these fields, the previous hardcoded values
are used as a fallback.

firopow uses Bitcoin-style RPC like kawpow, not `GETH`.

## 3. Config

Sample: `stratum/config.sample/firopow.conf`

```bash
cp stratum/config.sample/firopow.conf /etc/yiimp/stratum/firopow.conf
```

Adjust `[TCP]`, `[SQL]` and `[STRATUM]` to your setup.

There is no `[ETHASH]` section for firopow; these values are built in:

- `epoch_length = 1300`
- `period_length = 1`
- `dag_init_size = 1.5 GiB`

## 4. Supervisord

The block is already in `config/supervisord.conf`:

```ini
[program:stratum-firopow]
command=stratum /etc/yiimp/stratum/firopow.conf
```

Enable it:

```bash
supervisorctl reread
supervisorctl update
supervisorctl start stratum-firopow
```

## 5. Miner

Miners use yiimp's special-algo protocol, like kawpow:

```
stratum+tcp://pool.example.com:3641
```

## 6. Notes

Adding ethash/etchash did not change firopow; all its values are still in
`stratum/algos/progpow/firopow/overrides.cpp`.
