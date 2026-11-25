<?php

namespace app\commands;

use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use app\models\Coins;
use app\models\Payouts;
use app\models\Accounts;
use app\components\rpc\WalletRPC;
use app\services\PaymentService;
use app\exchanges\ExchangeFactory;

/**
 * Payout verification and repair.
 *
 * Usage:
 *   php yii payout/check <symbol> [fixit]   — compare wallet txs with DB payouts
 *   php yii payout/coinswaps                — find earnings/payouts with mismatched coins
 *   php yii payout/confirmations <symbol>   — list last 72h payouts with confirmation counts
 *   php yii payout/redotx <txid>            — redo payment from a bad fork (YIIMP_CLI_ALLOW_TXS required)
 *   php yii payout/usdt                     — one-shot SafeTrade USDT quote payout pass
 *   php yii payout/clear [SYM]              — clear matured earnings into miner quote wallets
 *   php yii payout/usdt-cycle               — clear → sell → aggregate payout (one-shot)
 */
class PayoutController extends Controller
{
    public bool $dryRun = false;
    public string $symbols = 'NEB';

    public function options($actionID): array
    {
        if ($actionID === 'usdt-cycle') {
            return array_merge(parent::options($actionID), ['dryRun', 'symbols']);
        }
        return parent::options($actionID);
    }

    /** One-shot USDT payout pass. Does not start queue workers. */
    public function actionUsdt(): int
    {
        (new PaymentService())->payUsdtQuotePayouts();
        $this->stdout("USDT quote payout pass complete\n");
        return ExitCode::OK;
    }

    /** One-shot clear of matured earnings into miner quote wallets. */
    public function actionClear(string $symbol = ''): int
    {
        $coinId = null;
        if ($symbol !== '') {
            $coin = Coins::find()->where(['symbol' => strtoupper(trim($symbol))])->one();
            if (!$coin) {
                $this->stderr("coin {$symbol} not found!\n");
                return ExitCode::UNSPECIFIED_ERROR;
            }
            $coinId = (int) $coin->id;
        }

        (new PaymentService())->clearEarnings($coinId);
        $this->stdout("earnings clear pass complete\n");
        return ExitCode::OK;
    }

    /**
     * Controlled one-shot USDT cycle: clear earnings, sell configured coins on
     * SafeTrade, then aggregate quote wallets into one central withdrawal.
     */
    public function actionUsdtCycle(): int
    {
        $settings = Yii::$app->settings;
        $raw      = trim($this->symbols) !== ''
            ? $this->symbols
            : (string) $settings->exchangeGet('safetrade', 'autosell_symbols', 'NEB');
        $symbols  = array_values(array_filter(array_unique(array_map(
            'strtoupper',
            array_map('trim', explode(',', $raw))
        ))));
        $address  = trim((string) $settings->exchangeGet('safetrade', 'withdraw_usdt_address', ''));
        $gate     = (bool) $settings->exchangeGet('safetrade', 'usdt_payout_enabled', false);

        $freq  = defined('YIIMP_PAYMENTS_FREQ') ? (int) YIIMP_PAYMENTS_FREQ : 14400;
        $delay = (defined('YIIMP_ALLOW_EXCHANGE') && YIIMP_ALLOW_EXCHANGE)
            ? time() - $freq
            : time() - (int) ($freq / 2);
        $db = Yii::$app->db;

        $quoteSymbol = Yii::$app->YiimpUtils->payoutQuoteSymbol();
        $quoteCoin   = Coins::find()->where(['symbol' => $quoteSymbol])->one();
        $quoteCoinId = $quoteCoin ? (int) $quoteCoin->id : 0;

        if ($this->dryRun) {
            $this->stdout("dry run; no changes\n");
            $this->stdout('payout gate: ' . ($gate ? 'on' : 'off') . "\n");
            $this->stdout('central wallet: ' . ($address !== '' ? $address : '(unset)') . "\n");
            $this->stdout('autosell: ' . ($symbols ? implode(',', $symbols) : '(none)') . "\n");
            $this->stdout("matured earnings:\n");
            $earnings = $db->createCommand(
                'SELECT C.symbol, COUNT(*) AS c, SUM(E.amount) AS amt
                 FROM earnings E JOIN coins C ON C.id = E.coinid
                 WHERE E.status = 1 AND E.mature_time < :d
                 GROUP BY E.coinid',
                [':d' => $delay]
            )->queryAll();
            if ($earnings) {
                foreach ($earnings as $row) {
                    $this->stdout(sprintf("  %s count=%s amount=%s\n", $row['symbol'], $row['c'], $row['amt']));
                }
            } else {
                $this->stdout("  (none)\n");
            }
            $wallet = $db->createCommand(
                'SELECT COUNT(*) AS c, COALESCE(SUM(balance), 0) AS total
                 FROM accounts WHERE coinid = :qid AND is_locked = 0 AND balance > 0',
                [':qid' => $quoteCoinId]
            )->queryOne();
            $this->stdout(sprintf("quote wallets: count=%s total=%s USDT\n", $wallet['c'], $wallet['total']));
            return ExitCode::OK;
        }

        if (!$gate) {
            $this->stderr("safetrade usdt payout gate is off; enable it first\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }
        if (!preg_match('/^0x[0-9a-fA-F]{40}$/', $address)) {
            $this->stderr("safetrade withdraw_usdt_address is not a BEP20 address\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $service = new PaymentService();
        $service->clearEarnings();
        $this->stdout("earnings cleared\n");

        $original = [];
        foreach ($symbols as $symbol) {
            $coin = Coins::find()->where(['symbol' => $symbol])->one();
            if (!$coin) {
                $this->stderr("coin {$symbol} not found, skipped\n");
                continue;
            }
            if ((int) $coin->dontsell !== 0) {
                $original[(int) $coin->id] = (int) $coin->dontsell;
                $coin->dontsell             = 0;
                $coin->save(false);
            }
        }

        try {
            if ($original) {
                ExchangeFactory::make('safetrade')->trade();
                $this->stdout("safetrade trade pass complete\n");
            } else {
                $this->stdout("no autosell coin flags changed\n");
            }
        } finally {
            foreach ($original as $coinId => $dontsell) {
                $coin = Coins::findOne($coinId);
                if ($coin) {
                    $coin->dontsell = $dontsell;
                    $coin->save(false);
                }
            }
        }

        $service->payUsdtQuotePayouts();
        $this->stdout("USDT quote payout pass complete\n");
        return ExitCode::OK;
    }

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
