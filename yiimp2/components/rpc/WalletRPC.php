<?php
namespace app\components\rpc;

use Yii;
use app\components\rpc\cBitcoinRPC;
use app\components\rpc\EthereumRPC;
use app\components\rpc\MoneroRPC;
use app\components\rpc\iRPCConnector;

/**
 * Unified wallet RPC facade used by all pool backend logic.
 *
 * Wraps cBitcoinRPC, EthereumRPC, or MoneroRPC depending on the coin's
 * rpcencoding field, and exposes a Bitcoin-compatible API surface so that
 * callers do not need to know which underlying daemon they are talking to.
 *
 * Usage:
 *   $wallet = new WalletRPC($coinModel);
 *   $info   = $wallet->getinfo();
 */
class WalletRPC
{
    public string $type = 'Bitcoin';

    /** @var cBitcoinRPC|EthereumRPC|MoneroRPC */
    private mixed $rpc;

    /** Monero only: separate wallet-RPC daemon process */
    private ?MoneroRPC $rpcWallet = null;

    /** Bitcoin: whether the daemon supports getinfo directly */
    private bool $hasGetInfo = false;

    /** Ethereum: default account / master wallet address */
    private ?string $account = null;

    // Ethereum caches (reset on each getinfo call)
    private ?array  $accounts = null;
    private ?array  $info     = null;
    private mixed   $height   = 0;
    private mixed   $ethHead  = null;

    /**
     * Geth has no Bitcoin-style listtransactions RPC and native transfers do
     * not appear in eth_getLogs. Bound the block scan used to reconstruct the
     * admin wallet transaction list so the page stays responsive.
     */
    private const ETH_TX_MIN_SCAN_BLOCKS = 1000;
    private const ETH_TX_MAX_SCAN_BLOCKS = 5000;

    /** Monero: full coin model (needed for master_wallet in getblocktemplate) */
    private mixed $coin = null;

    public ?string $error = null;

    // -------------------------------------------------------------------------

    /**
     * @param object|string $userOrCoin  A Coins ActiveRecord, or a plain
     *                                   username string for backward-compat.
     */
    public function __construct(
        mixed   $userOrCoin,
        string  $pw   = '',
        string  $host = 'localhost',
        int     $port = 8332,
        ?string $url  = null
    ) {
        if (is_object($userOrCoin)) {
            $coin = $userOrCoin;
            switch ($coin->rpcencoding) {
                case 'GETH':
                    $this->type    = 'Ethereum';
                    $this->coin    = $coin;
                    $this->account = empty($coin->account) ? $coin->master_wallet : $coin->account;
                    $this->rpc     = new EthereumRPC(
                        (string) ($coin->rpchost ?? 'localhost'),
                        (int)    ($coin->rpcport ?? 8545)
                    );
                    if (empty($coin->rpchost)) {
                        $this->error = 'RPC not configured';
                    }
                    break;

                case 'XMR':
                    $this->type      = 'CryptoNote';
                    $this->coin      = $coin;
                    $this->rpc       = new MoneroRPC(
                        (string) ($coin->rpchost   ?? 'localhost'),
                        (int)    ($coin->rpcport   ?? 18081),
                        (string) ($coin->rpcuser   ?? ''),
                        (string) ($coin->rpcpasswd ?? '')
                    );
                    $this->rpcWallet = new MoneroRPC(
                        '127.0.0.1',
                        (int)    ($coin->rpcport   ?? 18081),
                        (string) ($coin->rpcuser   ?? ''),
                        (string) ($coin->rpcpasswd ?? '')
                    );
                    if (empty($coin->rpchost)) {
                        $this->error = 'RPC not configured';
                    }
                    break;

                default:
                    $this->type       = 'Bitcoin';
                    $this->rpc        = new cBitcoinRPC(
                        (string) ($coin->rpcuser   ?? ''),
                        (string) ($coin->rpcpasswd ?? ''),
                        (string) ($coin->rpchost   ?? 'localhost'),
                        (int)    ($coin->rpcport   ?? 8332),
                        $url
                    );
                    $this->hasGetInfo = (bool) ($coin->hasgetinfo ?? false);
                    if (empty($coin->rpcuser)) {
                        $this->error = 'RPC not configured';
                    }
                    break;
            }
        } else {
            // Backward-compat: direct user/password/host/port
            $this->rpc = new cBitcoinRPC($userOrCoin, $pw, $host, $port, $url);
        }
    }

    /**
     * Map an Ethereum block to the Bitcoin-like shape used by the explorer
     * views. Original keys are preserved so backend callers are unaffected.
     */
    private function ethExplorerBlock(mixed $block): array|false
    {
        if (!is_object($block) && !is_array($block)) {
            return false;
        }

        /** @var EthereumRPC $eth */
        $eth = $this->rpc;
        $get = static function (mixed $src, string $key, mixed $default = null): mixed {
            return is_object($src) ? ($src->$key ?? $default) : (is_array($src) ? ($src[$key] ?? $default) : $default);
        };

        $number = (int) $eth->decodeHex((string) ($get($block, 'number', '0x0')));
        if (!isset($this->ethHead)) {
            $this->ethHead = $eth->eth_blockNumber();
        }
        $head = $this->ethHead;
        $result = is_object($block) ? (array) $block : $block;

        $result['height'] = $number;
        $result['confirmations'] = is_numeric($head) ? max(0, ((int) $head) - $number + 1) : 0;
        $result['time'] = (int) $eth->decodeHex((string) ($get($block, 'timestamp', '0x0')));
        $result['difficulty'] = $eth->decodeHex((string) ($get($block, 'difficulty', '0x0')));
        $result['version'] = (int) $eth->decodeHex((string) ($get($block, 'version', '0x0')));
        $result['size'] = (int) $eth->decodeHex((string) ($get($block, 'size', '0x0')));
        $result['bits'] = $get($block, 'bits', '');
        $result['merkleroot'] = $get($block, 'transactionsRoot', '');
        $result['previousblockhash'] = $get($block, 'parentHash', '');

        $txhashes = [];
        $rawtxs = $get($block, 'transactions', []);
        if (is_array($rawtxs)) {
            foreach ($rawtxs as $tx) {
                $hash = (is_object($tx) || is_array($tx)) ? $get($tx, 'hash') : $tx;
                if (!empty($hash)) {
                    $txhashes[] = $hash;
                }
            }
        }
        $result['tx'] = $txhashes;

        return $result;
    }

    /** Map an Ethereum transaction to the Bitcoin-like shape used by explorer views. */
    private function ethExplorerTx(mixed $tx): array|false
    {
        if (!is_object($tx) && !is_array($tx)) {
            return false;
        }

        /** @var EthereumRPC $eth */
        $eth = $this->rpc;
        $get = static function (mixed $src, string $key, mixed $default = null): mixed {
            return is_object($src) ? ($src->$key ?? $default) : (is_array($src) ? ($src[$key] ?? $default) : $default);
        };

        $value = (float) $eth->decodeHex((string) ($get($tx, 'value', '0x0'))) / 1e18;
        $to = $get($tx, 'to', '');
        if (empty($to)) {
            $to = $get($tx, 'contractAddress', '');
        }

        $result = is_object($tx) ? (array) $tx : $tx;
        $result['txid'] = $get($tx, 'hash', '');
        $result['hex'] = $get($tx, 'hex', '');
        $result['vout'] = [[
            'value' => $value,
            'scriptPubKey' => ['addresses' => [$to]],
        ]];
        $result['vin'] = [['addr' => $get($tx, 'from', '')]];

        return $result;
    }

    /**
     * Probe a Geth peer's public JSON-RPC port. Geth's admin_peers has no
     * height, ping or byte counters, so use the round-trip time and payload
     * sizes of a real eth_blockNumber/eth_chainId request instead. Public
     * bootnodes normally answer on port 8545.
     *
     * @return array{height:?int,ping:?float,rx:int,tx:int}
     */
    private function ethPeerStats(string $addr, mixed $localChain, float $deadline): array
    {
        $empty = ['height' => null, 'ping' => null, 'rx' => 0, 'tx' => 0];
        $host = '';
        if (preg_match('/^(\d{1,3}(?:\.\d{1,3}){3}):\d+$/', $addr, $m)) {
            $host = $m[1];
        } elseif (preg_match('/^\[([^\]]+)\]:\d+$/', $addr, $m)) {
            $host = $m[1];
        }
        if ($host === '' || filter_var($host, FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return $empty;
        }
        if (!function_exists('curl_init') || microtime(true) >= $deadline) {
            return $empty;
        }

        $payload = json_encode([
            ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'eth_blockNumber', 'params' => []],
            ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'eth_chainId', 'params' => []],
        ]);
        $ch = curl_init('http://' . $host . ':8545/');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT_MS => 700,
            CURLOPT_TIMEOUT_MS     => 1500,
        ]);
        $t0   = microtime(true);
        $resp = curl_exec($ch);
        $rtt  = microtime(true) - $t0;
        curl_close($ch);
        if (!is_string($resp)) {
            return $empty;
        }
        $stats = [
            'height' => null,
            'ping'   => $rtt,
            'rx'     => strlen($resp),
            'tx'     => strlen((string) $payload),
        ];

        $data = json_decode($resp, true);
        if (!is_array($data)) {
            return $stats;
        }
        $block = null;
        $chain = null;
        foreach ($data as $row) {
            if (!is_array($row)) {
                continue;
            }
            if (($row['id'] ?? null) === 1) {
                $block = $row['result'] ?? null;
            } elseif (($row['id'] ?? null) === 2) {
                $chain = $row['result'] ?? null;
            }
        }
        if (!is_string($block) || !preg_match('/^0x[0-9a-f]+$/i', $block)) {
            return $stats;
        }
        // eth_chainId() on the local node is decoded to int; a raw hex string is
        // also accepted here for callers using etherRequest() directly.
        $expected = null;
        if (is_string($localChain) && preg_match('/^0x[0-9a-f]+$/i', $localChain)) {
            $expected = (int) hexdec($localChain);
        } elseif (is_numeric($localChain)) {
            $expected = (int) $localChain;
        }
        if ($expected !== null && is_string($chain) && preg_match('/^0x[0-9a-f]+$/i', $chain)) {
            if (hexdec($chain) !== $expected) {
                return $stats;
            }
        }
        $stats['height'] = (int) hexdec($block);
        return $stats;
    }

    /**
     * Fallback ping for peers that do not expose an HTTP RPC port: time the TCP
     * handshake to the advertised devp2p address. Only public IPs are probed.
     */
    private function ethPeerTcpPing(string $addr, float $deadline): ?float
    {
        if (microtime(true) >= $deadline) {
            return null;
        }
        $host = '';
        $port = 0;
        if (preg_match('/^(\d{1,3}(?:\.\d{1,3}){3}):(\d+)$/', $addr, $m)) {
            $host = $m[1];
            $port = (int) $m[2];
        } elseif (preg_match('/^\[([^\]]+)\]:(\d+)$/', $addr, $m)) {
            $host = $m[1];
            $port = (int) $m[2];
        }
        if ($host === '' || $port < 1 || $port > 65535) {
            return null;
        }
        if (filter_var($host, FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return null;
        }
        $url = strpos($host, ':') === false ? "tcp://{$host}:{$port}" : "tcp://[{$host}]:{$port}";
        $t0  = microtime(true);
        $sock = @stream_socket_client($url, $errno, $errstr, 0.35, STREAM_CLIENT_CONNECT);
        $rtt = microtime(true) - $t0;
        if (!$sock) {
            return null;
        }
        fclose($sock);
        return $rtt;
    }

    /**
     * Bitcoin-style getpeerinfo for Ethereum nodes.
     *
     * Geth only exposes the detailed peer list through the optional `admin`
     * namespace; fall back to net_peerCount() + web3_clientVersion() when it is
     * disabled so the admin peers page is never blank.
     */
    private function ethPeerInfo(): array
    {
        /** @var EthereumRPC $eth */
        $eth   = $this->rpc;
        $saved = $this->error;
        $peers = [];
        $localChain = $eth->eth_chainId();
        $deadline   = microtime(true) + 4.0;

        $raw = $eth->etherRequest('admin_peers', []);
        if (is_array($raw)) {
            foreach (array_slice($raw, 0, 12) as $p) {
                // JSON-RPC decodes each peer as stdClass, some nodes as array.
                if (is_object($p)) {
                    $p = (array) $p;
                }
                if (!is_array($p)) {
                    continue;
                }
                $network = is_object($p['network'] ?? null) ? (array) $p['network'] : ($p['network'] ?? []);
                $caps = $p['caps'] ?? [];
                $addr = (string) ($network['remoteAddress']
                    ?? ($p['listenAddr'] ?? ($p['ip'] ?? ($p['enode'] ?? ''))));
                $stats  = $this->ethPeerStats($addr, $localChain, $deadline);
                $ping   = $stats['ping'] ?? $this->ethPeerTcpPing($addr, $deadline);
                $peers[] = [
                    'addr'           => $addr,
                    'subver'         => (string) ($p['name'] ?? ''),
                    'currentheight'  => $stats['height'] ?? 0,
                    'pingtime'       => $ping !== null ? round($ping * 1000, 1) . ' ms' : '',
                    'services'       => is_array($caps) ? implode(' ', $caps) : (string) $caps,
                    'conntime'       => time(),
                    'startingheight' => '',
                    'lastrecv'       => time(),
                    'lastsend'       => time(),
                    'bytesrecv'      => $stats['rx'],
                    'bytessent'      => $stats['tx'],
                    'synthetic'      => false,
                ];
            }
        }

        if (empty($peers)) {
            $count   = $eth->net_peerCount();
            $version = $eth->web3_clientVersion();
            if (is_string($count) && preg_match('/^0x[0-9a-f]+$/i', $count)) {
                $count = hexdec($count);
            }
            $peers[] = [
                'addr'           => 'Peer count: ' . (int) $count,
                'subver'         => is_string($version) ? $version : '',
                'currentheight'  => 0,
                'pingtime'       => '',
                'services'       => '',
                'conntime'       => time(),
                'startingheight' => '',
                'lastrecv'       => time(),
                'lastsend'       => time(),
                'bytesrecv'      => 0,
                'bytessent'      => 0,
                'synthetic'      => true,
            ];
        }

        // admin_peers is unavailable on most mining nodes; do not surface that
        // failed probe as a page-level RPC error.
        $this->error = $saved;
        return $peers;
    }

    // -------------------------------------------------------------------------
    // Unified API (called by pool backend logic)
    // -------------------------------------------------------------------------

    public function __call(string $method, array $params): mixed
    {
        if (stripos($method, 'dump') !== false || stripos($method, 'backupwallet') !== false) {
            $this->error = "{$method} not authorized!";
            Yii::warning("{$method} rpc method is not authorized!", __METHOD__);
            return false;
        }

        // --- Ethereum --------------------------------------------------------
        if ($this->type === 'Ethereum') {
            /** @var EthereumRPC $eth */
            $eth = $this->rpc;

            if (!isset($this->accounts)) {
                $accounts    = $eth->eth_accounts();
                $this->error = $eth->getError();
                if (is_array($accounts)) {
                    // A mining node may expose no local wallet accounts while
                    // still paying rewards to the configured pool address.
                    if (!empty($this->account)) {
                        $known = array_map('strtolower', $accounts);
                        if (!in_array(strtolower($this->account), $known, true)) {
                            $accounts[] = $this->account;
                        }
                    }
                    $this->accounts = $accounts;
                } else {
                    $this->accounts = null;
                }
            }
            if (!is_array($this->accounts)) {
                return false;
            }

            switch ($method) {
                case 'getaccountaddress':
                    return !empty($params[0]) ? $params[0] : $this->account;

                case 'getinfo':
                    if (!isset($this->info)) {
                        $info    = ['accounts' => []];
                        $balance = 0.0;
                        foreach ($this->accounts as $addr) {
                            $b = (float) $eth->eth_getBalance($addr, 'latest', true);
                            $b /= 1e18;
                            $balance                 += $b;
                            $info['accounts'][$addr]  = $b;
                        }
                        $this->height        = $this->height ?: $eth->eth_blockNumber();
                        $info['balance']     = $balance;
                        $info['blocks']      = $this->height;
                        $info['gasprice']    = (float) $eth->eth_gasPrice() / 1e18;
                        $info['connections'] = $eth->net_peerCount();
                        $info['version']     = $eth->web3_clientVersion();
                        $info['chainid']     = $eth->eth_chainId();

                        // EIP-1559 fields — present on ETH mainnet, absent on ETC; ignore errors.
                        $tip = $eth->eth_maxPriorityFeePerGas();
                        if ($tip !== false) {
                            $info['maxpriorityfee'] = (float) $tip / 1e18;
                        }
                        $latestBlock = $eth->eth_getBlockByNumber('latest', false);
                        if (is_object($latestBlock) && isset($latestBlock->baseFeePerGas)) {
                            $info['basefee'] = (float) $eth->decodeHex((string) $latestBlock->baseFeePerGas) / 1e18;
                        }

                        $this->info = $info;
                    }
                    return $this->info;

                case 'getdifficulty':
                    $this->height = $eth->eth_blockNumber();
                    $this->error  = $eth->getError();
                    $block        = $eth->eth_getBlockByNumber($this->height, false);
                    $difficulty   = is_object($block) ? ($block->difficulty ?? 0) : ($block['difficulty'] ?? 0);
                    return $eth->decodeHex((string) $difficulty);

                case 'getmininginfo':
                    $this->height = $eth->eth_blockNumber();
                    $block        = $eth->eth_getBlockByNumber($this->height, false);
                    $difficulty   = is_object($block) ? ($block->difficulty ?? 0) : ($block['difficulty'] ?? 0);
                    $this->error  = $eth->getError();
                    $info = [
                        'blocks'     => $this->height,
                        'difficulty' => $eth->decodeHex((string) $difficulty),
                        'generate'   => $eth->eth_mining(),
                        'errors'     => '',
                    ];
                    // EIP-1559 nodes include baseFeePerGas in the block (ETH mainnet only)
                    $baseFee = is_object($block) ? ($block->baseFeePerGas ?? null) : ($block['baseFeePerGas'] ?? null);
                    if ($baseFee !== null) {
                        $info['basefee'] = (float) $eth->decodeHex((string) $baseFee) / 1e18;
                    }
                    return $info;

                case 'getblock':
                    $hash        = (string) ($params[0] ?? '');
                    $block       = $eth->eth_getBlockByHash($hash, true);
                    $this->error = $eth->getError();
                    return $this->ethExplorerBlock($block);

                case 'getblockhash':
                    $n           = $params[0] ?? null;
                    // eth_getBlockByNumber requires a hex quantity, not a decimal height.
                    if (is_numeric($n)) {
                        $n = '0x' . dechex((int) $n);
                    }
                    $block       = $eth->eth_getBlockByNumber($n, false);
                    $this->error = $eth->getError();
                    if (!$block) {
                        return false;
                    }
                    return is_object($block) ? ($block->hash ?? null) : ($block['hash'] ?? null);

                case 'gettransaction':
                case 'getrawtransaction':
                    $txid = (string) ($params[0] ?? '');
                    $tx   = $eth->eth_getTransactionByHash($txid);
                    if (!$tx) {
                        // Ethereum has no coinbase transaction. Backend block
                        // accounting passes the block hash for PoW rewards, so
                        // synthesize a Bitcoin-like generated transaction.
                        $block = $eth->eth_getBlockByHash($txid, false);
                        if ($block) {
                            $mapped = $this->ethExplorerBlock($block);
                            $this->error = null;
                            return [
                                'txid'          => $txid,
                                'blockhash'     => $txid,
                                'time'          => (int) ($mapped['time'] ?? 0),
                                'confirmations' => (int) ($mapped['confirmations'] ?? 0),
                                'details'       => [[
                                    'category' => 'generate',
                                    'amount'   => (float) ($this->coin->reward ?? 0),
                                ]],
                            ];
                        }
                    }
                    $this->error = $eth->getError();
                    return $this->ethExplorerTx($tx);

                case 'getwork':
                    return false;

                case 'getpeerinfo':
                    return $this->ethPeerInfo();

                case 'listsinceblock':
                    return [];

                case 'listtransactions':
                    return $this->ethListTransactions($params);

                default:
                    $res         = $eth->etherRequest($method, $params);
                    $this->error = $eth->getError();
                    return $res;
            }
        }

        // --- CryptoNote (Monero / BBR) ----------------------------------------
        if ($this->type === 'CryptoNote') {
            /** @var MoneroRPC $xmr */
            $xmr = $this->rpc;

            switch ($method) {
                case 'getinfo':
                    $res              = $xmr->call('get_info', []);
                    $res['blocks']    = $res['height'] ?? null;
                    $res['connections'] = $res['white_peerlist_size'] ?? null;
                    $balances         = $this->rpcWallet->call('get_balance', []);
                    $res['balance']   = (float) ($balances['unlocked_balance'] ?? 0) / 1e12;
                    $res['pending']   = (float) ($balances['balance'] ?? 0) / 1e12 - $res['balance'];
                    $ver              = $res['mi'] ?? null;
                    $res['version']   = (int) sprintf('%02d%02d%02d%02d',
                        $ver['ver_major'] ?? 0, $ver['ver_minor'] ?? 0,
                        $ver['ver_revision'] ?? 0, $ver['build_no'] ?? 0);
                    $this->error      = ($this->rpcWallet->error ?? '') . ($xmr->error ?? '');
                    unset($res['mi']);
                    return $res;

                case 'getmininginfo':
                    $res = $xmr->call('get_info', []);
                    $res['networkhps'] = $res['current_network_hashrate_50'] ?? null;
                    $data   = $xmr->call('get_last_block_header', []);
                    $header = $data['block_header'] ?? [];
                    $res['reward'] = (float) ($header['reward'] ?? 0) / 1e12;
                    $this->error   = $xmr->getError();
                    unset($res['current_network_hashrate_50'], $res['current_network_hashrate_350'],
                          $res['mi'], $res['grey_peerlist_size'], $res['white_peerlist_size'],
                          $res['incoming_connections_count'], $res['outgoing_connections_count'],
                          $res['max_net_seen_height'], $res['synchronization_start_height'],
                          $res['transactions_cnt_per_day'], $res['transactions_volume_per_day']);
                    return $res;

                case 'getnetworkinfo':
                    $res = $xmr->call('get_info', []);
                    $res['connections'] = $res['white_peerlist_size'] ?? null;
                    $res['networkhps']  = $res['current_network_hashrate_50'] ?? null;
                    $this->error        = $xmr->getError();
                    unset($res['current_network_hashrate_50'], $res['current_network_hashrate_350'], $res['mi']);
                    return $res;

                case 'getaccountaddress':
                    $res         = $this->rpcWallet->call('get_address', []);
                    $this->error = $this->rpcWallet->getError();
                    return $res['address'] ?? null;

                case 'getblocktemplate':
                    $gbtParams = [
                        'wallet_address' => $this->coin->master_wallet,
                        'reserve_size'   => 8,
                    ];
                    $res    = $xmr->call('get_block_template', [$gbtParams]);
                    $data   = $xmr->call('get_last_block_header', []);
                    $header = $data['block_header'] ?? [];
                    $res['coinbase'] = (float) ($header['reward'] ?? 0) / 1e4;
                    $res['reward']   = (float) ($header['reward'] ?? 0) / 1e12;
                    $this->error     = $xmr->getError();
                    return $res;

                case 'getbalance':
                    $res         = $this->rpcWallet->call('get_balance', []);
                    $this->error = $this->rpcWallet->getError();
                    return (float) ($res['unlocked_balance'] ?? 0) / 1e12;

                case 'getbalances':
                    $res         = $this->rpcWallet->call('get_balance', []);
                    $this->error = $this->rpcWallet->getError();
                    return $res;

                case 'listtransactions':
                    return $this->xmrListTransactions($xmr);

                case 'getaddress':
                    $res         = $this->rpcWallet->call('get_address', []);
                    $this->error = $this->rpcWallet->getError();
                    return $res;

                case 'get_bulk_payments':
                    $res         = $this->rpcWallet->call('get_bulk_payments', []);
                    $this->error = $this->rpcWallet->getError();
                    return $res;

                case 'get_payments':
                    $namedParams = ['payment_id' => ($params[0] ?? null)];
                    $res         = $this->rpcWallet->call('get_payments', [$namedParams]);
                    $this->error = $this->rpcWallet->getError();
                    return $res;

                case 'get_transfers':
                    $res         = $this->rpcWallet->call('get_transfers', []);
                    $this->error = $this->rpcWallet->getError();
                    return $res;

                case 'incoming_transfers':
                    $namedParams = ['transfer_type' => ($params[0] ?? null)];
                    $res         = $this->rpcWallet->call('incoming_transfers', [$namedParams]);
                    $this->error = $this->rpcWallet->getError();
                    return $res;

                case 'sendtoaddress':
                    $destination = [
                        'address' => $params[0] ?? '',
                        'amount'  => (float) ($params[1] ?? 0) * 1e12,
                    ];
                    $namedParams = [
                        'ring_size'    => 16,
                        'destinations' => [(object) $destination],
                        'payment_id'   => $params[2] ?? null,
                    ];
                    $res         = $this->rpcWallet->call('transfer', [$namedParams]);
                    $this->error = $this->rpcWallet->getError();
                    return $res;

                case 'sendmany':
                    $destinations = [];
                    foreach ($params as $dest) {
                        foreach ($dest as $addr => $amount) {
                            $destinations[] = (object) ['amount' => (float) $amount * 1e12, 'address' => $addr];
                        }
                    }
                    $namedParams = [
                        'ring_size'    => 16,
                        'destinations' => $destinations,
                    ];
                    $res         = $this->rpcWallet->call('transfer', [$namedParams]);
                    $this->error = $this->rpcWallet->getError();
                    return $res;

                case 'transfer':
                case 'transfer_original':
                    $destination = [
                        'address' => $params[1] ?? '',
                        'amount'  => (float) ($params[2] ?? 0) * 1e12,
                    ];
                    $namedParams = [
                        'ring_size'    => 16,
                        'destinations' => [(object) $destination],
                        'payment_id'   => $params[3] ?? null,
                    ];
                    $res         = $this->rpcWallet->call('transfer', [$namedParams]);
                    $this->error = $this->rpcWallet->getError();
                    return $res;

                case 'reset':
                    $res         = $this->rpcWallet->call('rescan_blockchain', []);
                    $this->error = $this->rpcWallet->getError();
                    return $res;

                case 'store':
                    $res         = $this->rpcWallet->call('store', []);
                    $this->error = $this->rpcWallet->getError();
                    return $res;

                case 'gettransactions':
                    $namedParams = [
                        'txs_hashes'     => [$params[0] ?? []],
                        'decode_as_json' => true,
                    ];
                    $res = $xmr->call('get_transactions', [$namedParams]);
                    unset($res['txs_as_hex'], $res['txs_as_json']);
                    $this->error = $xmr->getError();
                    return $res;

                default:
                    $res         = $xmr->call($method, $params);
                    $this->error = $xmr->getError();
                    return $res;
            }
        }

        // --- Bitcoin (default) -----------------------------------------------
        if ($method === 'getinfo') {
            if ($this->hasGetInfo) {
                $res = $this->rpc->call('getinfo', $params);
            } else {
                $miningInfo = $this->rpc->call('getmininginfo', []);
                if (!$miningInfo) {
                    $this->error = $this->rpc->getError();
                    return false;
                }
                $walletInfo  = $this->rpc->call('getwalletinfo', []);
                $networkInfo = $this->rpc->call('getnetworkinfo', []);
                $res = [
                    'blocks'          => $miningInfo['blocks'] ?? null,
                    'difficulty'      => $miningInfo['difficulty'] ?? null,
                    'testnet'         => 'main' !== ($miningInfo['chain'] ?? 'main'),
                    'walletversion'   => $walletInfo['walletversion'] ?? null,
                    'balance'         => $walletInfo['balance'] ?? null,
                    'keypoololdest'   => $walletInfo['keypoololdest'] ?? null,
                    'keypoolsize'     => $walletInfo['keypoolsize'] ?? null,
                    'paytxfee'        => $walletInfo['paytxfee'] ?? null,
                    'version'         => $networkInfo['version'] ?? null,
                    'protocolversion' => $networkInfo['protocolversion'] ?? null,
                    'timeoffset'      => $networkInfo['timeoffset'] ?? null,
                    'connections'     => $networkInfo['connections'] ?? null,
                    'relayfee'        => $networkInfo['relayfee'] ?? null,
                ];
            }
        } else {
            $res = $this->rpc->call($method, $params);
        }

        $this->error = $this->rpc->getError();
        return $res;
    }

    /**
     * Reconstruct a Bitcoin-like transaction list for Ethereum wallets.
     *
     * Geth has no listtransactions RPC and native transfers are not exposed by
     * eth_getLogs, so scan a bounded number of recent blocks for transactions
     * involving the requested account (or all known accounts for '*'). Block
     * rewards mined to a wallet address are included as `generate` rows.
     */
    private function ethListTransactions(array $params): array
    {
        /** @var EthereumRPC $eth */
        $eth     = $this->rpc;
        $account = strtolower((string) ($params[0] ?? ''));
        $count   = max(1, min(2500, (int) ($params[1] ?? 500)));

        $wanted = [];
        if ($account !== '' && $account !== '*') {
            $wanted = [$account];
        } else {
            foreach ($this->accounts ?? [] as $addr) {
                $wanted[] = strtolower((string) $addr);
            }
        }
        $wanted = array_values(array_unique(array_filter($wanted)));
        if (empty($wanted)) {
            return [];
        }

        $head = (int) $eth->eth_blockNumber();
        if ($head <= 0) {
            $this->error = $eth->getError();
            return [];
        }

        // More requested rows => scan more history, within a hard cap.
        $scan = max(self::ETH_TX_MIN_SCAN_BLOCKS, min(self::ETH_TX_MAX_SCAN_BLOCKS, $count * 4));
        $from = max(0, $head - $scan + 1);
        $rows = [];

        for ($n = $head; $n >= $from && count($rows) < $count; $n--) {
            $block = $eth->eth_getBlockByNumber('0x' . dechex($n), true);
            if (!is_object($block)) {
                continue;
            }

            $blockHash = (string) ($block->hash ?? '');
            $time      = (int) $eth->decodeHex((string) ($block->timestamp ?? '0x0'));
            $confs     = $head - $n + 1;
            $miner     = strtolower((string) ($block->miner ?? ''));

            if (in_array($miner, $wanted, true)) {
                $rows[] = [
                    'blockhash'     => $blockHash,
                    'time'          => $time,
                    'category'      => 'generate',
                    'amount'        => (float) ($this->coin->reward ?? 0),
                    'address'       => (string) ($block->miner ?? ''),
                    'confirmations' => $confs,
                ];
                if (count($rows) >= $count) {
                    break;
                }
            }

            $txs = $block->transactions ?? [];
            if (!is_array($txs)) {
                continue;
            }
            foreach ($txs as $tx) {
                if (!is_object($tx)) {
                    continue;
                }
                $fromAddr = strtolower((string) ($tx->from ?? ''));
                $toAddr   = strtolower((string) ($tx->to   ?? ''));
                $receive  = in_array($toAddr, $wanted, true);
                $send     = in_array($fromAddr, $wanted, true);
                if (!$receive && !$send) {
                    continue;
                }

                $value = (float) $eth->decodeHex((string) ($tx->value ?? '0x0')) / 1e18;
                $rows[] = [
                    'txid'          => (string) ($tx->hash ?? ''),
                    'blockhash'     => $blockHash,
                    'time'          => $time,
                    'category'      => $receive ? 'receive' : 'send',
                    'amount'        => $receive ? $value : -$value,
                    'address'       => $receive ? (string) ($tx->from ?? '') : (string) ($tx->to ?? ''),
                    'confirmations' => $confs,
                ];
                if (count($rows) >= $count) {
                    break 2;
                }
            }
        }

        $this->error = $eth->getError();

        // The pool's own block records are the authoritative source for mined
        // rewards. Geth reports the physical miner address in block headers,
        // which may differ from the configured pool wallet, so the chain scan
        // above alone can miss every block this coin actually found.
        $rows = array_merge($rows, $this->ethPoolMinedBlocks());

        // Deduplicate (chain + DB can describe the same block), newest first.
        $seen = [];
        $rows = array_values(array_filter($rows, static function (array $row) use (&$seen): bool {
            $key = (string) ($row['blockhash'] ?? '') . '|' . (string) ($row['txid'] ?? '');
            if ($key === '|' || isset($seen[$key])) {
                return false;
            }
            $seen[$key] = true;
            return true;
        }));
        usort($rows, static fn (array $a, array $b): int =>
            ((int) ($b['time'] ?? 0)) <=> ((int) ($a['time'] ?? 0))
        );

        return array_slice($rows, 0, $count);
    }

    /**
     * Convert the pool's `blocks` rows for this coin into Bitcoin-like
     * `generate` entries for the admin wallet transaction table.
     */
    private function ethPoolMinedBlocks(): array
    {
        $coinId = (int) ($this->coin->id ?? 0);
        if ($coinId <= 0 || Yii::$app === null || !Yii::$app->has('db')) {
            return [];
        }

        try {
            $dbRows = Yii::$app->db->createCommand(
                'SELECT height, time, amount, confirmations, blockhash, txhash ' .
                'FROM blocks WHERE coin_id = :cid ORDER BY time DESC LIMIT 2500',
                [':cid' => $coinId]
            )->queryAll();
        } catch (\Throwable $e) {
            return [];
        }

        $wallet = (string) ($this->coin->master_wallet ?? $this->account ?? '');
        $rows   = [];
        foreach ($dbRows as $b) {
            $blockHash = (string) ($b['blockhash'] ?? '');
            $rows[] = [
                'txid'          => (string) (($b['txhash'] ?? '') ?: $blockHash),
                'blockhash'     => $blockHash,
                'time'          => (int) ($b['time'] ?? 0),
                'category'      => 'generate',
                'amount'        => (float) ($b['amount'] ?? 0),
                'address'       => $wallet,
                'confirmations' => (int) ($b['confirmations'] ?? 0),
            ];
        }

        return $rows;
    }

    // Passthrough property access to the underlying connector
    public function __get(string $prop): mixed
    {
        return $this->rpc->$prop ?? null;
    }

    public function __set(string $prop, mixed $value): void
    {
        $this->rpc->$prop = $value;
    }

    // -------------------------------------------------------------------------
    // Admin console: execute a free-form RPC command string
    // -------------------------------------------------------------------------

    public function execute(string $query): mixed
    {
        if (empty($query)) {
            return '';
        }

        try {
            // Raw JSON passthrough (Bitcoin only)
            if (str_contains($query, '{') && json_decode($query)) {
                if ($this->rpc instanceof cBitcoinRPC) {
                    try {
                        return $this->rpc->requestJson($query);
                    } catch (\Exception $e) {
                        return false;
                    }
                }
                return false;
            }

            $parts   = explode(' ', trim($query));
            $command = array_shift($parts);

            $p = [];
            foreach ($parts as $part) {
                if ($part === 'true' || $part === 'false') {
                    $p[] = $part === 'true';
                } elseif (str_starts_with($part, '0x')) {
                    $p[] = $part; // keep ETH hex strings as-is
                } else {
                    $p[] = is_numeric($part) ? (0 + $part) : trim($part, '"');
                }
            }

            return match (count($p)) {
                0 => $this->$command(),
                1 => $this->$command($p[0]),
                2 => $this->$command($p[0], $p[1]),
                3 => $this->$command($p[0], $p[1], $p[2]),
                4 => $this->$command($p[0], $p[1], $p[2], $p[3]),
                5 => $this->$command($p[0], $p[1], $p[2], $p[3], $p[4]),
                6 => $this->$command($p[0], $p[1], $p[2], $p[3], $p[4], $p[5]),
                7 => $this->$command($p[0], $p[1], $p[2], $p[3], $p[4], $p[5], $p[6]),
                8 => $this->$command($p[0], $p[1], $p[2], $p[3], $p[4], $p[5], $p[6], $p[7]),
                default => 'error: too many parameters',
            };
        } catch (\Exception $e) {
            return false;
        }
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Build a Bitcoin-style listtransactions array from Monero's transfer APIs.
     */
    private function xmrListTransactions(MoneroRPC $xmr): array
    {
        $txs = [];

        // Incoming transfers
        $res = $this->rpcWallet->call('incoming_transfers', [['transfer_type' => 'all']]);
        $res = $res['transfers'] ?? [];
        $this->error = $this->rpcWallet->getError();

        foreach ($res as $k => $tx) {
            $tx['category'] = 'receive';
            $tx['txid']     = $tx['tx_hash'];
            $tx['amount']   = $tx['amount'] / 1e12;

            $raw = $xmr->call('get_transactions', [[
                'txs_hashes'     => [$tx['tx_hash']],
                'decode_as_json' => true,
            ]]);
            $raw = reset($raw['txs'] ?? []);
            if (!empty($raw)) {
                $k = (float) ($raw['block_height'] ?? 0) + ($k / 1000.0);
                unset($raw['as_hex'], $raw['tx_hash'], $raw['as_json']);
                $tx = array_merge($tx, $raw);
            }
            unset($tx['tx_hash']);
            $txs[sprintf('%015.4F', $k)] = $tx;
        }

        // Outgoing payments
        $res = $this->rpcWallet->call('get_bulk_payments', [['min_block_height' => 1]]);
        $res = $res['payments'] ?? [];

        foreach ($res as $k => $tx) {
            $tx['category'] = 'send';
            $tx['txid']     = $tx['tx_hash'];
            $tx['amount']   = $tx['amount'] / 1e12;

            $raw = $xmr->call('get_transactions', [[
                'txs_hashes'     => [$tx['tx_hash']],
                'decode_as_json' => true,
            ]]);
            $raw = reset($raw['txs'] ?? []);
            if (!empty($raw)) {
                $k = (float) ($raw['block_height'] ?? 0) + 0.5 + ($k / 1000.0);
                unset($raw['as_hex'], $raw['tx_hash'], $raw['as_json']);
                $tx = array_merge($tx, $raw);
            }
            unset($tx['tx_hash']);
            $txs[sprintf('%015.4F', $k)] = $tx;
        }

        krsort($txs);
        return array_values($txs);
    }
}
