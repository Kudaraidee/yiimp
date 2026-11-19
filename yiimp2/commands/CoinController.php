<?php

namespace app\commands;

use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use app\models\Coins;
use app\models\Blocks;
use app\components\rpc\WalletRPC;

/**
 * Coin management and diagnostics.
 *
 * Usage:
 *   php yii coin/delete <SYM>                   — delete coin with all related records
 *   php yii coin/purge <SYM>                    — clean users and history only
 *   php yii coin/diff <SYM>                     — compare wallet vs computed difficulty
 *   php yii coin/blocktime <SYM>                — estimate chain block time
 *   php yii coin/checkblocks <SYM>              — recheck confirmed blocks for orphans
 *   php yii coin/generated <SYM> [startHeight]  — find missed blocks, optionally fix
 *   php yii coin/get <SYM> <key>
 *   php yii coin/set <SYM> <key> <value>
 *   php yii coin/unset <SYM> <key>
 *   php yii coin/settings <SYM>
 */
class CoinController extends Controller
{
    private function findCoin(string $symbol): ?Coins
    {
        $coin = Coins::find()->where(['symbol' => $symbol])->one();
        if (!$coin) $this->stderr("coin {$symbol} not found!\n");
        return $coin;
    }

    /** Delete coin and all related records. */
    public function actionDelete(string $symbol): int
    {
        $coin = $this->findCoin($symbol);
        if (!$coin) return ExitCode::UNSPECIFIED_ERROR;

        $this->actionPurge($symbol);
        $coin->installed = 0;
        $coin->enable    = 0;
        $coin->save(false);
        $coin->delete();
        $this->stdout("coin {$symbol} deleted\n");
        return ExitCode::OK;
    }

    /** Clean users and history for a coin. */
    public function actionPurge(string $symbol): int
    {
        $coin = $this->findCoin($symbol);
        if (!$coin) return ExitCode::UNSPECIFIED_ERROR;

        $db = Yii::$app->db;
        $db->createCommand()->delete('balanceuser', ['coinid' => $coin->id])->execute();
        $db->createCommand()->delete('hashuser',    ['coinid' => $coin->id])->execute();
        $db->createCommand()->delete('earnings',    ['coinid' => $coin->id])->execute();
        $db->createCommand()->delete('blocks',      ['coin_id' => $coin->id])->execute();
        $db->createCommand()->delete('markets',     ['coinid' => $coin->id])->execute();
        $db->createCommand()->delete('market_history', "idcoin = {$coin->id}")->execute();
        $db->createCommand()->update('accounts', ['balance' => 0], ['coinid' => $coin->id])->execute();
        $this->stdout("coin {$symbol} purged\n");
        return ExitCode::OK;
    }

    /** Compare wallet difficulty with computed difficulty from block target. */
    public function actionDiff(string $symbol): int
    {
        $coin = $this->findCoin($symbol);
        if (!$coin) return ExitCode::UNSPECIFIED_ERROR;

        $remote = new WalletRPC($coin);
        $tpl    = $remote->getblocktemplate();
        $mnf    = $remote->getmininginfo();
        if (empty($tpl)) {
            $this->stderr("error: " . json_encode($tpl) . "\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $target       = $tpl['target'] ?? '';
        $computedDiff = Yii::$app->ConversionUtils->decode_compact($target);
        $walletDiff   = (float)($mnf['difficulty'] ?? 0);
        $factor       = $computedDiff ? round($walletDiff / $computedDiff, 3) : 'NaN';
        $nethash      = Yii::$app->ConversionUtils->Itoa2(($mnf['networkhashps'] ?? 0) * 1000, 3);

        $this->stdout("{$symbol}: network={$nethash}H/s\n");
        $this->stdout("bits={$tpl['bits']} target={$target}\n");
        $this->stdout("difficulty={$walletDiff} hash_to_difficulty(target)={$computedDiff} factor={$factor}\n");
        return ExitCode::OK;
    }

    /** Estimate the average block time from wallet chain history. */
    public function actionBlocktime(string $symbol): int
    {
        $coin = $this->findCoin($symbol);
        if (!$coin) return ExitCode::UNSPECIFIED_ERROR;

        $remote = new WalletRPC($coin);
        $info   = $remote->getinfo();
        if (empty($info)) {
            $this->stderr("error: " . json_encode($info) . "\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }
        $height = (int)($info['blocks'] ?? 0);

        $getBlockTime = function (int $h) use ($remote): int {
            $hash  = $remote->getblockhash($h);
            $block = $remote->getblock($hash);
            return (int)($block['time'] ?? 0);
        };

        $t1024 = $getBlockTime($height - 1024);
        $t512  = $getBlockTime($height - 512);
        $t128  = $getBlockTime($height - 128);
        $t0    = $getBlockTime($height);

        $fmt = fn(int $s) => sprintf('%dmn%02d', $s / 60, $s % 60);

        $t = (int)$coin->block_time;
        $this->stdout("{$symbol}: current block_time in DB {$t}s ({$fmt($t)})\n");

        foreach ([1024 => $t1024, 512 => $t512, 128 => $t128] as $n => $ts) {
            $avg = round(($t0 - $ts) / $n);
            $this->stdout("{$symbol}: avg last {$n} blocks = {$fmt($avg)} ({$avg}s)\n");
            if ($n === 1024 && empty($coin->block_time) && $avg > 10) {
                $coin->block_time = $avg;
                $coin->save(false);
                $this->stdout("{$symbol}: block_time set to {$avg}\n");
            }
        }
        return ExitCode::OK;
    }

    /** Recheck confirmed blocks for orphans. */
    public function actionCheckblocks(string $symbol): int
    {
        $coin = $this->findCoin($symbol);
        if (!$coin) return ExitCode::UNSPECIFIED_ERROR;

        $blocks = Blocks::find()
            ->where(['coin_id' => $coin->id, 'category' => 'generate'])
            ->orderBy(['height' => SORT_DESC])
            ->all();

        if (!$blocks) {
            $this->stdout("no confirmed blocks found\n");
            return ExitCode::OK;
        }

        $remote   = new WalletRPC($coin);
        $nbReset  = 0;
        $totAmt   = 0.0;

        foreach ($blocks as $block) {
            $b     = $remote->getblock($block->blockhash);
            $confs = (int)($b['confirmations'] ?? 0);
            if ($confs <= 0 || !$b) {
                $height = $b['height'] ?? $block->height;
                $this->stdout("{$height} {$confs} orphan\n");
                $totAmt        += $block->amount;
                $block->amount  = 0;
                $block->category = 'orphan';
                $nbReset += (int) Yii::$app->db->createCommand()->update(
                    'earnings', ['amount' => 0], ['blockid' => $block->id]
                )->execute();
                $block->save(false);
            }
        }

        if ($totAmt) {
            $this->stdout("found {$totAmt} {$symbol} orphaned after confirmed status ({$nbReset} earnings reset)!\n");
        } else {
            $this->stdout(count($blocks) . " confirmed blocks verified\n");
        }
        return ExitCode::OK;
    }

    /** Find blocks missed by the backend; optionally recreate them from a given height. */
    public function actionGenerated(string $symbol, int $startHeight = 0): int
    {
        $coin = $this->findCoin($symbol);
        if (!$coin) return ExitCode::UNSPECIFIED_ERROR;

        $remote = new WalletRPC($coin);
        $curHeight = (int) $remote->getblockcount();
        if (!$curHeight) {
            $this->stderr("unable to query current block height!\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $txs = $remote->listtransactions($coin->account ?? '', 900);
        if (!$txs || !is_array($txs)) {
            $this->stdout("no txs found!\n");
            return ExitCode::OK;
        }

        $db    = Yii::$app->db;
        $nbOk  = 0;
        $nbMissed = 0;

        foreach ($txs as $tx) {
            $cat = $tx['category'] ?? '';
            if ($cat !== 'generate' && $cat !== 'immature') continue;
            $height = $curHeight - ($tx['confirmations'] ?? 0) + 1;
            if (($tx['confirmations'] ?? 0) < 3) continue;

            $block = Blocks::find()->where(['coin_id' => $coin->id, 'height' => $height])->one();
            if ($block) {
                if ($block->category === 'orphan') {
                    if ($startHeight > 0 && $height >= $startHeight) {
                        $block->category = 'new';
                        if ($block->save(false)) $this->stdout("Fixed orphan block id {$block->id}\n");
                    } else {
                        $this->stdout("warning: orphan block {$height} with confirmations!\n");
                    }
                }
                $nbOk++;
            } else {
                $time = round(($tx['time'] ?? 0) / 900) * 900;
                if ($time < time() - 2 * 86400) continue;
                $date = date('Y-m-d H:i', $tx['time'] ?? 0);
                $this->stdout("{$date} missed block {$height}: " . json_encode($tx) . "\n");
                if ($startHeight > 0 && $height >= $startHeight) {
                    $db->createCommand()->insert('blocks', [
                        'coin_id'   => $coin->id,
                        'height'    => $height,
                        'time'      => $tx['time'] ?? 0,
                        'category'  => 'new',
                        'algo'      => $coin->algo,
                        'blockhash' => $tx['blockhash'] ?? '',
                        'txhash'    => $tx['txid'] ?? '',
                    ])->execute();
                    $this->stdout("Added new block at height {$height}\n");
                }
                $nbMissed++;
            }
        }
        $this->stdout("{$nbOk} blocks checked, {$nbMissed} missed by the backend\n");
        return ExitCode::OK;
    }

    // ── Coin settings ──────────────────────────────────────────────────────────

    public function actionGet(string $symbol, string $key): int
    {
        $value = Yii::$app->settings->coinGet($symbol, $key);
        $this->stdout($value . "\n");
        return ExitCode::OK;
    }

    public function actionSet(string $symbol, string $key, string $value): int
    {
        Yii::$app->settings->coinSet($symbol, $key, $value);
        $val = Yii::$app->settings->coinGet($symbol, $key);
        $this->stdout("{$symbol} {$key} " . json_encode($val) . "\n");
        return ExitCode::OK;
    }

    public function actionUnset(string $symbol, string $key): int
    {
        Yii::$app->settings->coinUnset($symbol, $key);
        $this->stdout("ok\n");
        return ExitCode::OK;
    }

    public function actionSettings(string $symbol): int
    {
        $keys = Yii::$app->settings->coinPrefetch($symbol);
        foreach ($keys as $key => $value) {
            $this->stdout("{$key} " . json_encode($value) . "\n");
        }
        return ExitCode::OK;
    }
}
