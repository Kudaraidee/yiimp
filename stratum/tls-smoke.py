#!/usr/bin/env python3
"""Smoke-test stratum plaintext listeners, shared-port TLS and TLS-only ports.

Examples:
  ./tls-smoke.py
  ./tls-smoke.py --host pool.example.com --pair 3433:4433 --pair 3639:4639
  ./tls-smoke.py --no-verify        # self-signed certs during setup
"""
import argparse
import socket
import ssl
import sys
import time

DEFAULT_PAIRS = [(3433, 4433), (3639, 4639), (3640, 4640), (3641, 4641)]
MSG = b'{"id":1,"method":"mining.subscribe","params":["tls-smoke/1.0"]}\n'


def read_reply(sock):
    data = b''
    while b'\n' not in data and len(data) < 8192:
        chunk = sock.recv(4096)
        if not chunk:
            break
        data += chunk
    return data.decode('utf-8', 'replace').strip()


def plain(host, port):
    with socket.create_connection((host, port), 5) as sock:
        sock.sendall(MSG)
        return 'plaintext', read_reply(sock)


def secure(host, port, tls_ctx, server_hostname):
    with socket.create_connection((host, port), 5) as raw:
        with tls_ctx.wrap_socket(raw, server_hostname=server_hostname) as sock:
            cert = sock.getpeercert()
            # Give the server thread a moment to enter its JSON read loop.
            time.sleep(0.2)
            sock.sendall(MSG)
            cn = dict(x[0] for x in cert.get('subject', ())).get('commonName', '?')
            mode = '%s/%s CN=%s' % (sock.version(), sock.cipher()[0], cn)
            return mode, read_reply(sock)


def wait_for_listeners(host, ports, timeout=90):
    deadline = time.time() + timeout
    down = set(ports)
    while time.time() < deadline:
        for port in list(down):
            try:
                with socket.create_connection((host, port), 0.4):
                    down.remove(port)
            except OSError:
                pass
        if not down:
            return True
        time.sleep(1)
    print('listeners not reachable: %s' % ', '.join(map(str, sorted(down))))
    return False


def parse_pair(value):
    main, tls = value.split(':', 1)
    return int(main), int(tls)


def main():
    ap = argparse.ArgumentParser(description=__doc__)
    ap.add_argument('--host', default='127.0.0.1', help='stratum host (default: 127.0.0.1)')
    ap.add_argument('--server-name', help='TLS SNI / certificate name (default: --host)')
    ap.add_argument('--no-verify', action='store_true', help='do not verify the server certificate')
    ap.add_argument('--pair', action='append', type=parse_pair,
                    help='MAIN_PORT:TLS_PORT (repeatable; defaults to all four algos)')
    args = ap.parse_args()

    pairs = args.pair or DEFAULT_PAIRS
    server_name = args.server_name or args.host
    ctx = ssl._create_unverified_context() if args.no_verify else ssl.create_default_context()

    ports = sorted({p for pair in pairs for p in pair})
    if not wait_for_listeners(args.host, ports):
        return 1

    failed = False
    for main_port, tls_port in pairs:
        checks = (
            ('%d plaintext' % main_port, lambda p=main_port: plain(args.host, p)),
            ('%d shared TLS' % main_port,
             lambda p=main_port: secure(args.host, p, ctx, server_name)),
            ('%d dedicated TLS' % tls_port,
             lambda p=tls_port: secure(args.host, p, ctx, server_name)),
        )
        for name, check in checks:
            try:
                mode, reply = check()
                ok = '"result"' in reply and '"error":null' in reply
                print('%-18s %-5s %-58s %s' % (name, 'PASS' if ok else 'FAIL', mode, reply[:90]))
                failed = failed or not ok
            except Exception as exc:
                print('%-18s FAIL  %s: %s' % (name, type(exc).__name__, exc))
                failed = True

        name = '%d rejects plaintext' % tls_port
        try:
            with socket.create_connection((args.host, tls_port), 5) as sock:
                sock.sendall(MSG)
                data = sock.recv(4096)
            if data:
                print('%-18s FAIL  got %r' % (name, data[:80]))
                failed = True
            else:
                print('%-18s PASS  connection closed with no stratum reply' % name)
        except OSError as exc:
            print('%-18s PASS  %s: %s' % (name, type(exc).__name__, exc))

    return 1 if failed else 0


if __name__ == '__main__':
    sys.exit(main())
