<?php

namespace app\commands;

use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use app\models\Accounts;
use app\models\Coins;
use app\models\Workers;
use app\components\rpc\WalletRPC;

/**
 * User account management.
 *
 * Usage:
 *   php yii user/delete <id|address>          — delete user and all related records
 *   php yii user/purge [days]                 — delete inactive users (default: 180 days)
 *   php yii user/search <ip>                  — find users by worker IP address
 *   php yii user/swap <address> <symbol>      — assign a different coin to a user
 */
class UserController extends Controller
{
    private function deleteUserById(Accounts $user): void
    {
        $db = Yii::$app->db;
        $id = $user->id;
        $db->createCommand()->delete('balanceuser', ['userid'     => $id])->execute();
        $db->createCommand()->delete('hashuser',    ['userid'     => $id])->execute();
        $db->createCommand()->delete('shares',      ['userid'     => $id])->execute();
        $db->createCommand()->delete('workers',     ['userid'     => $id])->execute();
        $db->createCommand()->delete('earnings',    ['userid'     => $id])->execute();
        $db->createCommand()->delete('blocks',      ['userid'     => $id])->execute();
        $db->createCommand()->delete('payouts',     ['account_id' => $id])->execute();
        $user->delete();
    }

    /** Delete a user by ID or wallet address, including all related records. */
    public function actionDelete(string $idOrAddr): int
    {
        $user = strlen($idOrAddr) < 26
            ? Accounts::findOne((int) $idOrAddr)
            : Accounts::find()->where(['username' => $idOrAddr])->one();

        if (!$user) { $this->stderr("user not found!\n"); return ExitCode::UNSPECIFIED_ERROR; }

        $name = $user->username;
        $this->deleteUserById($user);
        $this->stdout("user {$name} deleted\n");
        return ExitCode::OK;
    }

    /** Delete inactive users with zero balance, payouts, and workers. */
    public function actionPurge(int $days = 180): int
    {
        $since = time() - $days * 86400;
        $db    = Yii::$app->db;
        $users = Accounts::find()
            ->where(['not', ['last_earning' => null]])
            ->andWhere(['<', 'last_earning', $since])
            ->andWhere(['or', ['balance' => null], ['balance' => 0]])
            ->andWhere(['or', ['donation' => null], ['donation' => 0]])
            ->andWhere(['or', ['no_fees' => null], ['no_fees' => 0]])
            ->orderBy(['id' => SORT_ASC])
            ->all();

        if (!$users) {
            $this->stdout("no inactive users found since " . date('Y-m-d', $since) . "\n");
            return ExitCode::OK;
        }

        $deleted = 0;
        foreach ($users as $user) {
            $payouts  = (float) $db->createCommand("SELECT SUM(amount) FROM payouts  WHERE account_id = :id", [':id' => $user->id])->queryScalar();
            $earnings = (float) $db->createCommand("SELECT SUM(amount) FROM earnings WHERE userid     = :id", [':id' => $user->id])->queryScalar();
            $workers  = (int)   $db->createCommand("SELECT COUNT(*)    FROM workers  WHERE userid     = :id", [':id' => $user->id])->queryScalar();
            if ($payouts == 0 && $earnings == 0 && $workers == 0) {
                $this->stdout("{$user->username}\n");
                $this->deleteUserById($user);
                $deleted++;
            }
        }
        $this->stdout("{$deleted} user(s) deleted\n");
        return ExitCode::OK;
    }

    /** Find users by worker IP address. */
    public function actionSearch(string $ip): int
    {
        $workers = Workers::find()
            ->where(['like', 'ip', $ip])
            ->orderBy(['id' => SORT_DESC])
            ->limit(25)
            ->all();

        if (!$workers) { $this->stdout("no users found with this IP\n"); return ExitCode::OK; }

        foreach ($workers as $w) {
            $user = Accounts::findOne($w->userid);
            if (!$user) continue;
            $time = date('Y-m-d H:i:s', $w->time);
            $this->stdout("{$time}\t{$user->username}\t{$w->ip}\t{$w->algo}\n");
        }
        return ExitCode::OK;
    }

    /** Reassign a user's payout coin (for pools without auto-exchange). */
    public function actionSwap(string $address, string $symbol, string $force = ''): int
    {
        $user = Accounts::find()->where(['username' => $address])->one();
        if (!$user) { $this->stderr("invalid user address\n"); return ExitCode::UNSPECIFIED_ERROR; }

        $coin = Coins::find()->where(['symbol' => $symbol, 'installed' => 1, 'enable' => 1])->one();
        if (!$coin) { $this->stderr("invalid or disabled symbol\n"); return ExitCode::UNSPECIFIED_ERROR; }

        if ((float)$user->balance > 0 && $force !== 'force') {
            $this->stderr("user has pending balance " . Yii::$app->ConversionUtils->bitcoinvaluetoa($user->balance) . " — pass 'force' to override\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $payouts = (float) Yii::$app->db->createCommand(
            "SELECT SUM(amount) FROM payouts WHERE account_id = :id", [':id' => $user->id]
        )->queryScalar();
        if ($payouts > 0) { $this->stderr("user already had payouts\n"); return ExitCode::UNSPECIFIED_ERROR; }

        $algo = Yii::$app->db->createCommand(
            "SELECT algo FROM workers WHERE userid = :id LIMIT 1", [':id' => $user->id]
        )->queryScalar();
        if ($algo && $coin->algo !== $algo) {
            if (!defined('YIIMP_ALLOW_EXCHANGE') || !YIIMP_ALLOW_EXCHANGE) {
                $this->stderr("user is mining {$algo} but coin uses {$coin->algo}\n");
                return ExitCode::UNSPECIFIED_ERROR;
            }
            $this->stdout("note: user is currently mining {$algo}\n");
        }

        $remote = new WalletRPC($coin);
        $valid  = $remote->validateaddress($address);
        if (!($valid['isvalid'] ?? false)) {
            $this->stderr("address is not valid for {$symbol}\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $db     = Yii::$app->db;
        $nbUpd  = $db->createCommand()->update('earnings', ['status' => 0], ['status' => -1, 'coinid' => $coin->id])->execute();
        $blocks = $db->createCommand(
            "SELECT id, category FROM blocks WHERE coin_id = :cid
             AND id IN (SELECT blockid FROM earnings WHERE coinid = :cid AND userid = :uid)",
            [':cid' => $coin->id, ':uid' => $user->id]
        )->queryAll();

        $nbConf = 0;
        foreach ($blocks as $b) {
            if ($b['category'] === 'generate') {
                $nbConf += $db->createCommand()->update(
                    'earnings',
                    ['status' => 1, 'mature_time' => time()],
                    ['blockid' => $b['id'], 'userid' => $user->id, ['<', 'status', 1]]
                )->execute();
            }
        }

        $user->coinid = $coin->id;
        if ($user->save(false)) {
            $this->stdout("coin {$symbol} assigned to {$address}, {$nbUpd} invalid earnings updated, {$nbConf} confirmed\n");
        }
        return ExitCode::OK;
    }
}
