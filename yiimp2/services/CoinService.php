<?php

namespace app\services;

use Yii;
use app\exchanges\ExchangeFactory;
use app\models\Coins;
use app\models\Connections;
use app\models\Mining;
use app\components\rpc\WalletRPC;

/**
 * CoinService — wallet connectivity checks, reward/difficulty updates, GitHub version tracking.
 *
 * Ported from:
 *   web/yaamp/core/backend/coins.php → updateCoinStats, updateVersionFromGithub
 *   web/yaamp/core/backend/rawcoins.php → updateRawCoins
 *
 * Per-exchange raw-coin discovery logic lives in ExchangeDriver subclasses
 * under app\exchanges\drivers\.
 *
 * External utility methods used (already in Yii2 components):
 *   Yii::$app->YiimpUtils->get_algos()       ← YIIMP_get_algos()
 *   Yii::$app->YiimpUtils->pool_rate($algo)  ← YIIMP_pool_rate()
 *   Yii::$app->YiimpUtils->coin_nethash($c)  ← YIIMP_coin_nethash()
 *   Yii::$app->ConversionUtils->decode_compact()  ← decode_compact()
 *   Yii::$app->ConversionUtils->target_to_diff()  ← target_to_diff()
 */
class CoinService
{
    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Poll every installed coin's wallet daemon: update difficulty, block height,
     * reward, connection count, master-wallet address, rpc-encoding detection,
     * and profitability index fields.
     * Ports: BackendCoinsUpdate()
     */
    public function updateCoinStats(): void
    {
        $t1       = microtime(true);
        $poolRate = [];

        foreach (Yii::$app->YiimpUtils->get_algos() as $algo) {
            $poolRate[$algo] = Yii::$app->YiimpUtils->pool_rate($algo);
        }

        // --- Phase 1: per-coin RPC queries -----------------------------------
        $coins = Coins::find()->where(['installed' => 1])->all();

        foreach ($coins as $coin) {
            $remote = new WalletRPC($coin);
            $info   = $remote->getinfo();

            if (!$info && $coin->enable) {
                Yii::info("{$coin->symbol} no getinfo, retrying…", __CLASS__);
                sleep(3);
                $info = $remote->getinfo();
                if (!$info) {
                    Yii::info("{$coin->symbol} disabled after 2 failed attempts. {$remote->error}", __CLASS__);
                    $coin->enable      = false;
                    $coin->connections = 0;
                    $coin->save();
                    continue;
                }
            }

            if ($coin->auto_ready && !empty($info)) {
                $coin->enable = true;
            } elseif (empty($info)) {
                continue;
            }

            // Difficulty
            $difficulty = $info['difficulty'] ?? $remote->getdifficulty();
            if (is_array($difficulty)) {
                $coin->difficulty     = $difficulty['proof-of-work'] ?? null;
                $coin->difficulty_pos = $difficulty['proof-of-stake'] ?? null;
            } else {
                $coin->difficulty = $difficulty;
            }
            if ($coin->algo === 'quark') {
                $coin->difficulty /= 0x100;
            }
            if (!$coin->difficulty) {
                $coin->difficulty = 1;
            }

            $coin->errors     = $info['errors'] ?? '';
            if (str_contains((string) $coin->errors, 'check your network connection')) {
                $coin->errors = '';
            }
            $coin->txfee      = $info['paytxfee'] ?? '';
            $coin->connections = $info['connections'] ?? '';
            $coin->multialgos = (int) isset($info['pow_algo_id']);
            $coin->balance    = $info['balance'] ?? 0;
            $coin->stake      = $info['stake'] ?? $coin->stake;
            $coin->mint       = (float) Yii::$app->db->createCommand(
                "SELECT SUM(amount) FROM blocks WHERE coin_id=:id AND category='immature'",
                [':id' => $coin->id]
            )->queryScalar();

            // Detect master wallet address on first run
            if (is_null($coin->master_wallet)) {
                if ($coin->rpcencoding === 'DCR' && empty($coin->account)) {
                    $coin->account = 'default';
                }
                $coin->master_wallet = $remote->getaccountaddress($coin->account);
            }

            // Auto-detect rpcencoding
            if (empty($coin->rpcencoding)) {
                $coin->rpcencoding = $this->detectRpcEncoding($coin, $remote);
            }

            // Detect submitblock support
            if (is_null($coin->hassubmitblock)) {
                $remote->submitblock('');
                $coin->hassubmitblock = strcasecmp((string) $remote->error, 'method not found') !== 0;
            }

            // Detect AuxPoW support
            if (is_null($coin->auxpow)) {
                $remote->getauxblock();
                $coin->auxpow = strcasecmp((string) $remote->error, 'method not found') !== 0;
            }

            // Block template / reward
            $template = $this->getBlockTemplate($coin, $remote);

            if ($template && isset($template['coinbasevalue'])) {
                $coin->reward = $template['coinbasevalue'] / 1e8 * $coin->reward_mul;
                $this->applyRewardDeductions($coin, $template);

                if (isset($template['bits'])) {
                    $target = Yii::$app->ConversionUtils->decode_compact($template['bits']);
                    $coin->difficulty = Yii::$app->ConversionUtils->target_to_diff($target);
                }

            } elseif (in_array($coin->rpcencoding, ['GETH', 'NIRO'], true)) {
                $coin->auto_ready = ($coin->connections > 0);

            } elseif (strcasecmp((string) $remote->error, 'method not found') === 0) {
                $template = $remote->getmemorypool();
                if ($template && isset($template['coinbasevalue'])) {
                    $coin->usememorypool = true;
                    $coin->reward        = $template['coinbasevalue'] / 1e8 * $coin->reward_mul;
                    if (isset($template['bits'])) {
                        $target = Yii::$app->ConversionUtils->decode_compact($template['bits']);
                        $coin->difficulty = Yii::$app->ConversionUtils->target_to_diff($target);
                    }
                } else {
                    $coin->auto_ready = false;
                    $coin->errors     = $remote->error;
                }

            } elseif ($coin->symbol === 'ZEC' || $coin->rpcencoding === 'ZEC') {
                $this->applyZecReward($coin, $remote, $template);

            } elseif ($coin->rpcencoding === 'DCR') {
                $wi = $remote->walletinfo();
                $coin->auto_ready = ($coin->connections > 0 && ($wi['daemonconnected'] ?? false));
                if ($coin->auto_ready && !($wi['unlocked'] ?? false)) {
                    Yii::warning("{$coin->symbol} wallet is not unlocked!", __CLASS__);
                }

            } else {
                $coin->auto_ready = false;
                $coin->errors     = $remote->error;
            }

            if (strcasecmp((string) $coin->errors, 'No more PoW blocks') === 0) {
                $coin->dontsell   = true;
                $coin->auto_ready = false;
            }

            // Block height + TTF
            if (isset($info['blocks'])) {
                if ($coin->block_height != $info['blocks']) {
                    $count = $info['blocks'] - $coin->block_height;
                    $ttf   = $count > 0 ? (time() - (int) $coin->last_network_found) / $count : 0;
                    if (is_null($coin->actual_ttf)) {
                        $coin->actual_ttf = $ttf;
                    }
                    $coin->actual_ttf       = $this->percentFeedback((float) $coin->actual_ttf, $ttf, 5);
                    $coin->last_network_found = time();
                }
                $coin->block_height = $info['blocks'];
            } else {
                Yii::info("{$coin->symbol} wallet is missing blocks in info-array", __CLASS__);
            }

            $coin->version = substr((string) ($info['version'] ?? ''), 0, 32);

            if ($coin->powend_height > 0 && $coin->block_height > $coin->powend_height && $coin->auto_ready) {
                $coin->auto_ready = false;
                $coin->errors     = 'PoW end reached';
            }

            // Cache wallet_info JSON in specifications so public explorer pages
            // (overview + peers popup) can read without live RPC calls.
            $walletInfo = json_decode($coin->specifications ?: '{}', true) ?: [];

            $miningInfo = $remote->getmininginfo();
            if ($miningInfo) {
                $nh = null;
                if (isset($miningInfo['networkhashps'])) {
                    $raw = $miningInfo['networkhashps'];
                    $nh  = is_array($raw) ? (float) ($raw[$coin->algo] ?? 0) : (float) $raw;
                } elseif (isset($miningInfo['netmhashps'])) {
                    $nh = (float) $miningInfo['netmhashps'] * 1e6;
                }
                if ($nh !== null && $nh > 0) {
                    $walletInfo['networkhashps'] = $nh;
                }
            }

            $peerList = $remote->getpeerinfo();
            if (is_array($peerList)) {
                $peers = [];
                foreach ($peerList as $peer) {
                    $addr = $peer['addr'] ?? '';
                    if ($addr === '') continue;
                    if (str_contains($addr, '127.0.0.1')) continue;
                    if (str_contains($addr, '192.168.'))  continue;
                    if (str_contains($addr, 'yiimp'))     continue;
                    $peers[] = $addr;
                }
                sort($peers);
                $walletInfo['peers'] = $peers;
            }

            $walletInfo['updated_at']   = time();
            $coin->specifications       = json_encode($walletInfo);

            $coin->save();

            // Refresh pool balances if wallet is ahead of DB
            if ($coin->available < 0 || $coin->cleared > $coin->balance) {
                (new BlockService())->updatePoolBalances($coin->id);
            }
        }

        // --- Phase 2: profitability index ------------------------------------
        $coins = Coins::find()->where(['enable' => 1])->orderBy(['auxpow' => SORT_DESC])->all();

        foreach ($coins as $coin) {
            $coin = Coins::findOne($coin->id);
            if (!$coin) {
                continue;
            }

            if ($coin->difficulty) {
                $coin->network_hash = Yii::$app->YiimpUtils->coin_nethash($coin);
                $coin->index_avg    = $coin->reward * $coin->price * 10000 / $coin->difficulty;

                if (!$coin->auxpow && $coin->rpcencoding === 'POW') {
                    $indexAux        = (float) Yii::$app->db->createCommand(
                        "SELECT SUM(index_avg) FROM coins WHERE enable AND visible AND auto_ready AND auxpow AND algo=:algo",
                        [':algo' => $coin->algo]
                    )->queryScalar();
                    $coin->index_avg += $indexAux;
                }
            }

            if ($coin->network_hash) {
                $coin->network_ttf = (int) min($coin->difficulty * 0x100000000 / $coin->network_hash, 2147483647);
            }

            if (isset($poolRate[$coin->algo]) && $poolRate[$coin->algo]) {
                $coin->pool_ttf = (int) min(
                    Yii::$app->YiimpUtils->coin_nethash($coin) / $poolRate[$coin->algo] * ((float) ($coin->actual_ttf ?? 0)),
                    2147483647
                );
            }

            // Download remote coin image once and store locally
            if (str_contains((string) $coin->image, 'http')) {
                $data        = @file_get_contents($coin->image);
                $localPath   = "/images/coin-{$coin->id}.png";
                $coin->image = $localPath;
                $htdocs      = defined('YIIMP_HTDOCS') ? YIIMP_HTDOCS : '';
                if ($data && $htdocs) {
                    @unlink($htdocs . $localPath);
                    file_put_contents($htdocs . $localPath, $data);
                }
            }

            $coin->save();
        }

        $elapsed = microtime(true) - $t1;
        Yii::debug(sprintf('%s took %.3fs', __METHOD__, $elapsed), 'performance');
    }

    /**
     * Check GitHub for newer wallet releases and send an email summary if found.
     * Ports: BackendCoinsVersionUpdate()
     */
    public function updateVersionFromGithub(string $algo = ''): void
    {
        $coins = $algo === ''
            ? Coins::find()->where(['installed' => 1])->orderBy('algo')->all()
            : Coins::find()->where(['installed' => 1, 'algo' => $algo])->all();

        $currentAlgo = '';
        $algoText    = '';
        $mailText    = '';

        foreach ($coins as $coin) {
            if ($currentAlgo !== $coin->algo) {
                if ($algoText !== '') {
                    $mailText .= "#### Algo: {$currentAlgo} ####\n{$algoText}";
                }
                $currentAlgo = $coin->algo;
                $algoText    = '';
            }

            $prefix = "{$coin->name}({$coin->symbol}) ID:{$coin->id} algo:{$coin->algo}";

            if (empty($coin->link_github) || !str_contains((string) $coin->link_github, 'github')) {
                $algoText .= "{$prefix} : missing github-repo\n";
                if ($coin->version_installed !== $coin->version_github) {
                    $algoText .= "{$prefix} : installed ({$coin->version_installed}) differs from release ({$coin->version_github})\n";
                }
                continue;
            }

            $apiUrl = str_replace('https://github.com/', 'https://api.github.com/repos/', $coin->link_github) . '/releases/latest';
            Yii::debug("GitHub version check: {$coin->name} ({$coin->symbol})", __CLASS__);

            $ch = curl_init($apiUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 5,
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_USERAGENT      => 'yiimp-version-checker/2.0',
            ]);
            if (defined('GITHUB_ACCESSTOKEN')) {
                curl_setopt($ch, CURLOPT_USERPWD, GITHUB_ACCESSTOKEN);
            }
            $obj = json_decode(strip_tags(curl_exec($ch)));
            curl_close($ch);

            if ($obj && isset($obj->tag_name) && $obj->tag_name !== '' && $obj->tag_name !== $coin->version_github) {
                Yii::info("New release for {$coin->symbol}: {$obj->tag_name}", __CLASS__);
                Yii::$app->db->createCommand(
                    'UPDATE coins SET version_github=:tag WHERE id=:id',
                    [':tag' => $obj->tag_name, ':id' => $coin->id]
                )->execute();
                $algoText .= "{$prefix} : New release ({$obj->tag_name})\n";
            }

            if ($coin->version_installed !== $coin->version_github) {
                $algoText .= "{$prefix} : installed ({$coin->version_installed}) differs from release ({$coin->version_github})\n";
            }

            sleep(5); // respect GitHub rate limit
        }

        // Flush last algo block
        if ($algoText !== '') {
            $mailText .= "#### Algo: {$currentAlgo} ####\n{$algoText}";
        }

        if ($mailText !== '') {
            Yii::info($mailText, __CLASS__);
            $admin = defined('YIIMP_ADMIN_EMAIL') ? YIIMP_ADMIN_EMAIL : (Yii::$app->params['adminEmail'] ?? '');
            if ($admin) {
                try {
                    Yii::$app->mailer->compose()
                        ->setTo($admin)
                        ->setSubject('new wallet versions found')
                        ->setTextBody($mailText)
                        ->send();
                } catch (\Throwable $e) {
                    Yii::warning("mailer failed: {$e->getMessage()}", __CLASS__);
                }
            }
        }
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function percentFeedback(float $v, float $n, float $p): float
    {
        return ($v * (100 - $p) + $n * $p) / 100;
    }

    private function detectRpcEncoding(Coins $coin, WalletRPC $remote): string
    {
        $etherCoins = ['ETH'];
        $zecCoins   = ['YEC','ZCL','ZEN','ZEC'];
        $zecParams  = [
            'ARRR' => ['ZEC','ZcashPoW',4], 'HUSH' => ['ZEC','ZcashPoW',4], 'KMD' => ['ZEC','ZcashPoW',4],
            'ANON' => ['ZEC','AnonyPoW',1], 'GLINK' => ['ZEC','sngemPoW',13],
            'BTCZ' => ['ZEC','BitcoinZ',13], 'BTG'  => ['POW','BgoldPoW',13],
        ];

        if ($coin->symbol === 'ETH' || in_array($coin->symbol, $etherCoins, true)) {
            return 'GETH';
        }
        if ($coin->symbol === 'DCR') {
            return 'DCR';
        }
        if ($coin->symbol === 'NIRO') {
            return 'NIRO';
        }
        if (in_array($coin->symbol, $zecCoins, true)) {
            $coin->personalization = 'ZcashPoW';
            $coin->powlimit_bits   = 13;
            return 'ZEC';
        }
        if (isset($zecParams[$coin->symbol])) {
            [$enc, $pers, $bits] = $zecParams[$coin->symbol];
            $coin->personalization = $pers;
            $coin->powlimit_bits   = $bits;
            return $enc;
        }

        $diff = $remote->getdifficulty();
        return is_array($diff) ? 'POS' : 'POW';
    }

    private function getBlockTemplate(Coins $coin, WalletRPC $remote): mixed
    {
        if ($coin->usemweb) {
            return $remote->getblocktemplate('{"rules":["segwit","mweb"]}');
        }
        if ($coin->usesegwit) {
            return $remote->getblocktemplate('{"rules":["segwit"]}');
        }
        return $remote->getblocktemplate('{}');
    }

    private function applyRewardDeductions(Coins $coin, array $template): void
    {
        if (isset($template['payee_amount']) && $coin->symbol !== 'LIMX') {
            $coin->charity_amount = ($coin->symbol === 'TAC' && isset($template['_V2']))
                ? $template['_V2'] / 1e8
                : (float) $template['payee_amount'] / 1e8;
            $coin->reward -= $coin->charity_amount;
        }

        switch ($coin->symbol) {
            case 'XZC':
                $coin->reward         = ($template['coinbasevalue'] ?? 0) / 1e8 * $coin->reward_mul;
                $coin->charity_amount = $coin->reward * $coin->charity_percent / 100;
                break;

            case 'BNODE':
            case 'BCRS':
            case 'IOTS':
                if (isset($template['masternode']) && ($template['masternode_payments_started'] ?? false)) {
                    $coin->reward -= ($template['masternode']['amount'] ?? 0) / 1e8;
                }
                if (isset($template['fundreward'])) {
                    $coin->reward -= ($template['fundreward']['amount'] ?? 0) / 1e8;
                }
                if (isset($template['evolution'])) {
                    $coin->reward -= ($template['evolution']['amount'] ?? 10000000) / 1e8;
                }
                break;

            default:
                if (isset($template['masternode']) && ($template['masternode_payments_enforced'] ?? false)
                    && ($template['masternode_payments_started'] ?? false)
                ) {
                    $mn = $template['masternode'];
                    if (is_array($mn) && !isset($mn['amount'])) {
                        foreach ($mn as $mnPayee) {
                            $coin->reward -= ($mnPayee['amount'] ?? 0) / 1e8;
                        }
                    } else {
                        $coin->reward -= ($mn['amount'] ?? 0) / 1e8;
                    }
                    $coin->hasmasternodes = true;
                }

                if (isset($template['devfee'])) {
                    $devfee = $template['devfee'];
                    if (is_array($devfee) && !isset($devfee['amount'])) {
                        foreach ($devfee as $devPayee) {
                            $coin->reward -= ($devPayee['amount'] ?? 0) / 1e8;
                        }
                    } else {
                        $coin->reward -= ($devfee['amount'] ?? 0) / 1e8;
                    }
                }

                if (!empty($coin->charity_address) && !$coin->charity_amount) {
                    $coin->reward -= $coin->reward * $coin->charity_percent / 100;
                }
                break;
        }
    }

    private function applyZecReward(Coins $coin, WalletRPC $remote, mixed $template): void
    {
        if ($template && isset($template['coinbasetxn'])) {
            $blockSubsidy        = $remote->getblocksubsidy();
            $coin->reward        = ($blockSubsidy['miner'] ?? 0);
            $coin->charity_amount = ($blockSubsidy['founders'] ?? 0);

            if (!$coin->reward) {
                $txn                 = $template['coinbasetxn'];
                $coin->charity_amount = ($txn['foundersreward'] ?? 0) / 1e8;
                $coin->reward         = $coin->charity_amount * 4 + ($txn['fee'] ?? 0) / 1e8;
            }

            if (isset($template['masternode']) && ($template['masternode_payments_enforced'] ?? false)) {
                $coin->reward -= ($template['masternode']['amount'] ?? 0) / 1e8;
                $coin->hasmasternodes = true;
            }
            if (isset($template['payee_amount']) && ($template['masternode_payments'] ?? false)) {
                $coin->charity_amount = $template['payee_amount'] / 1e8;
                $coin->reward        -= $coin->charity_amount;
            }

            $miningInfo      = $remote->getmininginfo();
            $coin->difficulty = $miningInfo['difficulty'] ?? $coin->difficulty;
        } else {
            $coin->auto_ready = false;
            $coin->errors     = $remote->error;
        }
    }

    // =========================================================================
    // Admin coinwallet page queries
    // =========================================================================

    /**
     * Coins shown on the /admin/coinwallets list page.
     */
    public static function getCoinWalletList(?string $server): array
    {
        $query = Coins::find()
            ->where(['installed' => 1, 'watch' => 1])
            ->orderBy('algo, index_avg DESC');

        if (!empty($server)) {
            $query->andWhere(['rpchost' => $server]);
        }

        return $query->all();
    }

    /**
     * Block counts for the last 100 network blocks per coin, batched into one
     * query instead of 2×N individual queries.
     * Returns ['found' => [coinId => int], 'orphan' => [coinId => int]].
     */
    public static function getBlockCountsByCoin(array $coinIds): array
    {
        if (empty($coinIds)) {
            return ['found' => [], 'orphan' => []];
        }

        $rows = (new \yii\db\Query())
            ->select([
                'b.coin_id',
                "SUM(b.category != 'orphan') AS found",
                "SUM(b.category = 'orphan')  AS orphan",
            ])
            ->from(['b' => 'blocks'])
            ->innerJoin(['c' => 'coins'], 'b.coin_id = c.id')
            ->where(['in', 'b.coin_id', $coinIds])
            ->andWhere('b.height >= c.block_height - 100')
            ->groupBy('b.coin_id')
            ->all();

        $found  = [];
        $orphan = [];
        foreach ($rows as $row) {
            $found[$row['coin_id']]  = (int) $row['found'];
            $orphan[$row['coin_id']] = (int) $row['orphan'];
        }

        return ['found' => $found, 'orphan' => $orphan];
    }

    /**
     * All DB-only data for the /admin/coinwallet detail page.
     * RPC calls (getinfo, listtransactions, getblock) remain in the view.
     *
     * Returned keys:
     *   balance, reserved1, owed, owed_btc, reserved2 (null unless exchange),
     *   markets (Markets[]), bookmarks (Bookmarks[]), symbol (string)
     */
    public static function getCoinWalletDetails(Coins $coin): array
    {
        $reserved1 = (new \yii\db\Query())
            ->select(['SUM(balance)'])
            ->from('accounts')
            ->where(['coinid' => $coin->id])
            ->scalar();

        $owed = (new \yii\db\Query())
            ->select(['SUM(earnings.amount)'])
            ->from('earnings')
            ->leftJoin('blocks', 'earnings.blockid = blocks.id')
            ->where(['coinid' => $coin->id])
            ->andWhere(['!=', 'earnings.status', 2])
            ->scalar();

        $result = [
            'balance'   => Yii::$app->ConversionUtils->altcoinvaluetoa($coin->balance),
            'reserved1' => Yii::$app->ConversionUtils->altcoinvaluetoa($reserved1),
            'owed'      => Yii::$app->ConversionUtils->altcoinvaluetoa($owed),
            'owed_btc'  => Yii::$app->ConversionUtils->bitcoinvaluetoa($owed * $coin->price),
            'reserved2' => null,
            'markets'   => \app\models\Markets::find()
                            ->where(['coinid' => $coin->id, 'deleted' => 0])
                            ->orderBy('disabled, priority DESC, price DESC')
                            ->all(),
            'bookmarks' => \app\models\Bookmarks::find()
                            ->where(['idcoin' => $coin->id])
                            ->orderBy('lastused DESC')
                            ->all(),
            'symbol'    => !empty($coin->symbol2) ? $coin->symbol2 : $coin->symbol,
        ];

        if (defined('YIIMP_ALLOW_EXCHANGE') && YIIMP_ALLOW_EXCHANGE) {
            $subquery = (new \yii\db\Query())
                ->select(['id'])
                ->from('accounts')
                ->where(['coinid' => $coin->id]);
            $tmp = (new \yii\db\Query())
                ->select(['SUM(amount*price)'])
                ->from('earnings')
                ->where(['in', 'id', $subquery])
                ->andWhere(['!=', 'status', 2])
                ->scalar();
            $result['reserved2'] = Yii::$app->ConversionUtils->bitcoinvaluetoa($tmp);
        }

        return $result;
    }

    /**
     * Batch-load which of the given wallet addresses exist in the accounts table.
     * Returns an array_flip'd set for O(1) isset() checks in view loops.
     */
    public static function getKnownAddresses(array $addresses): array
    {
        if (empty($addresses)) {
            return [];
        }
        $known = (new \yii\db\Query())
            ->select(['username'])
            ->from('accounts')
            ->where(['in', 'username', $addresses])
            ->column();
        return array_flip($known);
    }

    // =========================================================================
    // Raw-coin / market discovery (ported from rawcoins.php)
    // =========================================================================

    /**
     * Refresh market listings from all active exchanges: set disabled flags,
     * delete stale market rows, and optionally create new coin records.
     * Per-exchange discovery is delegated to ExchangeDriver::discoverCoins().
     * Ports: updateRawcoins()
     */
    public function updateRawCoins(): void
    {
        Yii::debug(__METHOD__, __CLASS__);

        // Apply default enabled/disabled flags per exchange
        $exchangeDefaults = [
            'binance'  => true,
            'exbitron' => false,
            'nestex'   => false,
            'hitbtc'   => true,
            'kraken'   => true,
            'kucoin'   => true,
            'poloniex' => true,
            'yobit'    => false,
        ];
        $settings = Yii::$app->settings;
        foreach ($exchangeDefaults as $exchange => $disabled) {
            $settings->exchangeSetDefault($exchange, 'disabled', $disabled);
        }
        $settings->prefetchAll();

        // Scan each active exchange for new coin listings
        $exchanges = \app\models\Balances::find()->all();
        foreach ($exchanges as $exchange) {
            $this->updateRawCoinExchange($exchange->name);
        }

        // Sync enabled/disabled state for every exchange that has market records
        $db = Yii::$app->db;
        $marketExchanges = $db->createCommand("SELECT DISTINCT name FROM markets")->queryColumn();

        foreach ($marketExchanges as $exchange) {
            $isDisabled = (bool) Yii::$app->settings->exchangeGet($exchange, 'disabled', false);

            if ($isDisabled) {
                $affected = (int) $db->createCommand(
                    "UPDATE markets SET disabled=8 WHERE name=:ex",
                    [':ex' => $exchange]
                )->execute();

                // Allow individually whitelisted coins on a disabled exchange
                $coins = Coins::find()
                    ->where(['in', 'id',
                        (new \yii\db\Query())->select('coinid')->from('markets')->where(['name' => $exchange])
                    ])
                    ->all();

                foreach ($coins as $coin) {
                    if (Yii::$app->settings->marketGet($exchange, $coin->getOfficialSymbol(), 'disabled', 1) == 0) {
                        $affected -= (int) $db->createCommand(
                            "UPDATE markets SET disabled=0 WHERE name=:ex AND coinid=:cid",
                            [':ex' => $exchange, ':cid' => $coin->id]
                        )->execute();
                    }
                }

                Yii::debug("{$exchange}: {$affected} markets disabled", __CLASS__);
            } else {
                $re = (int) $db->createCommand(
                    "UPDATE markets SET disabled=0 WHERE name=:ex AND disabled=8",
                    [':ex' => $exchange]
                )->execute();
                if ($re) {
                    Yii::debug("{$exchange}: {$re} markets re-enabled", __CLASS__);
                }
            }
        }

        $db->createCommand("DELETE FROM markets WHERE deleted")->execute();
    }

    /**
     * Snapshot MySQL SHOW PROCESSLIST into the connections table and prune stale rows.
     * Ports: BackendProcessList() — blocks.sh every 20s.
     */
    public function processJobQueue(): void
    {
        $db  = Yii::$app->db;
        $now = time();

        $rows = $db->createCommand('SHOW PROCESSLIST')->queryAll();
        foreach ($rows as $item) {
            $conn = Connections::findOne((int) $item['Id']);
            if (!$conn) {
                $conn          = new Connections();
                $conn->id      = (int) $item['Id'];
                $conn->user    = $item['User']  ?? '';
                $conn->host    = $item['Host']  ?? '';
                $conn->db      = $item['db']    ?? '';
                $conn->created = $now;
            }
            $conn->idle = (int) ($item['Time'] ?? 0);
            $conn->last = $now;
            $conn->save();
        }

        $db->createCommand('DELETE FROM connections WHERE last < :cutoff', [
            ':cutoff' => $now - 5 * 60,
        ])->execute();
    }

    /**
     * Fetch the current BTC/USD price from Bitstamp and store it in mining.usdbtc.
     * Ports: bitstamp_btcusd() + cron state-0 save block
     */
    public function updateBtcUsdPrice(): void
    {
        $ch = curl_init('https://www.bitstamp.net/api/v2/ticker/btcusd/');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 30,
        ]);
        $raw    = curl_exec($ch);
        $errno  = curl_errno($ch);
        curl_close($ch);

        if ($errno || !$raw) {
            Yii::warning('Bitstamp BTC/USD fetch failed (curl error ' . $errno . ')', __CLASS__);
            return;
        }

        $ticker = json_decode($raw, true);
        $btcusd = isset($ticker['last']) ? (float) $ticker['last'] : 0.0;

        if ($btcusd <= 0.0) {
            Yii::warning('Bitstamp BTC/USD returned invalid price: ' . $raw, __CLASS__);
            return;
        }

        $mining = Mining::find()->one() ?? new Mining();
        $mining->usdbtc = $btcusd;
        $mining->save();

        Yii::info(sprintf('BTC/USD updated: %.2f', $btcusd), __CLASS__);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function updateRawCoinExchange(string $exchange): void
    {
        Yii::debug("==== Exchange {$exchange} ====", __CLASS__);
        ExchangeFactory::make($exchange)->discoverCoins();
        Yii::debug('==== END Exchange ====', __CLASS__);
    }
}
