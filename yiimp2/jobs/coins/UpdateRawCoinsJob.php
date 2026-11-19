<?php

namespace app\jobs\coins;

use Yii;
use app\jobs\BaseJob;

/**
 * Refresh market listings from all active exchanges, then update the BTC/USD
 * price from Bitstamp into mining.usdbtc.
 * Ports: updateRawcoins() + bitstamp_btcusd() — main.sh cron state 0.
 */
class UpdateRawCoinsJob extends BaseJob
{
    public int $intervalSeconds = 600;

    protected function perform(): void
    {
        $svc = new \app\services\CoinService();
        $svc->updateRawCoins();
        $svc->updateBtcUsdPrice();
    }
}
