<?php

namespace app\jobs\stats;

use app\jobs\BaseJob;

/**
 * Snapshot pool-wide hashrate, pricing, earnings, and balance data.
 * Ports: BackendStatsUpdate() — loop2.sh every 60s.
 */
class UpdatePoolStatsJob extends BaseJob
{
    public int $intervalSeconds = 60;

    protected function perform(): void
    {
        (new \app\services\StatsService())->updatePoolStats();
    }
}
