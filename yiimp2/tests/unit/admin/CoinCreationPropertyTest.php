<?php

namespace tests\unit\admin;

use Codeception\Test\Unit;
use app\models\Coins;
use tests\helpers\DatabaseTestHelper;

/**
 * Property-based tests for coin creation
 * 
 * Feature: yiimp2-admin-panel-fixes, Property 1: Valid coin data creates records
 */
class CoinCreationPropertyTest extends Unit
{
    use DatabaseTestHelper;
    
    /**
     * Property 1: Valid coin data creates records
     * 
     * For any valid coin data submitted to the coin creation form, the system should 
     * create a new coin record in the database and redirect to the coin details page.
     * 
     * Validates: Requirements 1.2
     * 
     * @test
     */
    public function testValidCoinDataCreatesRecords()
    {
        // Feature: yiimp2-admin-panel-fixes, Property 1: Valid coin data creates records
        
        // Skip test if database is not available
        $this->requireDatabase();
        
        $iterations = 100;
        $failures = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            // Generate valid coin data (all required fields present)
            $coinData = $this->generateValidCoinData();
            
            // Create coin model
            $coin = new Coins();
            $coin->attributes = $coinData;
            
            // Attempt to save
            $saveResult = $coin->save();
            
            if (!$saveResult) {
                $failures[] = [
                    'iteration' => $i,
                    'data' => $coinData,
                    'errors' => $coin->errors,
                    'reason' => 'Valid coin data failed to save'
                ];
                continue;
            }
            
            $coinId = $coin->id;
            
            // Verify record was created in database
            $savedCoin = Coins::findOne($coinId);
            if ($savedCoin === null) {
                $failures[] = [
                    'iteration' => $i,
                    'coin_id' => $coinId,
                    'reason' => 'Coin not found in database after save'
                ];
                continue;
            }
            
            // Verify required fields persisted correctly
            if ($savedCoin->name !== $coinData['name']) {
                $failures[] = [
                    'iteration' => $i,
                    'field' => 'name',
                    'expected' => $coinData['name'],
                    'actual' => $savedCoin->name,
                    'reason' => 'Name field mismatch'
                ];
            }
            
            if ($savedCoin->symbol !== $coinData['symbol']) {
                $failures[] = [
                    'iteration' => $i,
                    'field' => 'symbol',
                    'expected' => $coinData['symbol'],
                    'actual' => $savedCoin->symbol,
                    'reason' => 'Symbol field mismatch'
                ];
            }
            
            // Clean up
            $savedCoin->delete();
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
     * Generate valid coin data with all required fields
     * 
     * @return array
     */
    protected function generateValidCoinData()
    {
        $algorithms = ['sha256', 'scrypt', 'x11', 'x13', 'x15', 'quark', 'lyra2v2', 'equihash', 'neoscrypt'];
        
        return [
            'name' => 'TestCoin' . rand(1000, 9999),
            'symbol' => 'TC' . rand(100, 999),
            'algo' => $algorithms[array_rand($algorithms)],
            'rpchost' => '127.0.0.1',
            'rpcport' => rand(8000, 9000),
            'rpcuser' => 'user' . rand(1, 999),
            'rpcpasswd' => 'pass' . rand(1000, 9999),
            'enable' => rand(0, 1),
            'visible' => rand(0, 1),
        ];
    }
}
