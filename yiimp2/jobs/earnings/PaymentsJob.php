<?php

namespace app\jobs\earnings;

use Yii;
use app\jobs\BaseJob;

/**
 * Execute the full payment sequence: backup, send coins to users, clean database.
 * Runs only in production and only when YIIMP_PAYMENTS_FREQ seconds have elapsed
 * since the last payout. Acquires 'balances_locked' for the duration.
 *
 * The last payout time lives in the database (mining.last_payout, as in the
 * legacy cronjob), not in the cache: a lost cache (container recreate, cache
 * flush) must not trigger an early payout run.
 *
 * Ports: BackendDoBackup() + BackendPayments() + BackendCleanDatabase() from payment.php
 */
class PaymentsJob extends BaseJob
{
    /** Check frequently; actual payment only runs when frequency condition is met. */
    public int $intervalSeconds = 60;

    protected function perform(): void
    {
        if (!defined('YIIMP_PRODUCTION') || !YIIMP_PRODUCTION) {
            return;
        }

        $freq = defined('YIIMP_PAYMENTS_FREQ') ? (int) YIIMP_PAYMENTS_FREQ : 14400;
        $now  = time();

        // Claim the payout slot atomically; like the legacy cronjob the slot is
        // taken before paying, so a failing run waits for the next period
        // instead of retrying every minute.
        $claimed = Yii::$app->db->createCommand(
            'UPDATE mining SET last_payout = :now
              WHERE IFNULL(last_payout, 0) + :freq <= :now LIMIT 1',
            [':now' => $now, ':freq' => $freq]
        )->execute();

        if (!$claimed) {
            return;
        }

        Yii::$app->cache->set('balances_locked', true, 300);
        try {
            $svc = new \app\services\PaymentService();
            $svc->doBackup();
            $svc->doPayments();
            $svc->cleanDatabase();
        } finally {
            Yii::$app->cache->delete('balances_locked');
        }
    }
}
