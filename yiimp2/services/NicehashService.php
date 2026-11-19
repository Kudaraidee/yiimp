<?php

namespace app\services;

use Yii;
use app\models\Nicehash;
use app\models\Services;
use app\models\Jobs;
use app\models\Renters;

/**
 * NicehashService — manages the pool's purchase of hash power FROM NiceHash.
 *
 * The pool is the BUYER here: it places orders on NiceHash to acquire hash power
 * for its own mining and auto-manages those orders against current profitability.
 * This is entirely separate from RentingService (pool rents OUT hash power).
 *
 * Ported from: web/yaamp/core/backend/services.php → BackendUpdateServices()
 * Updated to NiceHash REST API v2 (api2.nicehash.com/main/api/v2/).
 *
 * Required serverconfig.php constants (v2):
 *   YIIMP_USE_NICEHASH_API  — bool, enables this service
 *   NICEHASH_API_KEY        — string, API key UUID from NiceHash account settings
 *   NICEHASH_API_SECRET     — string, secret key for HMAC-SHA256 signing
 *   NICEHASH_ORG_ID         — string, organization ID UUID
 *   NICEHASH_DEPOSIT        — string, pool worker username / payout address
 *   NICEHASH_DEPOSIT_AMOUNT — float,  BTC per order (e.g. 0.01)
 *   NICEHASH_MARKET         — string, optional — 'EU' | 'EU_N' | 'USA' | 'USA_E' (default: 'EU')
 *   NICEHASH_POOL_HOST      — string, optional — stratum hostname (default: YIIMP_SITE_NAME)
 */
class NicehashService
{
    private const BASE_URL = 'https://api2.nicehash.com';

    /**
     * Pool algo name → NiceHash v2 algorithm string.
     * Only algos still active on NiceHash v2 are kept; legacy-only names are retained
     * for backward-compatible Services table entries but will return no live data.
     */
    private const POOL_TO_V2 = [
        'scrypt'    => 'SCRYPT',
        'sha256'    => 'SHA256',
        'scryptn'   => 'SCRYPTNF',
        'x11'       => 'X11',
        'x13'       => 'X13',
        'keccak'    => 'KECCAK',
        'x15'       => 'X15',
        'nist5'     => 'NIST5',
        'neoscrypt' => 'NEOSCRYPT',
        'lyra2'     => 'LYRA2RE',
        'whirlx'    => 'WHIRLPOOLX',
        'qubit'     => 'QUBIT',
        'quark'     => 'QUARK',
        'lyra2v2'   => 'LYRA2REV2',
        'blakecoin' => 'BLAKE256R8',
        'lbry'      => 'LBRY',
        'equihash'  => 'EQUIHASH',
        'sib'       => 'X11GOST',
        'blake2s'   => 'BLAKE2S',
        'skunk'     => 'SKUNK',
    ];

    /** NiceHash v2 algorithm string → pool algo name (reverse of POOL_TO_V2). */
    private const V2_TO_POOL = [
        'SCRYPT'       => 'scrypt',
        'SHA256'       => 'sha256',
        'SCRYPTNF'     => 'scryptn',
        'X11'          => 'x11',
        'X13'          => 'x13',
        'KECCAK'       => 'keccak',
        'X15'          => 'x15',
        'NIST5'        => 'nist5',
        'NEOSCRYPT'    => 'neoscrypt',
        'LYRA2RE'      => 'lyra2',
        'WHIRLPOOLX'   => 'whirlx',
        'QUBIT'        => 'qubit',
        'QUARK'        => 'quark',
        'LYRA2REV2'    => 'lyra2v2',
        'BLAKE256R8'   => 'blakecoin',
        'LBRY'         => 'lbry',
        'EQUIHASH'     => 'equihash',
        'X11GOST'      => 'sib',
        'BLAKE2S'      => 'blake2s',
        'SKUNK'        => 'skunk',
        // Newer algorithms not in the legacy v1 map but active on v2:
        'DAGGERHASHIMOTO' => 'ethash',
        'KAWPOW'          => 'kawpow',
        'RANDOMXMONERO'   => 'randomx',
        'EQUIHASHZEC'     => 'equihash',
        'ZHASH'           => 'zhash',
        'AUTOLYKOS'       => 'autolykos',
        'ETCHASH'         => 'etchash',
        'VERUSHASH'       => 'verushash',
        'KHEAVYHASH'      => 'kheavyhash',
        'FISHHASH'        => 'fishhash',
        'ALEPHIUM'        => 'alephium',
        'NEXAPOW'         => 'nexapow',
        'EAGLESONG'       => 'eaglesong',
        'BEAMV3'          => 'beamv3',
        'OCTOPUS'         => 'octopus',
    ];

    /** Cached server time offset (ms) from /api/v2/time. */
    private ?int $serverTimeOffsetMs = null;
    /** Cached per-algo metadata (priceDownStep, displayMarketFactor, etc.). */
    private array $algoDetails = [];
    /** Cached pool IDs: v2AlgoString → NiceHash pool UUID. */
    private array $poolIdCache = [];

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Run the full NiceHash sync: price data + order management.
     * Ports: BackendUpdateServices()
     */
    public function syncAll(): void
    {
        if (!defined('YIIMP_USE_NICEHASH_API') || !YIIMP_USE_NICEHASH_API) {
            return;
        }
        $this->syncPrices();
        $this->manageOrders();
    }

    /**
     * Fetch global NiceHash profitability data, update the `services` table,
     * and sync dependent renting job prices.
     */
    public function syncPrices(): void
    {
        // Public endpoint — no auth needed
        $res = $this->request('GET', '/main/api/v2/public/simplemultialgo/info/', [], null, false);
        if (!$res || empty($res['miningAlgorithms'])) {
            Yii::warning('NiceHash: simplemultialgo/info returned no data', __CLASS__);
            return;
        }

        foreach ($res['miningAlgorithms'] as $stat) {
            $v2Algo   = $stat['algorithm'] ?? '';
            $poolAlgo = self::V2_TO_POOL[$v2Algo] ?? null;
            if (!$poolAlgo) {
                continue;
            }

            // `paying` is BTC per speed unit per day (e.g. BTC/TH/day for SHA-256)
            $price = (float) ($stat['paying'] ?? 0);
            $speed = (float) ($stat['speed']  ?? 0);

            if ($price <= 0) {
                continue;
            }

            $service = Services::find()->where(['name' => 'Nicehash', 'algo' => $poolAlgo])->one()
                ?? new Services();
            $service->name  = 'Nicehash';
            $service->algo  = $poolAlgo;
            $service->price = $price;
            $service->speed = $speed;
            $service->save();

            // Adjust NiceHash-pegged renting jobs
            $jobs = Jobs::find()
                ->where(['>', 'percent', 0])
                ->andWhere(['algo' => $poolAlgo])
                ->andWhere(['or',
                    ['host' => 'stratum.westhash.com'],
                    ['host' => 'stratum.nicehash.com'],
                ])
                ->all();

            foreach ($jobs as $job) {
                $job->price = round($price * 1000 * (100 - $job->percent) / 100, 2);
                $job->save();
            }
        }

        // Sync custom-renter stats (still uses their own NiceHash-compatible endpoints)
        $customRenderers = Renters::find()
            ->andWhere(['not', ['custom_address' => null]])
            ->andWhere(['not', ['custom_server'  => null]])
            ->all();

        foreach ($customRenderers as $renter) {
            $data = $this->request('GET',
                "/api?method=stats.provider&addr={$renter->custom_address}",
                [], null, false,
                "https://{$renter->custom_server}"
            );
            if (!$data || empty($data['result']['stats'])) {
                continue;
            }
            $renter->custom_balance = 0;
            $renter->custom_accept  = 0;
            $renter->custom_reject  = 0;
            foreach ($data['result']['stats'] as $stat) {
                $v2Algo = $stat['algo'] ?? null;
                if (!isset(self::V2_TO_POOL[$v2Algo])) {
                    continue;
                }
                $renter->custom_balance += $stat['balance'] ?? 0;
                $renter->custom_accept  += ($stat['accepted_speed'] ?? 0) * 1_000_000_000;
            }
            $renter->save();
        }
    }

    /**
     * Auto-manage the pool's NiceHash orders: create, cancel, adjust price/speed
     * to stay within the target profitability band for each active algo.
     */
    public function manageOrders(): void
    {
        $creds = $this->credentials();
        if (!$creds) {
            return;
        }

        $amount  = defined('NICEHASH_DEPOSIT_AMOUNT') ? (float) NICEHASH_DEPOSIT_AMOUNT : 0.01;
        $market  = defined('NICEHASH_MARKET')         ? NICEHASH_MARKET                 : 'EU';
        $db      = Yii::$app->db;

        // Fetch algo metadata once (for priceDownStep, displayMarketFactor)
        $algoMeta = $this->fetchAlgoDetails();

        // Get current account balance
        $balRes  = $this->request('GET', '/main/api/v2/accounting/account2/BTC');
        $balance = (float) ($balRes['available'] ?? 0);

        foreach (self::POOL_TO_V2 as $poolAlgo => $v2Algo) {
            $record = Nicehash::find()->where(['algo' => $poolAlgo])->one()
                ?? new Nicehash();
            $record->algo = $poolAlgo;

            if (!$record->active) {
                // Cancel any open order and clear cached state
                if ($record->orderid) {
                    $this->request('DELETE', "/main/api/v2/hashpower/order/{$record->orderid}");
                    Yii::info("NiceHash: cancelled order {$record->orderid} for {$poolAlgo}", __CLASS__);
                    $record->orderid = null;
                }
                $record->btc           = null;
                $record->price         = null;
                $record->speed         = null;
                $record->last_decrease = null;
                $record->save();
                continue;
            }

            $meta        = $algoMeta[$v2Algo] ?? null;
            $dispFactor  = $meta['displayMarketFactor'] ?? 'TH';
            $mktFactor   = (string) ($meta['marketFactor']  ?? 1_000_000_000_000);
            $priceStep   = (float) ($meta['priceDownStep'] ?? 0.0001);

            // Current profitability target
            $price       = (float) $db->createCommand(
                "SELECT price FROM hashrate WHERE algo=:algo ORDER BY time DESC LIMIT 1",
                [':algo' => $poolAlgo]
            )->queryScalar();
            $minPrice    = $price * 0.5;
            $setPrice    = $price * 0.7;
            $maxPrice    = $price * 0.9;
            $cancelPrice = $price * 1.1;

            // List active orders for this algo
            $ordersRes = $this->request('GET', '/main/api/v2/hashpower/myOrders', [
                'algorithm' => $v2Algo,
                'active'    => 'true',
                'market'    => $market,
            ]);

            $orders = $ordersRes['list'] ?? [];

            if (empty($orders)) {
                // No open order — create one if we have sufficient balance
                if ($balance >= $amount) {
                    $poolId = $this->getOrCreatePool($v2Algo, $poolAlgo, $market, $creds);
                    if ($poolId) {
                        $newOrder = $this->request('POST', '/main/api/v2/hashpower/order', [], [
                            'type'                => 'STANDARD',
                            'market'              => $market,
                            'algorithm'           => $v2Algo,
                            'amount'              => number_format($amount, 8, '.', ''),
                            'price'               => number_format($setPrice, 8, '.', ''),
                            'limit'               => '0',
                            'poolId'              => $poolId,
                            'marketFactor'        => $mktFactor,
                            'displayMarketFactor' => $dispFactor,
                        ]);
                        if ($newOrder && isset($newOrder['id'])) {
                            $record->orderid      = $newOrder['id'];
                            $record->last_decrease = time();
                            Yii::info("NiceHash: created order {$newOrder['id']} for {$poolAlgo} at {$setPrice}", __CLASS__);
                        }
                    }
                }
                $record->save();
                continue;
            }

            $order = $orders[0];
            $orderId      = $order['id'];
            $orderPrice   = (float) ($order['price'] ?? 0);
            $orderLimit   = (float) ($order['limit'] ?? 0);
            $orderWorkers = (int)   ($order['rigsCount'] ?? 0);
            $orderBtc     = (float) ($order['availableAmount'] ?? 0);
            $orderSpeed   = (float) ($order['acceptedCurrentSpeed'] ?? 0);

            Yii::debug("NiceHash {$poolAlgo}: price={$orderPrice} min={$minPrice} set={$setPrice} max={$maxPrice} cancel={$cancelPrice}", __CLASS__);

            $record->orderid  = $orderId;
            $record->btc      = $orderBtc;
            $record->workers  = $orderWorkers;
            $record->price    = $orderPrice;
            $record->speed    = $orderLimit;
            $record->accepted = $orderSpeed;

            if ($orderPrice > $cancelPrice && $orderWorkers > 0) {
                // Over-priced with active workers — cancel order
                $this->request('DELETE', "/main/api/v2/hashpower/order/{$orderId}");
                Yii::info("NiceHash: cancelled over-priced order {$orderId} for {$poolAlgo}", __CLASS__);
                $record->orderid = null;

            } elseif ($orderPrice > $maxPrice && $orderLimit == 0) {
                // Price too high, speed unlimited — reduce speed first
                $this->updateOrderPriceAndLimit($orderId, $orderPrice, 0.05, $mktFactor, $dispFactor);

            } elseif ($orderPrice > $maxPrice && (int) $record->last_decrease + 10 * 60 < time()) {
                // Price still too high — decrease by one step (v2 requires manual step)
                $newPrice = max($minPrice, $orderPrice - $priceStep);
                $this->updateOrderPriceAndLimit($orderId, $newPrice, $orderLimit, $mktFactor, $dispFactor);
                $record->last_decrease = time();
                Yii::info("NiceHash: decreased price for {$poolAlgo}: {$orderPrice} → {$newPrice}", __CLASS__);

            } elseif ($orderPrice < $minPrice && $orderWorkers <= 0) {
                // Price below minimum with no workers — raise to target
                $this->updateOrderPriceAndLimit($orderId, $setPrice, $orderLimit, $mktFactor, $dispFactor);
                Yii::info("NiceHash: raised price for {$poolAlgo}: {$orderPrice} → {$setPrice}", __CLASS__);

            } elseif ($orderPrice < $maxPrice && $orderLimit == 0.05) {
                // Price back in range — restore unlimited speed
                $this->updateOrderPriceAndLimit($orderId, $orderPrice, 0, $mktFactor, $dispFactor);

            } elseif ($orderBtc < 0.000_750_00) {
                // Low balance — refill
                $this->request('POST', "/main/api/v2/hashpower/order/{$orderId}/refill/", [], [
                    'amount' => number_format(0.01, 8, '.', ''),
                ]);
                Yii::info("NiceHash: refilled order {$orderId} for {$poolAlgo}", __CLASS__);
            }

            $record->save();
        }
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Look up an existing pool matching our stratum config, or create a new one.
     * Results are cached per service instance to avoid duplicate API calls.
     */
    private function getOrCreatePool(string $v2Algo, string $poolAlgo, string $market, array $creds): ?string
    {
        if (isset($this->poolIdCache[$v2Algo])) {
            return $this->poolIdCache[$v2Algo];
        }

        $deposit  = defined('NICEHASH_DEPOSIT')   ? NICEHASH_DEPOSIT   : '';
        $poolHost = defined('NICEHASH_POOL_HOST')  ? NICEHASH_POOL_HOST
            : (defined('YIIMP_SITE_NAME') ? YIIMP_SITE_NAME : 'localhost');
        $poolPort = (int) Yii::$app->YiimpUtils->getAlgoPort($poolAlgo);

        // Check existing pools first
        $poolsRes = $this->request('GET', '/main/api/v2/pools/', ['algorithm' => $v2Algo]);
        $pools    = $poolsRes['list'] ?? [];

        foreach ($pools as $pool) {
            if (($pool['stratumHostname'] ?? '') === $poolHost
                && (int) ($pool['stratumPort'] ?? 0) === $poolPort
                && ($pool['username'] ?? '') === $deposit
            ) {
                $this->poolIdCache[$v2Algo] = $pool['id'];
                return $pool['id'];
            }
        }

        // Create a new pool object
        $newPool = $this->request('POST', '/main/api/v2/pool/', [], [
            'algorithm'        => $v2Algo,
            'name'             => "Yiimp {$poolAlgo}",
            'stratumHostname'  => $poolHost,
            'stratumPort'      => $poolPort,
            'username'         => $deposit,
            'password'         => 'x',
        ]);

        if (!$newPool || empty($newPool['id'])) {
            Yii::warning("NiceHash: failed to create pool for {$v2Algo}", __CLASS__);
            return null;
        }

        Yii::info("NiceHash: created pool {$newPool['id']} for {$v2Algo}", __CLASS__);
        $this->poolIdCache[$v2Algo] = $newPool['id'];
        return $newPool['id'];
    }

    /**
     * Fetch per-algo metadata from NiceHash (priceDownStep, displayMarketFactor, etc.).
     * Results are cached for the lifetime of this service instance.
     */
    private function fetchAlgoDetails(): array
    {
        if (!empty($this->algoDetails)) {
            return $this->algoDetails;
        }

        $res = $this->request('GET', '/main/api/v2/mining/algorithms/', [], null, false);
        if (!$res || empty($res['miningAlgorithms'])) {
            return [];
        }

        foreach ($res['miningAlgorithms'] as $a) {
            $this->algoDetails[$a['algorithm']] = $a;
        }

        return $this->algoDetails;
    }

    /** Convenience wrapper for updatePriceAndLimit endpoint. */
    private function updateOrderPriceAndLimit(
        string $orderId,
        float  $price,
        float  $limit,
        string $marketFactor,
        string $displayMarketFactor
    ): void {
        $this->request('POST', "/main/api/v2/hashpower/order/{$orderId}/updatePriceAndLimit", [], [
            'price'               => number_format($price, 8, '.', ''),
            'limit'               => number_format($limit, 8, '.', ''),
            'marketFactor'        => $marketFactor,
            'displayMarketFactor' => $displayMarketFactor,
        ]);
    }

    /**
     * Get NiceHash server time in milliseconds.
     * Cached per-instance; always uses server time to avoid clock-skew auth failures.
     */
    private function getServerTimeMs(): int
    {
        if ($this->serverTimeOffsetMs === null) {
            $localMs  = (int) (microtime(true) * 1000);
            $res      = $this->request('GET', '/api/v2/time', [], null, false);
            $serverMs = (int) ($res['serverTime'] ?? $localMs);
            $this->serverTimeOffsetMs = $serverMs - $localMs;
        }
        return (int) (microtime(true) * 1000) + $this->serverTimeOffsetMs;
    }

    /**
     * Send an HTTP request to the NiceHash API.
     *
     * Auth builds an HMAC-SHA256 signature per v2 spec:
     *   apiKey \x00 timeMs \x00 nonce \x00 \x00 orgId \x00 \x00 METHOD \x00 path \x00 queryString
     *   (for POST: append \x00 rawJsonBody)
     *
     * @param string      $method      GET | POST | DELETE
     * @param string      $path        Absolute path, e.g. /main/api/v2/hashpower/myOrders
     * @param array       $query       URL query parameters
     * @param array|null  $body        JSON request body (POST only)
     * @param bool        $auth        Whether to attach HMAC auth headers
     * @param string|null $baseOverride Override base URL (for custom-renter servers)
     */
    private function request(
        string  $method,
        string  $path,
        array   $query    = [],
        ?array  $body     = null,
        bool    $auth     = true,
        ?string $baseOverride = null
    ): ?array {
        $queryStr = !empty($query) ? http_build_query($query) : '';
        $base     = $baseOverride ?? self::BASE_URL;
        $url      = $base . $path . ($queryStr !== '' ? '?' . $queryStr : '');
        $headers  = ['Content-Type: application/json', 'Accept: application/json'];

        if ($auth) {
            $creds = $this->credentials();
            if (!$creds) {
                return null;
            }

            $timeMs  = $this->getServerTimeMs();
            $nonce   = implode('-', [uniqid('', true), uniqid('', true)]);
            $reqId   = implode('-', [uniqid('', true), uniqid('', true)]);
            $rawBody = $body !== null ? json_encode($body, JSON_UNESCAPED_UNICODE) : '';

            // Signature input: fields joined by \x00; empty placeholders produce double \x00
            $sigParts = [
                $creds['key'],
                (string) $timeMs,
                $nonce,
                '',              // placeholder
                $creds['orgId'],
                '',              // placeholder
                $method,
                $path,
                $queryStr,
            ];
            if ($body !== null) {
                $sigParts[] = $rawBody; // adds the final \x00{body} for POST
            }
            $sig = hash_hmac('sha256', implode("\x00", $sigParts), $creds['secret']);

            $headers = array_merge($headers, [
                "X-Time: {$timeMs}",
                "X-Nonce: {$nonce}",
                "X-Organization-Id: {$creds['orgId']}",
                "X-Request-Id: {$reqId}",
                "X-Auth: {$creds['key']}:{$sig}",
            ]);
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER     => $headers,
        ]);

        $rawBody = $body !== null ? json_encode($body, JSON_UNESCAPED_UNICODE) : '';
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $rawBody);
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }

        $result     = curl_exec($ch);
        $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr    = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            Yii::warning("NiceHash curl error on {$method} {$path}: {$curlErr}", __CLASS__);
            return null;
        }
        if ($httpStatus === 429) {
            Yii::warning("NiceHash rate-limited on {$method} {$path}", __CLASS__);
            sleep(2);
            return null;
        }
        if ($httpStatus >= 400) {
            Yii::warning("NiceHash HTTP {$httpStatus} on {$method} {$path}: {$result}", __CLASS__);
            return null;
        }

        $decoded = json_decode((string) $result, true);
        return is_array($decoded) ? $decoded : null;
    }

    /** Return v2 credentials array, or null if any required constant is missing. */
    private function credentials(): ?array
    {
        if (!defined('NICEHASH_API_KEY') || !defined('NICEHASH_API_SECRET') || !defined('NICEHASH_ORG_ID')) {
            Yii::warning('NiceHash: NICEHASH_API_KEY, NICEHASH_API_SECRET, or NICEHASH_ORG_ID not defined', __CLASS__);
            return null;
        }
        return [
            'key'    => NICEHASH_API_KEY,
            'secret' => NICEHASH_API_SECRET,
            'orgId'  => NICEHASH_ORG_ID,
        ];
    }
}
