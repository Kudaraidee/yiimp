<?php

namespace app\jobs\renting;

use app\jobs\BaseJob;
use app\services\RentingService;

/**
 * Scan the BTC wallet for renter deposits and process pending withdrawals.
 * Ports: BackendUpdateDeposit() — loop2.sh (was commented out upstream; ported but optional).
 */
class ProcessRentingJobsJob extends BaseJob
{
    public int $intervalSeconds = 300;

    protected function perform(): void
    {
        if (!defined('YIIMP_RENTAL') || !YIIMP_RENTAL) {
            return;
        }
        (new RentingService())->updateDeposit();
    }
}
