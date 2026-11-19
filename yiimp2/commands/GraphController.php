<?php

namespace app\commands;

use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use app\models\Coins;

/**
 * Detect and fill holes in history graphs (hashrate and balance time series).
 *
 * Usage:
 *   php yii graph/poolrate [algo]   — fill holes in pool hashrate graph
 *   php yii graph/hashrate [algo]   — fill holes in user hashrate graphs
 *   php yii graph/balances [symbol] — fill holes in user balance graphs
 */
class GraphController extends Controller
{
    private const STEP = 900; // 15 minutes

    /** Fill holes in pool-wide hashrate time series (hashrate table). */
    public function actionPoolrate(string $algo = ''): int
    {
        $algos = $algo ? [$algo] : Yii::$app->YiimpUtils->get_algos();
        foreach ($algos as $a) {
            do {
                $added = $this->fillPoolHashrateHoles($a);
            } while ($added > 0);
        }
        return ExitCode::OK;
    }

    /** Fill holes in per-user hashrate time series (hashuser table). */
    public function actionHashrate(string $algo = ''): int
    {
        $algos = $algo ? [$algo] : Yii::$app->YiimpUtils->get_algos();
        foreach ($algos as $a) {
            do {
                $added = $this->fillUserHashrateHoles($a);
            } while ($added > 0);
        }
        return ExitCode::OK;
    }

    /** Fill holes in per-user balance time series (balanceuser table). */
    public function actionBalances(string $symbol = ''): int
    {
        if ($symbol) {
            do {
                $added = $this->fillUserBalanceHoles($symbol);
            } while ($added > 0);
        } else {
            $coins = Yii::$app->db->createCommand(
                "SELECT symbol FROM coins WHERE enable AND visible ORDER BY symbol"
            )->queryColumn();
            foreach ($coins as $sym) {
                $this->stdout("checking {$sym}...\n");
                do {
                    $added = $this->fillUserBalanceHoles($sym);
                } while ($added > 0);
            }
        }
        return ExitCode::OK;
    }

    // ── Hole-filling helpers ───────────────────────────────────────────────────

    private function fillPoolHashrateHoles(string $algo): int
    {
        $since = time() - 86400;
        $rows  = Yii::$app->db->createCommand(
            "SELECT * FROM hashrate WHERE time >= :s AND algo = :a ORDER BY time ASC",
            [':s' => $since, ':a' => $algo]
        )->queryAll();

        $added   = 0;
        $lastRow = null;
        $lastT   = 0;

        foreach ($rows as $row) {
            $t = (int) $row['time'];
            $d = $lastT ? $t - $lastT : 0;
            if ($d && $d !== self::STEP) {
                $h0 = date('H:i', $lastT);
                $h  = date('H:i', $t);
                $this->stdout("pool {$algo}: hole {$h0}-{$h} ({$d}s)\n");
                Yii::$app->db->createCommand()->insert('hashrate', [
                    'time'       => $lastT + self::STEP,
                    'algo'       => $algo,
                    'hashrate'   => $d > 3600 ? 0 : ((float)$lastRow['hashrate'] + (float)$row['hashrate']) / 2,
                    'price'      => ((float)$lastRow['price']      + (float)$row['price'])      / 2,
                    'rent'       => ((float)$lastRow['rent']       + (float)$row['rent'])       / 2,
                    'difficulty' => ((float)$lastRow['difficulty'] + (float)$row['difficulty']) / 2,
                ])->execute();
                $added++;
            }
            $lastT   = $t;
            $lastRow = $row;
        }
        $this->stdout(count($rows) . " pool records for {$algo} ({$added} added)\n");
        return $added;
    }

    private function fillUserHashrateHoles(string $algo): int
    {
        $since = time() - 86400;
        $rows  = Yii::$app->db->createCommand(
            "SELECT * FROM hashuser WHERE time >= :s AND algo = :a ORDER BY userid, time",
            [':s' => $since, ':a' => $algo]
        )->queryAll();

        $added   = 0;
        $lastT   = 0;
        $lastRow = null;

        foreach ($rows as $row) {
            $t  = (int) $row['time'];
            $d  = ($lastRow && $lastRow['userid'] == $row['userid']) ? $t - $lastT : 0;
            if ($d && $d !== self::STEP && (float)$row['hashrate'] > 0) {
                $h0 = date('H:i', $lastT);
                $h  = date('H:i', $t);
                $this->stdout("uid {$row['userid']} {$algo}: hole {$h0}-{$h} ({$d}s)\n");
                Yii::$app->db->createCommand()->insert('hashuser', [
                    'time'     => $lastT + self::STEP,
                    'userid'   => $row['userid'],
                    'algo'     => $algo,
                    'hashrate' => $d > 3600 ? 0 : ((float)$lastRow['hashrate'] + (float)$row['hashrate']) / 2,
                ])->execute();
                $added++;
            }
            $lastT   = $t;
            $lastRow = $row;
        }
        $this->stdout(count($rows) . " user records for {$algo} ({$added} added)\n");
        return $added;
    }

    private function fillUserBalanceHoles(string $symbol): int
    {
        $coin = Coins::find()->where(['symbol' => $symbol])->one();
        if (!$coin) return 0;

        $since = time() - 86400;
        $rows  = Yii::$app->db->createCommand(
            "SELECT B.userid, B.time, B.balance, B.pending, A.username
             FROM balanceuser B
             INNER JOIN accounts A ON A.id = B.userid
             WHERE A.coinid = :cid AND A.last_earning > :s
             ORDER BY B.userid, B.time",
            [':cid' => $coin->id, ':s' => $since]
        )->queryAll();

        $added   = 0;
        $lastT   = 0;
        $lastRow = null;

        foreach ($rows as $row) {
            $t = (int) $row['time'];
            $d = ($lastRow && $lastRow['userid'] == $row['userid']) ? $t - $lastT : 0;
            if ($d && $d !== self::STEP && ((float)$row['pending'] + (float)$row['balance']) > 0) {
                if ($d > 3600) { $lastT = $t; $lastRow = $row; continue; }
                $h0 = date('H:i', $lastT);
                $h  = date('H:i', $t);
                $this->stdout("{$row['username']}: hole {$h0}-{$h} ({$d}s)\n");
                Yii::$app->db->createCommand()->insert('balanceuser', [
                    'time'    => $lastT + self::STEP,
                    'userid'  => $row['userid'],
                    'balance' => $lastRow['balance'],
                    'pending' => $lastRow['pending'],
                ])->execute();
                $added++;
            }
            $lastT   = $t;
            $lastRow = $row;
        }
        $this->stdout(count($rows) . " balance records for {$symbol} ({$added} added)\n");
        return $added;
    }
}
