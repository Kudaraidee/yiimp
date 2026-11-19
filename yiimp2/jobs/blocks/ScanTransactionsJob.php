<?php

namespace app\jobs\blocks;

use app\jobs\BaseJob;
use app\services\BlockService;

/**
 * Scan all enabled coins for newly mined blocks via listsinceblock, then
 * update aggregate pool balance fields on each coin record.
 * Ports: BackendBlockFind2() + BackendUpdatePoolBalances() — main.sh state 6 (~12 min).
 */
class ScanTransactionsJob extends BaseJob
{
    public int $intervalSeconds = 600;

    protected function perform(): void
    {
        $svc = new BlockService();
        $svc->scanTransactions();
        $svc->updatePoolBalances();
    }
}
