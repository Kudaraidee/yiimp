# Ethash / Etchash stratum setup

ภาษาไทย — วิธีติดตั้งและเปิดใช้งาน algo `ethash` และ `etchash` บน yiimp fork นี้

## 1. สิ่งที่ต้องมี

- Node แบบ PoW เท่านั้น ใช้ได้กับ core-geth / Besu / ERIGON ที่ยังรัน Ethash/Etchash อยู่  
  **ใช้ geth หลัง Merge ไม่ได้** เพราะไม่มี `eth_getWork`
- RPC ต้องเปิดเมธอดเหล่านี้:
  - `eth_getWork`
  - `eth_blockNumber`
  - `eth_submitWork`
- ตั้ง beneficiary/reward address ของ pool ไว้ที่ node เอง ฝั่ง stratum จะเรียก `eth_submitWork` เพื่อส่งบล็อกที่ solved แล้วเท่านั้น
- ติดตั้ง dependencies สำหรับ build stratum ให้ครบ (`mysqlclient`, `libsodium`, `curl`, `pthread`, `gmp` ฯลฯ)

## 2. Build

```bash
make -C stratum buildonly
# หรือ
make -C stratum
```

ตรวจสอบว่าได้ไฟล์ `stratum/stratum` แล้ว

## 3. SQL migration

ใช้ migration ที่เพิ่มเข้ามา:

```bash
mysql -u root -p yaamp < sql/2026-11-19-add_algo_ethash_etchash.sql
```

ไฟล์นี้เพิ่ม algo 2 ตัว:

| algo | port | s |
|---|---:|---|
| `ethash`  | 3639 | `#e2d0d2` |
| `etchash` | 3640 | `#e2d0d2` |

ปรับ port ให้ตรงกับ `[TCP] port` ในไฟล์ config ของ stratum เสมอ

หลัง insert แล้วให้ flush memcache key เหล่านี้ ไม่เช่นนั้นหน้าเว็บอาจยังเห็น algo เก่า:

```bash
printf 'delete yaamp_algo_list\r\ndelete yaamp_visible_algos\r\ndelete yaamp_unvisible_algos\r\ndelete yaamp_algo_ports\r\ndelete yaamp_algo_colors\r\n' | nc 127.0.0.1 11211
```

หรือ `flush_all` ถ้าทolerable ได้

## 4. เพิ่ม coin ใน DB

เพิ่ม coin ผ่าน admin UI ของเว็บ (หรือ INSERT ลงตาราง `coins` เอง) โดยกำหนดอย่างน้อย:

- `algo` = `ethash` หรือ `etchash`
- `rpcencoding` = `GETH`
- `enable` = 1
- `auto_ready` = 1
- RPC host/port/user/pass ชี้ไปยัง node ที่รองรับ Ethash/Etchash
- symbol/contract/info อื่น ๆ ตามเหรียญที่ต้องการ เช่น `ETC` สำหรับ etchash

ฝั่งเว็บไม่มี hardcoded algo allow-list — dropdown algo อ่านจากตาราง `algos` เลยหลังจาก migration ด้านบน algo ใหม่จะปรากฏเอง

## 5. ไฟล์ config stratum

มีตัวอย่างแล้วที่:

- `stratum/config.sample/ethash.conf`
- `stratum/config.sample/etchash.conf`

คัดลอกไปยังตำแหน่งจริง เช่น:

```bash
cp stratum/config.sample/ethash.conf  /etc/yiimp/stratum/ethash.conf
cp stratum/config.sample/etchash.conf /etc/yiimp/stratum/etchash.conf
```

แก้ `[TCP]`, `[SQL]`, `[STRATUM]` ให้ตรงกับระบบจริง และให้ `STRATUM:algo` เป็น `ethash` / `etchash` เป๊ะ ๆ

### `[ETHASH]` (ไม่บังคับ)

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

| key | ความหมาย | default ethash | default etchash |
|---|---|---:|---:|
| `epoch_length` | จำนวนบล็อกต่อ epoch หลัง fork | 30000 | 60000 |
| `seed_epoch_length` | ตัวหาร seed แบบเดิม (ECIP-1099 ใช้ 30000 เสมอ) | 30000 | 30000 |
| `etchash_fork_block` | บล็อกที่เปลี่ยนไปใช้ Etchash | 0 | 11700000 |
| `etchash_fork_epoch` | epoch ที่ fork_block (0 = คำนวณจาก `fork_block / seed_epoch_length`) | 0 | 0 → ได้ 390 |
| `epoch_offset` | บวกเพิ่มเข้า epoch ที่คำนวณได้ | 0 | 0 |
| `chain_id` | ข้อมูลเท่านั้น standard Ethash PoW ไม่ได้ commit chain-id | 0 | 61 |
| `dag_init_size` | ใช้คำนวณ dataset item count ของ light verification | 1 GiB | 1 GiB |

ตัวอย่าง chain อื่น:

```ini
; Mordor Etchash testnet
[ETHASH]
epoch_length = 60000
etchash_fork_block = 2520000
etchash_fork_epoch = 84
chain_id = 63
```

stratum จะถาม `eth_getWork` จาก node แล้วใช้ **seed hash ที่ node ส่งมาเป็นตัวกำหนด epoch จริง** ส่วน `eth_blockNumber` ใช้เพื่อเติม height เฉย ๆ

## 6. Supervisord

เพิ่ม block ไว้ให้แล้วใน `config/supervisord.conf`:

- `[program:stratum-ethash]`
- `[program:stratum-etchash]`

โดยค่าเริ่มต้น `autostart=false` เปิดใช้งานด้วย:

```bash
supervisorctl reread
supervisorctl update
supervisorctl start stratum-ethash stratum-etchash
supervisorctl status
```

หรือแก้ `autostart=true` ถ้าต้องการให้-start อัตโนมัติตอน supervisord รัน

## 7. เชื่อม miner

ใช้ Ethereum Stratum (`EthereumStratum/1.0.0`) ไม่ใช่ stratum แบบ Bitcoin-style เดิมของ yiimp

ตัวอย่าง:

```bash
ethminer -U -S pool.example.com:3639 -O WALLET.WORKER:x
# Etchash แยก port
ethminer -U -S pool.example.com:3640 -O WALLET.WORKER:x
```

สิ่งที่ stratum ส่งให้ miner:

- `mining.subscribe` → protocol `EthereumStratum/1.0.0`
- `mining.set_difficulty` → difficulty แบบ 2^256 / target
- `mining.notify` → `[jobId, seedHash, headerHash, true]`
- `mining.submit` → `[worker, jobId, nonceWithoutExtranonce]`

pool เป็นคนคำนวณ mix digest เองจาก `ethash_hash()` แล้วส่ง `eth_submitWork(nonce, header, mix)` ให้ node

## 8. ตรวจสอบ

ดู log ของ stratum:

```bash
tail -f /var/log/yiimp/ethash/*.log
tail -f /var/log/yiimp/etchash/*.log
```

สัญญาณที่ควรเห็น:

- มี job ใหม่เมื่อ node ได้บล็อกใหม่
- มี `client ... sent diff ... powhash ...` เมื่อรับ share
- มี `*** ACCEPTED <coin> <height> ...` เมื่อส่งบล็อกสำเร็จ
- ถ้า node ปฏิเสธบล็อก จะมี `*** REJECTED ...` ใน reject log

## 9. หมายเหตุ

- การตรวจ share ใช้ light cache ไม่ต้องโหลด full DAG ทั้งหมด แต่ epoch ใหม่อาจใช้เวลาสร้าง cache อีกหน่อย
- firopow/Kiiro ไม่ได้ถูกกระทบ — ค่า epoch/delegation เดิมของ firopow ยังเหมือนเดิม
- ถ้า seed hash ที่ node ส่งไม่ตรงกับ epoch ที่คำนวณจาก height จะมีข้อความ `seed/epoch mismatch` ใน log และ stratum จะใช้ค่าจาก height แทน
