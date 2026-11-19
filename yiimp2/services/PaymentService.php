<?php

namespace app\services;

use Yii;
use app\models\Accounts;
use app\models\Coins;
use app\models\Earnings;
use app\models\Payouts;

/**
 * PaymentService — coin payouts, earnings clearing, and database maintenance.
 *
 * Ported from:
 *   web/yaamp/core/backend/payment.php  → doPayments, payCoin, cancelFailedPayment
 *   web/yaamp/core/backend/clear.php    → clearEarnings
 *   web/yaamp/core/backend/system.php   → doBackup, quickClean, cleanDatabase,
 *                                          pruneMarketHistory, consolidateOldShares
 */
class PaymentService
{
    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Run the full payment sequence for every enabled coin that has users with payable balances.
     * Ports: BackendPayments()
     */
    public function doPayments(): void
    {
        set_time_limit(300);

        $coins = Coins::find()
            ->where(['enable' => 1])
            ->andWhere(['in', 'id',
                (new \yii\db\Query())->select('coinid')->from('accounts')->distinct()
            ])
            ->all();

        foreach ($coins as $coin) {
            $this->payCoin($coin);
        }

        Yii::$app->db->createCommand("UPDATE accounts SET balance=0 WHERE coinid=0")->execute();
    }

    /**
     * Execute payouts for a single coin.
     * Handles both sendmany (batch) and sendtoaddress (per-user) modes,
     * BTC cold-wallet distribution, and retry of previously failed payouts.
     * Ports: BackendCoinPayments()
     */
    public function payCoin(Coins $coin): void
    {
        $remote = new \app\components\rpc\WalletRPC($coin);
        $info   = $remote->getinfo();

        if (!$info) {
            Yii::warning("payment: can't connect to {$coin->symbol} wallet", __CLASS__);
            return;
        }

        $txfee      = (float) $coin->txfee;
        $paymentMin = defined('YIIMP_PAYMENTS_MINI') ? (float) YIIMP_PAYMENTS_MINI : 0.0001;
        $minPayout  = max($paymentMin, (float) $coin->payout_min, $txfee);

        // Sunday evening: reduce minimum to clear smaller balances
        if (date('w') == 0 && (int) date('H') > 18) {
            $minPayout = max($minPayout / 10, $txfee);
            if ($coin->symbol === 'DCR') {
                $minPayout = 0.01005;
            }
        }

        $users = Accounts::find()
            ->where(['coinid' => $coin->id])
            ->andWhere(['is_locked' => 0])
            ->andWhere(['>', 'balance', $minPayout])
            ->andWhere(['or', ['payout_threshold' => null], new \yii\db\Expression('balance > payout_threshold')])
            ->orderBy(['balance' => SORT_DESC])
            ->all();

        // Coins that do not support sendmany or have a payout_max cap
        $sendToAddressCoins = ['BITC','BNODE','BOD','DIME','BTCRY','IOTS','ECC','ADOT','SAPP','CURVE','CBE','PEPEW'];
        $useSendToAddress   = !empty($coin->payout_max) || in_array($coin->symbol, $sendToAddressCoins, true);

        if ($useSendToAddress) {
            foreach ($users as $user) {
                $user = Accounts::findOne($user->id);
                if (!$user) {
                    continue;
                }

                $amount = (float) $user->balance;
                while ($user->balance > $minPayout && $amount > $minPayout) {
                    Yii::info("{$coin->symbol} sendtoaddress {$user->username} {$amount}", __CLASS__);
                    $tx = $remote->sendtoaddress($user->username, round($amount, 8));

                    if (!$tx) {
                        $error = $remote->error ?? '';
                        Yii::warning("RPC {$error}, {$user->username}, {$amount}", __CLASS__);
                        if (stripos($error, 'transaction too large') !== false
                            || stripos($error, 'invalid amount') !== false
                            || stripos($error, 'insufficient funds') !== false
                            || stripos($error, 'transaction creation failed') !== false
                        ) {
                            $coin->payout_max = min((float) $amount, (float) ($coin->payout_max ?: PHP_INT_MAX));
                            $coin->save();
                            $amount /= 2;
                            continue;
                        }
                        break;
                    }

                    $payout             = new Payouts();
                    $payout->account_id = $user->id;
                    $payout->time       = time();
                    $payout->amount     = $this->bitcoinValueToString($amount);
                    $payout->fee        = 0;
                    $payout->tx         = $tx;
                    $payout->idcoin     = $coin->id;
                    $payout->save();

                    $user->balance -= $amount;
                    $user->save();
                }
            }

            Yii::info("{$coin->symbol} payment done", __CLASS__);
            return;
        }

        // --- sendmany path ---------------------------------------------------
        $totalToPay = 0.0;
        $addresses  = [];

        foreach ($users as $user) {
            $amount = round((float) $user->balance, $coin->symbol === 'MBC' ? 2 : 8);
            $totalToPay += $amount;
            $addresses[$user->username] = $amount;

            if ($coin->symbol === 'DCR' && count($addresses) > 990) {
                Yii::info("payment: more than 990 {$coin->symbol} users, limiting to top balances", __CLASS__);
                break;
            }
        }

        if (!$totalToPay) {
            return;
        }

        // Reduce payout if wallet balance is insufficient
        $coef = 1.0;
        if ((float) ($info['balance'] ?? 0) - $txfee < $totalToPay && $coin->symbol !== 'BTC') {
            $msg = "{$coin->symbol}: insufficient funds {$info['balance']} < {$totalToPay}";
            Yii::warning($msg, __CLASS__);
            $this->sendAlert("{$coin->symbol} payout problem detected", $msg);
            $coef       = 0.5;
            $totalToPay = $totalToPay * $coef;
            foreach ($addresses as $k => $v) {
                $addresses[$k] = $v * $coef;
            }
            if ((float) ($info['balance'] ?? 0) - $txfee < $totalToPay) {
                return;
            }
        }

        // BTC cold-wallet distribution
        if ($coin->symbol === 'BTC') {
            $coldWalletTable = Yii::$app->params['coldWalletTable'] ?? [];
            $balance         = (float) ($info['balance'] ?? 0);
            $renter          = (float) Yii::$app->db->createCommand("SELECT SUM(balance) FROM renters")->queryScalar();
            $pie             = $balance - $totalToPay - $renter - 1;

            if ($pie > 0) {
                foreach ($coldWalletTable as $coldWallet => $percent) {
                    $coldAmount = round($pie * $percent, 8);
                    if ($coldAmount < $minPayout) {
                        break;
                    }
                    Yii::info("paying cold wallet {$coldWallet} {$coldAmount}", __CLASS__);
                    $addresses[$coldWallet]  = $coldAmount;
                    $totalToPay             += $coldAmount;
                }
            }
        }

        Yii::info("paying {$totalToPay} {$coin->symbol}", __CLASS__);

        // Create payout records and debit user balances BEFORE broadcasting
        $payouts = [];
        foreach ($users as $user) {
            $user = Accounts::findOne($user->id);
            if (!$user || !isset($addresses[$user->username])) {
                continue;
            }

            $paymentAmount = $this->bitcoinValueToString($addresses[$user->username]);

            $payout             = new Payouts();
            $payout->account_id = $user->id;
            $payout->time       = time();
            $payout->amount     = $paymentAmount;
            $payout->fee        = 0;
            $payout->idcoin     = $coin->id;

            if ($payout->save()) {
                $payouts[$payout->id] = $user->id;
                $user->balance        = $this->bitcoinValueToString((float) $user->balance - (float) $paymentAmount);
                $user->save();
            }
        }

        set_time_limit(120);

        $account = $coin->account ?? '';
        $siteName = defined('YIIMP_SITE_NAME') ? YIIMP_SITE_NAME : 'Yiimp';
        $tx       = $coin->txmessage
            ? $remote->sendmany($account, $addresses, 1, $siteName)
            : $remote->sendmany($account, $addresses);

        $errMsg = null;
        if (!$tx) {
            Yii::warning("sendmany: unable to send {$totalToPay} {$remote->error} " . json_encode($addresses), __CLASS__);
            $errMsg = $remote->error;
        } elseif (!is_string($tx)) {
            Yii::warning("sendmany: result is not a string tx=" . json_encode($tx), __CLASS__);
            $errMsg = json_encode($tx);
        }

        foreach ($payouts as $id => $uid) {
            $payout = Payouts::findOne($id);
            if ($payout) {
                $payout->errmsg = $errMsg;
                if (empty($errMsg)) {
                    $payout->tx        = $tx;
                    $payout->completed = 1;
                }
                $payout->save();
            } else {
                Yii::warning("payout {$id} for {$uid} not found", __CLASS__);
            }
        }

        if (!empty($errMsg)) {
            return;
        }

        Yii::info("{$coin->symbol} payment done", __CLASS__);
        sleep(2);

        // Retry payouts that have no txid (RPC may have timed out during broadcast)
        $retryAddresses = [];
        $retryPayouts   = [];
        $mailMsg        = '';
        $mailWarn       = '';

        foreach ($users as $user) {
            $failed = Payouts::find()
                ->where(['account_id' => $user->id])
                ->andWhere(['or', ['tx' => null], ['tx' => '']])
                ->orderBy('time')
                ->all();

            if (empty($failed)) {
                continue;
            }

            $amountFailed = 0.0;

            if ($coin->symbol === 'CHC') {
                foreach ($failed as $p) {
                    $amountFailed += (float) $p->amount;
                }
                $notice   = "Found buggy payout without tx for {$user->username}: {$amountFailed} {$coin->symbol}";
                Yii::warning($notice, __CLASS__);
                $mailWarn .= "{$notice}\r\n";
                continue;
            }

            foreach ($failed as $p) {
                $amountFailed += (float) $p->amount;
                $p->delete();
            }

            if ($amountFailed <= 0.0) {
                continue;
            }

            Yii::warning("Found failed payment for {$user->username}, {$amountFailed} {$coin->symbol}", __CLASS__);

            if ($coin->rpcencoding === 'DCR') {
                $data = $remote->validateaddress($user->username);
                if (!($data['isvalid'] ?? false)) {
                    Yii::warning("Bad address {$user->username} ({$amountFailed} {$coin->symbol})", __CLASS__);
                    $user->is_locked = 1;
                    $user->save();
                    continue;
                }
            }

            $payout             = new Payouts();
            $payout->account_id = $user->id;
            $payout->time       = time();
            $payout->amount     = $amountFailed;
            $payout->fee        = 0;
            $payout->idcoin     = $coin->id;

            if ($payout->save() && $amountFailed > $minPayout) {
                $retryPayouts[$payout->id]        = $user->id;
                $retryAddresses[$user->username]  = $amountFailed;
                $mailMsg .= "{$amountFailed} {$coin->symbol} to {$user->username} (id {$user->id})\n";
            }
        }

        if (!empty($mailWarn)) {
            $this->sendAlert(
                "{$coin->symbol} payout tx problems to check",
                "{$mailWarn}\r\nCheck your wallet recent transactions — the RPC call may have timed out."
            );
        }

        if (!empty($retryAddresses)) {
            $tx = $coin->txmessage
                ? $remote->sendmany($account, $retryAddresses, 1, "{$siteName} retry")
                : $remote->sendmany($account, $retryAddresses);

            if (empty($tx)) {
                Yii::warning($remote->error, __CLASS__);
                foreach ($retryPayouts as $id => $uid) {
                    $payout = Payouts::findOne($id);
                    if ($payout) {
                        $payout->errmsg = $remote->error;
                        $payout->save();
                    }
                }
                $this->sendAlert("{$coin->symbol} payout problems detected\n{$remote->error}", $mailMsg);
            } else {
                foreach ($retryPayouts as $id => $uid) {
                    $payout = Payouts::findOne($id);
                    if ($payout) {
                        $payout->tx = $tx;
                        $payout->save();
                    } else {
                        Yii::warning("payout retry {$id} for {$uid} not found", __CLASS__);
                    }
                }
                $mailMsg .= "\ntxid {$tx}\n";
                $this->sendAlert("{$coin->symbol} payout problems resolved", $mailMsg);
            }
        }
    }

    /**
     * Delete any pending payouts without a txid and return the refunded amount to the user balance.
     * Ports: BackendUserCancelFailedPayment()
     */
    public function cancelFailedPayment(int $userId): float
    {
        $user = Accounts::findOne($userId);
        if (!$user) {
            return 0.0;
        }

        $failed = Payouts::find()
            ->where(['account_id' => $user->id])
            ->andWhere(['or', ['tx' => null], ['tx' => '']])
            ->all();

        if (empty($failed)) {
            return 0.0;
        }

        $amountFailed = 0.0;
        foreach ($failed as $payout) {
            $amountFailed += (float) $payout->amount;
            $payout->delete();
        }

        $user->balance += $amountFailed;
        $user->save();
        return $amountFailed;
    }

    /**
     * Move matured earnings into user wallet balances.
     * Skips if the balances_locked cache key is set (PaymentsJob is running).
     * Ports: BackendClearEarnings()
     */
    public function clearEarnings(?int $coinId = null): void
    {
        if (Yii::$app->cache->get('balances_locked')) {
            return;
        }

        $paymentFreq = defined('YIIMP_PAYMENTS_FREQ') ? (int) YIIMP_PAYMENTS_FREQ : 14400;
        $delay = defined('YIIMP_ALLOW_EXCHANGE') && YIIMP_ALLOW_EXCHANGE
            ? time() - $paymentFreq
            : time() - (int) ($paymentFreq / 2);

        $query = Earnings::find()
            ->where(['status' => 1])
            ->andWhere(['<', 'mature_time', $delay]);
        if ($coinId !== null) {
            $query->andWhere(['coinid' => $coinId]);
        }

        foreach ($query->each() as $earning) {
            $user = Accounts::findOne((int) $earning->userid);
            if (!$user) {
                $earning->delete();
                continue;
            }

            $coin = Coins::findOne((int) $earning->coinid);
            if (!$coin) {
                $earning->delete();
                continue;
            }

            $earning->status = 2; // cleared
            $earning->price  = $coin->auto_exchange ? $coin->price : 0;
            $earning->save();

            $value = $this->convertAmountForUser($coin, (float) $earning->amount, $user);

            // coinid=6 is BTC; skip if exchange not allowed
            if ($user->coinid == 6 && !(defined('YIIMP_ALLOW_EXCHANGE') && YIIMP_ALLOW_EXCHANGE)) {
                continue;
            }

            $user->balance += $value;
            $user->save();
        }
    }

    /**
     * Dump the full database to a compressed backup file.
     * Ports: BackendDoBackup()
     */
    public function doBackup(): void
    {
        if (!defined('YIIMP_MYSQLDUMP_USER') || !defined('YIIMP_MYSQLDUMP_PASS')) {
            return;
        }

        $d        = date('Y-m-d-H');
        $filename = "/root/backup/yaamp-{$d}.sql";

        if (is_readable('/usr/bin/xz')) {
            $zipTool = 'xz --threads=4';
            $ext     = '.xz';
        } else {
            $zipTool = 'gzip';
            $ext     = '.gz';
        }

        $host = defined('YIIMP_DBHOST') ? YIIMP_DBHOST : '';
        $db   = defined('YIIMP_DBNAME') ? YIIMP_DBNAME : '';
        $user = YIIMP_MYSQLDUMP_USER;
        $pass = YIIMP_MYSQLDUMP_PASS;

        // Dump first, compress in background to minimise DB lock time
        system("mysqldump -h {$host} -u{$user} -p{$pass} --skip-extended-insert {$db} > {$filename}");
        shell_exec("{$zipTool} {$filename} &");
    }

    /**
     * Light housekeeping: trim old block records and orphaned earnings.
     * Ports: BackendQuickClean()
     */
    public function quickClean(): void
    {
        $db    = Yii::$app->db;
        $coins = Coins::find()->where(['installed' => 1])->all();
        $delay = time() - 7 * 24 * 3600;

        foreach ($coins as $coin) {
            $id = $db->createCommand(
                "SELECT id FROM blocks
                 WHERE coin_id=:cid AND time<:delay
                   AND id NOT IN (SELECT blockid FROM earnings WHERE coinid=:cid2)
                 ORDER BY id DESC LIMIT 200, 1",
                [':cid' => $coin->id, ':delay' => $delay, ':cid2' => $coin->id]
            )->queryScalar();

            if ($id) {
                $db->createCommand(
                    "DELETE FROM blocks
                     WHERE coin_id=:cid AND time<:delay
                       AND id NOT IN (SELECT blockid FROM earnings WHERE coinid=:cid2)
                       AND id<:id",
                    [':cid' => $coin->id, ':delay' => $delay, ':cid2' => $coin->id, ':id' => $id]
                )->execute();
            }
        }

        $db->createCommand("DELETE FROM earnings WHERE blockid IN (SELECT id FROM blocks WHERE category='orphan')")->execute();
        $db->createCommand("DELETE FROM earnings WHERE blockid NOT IN (SELECT id FROM blocks)")->execute();
        $db->createCommand("UPDATE blocks SET amount=0 WHERE category='orphan' AND amount>0")->execute();
    }

    /**
     * Full database cleanup: prune market history, drop old records from all time-series tables.
     * Ports: BackendCleanDatabase()
     */
    public function cleanDatabase(): void
    {
        $this->pruneMarketHistory();
        $this->consolidateOldShares();

        $db     = Yii::$app->db;
        $delay60 = time() - 60 * 24 * 3600;
        $delay2  = time() - 2 * 24 * 3600;

        foreach ([
            "DELETE FROM blocks WHERE time<{$delay60}",
            "DELETE FROM hashstats WHERE time<{$delay60}",
            "DELETE FROM payouts WHERE time<{$delay60}",
            "DELETE FROM rentertxs WHERE time<{$delay60}",
            "DELETE FROM shares WHERE time<{$delay60}",
            "DELETE FROM stats WHERE time<{$delay2}",
            "DELETE FROM hashrate WHERE time<{$delay2}",
            "DELETE FROM hashuser WHERE time<{$delay2}",
            "DELETE FROM hashrenter WHERE time<{$delay2}",
            "DELETE FROM balanceuser WHERE time<{$delay2}",
            "DELETE FROM exchange_deposit WHERE send_time<{$delay2}",
            "DELETE FROM shares WHERE time<{$delay2} AND coinid NOT IN (SELECT id FROM coins)",
            "DELETE FROM shares WHERE time<{$delay2} AND blockrewarded > 0",
        ] as $sql) {
            $db->createCommand($sql)->execute();
        }
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Prune market_history: remove records older than 2 months;
     * consolidate records older than 7 days into 1-per-hour buckets.
     * Ports: marketHistoryPrune()
     */
    private function pruneMarketHistory(string $symbol = ''): void
    {
        $db      = Yii::$app->db;
        $delay2M = time() - 61 * 24 * 3600;
        $delay7D = time() - 7 * 24 * 3600;

        $db->createCommand("DELETE FROM market_history WHERE time < :d", [':d' => $delay2M])->execute();

        $symFilter = $symbol !== '' ? " AND C.symbol=:sym" : '';
        $params    = [':delay' => $delay7D];
        if ($symbol !== '') {
            $params[':sym'] = $symbol;
        }

        $rows = $db->createCommand(
            "SELECT idcoin, idmarket,
                    AVG(MH.price) AS price, AVG(MH.price2) AS price2,
                    MAX(MH.balance) AS balance,
                    MIN(MH.id) AS firstid, COUNT(MH.id) AS nbrecords,
                    (MH.time DIV 3600) AS ival
             FROM market_history MH
             INNER JOIN coins C ON C.id = MH.idcoin
             WHERE MH.time < :delay {$symFilter}
             GROUP BY MH.idcoin, MH.idmarket, ival
             HAVING nbrecords > 1",
            $params
        )->queryAll();

        $deleted = 0;
        $updated = 0;

        foreach ($rows as $row) {
            $mktFilter = empty($row['idmarket'])
                ? 'idcoin=:idcoin AND idmarket IS NULL'
                : 'idcoin=:idcoin AND idmarket=' . (int) $row['idmarket'];

            $deleted += (int) $db->createCommand(
                "DELETE FROM market_history
                 WHERE {$mktFilter} AND id != :firstid AND (time DIV 3600) = :ival",
                [':idcoin' => $row['idcoin'], ':firstid' => $row['firstid'], ':ival' => $row['ival']]
            )->execute();

            $updated += (int) $db->createCommand(
                "UPDATE market_history
                 SET time=:t, balance=:bal, price=:price, price2=:price2
                 WHERE id=:firstid",
                [
                    ':t'       => 3600 * $row['ival'],
                    ':bal'     => $row['balance'],
                    ':price'   => $row['price'],
                    ':price2'  => $row['price2'],
                    ':firstid' => $row['firstid'],
                ]
            )->execute();
        }

        if ($deleted) {
            Yii::info("market_history: {$deleted} records pruned, {$updated} updated" . ($symbol ? " ({$symbol})" : ''), __CLASS__);
        }
    }

    /**
     * Merge old share records into aggregated rows to keep the shares table manageable.
     * Ports: consolidateOldShares()
     */
    private function consolidateOldShares(): void
    {
        $db     = Yii::$app->db;
        $delay  = time() - 24 * 3600;
        $t1     = time() - 48 * 3600;

        // Drop stale invalid shares
        $db->createCommand("DELETE FROM shares WHERE time < :d AND valid = 0", [':d' => $delay])->execute();

        $rows = $db->createCommand(
            "SELECT coinid, userid, workerid, algo,
                    AVG(time) AS time, SUM(difficulty) AS difficulty,
                    MAX(share_diff) AS share_diff, MAX(blocknumber) AS max_blocknumber,
                    blockrewarded
             FROM shares
             WHERE pid != 0 AND time < :t AND valid
             GROUP BY coinid, userid, workerid, algo, blockrewarded
             ORDER BY coinid, userid",
            [':t' => $t1]
        )->queryAll();

        $pruned = 0;
        foreach ($rows as $row) {
            $res = $db->createCommand(
                "INSERT INTO shares (coinid, userid, workerid, algo, time, difficulty, share_diff,
                                     blocknumber, blockrewarded, valid, pid)
                 VALUES (:coinid, :userid, :workerid, :algo, :time, :diff, :sdiff,
                         :blocknum, :blockrewarded, 1, 0)",
                [
                    ':coinid'       => $row['coinid'],
                    ':userid'       => $row['userid'],
                    ':workerid'     => $row['workerid'],
                    ':algo'         => $row['algo'],
                    ':time'         => (int) $row['time'],
                    ':diff'         => $row['difficulty'],
                    ':sdiff'        => $row['share_diff'],
                    ':blocknum'     => $row['max_blocknumber'],
                    ':blockrewarded'=> $row['blockrewarded'],
                ]
            )->execute();

            if (!$res) {
                continue;
            }

            if (!is_null($row['blockrewarded'])) {
                $pruned += (int) $db->createCommand(
                    "DELETE FROM shares
                     WHERE blocknumber <= :bn AND blockrewarded = :br
                       AND coinid = :cid AND pid != 0 AND time < :t
                       AND userid = :uid AND workerid = :wid",
                    [
                        ':bn' => $row['max_blocknumber'], ':br' => $row['blockrewarded'],
                        ':cid' => $row['coinid'], ':t' => $t1,
                        ':uid' => $row['userid'], ':wid' => $row['workerid'],
                    ]
                )->execute();
            } else {
                $pruned += (int) $db->createCommand(
                    "DELETE FROM shares
                     WHERE blocknumber <= :bn AND blockrewarded IS NULL
                       AND coinid = :cid AND pid != 0 AND time < :t
                       AND userid = :uid AND workerid = :wid",
                    [
                        ':bn' => $row['max_blocknumber'],
                        ':cid' => $row['coinid'], ':t' => $t1,
                        ':uid' => $row['userid'], ':wid' => $row['workerid'],
                    ]
                )->execute();
            }
        }

        if ($pruned) {
            Yii::info("{$pruned} old share records consolidated", __CLASS__);
        }
    }

    /**
     * Convert a mined amount to the user's preferred coin value.
     * Inline port of YIIMP_convert_amount_user() from web/yaamp/core/functions/yaamp.php.
     */
    private function convertAmountForUser(Coins $coin, float $amount, object $user): float
    {
        if ($coin->id == $user->coinid) {
            return $amount;
        }

        $allowExchange = defined('YIIMP_ALLOW_EXCHANGE') && YIIMP_ALLOW_EXCHANGE;
        $refCoin       = Coins::findOne((int) $user->coinid);

        if ($allowExchange) {
            if (!$refCoin) {
                $refCoin = Coins::find()->where(['symbol' => 'BTC'])->one();
            }
            if (!$refCoin || $refCoin->price <= 0) {
                return 0.0;
            }
            return $amount * ($coin->auto_exchange ? (float) $coin->price : 0.0) / (float) $refCoin->price;
        }

        if ($coin->price && $refCoin && $refCoin->price > 0) {
            return $amount * ($coin->auto_exchange ? (float) $coin->price : 0.0) / (float) $refCoin->price;
        }

        return 0.0;
    }

    /**
     * Format a coin amount to 8 decimal places, matching the legacy bitcoinvaluetoa().
     */
    private function bitcoinValueToString(float $v, int $precision = 8): string
    {
        return Yii::$app->ConversionUtils->bitcoinvaluetoa($v, $precision);
    }

    /**
     * Send an admin email alert.
     * @todo Configure Yii::$app->mailer with real SMTP settings in config/web.php / console.php.
     */
    private function sendAlert(string $subject, string $body): void
    {
        $admin = defined('YIIMP_ADMIN_EMAIL') ? YIIMP_ADMIN_EMAIL : (Yii::$app->params['adminEmail'] ?? '');
        if (empty($admin)) {
            return;
        }

        try {
            Yii::$app->mailer->compose()
                ->setTo($admin)
                ->setSubject($subject)
                ->setTextBody($body)
                ->send();
        } catch (\Throwable $e) {
            Yii::warning("sendAlert failed: {$e->getMessage()}", __CLASS__);
        }
    }
}
