# Block notify — วิธีใช้

yiimp stratum มี endpoint `mining.update_block` สำหรับแจ้งบล็อกใหม่จาก coin daemon
โดย `blocknotify` binary จะ connect เข้า stratum port แล้วส่ง:

```json
{"id":1,"method":"mining.update_block","params":["tu8tu5",<coinid>,"<blockhash>"]}
```

รหัสผ่านต้องตรงกับ `TCP:password` ใน config ของ stratum นั้น (ค่าเริ่มต้น `tu8tu5`)
 และ `coinid` ต้องตรงกับ `coins.id` ในตาราง `yaamp.coins`

| Coin | Symbol | Algo | coinid | stratum port |
|------|--------|------|--------|--------------|
| Larissa Coin | LRS | ethash | 7 | 3639 |
| Lumenite | LMT | homescrypt | 12 | 3644 |

---

## 1. Daemon แบบ Bitcoin Core (Lumenite, Scash, EVR, …)

daemon ที่มี option `blocknotify=` สามารถเรียก binary ได้โดยตรง:

```ini
blocknotify=/home/codex/yiimp-runtime/stratum/blocknotify 127.0.0.1:3644 12 %s
```

ถ้า daemon รันใน Docker ต้อง mount binary เข้า container และใช้ host gateway แทน `127.0.0.1`:

```yaml
services:
  lumenite:
    extra_hosts:
      - "host.docker.internal:host-gateway"
    volumes:
      - ./data:/data
      - /home/codex/yiimp-runtime/stratum/blocknotify:/yiimp/blocknotify:ro
```

จากนั้นใน `lumenite.conf`:

```ini
blocknotify=/yiimp/blocknotify host.docker.internal:3644 12 %s
```

แล้ว restart node:

```bash
cd /home/codex/lrs-nodes/lumenite-node
docker compose up -d
```

---

## 2. geth (ethash / LRS)

**geth ไม่มี option `blocknotify=`** ดังนั้นต้องเลือกอย่างใดอย่างหนึ่ง:

### 2.1 Polling (ค่าเริ่มต้น — ไม่ต้องทำอะไรเพิ่ม)

stratum ethash เรียก `eth_getWork` ทุก ~1 วินาที และสร้าง job ใหม่ทันทีที่
`eth_header` เปลี่ยน (ดู `stratum/ethash/work.cpp`, `coind_template.cpp`)
วิธีนี้ทำงานได้อยู่แล้วแม้ไม่มี blocknotify

### 2.2 Push ด้วย `eth_subscribe("newHeads")` (แนะนำ)

เพิ่ม WebSocket RPC ให้ geth ใน `docker-compose.yml`:

```yaml
    command:
      - --http
      - --http.addr=0.0.0.0
      - --http.port=8545
      - --http.api=eth,net,web3,admin
      - --ws
      - --ws.addr=0.0.0.0
      - --ws.port=8546
      - --ws.api=eth
      - "--ws.origins=*"
    ports:
      - "${NODE_TS_IP}:8545:8545"
      - "${NODE_TS_IP}:8546:8546"
```

ติดตั้ง dependency และ reinstall watcher:

```bash
sudo apt-get install -y python3-websocket
cp bin/geth-blocknotify.py /home/codex/yiimp/bin/geth-blocknotify.py
cp contrib/geth-blocknotify.service ~/.config/systemd/user/yiimp-geth-blocknotify.service
systemctl --user daemon-reload
systemctl --user enable --now yiimp-geth-blocknotify.service
```

รันด้วยมือเพื่อทดสอบ (ไม่ต้องเป็น root):

```bash
python3 bin/geth-blocknotify.py
```

ปรับค่าได้ผ่าน environment variable:

| ตัวแปร | ค่าเริ่มต้น | ความหมาย |
|--------|------------|----------|
| `GETH_WS_URL` | `ws://127.0.0.1:8546` | WebSocket RPC ของ geth |
| `BLOCKNOTIFY_BIN` | `/home/codex/yiimp-runtime/stratum/blocknotify` | blocknotify binary |
| `STRATUM_HOSTPORT` | `127.0.0.1:3639` | ethash stratum |
| `COIN_ID` | `7` | LRS coin id |
| `BLOCKNOTIFY_LOG` | `/home/codex/yiimp-runtime/log/geth-blocknotify.log` | log file |

---

## 3. ตรวจสอบว่าใช้งานได้

เปิด debug ชั่วคราวใน config ของ stratum:

```ini
[DEBUGLOG]
client = 1
```

restart stratum แล้วดู log เมื่อมีบล็อกใหม่:

```bash
systemctl --user restart yiimp-stratum@ethash.service
tail -f /home/codex/yiimp-runtime/log/stratum/stratum-ethash.log | grep 'notify: new'
```

ควรเห็น:

```
client mining.update_block 1 null
notify: new LRS block 0x9a6a183b...
client terminate
```

และฝั่ง watcher:

```bash
tail -f /home/codex/yiimp-runtime/log/geth-blocknotify.log
```

```
subscribed newHeads id=0x...
new block 0x6a9bd2 0xec85c35a...
blocknotify 0xec85c35a... -> 0
```

ปิด `client = 1` แล้ว restart stratum เมื่อทดสอบเสร็จ

---

## 4. แนวทางแก้ปัญหา

- `error connect ...` จาก blocknotify → stratum ไม่ได้ listen ชั่วคราว (กำลัง restart)
  watcher จะ retry 3 ครั้งอัตโนมัติ
- `bad password` → `TCP:password` ใน stratum config ไม่ตรงกับ `tu8tu5`
- `notify: new ...` ไม่โผล่ → ตรวจสอบ WS: `curl` ไม่ได้ใช้กับ `eth_subscribe`
  ให้ใช้ `python3 -c "import websocket"` หรือดู log ของ watcher ว่า `subscribed` สำเร็จหรือไม่
- ถ้า geth อยู่เครื่องอื่น (Tailscale) ให้ตั้ง `GETH_WS_URL=ws://<tailscale-ip>:8546`
  และ publish port 8546 บน host นั้นด้วย
