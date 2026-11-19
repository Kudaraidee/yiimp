<?php

namespace app\jobs\stats;

use app\jobs\BaseJob;

/**
 * Snapshot per-user and per-renting-job hashrates, and user balance history.
 * Ports: BackendStatsUpdate2() — loop2.sh every 5 min.
 */
class UpdateExtendedStatsJob extends BaseJob
{
    public int $intervalSeconds = 300;

    protected function perform(): void
    {
        (new \app\services\StatsService())->updateExtendedStats();
    }
}
