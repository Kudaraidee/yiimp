<?php

namespace app\services;

use Yii;
use app\models\Algos;
use app\models\Accounts;
use app\models\Coins;
use app\models\Hashrate;
use app\models\HashStats;
use app\models\HashRenter;
use app\models\Hashuser;
use app\models\Balanceuser;
use app\models\Stats;
use app\models\Stratums;

/**
 * StatsService — pool hashrate, earnings, and balance snapshot recording.
 *
 * Ported from: web/yaamp/core/backend/stats.php
 *   BackendStatsUpdate()  → updatePoolStats()
 *   BackendStatsUpdate2() → updateExtendedStats()
 *
 * External utility methods (all in Yii::$app->YiimpUtils):
 *   get_algos(), pool_rate(), pool_rate_bad(), yiimp_profitability(),
 *   get_algo_norm(), hashrate_constant(), hashrate_step(),
 *   user_rate(), user_rate_bad(), job_rate(), job_rate_bad()
 */
class StatsService
{
    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Snapshot pool-wide hashrate, earnings, pricing, and balance data.
     * Runs every 60 s (UpdatePoolStatsJob).
     * Ports: BackendStatsUpdate()
     */
    public function updatePoolStats(): void
    {
        $db   = Yii::$app->db;
        $util = Yii::$app->YiimpUtils;
        $now  = time();

        // ------------------------------------------------------------------ //
        // Stratum cleanup — remove stale records and orphaned workers

        $staleTime = $now - 2 * 60;
        $db->createCommand("DELETE FROM stratums WHERE time < {$staleTime}")->execute();
        $db->createCommand("DELETE FROM workers WHERE pid NOT IN (SELECT pid FROM stratums)")->execute();
        $db->createCommand("DELETE FROM hashstats WHERE IFNULL(hashrate,0)=0 AND IFNULL(earnings,0)=0")->execute();

        // ------------------------------------------------------------------ //
        // Long-term stats: one row per (hour bucket × algo) in hashstats

        $tmHour = (int) (floor($now / 3600) * 3600);

        foreach ($util->get_algos() as $algo) {
            $poolRate = $util->pool_rate($algo);

            $stats = HashStats::find()
                ->where(['time' => $tmHour, 'algo' => $algo])
                ->one();

            if (!$stats) {
                $stats           = new HashStats();
                $stats->time     = $tmHour;
                $stats->algo     = $algo;
                $stats->hashrate = $poolRate;
                $stats->earnings = null;
            } else {
                $stats->hashrate = (int) round(($stats->hashrate * 99 + $poolRate) / 100);
            }

            $earnings = Yii::$app->ConversionUtils->bitcoinvaluetoa(
                (float) $db->createCommand(
                    "SELECT SUM(amount*price) FROM blocks WHERE algo=:algo AND time>:tm AND category!='orphan'",
                    [':algo' => $algo, ':tm' => $tmHour]
                )->queryScalar()
            );

            if (Yii::$app->ConversionUtils->bitcoinvaluetoa($stats->earnings) !== $earnings) {
                Yii::debug("{$algo} earnings: {$earnings} BTC", __CLASS__);
                $stats->earnings = $earnings;
            }

            if ((float) $earnings || $stats->hashrate) {
                $stats->save();
            }
        }

        // ------------------------------------------------------------------ //
        // Short-term stats: 15-minute bucket per algo in hashrate table

        $step = 15;
        $tm   = (int) (floor($now / ($step * 60)) * $step * 60);

        $feesRenting   = defined('YIIMP_FEES_RENTING') ? (float) YIIMP_FEES_RENTING : 2.0;
        $limitEstimate = defined('YIIMP_LIMIT_ESTIMATE') && YIIMP_LIMIT_ESTIMATE;

        foreach ($util->get_algos() as $algo) {
            $stats = Hashrate::find()->where(['time' => $tm, 'algo' => $algo])->one();

            if (!$stats) {
                $stats = new Hashrate();
                $stats->time         = $tm;
                $stats->algo         = $algo;
                $stats->hashrate     = (int) $db->createCommand(
                    "SELECT hashrate FROM hashrate WHERE algo=:algo ORDER BY time DESC LIMIT 1",
                    [':algo' => $algo]
                )->queryScalar();
                $stats->hashrate_bad = 0;
                $stats->price        = (float) $db->createCommand(
                    "SELECT price FROM hashrate WHERE algo=:algo ORDER BY time DESC LIMIT 1",
                    [':algo' => $algo]
                )->queryScalar();
                $stats->rent         = (float) $db->createCommand(
                    "SELECT rent FROM hashrate WHERE algo=:algo ORDER BY time DESC LIMIT 1",
                    [':algo' => $algo]
                )->queryScalar();
            }

            $poolRate        = $util->pool_rate($algo);
            $poolRateBad     = $util->pool_rate_bad($algo);
            $stats->hashrate = ($poolRate < 1000) ? 0 : (int) $poolRate;
            $stats->hashrate_bad = ($poolRateBad < 1000) ? 0 : (int) $poolRateBad;

            // Price/rent calculation from recent shares
            $t5m = $now - 5 * 60;
            $totalRentable = (float) $db->createCommand(
                "SELECT SUM(difficulty) FROM shares WHERE valid AND extranonce1 AND algo=:algo AND time>:t",
                [':algo' => $algo, ':t' => $t5m]
            )->queryScalar();
            $totalDiff = (float) $db->createCommand(
                "SELECT SUM(difficulty) FROM shares WHERE valid AND algo=:algo AND time>:t",
                [':algo' => $algo, ':t' => $t5m]
            )->queryScalar();

            if (!$totalDiff) {
                $t15m      = $now - 15 * 60;
                $totalDiff = (float) $db->createCommand(
                    "SELECT SUM(difficulty) FROM shares WHERE valid AND algo=:algo AND time>:t",
                    [':algo' => $algo, ':t' => $t15m]
                )->queryScalar();
            }

            if ($totalDiff > 0) {
                $price       = 0.0;
                $rent        = 0.0;
                $totalRented = 0.0;

                $sharesByCoins = $db->createCommand(
                    "SELECT coinid, SUM(difficulty) AS d FROM shares WHERE valid AND algo=:algo AND time>:t GROUP BY coinid",
                    [':algo' => $algo, ':t' => $t5m]
                )->queryAll();

                foreach ($sharesByCoins as $item) {
                    if ($item['coinid'] == 0) {
                        if (!$totalRentable) {
                            continue;
                        }
                        $totalRented = $item['d'];
                        $price      += $stats->rent * $item['d'] / $totalDiff;
                        $rent       += $stats->rent * $item['d'] / $totalRentable;
                    } else {
                        $coin = Coins::findOne((int) $item['coinid']);
                        if (!$coin) {
                            continue;
                        }
                        $btcGhd  = $util->yiimp_profitability($coin);
                        $price  += $btcGhd * $item['d'] / $totalDiff;
                        $rent   += $btcGhd * $item['d'] / $totalDiff;
                    }
                }

                $rent = max($price, ($stats->rent * 67 + $rent * 33) / 100);

                // Adjust rent based on rentable capacity vs demand
                $hashConst = $this->hashrateConstant($algo);
                $hashStep  = $this->hashrateStep();
                $aa        = $totalRentable * $hashConst / $hashStep / 1000;
                $bb        = (float) $db->createCommand(
                    "SELECT SUM(speed) FROM jobs WHERE active AND ready AND price>:rent AND algo=:algo",
                    [':rent' => $rent, ':algo' => $algo]
                )->queryScalar();

                if ($totalRented * 1.3 < $totalRentable || $bb > $aa) {
                    $rent += $price * $feesRenting / 100;
                } else {
                    $rent -= $price * $feesRenting / 100;
                }

                $stats->price = $price;
                $stats->rent  = $rent;

            } else {
                $bestCoin = Coins::find()
                    ->where(['enable' => 1, 'auto_ready' => 1, 'algo' => $algo])
                    ->orderBy(['index_avg' => SORT_DESC])
                    ->one();
                if ($bestCoin) {
                    $btcGhd      = $util->yiimp_profitability($bestCoin);
                    $stats->price = $btcGhd;
                    $stats->rent  = $btcGhd + $btcGhd * $feesRenting / 100;
                }
            }

            // Cap estimate against 24h average × 1.5
            if ($limitEstimate) {
                $avg = (float) $db->createCommand(
                    "SELECT AVG(price) FROM hashrate WHERE time>:t AND algo=:algo",
                    [':t' => $now - 86400, ':algo' => $algo]
                )->queryScalar();
                if ($avg) {
                    $stats->price = min((float) $stats->price, $avg * 1.5);
                }
            }

            $stats->difficulty = (float) $db->createCommand(
                "SELECT SUM(difficulty) FROM coins WHERE enable AND auto_ready AND algo=:algo",
                [':algo' => $algo]
            )->queryScalar();

            $stats->save();
        }

        // ------------------------------------------------------------------ //
        // Pool financial snapshot

        $btc    = Coins::find()->where(['symbol' => 'BTC'])->one();
        $btcId  = $btc ? $btc->id : 6;
        $btcBal = $btc ? (float) $btc->balance : 0.0;

        $snapshot = Stats::find()->where(['time' => $tm])->one() ?? new Stats();
        $snapshot->time     = $tm;
        $snapshot->profit   = $btcBal
            + (float) $db->createCommand("SELECT SUM(balance) FROM balances")->queryScalar()
            + (float) $db->createCommand("SELECT SUM(amount*bid) FROM orders")->queryScalar()
            + (float) $db->createCommand("SELECT SUM(balance*price) FROM coins WHERE enable AND symbol!='BTC'")->queryScalar()
            - (float) $db->createCommand("SELECT SUM(balance) FROM accounts WHERE coinid=:id", [':id' => $btcId])->queryScalar()
            - (float) $db->createCommand("SELECT SUM(balance) FROM renters")->queryScalar();

        $snapshot->wallet   = $btcBal;
        $snapshot->wallets  = (float) $db->createCommand("SELECT SUM(balance*price) FROM coins WHERE enable AND symbol!='BTC'")->queryScalar();
        $snapshot->margin   = $btcBal - (float) $db->createCommand("SELECT SUM(balance) FROM accounts WHERE coinid=:id", [':id' => $btcId])->queryScalar();
        $snapshot->balances = (float) $db->createCommand("SELECT SUM(balance) FROM balances")->queryScalar();
        $snapshot->onsell   = (float) $db->createCommand("SELECT SUM(amount*bid) FROM orders")->queryScalar();
        $snapshot->immature = (float) $db->createCommand("SELECT SUM(amount*price) FROM earnings WHERE status=0")->queryScalar();
        $snapshot->waiting  = (float) $db->createCommand("SELECT SUM(amount*price) FROM earnings WHERE status=1")->queryScalar();
        $snapshot->renters  = (float) $db->createCommand("SELECT SUM(balance) FROM renters")->queryScalar();
        $snapshot->save();

        // ------------------------------------------------------------------ //
        // Algo table — current price/rent snapshot

        foreach ($util->get_algos() as $algo) {
            $dbalgo = Algos::find()->where(['name' => $algo])->one() ?? new Algos();
            $dbalgo->name   = $algo;
            $dbalgo->profit = (float) $db->createCommand(
                "SELECT price FROM hashrate WHERE algo=:algo ORDER BY time DESC LIMIT 1",
                [':algo' => $algo]
            )->queryScalar();
            $dbalgo->rent   = (float) $db->createCommand(
                "SELECT rent FROM hashrate WHERE algo=:algo ORDER BY time DESC LIMIT 1",
                [':algo' => $algo]
            )->queryScalar();
            $dbalgo->factor = $util->get_algo_norm($algo);
            $dbalgo->save();
        }
    }

    /**
     * Snapshot per-user and per-renting-job hashrates, and user balance history.
     * Runs every 5 min (UpdateExtendedStatsJob).
     * Ports: BackendStatsUpdate2()
     */
    public function updateExtendedStats(): void
    {
        $db   = Yii::$app->db;
        $util = Yii::$app->YiimpUtils;
        $now  = time();
        $step = 15;
        $tm   = (int) (floor($now / ($step * 60)) * $step * 60);

        // ------------------------------------------------------------------ //
        // Per-user hashrate per algo

        $activeUsers = $db->createCommand(
            "SELECT userid, algo FROM shares WHERE time>:tm GROUP BY userid, algo",
            [':tm' => $tm]
        )->queryAll();

        foreach ($activeUsers as $item) {
            $stats = Hashuser::find()
                ->where(['time' => $tm, 'algo' => $item['algo'], 'userid' => $item['userid']])
                ->one();

            if (!$stats) {
                $stats           = new Hashuser();
                $stats->userid   = $item['userid'];
                $stats->time     = $tm;
                $stats->algo     = $item['algo'];
                $stats->hashrate = (int) $db->createCommand(
                    "SELECT hashrate FROM hashuser WHERE algo=:algo AND userid=:uid ORDER BY time DESC LIMIT 1",
                    [':algo' => $item['algo'], ':uid' => $item['userid']]
                )->queryScalar();
                $stats->hashrate_bad = 0;
            }

            $userRate    = $this->userRate((int) $item['userid'], $item['algo']);
            $userRateBad = $this->userRateBad((int) $item['userid'], $item['algo']);

            $stats->hashrate     = ($userRate < 1000) ? 0 : (int) round(($stats->hashrate * 80 + $userRate * 20) / 100);
            $stats->hashrate_bad = ($userRateBad < 1000) ? 0 : (int) round(($stats->hashrate_bad * 80 + $userRateBad * 20) / 100);
            $stats->save();
        }

        // ------------------------------------------------------------------ //
        // Per-renting-job hashrate

        $activeJobs = $db->createCommand(
            "SELECT DISTINCT jobid FROM jobsubmits WHERE time>:tm",
            [':tm' => $tm]
        )->queryAll();

        foreach ($activeJobs as $item) {
            $jobId = (int) $item['jobid'];

            $stats = HashRenter::find()->where(['time' => $tm, 'jobid' => $jobId])->one();

            if (!$stats) {
                $stats           = new HashRenter();
                $stats->jobid    = $jobId;
                $stats->time     = $tm;
                $stats->hashrate = (int) $db->createCommand(
                    "SELECT hashrate FROM hashrenter WHERE jobid=:jid ORDER BY time DESC LIMIT 1",
                    [':jid' => $jobId]
                )->queryScalar();
                $stats->hashrate_bad = 0;
            }

            $jobRate    = $this->jobRate($jobId);
            $jobRateBad = $this->jobRateBad($jobId);

            $stats->hashrate     = ($jobRate < 1000) ? 0 : (int) round(($stats->hashrate * 80 + $jobRate * 20) / 100);
            $stats->hashrate_bad = ($jobRateBad < 1000) ? 0 : (int) round(($stats->hashrate_bad * 80 + $jobRateBad * 20) / 100);
            $stats->save();
        }

        // ------------------------------------------------------------------ //
        // User balance snapshots

        $delay24h = $now - 86400;
        $users    = Accounts::find()
            ->where(['>', 'balance', 0])
            ->orWhere(['>', 'last_earning', $delay24h])
            ->all();

        foreach ($users as $user) {
            $bals = Balanceuser::find()->where(['time' => $tm, 'userid' => $user->id])->one();
            if (!$bals) {
                $bals         = new Balanceuser();
                $bals->userid = $user->id;
                $bals->time   = $tm;
            }

            $bals->pending = Yii::$app->ConversionUtils->bitcoinvaluetoa(
                $this->convertEarningsForUser($user, 'status!=2')
            );
            $bals->balance = $user->balance;
            $bals->save();

            // Drop old cleared earnings beyond the last 100, keeping recent ones
            $cutoffId = $db->createCommand(
                "SELECT id FROM earnings WHERE userid=:uid ORDER BY id DESC LIMIT 100, 1",
                [':uid' => $user->id]
            )->queryScalar();
            if ($cutoffId) {
                $db->createCommand(
                    "DELETE FROM earnings WHERE status=2 AND userid=:uid AND id<:id",
                    [':uid' => $user->id, ':id' => $cutoffId]
                )->execute();
            }
        }
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Sum the pending earnings value (converted to user's coin) for a user.
     * Inline port of YIIMP_convert_earnings_user() from web/yaamp/core/functions/yaamp.php.
     */
    private function convertEarningsForUser(object $user, string $statusCondition): float
    {
        $db    = Yii::$app->db;
        $allow = defined('YIIMP_ALLOW_EXCHANGE') && YIIMP_ALLOW_EXCHANGE;

        $refCoin = $user->coinid ? Coins::findOne((int) $user->coinid) : null;
        if (!$refCoin && $allow) {
            $refCoin = Coins::find()->where(['symbol' => 'BTC'])->one();
        }

        if (!$refCoin || $refCoin->price <= 0) {
            return 0.0;
        }

        $rows = $db->createCommand(
            "SELECT coinid, SUM(amount) AS total FROM earnings
             WHERE userid=:uid AND {$statusCondition}
             GROUP BY coinid",
            [':uid' => $user->id]
        )->queryAll();

        $total = 0.0;
        foreach ($rows as $row) {
            $coin = Coins::findOne((int) $row['coinid']);
            if (!$coin) {
                continue;
            }
            if ($coin->id == $refCoin->id) {
                $total += (float) $row['total'];
            } elseif ($coin->auto_exchange && $coin->price > 0 && $refCoin->price > 0) {
                $total += (float) $row['total'] * $coin->price / $refCoin->price;
            }
        }

        return $total;
    }

    private function hashrateConstant(string $algo): float
    {
        return in_array($algo, ['equihash', 'equihash96', 'equihash125', 'equihash144', 'equihash192'], true)
            ? 0x0000000004000000
            : 0x0000040000000000;
    }

    private function hashrateStep(): float
    {
        return 300.0;
    }

    private function userRate(int $userId, string $algo): float
    {
        $interval = $this->hashrateStep();
        $target   = $this->hashrateConstant($algo);
        $t        = time() - (int) $interval;
        $diff     = (float) Yii::$app->db->createCommand(
            "SELECT SUM(difficulty) FROM shares WHERE valid AND userid=:uid AND algo=:algo AND time>:t",
            [':uid' => $userId, ':algo' => $algo, ':t' => $t]
        )->queryScalar();
        return $diff ? $diff * $target / $interval / 1000 : 0.0;
    }

    private function userRateBad(int $userId, string $algo): float
    {
        $interval = $this->hashrateStep();
        $target   = $this->hashrateConstant($algo);
        $t        = time() - (int) $interval;
        $avgDiff  = (float) Yii::$app->db->createCommand(
            "SELECT AVG(difficulty) FROM shares WHERE valid AND userid=:uid AND algo=:algo AND time>:t",
            [':uid' => $userId, ':algo' => $algo, ':t' => $t]
        )->queryScalar();
        $count = (float) Yii::$app->db->createCommand(
            "SELECT COUNT(id) FROM shares WHERE valid != 1 AND userid=:uid AND algo=:algo AND time>:t",
            [':uid' => $userId, ':algo' => $algo, ':t' => $t]
        )->queryScalar();
        return ($avgDiff && $count) ? $count * $avgDiff * $target / $interval / 1000 : 0.0;
    }

    public function jobRate(int $jobId): float
    {
        $job = Yii::$app->db->createCommand(
            "SELECT algo FROM jobs WHERE id=:jid", [':jid' => $jobId]
        )->queryOne();
        if (!$job) return 0.0;
        $interval = $this->hashrateStep();
        $target   = $this->hashrateConstant($job['algo']);
        $t        = time() - (int) $interval;
        $diff     = (float) Yii::$app->db->createCommand(
            "SELECT SUM(difficulty) FROM jobsubmits WHERE valid AND jobid=:jid AND time>:t",
            [':jid' => $jobId, ':t' => $t]
        )->queryScalar();
        return $diff ? $diff * $target / $interval / 1000 : 0.0;
    }

    public function jobRateBad(int $jobId): float
    {
        $job = Yii::$app->db->createCommand(
            "SELECT algo FROM jobs WHERE id=:jid", [':jid' => $jobId]
        )->queryOne();
        if (!$job) return 0.0;
        $interval = $this->hashrateStep();
        $target   = $this->hashrateConstant($job['algo']);
        $t        = time() - (int) $interval;
        $avgDiff  = (float) Yii::$app->db->createCommand(
            "SELECT AVG(difficulty) FROM jobsubmits WHERE valid AND jobid=:jid AND time>:t",
            [':jid' => $jobId, ':t' => $t]
        )->queryScalar();
        $count = (float) Yii::$app->db->createCommand(
            "SELECT COUNT(id) FROM jobsubmits WHERE valid != 1 AND jobid=:jid AND time>:t",
            [':jid' => $jobId, ':t' => $t]
        )->queryScalar();
        return ($avgDiff && $count) ? $count * $avgDiff * $target / $interval / 1000 : 0.0;
    }
}
