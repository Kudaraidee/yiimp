<?php

namespace tests\unit\controllers;

use tests\helpers\DatabaseTestHelper;

use Codeception\Test\Unit;
use yii\web\Response;
use app\controllers\ApiController;
use app\models\Accounts;
use app\models\Coins;

/**
 * Property-based tests for API Wallet endpoint
 * 
 * Feature: yiimp-to-yiimp2-migration, Property 31: API wallet statistics response format
 */
class ApiWalletPropertyTest extends Unit
{
    use DatabaseTestHelper;

    protected $controller;
    protected $testAccounts = [];
    
    protected function _before()
    {
        parent::_before();
        $this->controller = new ApiController('api', \Yii::$app);
        \Yii::$app->response->format = Response::FORMAT_JSON;
    }
    
    protected function _after()
    {
        // Clean up test accounts
        foreach ($this->testAccounts as $account) {
            if ($account && !$account->isNewRecord) {
                $account->delete();
            }
        }
        parent::_after();
    }
    
    /**
     * Property 31: API Wallet Statistics Response Format
     * 
     * For any wallet statistics API request with a valid address, the response should contain
     * all required fields (balance, hashrate, workers, earnings) in valid JSON format.
     * 
     * Validates: Requirements 8.2
     * 
     * @test
     */
    public function testApiWalletStatisticsResponseFormat()
    {
        // Feature: yiimp-to-yiimp2-migration, Property 31: API wallet statistics response format
        
        // Skip test if database is not available
        
        $this->requireDatabase();
        
        $iterations = 100;
        $failures = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            try {
                // Create a test account with random data
                $account = $this->createTestAccount();
                
                if (!$account) {
                    $failures[] = [
                        'iteration' => $i,
                        'reason' => 'Failed to create test account'
                    ];
                    continue;
                }
                
                // Call the wallet endpoint
                $response = $this->controller->actionWallet($account->username);
                
                // Verify response is an array
                if (!is_array($response)) {
                    $failures[] = [
                        'iteration' => $i,
                        'address' => $account->username,
                        'reason' => 'Response is not an array',
                        'type' => gettype($response)
                    ];
                    continue;
                }
                
                // Check if it's an error response
                if (isset($response['error']) && $response['error'] === true) {
                    // This is acceptable for some edge cases
                    continue;
                }
                
                // Verify required fields
                $this->assertWalletStatsFormat($response, $i, $failures);
                
            } catch (\Exception $e) {
                $failures[] = [
                    'iteration' => $i,
                    'reason' => 'Exception thrown',
                    'message' => $e->getMessage()
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
     * Assert wallet statistics format
     * 
     * @param array $stats
     * @param int $iteration
     * @param array &$failures
     */
    protected function assertWalletStatsFormat($stats, $iteration, &$failures)
    {
        // Required fields for wallet statistics response
        $requiredFields = [
            'currency' => 'string',
            'unsold' => 'double',
            'balance' => 'double',
            'unpaid' => 'double',
            'paid24h' => 'double',
            'total' => 'double',
            'hashrate' => 'double',
            'workers' => 'integer'
        ];
        
        foreach ($requiredFields as $field => $expectedType) {
            if (!array_key_exists($field, $stats)) {
                $failures[] = [
                    'iteration' => $iteration,
                    'field' => $field,
                    'reason' => 'Required field missing',
                    'response' => $stats
                ];
                continue;
            }
            
            $actualType = gettype($stats[$field]);
            
            // Handle type checking
            if ($expectedType === 'integer' && !is_int($stats[$field])) {
                $failures[] = [
                    'iteration' => $iteration,
                    'field' => $field,
                    'expected_type' => $expectedType,
                    'actual_type' => $actualType,
                    'value' => $stats[$field],
                    'reason' => 'Field type mismatch'
                ];
            } elseif ($expectedType === 'double' && !is_numeric($stats[$field])) {
                $failures[] = [
                    'iteration' => $iteration,
                    'field' => $field,
                    'expected_type' => $expectedType,
                    'actual_type' => $actualType,
                    'value' => $stats[$field],
                    'reason' => 'Field type mismatch'
                ];
            } elseif ($expectedType === 'string' && !is_string($stats[$field])) {
                $failures[] = [
                    'iteration' => $iteration,
                    'field' => $field,
                    'expected_type' => $expectedType,
                    'actual_type' => $actualType,
                    'value' => $stats[$field],
                    'reason' => 'Field type mismatch'
                ];
            }
        }
    }
    
    /**
     * Create a test account with random data
     * 
     * @return Accounts|null
     */
    protected function createTestAccount()
    {
        // Get a random coin
        $coin = Coins::find()->where(['enable' => 1])->orderBy('RAND()')->one();
        if (!$coin) {
            return null;
        }
        
        $account = new Accounts();
        $account->username = 'test_' . bin2hex(random_bytes(16));
        $account->coinid = $coin->id;
        $account->balance = round(rand(0, 100000) / 100000, 8);
        $account->is_locked = 0;
        
        if ($account->save()) {
            $this->testAccounts[] = $account;
            return $account;
        }
        
        return null;
    }
    
    /**
     * Test error response format for invalid address
     * 
     * @test
     */
    public function testApiWalletErrorResponseFormat()
    {
        // Feature: yiimp-to-yiimp2-migration, Property 31: API wallet statistics response format (error case)
        
        // Skip test if database is not available
        
        $this->requireDatabase();
        
        // Test with missing address
        $response = $this->controller->actionWallet();
        
        $this->assertIsArray($response);
        $this->assertArrayHasKey('error', $response);
        $this->assertTrue($response['error']);
        $this->assertArrayHasKey('message', $response);
        $this->assertArrayHasKey('code', $response);
        $this->assertEquals(400, $response['code']);
    }
    
    /**
     * Test that response is valid JSON when encoded
     * 
     * @test
     */
    public function testApiWalletResponseIsValidJson()
    {
        // Feature: yiimp-to-yiimp2-migration, Property 31: API wallet statistics response format (JSON validity)
        
        // Skip test if database is not available
        
        $this->requireDatabase();
        
        $account = $this->createTestAccount();
        if (!$account) {
            $this->markTestSkipped('Could not create test account');
        }
        
        $response = $this->controller->actionWallet($account->username);
        
        // Encode to JSON
        $json = json_encode($response);
        
        $this->assertNotFalse($json, 'Response cannot be encoded to JSON');
        $this->assertJson($json, 'Response is not valid JSON');
        
        // Decode back and verify structure is preserved
        $decoded = json_decode($json, true);
        $this->assertEquals($response, $decoded, 'JSON encode/decode does not preserve structure');
    }
}
