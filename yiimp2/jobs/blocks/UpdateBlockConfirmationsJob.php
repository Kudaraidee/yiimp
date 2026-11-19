<?php

namespace app\jobs\blocks;

use app\jobs\BaseJob;
use app\services\BlockService;

/**
 * Refresh confirmation counts on all immature/stake/orphan blocks.
 * Matures earnings when blocks are fully confirmed; detects orphans.
 * Ports: BackendBlocksUpdate() — called by both blocks.sh (20s) and main.sh state 4 (90s).
 */
class UpdateBlockConfirmationsJob extends BaseJob
{
    public int $intervalSeconds = 30;

    protected function perform(): void
    {
        (new BlockService())->updateBlockConfirmations();
    }
}
