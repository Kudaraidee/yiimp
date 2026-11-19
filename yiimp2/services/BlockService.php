<?php

namespace app\services;

use Yii;
use yii\db\Exception as DbException;
use app\models\Blocks;
use app\models\Coins;
use app\models\Accounts;
use app\models\Workers;
use app\models\Earnings;
use app\components\rpc\WalletRPC;

/**
 * BlockService — full block lifecycle management for the pool.
 *
 * Ported from web/yaamp/core/backend/blocks.php.
 *
 * Method map:
 *   BackendBlockNew()           → distributeReward()
 *   BackendBlockFind1()         → processNewBlocks()
 *   BackendBlocksUpdate()       → updateBlockConfirmations()
 *   BackendBlockFind2()         → scanTransactions()
 *   BackendUpdatePoolBalances() → updatePoolBalances()
 *   MonitorBTC()                → monitorBtcWithdrawals()
 */
class BlockService
{
    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Distribute the reward for a confirmed block among miners proportionally
     * to their submitted shares. Handles both shared-pool and solo mining.
     *
     * Must be called after the block has been validated by the wallet daemon.
     */
    public function distributeReward(Coins $coin, Blocks $block): void
    {
        $reward = (float) $block->amount;
        if (!$reward || $block->algo === 'PoS' || $block->algo === 'MN') {
            return;
        }
        if ($block->category === 'stake' || $block->category === 'generated') {
            return;
        }

        $isSolo = $this->isSoloBlock($block);
        $db     = Yii::$app->db;

        // Share window: all shares up to this block height that have not yet
        // been rewarded, belonging to this coin.
        $shareWhere  = 'blocknumber <= :bh AND blockrewarded IS NULL AND coinid = :coin';
        $shareParams = [':bh' => (int) $block->height, ':coin' => (int) $coin->id];

        if (!$isSolo) {
            Yii::info("Shared mining block: coin {$coin->id} height {$block->height} by user {$block->userid}", __CLASS__);

            // Remove solo-miner shares from the shared reward window so that
            // solo workers do not dilute the shared pool calculation.
            $soloWorkerIds = Workers::find()
                ->select('id')
                ->where(['algo' => $block->algo])
                ->andWhere(['like', 'password', 'm=solo'])
                ->column();

            if (!empty($soloWorkerIds)) {
                $ids = implode(',', array_map('intval', $soloWorkerIds));
                $db->createCommand(
                    "DELETE FROM shares WHERE algo=:algo AND workerid IN ({$ids}) AND {$shareWhere}",
                    array_merge([':algo' => $coin->algo], $shareParams)
                )->execute();
            }

            $totalHashPower = (float) $db->createCommand(
                "SELECT SUM(difficulty) FROM shares WHERE algo=:algo AND valid=1 AND {$shareWhere}",
                array_merge([':algo' => $coin->algo], $shareParams)
            )->queryScalar();

            if (!$totalHashPower) {
                return;
            }

            $sharesByUser = $db->createCommand(
                "SELECT userid, SUM(difficulty) AS total FROM shares
                 WHERE algo=:algo AND valid=1 AND {$shareWhere}
                 GROUP BY userid",
                array_merge([':algo' => $coin->algo], $shareParams)
            )->queryAll();

            foreach ($sharesByUser as $row) {
                $hashPower = (float) $row['total'];
                if (!$hashPower) {
                    continue;
                }

                $account = Accounts::findOne((int) $row['userid']);
                if (!$account) {
                    continue;
                }
                $userCoin = Coins::findOne((int) $account->coinid);

                $amount = $reward * $hashPower / $totalHashPower;

                if (!$account->no_fees) {
                    $amount = $this->takeFee($amount, $coin->algo);
                }
                if (!empty($account->donation)) {
                    $amount = $this->takeFee($amount, $coin->algo, (float) $account->donation);
                    if ($amount <= 0) {
                        continue;
                    }
                }

                if ($this->earningAllowed($coin, $userCoin)) {
                    $earning            = new Earnings();
                    $earning->userid    = $account->id;
                    $earning->amount    = $amount;
                    $earning->coinid    = $coin->id;
                    $earning->blockid   = $block->id;
                    $earning->create_time = $block->time;
                    $earning->price     = $coin->auto_exchange ? $coin->price : 0;

                    if ($block->category === 'generate') {
                        $earning->mature_time = time();
                        $earning->status      = 1;
                    } else {
                        $earning->status = 0;
                    }

                    if ($userCoin && !$this->allowExchange() && $userCoin->algo !== $coin->algo) {
                        Yii::warning("{$coin->symbol}: invalid earning for {$account->username}, user coin is {$userCoin->symbol}", __CLASS__);
                        $earning->status = -1;
                    }

                    if (!$earning->save()) {
                        Yii::error(__METHOD__ . ': unable to insert earning for user ' . $account->id, __CLASS__);
                    }
                }

                $account->last_earning = time();
                $account->save();
            }

            // Calculate and store block effort (shared)
            $lastShared    = $db->createCommand(
                "SELECT height, time FROM blocks
                 WHERE coin_id=:id AND solo=0 AND category IN ('immature','generate')
                 ORDER BY height DESC LIMIT 1",
                [':id' => $coin->id]
            )->queryOne();
            $timeLastShared = (int) ($lastShared['time'] ?? 0);

            $totalDiff = (float) $db->createCommand(
                'SELECT SUM(difficulty) FROM shares
                 WHERE coinid=:coin AND algo=:algo AND solo=0 AND time>=:since',
                [':coin' => $coin->id, ':algo' => $coin->algo, ':since' => $timeLastShared]
            )->queryScalar();

            if ($block->difficulty) {
                $block->effort = round($totalDiff * 100 / $block->difficulty, 2);
            }
            $block->solo = 0;
            $block->save();

        } else {
            // --- Solo mining path ---
            Yii::info("Solo mining block: coin {$coin->id} height {$block->height} by user {$block->userid}", __CLASS__);

            $account = Accounts::findOne((int) $block->userid);
            if (!$account) {
                return;
            }
            $userCoin = Coins::findOne((int) $account->coinid);

            $amount = $reward;
            if (!$account->no_fees) {
                $amount = $this->takeFee($amount, $coin->algo, $this->getSoloFeePercent($coin->algo));
            }

            if ($this->earningAllowed($coin, $userCoin)) {
                $earning            = new Earnings();
                $earning->userid    = $account->id;
                $earning->amount    = $amount;
                $earning->coinid    = $coin->id;
                $earning->blockid   = $block->id;
                $earning->create_time = $block->time;
                $earning->price     = $coin->auto_exchange ? $coin->price : 0;

                if ($block->category === 'generate') {
                    $earning->mature_time = time();
                    $earning->status      = 1;
                } else {
                    $earning->status = 0;
                }

                if ($userCoin && !$this->allowExchange() && $userCoin->algo !== $coin->algo) {
                    Yii::warning("{$coin->symbol}: invalid earning for {$account->username}, user coin is {$userCoin->symbol}", __CLASS__);
                    $earning->status = -1;
                }

                if (!$earning->save()) {
                    Yii::error(__METHOD__ . ': unable to insert earning for user ' . $account->id, __CLASS__);
                }
            }

            $account->last_earning = time();
            $account->save();

            // Calculate and store block effort (solo)
            $lastSolo    = $db->createCommand(
                "SELECT height, time FROM blocks
                 WHERE coin_id=:id AND solo=1 AND category IN ('immature','generate')
                 ORDER BY height DESC LIMIT 1",
                [':id' => $coin->id]
            )->queryOne();
            $timeLastSolo = (int) ($lastSolo['time'] ?? 0);

            $totalDiff = (float) $db->createCommand(
                'SELECT SUM(difficulty) FROM shares
                 WHERE coinid=:coin AND algo=:algo AND solo=1 AND time>=:since',
                [':coin' => $coin->id, ':algo' => $coin->algo, ':since' => $timeLastSolo]
            )->queryScalar();

            if ($block->difficulty) {
                $block->effort = round($totalDiff * 100 / $block->difficulty, 2);
            }
            $block->solo = 1;
            $block->save();
        }

        // Mark the rewarded shares so they are not processed again.
        // Retry once on deadlock (lock wait timeout is common under load).
        $soloClause = $isSolo ? '=1' : '!=1';
        $updateSql  = "UPDATE shares SET pid=-1, blocknumber=:bh, blockrewarded=:bh
                       WHERE algo=:algo AND {$shareWhere} AND solo{$soloClause}";
        $updateParams = array_merge([':algo' => $coin->algo], $shareParams);

        try {
            $db->createCommand($updateSql, $updateParams)->execute();
        } catch (DbException $e) {
            Yii::warning("Unable to update shares for block {$block->height}, retrying… ({$e->getMessage()})", __CLASS__);
            sleep(1);
            $db->createCommand($updateSql, $updateParams)->execute();
        }
    }

    /**
     * Process blocks in 'new' state that were injected by the stratum server.
     * Validates each block via the wallet RPC, resolves the coinbase transaction,
     * and transitions the block to 'immature' or 'orphan'.
     */
    public function processNewBlocks(?int $coinId = null): void
    {
        $query = Blocks::find()->where(['category' => 'new'])->orderBy('time');
        if ($coinId !== null) {
            $query->andWhere(['coin_id' => $coinId]);
        }

        foreach ($query->each() as $block) {
            /** @var Blocks $block */
            $coin = Coins::findOne((int) $block->coin_id);
            if (!$coin || !$block->coin_id) {
                Yii::warning("Bad coin id {$block->coin_id} for block id {$block->id} — deleting.", __CLASS__);
                $block->delete();
                continue;
            }
            if (!$coin->enable) {
                continue;
            }
            if ($coin->rpcencoding === 'DCR' && !$coin->auto_ready) {
                continue;
            }

            // Deduplicate: ignore if another block record for the same hash/height exists.
            $duplicate = Blocks::find()
                ->where(['coin_id' => $coin->id, 'blockhash' => $block->blockhash, 'height' => $block->height])
                ->andWhere(['!=', 'id', $block->id])
                ->exists();
            if ($duplicate) {
                Yii::warning("Duplicate {$coin->symbol} block at height {$block->height} — deleting.", __CLASS__);
                $block->delete();
                continue;
            }

            $block->category = 'orphan';
            $remote          = new WalletRPC($coin);
            $blockExt        = $remote->getblock($block->blockhash);
            $blockAge        = time() - $block->time;

            if ($coin->rpcencoding === 'DCR' && $blockAge < 2000) {
                // DCR generated blocks need network acceptance time before gettransaction works.
                if (!$blockExt) {
                    continue;
                }
                $txid = $blockExt['tx'][0] ?? null;
                if (!$txid) {
                    continue;
                }
                $tx = $remote->gettransaction($txid);
                if (!$tx || !isset($tx['details'])) {
                    continue;
                }
                Yii::info("{$coin->symbol} {$block->height} confirmed after {$blockAge}s", __CLASS__);

            } elseif (!$blockExt || !isset($blockExt['tx'][0])) {
                $block->amount = 0;
                $block->save();
                Yii::info("{$coin->symbol} orphan at height {$block->height} after " . (time() - $block->time) . 's', __CLASS__);
                continue;

            } elseif ($coin->rpcencoding === 'POS' && ($blockExt['nonce'] ?? null) == 0) {
                $block->category = 'stake';
                $block->save();
                continue;
            }

            $tx = $remote->gettransaction($blockExt['tx'][0]);
            if (!$tx || !isset($tx['details'][0])) {
                $block->amount = 0;
                $block->save();
                continue;
            }

            $block->txhash        = $blockExt['tx'][0];
            $block->category      = 'immature';
            $block->amount        = $tx['details'][0]['amount'];
            $block->confirmations = $tx['confirmations'] ?? 0;
            $block->price         = $coin->price;

            // Back-fill workerid from the highest-difficulty share at block time.
            if (empty($block->workerid) && $block->userid > 0) {
                $wid = Yii::$app->db->createCommand(
                    'SELECT workerid FROM shares
                     WHERE userid=:uid AND coinid=:cid AND valid=1 AND time<=:t
                     ORDER BY difficulty DESC LIMIT 1',
                    [':uid' => $block->userid, ':cid' => $block->coin_id, ':t' => $block->time]
                )->queryScalar();
                $block->workerid = $wid ?: null;
            }

            if (!$block->save()) {
                Yii::error(__FUNCTION__ . ': unable to save block ' . $block->id, __CLASS__);
            }

            if ($block->category !== 'orphan') {
                $this->distributeReward($coin, $block);
            }
        }
    }

    /**
     * Refresh confirmation counts on all immature/stake/orphan blocks.
     * Transitions blocks to 'generate' when fully confirmed and matures earnings.
     * Handles orphan detection and LUX-style reorg recovery.
     */
    public function updateBlockConfirmations(?int $coinId = null): void
    {
        $t1 = microtime(true);
        $db = Yii::$app->db;

        $query = Blocks::find()
            ->where(['in', 'category', ['immature', 'stake', 'orphan']])
            ->orderBy('time');
        if ($coinId !== null) {
            $query->andWhere(['coin_id' => $coinId]);
        }

        foreach ($query->each() as $block) {
            /** @var Blocks $block */
            $coin = Coins::findOne((int) $block->coin_id);
            if (!$coin || !$block->coin_id) {
                Yii::warning("Bad coin id {$block->coin_id} for block id {$block->id} — deleting.", __CLASS__);
                $block->delete();
                continue;
            }

            if (!$coin->auto_ready || ($coin->target_height && $coin->target_height > $coin->block_height)) {
                continue;
            }

            $remote = new WalletRPC($coin);

            // Resolve txhash if missing.
            if (empty($block->txhash)) {
                $blockExt = $remote->getblock($block->blockhash);

                if ($coin->rpcencoding === 'POS' && ($blockExt['nonce'] ?? null) == 0) {
                    $block->category = 'stake';
                    $block->save();
                }

                if (!$blockExt || empty($blockExt['tx'][0])) {
                    continue;
                }
                $block->txhash = $blockExt['tx'][0];
                if (empty($block->txhash)) {
                    continue;
                }
            }

            $tx = $remote->gettransaction($block->txhash);

            if (!$tx && $block->category !== 'orphan') {
                if ($coin->enable) {
                    Yii::warning("{$coin->name}: unable to find {$block->category} block {$block->height} tx {$block->txhash}", __CLASS__);

                    // DCR orphan detection via getblock confirmations.
                    if ($coin->rpcencoding === 'DCR' && $block->category === 'immature' && $coin->auto_ready) {
                        $blockExt = $remote->getblock($block->blockhash);
                        $conf     = $blockExt['confirmations'] ?? -1;
                        if ($conf === -1 || ($conf > 2 && empty($blockExt['nextblockhash']))) {
                            Yii::info("{$coin->name} orphan block {$block->height} detected after {$conf} confirmations", __CLASS__);
                            $block->confirmations = -1;
                            $block->amount        = 0;
                            $block->category      = 'orphan';
                            $block->save();
                            continue;
                        }
                    }
                } elseif ((time() - $block->time) > 7 * 24 * 3600) {
                    Yii::info("{$coin->name} outdated immature block {$block->height} — marking orphan", __CLASS__);
                    $block->category = 'orphan';
                }
                $block->save();
                continue;
            }

            // LUX-style reorg recovery: an orphan that later gets confirmations is reinstated.
            if ($block->category === 'orphan') {
                if ($coin->enable && (time() - $block->time) < 3600) {
                    $blockExt = $remote->getblock($block->blockhash);
                    $conf     = $blockExt['confirmations'] ?? -1;
                    if ($conf > 2 && !empty($blockExt['nextblockhash'])) {
                        Yii::info("{$coin->name} orphan block {$block->height} reinstated ({$conf} confirmations)", __CLASS__);
                        $block->category = 'new'; // re-queued for distributeReward
                        $block->save();
                    }
                }
                continue;
            }

            $block->confirmations = (int) ($tx['confirmations'] ?? 0);
            $category             = $block->category;

            if ($block->confirmations === -1 && $coin->enable && $coin->auto_ready) {
                $category      = 'orphan';
                $block->amount = 0;
            } elseif (isset($tx['details'][0]['category'])) {
                $category = $tx['details'][0]['category'];
            } elseif (isset($tx['category'])) {
                $category = $tx['category'];
            }

            // PoS stake blocks.
            if ($block->category === 'stake') {
                if ($category === 'generate') {
                    $block->category = 'generated';
                } elseif ($category === 'orphan') {
                    $block->category = 'orphan';
                }
                $block->save();
                continue;
            }

            // PoW blocks.
            $block->category = $category;
            $block->save();

            if ($category === 'generate') {
                $db->createCommand(
                    'UPDATE earnings SET status=1, mature_time=UNIX_TIMESTAMP() WHERE blockid=:bid AND status!=-1',
                    [':bid' => $block->id]
                )->execute();

                // Auto-calibrate mature_blocks on the coin record.
                if ($block->confirmations > 0 &&
                    ($block->confirmations < $coin->mature_blocks || empty($coin->mature_blocks))
                ) {
                    $coin = Coins::findOne($block->coin_id); // refresh
                    Yii::info("{$coin->symbol} mature_blocks updated to {$block->confirmations}", __CLASS__);
                    $coin->mature_blocks = $block->confirmations;
                    $coin->save();
                }
            } elseif ($category !== 'immature') {
                $db->createCommand(
                    'DELETE FROM earnings WHERE blockid=:bid AND status!=-1',
                    [':bid' => $block->id]
                )->execute();
            }
        }

        $elapsed = microtime(true) - $t1;
        Yii::debug(sprintf('%s took %.3f sec', __METHOD__, $elapsed), 'performance');
    }

    /**
     * Scan all enabled coins for newly mined blocks using listsinceblock.
     * Creates Blocks records for any newly discovered blocks and calls
     * distributeReward() immediately for fully confirmed ones.
     */
    public function scanTransactions(?int $coinId = null): void
    {
        $t1 = microtime(true);
        $db = Yii::$app->db;

        $query = $coinId
            ? Coins::find()->where(['id' => $coinId])
            : Coins::find()->where(['enable' => 1]);

        foreach ($query->each() as $coin) {
            /** @var Coins $coin */
            if ($coin->symbol === 'BTC') {
                continue;
            }

            $remote    = new WalletRPC($coin);
            $timerRpc  = microtime(true);
            $lastBlock = $coin->lastblock ?? '';
            $list      = $remote->listsinceblock($lastBlock);
            $rpcDelay  = microtime(true) - $timerRpc;

            if ($rpcDelay > 0.5) {
                $txCount = is_array($list) ? count($list['transactions'] ?? []) : 0;
                Yii::info(sprintf('%s: %s listsinceblock took %.3fs, %d txs', __FUNCTION__, $coin->symbol, $rpcDelay, $txCount), __CLASS__);
            }

            if (!$list) {
                continue;
            }

            $mostRecent = 0;
            foreach ($list['transactions'] as $transaction) {
                if (!isset($transaction['blockhash'])) {
                    continue;
                }
                $txTime = $transaction['time'] ?? 0;
                if ($txTime > time() - 5 * 60) {
                    continue; // too recent — wait for it to stabilise
                }
                if ($txTime < time() - 60 * 60) {
                    continue; // too old — already processed
                }
                if (!in_array($transaction['category'] ?? '', ['generate', 'immature'], true)) {
                    continue;
                }

                $blockExt = $remote->getblock($transaction['blockhash']);
                if (!$blockExt) {
                    continue;
                }

                // Skip blocks we already know about.
                $exists = Blocks::find()
                    ->where(['coin_id' => $coin->id])
                    ->andWhere(['or', ['blockhash' => $transaction['blockhash']], ['height' => $blockExt['height']]])
                    ->exists();
                if ($exists) {
                    continue;
                }

                if ($coin->rpcencoding === 'DCR') {
                    Yii::info("{$coin->name} generated block {$blockExt['height']} detected", __CLASS__);
                }

                // Advance the coin's checkpoint to the most recent block.
                if ($txTime > $mostRecent) {
                    $coin             = Coins::findOne($coin->id); // refresh
                    $coin->lastblock  = $transaction['blockhash'];
                    $coin->save();
                    $mostRecent = $txTime;
                }

                $newBlock             = new Blocks();
                $newBlock->blockhash  = $transaction['blockhash'];
                $newBlock->coin_id    = $coin->id;
                $newBlock->category   = 'immature';
                $newBlock->time       = $txTime;
                $newBlock->amount     = (float) ($transaction['amount'] ?? 0);
                $newBlock->algo       = $coin->algo;

                if (($blockExt['nonce'] ?? 0) != 0) {
                    // PoW block — compute user-facing difficulty from block hash.
                    $newBlock->difficulty_user = Yii::$app->ConversionUtils->hash_to_difficulty($coin, $transaction['blockhash']);
                } elseif ($coin->rpcencoding === 'POS' && ($blockExt['flags'] ?? '') === 'proof-of-stake') {
                    $newBlock->category = 'stake';
                }

                // Masternode earnings: zero-amount generated blocks.
                if (empty($newBlock->userid) &&
                    (!isset($transaction['amount']) || $transaction['amount'] == 0) &&
                    !empty($transaction['generated'])
                ) {
                    $newBlock->algo = 'MN';
                    $rawTx = $remote->getrawtransaction($transaction['txid'], 1);

                    if (isset($rawTx['vout']) && !empty($rawTx['vout'])) {
                        $lastVout          = end($rawTx['vout']);
                        $newBlock->amount  = (float) ($lastVout['value'] ?? 0);
                        Yii::info(sprintf('MN %s %s (%d)', Yii::$app->ConversionUtils->bitcoinvaluetoa($newBlock->amount), $coin->symbol, $blockExt['height']), __CLASS__);
                    }

                    if (!$coin->hasmasternodes) {
                        $coin = Coins::findOne($coin->id); // refresh
                        $coin->hasmasternodes = true;
                        $coin->save();
                    }
                } elseif ($coin->rpcencoding === 'MN') {
                    $newBlock->category = 'generated';
                }

                $newBlock->confirmations    = (int) ($transaction['confirmations'] ?? 0);
                $newBlock->height           = (int) $blockExt['height'];
                $newBlock->difficulty       = (float) $blockExt['difficulty'];
                $newBlock->price            = (float) $coin->price;

                if (!$newBlock->save()) {
                    Yii::error(__FUNCTION__ . ': unable to insert block at height ' . $newBlock->height, __CLASS__);
                }

                $this->distributeReward($coin, $newBlock);
            }
        }

        $elapsed = microtime(true) - $t1;
        Yii::debug(sprintf('%s took %.3f sec', __FUNCTION__, $elapsed), 'performance');
        if ($elapsed > 3.0) {
            Yii::info(sprintf('%s took %.3f sec', __FUNCTION__, $elapsed), __CLASS__);
        }
    }

    /**
     * Recompute and persist aggregate balance fields on each enabled coin record:
     * immature (sum of unconfirmed block amounts), cleared (sum of user balances),
     * and available (wallet balance minus cleared minus pending earnings).
     */
    public function updatePoolBalances(?int $coinId = null): void
    {
        $t1 = microtime(true);
        $db = Yii::$app->db;

        if ($coinId !== null) {
            // When called for a single coin, refresh the wallet balance first.
            $coin   = Coins::findOne($coinId);
            $remote = new WalletRPC($coin);
            $info   = $remote->getinfo();
            if (isset($info['balance'])) {
                $coin->balance = $info['balance'];
                $coin->save();
            }
            $coins = [$coin];
        } else {
            $coins = Coins::find()->where(['enable' => 1])->all();
        }

        foreach ($coins as $coin) {
            $id = (int) $coin->id;

            $coin->immature = (float) $db->createCommand(
                "SELECT SUM(amount) FROM blocks WHERE category='immature' AND coin_id=:id",
                [':id' => $id]
            )->queryScalar();

            $coin->cleared = (float) $db->createCommand(
                'SELECT SUM(balance) FROM accounts WHERE coinid=:id',
                [':id' => $id]
            )->queryScalar();

            $pending = (float) $db->createCommand(
                'SELECT SUM(amount) FROM earnings WHERE status=1 AND coinid=:id',
                [':id' => $id]
            )->queryScalar();

            $coin->available = (float) $coin->balance - $coin->cleared - $pending;
            $coin->save();
        }

        $elapsed = microtime(true) - $t1;
        Yii::debug(sprintf('%s took %.3f sec', __METHOD__, $elapsed), 'performance');
    }

    /**
     * Monitor the BTC wallet for outgoing transactions and notify the admin by email.
     * Intended to run on every main-loop iteration.
     */
    public function monitorBtcWithdrawals(): void
    {
        $coin = Coins::find()->where(['symbol' => 'BTC'])->one();
        if (!$coin) {
            return;
        }

        $remote    = new WalletRPC($coin);
        $lastBlock = $coin->lastblock ?? '';
        $list      = $remote->listsinceblock($lastBlock);
        if (!$list) {
            return;
        }

        $coin->lastblock = $list['lastblock'] ?? $lastBlock;
        $coin->save();

        foreach ($list['transactions'] as $transaction) {
            if (!isset($transaction['blockhash'])) {
                continue;
            }
            if (($transaction['confirmations'] ?? 0) == 0) {
                continue;
            }
            if (($transaction['category'] ?? '') !== 'send') {
                continue;
            }

            $txid  = $transaction['txid'];
            $txUrl = "https://blockchain.info/tx/{$txid}";
            $admin = defined('YIIMP_ADMIN_EMAIL') ? YIIMP_ADMIN_EMAIL : (Yii::$app->params['adminEmail'] ?? '');

            Yii::info("BTC withdrawal detected: {$transaction['amount']} to {$transaction['address']}", __CLASS__);

            if ($admin) {
                // TODO: replace with Yii::$app->mailer once mailer is configured.
                mail($admin, "withdraw {$transaction['amount']}", "<a href='{$txUrl}'>{$transaction['address']}</a>");
            }
        }
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Determine whether a block was mined in solo mode.
     * Checks the block's own `solo` flag first; falls back to querying the
     * worker record when the flag has not yet been set.
     */
    private function isSoloBlock(Blocks $block): bool
    {
        if (!is_null($block->solo)) {
            return (bool) $block->solo;
        }

        return Workers::find()
            ->where([
                'algo'     => $block->algo,
                'userid'   => $block->userid,
                'id'       => $block->workerid,
            ])
            ->andWhere(['like', 'password', 'm=solo'])
            ->exists();
    }

    /**
     * Deduct a fee percentage from an amount.
     *
     * @param float $amount          Gross amount
     * @param string $algo           Mining algorithm (for per-algo fee lookup)
     * @param float $percentOverride Use this percentage instead of the configured rate.
     *                               Pass -1 (default) to use the configured mining fee.
     */
    private function takeFee(float $amount, string $algo, float $percentOverride = -1): float
    {
        $percent = ($percentOverride >= 0) ? $percentOverride : $this->getMiningFeePercent($algo);
        return $amount - ($amount * $percent / 100.0);
    }

    /**
     * Return the configured mining fee percentage for an algorithm.
     * Reads YIIMP_FEES_MINING (defined in serverconfig.php) and caches the result.
     *
     * TODO: extend to read per-algo overrides from params or DB (port of $configFixedPoolFees).
     */
    private function getMiningFeePercent(string $algo): float
    {
        $cacheKey = "YIIMP_fee_{$algo}";
        $cached   = Yii::$app->cache->get($cacheKey);
        if ($cached !== false) {
            return (float) $cached;
        }
        $fee = defined('YIIMP_FEES_MINING') ? (float) YIIMP_FEES_MINING : 0.5;
        Yii::$app->cache->set($cacheKey, $fee, 3600);
        return $fee;
    }

    /**
     * Return the configured solo mining fee percentage.
     * Reads YIIMP_FEES_SOLO (defined in serverconfig.php).
     *
     * TODO: extend to read per-algo solo overrides ($configFixedPoolFeesSolo).
     */
    private function getSoloFeePercent(string $algo): float
    {
        $cacheKey = "YIIMP_fee_solo_{$algo}";
        $cached   = Yii::$app->cache->get($cacheKey);
        if ($cached !== false) {
            return (float) $cached;
        }
        $fee = defined('YIIMP_FEES_SOLO') ? (float) YIIMP_FEES_SOLO : 1.0;
        Yii::$app->cache->set($cacheKey, $fee, 3600);
        return $fee;
    }

    /**
     * Return true when an earning record should be created for a user.
     * Mirrors the condition from the legacy code:
     *   exchange is disabled, OR the user coin matches the mined coin,
     *   OR both coins participate in auto-exchange.
     */
    private function earningAllowed(Coins $minedCoin, ?Coins $userCoin): bool
    {
        if (!$userCoin) {
            return true;
        }
        return !$this->allowExchange()
            || $userCoin->id == $minedCoin->id
            || ($userCoin->auto_exchange && $minedCoin->auto_exchange);
    }

    private function allowExchange(): bool
    {
        return defined('YIIMP_ALLOW_EXCHANGE') ? (bool) YIIMP_ALLOW_EXCHANGE : false;
    }
}
