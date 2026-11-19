<?php

namespace app\jobs\system;

use app\jobs\BaseJob;

/**
 * Evaluate all enabled notification rules and dispatch email/rpc/system alerts.
 * Ports: NotifyCheckRules() — main.sh state 7.
 */
class NotificationsJob extends BaseJob
{
    public int $intervalSeconds = 900;

    protected function perform(): void
    {
        (new \app\services\NotificationService())->checkRules();
    }
}
