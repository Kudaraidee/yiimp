<?php

namespace tests\integration;

use Codeception\Test\Unit;
use app\models\Accounts;
use app\models\Workers;
use app\models\Shares;
use app\models\Blocks;
use app\models\Earnings;
use app\models\Payouts;
use app\models\Coins;

/**
 * Integration test for mining and payout workflow
 * 
 * Tests end-to-end workflow: Miner submits shares → Shares recorded → Earnings calculated → Payout processed
 */
class MiningPayoutWorkflowTest extends Unit
{
    /**
     * Test complete mining to payout workflow
     * 
     * @test
     */
    public function testMiningPayoutWorkflow()
    {
        // Step 1: Create test coin
        $coin = new Coins();
        $coin->name = 'PayoutTestCoin';
        $coin->symbol = 'PTC';
        $coin->algo = 'sha256';
        $coin->rpchost = '127.0.0.1';
        $coin->rpcport = 8333;
        $coin->rpcuser = 'user';
        $coin->rpcpasswd = 'pass';
        $coin->enable = 1;
        $coin->reward = 50;
        $coin->price = 0.0001;
        $coin->save();
        
        // Step 2: Create miner account
        $account = new Accounts();
        $account->username = $this->generateRandomAddress();
        $account->balance = 0;
        $account->coinid = $coin->id;
        $account->save();
        
        // Step 3: Create worker for miner
        $worker = new Workers();
        $worker->userid = $account->id;
        $worker->name = 'worker1';
        $worker->worker = $account->username . '.worker1';
        $worker->algo = $coin->algo;
        $worker->difficulty = 1024;
        $worker->hashrate = 1000000000; // 1 GH/s
        $worker->subscribe = 1;
        $worker->save();
        
        // Step 4: Simulate share submissions
        $shareCount = 10;
        for ($i = 0; $i < $shareCount; $i++) {
            $share = new Shares();
            $share->userid = $account->id;
            $share->workerid = $worker->id;
            $share->coinid = $coin->id;
            $share->algo = $coin->algo;
            $share->difficulty = $worker->difficulty;
            $share->time = time() - (600 - $i * 60); // Spread over 10 minutes
            $share->valid = 1;
            $share->save();
        }
        
        // Step 5: Verify shares were recorded
        $recordedShares = Shares::find()
            ->where(['userid' => $account->id, 'workerid' => $worker->id])
            ->count();
        $this->assertEquals($shareCount, $recordedShares, 'Not all shares were recorded');
        
        // Step 6: Simulate block found
        $block = new Blocks();
        $block->coin_id = $coin->id;
        $block->userid = $account->id;
        $block->workerid = $worker->id;
        $block->height = 100000;
        $block->blockhash = bin2hex(random_bytes(32));
        $block->amount = $coin->reward;
        $block->difficulty = 1000000;
        $block->time = time();
        $block->algo = $coin->algo;
        $block->category = 'generate';
        $block->confirmations = 0;
        $block->save();
        
        // Step 7: Verify block was recorded
        $recordedBlock = Blocks::findOne(['userid' => $account->id, 'coin_id' => $coin->id]);
        $this->assertNotNull($recordedBlock, 'Block was not recorded');
        $this->assertEquals($coin->reward, $recordedBlock->amount);
        
        // Step 8: Simulate block confirmation and earnings calculation
        $block->confirmations = 120; // Mature block
        $block->category = 'generate';
        $block->save();
        
        // Calculate earnings (simplified)
        $earning = new Earnings();
        $earning->userid = $account->id;
        $earning->coinid = $coin->id;
        $earning->blockid = $block->id;
        $earning->amount = $coin->reward * 0.99; // 1% pool fee
        $earning->status = 0; // Unpaid
        $earning->create_time = time();
        $earning->save();
        
        // Step 9: Verify earnings were calculated
        $recordedEarnings = Earnings::find()
            ->where(['userid' => $account->id, 'coinid' => $coin->id])
            ->sum('amount');
        $this->assertGreaterThan(0, $recordedEarnings, 'No earnings calculated');
        
        // Step 10: Update account balance
        $account->balance = $recordedEarnings;
        $account->save();
        
        // Step 11: Verify account balance updated
        $updatedAccount = Accounts::findOne($account->id);
        $this->assertEquals($recordedEarnings, $updatedAccount->balance, 'Account balance not updated');
        
        // Step 12: Process payout
        $payout = new Payouts();
        $payout->account_id = $account->id;
        $payout->coinid = $coin->id;
        $payout->amount = $account->balance;
        $payout->tx = bin2hex(random_bytes(32)); // Transaction ID
        $payout->time = time();
        $payout->save();
        
        // Step 13: Verify payout was recorded
        $recordedPayout = Payouts::findOne(['account_id' => $account->id]);
        $this->assertNotNull($recordedPayout, 'Payout was not recorded');
        $this->assertEquals($account->balance, $recordedPayout->amount);
        
        // Step 14: Update earnings status to paid
        $earning->status = 1; // Paid
        $earning->save();
        
        // Step 15: Deduct from account balance
        $account->balance = 0;
        $account->save();
        
        // Step 16: Verify final state
        $finalAccount = Accounts::findOne($account->id);
        $this->assertEquals(0, $finalAccount->balance, 'Account balance not zeroed after payout');
        
        $paidEarnings = Earnings::find()
            ->where(['userid' => $account->id, 'status' => 1])
            ->count();
        $this->assertGreaterThan(0, $paidEarnings, 'Earnings not marked as paid');
        
        // Clean up
        $payout->delete();
        $earning->delete();
        $block->delete();
        Shares::deleteAll(['userid' => $account->id]);
        $worker->delete();
        $account->delete();
        $coin->delete();
    }
    
    /**
     * Generate random wallet address
     * 
     * @return string
     */
    protected function generateRandomAddress()
    {
        $chars = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
        $address = '';
        for ($i = 0; $i < 34; $i++) {
            $address .= $chars[rand(0, strlen($chars) - 1)];
        }
        return $address;
    }
}
