<?php

namespace app\jobs\market;

use app\jobs\BaseJob;
use app\services\MarketService;

/**
 * Execute automated trading on Binance, KuCoin, Yobit, Nestex, and Nonkyc.
 * Ports: doBinanceTrading() + doKuCoinTrading() + doYobitTrading() + others
 * main.sh state 2 (prod only).
 */
class TradingJob extends BaseJob
{
    public int $intervalSeconds = 600;

    protected function perform(): void
    {
        if (!defined('YIIMP_PRODUCTION') || !YIIMP_PRODUCTION) {
            return;
        }
        (new MarketService())->doTrading();
    }
}
