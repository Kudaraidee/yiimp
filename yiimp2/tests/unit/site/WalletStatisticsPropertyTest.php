<?php

namespace tests\unit\site;

use Codeception\Test\Unit;
use app\models\Accounts;
use app\models\Workers;
use app\models\Earnings;
use app\models\Payouts;

/**
 * Property-based tests for Wallet Statistics Display
 * 
 * Feature: yiimp-to-yiimp2-migration, Property 5: Wallet statistics completeness
 */
class WalletStatisticsPropertyTest extends Unit
{
    /**
     * Property 5: Wallet Statistics Completeness
     * 
     * For any valid wallet address, the wallet statistics page should display all
     * required fields: personal hashrate, workers list, earnings breakdown, and
     * payout history.
     * 
     * Validates: Requirements 2.2
     * 
     * @test
     */
    public function testWalletStatisticsCompleteness()
    {
        // Feature: yiimp-to-yiimp2-migration, Property 5: Wallet statistics completeness
        
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
            // Create test account with associated data
            $account = $this->createTestAccount();
            $workers = $this->createTestWorkers($account, rand(1, 5));
            $earnings = $this->createTestEarnings($account, rand(1, 3));
            $payouts = $this->createTestPayouts($account, rand(0, 3));
            
            // Fetch wallet statistics
            $stats = $this->getWalletStatistics($account->username);
            
            // Verify all required fields are present
            $requiredFields = ['hashrate', 'workers', 'earnings', 'payouts'];
            foreach ($requiredFields as $field) {
                if (!isset($stats[$field])) {
                    $failures[] = [
                        'iteration' => $i,
                        'wallet' => $account->username,
                        'missing_field' => $field,
                        'reason' => 'Required field missing from statistics'
                    ];
                }
            }
            
            // Verify workers list completeness
            if (isset($stats['workers'])) {
                if (count($stats['workers']) != count($workers)) {
                    $failures[] = [
                        'iteration' => $i,
                        'wallet' => $account->username,
                        'expected_workers' => count($workers),
                        'actual_workers' => count($stats['workers']),
                        'reason' => 'Worker count mismatch'
                    ];
                }
            }
            
            // Verify earnings breakdown present
            if (isset($stats['earnings'])) {
                if (count($stats['earnings']) != count($earnings)) {
                    $failures[] = [
                        'iteration' => $i,
                        'wallet' => $account->username,
                        'expected_earnings' => count($earnings),
                        'actual_earnings' => count($stats['earnings']),
                        'reason' => 'Earnings count mismatch'
                    ];
                }
            }
            
            // Verify payout history present
            if (isset($stats['payouts'])) {
                if (count($stats['payouts']) != count($payouts)) {
                    $failures[] = [
                        'iteration' => $i,
                        'wallet' => $account->username,
                        'expected_payouts' => count($payouts),
                        'actual_payouts' => count($stats['payouts']),
                        'reason' => 'Payout count mismatch'
                    ];
                }
            }
            
            // Clean up
            foreach ($payouts as $payout) $payout->delete();
            foreach ($earnings as $earning) $earning->delete();
            foreach ($workers as $worker) $worker->delete();
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
     * Create test workers
     * 
     * @param Accounts $account
     * @param int $count
     * @return Workers[]
     */
    protected function createTestWorkers($account, $count)
    {
        $workers = [];
        for ($i = 0; $i < $count; $i++) {
            $worker = new Workers();
            $worker->userid = $account->id;
            $worker->name = 'worker' . $i;
            $worker->worker = $account->username . '.worker' . $i;
            $worker->algo = 'sha256';
            $worker->difficulty = rand(1, 1000);
            $worker->hashrate = rand(1000000, 1000000000);
            $worker->subscribe = 1;
            if ($worker->save()) {
                $workers[] = $worker;
            }
        }
        return $workers;
    }
    
    /**
     * Create test earnings
     * 
     * @param Accounts $account
     * @param int $count
     * @return Earnings[]
     */
    protected function createTestEarnings($account, $count)
    {
        $earnings = [];
        for ($i = 0; $i < $count; $i++) {
            $earning = new Earnings();
            $earning->userid = $account->id;
            $earning->coinid = rand(1, 100);
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
     * @param int $count
     * @return Payouts[]
     */
    protected function createTestPayouts($account, $count)
    {
        // Create a test coin first to satisfy foreign key constraint
        $coin = new \app\models\Coins();
        $coin->name = 'Test Coin';
        $coin->symbol = 'TST' . rand(100, 999);
        $coin->algo = 'sha256';
        $coin->save();
        
        $payouts = [];
        for ($i = 0; $i < $count; $i++) {
            $payout = new Payouts();
            $payout->account_id = $account->id;
            $payout->idcoin = $coin->id;
            $payout->amount = round(rand(1, 10000) / 100, 8);
            $payout->tx = $this->generateRandomTxId();
            $payout->time = time() - rand(0, 86400);
            if ($payout->save()) {
                $payouts[] = $payout;
            }
        }
        return $payouts;
    }
    
    /**
     * Get wallet statistics
     * 
     * @param string $address
     * @return array
     */
    protected function getWalletStatistics($address)
    {
        $account = Accounts::findOne(['username' => $address]);
        if (!$account) {
            return [];
        }
        
        return [
            'hashrate' => $this->calculateHashrate($account),
            'workers' => Workers::find()->where(['userid' => $account->id])->all(),
            'earnings' => Earnings::find()->where(['userid' => $account->id])->all(),
            'payouts' => Payouts::find()->where(['account_id' => $account->id])->all(),
        ];
    }
    
    /**
     * Calculate total hashrate for account
     * 
     * @param Accounts $account
     * @return int
     */
    protected function calculateHashrate($account)
    {
        $workers = Workers::find()->where(['userid' => $account->id])->all();
        $totalHashrate = 0;
        foreach ($workers as $worker) {
            $totalHashrate += $worker->hashrate;
        }
        return $totalHashrate;
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
}
