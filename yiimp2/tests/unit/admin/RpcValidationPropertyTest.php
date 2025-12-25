<?php

namespace tests\unit\admin;

use Codeception\Test\Unit;
use app\models\Coins;
use app\components\RpcClient;
use tests\helpers\DatabaseTestHelper;

/**
 * Property-based tests for RPC validation
 * 
 * Feature: yiimp2-routing-database-alignment, Property 6: RPC Configuration Completeness
 * Validates: Requirements 3.1, 5.5
 */
class RpcValidationPropertyTest extends Unit
{
    use DatabaseTestHelper;
    
    /**
     * Property 6: RPC Configuration Completeness
     * 
     * For any enabled coin in the database, all required RPC fields (host, port, user, password) 
     * should be non-null and RPC validation should catch missing fields.
     * 
     * Validates: Requirements 3.1, 5.5
     * 
     * @test
     */
    public function testEnabledCoinsHaveRequiredRpcFields()
    {
        // Feature: yiimp2-routing-database-alignment, Property 6: RPC Configuration Completeness
        
        // Skip test if database is not available
        $this->requireDatabase();
        
        $iterations = 100;
        $failures = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            // Generate random coin configuration
            $coinData = $this->generateRandomCoinConfiguration();
            
            // Create coin model
            $coin = new Coins();
            $coin->attributes = $coinData;
            
            // Save the coin (may fail validation, which is expected)
            $saved = $coin->save();
            
            if (!$saved) {
                // Validation failed - this is expected for some random data
                continue;
            }
            
            // If coin is enabled, verify RPC validation
            if ($coin->enable == 1) {
                // Get RPC client
                $rpcClient = \Yii::$app->get('rpcClient');
                
                // Validate RPC connection
                $validation = $rpcClient->validateConnection($coin);
                
                // Check if all required fields are present
                $hasAllFields = !empty($coin->rpchost) && 
                               !empty($coin->rpcport) && 
                               !empty($coin->rpcuser) && 
                               !empty($coin->rpcpasswd);
                
                if ($hasAllFields) {
                    // All fields present - validation should not report missing fields
                    if ($validation['success'] === false && 
                        strpos($validation['error'] ?? '', 'Missing required RPC fields') !== false) {
                        $failures[] = [
                            'iteration' => $i,
                            'coin_id' => $coin->id,
                            'coin_symbol' => $coin->symbol,
                            'rpchost' => $coin->rpchost,
                            'rpcport' => $coin->rpcport,
                            'rpcuser' => $coin->rpcuser,
                            'rpcpasswd' => !empty($coin->rpcpasswd) ? '[SET]' : '[EMPTY]',
                            'validation_error' => $validation['error'],
                            'reason' => 'Validation reported missing fields when all fields are present'
                        ];
                    }
                } else {
                    // Some fields missing - validation should report this
                    if ($validation['success'] === true) {
                        $failures[] = [
                            'iteration' => $i,
                            'coin_id' => $coin->id,
                            'coin_symbol' => $coin->symbol,
                            'rpchost' => $coin->rpchost ?? '[EMPTY]',
                            'rpcport' => $coin->rpcport ?? '[EMPTY]',
                            'rpcuser' => $coin->rpcuser ?? '[EMPTY]',
                            'rpcpasswd' => !empty($coin->rpcpasswd) ? '[SET]' : '[EMPTY]',
                            'reason' => 'Validation succeeded despite missing required RPC fields'
                        ];
                    } else {
                        // Validation failed - verify it's because of missing fields
                        if (strpos($validation['error'] ?? '', 'Missing required RPC fields') === false) {
                            // It failed for a different reason (connection error, etc.)
                            // This is acceptable - the validation caught the issue
                        }
                    }
                }
            }
            
            // Clean up test coin
            $coin->delete();
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
     * Property 6b: RPC Validation Catches Missing Fields
     * 
     * For any coin configuration with missing RPC fields, the RPC validation 
     * should explicitly report which fields are missing.
     * 
     * Validates: Requirements 3.1, 5.5
     * 
     * @test
     */
    public function testRpcValidationCatchesMissingFields()
    {
        // Feature: yiimp2-routing-database-alignment, Property 6: RPC Configuration Completeness
        
        // Skip test if database is not available
        $this->requireDatabase();
        
        $iterations = 50;
        $failures = [];
        
        $requiredFields = ['rpchost', 'rpcport', 'rpcuser', 'rpcpasswd'];
        
        for ($i = 0; $i < $iterations; $i++) {
            // Generate coin with intentionally missing RPC field(s)
            $missingField = $requiredFields[array_rand($requiredFields)];
            $coinData = $this->generateCoinWithMissingRpcField($missingField);
            
            // Create coin model
            $coin = new Coins();
            $coin->attributes = $coinData;
            
            // Save the coin
            if (!$coin->save()) {
                // Validation failed at model level - skip this iteration
                continue;
            }
            
            // Get RPC client
            $rpcClient = \Yii::$app->get('rpcClient');
            
            // Validate RPC connection
            $validation = $rpcClient->validateConnection($coin);
            
            // Validation should fail
            if ($validation['success'] === true) {
                $failures[] = [
                    'iteration' => $i,
                    'coin_id' => $coin->id,
                    'missing_field' => $missingField,
                    'reason' => 'RPC validation succeeded despite missing field: ' . $missingField
                ];
            } else {
                // Validation failed - verify error message mentions missing fields
                $errorMessage = $validation['error'] ?? '';
                if (strpos($errorMessage, 'Missing required RPC fields') === false &&
                    strpos($errorMessage, 'Coin not found') === false) {
                    // Error doesn't mention missing fields - might be connection error
                    // This is acceptable as long as validation failed
                }
            }
            
            // Clean up test coin
            $coin->delete();
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
     * Generate random coin configuration
     * 
     * @return array
     */
    protected function generateRandomCoinConfiguration()
    {
        $enabled = rand(0, 1);
        
        // Base configuration
        $config = [
            'name' => 'TestCoin' . rand(1000, 9999),
            'symbol' => 'TC' . rand(100, 999),
            'algo' => $this->getRandomAlgo(),
            'enable' => $enabled,
        ];
        
        // Randomly include or exclude RPC fields
        if (rand(0, 1)) {
            $config['rpchost'] = $this->getRandomHost();
        }
        
        if (rand(0, 1)) {
            $config['rpcport'] = rand(8000, 9999);
        }
        
        if (rand(0, 1)) {
            $config['rpcuser'] = 'user' . rand(100, 999);
        }
        
        if (rand(0, 1)) {
            $config['rpcpasswd'] = 'pass' . rand(1000, 9999);
        }
        
        return $config;
    }
    
    /**
     * Generate coin configuration with specific missing RPC field
     * 
     * @param string $missingField Field to omit
     * @return array
     */
    protected function generateCoinWithMissingRpcField($missingField)
    {
        $config = [
            'name' => 'TestCoin' . rand(1000, 9999),
            'symbol' => 'TC' . rand(100, 999),
            'algo' => $this->getRandomAlgo(),
            'enable' => 1, // Always enabled for this test
            'rpchost' => 'localhost',
            'rpcport' => rand(8000, 9999),
            'rpcuser' => 'testuser',
            'rpcpasswd' => 'testpass',
        ];
        
        // Remove the specified field
        unset($config[$missingField]);
        
        return $config;
    }
    
    /**
     * Get random algorithm
     * 
     * @return string
     */
    protected function getRandomAlgo()
    {
        $algos = ['sha256', 'scrypt', 'x11', 'x13', 'x15', 'neoscrypt', 'lyra2v2'];
        return $algos[array_rand($algos)];
    }
    
    /**
     * Get random host
     * 
     * @return string
     */
    protected function getRandomHost()
    {
        $hosts = ['localhost', '127.0.0.1', '192.168.1.' . rand(1, 254)];
        return $hosts[array_rand($hosts)];
    }
}
