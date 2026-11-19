<?php

namespace app\commands;

use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;
use yii\db\Query;
use app\components\JobRegistry;

/**
 * Manage the yiimp2 recurring job queue.
 *
 * Usage:
 *   php yii queue/seed     — push all recurring jobs if not already active (idempotent)
 *   php yii queue/status   — show pending counts and next scheduled run per job
 *   php yii queue/migrate  — reminder to run the yii2-queue DB migration
 */
class QueueController extends Controller
{
    // Job class list is centralised in JobRegistry — see app\components\JobRegistry.

    // -------------------------------------------------------------------------

    /**
     * Push all recurring jobs into the queue if they are not already active.
     *
     * Safe to run multiple times — existing active jobs are not duplicated.
     * Intended to be called once from supervisor on pool startup:
     *
     *   [program:yiimp-queue-seed]
     *   command=php /var/www/yiimp/yiimp2/yii queue/seed
     *   autorestart=false
     */
    public function actionSeed(): int
    {
        if (!$this->queueTableExists()) {
            $this->stderr("ERROR: queue table does not exist. Run: php yii migrate --migrationPath=@yii/queue/db/migrations\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout("Seeding queue jobs...\n\n", Console::BOLD);

        $seeded  = 0;
        $skipped = 0;

        foreach (JobRegistry::jobClasses() as $jobClass) {
            $shortName = JobRegistry::shortName($jobClass);

            if ($this->jobIsPending($shortName)) {
                $this->stdout(sprintf("  %-45s [skip — already active]\n", $shortName), Console::FG_YELLOW);
                $skipped++;
            } else {
                Yii::$app->queue->push(new $jobClass());
                $this->stdout(sprintf("  %-45s [seeded]\n", $shortName), Console::FG_GREEN);
                $seeded++;
            }
        }

        $this->stdout("\n");
        $this->stdout("Seeded: {$seeded}   Skipped: {$skipped}\n", Console::BOLD);
        return ExitCode::OK;
    }

    /**
     * Show the queue status: pending counts and next scheduled run per job.
     */
    public function actionStatus(): int
    {
        if (!$this->queueTableExists()) {
            $this->stderr("ERROR: queue table does not exist.\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        // Aggregate totals
        $waiting  = (int) (new Query())->from('{{%queue}}')
            ->where(['channel' => 'default', 'reserved_at' => null, 'done_at' => null])
            ->count();
        $running  = (int) (new Query())->from('{{%queue}}')
            ->where(['channel' => 'default', 'done_at' => null])
            ->andWhere(['not', ['reserved_at' => null]])
            ->count();
        $done     = (int) (new Query())->from('{{%queue}}')
            ->where(['channel' => 'default'])
            ->andWhere(['not', ['done_at' => null]])
            ->count();

        $this->stdout("\nQueue status\n", Console::BOLD);
        $this->stdout(str_repeat('─', 60) . "\n");
        $this->stdout(sprintf("  %-18s %d\n", 'Waiting:', $waiting));
        $this->stdout(sprintf("  %-18s %d\n", 'Running:', $running));
        $this->stdout(sprintf("  %-18s %d\n", 'Done (history):', $done));

        $locked = Yii::$app->cache->get('balances_locked') ? 'YES' : 'no';
        $this->stdout(sprintf("  %-18s %s\n", 'Balances locked:', $locked));
        $this->stdout("\n");

        // Per-job details
        $this->stdout(sprintf("  %-44s %-8s %-14s %s\n", 'Job', 'Every', 'Status', 'Runs in'), Console::BOLD);
        $this->stdout(str_repeat('─', 80) . "\n");

        foreach (JobRegistry::jobClasses() as $jobClass) {
            $shortName = JobRegistry::shortName($jobClass);
            $job       = new $jobClass();
            $interval  = $job->intervalSeconds . 's';

            $row = (new Query())
                ->from('{{%queue}}')
                ->where(['channel' => 'default', 'done_at' => null])
                ->andWhere(['like', 'job', $shortName])
                ->orderBy(['pushed_at' => SORT_ASC])
                ->one();

            if ($row) {
                $runsAt  = ((int) $row['pushed_at']) + ((int) ($row['delay'] ?? 0));
                $secsLeft = $runsAt - time();

                if ($row['reserved_at']) {
                    [$statusStr, $color] = ['running', Console::FG_CYAN];
                } elseif ($secsLeft <= 0) {
                    [$statusStr, $color] = ['ready', Console::FG_GREEN];
                } elseif ($secsLeft > $job->intervalSeconds * 2) {
                    [$statusStr, $color] = ['delayed', Console::FG_YELLOW];
                } else {
                    [$statusStr, $color] = ['pending', Console::FG_GREEN];
                }

                $runsInStr = $secsLeft <= 0 ? 'now' : $this->formatSeconds($secsLeft);
            } else {
                [$statusStr, $color, $runsInStr] = ['NOT SEEDED', Console::FG_RED, '—'];
            }

            $this->stdout(sprintf("  %-44s %-8s ", $shortName, $interval));
            $this->stdout(sprintf("%-14s", $statusStr), $color);
            $this->stdout($runsInStr . "\n");
        }

        $this->stdout("\n");
        return ExitCode::OK;
    }

    /**
     * Print a reminder about the required DB migration.
     */
    public function actionMigrate(): int
    {
        $this->stdout("Run the yii2-queue migration to create the queue table:\n\n");
        $this->stdout("  php yii migrate --migrationPath=@yii/queue/db/migrations\n\n");
        return ExitCode::OK;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function queueTableExists(): bool
    {
        return Yii::$app->db->getSchema()->getTableSchema('{{%queue}}') !== null;
    }

    private function jobIsPending(string $shortName): bool
    {
        return (new Query())
            ->from('{{%queue}}')
            ->where(['channel' => 'default', 'done_at' => null])
            ->andWhere(['like', 'job', $shortName])
            ->exists();
    }

    private function formatSeconds(int $secs): string
    {
        if ($secs < 60) {
            return "{$secs}s";
        }
        if ($secs < 3600) {
            return round($secs / 60) . 'm';
        }
        return round($secs / 3600, 1) . 'h';
    }
}
