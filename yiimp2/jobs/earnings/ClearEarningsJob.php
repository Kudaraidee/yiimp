<?php

namespace app\jobs\earnings;

use Yii;
use app\jobs\BaseJob;

/**
 * Move matured earnings from the earnings table into user wallet balances.
 * Skips if PaymentsJob holds the balances lock.
 *
 * @todo implement — calls PaymentService::clearEarnings()
 * Ports: BackendClearEarnings() from web/yaamp/core/backend/payment.php
 * Called by blocks.sh every 20s (when not locked).
 */
class ClearEarningsJob extends BaseJob
{
    public int $intervalSeconds = 60;

    protected function perform(): void
    {
        if (Yii::$app->cache->get('balances_locked')) {
            return;
        }
        (new \app\services\PaymentService())->clearEarnings();
    }
}
