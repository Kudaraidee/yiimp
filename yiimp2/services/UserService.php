<?php

namespace app\services;

use Yii;
use app\models\Accounts;
use app\models\Coins;
use app\components\rpc\WalletRPC;

/**
 * UserService — resolves user wallet addresses to their correct coin and
 * handles coin switching when users change their payout currency.
 *
 * Ported from: web/yaamp/core/backend/users.php → updateUserStats()
 */
class UserService
{
    /**
     * GETH-family daemons (including Parallax/XHash) have no validateaddress
     * RPC. Their payout addresses are plain 20-byte EVM hex addresses, so
     * validate locally and let an explicit c=<symbol> bind the account to the
     * requested coin instead of falling back to the quote wallet.
     */
    private function isGethAddressValid($coin, string $address): bool
    {
        return $coin !== null
            && (string) $coin->rpcencoding === 'GETH'
            && preg_match('/^0x[0-9a-fA-F]{40}$/', $address) === 1;
    }

    /**
     * For each user whose coinid is unresolved (NULL) or whose coinsymbol has changed,
     * validate their wallet address against known coin daemons and assign the correct coin.
     * Ports: BackendUsersUpdate()
     */
    public function updateUserStats(): void
    {
        $t1 = microtime(true);

        // Users that either have no coin assigned or are requesting a coin switch via coinsymbol
        $users = Accounts::find()
            ->where(['coinid' => null])
            ->orWhere(['not', ['coinsymbol' => null]])
            ->andWhere(['not', ['coinsymbol' => '']])
            ->all();

        $allowExchange = defined('YIIMP_ALLOW_EXCHANGE') && YIIMP_ALLOW_EXCHANGE;

        foreach ($users as $user) {
            $oldUserCoinId = $user->coinid;

            // --- Explicit coin-switch request via coinsymbol -----------------
            if (!empty($user->coinsymbol)) {
                $coin = Coins::find()->where(['symbol' => $user->coinsymbol])->one();
                $user->coinsymbol = '';

                if ($coin) {
                    if ($user->coinid == $coin->id) {
                        $user->save();
                        continue;
                    }

                    $remote = new WalletRPC($coin);
                    $b      = $this->isGethAddressValid($coin, (string) $user->username)
                        ? ['isvalid' => true]
                        : $remote->validateaddress($user->username);

                    if ($b['isvalid'] ?? false) {
                        $oldBalance = (float) $user->balance;

                        if ($user->balance > 0) {
                            $oldCoin = Coins::findOne((int) $user->coinid);
                            if (!$oldCoin) {
                                if ($allowExchange) {
                                    $oldCoin = Coins::find()->where(['symbol' => Yii::$app->YiimpUtils->payoutQuoteSymbol()])->one();
                                } else {
                                    $user->save();
                                    continue;
                                }
                            }

                            $util     = Yii::$app->YiimpUtils;
                            $oldPrice = $util->payoutPrice($oldCoin);
                            $newPrice = $util->payoutPrice($coin);
                            if ($oldPrice > 0 && $newPrice > 0) {
                                $user->balance = $user->balance * $oldPrice / $newPrice;
                            }
                        }

                        $user->coinid = $coin->id;
                        $user->save();

                        Yii::info("{$user->username} converted to {$user->balance} {$coin->symbol} (old: {$oldBalance})", __CLASS__);
                        continue;
                    }
                }
            }

            // --- Address-scan: find which coin this address belongs to -------
            $user->coinid = 0;

            $order = $allowExchange ? 'difficulty DESC' : 'id ASC';
            $coins = Coins::find()->where(['enable' => 1])->orderBy($order)->all();

            foreach ($coins as $coin) {
                $remote = new WalletRPC($coin);
                $b      = $this->isGethAddressValid($coin, (string) $user->username)
                    ? ['isvalid' => true]
                    : $remote->validateaddress($user->username);

                if (!($b['isvalid'] ?? false)) {
                    continue;
                }

                if ($oldUserCoinId && $oldUserCoinId != $coin->id) {
                    Yii::info("{$user->username} set to {$coin->symbol}, balance {$user->balance} reset to 0", __CLASS__);
                    $user->balance = 0;
                }

                $user->coinid = $coin->id;
                break;
            }

            if (empty($user->coinid)) {
                Yii::info("{$user->hostaddr} - {$user->username} is an unknown address", __CLASS__);
            }

            $user->save();
        }

        Yii::debug(sprintf('%s took %.3fs', __METHOD__, microtime(true) - $t1), 'performance');
    }
}
