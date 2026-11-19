<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\web\Response;
use app\models\Accounts;
use app\models\Coins;
use app\models\Workers;
use app\models\Payouts;
use app\models\Renters;
use app\models\Jobs;

/**
 * Public REST API — mirrors the Yii1 ApiController endpoints.
 *
 * Routes (all return application/json):
 *   GET /api/status
 *   GET /api/currencies
 *   GET /api/wallet?address=
 *   GET /api/walletEx?address=
 *   GET /api/rental?key=          (requires YIIMP_RENTAL)
 *   GET /api/rental_price?...
 *   GET /api/rental_hashrate?...
 *   GET /api/rental_start?...
 *   GET /api/rental_stop?...
 */
class ApiController extends Controller
{
    public $enableCsrfValidation = false;

    // -------------------------------------------------------------------------

    public function actionStatus(): Response
    {
        if ($this->isOverloaded()) {
            return $this->serviceUnavailable();
        }

        $cache    = Yii::$app->cache;
        $cacheKey = 'api_status';
        $cached   = $cache->get($cacheKey);
        if ($cached !== false) {
            return $this->jsonRaw($cached);
        }

        $db   = Yii::$app->db;
        $util = Yii::$app->YiimpUtils;
        $conv = Yii::$app->ConversionUtils;
        $t24  = time() - 86400;

        $stats = [];
        foreach ($util->get_algos() as $algo) {
            $coins = (int) $db->createCommand(
                "SELECT COUNT(id) FROM coins WHERE enable=1 AND visible=1 AND auto_ready=1 AND algo=:algo",
                [':algo' => $algo]
            )->queryScalar();

            if (!$coins) continue;

            $workers       = (int) $db->createCommand("SELECT COUNT(id) FROM workers WHERE algo=:algo", [':algo' => $algo])->queryScalar();
            $workersShared = (int) $db->createCommand("SELECT COUNT(id) FROM workers WHERE algo=:algo AND password NOT LIKE '%m=solo%'", [':algo' => $algo])->queryScalar();
            $workersSolo   = (int) $db->createCommand("SELECT COUNT(id) FROM workers WHERE algo=:algo AND password LIKE '%m=solo%'", [':algo' => $algo])->queryScalar();

            $poolHash       = (int) ($util->pool_rate($algo) ?? 0);
            $poolSharedHash = (int) ($this->poolSharedRate($algo, $util) ?? 0);
            $poolSoloHash   = (int) ($this->poolSoloRate($algo, $util) ?? 0);

            $price = (float) $db->createCommand(
                "SELECT price FROM hashrate WHERE algo=:algo ORDER BY time DESC LIMIT 1",
                [':algo' => $algo]
            )->queryScalar();
            $price = $conv->bitcoinvaluetoa($util->take_yiimp_fee($price / 1000, $algo));

            $rental = $conv->bitcoinvaluetoa(
                (float) $db->createCommand(
                    "SELECT rent FROM hashrate WHERE algo=:algo ORDER BY time DESC LIMIT 1",
                    [':algo' => $algo]
                )->queryScalar()
            );

            $avgPrice = (float) $db->createCommand(
                "SELECT AVG(price) FROM hashrate WHERE algo=:algo AND time>:t",
                [':algo' => $algo, ':t' => $t24]
            )->queryScalar();
            $avgPrice = $conv->bitcoinvaluetoa($util->take_yiimp_fee($avgPrice / 1000, $algo));

            $total24h   = (float) $db->createCommand(
                "SELECT SUM(amount*price) FROM blocks WHERE category!='orphan' AND time>:t AND algo=:algo",
                [':t' => $t24, ':algo' => $algo]
            )->queryScalar();
            $hashrate24h = (float) $db->createCommand(
                "SELECT AVG(hashrate) FROM hashrate WHERE time>:t AND algo=:algo",
                [':t' => $t24, ':algo' => $algo]
            )->queryScalar();

            $algoFactor = $util->algo_mBTC_factor($algo);
            $btcMhDay   = $hashrate24h > 0
                ? $conv->mbitcoinvaluetoa($total24h / $hashrate24h * 1e6 * 1000 * $algoFactor)
                : 0;

            $stat = [
                'name'             => $algo,
                'port'             => (int) $util->getAlgoPort($algo),
                'coins'            => $coins,
                'fees'             => (float) $util->yiimp_fee($algo),
                'fees_solo'        => (float) $util->yiimp_fee_solo($algo),
                'hashrate'         => $poolHash,
                'hashrate_shared'  => $poolSharedHash,
                'hashrate_solo'    => $poolSoloHash,
                'workers'          => $workers,
                'workers_shared'   => $workersShared,
                'workers_solo'     => $workersSolo,
                'estimate_current' => $price,
                'estimate_last24h' => $avgPrice,
                'actual_last24h'   => $btcMhDay,
                'mbtc_mh_factor'   => $algoFactor,
                'hashrate_last24h' => $hashrate24h,
            ];
            if (defined('YIIMP_RENTAL') && YIIMP_RENTAL) {
                $stat['rental_current'] = $rental;
            }

            $stats[$algo] = $stat;
        }

        ksort($stats);
        $json = json_encode($stats);
        $cache->set($cacheKey, $json, 30);
        return $this->jsonRaw($json);
    }

    // -------------------------------------------------------------------------

    public function actionCurrencies(): Response
    {
        if ($this->isOverloaded()) {
            return $this->serviceUnavailable();
        }

        $cache    = Yii::$app->cache;
        $cacheKey = 'api_currencies';
        $cached   = $cache->get($cacheKey);
        if ($cached !== false) {
            return $this->jsonRaw($cached);
        }

        $db   = Yii::$app->db;
        $util = Yii::$app->YiimpUtils;
        $conv = Yii::$app->ConversionUtils;
        $t24  = time() - 86400;

        $coins = Coins::find()
            ->where(['enable' => 1, 'visible' => 1, 'auto_ready' => 1])
            ->andWhere(['not', ['algo' => 'PoS']])
            ->andWhere(['not', ['algo' => null]])
            ->orderBy('symbol')
            ->all();

        $data = [];
        foreach ($coins as $coin) {
            $last       = $db->createCommand(
                "SELECT height, time FROM blocks WHERE coin_id=:id AND category IN ('immature','generate') ORDER BY height DESC LIMIT 1",
                [':id' => $coin->id]
            )->queryOne();
            $lastShared = $db->createCommand(
                "SELECT height, time FROM blocks WHERE coin_id=:id AND solo=0 AND category IN ('immature','generate') ORDER BY height DESC LIMIT 1",
                [':id' => $coin->id]
            )->queryOne();
            $lastSolo   = $db->createCommand(
                "SELECT height, time FROM blocks WHERE coin_id=:id AND solo=1 AND category IN ('immature','generate') ORDER BY height DESC LIMIT 1",
                [':id' => $coin->id]
            )->queryOne();

            $lastBlock       = (int) ($last['height']       ?? 0);
            $lastBlockShared = (int) ($lastShared['height'] ?? 0);
            $lastBlockSolo   = (int) ($lastSolo['height']   ?? 0);

            $timeLast       = (int) ($last['time']       ?? 0);
            $timeLastShared = (int) ($lastShared['time'] ?? 0);
            $timeLastSolo   = (int) ($lastSolo['time']   ?? 0);

            $timeSinceLast       = $timeLast       ? time() - $timeLast       : 0;
            $timeSinceLastShared = $timeLastShared ? time() - $timeLastShared : 0;
            $timeSinceLastSolo   = $timeLastSolo   ? time() - $timeLastSolo   : 0;

            $miners = (int) $db->createCommand(
                "SELECT COUNT(DISTINCT userid) FROM accounts WHERE coinid=:cid AND id IN (SELECT DISTINCT userid FROM workers)",
                [':cid' => $coin->id]
            )->queryScalar();

            $workers       = (int) $db->createCommand(
                "SELECT COUNT(W.userid) FROM workers W INNER JOIN accounts A ON A.id=W.userid WHERE W.algo=:algo AND A.coinid IN (:id, 6)",
                [':algo' => $coin->algo, ':id' => $coin->id]
            )->queryScalar();
            $workersShared = (int) $db->createCommand(
                "SELECT COUNT(W.userid) FROM workers W INNER JOIN accounts A ON A.id=W.userid WHERE W.algo=:algo AND A.coinid IN (:id, 6) AND W.password NOT LIKE '%m=solo%'",
                [':algo' => $coin->algo, ':id' => $coin->id]
            )->queryScalar();
            $workersSolo   = (int) $db->createCommand(
                "SELECT COUNT(W.userid) FROM workers W INNER JOIN accounts A ON A.id=W.userid WHERE W.algo=:algo AND A.coinid IN (:id, 6) AND W.password LIKE '%m=solo%'",
                [':algo' => $coin->algo, ':id' => $coin->id]
            )->queryScalar();

            $since  = $timeLast ?: time() - 3600;
            $shares = $db->createCommand(
                "SELECT COUNT(id) AS shares, SUM(difficulty) AS coin_hr FROM shares WHERE time>:since AND algo=:algo AND coinid IN (0, :id)",
                [':since' => $since, ':algo' => $coin->algo, ':id' => $coin->id]
            )->queryOne();

            $res24h       = $db->createCommand(
                "SELECT COUNT(id) AS a, SUM(amount*price) AS b FROM blocks WHERE coin_id=:id AND NOT category IN ('orphan','stake','generated') AND time>:t AND algo=:algo",
                [':id' => $coin->id, ':t' => $t24, ':algo' => $coin->algo]
            )->queryOne();
            $res24hShared = $db->createCommand(
                "SELECT COUNT(id) AS a, SUM(amount*price) AS b FROM blocks WHERE coin_id=:id AND solo=0 AND NOT category IN ('orphan','stake','generated') AND time>:t AND algo=:algo",
                [':id' => $coin->id, ':t' => $t24, ':algo' => $coin->algo]
            )->queryOne();
            $res24hSolo   = $db->createCommand(
                "SELECT COUNT(id) AS a, SUM(amount*price) AS b FROM blocks WHERE coin_id=:id AND solo=1 AND NOT category IN ('orphan','stake','generated') AND time>:t AND algo=:algo",
                [':id' => $coin->id, ':t' => $t24, ':algo' => $coin->algo]
            )->queryOne();

            $poolHash       = (int) $this->coinRate($coin->id, $coin->algo, $util);
            $poolSharedHash = (int) $this->coinSharedRate($coin->id, $coin->algo, $util);
            $poolSoloHash   = (int) $this->coinSoloRate($coin->id, $coin->algo, $util);

            $btcMhd = $conv->mbitcoinvaluetoa($util->yiimp_profitability($coin));

            $minTtf      = ($coin->network_ttf > 0) ? min($coin->actual_ttf, $coin->network_ttf) : $coin->actual_ttf;
            $networkHash = $coin->difficulty * 0x100000000 / ($minTtf ?: 60);

            $stratumRow = $db->createCommand(
                "SELECT port FROM stratums WHERE algo=:algo AND symbol=:sym LIMIT 1",
                [':algo' => $coin->algo, ':sym' => $coin->symbol]
            )->queryScalar();
            $port = $stratumRow ?: $util->getAlgoPort($coin->algo);

            $minPayout = max(
                (float) (defined('YIIMP_PAYMENTS_MINI') ? YIIMP_PAYMENTS_MINI : 0.001),
                (float) ($coin->payout_min ?? 0)
            );

            $row = [
                'name'                  => $coin->name,
                'algo'                  => $coin->algo,
                'port'                  => (int) $port,
                'reward'                => (float) $coin->reward,
                'blocktime'             => (int) $coin->block_time,
                'height'                => (int) $coin->block_height,
                'difficulty'            => (float) $coin->difficulty,
                'autotrade'             => (bool) $coin->auto_exchange,
                'minimumPayment'        => $minPayout,
                'fees'                  => (float) $util->yiimp_fee($coin->algo),
                'fees_solo'             => (float) $util->yiimp_fee_solo($coin->algo),
                'miners'                => $miners,
                'workers'               => $workers,
                'workers_shared'        => $workersShared,
                'workers_solo'          => $workersSolo,
                'shares'                => (int) ($shares['shares'] ?? 0),
                'hashrate'              => $poolHash,
                'hashrate_shared'       => $poolSharedHash,
                'hashrate_solo'         => $poolSoloHash,
                'network_hashrate'      => (int) $networkHash,
                'estimate'              => $btcMhd,
                '24h_blocks'            => (int) ($res24h['a']       ?? 0),
                '24h_blocks_shared'     => (int) ($res24hShared['a'] ?? 0),
                '24h_blocks_solo'       => (int) ($res24hSolo['a']   ?? 0),
                '24h_btc'               => round((float) ($res24h['b'] ?? 0), 8),
                'lastblock'             => $lastBlock,
                'lastblock_shared'      => $lastBlockShared,
                'lastblock_solo'        => $lastBlockSolo,
                'timesincelast'         => $timeSinceLast,
                'timesincelast_shared'  => $timeSinceLastShared,
                'timesincelast_solo'    => $timeSinceLastSolo,
            ];

            if (!empty($coin->symbol2)) {
                $row['symbol'] = $coin->symbol2;
            }

            $data[$coin->symbol] = $row;
        }

        $json = json_encode($data);
        $cache->set($cacheKey, $json, 15);
        return $this->jsonRaw($json);
    }

    // -------------------------------------------------------------------------

    public function actionWallet(): Response
    {
        if ($this->isOverloaded()) {
            return $this->serviceUnavailable();
        }

        $wallet  = Yii::$app->request->get('address', '');
        $user    = $this->findUser($wallet);
        if (!$user) {
            return $this->asJson([]);
        }

        $conv        = Yii::$app->ConversionUtils;
        $totalUnsold = $this->convertEarnings($user, 'status!=2');
        $t24         = time() - 86400;
        $paid24h     = (float) Yii::$app->db->createCommand(
            "SELECT SUM(amount) FROM payouts WHERE time>=:t AND account_id=:uid",
            [':t' => $t24, ':uid' => $user->id]
        )->queryScalar();

        $balance    = $conv->bitcoinvaluetoa($user->balance);
        $totalUnpaid = $conv->bitcoinvaluetoa((float) $user->balance + $totalUnsold);
        $totalEarned = $conv->bitcoinvaluetoa((float) $user->balance + $totalUnsold + $paid24h);

        $coin = Coins::findOne((int) $user->coinid);

        return $this->asJson([
            'currency' => $coin ? $coin->symbol : 'BTC',
            'unsold'   => $totalUnsold,
            'balance'  => $balance,
            'unpaid'   => $totalUnpaid,
            'paid24h'  => $conv->bitcoinvaluetoa($paid24h),
            'total'    => $totalEarned,
        ]);
    }

    // -------------------------------------------------------------------------

    public function actionWalletEx(): Response
    {
        if ($this->isOverloaded()) {
            return $this->serviceUnavailable();
        }

        $wallet = Yii::$app->request->get('address', '');
        $user   = $this->findUser($wallet);
        if (!$user) {
            return $this->asJson([]);
        }

        $conv        = Yii::$app->ConversionUtils;
        $util        = Yii::$app->YiimpUtils;
        $totalUnsold = $this->convertEarnings($user, 'status!=2');
        $t24         = time() - 86400;
        $paid24h     = (float) Yii::$app->db->createCommand(
            "SELECT SUM(amount) FROM payouts WHERE time>=:t AND account_id=:uid",
            [':t' => $t24, ':uid' => $user->id]
        )->queryScalar();

        $balance     = $conv->bitcoinvaluetoa($user->balance);
        $totalUnpaid = $conv->bitcoinvaluetoa((float) $user->balance + $totalUnsold);
        $totalEarned = $conv->bitcoinvaluetoa((float) $user->balance + $totalUnsold + $paid24h);
        $coin        = Coins::findOne((int) $user->coinid);

        $miners = [];
        $workers = Workers::find()->where(['userid' => $user->id])->orderBy('password')->all();
        foreach ($workers as $worker) {
            $miners[] = [
                'version'    => $worker->version,
                'password'   => $worker->password,
                'ID'         => $worker->worker ?? '',
                'algo'       => $worker->algo,
                'difficulty' => (float) $worker->difficulty,
                'subscribe'  => (int) $worker->subscribe,
                'accepted'   => round((float) $util->worker_rate($worker->id, $worker->algo), 3),
                'rejected'   => round((float) $util->worker_rate_bad($worker->id, $worker->algo), 3),
            ];
        }

        $result = [
            'currency' => $coin ? $coin->symbol : 'BTC',
            'unsold'   => $totalUnsold,
            'balance'  => $balance,
            'unpaid'   => $totalUnpaid,
            'paid24h'  => $conv->bitcoinvaluetoa($paid24h),
            'total'    => $totalEarned,
            'miners'   => $miners,
        ];

        if (defined('YIIMP_API_PAYOUTS') && YIIMP_API_PAYOUTS) {
            $period = defined('YIIMP_API_PAYOUTS_PERIOD') ? (int) YIIMP_API_PAYOUTS_PERIOD : 86400;
            $payouts = Payouts::find()
                ->where(['account_id' => $user->id, 'completed' => 1])
                ->andWhere(['not', ['tx' => null]])
                ->andWhere(['>=', 'time', time() - $period])
                ->orderBy(['time' => SORT_DESC])
                ->all();
            $result['payouts'] = array_map(fn($p) => [
                'time'   => (int) $p->time,
                'amount' => (string) $p->amount,
                'tx'     => (string) $p->tx,
            ], $payouts);
        }

        return $this->asJson($result);
    }

    // -------------------------------------------------------------------------

    public function actionRental(): Response
    {
        if (!defined('YIIMP_RENTAL') || !YIIMP_RENTAL) {
            return $this->asJson(['error' => 'Rental not enabled']);
        }
        $util   = Yii::$app->YiimpUtils;
        $conv   = Yii::$app->ConversionUtils;
        $key    = Yii::$app->request->get('key', '');
        $renter = Renters::find()->where(['apikey' => $key])->one();
        if (!$renter) {
            return $this->asJson([]);
        }

        $jobs = [];
        foreach (Jobs::find()->where(['renterid' => $renter->id])->all() as $job) {
            $jobs[] = [
                'jobid'    => (string) $job->id,
                'algo'     => $job->algo,
                'price'    => (string) $job->price,
                'hashrate' => (string) $job->speed,
                'server'   => $job->host,
                'port'     => (string) $job->port,
                'username' => $job->username,
                'password' => $job->password,
                'started'  => (string) $job->ready,
                'active'   => (string) $job->active,
                'accepted' => (string) $util->job_rate($job->id),
                'rejected' => (string) $util->job_rate_bad($job->id),
                'diff'     => (string) $job->difficulty,
            ];
        }

        return $this->asJson([
            'balance'     => $conv->bitcoinvaluetoa($renter->balance),
            'unconfirmed' => $conv->bitcoinvaluetoa($renter->unconfirmed),
            'jobs'        => $jobs,
        ]);
    }

    public function actionRentalPrice(): Response
    {
        return $this->rentalJobUpdate(function ($job) {
            $job->price = Yii::$app->request->get('price', $job->price);
            $job->time  = time();
            $job->save();
        });
    }

    public function actionRentalHashrate(): Response
    {
        return $this->rentalJobUpdate(function ($job) {
            $job->speed = Yii::$app->request->get('hashrate', $job->speed);
            $job->time  = time();
            $job->save();
        });
    }

    public function actionRentalStart(): Response
    {
        return $this->rentalJobUpdate(function ($job) {
            $renter = Renters::findOne($job->renterid);
            if (!$renter || $renter->balance <= 0) return;
            $job->ready = true;
            $job->time  = time();
            $job->save();
        });
    }

    public function actionRentalStop(): Response
    {
        return $this->rentalJobUpdate(function ($job) {
            $job->ready = false;
            $job->time  = time();
            $job->save();
        });
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function findUser(string $wallet): ?Accounts
    {
        if ($wallet === '') return null;
        $user = Accounts::find()->where(['username' => $wallet])->one();
        if (!$user || $user->is_locked) return null;
        return $user;
    }

    /** Convert pending earnings to the user's preferred coin value. */
    private function convertEarnings(Accounts $user, string $statusCondition): float
    {
        $db        = Yii::$app->db;
        $allow     = defined('YIIMP_ALLOW_EXCHANGE') && YIIMP_ALLOW_EXCHANGE;
        $refCoin   = Coins::findOne((int) $user->coinid);
        if (!$refCoin && $allow) {
            $refCoin = Coins::find()->where(['symbol' => 'BTC'])->one();
        }
        if (!$refCoin || $refCoin->price <= 0) return 0.0;

        $rows = $db->createCommand(
            "SELECT coinid, SUM(amount) AS total FROM earnings WHERE userid=:uid AND {$statusCondition} GROUP BY coinid",
            [':uid' => $user->id]
        )->queryAll();

        $total = 0.0;
        foreach ($rows as $row) {
            $coin = Coins::findOne((int) $row['coinid']);
            if (!$coin) continue;
            if ($coin->id == $refCoin->id) {
                $total += (float) $row['total'];
            } elseif ($coin->auto_exchange && $coin->price > 0) {
                $total += (float) $row['total'] * $coin->price / $refCoin->price;
            }
        }
        return $total;
    }

    private function rentalJobUpdate(\Closure $fn): Response
    {
        if (!defined('YIIMP_RENTAL') || !YIIMP_RENTAL) {
            return $this->asJson(['error' => 'Rental not enabled']);
        }
        $key    = Yii::$app->request->get('key', '');
        $renter = Renters::find()->where(['apikey' => $key])->one();
        if (!$renter) return $this->asJson([]);

        $jobId = (int) Yii::$app->request->get('jobid', 0);
        $job   = Jobs::findOne($jobId);
        if (!$job || $job->renterid != $renter->id) return $this->asJson([]);

        $fn($job);
        return $this->asJson(['result' => true]);
    }

    private function isOverloaded(): bool
    {
        $logDir = defined('YIIMP_LOGS') ? YIIMP_LOGS : '/var/log/yiimp';
        return is_file($logDir . '/overloaded');
    }

    private function serviceUnavailable(): Response
    {
        Yii::$app->response->statusCode = 503;
        return $this->asJson(['error' => 'Server overloaded']);
    }

    /** Return a pre-encoded JSON string as-is (avoids double-encoding for cached responses). */
    private function jsonRaw(string $json): Response
    {
        $response = Yii::$app->response;
        $response->format = Response::FORMAT_RAW;
        $response->headers->set('Content-Type', 'application/json; charset=utf-8');
        $response->data = $json;
        return $response;
    }

    // Hashrate helpers — fall back to total pool rate if per-mode not available

    private function poolSharedRate(string $algo, $util): float
    {
        if (method_exists($util, 'pool_shared_rate')) return (float) $util->pool_shared_rate($algo);
        $target   = $util->hashrate_constant($algo);
        $interval = $util->hashrate_step();
        return (float) Yii::$app->db->createCommand(
            "SELECT SUM(difficulty) * :t / :i / 1000 FROM shares WHERE valid=1 AND time>:d AND algo=:algo AND workerid NOT IN (SELECT id FROM workers WHERE password LIKE '%m=solo%')",
            [':t' => $target, ':i' => $interval, ':d' => time() - $interval, ':algo' => $algo]
        )->queryScalar();
    }

    private function poolSoloRate(string $algo, $util): float
    {
        if (method_exists($util, 'pool_solo_rate')) return (float) $util->pool_solo_rate($algo);
        $target   = $util->hashrate_constant($algo);
        $interval = $util->hashrate_step();
        return (float) Yii::$app->db->createCommand(
            "SELECT SUM(difficulty) * :t / :i / 1000 FROM shares WHERE valid=1 AND time>:d AND algo=:algo AND workerid IN (SELECT id FROM workers WHERE password LIKE '%m=solo%')",
            [':t' => $target, ':i' => $interval, ':d' => time() - $interval, ':algo' => $algo]
        )->queryScalar();
    }

    private function coinRate(int $coinId, string $algo, $util): float
    {
        if (method_exists($util, 'coin_rate')) return (float) $util->coin_rate($coinId);
        $target   = $util->hashrate_constant($algo);
        $interval = $util->hashrate_step();
        return (float) Yii::$app->db->createCommand(
            "SELECT SUM(difficulty) * :t / :i / 1000 FROM shares WHERE valid=1 AND time>:d AND coinid=:cid",
            [':t' => $target, ':i' => $interval, ':d' => time() - $interval, ':cid' => $coinId]
        )->queryScalar();
    }

    private function coinSharedRate(int $coinId, string $algo, $util): float
    {
        if (method_exists($util, 'coin_shared_rate')) return (float) $util->coin_shared_rate($coinId);
        $target   = $util->hashrate_constant($algo);
        $interval = $util->hashrate_step();
        return (float) Yii::$app->db->createCommand(
            "SELECT SUM(difficulty) * :t / :i / 1000 FROM shares WHERE valid=1 AND time>:d AND coinid=:cid AND workerid NOT IN (SELECT id FROM workers WHERE password LIKE '%m=solo%')",
            [':t' => $target, ':i' => $interval, ':d' => time() - $interval, ':cid' => $coinId]
        )->queryScalar();
    }

    private function coinSoloRate(int $coinId, string $algo, $util): float
    {
        if (method_exists($util, 'coin_solo_rate')) return (float) $util->coin_solo_rate($coinId);
        $target   = $util->hashrate_constant($algo);
        $interval = $util->hashrate_step();
        return (float) Yii::$app->db->createCommand(
            "SELECT SUM(difficulty) * :t / :i / 1000 FROM shares WHERE valid=1 AND time>:d AND coinid=:cid AND workerid IN (SELECT id FROM workers WHERE password LIKE '%m=solo%')",
            [':t' => $target, ':i' => $interval, ':d' => time() - $interval, ':cid' => $coinId]
        )->queryScalar();
    }
}
