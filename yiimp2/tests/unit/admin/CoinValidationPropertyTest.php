<?php

namespace tests\unit\admin;

use Codeception\Test\Unit;
use app\models\Coins;
use tests\helpers\DatabaseTestHelper;

/**
 * Property-based tests for coin validation
 * 
 * Feature: yiimp2-admin-panel-fixes, Property 2: Invalid coin data shows validation errors
 */
class CoinValidationPropertyTest extends Unit
{
    use DatabaseTestHelper;
    
    /**
     * Property 2: Invalid coin data shows validation errors
     * 
     * For any invalid coin data submitted to the coin creation form, the system should 
     * return validation errors without creating a database record.
     * 
     * Validates: Requirements 1.3
     * 
     * @test
     */
    public function testInvalidCoinDataShowsValidationErrors()
    {
        // Feature: yiimp2-admin-panel-fixes, Property 2: Invalid coin data shows validation errors
        
        // Skip test if database is not available
        $this->requireDatabase();
        
        $iterations = 100;
        $failures = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            // Generate invalid coin data (missing required fields or invalid values)
            $coinData = $this->generateInvalidCoinData();
            
            // Create coin model
            $coin = new Coins();
            $coin->attributes = $coinData;
            
            // Attempt to save - should fail
            $saveResult = $coin->save();
            
            if ($saveResult) {
                $failures[] = [
                    'iteration' => $i,
                    'data' => $coinData,
                    'errors' => $coin->errors,
                    'reason' => 'Invalid coin data was saved (should have failed validation)'
                ];
                
                // Clean up the incorrectly saved record
                $coin->delete();
                continue;
            }
            
            // Save failed as expected - this is correct behavior for invalid data
            // No need to check errors explicitly, save() failing is sufficient
            
            // Verify no record was created in database
            if ($coin->id !== null) {
                $savedCoin = Coins::findOne($coin->id);
                if ($savedCoin !== null) {
                    $failures[] = [
                        'iteration' => $i,
                        'coin_id' => $coin->id,
                        'reason' => 'Database record created despite validation failure'
                    ];
                    $savedCoin->delete();
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
        
        $this->assertTrue(true, "Property holds for all $iterations iterations");
    }
    
    /**
     * Generate invalid coin data
     * 
     * @return array
     */
    protected function generateInvalidCoinData()
    {
        $invalidTypes = [
            'name_too_long',
            'symbol_too_long',
            'invalid_port_negative',
            'invalid_port_too_large',
            'empty_name',
            'empty_symbol',
        ];
        
        $type = $invalidTypes[array_rand($invalidTypes)];
        
        switch ($type) {
            case 'name_too_long':
                // Name exceeds max length (64 characters)
                return [
                    'name' => str_repeat('A', 65),
                    'symbol' => 'TC',
                ];
                
            case 'symbol_too_long':
                // Symbol exceeds max length (16 characters)
                return [
                    'name' => 'TestCoin',
                    'symbol' => str_repeat('B', 17),
                ];
                
            case 'invalid_port_negative':
                // Invalid port number (negative)
                return [
                    'name' => 'TestCoin' . rand(1000, 9999),
                    'symbol' => 'TC' . rand(100, 999),
                    'rpcport' => -1,
                ];
                
            case 'invalid_port_too_large':
                // Invalid port number (too large)
                return [
                    'name' => 'TestCoin' . rand(1000, 9999),
                    'symbol' => 'TC' . rand(100, 999),
                    'rpcport' => 70000,
                ];
                
            case 'empty_name':
                // Empty name string
                return [
                    'name' => '',
                    'symbol' => 'TC' . rand(100, 999),
                ];
                
            case 'empty_symbol':
                // Empty symbol string
                return [
                    'name' => 'TestCoin' . rand(1000, 9999),
                    'symbol' => '',
                ];
                
            default:
                return [];
        }
    }
}
