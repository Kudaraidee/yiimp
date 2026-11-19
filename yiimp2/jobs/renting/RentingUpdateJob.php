<?php

namespace app\jobs\renting;

use app\jobs\BaseJob;
use app\services\RentingService;

/**
 * Activate/deactivate hash-power renting jobs based on current algo prices,
 * then process pending share submissions and deduct from renter balances.
 * Ports: BackendRentingUpdate() — called by blocks.sh every 20s.
 */
class RentingUpdateJob extends BaseJob
{
    public int $intervalSeconds = 60;

    protected function perform(): void
    {
        (new RentingService())->updateRenting();
    }
}
