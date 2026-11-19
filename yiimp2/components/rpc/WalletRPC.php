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
                $this->accounts = $eth->eth_accounts();
                $this->error    = $eth->getError();
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
                    $hash        = $params[0] ?? null;
                    $block       = $eth->eth_getBlockByHash($hash);
                    $this->error = $eth->getError();
                    return $block;

                case 'getblockhash':
                    $n           = $params[0] ?? null;
                    $block       = $eth->eth_getBlockByNumber($n);
                    $this->error = $eth->getError();
                    return $block->hash ?? null;

                case 'gettransaction':
                case 'getrawtransaction':
                    $txid        = $params[0] ?? '';
                    $tx          = $eth->eth_getTransactionByHash($txid);
                    $this->error = $eth->getError();
                    return $tx;

                case 'getwork':
                    return false;

                case 'getpeerinfo':
                case 'listtransactions':
                case 'listsinceblock':
                    return [];

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
