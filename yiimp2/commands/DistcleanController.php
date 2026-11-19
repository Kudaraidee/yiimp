<?php

namespace app\commands;

use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use app\models\Accounts;

/**
 * Erase all user data before a public release / demo reset.
 *
 * ⚠ DESTRUCTIVE — permanently deletes all user accounts, balances, blocks,
 *   hashrate history, payouts, and resets coin wallet credentials.
 *   This operation CANNOT be undone.
 *
 * Double confirmation is required to prevent accidental execution:
 *
 *   1. SERVER CONFIG — set in /etc/yiimp/serverconfig.php:
 *        define('YIIMP_CLI_ALLOW_DISTCLEAN', true);
 *      This must be explicitly enabled before the command will run at all.
 *      Set it back to false (or remove it) after use.
 *
 *   2. RUNTIME PASSWORD — pass the database password on the command line:
 *        php yii distclean <YIIMP_DBPASSWORD>
 *      The value must match YIIMP_DBPASSWORD in serverconfig.php exactly.
 *
 * Both checks must pass; failing either aborts with no data changed.
 *
 * Usage:
 *   php yii distclean <dbPassword>
 */
class DistcleanController extends Controller
{
    public $defaultAction = 'index';

    public function actionIndex(string $dbPassword): int
    {
        if (!defined('YIIMP_CLI_ALLOW_DISTCLEAN') || !YIIMP_CLI_ALLOW_DISTCLEAN) {
            $this->stderr("distclean is disabled. Set YIIMP_CLI_ALLOW_DISTCLEAN = true in serverconfig.php to enable.\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }

        if (!defined('YIIMP_DBPASSWORD') || $dbPassword !== YIIMP_DBPASSWORD) {
            $this->stderr("Bad password\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $db         = Yii::$app->db;
        $nbDeleted  = 0;

        foreach (Accounts::find()->all() as $user) {
            $db->createCommand()->delete('balanceuser', ['userid'     => $user->id])->execute();
            $db->createCommand()->delete('hashuser',    ['userid'     => $user->id])->execute();
            $db->createCommand()->delete('shares',      ['userid'     => $user->id])->execute();
            $db->createCommand()->delete('workers',     ['userid'     => $user->id])->execute();
            $db->createCommand()->delete('earnings',    ['userid'     => $user->id])->execute();
            $db->createCommand()->delete('blocks',      ['userid'     => $user->id])->execute();
            $db->createCommand()->delete('payouts',     ['account_id' => $user->id])->execute();
            $nbDeleted += (int) $user->delete();
        }
        $this->stdout("{$nbDeleted} users deleted\n");

        foreach ([
            'DELETE FROM stats',
            'DELETE FROM blocks',
            'DELETE FROM hashrate',
            'DELETE FROM hashstats',
            'DELETE FROM payouts',
            'DELETE FROM connections',
            'DELETE FROM stratums',
            'DELETE FROM exchange',
            'UPDATE balances SET balance = 0',
            'DELETE FROM markets WHERE coinid NOT IN (SELECT id FROM coins)',
            'UPDATE markets SET deposit_address = NULL',
            "UPDATE coins SET master_wallet=NULL, charity_address=NULL, deposit_address=NULL,
                balance=0, rpcpasswd=NULL, rpcuser=NULL WHERE 1",
        ] as $sql) {
            $db->createCommand($sql)->execute();
        }

        $this->stdout("distclean complete\n");
        return ExitCode::OK;
    }
}
