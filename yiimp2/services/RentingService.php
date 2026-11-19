<?php

namespace app\services;

use Yii;
use app\models\Accounts;
use app\models\Blocks;
use app\models\Coins;
use app\models\Earnings;
use app\models\Jobs;
use app\models\JobSubmits;
use app\models\Renters;
use app\models\RenterTxs;
use app\components\rpc\WalletRPC;

/**
 * RentingService — manages the pool's hash-power renting-out system.
 *
 * In this system external renters SEND hash power TO the pool, paying the
 * pool for access to its stratum servers.  This is distinct from NiceHash
 * (where the pool BUYS hash power from NiceHash for its own mining) which
 * is handled by NicehashService.
 *
 * Ported from: web/yaamp/core/backend/renting.php
 *   BackendRentingUpdate()  → updateRenting()
 *   BackendRentingPayout()  → doRentingPayout()
 *   BackendUpdateDeposit()  → updateDeposit()
 */
class RentingService
{
    /**
     * Activate/deactivate renting jobs based on current algo prices, then
     * process pending share submissions (job submits) and deduct from renter balances.
     * Ports: BackendRentingUpdate()
     */
    public function updateRenting(): void
    {
        $db   = Yii::$app->db;
        $util = Yii::$app->YiimpUtils;

        if (!defined('YIIMP_RENTAL') || !YIIMP_RENTAL) {
            $db->createCommand("UPDATE jobs SET active=false, ready=false")->execute();
            return;
        }

        $db->createCommand("UPDATE jobs SET active=false WHERE NOT ready")->execute();

        foreach ($util->get_algos() as $algo) {
            $rent = (float) $db->createCommand(
                "SELECT rent FROM hashrate WHERE algo=:algo ORDER BY time DESC LIMIT 1",
                [':algo' => $algo]
            )->queryScalar();

            $db->createCommand(
                "UPDATE jobs SET active=true WHERE ready AND price>:rent AND algo=:algo",
                [':rent' => $rent, ':algo' => $algo]
            )->execute();
            $db->createCommand(
                "UPDATE jobs SET active=false WHERE active AND price<:rent AND algo=:algo",
                [':rent' => $rent, ':algo' => $algo]
            )->execute();
        }

        $feesRenting = defined('YIIMP_FEES_RENTING') ? (float) YIIMP_FEES_RENTING : 2.0;

        $submits = JobSubmits::find()->where(['status' => 0])->all();
        foreach ($submits as $submit) {
            $rent = (float) $db->createCommand(
                "SELECT rent FROM hashrate WHERE algo=:algo ORDER BY time DESC LIMIT 1",
                [':algo' => $submit->algo]
            )->queryScalar();

            // Convert share difficulty to BTC amount at current rental price
            $amount = $rent * $submit->difficulty / 20116.56761169;
            $factor = $util->algo_mBTC_factor($submit->algo); // 1000 for SHA-256
            $amount /= $factor;

            $submit->amount = $amount - $amount * $feesRenting / 100;
            $submit->status = 1;
            $submit->save();

            $job = Jobs::findOne((int) $submit->jobid);
            if (!$job) {
                $submit->delete();
                continue;
            }

            $renter = Renters::findOne((int) $job->renterid);
            if (!$renter) {
                $job->delete();
                $submit->delete();
                continue;
            }

            $renter->balance -= $amount;
            $renter->spent   += $amount;

            if ((float) $renter->balance <= 0.000_010_00) {
                Yii::info("resetting balance to 0: renter {$renter->id} addr {$renter->address}", __CLASS__);
                $renter->balance = 0;
                $db->createCommand(
                    "UPDATE jobs SET active=false, ready=false WHERE renterid=:rid",
                    [':rid' => $renter->id]
                )->execute();
            }

            $renter->updated = time();
            $renter->save();
        }
    }

    /**
     * Convert accumulated job-submit earnings into synthetic block records and
     * distribute proportional earnings to pool miners.
     * Ports: BackendRentingPayout()
     */
    public function doRentingPayout(): void
    {
        $db          = Yii::$app->db;
        $util        = Yii::$app->YiimpUtils;
        $feesRenting = defined('YIIMP_FEES_RENTING') ? (float) YIIMP_FEES_RENTING : 2.0;
        $totalCleared = 0.0;

        foreach ($util->get_algos() as $algo) {
            $delay = time() - 5 * 60;

            // Clean up old processed submits
            $db->createCommand(
                "DELETE FROM jobsubmits WHERE status=2 AND algo=:algo AND time<:d",
                [':algo' => $algo, ':d' => $delay]
            )->execute();

            $amount = (float) $db->createCommand(
                "SELECT SUM(amount) FROM jobsubmits WHERE status=1 AND algo=:algo",
                [':algo' => $algo]
            )->queryScalar();

            if ($amount < 0.000_020_00) {
                continue;
            }

            // Mark submits as being processed
            $db->createCommand(
                "UPDATE jobsubmits SET status=2 WHERE status=1 AND algo=:algo",
                [':algo' => $algo]
            )->execute();

            $totalCleared += $amount;

            // Create a synthetic block record (coin_id=0 = BTC-denominated rental income)
            $block           = new Blocks();
            $block->coin_id  = 0;
            $block->time     = time();
            $block->amount   = $amount;
            $block->price    = 1;
            $block->algo     = $algo;
            $block->category = 'generate';
            $block->save();

            $totalHashPower = (float) $db->createCommand(
                "SELECT SUM(difficulty) FROM shares WHERE valid AND algo=:algo",
                [':algo' => $algo]
            )->queryScalar();

            if (!$totalHashPower) {
                continue;
            }

            $sharesByUser = $db->createCommand(
                "SELECT userid, SUM(difficulty) AS total FROM shares WHERE valid AND algo=:algo GROUP BY userid",
                [':algo' => $algo]
            )->queryAll();

            foreach ($sharesByUser as $item) {
                $hashPower = (float) $item['total'];
                if (!$hashPower) {
                    continue;
                }

                $account = Accounts::findOne((int) $item['userid']);
                if (!$account) {
                    continue;
                }

                $earning             = new Earnings();
                $earning->userid     = $account->id;
                $earning->coinid     = 0;
                $earning->blockid    = $block->id;
                $earning->create_time = time();
                $earning->price      = 1;
                $earning->status     = 2; // immediately cleared

                $earning->amount = $amount * $hashPower / $totalHashPower;
                if (!$account->no_fees) {
                    $pct = $this->miningFeePercent($algo);
                    $earning->amount -= $earning->amount * $pct / 100.0;
                }
                if (!empty($account->donation)) {
                    $earning->amount -= $earning->amount * (float) $account->donation / 100.0;
                    if ($earning->amount <= 0) {
                        continue;
                    }
                }

                $earning->save();

                // Credit user balance (rental earnings are denominated in BTC, price2 = BTC price)
                $refCoin = Coins::findOne((int) $account->coinid);
                $price2  = ($refCoin && $refCoin->price2) ? (float) $refCoin->price2 : 1.0;
                $value   = $earning->amount / $price2;

                $account->last_earning = time();
                $account->balance     += $value;
                $account->save();
            }

            // Drop old shares after payout
            $db->createCommand(
                "DELETE FROM shares WHERE algo=:algo AND time<:d",
                [':algo' => $algo, ':d' => $delay]
            )->execute();
        }

        if ($totalCleared > 0) {
            Yii::info(sprintf('total cleared from rental %.8f BTC', $totalCleared), __CLASS__);
        }
    }

    /**
     * Process BTC deposits from renters' wallet accounts and handle pending withdrawals.
     * Ports: BackendUpdateDeposit()
     *
     * @todo Verify the listaccounts / move / listtransactions calls against current Bitcoin RPC —
     *       account-based wallet API was removed in Bitcoin Core 0.17 (use descriptors instead).
     */
    public function updateDeposit(): void
    {
        $db  = Yii::$app->db;
        $btc = Coins::find()->where(['symbol' => 'BTC'])->one();
        if (!$btc) {
            return;
        }

        $remote = new WalletRPC($btc);
        $info   = $remote->getinfo();
        if (!$info || !isset($info['blocks'])) {
            return;
        }

        $hash  = $remote->getblockhash((int) $info['blocks']);
        $block = $remote->getblock($hash);
        if (!$block || !isset($block['time'])) {
            return;
        }
        if ((int) $block['time'] + 30 * 60 < time()) {
            return;
        }

        $txFeeWd = defined('YIIMP_TXFEE_RENTING_WD') ? (float) YIIMP_TXFEE_RENTING_WD : 0.002;

        // Confirmed deposits
        $accounts = $remote->listaccounts(1);
        foreach ((array) $accounts as $accountName => $amount) {
            if ($amount == 0) {
                continue;
            }
            if (!preg_match('/renter-prod-([0-9]+)/', $accountName, $m)) {
                continue;
            }

            $renter = Renters::findOne((int) $m[1]);
            if (!$renter) {
                continue;
            }

            $ts    = $remote->listtransactions($this->renterAccount($renter), 1);
            if (!$ts || !isset($ts[0])) {
                continue;
            }

            $moved = $remote->move($this->renterAccount($renter), '', $amount);
            if (!$moved) {
                continue;
            }

            Yii::info("deposit renter {$renter->id} {$renter->address}: {$amount} BTC", __CLASS__);

            $tx           = new RenterTxs();
            $tx->renterid = $renter->id;
            $tx->time     = time();
            $tx->amount   = $amount;
            $tx->type     = 'deposit';
            $tx->tx       = $ts[0]['txid'] ?? '';
            $tx->save();

            $renter->unconfirmed = 0;
            $renter->balance    += $amount;
            $renter->updated     = time();
            $renter->save();
        }

        // Unconfirmed deposits
        $pendingAccounts = $remote->listaccounts(0);
        foreach ((array) $pendingAccounts as $accountName => $amount) {
            if ($amount == 0) {
                continue;
            }
            if (!preg_match('/renter-prod-([0-9]+)/', $accountName, $m)) {
                continue;
            }
            $renter = Renters::findOne((int) $m[1]);
            if (!$renter) {
                continue;
            }
            Yii::info("unconfirmed renter {$renter->id}: {$amount} BTC", __CLASS__);
            $renter->unconfirmed = $amount;
            $renter->updated     = time();
            $renter->save();
        }

        // Process scheduled withdrawals
        $pendingWithdrawals = RenterTxs::find()
            ->where(['type' => 'withdraw', 'tx' => 'scheduled'])
            ->all();

        foreach ($pendingWithdrawals as $tx) {
            $renter = Renters::findOne((int) $tx->renterid);
            if (!$renter) {
                continue;
            }

            $txAmount = (float) min($tx->amount, (float) $renter->balance - $txFeeWd);
            if ($txAmount < $txFeeWd * 2) {
                $tx->tx = 'failed';
                $tx->save();
                continue;
            }

            $tx->amount = Yii::$app->ConversionUtils->bitcoinvaluetoa($txAmount);
            $sentTx     = $remote->sendtoaddress($tx->address, round($txAmount, 8));

            if (!$sentTx) {
                $tx->tx = 'failed';
                $tx->save();
                continue;
            }

            $tx->tx = $sentTx;
            $tx->save();

            $newBalance = max(0.0, (float) $renter->balance - $txAmount - $txFeeWd);
            $db->createCommand(
                "UPDATE renters SET balance=:bal WHERE id=:id",
                [':bal' => $newBalance, ':id' => $renter->id]
            )->execute();

            if ($newBalance <= 0.0001) {
                $db->createCommand(
                    "UPDATE jobs SET active=false, ready=false WHERE renterid=:rid",
                    [':rid' => $renter->id]
                )->execute();
            }
        }
    }

    // -------------------------------------------------------------------------

    private function renterAccount(Renters $renter): string
    {
        return "renter-prod-{$renter->id}";
    }

    private function miningFeePercent(string $algo): float
    {
        return defined('YIIMP_FEES_MINING') ? (float) YIIMP_FEES_MINING : 0.5;
    }
}
