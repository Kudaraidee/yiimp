<?php

namespace app\jobs\coins;

use Yii;
use app\jobs\BaseJob;
use app\services\CoinService;
use yii\queue\RetryableJobInterface;

/**
 * Check GitHub for newer wallet releases of installed coins and mail a summary.
 * Ports: cronjob/GithubScan → BackendCoinsVersionUpdate() (legacy daily crontab,
 * shipped disabled). Opt-in: define('YIIMP_GITHUB_SCAN', true) in serverconfig.php.
 *
 * The scan takes up to ~45s per coin (5s rate-limit pause + 40s curl timeout),
 * far beyond the queue's default 300s TTR. A TTR overrun would make yii2-queue
 * start a second copy while the first still runs (duplicate mails, and each copy
 * reschedules itself), so this job declares its own TTR and never retries.
 */
class GithubVersionsJob extends BaseJob implements RetryableJobInterface
{
    public int $intervalSeconds = 86400;

    protected function perform(): void
    {
        if (!YIIMP_GITHUB_SCAN) {
            Yii::debug('GitHub version scan disabled (YIIMP_GITHUB_SCAN)', 'queue');
            return;
        }
        (new CoinService())->updateVersionFromGithub();
    }

    public function getTtr(): int
    {
        return 6 * 3600;
    }

    public function canRetry($attempt, $error): bool
    {
        return false;
    }
}
