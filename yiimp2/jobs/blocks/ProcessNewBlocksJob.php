<?php

namespace app\jobs\blocks;

use app\jobs\BaseJob;
use app\services\BlockService;

/**
 * Process blocks in 'new' state notified by the stratum server.
 * Validates each block via wallet RPC and transitions it to 'immature' or 'orphan'.
 * Ports: BackendBlockFind1() — called by blocks.sh every 20s.
 */
class ProcessNewBlocksJob extends BaseJob
{
    public int $intervalSeconds = 15;

    protected function perform(): void
    {
        (new BlockService())->processNewBlocks();
    }
}
