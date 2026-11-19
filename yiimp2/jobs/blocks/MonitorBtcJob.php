<?php

namespace app\jobs\blocks;

use app\jobs\BaseJob;
use app\services\BlockService;

/**
 * Monitor the BTC wallet for outgoing transactions and notify the admin by email.
 * Ports: MonitorBTC() — called by loop2.sh every 60s.
 */
class MonitorBtcJob extends BaseJob
{
    public int $intervalSeconds = 60;

    protected function perform(): void
    {
        (new BlockService())->monitorBtcWithdrawals();
    }
}
