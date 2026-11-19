<?php

namespace app\jobs\system;

use app\jobs\BaseJob;

/**
 * Clean benchmark records, resolve chip names, and sync the bench_chips table.
 * Ports: BenchUpdateChips() — main.sh state 7.
 */
class BenchUpdateJob extends BaseJob
{
    public int $intervalSeconds = 900;

    protected function perform(): void
    {
        (new \app\services\BenchService())->updateChips();
    }
}
