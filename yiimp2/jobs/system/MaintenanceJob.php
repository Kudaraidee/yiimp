<?php

namespace app\jobs\system;

use app\jobs\BaseJob;

/**
 * Prune old unreferenced blocks, orphan earnings, and zero orphan block amounts.
 * Ports: BackendQuickClean() — hourly housekeeping.
 */
class MaintenanceJob extends BaseJob
{
    public int $intervalSeconds = 3600;

    protected function perform(): void
    {
        (new \app\services\PaymentService())->quickClean();
    }
}
