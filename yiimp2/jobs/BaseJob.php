<?php

namespace app\jobs;

use Yii;
use yii\queue\JobInterface;
use app\components\JobRegistry;

/**
 * Abstract base for all recurring pool jobs.
 *
 * Subclasses implement perform(). This class handles:
 *   - Timing and structured logging
 *   - Exception isolation (errors are logged; yii2-queue does not see them)
 *   - Last-run timestamp written to cache after each execution (used by the web Jobs UI)
 *   - Pause check: if the job is marked paused via the web UI it will NOT reschedule
 *   - Self-rescheduling: at the end of every non-paused run a new delayed instance is
 *     pushed so the job repeats at intervalSeconds cadence
 */
abstract class BaseJob implements JobInterface
{
    /** Seconds to wait before the next run. Set to 0 to run once without rescheduling. */
    public int $intervalSeconds = 60;

    /**
     * Called by yii2-queue when the job is dequeued.
     * Do not override — implement perform() instead.
     */
    final public function execute($queue): void
    {
        $name = substr(strrchr(static::class, '\\'), 1);
        $t    = microtime(true);

        try {
            $this->perform();
        } catch (\Throwable $e) {
            Yii::error(
                sprintf('[%s] %s in %s:%d', $name, $e->getMessage(), $e->getFile(), $e->getLine()),
                'queue'
            );
        } finally {
            $elapsed = microtime(true) - $t;
            Yii::info(sprintf('[%s] done in %.3fs', $name, $elapsed), 'queue');

            // Record last-run time for the web Jobs UI
            Yii::$app->cache->set(JobRegistry::lastRunKey($name), time(), 7 * 86400);

            // Reschedule — unless the job has been paused from the web UI
            if ($this->intervalSeconds > 0 && !$this->isPaused($name)) {
                Yii::$app->queue->delay($this->intervalSeconds)->push(new static());
            } elseif ($this->isPaused($name)) {
                Yii::info("[{$name}] paused — not rescheduling", 'queue');
            }
        }
    }

    abstract protected function perform(): void;

    private function isPaused(string $shortName): bool
    {
        return (bool) Yii::$app->cache->get(JobRegistry::pauseKey($shortName));
    }
}
