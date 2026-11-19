# Firopow / Kiiro setup

วิธีเปิดใช้งาน algo `firopow` สำหรับเหรียญ Kiiro

## 1. SQL

```bash
mysql -u root -p yaamp < sql/2026-11-19-add_algo_firopow.sql
```

 migration นี้เพิ่ม:

| algo | port |
|---|---:|
| `firopow` | 3641 |

ปรับ port ให้ตรงกับ `[TCP] port` ใน config เสมอ แล้ว flush memcache:

```bash
printf 'delete yaamp_algo_list\r\ndelete yaamp_visible_algos\r\ndelete yaamp_unvisible_algos\r\ndelete yaamp_algo_ports\r\ndelete yaamp_algo_colors\r\n' | nc 127.0.0.1 11211
```

## 2. Coin

เพิ่ม coin ผ่าน admin UI:

- `algo` = `firopow`
- RPC ชี้ไปยัง Kiiro node ที่รองรับ Firopow
- node ต้องรองรับ `getblocktemplate` / `submitblock` แบบ Firopow
- `enable = 1`, `auto_ready = 1`

firopow ใช้ RPC แบบ Bitcoin-style เหมือน kawpow ไม่ใช่ `GETH`

## 3. Config

ตัวอย่าง: `stratum/config.sample/firopow.conf`

```bash
cp stratum/config.sample/firopow.conf /etc/yiimp/stratum/firopow.conf
```

แก้ `[TCP]`, `[SQL]`, `[STRATUM]` ให้ตรงระบบ

ไม่มี `[ETHASH]` section สำหรับ firopow — ค่าเหล่านี้ถูกกำหนดในไบนารีแล้ว:

- `epoch_length = 1300`
- `period_length = 1`
- `dag_init_size = 1.5 GiB`

## 4. Supervisord

เพิ่ม block ไว้ให้แล้วใน `config/supervisord.conf`:

```ini
[program:stratum-firopow]
command=stratum /etc/yiimp/stratum/firopow.conf
```

เปิดใช้งาน:

```bash
supervisorctl reread
supervisorctl update
supervisorctl start stratum-firopow
```

## 5. Miner

miner ใช้ protocol แบบ special algo ของ yiimp เหมือน kawpow:

```
stratum+tcp://pool.example.com:3641
```

## 6. หมายเหตุ

การเพิ่ม ethash/etchash ไม่ได้เปลี่ยนพฤติกรรม firopow ค่าเดิมทั้งหมดยังคงอยู่ใน `stratum/firopow/overrides.cpp`
