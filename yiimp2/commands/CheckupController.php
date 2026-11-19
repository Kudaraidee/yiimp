<?php

namespace app\commands;

use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use app\models\Coins;
use app\models\Accounts;

/**
 * Site health checks.
 *
 * Usage:
 *   php yii checkup          — run all checks (directories, PHP extensions, images, BTC balances)
 *   php yii checkup/dirs     — check writable directories only
 *   php yii checkup/images   — auto-link / clean coin images only
 */
class CheckupController extends Controller
{
    public $defaultAction = 'index';

    /** Run all checks. */
    public function actionIndex(): int
    {
        $this->actionDirs();
        $this->actionPhp();
        $this->actionImages();

        if (defined('YIIMP_ALLOW_EXCHANGE') && !YIIMP_ALLOW_EXCHANGE) {
            $this->cleanUserBalancesBtc();
        }

        $this->stdout("ok\n", \yii\helpers\Console::FG_GREEN);
        return ExitCode::OK;
    }

    /** Check writable directories and required files. */
    public function actionDirs(): int
    {
        $root = defined('YIIMP_HTDOCS') ? YIIMP_HTDOCS : dirname(Yii::$app->basePath);
        foreach ([
            $root . '/assets',
            defined('YIIMP_LOGS') ? YIIMP_LOGS : ($root . '/log'),
        ] as $dir) {
            if (!is_writable($dir)) {
                $this->stderr("directory {$dir} is not writable!\n", \yii\helpers\Console::FG_RED);
            }
        }
        if (!is_readable('/etc/yiimp/keys.php')) {
            $this->stdout("note: /etc/yiimp/keys.php not found (optional)\n");
        }
        return ExitCode::OK;
    }

    /** Check required PHP extensions. */
    public function actionPhp(): int
    {
        if (!function_exists('curl_init')) {
            $this->stderr("missing curl PHP extension!\n", \yii\helpers\Console::FG_RED);
        }
        if (!extension_loaded('memcache') && !extension_loaded('memcached')) {
            $this->stdout("note: memcache PHP extension not loaded (optional if using FileCache)\n");
        }
        return ExitCode::OK;
    }

    /** Auto-link coin images and remove HTML-injected fakes. */
    public function actionImages(): int
    {
        $root    = defined('YIIMP_HTDOCS') ? YIIMP_HTDOCS : dirname(Yii::$app->basePath);
        $coins   = Coins::find()->all();
        $updated = 0;
        $dropped = 0;

        foreach ($coins as $coin) {
            if (!empty($coin->image)) {
                $path = $root . $coin->image;
                if (file_exists($path)) {
                    $data = file_get_contents($path);
                    if (str_contains($data, '<script') || str_contains($data, '<html')) {
                        unlink($path);
                        $coin->image = null;
                        $dropped += (int) $coin->save(false);
                    }
                    continue;
                }
                $auto = "/images/coin-{$coin->symbol}.png";
                if (file_exists($root . $auto)) {
                    $coin->image = $auto;
                    $updated += (int) $coin->save(false);
                } else {
                    $coin->image = null;
                    $dropped += (int) $coin->save(false);
                }
            } elseif (file_exists($root . "/images/coin-{$coin->symbol}.png")) {
                $coin->image = "/images/coin-{$coin->symbol}.png";
                $updated += (int) $coin->save(false);
            }
        }

        $this->stdout(count($coins) . " coins checked");
        if ($updated || $dropped) {
            $this->stdout(", {$updated} images updated, {$dropped} removed");
        }
        $this->stdout("\n");
        return ExitCode::OK;
    }

    /** Drop BTC user balances when YIIMP_ALLOW_EXCHANGE is false. */
    private function cleanUserBalancesBtc(): void
    {
        $db      = Yii::$app->db;
        $btcCoin = Coins::find()->where(['symbol' => 'BTC'])->one();
        if (!$btcCoin) return;

        $users   = Accounts::find()->where(['coinid' => $btcCoin->id])->all();
        $cleaned = 0;
        foreach ($users as $user) {
            $user->balance = 0;
            $db->createCommand()->delete('balanceuser', ['userid' => $user->id])->execute();
            $db->createCommand()->delete('hashuser',    ['userid' => $user->id])->execute();
            $db->createCommand()->delete('shares',      ['userid' => $user->id])->execute();
            $db->createCommand()->delete('workers',     ['userid' => $user->id])->execute();
            $db->createCommand()->update('earnings', ['userid' => 0], ['userid' => $user->id])->execute();
            $db->createCommand()->update('blocks',   ['userid' => 0], ['userid' => $user->id])->execute();
            $db->createCommand()->update('payouts', ['account_id' => 0], ['account_id' => $user->id])->execute();
            $cleaned += (int) $user->save(false);
        }
        $this->stdout("{$cleaned} BTC users cleaned\n");
    }
}
