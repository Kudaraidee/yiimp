<?php

namespace app\controllers;

use Yii;
use yii\db\Query;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use app\components\JobRegistry;

/**
 * Admin UI for monitoring and controlling recurring queue jobs.
 *
 * Actions:
 *   jobs/index      — dashboard listing all jobs with status
 *   jobs/pause      — POST: set pause flag so job stops rescheduling after current run
 *   jobs/resume     — POST: clear pause flag and re-push job if missing from queue
 *   jobs/run-now    — POST: cancel current delay and push immediately
 *   jobs/seed-all   — POST: push any jobs that have no queue row
 */
class JobsController extends BaseController
{
    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [[
                    'allow' => true,
                    'roles' => ['@'],
                    'matchCallback' => function () {
                        return Yii::$app->user->identity?->is_admin;
                    },
                ]],
                'denyCallback' => fn() => throw new ForbiddenHttpException(),
            ],
            'verbs' => [
                'class'   => VerbFilter::class,
                'actions' => [
                    'pause'    => ['POST'],
                    'resume'   => ['POST'],
                    'run-now'  => ['POST'],
                    'seed-all' => ['POST'],
                ],
            ],
        ];
    }

    // -------------------------------------------------------------------------

    /** Show the jobs dashboard. */
    public function actionIndex(): string
    {
        $jobs = $this->buildJobData();
        return $this->render('index', [
            'jobs'          => $jobs,
            'tableExists'   => $this->queueTableExists(),
            'balancesLocked' => (bool) Yii::$app->cache->get('balances_locked'),
        ]);
    }

    /** Pause a job: sets the cache flag so BaseJob skips rescheduling after this run. */
    public function actionPause(string $job): \yii\web\Response
    {
        $shortName = $this->resolveJobName($job);
        Yii::$app->cache->set(JobRegistry::pauseKey($shortName), true);
        Yii::$app->session->setFlash('success', "{$shortName} paused — will stop after current run completes.");
        return $this->redirect(['index']);
    }

    /** Resume a paused job: clears pause flag and re-pushes it if the queue row is missing. */
    public function actionResume(string $job): \yii\web\Response
    {
        $shortName = $this->resolveJobName($job);
        Yii::$app->cache->delete(JobRegistry::pauseKey($shortName));

        if (!$this->jobIsPending($shortName)) {
            $fqcn = $this->resolveJobClass($shortName);
            Yii::$app->queue->push(new $fqcn());
        }

        Yii::$app->session->setFlash('success', "{$shortName} resumed.");
        return $this->redirect(['index']);
    }

    /** Run a job immediately: removes any current delay and pushes with delay=0. */
    public function actionRunNow(string $job): \yii\web\Response
    {
        $shortName = $this->resolveJobName($job);
        $fqcn      = $this->resolveJobClass($shortName);

        // Delete the pending delayed row so it doesn't run twice
        Yii::$app->db->createCommand(
            "DELETE FROM {{%queue}} WHERE channel='default' AND done_at IS NULL AND reserved_at IS NULL
             AND job LIKE :pattern",
            [':pattern' => "%{$shortName}%"]
        )->execute();

        // Push with no delay
        Yii::$app->queue->push(new $fqcn());
        Yii::$app->session->setFlash('success', "{$shortName} queued for immediate execution.");
        return $this->redirect(['index']);
    }

    /** Push any jobs that have no active queue row. */
    public function actionSeedAll(): \yii\web\Response
    {
        $seeded = 0;
        foreach (JobRegistry::jobClasses() as $fqcn) {
            $shortName = JobRegistry::shortName($fqcn);
            if (!$this->jobIsPending($shortName)) {
                // Clear any stale pause flag so the job actually runs
                Yii::$app->cache->delete(JobRegistry::pauseKey($shortName));
                Yii::$app->queue->push(new $fqcn());
                $seeded++;
            }
        }
        Yii::$app->session->setFlash('success', "Seeded {$seeded} job(s).");
        return $this->redirect(['index']);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Build the data array for all known jobs to pass to the view.
     * @return array[]
     */
    private function buildJobData(): array
    {
        if (!$this->queueTableExists()) {
            return [];
        }

        $jobs = [];
        foreach (JobRegistry::jobClasses() as $fqcn) {
            $shortName = JobRegistry::shortName($fqcn);
            $job       = new $fqcn();
            $paused    = (bool) Yii::$app->cache->get(JobRegistry::pauseKey($shortName));
            $lastRun   = Yii::$app->cache->get(JobRegistry::lastRunKey($shortName)) ?: null;

            // Find the active queue row for this job
            $row = (new Query())
                ->from('{{%queue}}')
                ->where(['channel' => 'default', 'done_at' => null])
                ->andWhere(['like', 'job', $shortName])
                ->orderBy(['pushed_at' => SORT_ASC])
                ->one();

            if ($row) {
                $runsAt   = (int) $row['pushed_at'] + (int) ($row['delay'] ?? 0);
                $secsLeft = $runsAt - time();
                $running  = !empty($row['reserved_at']);

                if ($paused) {
                    $status = 'paused';
                } elseif ($running) {
                    $status = 'running';
                } elseif ($secsLeft <= 0) {
                    $status = 'ready';
                } elseif ($secsLeft > $job->intervalSeconds * 3) {
                    $status = 'late';
                } else {
                    $status = 'pending';
                }
            } else {
                $secsLeft = null;
                $running  = false;
                $status   = $paused ? 'paused' : 'not_seeded';
            }

            $jobs[] = [
                'fqcn'      => $fqcn,
                'name'      => $shortName,
                'domain'    => JobRegistry::domain($fqcn),
                'interval'  => $job->intervalSeconds,
                'status'    => $status,
                'secsLeft'  => $secsLeft,
                'lastRun'   => $lastRun,
                'paused'    => $paused,
                'running'   => $running,
                'queueRow'  => $row,
            ];
        }

        return $jobs;
    }

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

    /** Validate and return the short name, throwing 404 for unknown jobs. */
    private function resolveJobName(string $input): string
    {
        foreach (JobRegistry::jobClasses() as $fqcn) {
            if (JobRegistry::shortName($fqcn) === $input) {
                return $input;
            }
        }
        throw new NotFoundHttpException("Unknown job: {$input}");
    }

    /** Return the FQCN for a given short name. */
    private function resolveJobClass(string $shortName): string
    {
        foreach (JobRegistry::jobClasses() as $fqcn) {
            if (JobRegistry::shortName($fqcn) === $shortName) {
                return $fqcn;
            }
        }
        throw new NotFoundHttpException("Unknown job: {$shortName}");
    }
}
