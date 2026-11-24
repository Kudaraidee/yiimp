#!/usr/bin/env python3
"""Generate the templcheck inputs (wallet getblocktemplate answers).

fixtures/<case>/  normal templates; expected.txt is the golden master
                  (written by `make -C tests vectors`)
overflow/<case>/  inputs larger than stratum's fixed buffers, for the
                  hardened/asan runs (`make -C tests hardened|asan`)

Run from stratum/tests:  python3 gen_fixtures.py
The data is synthetic but shaped like real bitcoind/dashd answers and fully
deterministic (hashes derive from counters), so regenerating changes nothing.
"""
import hashlib
import json
import os

HERE = os.path.dirname(os.path.abspath(__file__))

# pool wallet: hash160 of the payout address (P2PKH), as validateaddress gives it
POOL_H160 = '89abcdefabbaabbaabbaabbaabbaabbaabbaabba'
WALLET = '1DYwPTpZuLjY2qApmJdHaSAuWRvEF5skCN'


def h(*parts):
    return hashlib.sha256('/'.join(str(p) for p in parts).encode()).hexdigest()


def tx(i, segwit=False):
    data = h('txdata', i) * 3                 # 96 bytes of fake raw tx
    txid = h('txid', i)
    return {
        'data': data,
        'txid': txid,
        'hash': h('wtxid', i) if segwit else txid,
        'fee': 1000 + i,
        'depends': [],
        'weight': 384,
    }


def gbt(height=900000, ntx=3, segwit=False, **extra):
    r = {
        'capabilities': ['proposal'],
        'version': 0x20000000,
        'rules': ['csv', '!segwit'] if segwit else ['csv'],
        'previousblockhash': '00000000000000000001' + h('prev', height)[20:],
        'transactions': [tx(i, segwit) for i in range(ntx)],
        'coinbaseaux': {'flags': ''},
        'coinbasevalue': 312500000 + sum(1000 + i for i in range(ntx)),
        'target': '00000000000000000002f1280000000000000000000000000000000000000000',
        'mintime': 1767225000,
        'mutable': ['time', 'transactions', 'prevblock'],
        'noncerange': '00000000ffffffff',
        'curtime': 1767225600,
        'bits': '17030ecd',
        'height': height,
    }
    if segwit:
        r['default_witness_commitment'] = '6a24aa21a9ed' + h('wc', height)
    r.update(extra)
    return r


def payees(n, name='masternode'):
    return {name: [{'payee': 'X' + h('addr', i)[:33],
                    'script': '76a914' + h('script', i)[:40] + '88ac',
                    'amount': 1000000 + i} for i in range(n)]}


def write_case(kind, name, conf, answers):
    d = os.path.join(HERE, kind, name)
    os.makedirs(d, exist_ok=True)
    with open(os.path.join(d, 'coind.conf'), 'w', newline='\n') as f:
        for k, v in conf.items():
            f.write(f'{k}={v}\n')
    for method, result in answers.items():
        with open(os.path.join(d, method + '.json'), 'w', newline='\n') as f:
            json.dump({'result': result, 'error': None, 'id': 1}, f, indent=1)
            f.write('\n')


BASE = {'algo': 'sha256', 'symbol': 'TBTC', 'wallet': WALLET,
        'script_pubkey': POOL_H160, 'rpcencoding': 'POW'}

# ---- normal templates (golden master) --------------------------------------
write_case('fixtures', 'btc-basic', BASE, {'getblocktemplate': gbt()})
write_case('fixtures', 'btc-segwit', dict(BASE, segwit=1),
           {'getblocktemplate': gbt(ntx=5, segwit=True)})
write_case('fixtures', 'btc-empty', BASE, {'getblocktemplate': gbt(ntx=0)})
write_case('fixtures', 'x11-masternodes', dict(BASE, algo='x11', symbol='TDASH'),
           {'getblocktemplate': gbt(ntx=2, **payees(2), coinbase_payload='00' * 40)})

# ---- overflow inputs -------------------------------------------------------
# 200 payees: script_dests[4096] (stack), coinb2[8K], coinbase[4096],
# coinbase_bin[1024] (stack) in coind_create_template
write_case('overflow', 'payees-200', dict(BASE, algo='x11', symbol='TDASH'),
           {'getblocktemplate': gbt(ntx=2, **payees(200))})
# 40 payees: below script_dests, but the coinbase passes 2048 hex chars,
# so binlify() overruns coinbase_bin[1024]
write_case('overflow', 'payees-40', dict(BASE, algo='x11', symbol='TDASH'),
           {'getblocktemplate': gbt(ntx=2, **payees(40))})
# 10 KB coinbase_payload: coinb2[8K]
write_case('overflow', 'coinbase-payload', dict(BASE, algo='x11', symbol='TDASH'),
           {'getblocktemplate': gbt(ntx=2, **payees(1), coinbase_payload='ab' * 5120)})
# oversized header fields: nbits[32], prevhash_hex[512], flags[64]
write_case('overflow', 'long-fields', BASE,
           {'getblocktemplate': gbt(bits='1d00ffff' * 8, previousblockhash='00' * 300,
                                    coinbaseaux={'flags': 'ff' * 64})})
# tx hash longer than hash_be[256] (stack) in the transaction loop
long_tx = tx(0)
long_tx['hash'] = long_tx['txid'] = 'cd' * 200
write_case('overflow', 'long-txhash', BASE,
           {'getblocktemplate': gbt(ntx=1, transactions=[long_tx])})

print('fixtures written')
