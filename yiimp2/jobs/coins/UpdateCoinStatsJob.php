<?php

namespace app\jobs\coins;

use app\jobs\BaseJob;

/**
 * Poll every installed coin's wallet daemon: update difficulty, block height,
 * reward, connections, and profitability index.
 * Ports: BackendCoinsUpdate() — loop2.sh every 60s.
 */
class UpdateCoinStatsJob extends BaseJob
{
    public int $intervalSeconds = 60;

    protected function perform(): void
    {
        (new \app\services\CoinService())->updateCoinStats();
    }
}
