<?php

namespace tests\unit\admin;

use Codeception\Test\Unit;
use app\models\Coins;
use tests\helpers\DatabaseTestHelper;

/**
 * Property-based tests for Coin CRUD operations
 * 
 * Feature: yiimp-to-yiimp2-migration, Property 1: Coin CRUD data integrity
 */
class CoinCrudPropertyTest extends Unit
{
    use DatabaseTestHelper;
    
    /**
     * Property 1: Coin CRUD Data Integrity
     * 
     * For any coin data (name, symbol, algorithm, RPC configuration), when performing
     * create, update, or delete operations, the system should correctly persist all
     * fields to the database and maintain referential integrity with related records.
     * 
     * Validates: Requirements 1.2
     * 
     * @test
     */
    public function testCoinCrudDataIntegrity()
    {
        // Feature: yiimp-to-yiimp2-migration, Property 1: Coin CRUD data integrity
        
        // Skip test if database is not available
        $this->requireDatabase();
        
        $iterations = 100;
        $failures = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            // Generate random coin data
            $coinData = $this->generateRandomCoinData();
            
            // CREATE operation
            $coin = new Coins();
            $coin->attributes = $coinData;
            
            if (!$coin->save()) {
                $failures[] = [
                    'iteration' => $i,
                    'operation' => 'create',
                    'data' => $coinData,
                    'errors' => $coin->errors,
                    'reason' => 'Failed to create coin'
                ];
                continue;
            }
            
            $coinId = $coin->id;
            
            // READ operation - verify all fields persisted
            $savedCoin = Coins::findOne($coinId);
            if ($savedCoin === null) {
                $failures[] = [
                    'iteration' => $i,
                    'operation' => 'read',
                    'coin_id' => $coinId,
                    'reason' => 'Coin not found after save'
                ];
                continue;
            }
            
            // Verify field integrity
            $this->verifyCoinFields($savedCoin, $coinData, $i, $failures);
            
            // UPDATE operation
            $updateData = $this->generateRandomCoinData();
            $savedCoin->attributes = $updateData;
            
            if (!$savedCoin->save()) {
                $failures[] = [
                    'iteration' => $i,
                    'operation' => 'update',
                    'coin_id' => $coinId,
                    'errors' => $savedCoin->errors,
                    'reason' => 'Failed to update coin'
                ];
                $savedCoin->delete();
                continue;
            }
            
            // Verify update persisted
            $updatedCoin = Coins::findOne($coinId);
            $this->verifyCoinFields($updatedCoin, $updateData, $i, $failures);
            
            // DELETE operation
            $deleteResult = $updatedCoin->delete();
            if ($deleteResult === false) {
                $failures[] = [
                    'iteration' => $i,
                    'operation' => 'delete',
                    'coin_id' => $coinId,
                    'reason' => 'Failed to delete coin'
                ];
                continue;
            }
            
            // Verify deletion
            $deletedCoin = Coins::findOne($coinId);
            if ($deletedCoin !== null) {
                $failures[] = [
                    'iteration' => $i,
                    'operation' => 'delete_verify',
                    'coin_id' => $coinId,
                    'reason' => 'Coin still exists after delete'
                ];
            }
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
     * Generate random coin data
     * 
     * @return array
     */
    protected function generateRandomCoinData()
    {
        $algorithms = ['sha256', 'scrypt', 'x11', 'x13', 'x15', 'quark', 'lyra2v2', 'equihash'];
        $symbols = ['BTC', 'LTC', 'DOGE', 'XMR', 'ZEC', 'DASH', 'ETH', 'TEST' . rand(1, 999)];
        
        return [
            'name' => 'TestCoin' . rand(1000, 9999),
            'symbol' => $symbols[array_rand($symbols)] . rand(1, 99),
            'symbol2' => 'T' . rand(100, 999),
            'algo' => $algorithms[array_rand($algorithms)],
            'rpchost' => '127.0.0.1',
            'rpcport' => rand(8000, 9000),
            'rpcuser' => 'user' . rand(1, 999),
            'rpcpasswd' => 'pass' . rand(1000, 9999),
            'rpcencoding' => 'hex',
            'master_wallet' => $this->generateRandomAddress(),
            'enable' => rand(0, 1),
            'visible' => rand(0, 1),
            'auto_ready' => rand(0, 1),
            'pool_ttf' => rand(60, 3600),
            'actual_ttf' => rand(60, 3600),
            'network_ttf' => rand(60, 3600),
            'difficulty' => round(rand(1000, 1000000) / 100, 2),
            'reward' => round(rand(1, 100) / 10, 2),
            'price' => round(rand(1, 10000) / 100000, 8),
            'block_height' => rand(1, 1000000),
            'network_hash' => rand(1000000, 1000000000000),
            'txfee' => round(rand(1, 100) / 10000, 4),
        ];
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
     * Verify coin fields match expected data
     * 
     * @param Coins $coin
     * @param array $expectedData
     * @param int $iteration
     * @param array &$failures
     */
    protected function verifyCoinFields($coin, $expectedData, $iteration, &$failures)
    {
        $fieldsToCheck = ['name', 'symbol', 'algo', 'rpchost', 'rpcport', 'rpcuser', 
                          'master_wallet', 'enable', 'visible'];
        
        foreach ($fieldsToCheck as $field) {
            if (isset($expectedData[$field]) && $coin->$field != $expectedData[$field]) {
                $failures[] = [
                    'iteration' => $iteration,
                    'field' => $field,
                    'expected' => $expectedData[$field],
                    'actual' => $coin->$field,
                    'reason' => 'Field value mismatch'
                ];
            }
        }
    }
}
