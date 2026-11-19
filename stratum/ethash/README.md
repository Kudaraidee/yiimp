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
- `installed` = 1 — ถ้าเป็น 0 `BackendCoinsUpdate()` จะข้าม coin นี้ ทำให้ `difficulty`, `block_height`, `connections` ค้างค่าเดิม
- RPC host/port/user/pass ชี้ไปยัง node ที่รองรับ Ethash/Etchash
- `master_wallet` = address ที่ node ใช้เป็น beneficiary
- `block_time` / `network_ttf` = ค่าเฉลี่ยจริงของเชน (เช่น ~11 วิสำหรับ Larissa)
- `powlimit_bits` = 0 — difficulty แบบ Ethereum เป็นจำนวน hash ที่คาดหวัง ไม่ต้องคูณ 2^32 แบบ Bitcoin (ตั้งที่ coin หรือปล่อยให้ค่าจากตาราง `algos` ใน migration ด้านบน)
- `reward` = ผลตอบแทนต่อบล็อกจริง ตรวจจาก node/explorer แล้วอัปเดตเป็นระยะ (เช่น Larissa ลดทุกสัปดาห์)
- symbol/contract/info อื่น ๆ ตามเหรียญที่ต้องการ เช่น `ETC` สำหรับ etchash

ฝั่งเว็บไม่มี hardcoded algo allow-list — dropdown algo อ่านจากตาราง `algos` เลยหลังจาก migration ด้านบน algo ใหม่จะปรากฏเอง

ถ้าตลาดที่ใช้เป็นสกุล quote อื่น (เช่น `LRS/USDT` บน nonkyc) ให้เก็บ `markets.price` เป็นราคาในสกุลนั้นตามข้อมูล exchange ฝั่ง backend จะดึงอัตรา `USDT/BTC` (หรือ `USDC/BTC`) เก็บในตาราง `settings` เป็น `usdt_btc` / `usdc_btc` แล้วแปลงเป็น `coins.price` ให้เอง ทั้งหน้าเว็บและ yiimp2 จะแสดงราคาตลาดเป็น BTC จากอัตราเดียวกันนี้

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

ตัวอย่าง Rigel:

```bash
./rigel -a ethash -o ethstratum+tcp://pool.example.com:3639 -u WALLET -p c=LRS,mc=LRS
```

ตัวอย่าง T-Rex 0.19/0.26:

```bash
./t-rex -a ethash -o stratum+tcp://pool.example.com:3639 \
  -u WALLET.WORKER -p c=LRS,mc=LRS --intensity 16 --kernel 0
```

> **หมายเหตุสำคัญ:** T-Rex **ไม่ได้พูด EthereumStratum แบบ `mining.subscribe`/`mining.submit`** สำหรับ `-a ethash` ทั้งของ v0.19.14 และ v0.26.8 ถึงจะขึ้น `Using protocol: stratum1` หรือใช้ scheme `ethstratum+tcp://` ก็ตาม มันยังส่ง `eth_submitLogin` / `eth_getWork` / `eth_submitWork` (ethproxy) เหมือนกัน
>
> stratum ของโปรเจกต์นี้รองรับ ethproxy โดยตรงแล้ว:
>
> - `eth_submitLogin` → ใช้ username/password แบบเดียวกับ `mining.authorize`
> - `eth_getWork` → ตอบ `[headerHash, seedHash, shareTarget]` (target มาจาก difficulty ที่ตั้งค่าไว้) ถ้าตั้ง `difficulty = 1` target จะเป็น `0xffff...ff` และ T-Rex อาจแสดง `diff: 0.00` ได้ ให้ตั้งค่า `difficulty = 16` ขึ้นไปเพื่อให้เห็น diff ที่แท้จริงและลดจำนวน share ที่ไหลเข้าพูล
> - `eth_submitHashrate` → ตอบ `true`
> - `eth_submitWork` → pool ตรวจ mix digest ด้วย `ethash_hash()` เอง บันทึก share และส่งบล็อกให้ node ด้วย `eth_submitWork` ถ้าเจอ block
>
> กับ client แบบ ethproxy pool **จะไม่ส่ง** `mining.set_difficulty` / `mining.notify` (T-Rex จะฟ้อง `Unregistered stratum method`) แต่จะส่ง work ใหม่เป็น `{"jsonrpc":"2.0","result":[...]}` แทน
>
> ใช้ `stratum+tcp://`, `ethstratum+tcp://` หรือ `stratum+http://` กับ T-Rex ก็ได้ เพราะทั้งหมดตกลงมาเป็น ethproxy ฝั่ง pool รองรับหมดแล้ว

> **การ์ด VRAM 4GB (เช่น GTX 1050 Ti):** DAG epoch ปัจจุบัน (~231) ใช้ ~2.8GB ถ้า T-Rex ฟ้อง `not enough free memory to mine ethash` ให้ลด workload ด้วย `--intensity 16 --kernel 0` หรือ `--dag-build-mode 1` และปิดโปรแกรมอื่นที่ใช้ VRAM ด้วย (Rigel มักพอแม้ intensity ต่ำกว่า)

**สำคัญ:** ถ้าเปิดหลาย coin ใน `algo='ethash'` เดียวกัน ให้ระบุ `mc=SYMBOL` เสมอ (หรือตั้ง `autoexchange = 0` ใน `[STRATUM]`) มิฉะนั้น stratum จะส่ง job จากทุก chain ให้ miner → seed hash เปลี่ยน → Rigel ต้องสร้าง DAG ใหม่หลาย GB ทุกครั้งที่ job เปลี่ยน และอาจหลุดการเชื่อมต่อ

สิ่งที่ stratum ส่งให้ miner แบบ EthereumStratum (Rigel / ethminer):

- `mining.subscribe` → `{"mining_notify":[["mining.set_difficulty","..."],["mining.notify","..."]],"extranonce":"..."}`
  (Rigel decode ผลลัพธ์เป็น struct ที่มีฟิลด์ `mining_notify` กับ `extranonce`; ถ้าตอบเป็น array แบบดั้งเดิม Rigel จะฟ้อง `data did not match any variant of untagged enum ServerMessage`)
- `mining.set_difficulty` → difficulty แบบ 2^256 / target เป็น **JSON number** (เช่น `[0.5]`); การส่งเป็น string `["0.5"]` ทำให้ Rigel reject notification แล้ว reconnect
- `mining.notify` → `[jobId, seedHash, headerHash, true]`
- `mining.submit` → `[worker, jobId, nonce]`
  pool รองรับทั้ง nonce แบบเต็ม 16 hex (Rigel/ethminer รวม extranonce ไว้แล้ว) และแบบ 8 hex ต่อท้าย extranonce1

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
