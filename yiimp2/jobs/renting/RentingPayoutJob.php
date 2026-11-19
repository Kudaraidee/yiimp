<?php

namespace app\jobs\renting;

use Yii;
use app\jobs\BaseJob;
use app\services\RentingService;

/**
 * Convert accumulated renting-job share earnings into synthetic block records
 * and distribute proportional earnings to pool miners.
 * Skips if PaymentsJob holds the balances lock.
 * Ports: BackendRentingPayout() — called by loop2.sh every 5 min (when not locked).
 */
class RentingPayoutJob extends BaseJob
{
    public int $intervalSeconds = 300;

    protected function perform(): void
    {
        if (!defined('YIIMP_RENTAL') || !YIIMP_RENTAL) {
            return;
        }
        if (Yii::$app->cache->get('balances_locked')) {
            return;
        }
        (new RentingService())->doRentingPayout();
    }
}
