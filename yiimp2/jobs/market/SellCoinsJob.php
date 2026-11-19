<?php

namespace app\jobs\market;

use app\jobs\BaseJob;

/**
 * Send excess coin wallet balances to their best market deposit address.
 * Ports: TradingSellCoins() — main.sh state 5.
 */
class SellCoinsJob extends BaseJob
{
    public int $intervalSeconds = 600;

    protected function perform(): void
    {
        (new \app\services\MarketService())->sellCoins();
    }
}
