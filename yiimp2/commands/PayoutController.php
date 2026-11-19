<?php

namespace app\commands;

use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use app\models\Coins;
use app\models\Payouts;
use app\models\Accounts;
use app\components\rpc\WalletRPC;

/**
 * Payout verification and repair.
 *
 * Usage:
 *   php yii payout/check <symbol> [fixit]   — compare wallet txs with DB payouts
 *   php yii payout/coinswaps                — find earnings/payouts with mismatched coins
 *   php yii payout/confirmations <symbol>   — list last 72h payouts with confirmation counts
 *   php yii payout/redotx <txid>            — redo payment from a bad fork (YIIMP_CLI_ALLOW_TXS required)
 */
class PayoutController extends Controller
{
    /** Compare wallet transactions with database payouts; optionally create missing ones. */
    public function actionCheck(string $symbol, string $fixit = ''): int
    {
        $coin = Coins::find()->where(['symbol' => $symbol])->one();
        if (!$coin) { $this->stderr("wallet {$symbol} not found!\n"); return ExitCode::UNSPECIFIED_ERROR; }

        $db       = Yii::$app->db;
        $cu       = Yii::$app->ConversionUtils;
        $minPay   = max((float)($coin->txfee ?? 0), defined('YIIMP_PAYMENTS_MINI') ? (float)YIIMP_PAYMENTS_MINI : 0.001);

        // Users with balance or failed payouts
        $users = $db->createCommand(
            "SELECT DISTINCT A.id AS userid, A.username
             FROM accounts A
             WHERE A.coinid = :cid AND A.balance > 0",
            [':cid' => $coin->id]
        )->queryAll();

        if (empty($users)) { $this->stdout("no users found\n"); return ExitCode::OK; }

        $remote  = new WalletRPC($coin);
        $account = ($coin->rpcencoding ?? '') === 'DCR' ? '*' : '';
        $rawtxs  = $remote->listtransactions($account, 25000);

        $nbUpdated = 0;
        $nbCreated = 0;

        foreach ($users as $u) {
            $uid      = (int) $u['userid'];
            $addr     = $u['username'];
            $since    = (int) $db->createCommand(
                "SELECT MAX(time) FROM payouts WHERE account_id = :uid AND fee > 0",
                [':uid' => $uid]
            )->queryScalar() ?: time() - 7 * 86400;

            $payouts  = Payouts::find()
                ->where(['account_id' => $uid])
                ->andWhere(['>=', 'time', $since])
                ->orderBy(['time' => SORT_DESC])
                ->all();

            $this->stdout("{$addr}: " . count($payouts) . " payouts since " . date('Y-m-d H:i', $since) . "\n");

            $totSent    = 0.0;
            $totPayouts = 0.0;

            foreach ($rawtxs as $tx) {
                if (($tx['time'] ?? 0) < $since) continue;
                if (($tx['category'] ?? '') !== 'send' || ($tx['address'] ?? '') !== $addr) continue;

                $amount = abs((float)($tx['amount'] ?? 0));
                $txid   = $tx['txid'] ?? '';
                $totSent += $amount + abs((float)($tx['fee'] ?? 0));
                $match  = false;

                foreach ($payouts as $payout) {
                    if ($payout->tx === $txid && round($payout->amount) === round($amount)) {
                        $totPayouts += $amount + abs((float)($tx['fee'] ?? 0));
                        $match = true;
                        if (($tx['confirmations'] ?? 0) > 5) {
                            $payout->completed = 1;
                            $nbUpdated += (int) $payout->save(false);
                        }
                        break;
                    }
                }

                if (!$match && $fixit === 'fixit') {
                    $p              = new Payouts();
                    $p->account_id  = $uid;
                    $p->tx          = $txid;
                    $p->time        = $tx['time'] ?? time();
                    $p->completed   = 1;
                    $p->amount      = $amount;
                    $p->fee         = abs((float)($tx['fee'] ?? 0));
                    $nbCreated     += (int) $p->save();
                    $user           = Accounts::findOne($uid);
                    if ($user) {
                        $user->balance = (float)$user->balance - $amount;
                        $db->createCommand(
                            "UPDATE balanceuser SET balance = (balance - :a) WHERE userid = :uid AND time >= :t",
                            [':a' => $amount, ':uid' => $uid, ':t' => $p->time]
                        )->execute();
                        $user->save(false);
                    }
                    $this->stdout("extra user tx {$txid} " . date('Y-m-d H:i', $p->time) . " {$amount} {$symbol}\n");
                }
            }

            $diff = $totSent - $totPayouts;
            if ($diff != 0.0) {
                $this->stdout("{$addr}: sent {$totSent} (real), {$totPayouts} (db) → diff {$diff} {$symbol}\n");
            } else {
                $this->stdout("{$addr}: ok\n");
            }
        }

        if ($nbUpdated || $nbCreated) {
            $this->stdout("{$nbUpdated} payouts confirmed, {$nbCreated} payouts created\n");
        }
        return ExitCode::OK;
    }

    /** Find payouts and earnings where the coin does not match the user's assigned coin. */
    public function actionCoinswaps(): int
    {
        $since = time() - 7 * 86400;
        $db    = Yii::$app->db;

        $payouts = $db->createCommand(
            "SELECT C.symbol, C.algo, C2.symbol AS sym2, C2.algo AS algo2, A.username
             FROM payouts P
             INNER JOIN coins C  ON P.idcoin = C.id
             INNER JOIN accounts A ON P.account_id = A.id
             INNER JOIN coins C2 ON A.coinid = C2.id
             WHERE P.time > :s AND A.coinid != P.idcoin",
            [':s' => $since]
        )->queryAll();

        if ($payouts) {
            $this->stdout("user payouts to check:\n");
            foreach ($payouts as $row) $this->stdout(json_encode($row) . "\n");
        } else {
            $this->stdout("payouts: all fine\n");
        }

        $earnings = $db->createCommand(
            "SELECT DISTINCT C.symbol, C.algo, A.username
             FROM earnings E
             INNER JOIN accounts A ON E.userid = A.id
             INNER JOIN coins C ON E.coinid = C.id
             WHERE E.create_time > :s AND E.status < 0",
            [':s' => $since]
        )->queryAll();

        if ($earnings) {
            $this->stdout("user earnings to check:\n");
            foreach ($earnings as $row) $this->stdout(json_encode($row) . "\n");
        } else {
            $this->stdout("earnings: all fine\n");
        }

        return ExitCode::OK;
    }

    /** List last 72h payouts with confirmation counts from the wallet. */
    public function actionConfirmations(string $symbol): int
    {
        $coin = Coins::find()->where(['symbol' => $symbol])->one();
        if (!$coin) { $this->stderr("wallet {$symbol} not found!\n"); return ExitCode::UNSPECIFIED_ERROR; }

        $since = time() - 72 * 3600;
        $cu    = Yii::$app->ConversionUtils;
        $rows  = Yii::$app->db->createCommand(
            "SELECT P.tx, MAX(P.time) AS time, SUM(P.amount) AS amount
             FROM payouts P WHERE P.time > :s AND P.idcoin = :id
             GROUP BY P.tx ORDER BY time DESC",
            [':s' => $since, ':id' => $coin->id]
        )->queryAll();

        $remote = new WalletRPC($coin);
        foreach ($rows as $row) {
            $tx    = $remote->gettransaction($row['tx']);
            $confs = $tx['confirmations'] ?? 0;
            $fee   = $cu->bitcoinvaluetoa($tx['fee'] ?? 0);
            $amt   = $cu->bitcoinvaluetoa($row['amount']);
            $date  = date('Y-m-d H:i', (int)$row['time']);
            $this->stdout("{$date} {$row['tx']} {$confs} confs ({$amt} {$symbol}, fees: {$fee})\n");
        }
        return ExitCode::OK;
    }

    /** Redo payment from a bad fork — re-sends same amounts under a new txid. */
    public function actionRedotx(string $txid): int
    {
        if (!defined('YIIMP_CLI_ALLOW_TXS') || !YIIMP_CLI_ALLOW_TXS) {
            $this->stderr("YIIMP_CLI_ALLOW_TXS is not enabled in serverconfig.php\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $payouts = Payouts::find()->where(['tx' => $txid])->all();
        if (!$payouts) { $this->stderr("invalid payout txid\n"); return ExitCode::UNSPECIFIED_ERROR; }

        $coin = Coins::findOne($payouts[0]->idcoin);
        if (!$coin || !$coin->installed) { $this->stderr("invalid coin\n"); return ExitCode::UNSPECIFIED_ERROR; }

        $dests    = [];
        $total    = 0.0;
        $relayfee = 0.0001;

        foreach ($payouts as $payout) {
            $user = Accounts::findOne($payout->account_id);
            if (!$user || $user->coinid != $coin->id) continue;
            if ((float)$payout->amount < $relayfee) continue;
            $dests[$user->username] = (float)$payout->amount;
            $total += (float)$payout->amount;
        }

        $this->stdout("{$total} {$coin->symbol} to pay to " . count($dests) . " recipients...\n");

        $remote   = new WalletRPC($coin);
        $newTxid  = $remote->sendmany((string)($coin->account ?? ''), $dests);
        if (!$newTxid) {
            $this->stderr("sendmany failed\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout("new txid: {$newTxid}\n");
        $nbnew = 0;
        foreach ($payouts as $payout) {
            if ((float)$payout->amount < $relayfee) continue;
            $p              = new Payouts();
            $p->time        = time();
            $p->idcoin      = $coin->id;
            $p->amount      = (float)$payout->amount;
            $p->account_id  = $payout->account_id;
            $p->completed   = 1;
            $p->fee         = 0;
            $p->tx          = $newTxid;
            $nbnew         += (int) $p->save();
        }
        $this->stdout("payout rows added: {$nbnew}\n");

        if ($nbnew === count($payouts)) {
            Yii::$app->db->createCommand()->update(
                'payouts', ['completed' => 0, 'tx' => 'orphaned', 'memoid' => 'redo'], ['tx' => $txid]
            )->execute();
            $this->stdout("original payouts marked as 'orphaned'\n");
        }
        return ExitCode::OK;
    }
}
