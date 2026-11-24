<?php

namespace app\jobs\system;

use app\jobs\BaseJob;
use app\services\CoinService;

/**
 * Snapshot MySQL SHOW PROCESSLIST into the connections table and prune stale
 * rows (admin "Connections" page).
 * Ports: BackendProcessList() — blocks.sh every 20s.
 */
class DbConnectionsJob extends BaseJob
{
    public int $intervalSeconds = 30;

    protected function perform(): void
    {
        (new CoinService())->snapshotDbConnections();
    }
}
