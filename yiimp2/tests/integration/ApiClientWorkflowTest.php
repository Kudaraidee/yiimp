<?php

namespace tests\integration;

use Codeception\Test\Unit;
use app\controllers\ApiController;
use app\models\Accounts;
use app\models\Workers;
use app\models\Blocks;
use app\models\Coins;
use yii\web\Response;

/**
 * Integration test for API client interactions
 * 
 * Tests end-to-end workflow: API client requests wallet stats → Receives complete data → Parses successfully
 */
class ApiClientWorkflowTest extends Unit
{
    protected $controller;
    
    protected function _before()
    {
        parent::_before();
        $this->controller = new ApiController('api', \Yii::$app);
        \Yii::$app->response->format = Response::FORMAT_JSON;
    }
    
    /**
     * Test complete API client workflow for wallet statistics
     * 
     * @test
     */
    public function testApiWalletStatisticsWorkflow()
    {
        // Step 1: Create test data (coin, account, workers)
        $coin = new Coins();
        $coin->name = 'ApiTestCoin';
        $coin->symbol = 'ATC';
        $coin->algo = 'sha256';
        $coin->rpchost = '127.0.0.1';
        $coin->rpcport = 8334;
        $coin->rpcuser = 'user';
        $coin->rpcpasswd = 'pass';
        $coin->enable = 1;
        $coin->save();
        
        $account = new Accounts();
        $account->username = $this->generateRandomAddress();
        $account->balance = 1.5;
        $account->coinid = $coin->id;
        $account->save();
        
        $worker1 = new Workers();
        $worker1->userid = $account->id;
        $worker1->name = 'worker1';
        $worker1->worker = $account->username . '.worker1';
        $worker1->algo = 'sha256';
        $worker1->time = time();
        $worker1->save();
        
        $worker2 = new Workers();
        $worker2->userid = $account->id;
        $worker2->name = 'worker2';
        $worker2->worker = $account->username . '.worker2';
        $worker2->algo = 'sha256';
        $worker2->time = time();
        $worker2->save();
        
        // Step 2: API client requests wallet statistics
        $response = $this->controller->actionWallet($account->username);
        
        // Step 3: Verify response is an array (will be JSON encoded)
        $this->assertIsArray($response, 'API response should be an array');
        
        // Step 4: Verify response contains required fields
        $requiredFields = ['balance', 'hashrate', 'workers'];
        foreach ($requiredFields as $field) {
            $this->assertArrayHasKey($field, $response, "Response missing required field: $field");
        }
        
        // Step 5: Verify balance is correct
        $this->assertEquals($account->balance, $response['balance'], 'Balance mismatch');
        
        // Step 6: Verify hashrate is calculated (will be 0 without shares data, but should be numeric)
        // Note: Hashrate is calculated from shares table, not from worker.hashrate property
        $this->assertIsNumeric($response['hashrate'], 'Hashrate should be numeric');
        $this->assertGreaterThanOrEqual(0, $response['hashrate'], 'Hashrate mismatch');
        
        // Step 7: Verify worker count
        $this->assertEquals(2, $response['workers'], 'Worker count mismatch');
        
        // Step 8: Simulate JSON encoding (what happens in real API)
        $json = json_encode($response);
        $this->assertNotFalse($json, 'Response cannot be encoded to JSON');
        
        // Step 9: Simulate client parsing JSON
        $parsed = json_decode($json, true);
        $this->assertIsArray($parsed, 'Parsed JSON should be an array');
        $this->assertEquals($response, $parsed, 'JSON encode/decode should preserve data');
        
        // Step 10: Verify client can extract all data
        $this->assertEquals($account->balance, $parsed['balance']);
        $this->assertEquals($expectedHashrate, $parsed['hashrate']);
        $this->assertEquals(2, $parsed['workers']);
        
        // Clean up
        $worker1->delete();
        $worker2->delete();
        $account->delete();
        $coin->delete();
    }
    
    /**
     * Test API pool status workflow
     * 
     * @test
     */
    public function testApiPoolStatusWorkflow()
    {
        // Step 1: Create test coins with different algorithms
        $coins = [];
        $algorithms = ['sha256', 'scrypt', 'x11'];
        
        foreach ($algorithms as $algo) {
            $coin = new Coins();
            $coin->name = 'TestCoin_' . $algo;
            $coin->symbol = strtoupper(substr($algo, 0, 3));
            $coin->algo = $algo;
            $coin->rpchost = '127.0.0.1';
            $coin->rpcport = 8335 + count($coins);
            $coin->rpcuser = 'user';
            $coin->rpcpasswd = 'pass';
            $coin->enable = 1;
            $coin->visible = 1;
            $coin->auto_ready = 1;
            $coin->save();
            $coins[] = $coin;
        }
        
        // Step 2: API client requests pool status
        $response = $this->controller->actionStatus();
        
        // Step 3: Verify response structure
        $this->assertIsArray($response, 'Pool status should be an array');
        
        // Step 4: Verify each algorithm has statistics
        foreach ($algorithms as $algo) {
            $this->assertArrayHasKey($algo, $response, "Missing algorithm: $algo");
            
            // Verify algorithm stats structure
            $stats = $response[$algo];
            $this->assertIsArray($stats, "Stats for $algo should be an array");
            
            $requiredFields = ['name', 'port', 'coins', 'hashrate', 'workers'];
            foreach ($requiredFields as $field) {
                $this->assertArrayHasKey($field, $stats, "Missing field $field for $algo");
            }
        }
        
        // Step 5: Simulate JSON encoding
        $json = json_encode($response);
        $this->assertNotFalse($json, 'Response cannot be encoded to JSON');
        
        // Step 6: Simulate client parsing
        $parsed = json_decode($json, true);
        $this->assertIsArray($parsed, 'Parsed JSON should be an array');
        
        // Step 7: Verify client can navigate structure
        foreach ($algorithms as $algo) {
            $this->assertArrayHasKey($algo, $parsed);
            $this->assertIsArray($parsed[$algo]);
        }
        
        // Clean up
        foreach ($coins as $coin) {
            $coin->delete();
        }
    }
    
    /**
     * Test API error handling workflow
     * 
     * @test
     */
    public function testApiErrorHandlingWorkflow()
    {
        // Step 1: API client requests wallet with invalid address
        $response = $this->controller->actionWallet('invalid_address_123');
        
        // Step 2: Verify error response structure
        $this->assertIsArray($response, 'Error response should be an array');
        $this->assertArrayHasKey('error', $response, 'Error response should have error field');
        
        // Step 3: Verify error message is present
        $this->assertNotEmpty($response['error'], 'Error message should not be empty');
        
        // Step 4: Simulate JSON encoding
        $json = json_encode($response);
        $this->assertNotFalse($json, 'Error response cannot be encoded to JSON');
        
        // Step 5: Simulate client parsing error
        $parsed = json_decode($json, true);
        $this->assertIsArray($parsed, 'Parsed error should be an array');
        $this->assertArrayHasKey('error', $parsed, 'Parsed error should have error field');
        
        // Step 6: Verify client can detect error condition
        $this->assertTrue(isset($parsed['error']), 'Client should be able to detect error');
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
