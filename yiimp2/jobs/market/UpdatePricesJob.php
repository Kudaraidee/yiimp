<?php

namespace app\jobs\market;

use app\jobs\BaseJob;

/**
 * Sync market prices from all active exchanges, then snapshot price/balance history.
 * Ports: BackendPricesUpdate() + BackendWatchMarkets() — main.sh state 3.
 */
class UpdatePricesJob extends BaseJob
{
    public int $intervalSeconds = 600;

    protected function perform(): void
    {
        $svc = new \app\services\MarketService();
        $svc->updatePrices();
        $svc->watchMarkets();
    }
}
