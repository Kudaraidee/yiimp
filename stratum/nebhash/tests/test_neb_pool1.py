#!/usr/bin/env python3
"""Smoke-test a Yiimp nebhash listener with the neb-pool/1 protocol.

The test verifies the subscribe shape, local address validation, target/model
push order, notification fields, multi-login authorize and NEB error frames.
It does not attempt to find a share (that needs the NEB PoW engine).
"""

import argparse
import json
import socket
import sys

WALLET = "neb1vnf9ulnttzvdmrn58ymqqthmc074pegvc0ay8en8gnepwdwq2y3s2vyazg"
PROTOCOL = "neb-pool/1"
SUBSCRIBE_KEYS = {
    "session",
    "extranonce1",
    "extranonce2_size",
    "chain_id",
    "dataset_hash",
}
NOTIFY_KEYS = {
    "job_id", "clean", "version", "parent", "time_start", "time_window",
    "bits", "target", "share_target", "coinbase_prefix", "coinbase_suffix",
    "branch", "tx_count", "shares_root", "height", "slot", "weights_hash",
    "expiry",
}


def is_hex(value, length):
    return isinstance(value, str) and len(value) == length and all(
        c in "0123456789abcdefABCDEF" for c in value
    )


class Client:
    def __init__(self, host, port, timeout):
        self.sock = socket.create_connection((host, port), timeout)
        self.sock.settimeout(timeout)
        self.stream = self.sock.makefile("rb")
        self.next_id = 1
        self.messages = []

    def send(self, method, params):
        msg_id = self.next_id
        self.next_id += 1
        line = json.dumps(
            {"id": msg_id, "method": method, "params": params},
            separators=(",", ":"),
        ) + "\n"
        self.sock.sendall(line.encode())
        return msg_id

    def read(self):
        line = self.stream.readline()
        if not line:
            raise RuntimeError("pool closed the connection")
        msg = json.loads(line)
        self.messages.append(msg)
        return msg

    def read_response(self, msg_id, max_lines=100):
        for _ in range(max_lines):
            msg = self.read()
            if msg.get("id") == msg_id:
                return msg
        raise RuntimeError("no response for id %s" % msg_id)

    def read_method(self, method, max_lines=100):
        for _ in range(max_lines):
            msg = self.read()
            if msg.get("method") == method:
                return msg
        raise RuntimeError("no %s notification" % method)

    def close(self):
        self.sock.close()


def check_error(msg, code):
    assert msg.get("result") is None, msg
    err = msg.get("error")
    assert isinstance(err, list) and len(err) == 2, msg
    assert err[0] == code, msg


def le_shift_hex(hex_value, k):
    """Shift a 32-byte little-endian NEB target left by k bits."""
    value = int.from_bytes(bytes.fromhex(hex_value), "little")
    value = (value << k) & ((1 << 256) - 1)
    return value.to_bytes(32, "little").hex()


def check_password_k(args, password, k, label):
    """d=/sd= must select the session's NEB shift k before job publication."""
    c = Client(args.host, args.port, args.timeout)
    try:
        sid = c.send("mining.subscribe", ["neb-smoke-test/1", PROTOCOL])
        reply = c.read_response(sid)
        assert reply.get("error") is None, reply

        aid = c.send("mining.authorize", [args.wallet + ".k", password])
        reply = c.read_response(aid)
        assert reply.get("error") is None and reply.get("result") is True, reply

        target_msg = c.read_method("mining.set_target")
        target = target_msg.get("params", [None])[0]
        assert is_hex(target, 64), target_msg
        c.read_method("mining.set_model")

        notify_msg = c.read_method("mining.notify")
        job = notify_msg.get("params", [None])[0]
        assert isinstance(job, dict) and is_hex(job.get("share_target"), 64), notify_msg

        expected = le_shift_hex(job["share_target"], k)
        assert target.lower() == expected.lower(), (label, target, expected, job["share_target"])
        print("PASS %s -> k=%d" % (label, k))
    finally:
        c.close()


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--host", default="127.0.0.1")
    ap.add_argument("--port", type=int, default=3645)
    ap.add_argument("--wallet", default=WALLET)
    ap.add_argument("--timeout", type=float, default=10.0)
    args = ap.parse_args()

    c = Client(args.host, args.port, args.timeout)
    try:
        sid = c.send("mining.subscribe", ["neb-smoke-test/1", PROTOCOL])
        reply = c.read_response(sid)
        assert reply.get("error") is None, reply
        result = reply.get("result")
        assert isinstance(result, dict), reply
        assert set(result) == SUBSCRIBE_KEYS, result
        assert is_hex(result["extranonce1"], 8), result
        assert isinstance(result["extranonce2_size"], int)
        assert result["extranonce2_size"] >= 8, result
        assert is_hex(result["chain_id"], 64), result
        assert is_hex(result["dataset_hash"], 64), result
        assert isinstance(result["session"], str) and result["session"], result
        print("PASS subscribe:", result)

        aid = c.send("mining.authorize", [args.wallet + ".smoke", ""])
        reply = c.read_response(aid)
        assert reply.get("error") is None and reply.get("result") is True, reply

        target_msg = c.read_method("mining.set_target")
        target_params = target_msg.get("params", [])
        assert len(target_params) == 1 and is_hex(target_params[0], 64), target_msg

        model_msg = c.read_method("mining.set_model")
        model_params = model_msg.get("params", [])
        assert len(model_params) == 3, model_msg
        assert isinstance(model_params[0], int), model_msg
        assert is_hex(model_params[1], 64), model_msg
        assert isinstance(model_params[2], str) and len(model_params[2]) in (26000, 58000), len(model_params[2])

        notify_msg = c.read_method("mining.notify")
        notify_params = notify_msg.get("params", [])
        assert len(notify_params) == 1, notify_msg
        job = notify_params[0]
        assert set(job) == NOTIFY_KEYS, job
        assert isinstance(job["job_id"], str) and job["job_id"], job
        assert isinstance(job["clean"], bool) and job["clean"], job
        assert is_hex(job["parent"], 64), job
        assert is_hex(job["target"], 64) and is_hex(job["share_target"], 64), job
        assert isinstance(job["branch"], list), job
        assert is_hex(job["shares_root"], 64) and is_hex(job["weights_hash"], 64), job
        print("PASS target/model/notify")

        aid = c.send("mining.authorize", [args.wallet + ".devfee", "solo"])
        reply = c.read_response(aid)
        assert reply.get("error") is None and reply.get("result") is True, reply

        did = c.send("mining.suggest_difficulty", [12345])
        reply = c.read_response(did)
        assert reply.get("error") is None and reply.get("result") is True, reply
        assert not any(m.get("method") == "mining.set_difficulty" for m in c.messages), c.messages
        print("PASS multi-login and no generic set_difficulty")

        check_password_k(args, "d=14", 14, "d= pins k")
        check_password_k(args, "sd=12", 12, "sd= seeds k")
        check_password_k(args, "sd=12,d=14", 14, "d= wins over sd=")

        eid = c.send("mining.submit", [args.wallet + ".unknown", "deadbeef", "00", "0", "0"])
        check_error(c.read_response(eid), 24)

        uid = c.send("mining.not_a_method", [])
        check_error(c.read_response(uid), 20)
        print("PASS NEB error shapes")
    finally:
        c.close()


if __name__ == "__main__":
    try:
        main()
    except Exception as exc:
        print("FAIL: %s" % exc, file=sys.stderr)
        raise
