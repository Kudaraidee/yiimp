<?php

namespace app\jobs\earnings;

use Yii;
use app\jobs\BaseJob;

/**
 * Execute the full payment sequence: backup, send coins to users, clean database.
 * Runs only in production and only when YIIMP_PAYMENTS_FREQ seconds have elapsed
 * since the last payout. Acquires 'balances_locked' for the duration.
 *
 * @todo implement — calls PaymentService::doPayments()
 * Ports: BackendDoBackup() + BackendPayments() + BackendCleanDatabase() from payment.php
 * Triggered from main.sh after the 8-state cycle when payment time is due.
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

        $freq       = defined('YIIMP_PAYMENTS_FREQ') ? (int) YIIMP_PAYMENTS_FREQ : 14400;
        $lastPayout = (int) (Yii::$app->cache->get('last_payout_time') ?: 0);

        if ($lastPayout + $freq > time()) {
            return;
        }

        Yii::$app->cache->set('balances_locked', true, 300);
        try {
            $svc = new \app\services\PaymentService();
            $svc->doBackup();
            $svc->doPayments();
            $svc->cleanDatabase();
            Yii::$app->cache->set('last_payout_time', time(), $freq * 2);
        } finally {
            Yii::$app->cache->delete('balances_locked');
        }
    }
}
