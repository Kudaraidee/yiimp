<?php

namespace app\jobs\nicehash;

use app\jobs\BaseJob;
use app\services\NicehashService;

/**
 * Sync NiceHash global pricing data and auto-manage the pool's NiceHash orders.
 * Only active when YIIMP_USE_NICEHASH_API is true.
 *
 * @note NicehashService uses the v1 API — verify endpoint compatibility before enabling.
 * Ports: BackendUpdateServices() from web/yaamp/core/backend/services.php
 */
class NicehashSyncJob extends BaseJob
{
    public int $intervalSeconds = 120;

    protected function perform(): void
    {
        (new NicehashService())->syncAll();
    }
}
