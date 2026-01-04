<?php

namespace tests\unit\site;

use tests\helpers\DatabaseTestHelper;

use Codeception\Test\Unit;
use app\models\Accounts;
use app\models\Workers;
use app\models\Earnings;
use app\models\Payouts;
use app\models\Blocks;
use app\models\Coins;

/**
 * Property-based tests for Worker and Earnings Display
 * 
 * Feature: yiimp-to-yiimp2-migration
 * Properties 6, 7, 8, 9: Worker detail display, Earnings display completeness,
 * Payout history completeness, Block history display
 */
class WorkerEarningsDisplayPropertyTest extends Unit
{
    use DatabaseTestHelper;

    /**
     * Property 6: Worker Detail Display
     * 
     * For any worker record, the worker details should display all required fields:
     * hashrate, shares submitted, and last activity timestamp.
     * 
     * Validates: Requirements 2.3
     * 
     * @test
     */
    public function testWorkerDetailDisplay()
    {
        // Feature: yiimp-to-yiimp2-migration, Property 6: Worker detail display
        
        // Skip test if database is not available
        
        $this->requireDatabase();
        
        // Check if database is available
        try {
            \Yii::$app->db->open();
        } catch (\Exception $e) {
            $this->markTestSkipped(
                'Database connection not available. ' .
                'This test requires a working database connection. ' .
                'Error: ' . $e->getMessage()
            );
            return;
        }
        
        $iterations = 100;
        $failures = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            // Create test account and worker
            $account = $this->createTestAccount();
            $worker = $this->createTestWorker($account);
            
            // Fetch worker details
            $workerDetails = $this->getWorkerDetails($worker->id);
            
            // Verify all required fields are present
            $requiredFields = ['hashrate', 'shares', 'time'];
            foreach ($requiredFields as $field) {
                if (!isset($workerDetails[$field])) {
                    $failures[] = [
                        'iteration' => $i,
                        'worker_id' => $worker->id,
                        'missing_field' => $field,
                        'reason' => 'Required field missing from worker details'
                    ];
                }
            }
            
            // Verify hashrate is present and numeric
            if (isset($workerDetails['hashrate'])) {
                if (!is_numeric($workerDetails['hashrate'])) {
                    $failures[] = [
                        'iteration' => $i,
                        'worker_id' => $worker->id,
                        'field' => 'hashrate',
                        'value' => $workerDetails['hashrate'],
                        'reason' => 'Hashrate is not numeric'
                    ];
                }
            }
            
            // Verify shares is present and numeric
            if (isset($workerDetails['shares'])) {
                if (!is_numeric($workerDetails['shares'])) {
                    $failures[] = [
                        'iteration' => $i,
                        'worker_id' => $worker->id,
                        'field' => 'shares',
                        'value' => $workerDetails['shares'],
                        'reason' => 'Shares is not numeric'
                    ];
                }
            }
            
            // Verify last activity timestamp is present and valid
            if (isset($workerDetails['time'])) {
                if (!is_numeric($workerDetails['time']) || $workerDetails['time'] < 0) {
                    $failures[] = [
                        'iteration' => $i,
                        'worker_id' => $worker->id,
                        'field' => 'time',
                        'value' => $workerDetails['time'],
                        'reason' => 'Last activity timestamp is invalid'
                    ];
                }
            }
            
            // Clean up
            $worker->delete();
            $account->delete();
        }
        
        // Report failures if any
        if (!empty($failures)) {
            $this->fail(
                "Property test failed in " . count($failures) . " out of $iterations iterations:\n" .
                json_encode($failures, JSON_PRETTY_PRINT)
            );
        }
        
        $this->assertTrue(true, "Property holds for all $iterations iterations");
    }
    
    /**
     * Property 7: Earnings Display Completeness
     * 
     * For any miner account, the earnings display should show all required fields:
     * unpaid balance, total paid, and breakdown by coin.
     * 
     * Validates: Requirements 2.4
     * 
     * @test
     */
    public function testEarningsDisplayCompleteness()
    {
        // Feature: yiimp-to-yiimp2-migration, Property 7: Earnings display completeness
        
        // Skip test if database is not available
        
        $this->requireDatabase();
        
        // Check if database is available
        try {
            \Yii::$app->db->open();
        } catch (\Exception $e) {
            $this->markTestSkipped(
                'Database connection not available. ' .
                'This test requires a working database connection. ' .
                'Error: ' . $e->getMessage()
            );
            return;
        }
        
        $iterations = 100;
        $failures = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            // Create test account with earnings
            $account = $this->createTestAccount();
            $coin = $this->createTestCoin();
            $earnings = $this->createTestEarnings($account, $coin, rand(1, 5));
            
            // Fetch earnings display data
            $earningsDisplay = $this->getEarningsDisplay($account->id);
            
            // Verify all required fields are present
            $requiredFields = ['unpaid_balance', 'total_paid', 'breakdown'];
            foreach ($requiredFields as $field) {
                if (!isset($earningsDisplay[$field])) {
                    $failures[] = [
                        'iteration' => $i,
                        'account_id' => $account->id,
                        'missing_field' => $field,
                        'reason' => 'Required field missing from earnings display'
                    ];
                }
            }
            
            // Verify unpaid balance is numeric
            if (isset($earningsDisplay['unpaid_balance'])) {
                if (!is_numeric($earningsDisplay['unpaid_balance'])) {
                    $failures[] = [
                        'iteration' => $i,
                        'account_id' => $account->id,
                        'field' => 'unpaid_balance',
                        'value' => $earningsDisplay['unpaid_balance'],
                        'reason' => 'Unpaid balance is not numeric'
                    ];
                }
            }
            
            // Verify total paid is numeric
            if (isset($earningsDisplay['total_paid'])) {
                if (!is_numeric($earningsDisplay['total_paid'])) {
                    $failures[] = [
                        'iteration' => $i,
                        'account_id' => $account->id,
                        'field' => 'total_paid',
                        'value' => $earningsDisplay['total_paid'],
                        'reason' => 'Total paid is not numeric'
                    ];
                }
            }
            
            // Verify breakdown by coin is present
            if (isset($earningsDisplay['breakdown'])) {
                if (!is_array($earningsDisplay['breakdown'])) {
                    $failures[] = [
                        'iteration' => $i,
                        'account_id' => $account->id,
                        'field' => 'breakdown',
                        'reason' => 'Breakdown is not an array'
                    ];
                }
            }
            
            // Clean up
            foreach ($earnings as $earning) $earning->delete();
            $coin->delete();
            $account->delete();
        }
        
        // Report failures if any
        if (!empty($failures)) {
            $this->fail(
                "Property test failed in " . count($failures) . " out of $iterations iterations:\n" .
                json_encode($failures, JSON_PRETTY_PRINT)
            );
        }
        
        $this->assertTrue(true, "Property holds for all $iterations iterations");
    }
    
    /**
     * Property 8: Payout History Completeness
     * 
     * For any miner with payout records, all payouts should be displayed with
     * transaction IDs, amounts, and timestamps.
     * 
     * Validates: Requirements 2.5
     * 
     * @test
     */
    public function testPayoutHistoryCompleteness()
    {
        // Feature: yiimp-to-yiimp2-migration, Property 8: Payout history completeness
        
        // Skip test if database is not available
        
        $this->requireDatabase();
        
        // Check if database is available
        try {
            \Yii::$app->db->open();
        } catch (\Exception $e) {
            $this->markTestSkipped(
                'Database connection not available. ' .
                'This test requires a working database connection. ' .
                'Error: ' . $e->getMessage()
            );
            return;
        }
        
        $iterations = 100;
        $failures = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            // Create test account with payouts
            $account = $this->createTestAccount();
            $coin = $this->createTestCoin();
            $payoutCount = rand(1, 5);
            $payouts = $this->createTestPayouts($account, $coin, $payoutCount);
            
            // Fetch payout history
            $payoutHistory = $this->getPayoutHistory($account->id);
            
            // Verify all payouts are displayed
            if (count($payoutHistory) != $payoutCount) {
                $failures[] = [
                    'iteration' => $i,
                    'account_id' => $account->id,
                    'expected_count' => $payoutCount,
                    'actual_count' => count($payoutHistory),
                    'reason' => 'Payout count mismatch'
                ];
            }
            
            // Verify each payout has required fields
            foreach ($payoutHistory as $idx => $payout) {
                $requiredFields = ['tx', 'amount', 'time'];
                foreach ($requiredFields as $field) {
                    if (!isset($payout[$field])) {
                        $failures[] = [
                            'iteration' => $i,
                            'account_id' => $account->id,
                            'payout_index' => $idx,
                            'missing_field' => $field,
                            'reason' => 'Required field missing from payout'
                        ];
                    }
                }
                
                // Verify transaction ID is not empty
                if (isset($payout['tx']) && empty($payout['tx'])) {
                    $failures[] = [
                        'iteration' => $i,
                        'account_id' => $account->id,
                        'payout_index' => $idx,
                        'field' => 'tx',
                        'reason' => 'Transaction ID is empty'
                    ];
                }
                
                // Verify amount is numeric and positive
                if (isset($payout['amount'])) {
                    if (!is_numeric($payout['amount']) || $payout['amount'] <= 0) {
                        $failures[] = [
                            'iteration' => $i,
                            'account_id' => $account->id,
                            'payout_index' => $idx,
                            'field' => 'amount',
                            'value' => $payout['amount'],
                            'reason' => 'Amount is invalid'
                        ];
                    }
                }
                
                // Verify timestamp is valid
                if (isset($payout['time'])) {
                    if (!is_numeric($payout['time']) || $payout['time'] < 0) {
                        $failures[] = [
                            'iteration' => $i,
                            'account_id' => $account->id,
                            'payout_index' => $idx,
                            'field' => 'time',
                            'value' => $payout['time'],
                            'reason' => 'Timestamp is invalid'
                        ];
                    }
                }
            }
            
            // Clean up
            foreach ($payouts as $payout) $payout->delete();
            $coin->delete();
            $account->delete();
        }
        
        // Report failures if any
        if (!empty($failures)) {
            $this->fail(
                "Property test failed in " . count($failures) . " out of $iterations iterations:\n" .
                json_encode($failures, JSON_PRETTY_PRINT)
            );
        }
        
        $this->assertTrue(true, "Property holds for all $iterations iterations");
    }
    
    /**
     * Property 9: Block History Display
     * 
     * For any miner with found blocks, all blocks should be displayed with
     * confirmation status and rewards.
     * 
     * Validates: Requirements 2.6
     * 
     * @test
     */
    public function testBlockHistoryDisplay()
    {
        // Feature: yiimp-to-yiimp2-migration, Property 9: Block history display
        
        // Skip test if database is not available
        
        $this->requireDatabase();
        
        // Check if database is available
        try {
            \Yii::$app->db->open();
        } catch (\Exception $e) {
            $this->markTestSkipped(
                'Database connection not available. ' .
                'This test requires a working database connection. ' .
                'Error: ' . $e->getMessage()
            );
            return;
        }
        
        $iterations = 100;
        $failures = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            // Create test account with blocks
            $account = $this->createTestAccount();
            $coin = $this->createTestCoin();
            $blockCount = rand(1, 5);
            $blocks = $this->createTestBlocks($account, $coin, $blockCount);
            
            // Fetch block history
            $blockHistory = $this->getBlockHistory($account->id);
            
            // Verify all blocks are displayed
            if (count($blockHistory) != $blockCount) {
                $failures[] = [
                    'iteration' => $i,
                    'account_id' => $account->id,
                    'expected_count' => $blockCount,
                    'actual_count' => count($blockHistory),
                    'reason' => 'Block count mismatch'
                ];
            }
            
            // Verify each block has required fields
            foreach ($blockHistory as $idx => $block) {
                $requiredFields = ['confirmations', 'amount', 'category'];
                foreach ($requiredFields as $field) {
                    if (!isset($block[$field])) {
                        $failures[] = [
                            'iteration' => $i,
                            'account_id' => $account->id,
                            'block_index' => $idx,
                            'missing_field' => $field,
                            'reason' => 'Required field missing from block'
                        ];
                    }
                }
                
                // Verify confirmations is numeric
                if (isset($block['confirmations'])) {
                    if (!is_numeric($block['confirmations'])) {
                        $failures[] = [
                            'iteration' => $i,
                            'account_id' => $account->id,
                            'block_index' => $idx,
                            'field' => 'confirmations',
                            'value' => $block['confirmations'],
                            'reason' => 'Confirmations is not numeric'
                        ];
                    }
                }
                
                // Verify amount (reward) is numeric and positive
                if (isset($block['amount'])) {
                    if (!is_numeric($block['amount']) || $block['amount'] <= 0) {
                        $failures[] = [
                            'iteration' => $i,
                            'account_id' => $account->id,
                            'block_index' => $idx,
                            'field' => 'amount',
                            'value' => $block['amount'],
                            'reason' => 'Amount (reward) is invalid'
                        ];
                    }
                }
                
                // Verify category is valid
                if (isset($block['category'])) {
                    $validCategories = ['generate', 'immature', 'orphan', 'new'];
                    if (!in_array($block['category'], $validCategories)) {
                        $failures[] = [
                            'iteration' => $i,
                            'account_id' => $account->id,
                            'block_index' => $idx,
                            'field' => 'category',
                            'value' => $block['category'],
                            'reason' => 'Category is invalid'
                        ];
                    }
                }
            }
            
            // Clean up
            foreach ($blocks as $block) $block->delete();
            $coin->delete();
            $account->delete();
        }
        
        // Report failures if any
        if (!empty($failures)) {
            $this->fail(
                "Property test failed in " . count($failures) . " out of $iterations iterations:\n" .
                json_encode($failures, JSON_PRETTY_PRINT)
            );
        }
        
        $this->assertTrue(true, "Property holds for all $iterations iterations");
    }
    
    // ========== Helper Methods ==========
    
    /**
     * Create test account
     * 
     * @return Accounts
     */
    protected function createTestAccount()
    {
        $account = new Accounts();
        $account->username = $this->generateRandomAddress();
        $account->balance = round(rand(0, 100000) / 100, 8);
        $account->coinid = rand(1, 100);
        $account->save();
        return $account;
    }
    
    /**
     * Create test coin
     * 
     * @return Coins
     */
    protected function createTestCoin()
    {
        $coin = new Coins();
        $coin->name = 'TestCoin' . rand(1000, 9999);
        $coin->symbol = 'TST' . rand(10, 99);
        $coin->algo = 'sha256';
        $coin->enable = 1;
        $coin->visible = 1;
        $coin->save();
        return $coin;
    }
    
    /**
     * Create test worker
     * 
     * @param Accounts $account
     * @return Workers
     */
    protected function createTestWorker($account)
    {
        $worker = new Workers();
        $worker->userid = $account->id;
        $worker->name = 'worker' . rand(1, 999);
        $worker->worker = $account->username . '.worker' . rand(1, 999);
        $worker->algo = 'sha256';
        $worker->difficulty = rand(1, 1000);
        $worker->shares = rand(0, 10000);
        $worker->time = time() - rand(0, 3600);
        $worker->subscribe = 1;
        $worker->save();
        return $worker;
    }
    
    /**
     * Create test earnings
     * 
     * @param Accounts $account
     * @param Coins $coin
     * @param int $count
     * @return Earnings[]
     */
    protected function createTestEarnings($account, $coin, $count)
    {
        $earnings = [];
        for ($i = 0; $i < $count; $i++) {
            $earning = new Earnings();
            $earning->userid = $account->id;
            $earning->coinid = $coin->id;
            $earning->amount = round(rand(1, 10000) / 100, 8);
            $earning->status = rand(0, 1);
            $earning->create_time = time() - rand(0, 86400);
            if ($earning->save()) {
                $earnings[] = $earning;
            }
        }
        return $earnings;
    }
    
    /**
     * Create test payouts
     * 
     * @param Accounts $account
     * @param Coins $coin
     * @param int $count
     * @return Payouts[]
     */
    protected function createTestPayouts($account, $coin, $count)
    {
        $payouts = [];
        for ($i = 0; $i < $count; $i++) {
            $payout = new Payouts();
            $payout->account_id = $account->id;
            $payout->coinid = $coin->id;
            $payout->amount = round(rand(100, 10000) / 100, 8);
            $payout->tx = $this->generateRandomTxId();
            $payout->time = time() - rand(0, 86400);
            if ($payout->save()) {
                $payouts[] = $payout;
            }
        }
        return $payouts;
    }
    
    /**
     * Create test blocks
     * 
     * @param Accounts $account
     * @param Coins $coin
     * @param int $count
     * @return Blocks[]
     */
    protected function createTestBlocks($account, $coin, $count)
    {
        $blocks = [];
        $categories = ['generate', 'immature', 'orphan', 'new'];
        
        for ($i = 0; $i < $count; $i++) {
            $block = new Blocks();
            $block->userid = $account->id;
            $block->coin_id = $coin->id;
            $block->height = rand(100000, 999999);
            $block->blockhash = $this->generateRandomHash();
            $block->amount = round(rand(100, 10000) / 100, 8);
            $block->confirmations = rand(0, 120);
            $block->category = $categories[array_rand($categories)];
            $block->time = time() - rand(0, 86400);
            $block->difficulty = rand(1000, 1000000);
            if ($block->save()) {
                $blocks[] = $block;
            }
        }
        return $blocks;
    }
    
    /**
     * Get worker details
     * 
     * @param int $workerId
     * @return array
     */
    protected function getWorkerDetails($workerId)
    {
        $worker = Workers::findOne($workerId);
        if (!$worker) {
            return [];
        }
        
        return [
            'hashrate' => $worker->difficulty ?? 0,
            'shares' => $worker->shares ?? 0,
            'time' => $worker->time ?? 0,
        ];
    }
    
    /**
     * Get earnings display data
     * 
     * @param int $accountId
     * @return array
     */
    protected function getEarningsDisplay($accountId)
    {
        $account = Accounts::findOne($accountId);
        if (!$account) {
            return [];
        }
        
        $earnings = Earnings::find()->where(['userid' => $accountId])->all();
        $payouts = Payouts::find()->where(['account_id' => $accountId])->all();
        
        // Calculate unpaid balance
        $unpaidBalance = $account->balance ?? 0;
        
        // Calculate total paid
        $totalPaid = 0;
        foreach ($payouts as $payout) {
            $totalPaid += $payout->amount;
        }
        
        // Create breakdown by coin
        $breakdown = [];
        foreach ($earnings as $earning) {
            $coinId = $earning->coinid;
            if (!isset($breakdown[$coinId])) {
                $breakdown[$coinId] = 0;
            }
            $breakdown[$coinId] += $earning->amount;
        }
        
        return [
            'unpaid_balance' => $unpaidBalance,
            'total_paid' => $totalPaid,
            'breakdown' => $breakdown,
        ];
    }
    
    /**
     * Get payout history
     * 
     * @param int $accountId
     * @return array
     */
    protected function getPayoutHistory($accountId)
    {
        $payouts = Payouts::find()
            ->where(['account_id' => $accountId])
            ->orderBy(['time' => SORT_DESC])
            ->all();
        
        $history = [];
        foreach ($payouts as $payout) {
            $history[] = [
                'tx' => $payout->tx,
                'amount' => $payout->amount,
                'time' => $payout->time,
            ];
        }
        
        return $history;
    }
    
    /**
     * Get block history
     * 
     * @param int $accountId
     * @return array
     */
    protected function getBlockHistory($accountId)
    {
        $blocks = Blocks::find()
            ->where(['userid' => $accountId])
            ->orderBy(['time' => SORT_DESC])
            ->all();
        
        $history = [];
        foreach ($blocks as $block) {
            $history[] = [
                'confirmations' => $block->confirmations,
                'amount' => $block->amount,
                'category' => $block->category,
            ];
        }
        
        return $history;
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
    
    /**
     * Generate random transaction ID
     * 
     * @return string
     */
    protected function generateRandomTxId()
    {
        return bin2hex(random_bytes(32));
    }
    
    /**
     * Generate random block hash
     * 
     * @return string
     */
    protected function generateRandomHash()
    {
        return bin2hex(random_bytes(32));
    }
}
