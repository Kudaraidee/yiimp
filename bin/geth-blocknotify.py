#!/usr/bin/python3
"""Forward geth `newHeads` events to the yiimp blocknotify endpoint.

geth has no `blocknotify=` option like Bitcoin Core. This watcher subscribes
to WebSocket `newHeads` and calls the standard yiimp `blocknotify` binary for
each newly accepted block, so the stratum is notified immediately instead of
waiting for the next eth_getWork poll.

See docs/blocknotify.md for setup.
"""

import json
import os
import subprocess
import sys
import time
from datetime import datetime, timezone

import websocket  # python3-websocket / websocket-client

WS_URL = os.environ.get("GETH_WS_URL", "ws://127.0.0.1:8546")
BLOCKNOTIFY = os.environ.get("BLOCKNOTIFY_BIN", "/home/codex/yiimp-runtime/stratum/blocknotify")
STRATUM = os.environ.get("STRATUM_HOSTPORT", "127.0.0.1:3639")
COIN_ID = os.environ.get("COIN_ID", "7")
LOG_FILE = os.environ.get("BLOCKNOTIFY_LOG", "/home/codex/yiimp-runtime/log/geth-blocknotify.log")


def log(message):
    line = "%s %s\n" % (datetime.now(timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ"), message)
    with open(LOG_FILE, "a") as f:
        f.write(line)
    print(line, end="", flush=True)


def notify(blockhash):
    for attempt in range(1, 4):
        try:
            result = subprocess.run(
                [BLOCKNOTIFY, STRATUM, COIN_ID, blockhash],
                stdout=subprocess.PIPE,
                stderr=subprocess.STDOUT,
                timeout=10,
            )
            output = result.stdout.decode("utf-8", "replace").strip()
            log("blocknotify %s -> %s %s" % (blockhash, result.returncode, output))
            if result.returncode == 0:
                return
        except Exception as e:
            log("blocknotify %s attempt %d failed: %s" % (blockhash, attempt, e))
        time.sleep(2)
    log("blocknotify %s give up after 3 attempts" % blockhash)


def main():
    last_hash = None
    while True:
        ws = None
        try:
            ws = websocket.create_connection(WS_URL, timeout=15)
            ws.send(json.dumps({
                "jsonrpc": "2.0",
                "id": 1,
                "method": "eth_subscribe",
                "params": ["newHeads"],
            }))
            ack = json.loads(ws.recv())
            sub_id = ack.get("result")
            if not sub_id:
                raise RuntimeError("subscribe failed: %s" % ack)
            log("subscribed newHeads id=%s" % sub_id)
            ws.settimeout(90)

            while True:
                try:
                    raw = ws.recv()
                except websocket.WebSocketTimeoutException:
                    ws.ping()
                    continue

                msg = json.loads(raw)
                if msg.get("method") != "eth_subscription":
                    continue
                params = msg.get("params", {})
                if params.get("subscription") != sub_id:
                    continue

                blockhash = params.get("result", {}).get("hash")
                if not blockhash or blockhash == last_hash:
                    continue
                last_hash = blockhash
                number = params["result"].get("number")
                log("new block %s %s" % (number, blockhash))
                notify(blockhash)

        except Exception as e:
            log("connection error: %s" % e)
            time.sleep(3)
        finally:
            if ws:
                try:
                    ws.close()
                except Exception:
                    pass


if __name__ == "__main__":
    try:
        main()
    except KeyboardInterrupt:
        sys.exit(0)
