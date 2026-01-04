<?php

namespace tests\unit\models;

use Codeception\Test\Unit;
use app\models\Coins;
use app\models\Accounts;
use app\models\Workers;
use app\models\Blocks;
use app\models\Payouts;
use app\models\Markets;
use app\models\Algos;
use app\models\Stratums;
use app\models\Renters;
use app\models\Orders;
use app\models\Nicehash;
use app\models\Bookmarks;
use app\models\Benchmarks;

/**
 * Property-based tests for database schema compatibility
 * 
 * Feature: yiimp-to-yiimp2-migration
 */
class DatabaseSchemaCompatibilityTest extends Unit
{
    /**
     * Property 38: Database Schema Compatibility
     * 
     * For any Yiimp2 model operation (read, write, update, delete), 
     * the operation should work correctly with the existing database 
     * schema without requiring schema changes.
     * 
     * Validates: Requirements 11.1
     * 
     * @test
     */
    public function testDatabaseSchemaCompatibility()
    {
        // Feature: yiimp-to-yiimp2-migration, Property 38: Database schema compatibility
        
        // This property test verifies that all core models can perform CRUD operations
        // on the existing database schema without modifications
        
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
        
        // Test each core model type
        $modelTests = [
            'Coins' => function() { return $this->testCoinsModel(); },
            'Accounts' => function() { return $this->testAccountsModel(); },
            'Workers' => function() { return $this->testWorkersModel(); },
            'Blocks' => function() { return $this->testBlocksModel(); },
            'Payouts' => function() { return $this->testPayoutsModel(); },
            'Markets' => function() { return $this->testMarketsModel(); },
            'Algos' => function() { return $this->testAlgosModel(); },
            'Stratums' => function() { return $this->testStratumsModel(); },
        ];
        
        foreach ($modelTests as $modelName => $testFunc) {
            for ($i = 0; $i < ($iterations / count($modelTests)); $i++) {
                try {
                    $result = $testFunc();
                    
                    if ($result !== true) {
                        $failures[] = [
                            'model' => $modelName,
                            'iteration' => $i,
                            'error' => $result,
                        ];
                    }
                } catch (\Exception $e) {
                    $failures[] = [
                        'model' => $modelName,
                        'iteration' => $i,
                        'error' => 'Exception: ' . $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ];
                }
            }
        }
        
        // Report failures if any
        if (!empty($failures)) {
            $this->fail(
                "Property test failed in " . count($failures) . " out of $iterations iterations:\n" .
                json_encode($failures, JSON_PRETTY_PRINT)
            );
        }
        
        $this->assertTrue(true, "Property holds for all $iterations iterations across all models");
    }
    
    /**
     * Test Coins model CRUD operations
     * 
     * @return bool|string True if successful, error message otherwise
     */
    protected function testCoinsModel()
    {
        $data = $this->generateRandomCoinData();
        
        // CREATE
        $coin = new Coins();
        $coin->attributes = $data;
        
        if (!$coin->validate()) {
            return 'Coins validation failed: ' . json_encode($coin->errors);
        }
        
        if (!$coin->save()) {
            return 'Coins save failed: ' . json_encode($coin->errors);
        }
        
        $id = $coin->id;
        
        // READ
        $retrieved = Coins::findOne($id);
        if ($retrieved === null) {
            return 'Coins not found after save';
        }
        
        // Verify data integrity
        if ($retrieved->name !== $data['name']) {
            return 'Coins name mismatch';
        }
        if ($retrieved->symbol !== $data['symbol']) {
            return 'Coins symbol mismatch';
        }
        if ($retrieved->algo !== $data['algo']) {
            return 'Coins algo mismatch';
        }
        
        // UPDATE
        $newName = 'Updated_' . $data['name'];
        $retrieved->name = $newName;
        if (!$retrieved->save()) {
            return 'Coins update failed: ' . json_encode($retrieved->errors);
        }
        
        $updated = Coins::findOne($id);
        if ($updated->name !== $newName) {
            return 'Coins update not persisted';
        }
        
        // DELETE
        if (!$updated->delete()) {
            return 'Coins delete failed';
        }
        
        $deleted = Coins::findOne($id);
        if ($deleted !== null) {
            return 'Coins still exists after delete';
        }
        
        return true;
    }
    
    /**
     * Test Accounts model CRUD operations
     * 
     * @return bool|string True if successful, error message otherwise
     */
    protected function testAccountsModel()
    {
        $data = $this->generateRandomAccountData();
        
        // CREATE
        $account = new Accounts();
        $account->attributes = $data;
        
        if (!$account->validate()) {
            return 'Accounts validation failed: ' . json_encode($account->errors);
        }
        
        if (!$account->save()) {
            return 'Accounts save failed: ' . json_encode($account->errors);
        }
        
        $id = $account->id;
        
        // READ
        $retrieved = Accounts::findOne($id);
        if ($retrieved === null) {
            return 'Accounts not found after save';
        }
        
        // Verify data integrity
        if ($retrieved->username !== $data['username']) {
            return 'Accounts username mismatch';
        }
        if (abs($retrieved->balance - $data['balance']) > 0.00000001) {
            return 'Accounts balance mismatch';
        }
        
        // UPDATE
        $newBalance = $data['balance'] + 1.5;
        $retrieved->balance = $newBalance;
        if (!$retrieved->save()) {
            return 'Accounts update failed: ' . json_encode($retrieved->errors);
        }
        
        $updated = Accounts::findOne($id);
        if (abs($updated->balance - $newBalance) > 0.00000001) {
            return 'Accounts update not persisted';
        }
        
        // DELETE
        if (!$updated->delete()) {
            return 'Accounts delete failed';
        }
        
        $deleted = Accounts::findOne($id);
        if ($deleted !== null) {
            return 'Accounts still exists after delete';
        }
        
        return true;
    }
    
    /**
     * Test Workers model CRUD operations
     * 
     * @return bool|string True if successful, error message otherwise
     */
    protected function testWorkersModel()
    {
        // First create an account for the worker
        $accountData = $this->generateRandomAccountData();
        $account = new Accounts();
        $account->attributes = $accountData;
        if (!$account->save()) {
            return 'Failed to create account for worker test';
        }
        
        $data = $this->generateRandomWorkerData($account->id);
        
        // CREATE
        $worker = new Workers();
        $worker->attributes = $data;
        
        if (!$worker->validate()) {
            $account->delete();
            return 'Workers validation failed: ' . json_encode($worker->errors);
        }
        
        if (!$worker->save()) {
            $account->delete();
            return 'Workers save failed: ' . json_encode($worker->errors);
        }
        
        $id = $worker->id;
        
        // READ
        $retrieved = Workers::findOne($id);
        if ($retrieved === null) {
            $account->delete();
            return 'Workers not found after save';
        }
        
        // Verify data integrity
        if ($retrieved->name !== $data['name']) {
            $worker->delete();
            $account->delete();
            return 'Workers name mismatch';
        }
        if ($retrieved->algo !== $data['algo']) {
            $worker->delete();
            $account->delete();
            return 'Workers algo mismatch';
        }
        
        // UPDATE
        $newShares = $data['shares'] + 100;
        $retrieved->shares = $newShares;
        if (!$retrieved->save()) {
            $worker->delete();
            $account->delete();
            return 'Workers update failed: ' . json_encode($retrieved->errors);
        }
        
        $updated = Workers::findOne($id);
        if ($updated->shares !== $newShares) {
            $worker->delete();
            $account->delete();
            return 'Workers update not persisted';
        }
        
        // DELETE
        if (!$updated->delete()) {
            $account->delete();
            return 'Workers delete failed';
        }
        
        $deleted = Workers::findOne($id);
        if ($deleted !== null) {
            $account->delete();
            return 'Workers still exists after delete';
        }
        
        // Clean up account
        $account->delete();
        
        return true;
    }
    
    /**
     * Test Blocks model CRUD operations
     * 
     * @return bool|string True if successful, error message otherwise
     */
    protected function testBlocksModel()
    {
        // Create dependencies
        $coinData = $this->generateRandomCoinData();
        $coin = new Coins();
        $coin->attributes = $coinData;
        if (!$coin->save()) {
            return 'Failed to create coin for block test';
        }
        
        $data = $this->generateRandomBlockData($coin->id);
        
        // CREATE
        $block = new Blocks();
        $block->attributes = $data;
        
        if (!$block->validate()) {
            $coin->delete();
            return 'Blocks validation failed: ' . json_encode($block->errors);
        }
        
        if (!$block->save()) {
            $coin->delete();
            return 'Blocks save failed: ' . json_encode($block->errors);
        }
        
        $id = $block->id;
        
        // READ
        $retrieved = Blocks::findOne($id);
        if ($retrieved === null) {
            $coin->delete();
            return 'Blocks not found after save';
        }
        
        // Verify data integrity
        if ($retrieved->hash !== $data['hash']) {
            $block->delete();
            $coin->delete();
            return 'Blocks hash mismatch';
        }
        if ($retrieved->height !== $data['height']) {
            $block->delete();
            $coin->delete();
            return 'Blocks height mismatch';
        }
        
        // UPDATE
        $newConfirmations = $data['confirmations'] + 10;
        $retrieved->confirmations = $newConfirmations;
        if (!$retrieved->save()) {
            $block->delete();
            $coin->delete();
            return 'Blocks update failed: ' . json_encode($retrieved->errors);
        }
        
        $updated = Blocks::findOne($id);
        if ($updated->confirmations !== $newConfirmations) {
            $block->delete();
            $coin->delete();
            return 'Blocks update not persisted';
        }
        
        // DELETE
        if (!$updated->delete()) {
            $coin->delete();
            return 'Blocks delete failed';
        }
        
        $deleted = Blocks::findOne($id);
        if ($deleted !== null) {
            $coin->delete();
            return 'Blocks still exists after delete';
        }
        
        // Clean up
        $coin->delete();
        
        return true;
    }
    
    /**
     * Test Payouts model CRUD operations
     * 
     * @return bool|string True if successful, error message otherwise
     */
    protected function testPayoutsModel()
    {
        // Create dependencies
        $accountData = $this->generateRandomAccountData();
        $account = new Accounts();
        $account->attributes = $accountData;
        if (!$account->save()) {
            return 'Failed to create account for payout test';
        }
        
        $data = $this->generateRandomPayoutData($account->id);
        
        // CREATE
        $payout = new Payouts();
        $payout->attributes = $data;
        
        if (!$payout->validate()) {
            $account->delete();
            return 'Payouts validation failed: ' . json_encode($payout->errors);
        }
        
        if (!$payout->save()) {
            $account->delete();
            return 'Payouts save failed: ' . json_encode($payout->errors);
        }
        
        $id = $payout->id;
        
        // READ
        $retrieved = Payouts::findOne($id);
        if ($retrieved === null) {
            $account->delete();
            return 'Payouts not found after save';
        }
        
        // Verify data integrity
        if (abs($retrieved->amount - $data['amount']) > 0.00000001) {
            $payout->delete();
            $account->delete();
            return 'Payouts amount mismatch';
        }
        
        // UPDATE
        $newAmount = $data['amount'] + 0.5;
        $retrieved->amount = $newAmount;
        if (!$retrieved->save()) {
            $payout->delete();
            $account->delete();
            return 'Payouts update failed: ' . json_encode($retrieved->errors);
        }
        
        $updated = Payouts::findOne($id);
        if (abs($updated->amount - $newAmount) > 0.00000001) {
            $payout->delete();
            $account->delete();
            return 'Payouts update not persisted';
        }
        
        // DELETE
        if (!$updated->delete()) {
            $account->delete();
            return 'Payouts delete failed';
        }
        
        $deleted = Payouts::findOne($id);
        if ($deleted !== null) {
            $account->delete();
            return 'Payouts still exists after delete';
        }
        
        // Clean up
        $account->delete();
        
        return true;
    }
    
    /**
     * Test Markets model CRUD operations
     * 
     * @return bool|string True if successful, error message otherwise
     */
    protected function testMarketsModel()
    {
        // Create dependencies
        $coinData = $this->generateRandomCoinData();
        $coin = new Coins();
        $coin->attributes = $coinData;
        if (!$coin->save()) {
            return 'Failed to create coin for market test';
        }
        
        $data = $this->generateRandomMarketData($coin->id);
        
        // CREATE
        $market = new Markets();
        $market->attributes = $data;
        
        if (!$market->validate()) {
            $coin->delete();
            return 'Markets validation failed: ' . json_encode($market->errors);
        }
        
        if (!$market->save()) {
            $coin->delete();
            return 'Markets save failed: ' . json_encode($market->errors);
        }
        
        $id = $market->id;
        
        // READ
        $retrieved = Markets::findOne($id);
        if ($retrieved === null) {
            $coin->delete();
            return 'Markets not found after save';
        }
        
        // Verify data integrity
        if ($retrieved->name !== $data['name']) {
            $market->delete();
            $coin->delete();
            return 'Markets name mismatch';
        }
        
        // UPDATE
        $newPrice = $data['price'] * 1.1;
        $retrieved->price = $newPrice;
        if (!$retrieved->save()) {
            $market->delete();
            $coin->delete();
            return 'Markets update failed: ' . json_encode($retrieved->errors);
        }
        
        $updated = Markets::findOne($id);
        if (abs($updated->price - $newPrice) > 0.00000001) {
            $market->delete();
            $coin->delete();
            return 'Markets update not persisted';
        }
        
        // DELETE
        if (!$updated->delete()) {
            $coin->delete();
            return 'Markets delete failed';
        }
        
        $deleted = Markets::findOne($id);
        if ($deleted !== null) {
            $coin->delete();
            return 'Markets still exists after delete';
        }
        
        // Clean up
        $coin->delete();
        
        return true;
    }
    
    /**
     * Test Algos model CRUD operations
     * 
     * @return bool|string True if successful, error message otherwise
     */
    protected function testAlgosModel()
    {
        $data = $this->generateRandomAlgoData();
        
        // CREATE
        $algo = new Algos();
        $algo->attributes = $data;
        
        if (!$algo->validate()) {
            return 'Algos validation failed: ' . json_encode($algo->errors);
        }
        
        if (!$algo->save()) {
            return 'Algos save failed: ' . json_encode($algo->errors);
        }
        
        $id = $algo->id;
        
        // READ
        $retrieved = Algos::findOne($id);
        if ($retrieved === null) {
            return 'Algos not found after save';
        }
        
        // Verify data integrity
        if ($retrieved->name !== $data['name']) {
            return 'Algos name mismatch';
        }
        
        // UPDATE
        $newName = 'Updated_' . $data['name'];
        $retrieved->name = $newName;
        if (!$retrieved->save()) {
            return 'Algos update failed: ' . json_encode($retrieved->errors);
        }
        
        $updated = Algos::findOne($id);
        if ($updated->name !== $newName) {
            return 'Algos update not persisted';
        }
        
        // DELETE
        if (!$updated->delete()) {
            return 'Algos delete failed';
        }
        
        $deleted = Algos::findOne($id);
        if ($deleted !== null) {
            return 'Algos still exists after delete';
        }
        
        return true;
    }
    
    /**
     * Test Stratums model CRUD operations
     * 
     * @return bool|string True if successful, error message otherwise
     */
    protected function testStratumsModel()
    {
        $data = $this->generateRandomStratumData();
        
        // CREATE
        $stratum = new Stratums();
        $stratum->attributes = $data;
        
        if (!$stratum->validate()) {
            return 'Stratums validation failed: ' . json_encode($stratum->errors);
        }
        
        if (!$stratum->save()) {
            return 'Stratums save failed: ' . json_encode($stratum->errors);
        }
        
        $id = $stratum->id;
        
        // READ
        $retrieved = Stratums::findOne($id);
        if ($retrieved === null) {
            return 'Stratums not found after save';
        }
        
        // Verify data integrity
        if ($retrieved->algo !== $data['algo']) {
            return 'Stratums algo mismatch';
        }
        
        // UPDATE
        $newPort = $data['port'] + 1;
        $retrieved->port = $newPort;
        if (!$retrieved->save()) {
            return 'Stratums update failed: ' . json_encode($retrieved->errors);
        }
        
        $updated = Stratums::findOne($id);
        if ($updated->port !== $newPort) {
            return 'Stratums update not persisted';
        }
        
        // DELETE
        if (!$updated->delete()) {
            return 'Stratums delete failed';
        }
        
        $deleted = Stratums::findOne($id);
        if ($deleted !== null) {
            return 'Stratums still exists after delete';
        }
        
        return true;
    }
    
    // Data generation methods
    
    protected function generateRandomCoinData()
    {
        $algorithms = ['sha256', 'scrypt', 'x11', 'x13', 'x15', 'quark', 'lyra2v2', 'equihash'];
        $suffix = substr(md5(microtime()), 0, 8);
        
        return [
            'name' => 'TestCoin_' . $suffix,
            'symbol' => 'TST' . rand(100, 999),
            'algo' => $algorithms[array_rand($algorithms)],
            'enable' => rand(0, 1),
            'visible' => rand(0, 1),
            'auto_ready' => rand(0, 1),
            'difficulty' => rand(1000, 1000000) / 100,
            'reward' => rand(1, 100),
            'price' => rand(1, 10000) / 100000000,
            'block_time' => rand(30, 600),
        ];
    }
    
    protected function generateRandomAccountData()
    {
        $suffix = substr(md5(microtime() . rand()), 0, 16);
        
        return [
            'username' => 'test_wallet_' . $suffix,
            'balance' => rand(0, 100000) / 100000,
            'is_locked' => rand(0, 1),
            'no_fees' => rand(0, 1),
            'donation' => rand(0, 10),
        ];
    }
    
    protected function generateRandomWorkerData($userid)
    {
        $algorithms = ['sha256', 'scrypt', 'x11', 'x13', 'x15', 'quark', 'lyra2v2', 'equihash'];
        $suffix = substr(md5(microtime() . rand()), 0, 8);
        
        return [
            'userid' => $userid,
            'name' => 'worker_' . $suffix,
            'worker' => 'worker_' . $suffix,
            'algo' => $algorithms[array_rand($algorithms)],
            'ip' => rand(1, 255) . '.' . rand(0, 255) . '.' . rand(0, 255) . '.' . rand(1, 255),
            'difficulty' => rand(1, 1000),
            'shares' => rand(0, 10000),
            'time' => time(),
        ];
    }
    
    protected function generateRandomBlockData($coinid)
    {
        $categories = ['immature', 'generate', 'orphan'];
        $algorithms = ['sha256', 'scrypt', 'x11', 'x13', 'x15', 'quark', 'lyra2v2', 'equihash'];
        
        return [
            'coinid' => $coinid,
            'category' => $categories[array_rand($categories)],
            'difficulty' => rand(1000, 1000000) / 100,
            'hash' => bin2hex(random_bytes(32)),
            'height' => rand(1, 1000000),
            'amount' => rand(1, 100),
            'confirmations' => rand(0, 100),
            'time' => time(),
            'algo' => $algorithms[array_rand($algorithms)],
        ];
    }
    
    protected function generateRandomPayoutData($accountId)
    {
        return [
            'account_id' => $accountId,
            'amount' => rand(1, 10000) / 100000,
            'time' => time(),
            'completed' => rand(0, 1),
        ];
    }
    
    protected function generateRandomMarketData($coinid)
    {
        $exchanges = ['bittrex', 'poloniex', 'cryptopia', 'yobit'];
        
        return [
            'coinid' => $coinid,
            'name' => $exchanges[array_rand($exchanges)],
            'price' => rand(1, 10000) / 100000000,
            'lasttraded' => time(),
        ];
    }
    
    protected function generateRandomAlgoData()
    {
        $algorithms = ['sha256', 'scrypt', 'x11', 'x13', 'x15', 'quark', 'lyra2v2', 'equihash'];
        $algo = $algorithms[array_rand($algorithms)] . '_' . rand(1000, 9999);
        
        return [
            'name' => $algo,
        ];
    }
    
    protected function generateRandomStratumData()
    {
        $algorithms = ['sha256', 'scrypt', 'x11', 'x13', 'x15', 'quark', 'lyra2v2', 'equihash'];
        
        return [
            'algo' => $algorithms[array_rand($algorithms)],
            'port' => rand(3000, 9999),
        ];
    }
}
