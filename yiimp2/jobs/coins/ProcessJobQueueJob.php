<?php

namespace app\jobs\coins;

use app\jobs\BaseJob;
use app\services\CoinService;

/**
 * Snapshot MySQL SHOW PROCESSLIST into the connections table and prune stale rows.
 * Ports: BackendProcessList() — blocks.sh every 20s.
 */
class ProcessJobQueueJob extends BaseJob
{
    public int $intervalSeconds = 30;

    protected function perform(): void
    {
        (new CoinService())->processJobQueue();
    }
}
