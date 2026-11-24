<?php

namespace app\jobs\blocks;

use Yii;
use app\jobs\BaseJob;
use app\services\BlockService;

/**
 * Process blocks in 'new' state notified by the stratum server.
 * Validates each block via wallet RPC and transitions it to 'immature' or 'orphan'.
 * Ports: BackendBlockFind1() — called by blocks.sh every 20s.
 *
 * Stall reset (legacy cronjob/runBlocks): if the last completed run is older than
 * STALL_SECONDS (workers were down, queue stuck, job paused, or processing keeps
 * failing), all renting jobs are deactivated once per stall; RentingUpdateJob
 * re-activates them. This runs inside the job itself, so an outage is acted on
 * when processing resumes, not while it lasts.
 */
class ProcessNewBlocksJob extends BaseJob
{
    public int $intervalSeconds = 15;

    private const STALL_SECONDS     = 300;
    private const LAST_COMPLETE_KEY = 'blocks_last_complete';
    private const STALLED_KEY       = 'blocks_stalled';

    protected function perform(): void
    {
        $cache = Yii::$app->cache;
        $last  = $cache->get(self::LAST_COMPLETE_KEY);

        if ($last === false) {
            // No history (fresh cache, memcache restart): nothing proves a stall
            $cache->set(self::LAST_COMPLETE_KEY, time());
        } elseif ((int) $last + self::STALL_SECONDS < time() && !$cache->get(self::STALLED_KEY)) {
            // Once per stall: without the flag, a pipeline that keeps throwing
            // would deactivate every 15s while RentingUpdateJob re-activates
            $n = Yii::$app->db->createCommand('UPDATE jobs SET active=false WHERE active')->execute();
            $cache->set(self::STALLED_KEY, 1);
            Yii::warning(sprintf(
                'block pipeline stalled (last complete run %s), %d renting job(s) deactivated',
                date('Y-m-d H:i:s', (int) $last), $n
            ), 'queue');
        }

        (new BlockService())->processNewBlocks();

        $cache->set(self::LAST_COMPLETE_KEY, time());
        $cache->delete(self::STALLED_KEY);
    }
}
