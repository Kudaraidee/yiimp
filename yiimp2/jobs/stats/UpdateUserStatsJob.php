<?php

namespace app\jobs\stats;

use app\jobs\BaseJob;

/**
 * Resolve unassigned wallet addresses to their coin and process coin-switch requests.
 * Ports: BackendUsersUpdate() — loop2.sh every 60s.
 */
class UpdateUserStatsJob extends BaseJob
{
    public int $intervalSeconds = 60;

    protected function perform(): void
    {
        (new \app\services\UserService())->updateUserStats();
    }
}
