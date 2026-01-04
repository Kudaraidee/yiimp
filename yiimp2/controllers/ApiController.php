<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\web\Response;
use yii\helpers\Json;

use app\models\Coins;
use app\models\Accounts;
use app\models\Workers;
use app\models\Blocks;
use app\models\Algos;
use app\models\Payouts;

/**
 * API Controller
 * Provides RESTful API endpoints for pool statistics and user data
 */
class ApiController extends Controller
{
    public $enableCsrfValidation = false;

    /**
     * @inheritdoc
     */
    public function beforeAction($action)
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        // Set JSON response format
        Yii::$app->response->format = Response::FORMAT_JSON;

        return true;
    }

    /**
     * API endpoint for pool status
     * Returns hashrate, active miners, and algorithm statistics in JSON
     * 
     * @return array Pool status data
     */
    public function actionStatus()
    {
        try {
            // Check for server overload
            if (is_file(Yii::getAlias('@app') . '/../log/overloaded')) {
                return $this->errorResponse('Server overloaded', 503);
            }

            // Check cache first (30 second cache)
            $cache_key = 'api_status';
            $cached = Yii::$app->cache->get($cache_key);
            if ($cached !== false) {
                return $cached;
            }

            $stats = [];
            $algos = Yii::$app->YiimpUtils->get_algos(true); // Only visible algos

            foreach ($algos as $algo) {
                // Get coin count for this algorithm (with 5 second cache)
                $coin_cache_key = "api_status_coins-{$algo}";
                $coins = Yii::$app->cache->getOrSet($coin_cache_key, function() use ($algo) {
                    return Coins::find()
                        ->where([
                            'enable' => 1,
                            'visible' => 1,
                            'auto_ready' => 1,
                            'algo' => $algo
                        ])
                        ->count();
                }, 5);

                if (!$coins) {
                    continue;
                }

                // Get worker counts (with 5 second cache)
                $workers_cache_key = "api_status_workers-{$algo}";
                $workers = Yii::$app->cache->getOrSet($workers_cache_key, function() use ($algo) {
                    return Workers::find()
                        ->where(['algo' => $algo])
                        ->count();
                }, 5);

                $workers_shared_cache_key = "api_status_workers_shared-{$algo}";
                $workers_shared = Yii::$app->cache->getOrSet($workers_shared_cache_key, function() use ($algo) {
                    return Workers::find()
                        ->where(['algo' => $algo])
                        ->andWhere(['not like', 'password', '%m=solo%', false])
                        ->count();
                }, 5);

                $workers_solo_cache_key = "api_status_workers_solo-{$algo}";
                $workers_solo = Yii::$app->cache->getOrSet($workers_solo_cache_key, function() use ($algo) {
                    return Workers::find()
                        ->where(['algo' => $algo])
                        ->andWhere(['like', 'password', '%m=solo%', false])
                        ->count();
                }, 5);

                // Get hashrates
                $pool_hash = Yii::$app->YiimpUtils->pool_rate($algo) ?: 0;
                $pool_shared_hash = Yii::$app->YiimpUtils->pool_shared_rate($algo) ?: 0;
                $pool_solo_hash = Yii::$app->YiimpUtils->pool_solo_rate($algo) ?: 0;

                // Get current price estimate (with 5 second cache)
                $price_cache_key = "api_status_price-{$algo}";
                $price = Yii::$app->cache->getOrSet($price_cache_key, function() use ($algo) {
                    return (new \yii\db\Query())
                        ->select(['price'])
                        ->from('hashrate')
                        ->where(['algo' => $algo])
                        ->orderBy(['time' => SORT_DESC])
                        ->limit(1)
                        ->scalar();
                }, 5);

                $price = Yii::$app->ConversionUtils->bitcoinvaluetoa(
                    Yii::$app->YiimpUtils->take_yiimp_fee($price / 1000, $algo)
                );

                // Get rental price if enabled (with 5 second cache)
                $rental = null;
                if (defined('YAAMP_RENTAL') && YAAMP_RENTAL) {
                    $rental_cache_key = "api_status_rental-{$algo}";
                    $rental_price = Yii::$app->cache->getOrSet($rental_cache_key, function() use ($algo) {
                        return (new \yii\db\Query())
                            ->select(['rent'])
                            ->from('hashrate')
                            ->where(['algo' => $algo])
                            ->orderBy(['time' => SORT_DESC])
                            ->limit(1)
                            ->scalar();
                    }, 5);
                    $rental = Yii::$app->ConversionUtils->bitcoinvaluetoa($rental_price);
                }

                // Get 24h average price (with 5 second cache)
                $t = time() - 24 * 60 * 60;
                $avgprice_cache_key = "api_status_avgprice-{$algo}";
                $avgprice = Yii::$app->cache->getOrSet($avgprice_cache_key, function() use ($algo, $t) {
                    return (new \yii\db\Query())
                        ->select(['avg(price)'])
                        ->from('hashrate')
                        ->where(['algo' => $algo])
                        ->andWhere(['>', 'time', $t])
                        ->scalar();
                }, 5);

                $avgprice = Yii::$app->ConversionUtils->bitcoinvaluetoa(
                    Yii::$app->YiimpUtils->take_yiimp_fee($avgprice / 1000, $algo)
                );

                // Get 24h actual earnings (with 5 second cache)
                $total_cache_key = "api_status_total-{$algo}";
                $total1 = Yii::$app->cache->getOrSet($total_cache_key, function() use ($algo, $t) {
                    return (new \yii\db\Query())
                        ->select(['sum(amount*price)'])
                        ->from('blocks')
                        ->where(['algo' => $algo])
                        ->andWhere(['!=', 'category', 'orphan'])
                        ->andWhere(['>', 'time', $t])
                        ->scalar();
                }, 5);

                $hashrate_cache_key = "api_status_avghashrate-{$algo}";
                $hashrate1 = Yii::$app->cache->getOrSet($hashrate_cache_key, function() use ($algo, $t) {
                    return (new \yii\db\Query())
                        ->select(['avg(hashrate)'])
                        ->from('hashrate')
                        ->where(['algo' => $algo])
                        ->andWhere(['>', 'time', $t])
                        ->scalar();
                }, 5);

                $algo_unit_factor = Yii::$app->YiimpUtils->algo_mBTC_factor($algo);
                $btcmhday1 = $hashrate1 > 0 ? 
                    Yii::$app->ConversionUtils->mbitcoinvaluetoa($total1 / $hashrate1 * 1000000 * 1000 * $algo_unit_factor) : 
                    0;

                // Get fees and port
                $fees = Yii::$app->YiimpUtils->yiimp_fee($algo);
                $fees_solo = Yii::$app->YiimpUtils->yiimp_fee_solo($algo);
                $port = Yii::$app->YiimpUtils->getAlgoPort($algo);

                $stat = [
                    'name' => $algo,
                    'port' => (int) $port,
                    'coins' => (int) $coins,
                    'fees' => (double) $fees,
                    'fees_solo' => (double) $fees_solo,
                    'hashrate' => (int) $pool_hash,
                    'hashrate_shared' => (int) $pool_shared_hash,
                    'workers' => (int) $workers,
                    'workers_shared' => (int) $workers_shared,
                    'workers_solo' => (int) $workers_solo,
                    'estimate_current' => $price,
                    'estimate_last24h' => $avgprice,
                    'actual_last24h' => $btcmhday1,
                    'mbtc_mh_factor' => $algo_unit_factor,
                    'hashrate_last24h' => (double) $hashrate1
                ];

                // Add rental price if enabled
                if ($rental !== null) {
                    $stat['rental_current'] = $rental;
                }

                $stats[$algo] = $stat;
            }

            ksort($stats);

            // Cache the full response for 30 seconds
            Yii::$app->cache->set($cache_key, $stats, 30);

            return $stats;

        } catch (\Exception $e) {
            Yii::error('API Status Error: ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString(), __METHOD__);
            return $this->errorResponse('Internal server error', 500);
        }
    }

    /**
     * API endpoint for wallet statistics
     * Returns balance, hashrate, workers, and earnings for specified wallet
     * 
     * @param string $address Wallet address
     * @return array Wallet statistics
     */
    public function actionWallet($address = null)
    {
        try {
            // Check for server overload
            if (is_file(Yii::getAlias('@app') . '/../log/overloaded')) {
                return $this->errorResponse('Server overloaded', 503);
            }

            // Get address from parameter or query string
            if ($address === null) {
                $address = Yii::$app->request->get('address');
            }

            if (empty($address)) {
                return $this->errorResponse('Missing required parameter: address', 400);
            }

            // Validate and get user
            $user = Yii::$app->YiimpUtils->getuserbyaddress($address);
            if (!$user) {
                return $this->errorResponse('Wallet not found', 404);
            }
            if ($user->is_locked) {
                return $this->errorResponse('Wallet is locked', 404);
            }

            // Get coin information
            $coin = Coins::findOne($user->coinid);
            if (!$coin) {
                return $this->errorResponse('Coin not found', 404);
            }

            // Calculate unsold earnings (cached)
            $unsold_cache_key = "api_wallet_unsold-{$user->id}";
            $total_unsold = Yii::$app->cache->getOrSet($unsold_cache_key, function() use ($user) {
                return Yii::$app->YiimpUtils->convert_earnings_user($user, 0); // status != 2
            }, 60);

            // Get 24h paid amount (cached)
            $t = time() - 24 * 60 * 60;
            $paid_cache_key = "api_wallet_paid-{$user->id}";
            $total_paid_raw = Yii::$app->cache->getOrSet($paid_cache_key, function() use ($user, $t) {
                return (new \yii\db\Query())
                    ->select(['sum(amount)'])
                    ->from('payouts')
                    ->where(['account_id' => $user->id])
                    ->andWhere(['>=', 'time', $t])
                    ->scalar();
            }, 60);
            $total_paid = Yii::$app->ConversionUtils->bitcoinvaluetoa($total_paid_raw ?: 0);

            // Calculate balances
            $balance = Yii::$app->ConversionUtils->bitcoinvaluetoa($user->balance);
            $total_unpaid = Yii::$app->ConversionUtils->bitcoinvaluetoa($user->balance + $total_unsold);
            $total_earned = Yii::$app->ConversionUtils->bitcoinvaluetoa($total_unpaid + $total_paid);

            // Get hashrate and worker count (cached)
            $hashrate_cache_key = "api_wallet_hashrate-{$user->id}";
            $hashrate_data = Yii::$app->cache->getOrSet($hashrate_cache_key, function() use ($user) {
                $hashrate = 0;
                $workers = Workers::find()->where(['userid' => $user->id])->all();
                
                // Calculate total hashrate across all algorithms
                $algos_processed = [];
                foreach ($workers as $worker) {
                    // Only calculate once per algorithm to avoid double counting
                    if (!in_array($worker->algo, $algos_processed)) {
                        $hashrate += Yii::$app->YiimpUtils->user_rate($user->id, $worker->algo);
                        $algos_processed[] = $worker->algo;
                    }
                }
                
                return [
                    'hashrate' => $hashrate,
                    'worker_count' => count($workers)
                ];
            }, 30);

            return [
                'currency' => $coin->symbol,
                'unsold' => (double) $total_unsold,
                'balance' => (double) $balance,
                'unpaid' => (double) $total_unpaid,
                'paid24h' => (double) $total_paid,
                'total' => (double) $total_earned,
                'hashrate' => (double) $hashrate_data['hashrate'],
                'workers' => (int) $hashrate_data['worker_count']
            ];

        } catch (\Exception $e) {
            Yii::error('API Wallet Error: ' . $e->getMessage() . ' | Address: ' . ($address ?? 'null') . ' | Trace: ' . $e->getTraceAsString(), __METHOD__);
            return $this->errorResponse('Internal server error', 500);
        }
    }

    /**
     * API endpoint for extended wallet statistics
     * Returns balance, hashrate, workers, earnings, and detailed worker information
     * Optionally includes payout history if YIIMP_API_PAYOUTS is enabled
     * 
     * @param string $address Wallet address
     * @return array Extended wallet statistics
     */
    public function actionWalletEx($address = null)
    {
        try {
            // Get address from parameter or query string
            if ($address === null) {
                $address = Yii::$app->request->get('address');
            }

            if (empty($address)) {
                return $this->errorResponse('Missing required parameter: address', 400);
            }

            // Validate and get user
            $user = Yii::$app->YiimpUtils->getuserbyaddress($address);
            if (!$user) {
                return $this->errorResponse('Wallet not found', 404);
            }
            if ($user->is_locked) {
                return $this->errorResponse('Wallet is locked', 404);
            }

            // Get coin information
            $coin = Coins::findOne($user->coinid);
            if (!$coin) {
                return $this->errorResponse('Coin not found', 404);
            }

            // Calculate unsold earnings (cached)
            $unsold_cache_key = "api_walletex_unsold-{$user->id}";
            $total_unsold = Yii::$app->cache->getOrSet($unsold_cache_key, function() use ($user) {
                return Yii::$app->YiimpUtils->convert_earnings_user($user, 0); // status != 2
            }, 60);

            // Get 24h paid amount (cached)
            $t = time() - 24 * 60 * 60;
            $paid_cache_key = "api_walletex_paid-{$user->id}";
            $total_paid_raw = Yii::$app->cache->getOrSet($paid_cache_key, function() use ($user, $t) {
                return (new \yii\db\Query())
                    ->select(['sum(amount)'])
                    ->from('payouts')
                    ->where(['account_id' => $user->id])
                    ->andWhere(['>=', 'time', $t])
                    ->scalar();
            }, 60);
            $total_paid = Yii::$app->ConversionUtils->bitcoinvaluetoa($total_paid_raw ?: 0);

            // Calculate balances
            $balance = Yii::$app->ConversionUtils->bitcoinvaluetoa($user->balance);
            $total_unpaid = Yii::$app->ConversionUtils->bitcoinvaluetoa($user->balance + $total_unsold);
            $total_earned = Yii::$app->ConversionUtils->bitcoinvaluetoa($total_unpaid + $total_paid);

            // Build response with basic wallet fields
            $response = [
                'currency' => $coin->symbol,
                'unsold' => (double) $total_unsold,
                'balance' => (double) $balance,
                'unpaid' => (double) $total_unpaid,
                'paid24h' => (double) $total_paid,
                'total' => (double) $total_earned,
            ];

            // Get all workers for the user and build detailed worker information (cached)
            $workers_cache_key = "api_walletex_workers-{$user->id}";
            $miners = Yii::$app->cache->getOrSet($workers_cache_key, function() use ($user) {
                $workers = Workers::find()
                    ->where(['userid' => $user->id])
                    ->orderBy(['password' => SORT_ASC])
                    ->all();
                $miners = [];

                foreach ($workers as $worker) {
                    // Calculate accepted and rejected hashrates for this worker
                    $accepted = Yii::$app->YiimpUtils->worker_rate($worker->id, $worker->algo);
                    $rejected = Yii::$app->YiimpUtils->worker_rate_bad($worker->id, $worker->algo);

                    $miners[] = [
                        'version' => $worker->version ?: '',
                        'password' => $worker->password ?: '',
                        'ID' => $worker->worker ?: '',
                        'algo' => $worker->algo,
                        'difficulty' => (double) $worker->difficulty,
                        'subscribe' => (int) $worker->subscribe,
                        'accepted' => round($accepted, 3),
                        'rejected' => round($rejected, 3)
                    ];
                }

                return $miners;
            }, 30);

            $response['miners'] = $miners;

            // Add conditional payout history if enabled
            if (defined('YIIMP_API_PAYOUTS') && YIIMP_API_PAYOUTS) {
                $payout_period = defined('YIIMP_API_PAYOUTS_PERIOD') ? YIIMP_API_PAYOUTS_PERIOD : 86400; // Default 24 hours
                $payout_time = time() - $payout_period;

                // Check cache first
                $cache_key = 'api_walletex_payouts_' . $user->id;
                $payouts_data = Yii::$app->cache->get($cache_key);

                if ($payouts_data === false) {
                    // Query payouts table for recent payouts
                    $payouts = Payouts::find()
                        ->where(['account_id' => $user->id])
                        ->andWhere(['>=', 'time', $payout_time])
                        ->orderBy(['time' => SORT_DESC])
                        ->all();

                    $payouts_data = [];
                    foreach ($payouts as $payout) {
                        $payouts_data[] = [
                            'time' => (int) $payout->time,
                            'amount' => Yii::$app->ConversionUtils->bitcoinvaluetoa($payout->amount),
                            'tx' => $payout->tx ?: ''
                        ];
                    }

                    // Cache payout data for 5 minutes
                    Yii::$app->cache->set($cache_key, $payouts_data, 300);
                }

                $response['payouts'] = $payouts_data;
            }

            return $response;

        } catch (\Exception $e) {
            Yii::error('API WalletEx Error: ' . $e->getMessage() . ' | Address: ' . ($address ?? 'null') . ' | Trace: ' . $e->getTraceAsString(), __METHOD__);
            return $this->errorResponse('Internal server error', 500);
        }
    }

    /**
     * API endpoint for currency information
     * Returns exchange rates and profitability data
     * 
     * @return array Currency information
     */
    public function actionCurrency()
    {
        try {
            // Check for server overload
            if (is_file(Yii::getAlias('@app') . '/../log/overloaded')) {
                return $this->errorResponse('Server overloaded', 503);
            }

            // Check cache first (15 second cache)
            $cache_key = 'api_currencies';
            $cached = Yii::$app->cache->get($cache_key);
            if ($cached !== false) {
                return $cached;
            }

            $data = [];
            $coins = Coins::find()
                ->where([
                    'enable' => 1,
                    'visible' => 1,
                    'auto_ready' => 1
                ])
                ->andWhere(['!=', 'algo', 'PoS'])
                ->orderBy(['symbol' => SORT_ASC])
                ->all();

            foreach ($coins as $coin) {
                $symbol = $coin->symbol;

                // Get last block info
                $last = (new \yii\db\Query())
                    ->select(['height', 'time'])
                    ->from('blocks')
                    ->where(['coin_id' => $coin->id])
                    ->andWhere(['in', 'category', ['immature', 'generate']])
                    ->orderBy(['height' => SORT_DESC])
                    ->limit(1)
                    ->one();

                $lastblock = $last ? (int) $last['height'] : 0;
                $timesincelast = $last && $last['time'] > 0 ? time() - $last['time'] : 0;

                // Get miner and worker counts
                $miners = Accounts::find()
                    ->where(['coinid' => $coin->id])
                    ->andWhere(['in', 'id', 
                        (new \yii\db\Query())->select(new \yii\db\Expression('DISTINCT userid'))->from('workers')
                    ])
                    ->count();

                $workers = Workers::find()
                    ->innerJoin('accounts', 'accounts.id = workers.userid')
                    ->where(['workers.algo' => $coin->algo])
                    ->andWhere(['in', 'accounts.coinid', [$coin->id, 6]]) // 6 is BTC
                    ->count();

                // Get hashrate
                $pool_hash = Yii::$app->YiimpUtils->coin_rate($coin->id) ?: 0;

                // Get 24h blocks (with cache)
                $t24 = time() - 24 * 60 * 60;
                $blocks_cache_key = "history_item2-{$coin->id}-{$coin->algo}";
                $res24h = Yii::$app->cache->getOrSet($blocks_cache_key, function() use ($coin, $t24) {
                    return (new \yii\db\Query())
                        ->select(['COUNT(id) as a', 'SUM(amount*price) as b'])
                        ->from('blocks')
                        ->where(['coin_id' => $coin->id])
                        ->andWhere(['not in', 'category', ['orphan', 'stake', 'generated']])
                        ->andWhere(['>', 'time', $t24])
                        ->one();
                }, 60);

                // Calculate profitability
                $btcmhd = Yii::$app->YiimpUtils->yiimp_profitability($coin);
                $btcmhd = Yii::$app->ConversionUtils->mbitcoinvaluetoa($btcmhd);

                // Calculate network hashrate
                $min_ttf = $coin->network_ttf > 0 ? min($coin->actual_ttf, $coin->network_ttf) : $coin->actual_ttf;
                $network_hash = $coin->difficulty * 0x100000000 / ($min_ttf ?: 60);

                // Get fees and port
                $fees = Yii::$app->YiimpUtils->yiimp_fee($coin->algo);
                $fees_solo = Yii::$app->YiimpUtils->yiimp_fee_solo($coin->algo);
                $port = Yii::$app->YiimpUtils->getAlgoPort($coin->algo);

                $data[$symbol] = [
                    'name' => $coin->name,
                    'algo' => $coin->algo,
                    'port' => (int) $port,
                    'reward' => (double) $coin->reward,
                    'blocktime' => (int) $coin->block_time,
                    'height' => (int) $coin->block_height,
                    'difficulty' => (double) $coin->difficulty,
                    'autotrade' => (bool) $coin->auto_exchange,
                    'fees' => (double) $fees,
                    'fees_solo' => (double) $fees_solo,
                    'miners' => (int) $miners,
                    'workers' => (int) $workers,
                    'hashrate' => (double) $pool_hash,
                    'network_hashrate' => (double) $network_hash,
                    'estimate' => $btcmhd,
                    '24h_blocks' => (int) ($res24h['a'] ?? 0),
                    '24h_btc' => round($res24h['b'] ?? 0, 8),
                    'lastblock' => $lastblock,
                    'timesincelast' => $timesincelast
                ];
            }

            // Cache the full response for 15 seconds
            Yii::$app->cache->set($cache_key, $data, 15);

            return $data;

        } catch (\Exception $e) {
            Yii::error('API Currency Error: ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString(), __METHOD__);
            return $this->errorResponse('Internal server error', 500);
        }
    }

    /**
     * API endpoint for block information
     * Returns recent blocks found by the pool
     * 
     * @return array Block information
     */
    public function actionBlocks()
    {
        try {
            // Check for server overload
            if (is_file(Yii::getAlias('@app') . '/../log/overloaded')) {
                return $this->errorResponse('Server overloaded', 503);
            }

            $limit = Yii::$app->request->get('limit', 50);
            $limit = min(max((int) $limit, 1), 100); // Limit between 1 and 100

            $blocks = Blocks::find()
                ->with(['coin', 'account'])
                ->where(['category' => ['generate', 'immature']])
                ->orderBy(['time' => SORT_DESC])
                ->limit($limit)
                ->all();

            $data = [];
            foreach ($blocks as $block) {
                $data[] = [
                    'height' => (int) $block->height,
                    'blockhash' => $block->hash,
                    'coin' => $block->coin ? $block->coin->symbol : 'Unknown',
                    'algo' => $block->algo,
                    'amount' => (double) $block->amount,
                    'difficulty' => (double) $block->difficulty,
                    'time' => (int) $block->time,
                    'timestamp' => date('Y-m-d H:i:s', $block->time),
                    'confirmations' => (int) $block->confirmations,
                    'txhash' => $block->txhash,
                    'reward' => Yii::$app->ConversionUtils->bitcoinvaluetoa($block->amount)
                ];
            }

            return $data;

        } catch (\Exception $e) {
            $limit = Yii::$app->request->get('limit', 50);
            Yii::error('API Blocks Error: ' . $e->getMessage() . ' | Limit: ' . $limit . ' | Trace: ' . $e->getTraceAsString(), __METHOD__);
            return $this->errorResponse('Internal server error', 500);
        }
    }

    /**
     * Helper method to return error responses
     * Ensures consistent error format across all API endpoints
     * 
     * @param string $message Error message
     * @param int $code HTTP status code (400, 404, 429, 500, 503)
     * @return array Error response with error flag, message, and code
     */
    protected function errorResponse($message, $code = 400)
    {
        // Validate that code is an appropriate HTTP error code
        $validCodes = [400, 401, 403, 404, 429, 500, 503];
        if (!in_array($code, $validCodes)) {
            // Default to 500 for invalid codes
            $code = 500;
            $message = 'Internal server error';
        }
        
        Yii::$app->response->statusCode = $code;
        return [
            'error' => true,
            'message' => $message,
            'code' => $code
        ];
    }
}
