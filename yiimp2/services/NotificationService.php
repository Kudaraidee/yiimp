<?php

namespace app\services;

use Yii;
use app\models\Notifications;
use app\components\rpc\WalletRPC;

/**
 * NotificationService — evaluates user-configured coin alert rules and
 * dispatches actions (email / RPC command / system command).
 *
 * Ported from: web/yaamp/core/backend/notify.php → NotifyCheckRules()
 *
 * Notification types: 'email', 'rpc', 'system'
 * Condition format: "<field> <operator>" e.g. "price >" or "connections <"
 */
class NotificationService
{
    /**
     * Evaluate all enabled notification rules and fire any that are triggered.
     * Ports: NotifyCheckRules()
     */
    public function checkRules(): void
    {
        $now   = time();
        $since = $now - 10 * 60;

        // Load enabled rules whose check interval has elapsed, with coins eager-loaded.
        // Filter: coin must be installed, enabled, or watched.
        $rules = Notifications::find()
            ->with('coin')
            ->where(['enabled' => 1])
            ->andWhere(['<=', 'lastchecked', $since])
            ->all();

        foreach ($rules as $rule) {
            $coin = $rule->coin;

            if (!$coin || (!$coin->installed && !$coin->enable && !$coin->watch)) {
                continue;
            }

            // Parse condition: "<field> <operator>"
            $parts = explode(' ', (string) $rule->conditiontype, 2);
            if (count($parts) < 2) {
                Yii::warning("notify: invalid conditiontype for {$coin->symbol}: '{$rule->conditiontype}'", __CLASS__);
                continue;
            }
            [$field, $op] = $parts;

            $attrs = $coin->getAttributes();
            if (!array_key_exists($field, $attrs)) {
                Yii::warning("notify: unknown field '{$field}' for {$coin->symbol}", __CLASS__);
                continue;
            }

            $value    = $attrs[$field];
            $valref   = $rule->conditionvalue;
            $triggered = match ($op) {
                '<'       => $value < $valref,
                '>'       => $value > $valref,
                '<='      => $value <= $valref,
                '>='      => $value >= $valref,
                '=', '==' => $value == $valref,
                default   => false,
            };

            // Already notified this trigger cycle — just update timestamp
            if ($triggered && $rule->lasttriggered == $rule->lastchecked) {
                $rule->lasttriggered = $now;
                $rule->lastchecked   = $now;
                $rule->save();
                continue;
            }

            $rule->lasttriggered = $triggered ? $now : 0;
            $rule->lastchecked   = $now;
            $rule->save();

            if (!$triggered) {
                continue;
            }

            $valueStr = Yii::$app->ConversionUtils->bitcoinvaluetoa($value);
            Yii::info("trigger: {$coin->symbol} {$rule->notifytype} {$rule->conditiontype} {$rule->conditionvalue} ({$valueStr})", __CLASS__);

            $this->dispatch($rule, $coin, $field, $valueStr, $now);
        }
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function dispatch(Notifications $rule, object $coin, string $field, string $value, int $time): void
    {
        // Build variable substitution map used in all notify types
        $vars = [
            '$X'   => $value,
            '$F'   => $field,
            '$T'   => $rule->conditiontype,
            '$V'   => $rule->conditionvalue,
            '$N'   => $coin->name,
            '$SYM' => $coin->symbol,
            '$S2'  => $coin->symbol2 ?? '',
            '$A'   => $coin->master_wallet ?? '',
        ];

        switch ($rule->notifytype) {

            case 'email':
                $subject = "[{$coin->symbol}] Trigger {$rule->conditiontype} {$rule->conditionvalue} ({$value})";
                $body    = strtr(
                    "Description: {$rule->description}\n\nField: {$field}\nValue: {$value} at " .
                    date('Y-m-d H:i:s T', $time) . "\n",
                    $vars
                );

                $dest = defined('YIIMP_ADMIN_EMAIL') ? YIIMP_ADMIN_EMAIL : (Yii::$app->params['adminEmail'] ?? '');
                if (!empty($rule->notifycmd) && str_contains((string) $rule->notifycmd, '@')) {
                    $dest = $rule->notifycmd;
                }

                if ($dest) {
                    try {
                        Yii::$app->mailer->compose()
                            ->setTo($dest)
                            ->setSubject($subject)
                            ->setTextBody($body)
                            ->send();
                    } catch (\Throwable $e) {
                        Yii::warning("notify: mailer failed for {$coin->symbol}: {$e->getMessage()}", __CLASS__);
                    }
                }
                break;

            case 'rpc':
                $command = strtr((string) $rule->notifycmd, $vars);
                $remote  = new WalletRPC($coin);
                $res     = $remote->execute($command);
                if ($res === false) {
                    Yii::warning("trigger: {$coin->symbol} rpc error '{$command}' {$remote->error}", __CLASS__);
                } else {
                    Yii::info("trigger: {$coin->symbol} rpc → {$res}", __CLASS__);
                }
                break;

            case 'system':
                $command = strtr((string) $rule->notifycmd, $vars);
                $res     = system($command);
                if ($res === false) {
                    Yii::warning("trigger: {$coin->symbol} unable to execute '{$command}'", __CLASS__);
                }
                break;
        }
    }
}
