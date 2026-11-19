<?php

namespace app\jobs\market;

use app\jobs\BaseJob;
use app\services\MarketService;

/**
 * Sync BTC and altcoin balances from Bitstamp, CexIO, Kraken, and Poloniex.
 * Ports: getBitstampBalances() + getCexIoBalances() + doKrakenTrading() + doPoloniexTrading()
 * main.sh state 1 (prod only).
 */
class ExchangeBalancesJob extends BaseJob
{
    public int $intervalSeconds = 600;

    protected function perform(): void
    {
        if (!defined('YIIMP_PRODUCTION') || !YIIMP_PRODUCTION) {
            return;
        }
        (new MarketService())->updateExchangeBalances();
    }
}
