<?php

namespace tests\unit\controllers;

use tests\helpers\DatabaseTestHelper;

use Codeception\Test\Unit;
use yii\web\Response;
use app\controllers\ApiController;
use app\models\Accounts;
use app\models\Coins;
use app\models\Workers;
use app\models\Payouts;

/**
 * Unit tests for API WalletEx endpoint
 * 
 * Tests Requirements: 4.1, 4.2, 4.3, 4.4, 4.5
 */
class ApiWalletExTest extends Unit
{
    use DatabaseTestHelper;

    protected $controller;
    protected $testAccounts = [];
    protected $testWorkers = [];
    protected $testPayouts = [];
    
    protected function _before()
    {
        parent::_before();
        $this->controller = new ApiController('api', \Yii::$app);
        \Yii::$app->response->format = Response::FORMAT_JSON;
    }
    
    protected function _after()
    {
        // Clean up test data
        foreach ($this->testPayouts as $payout) {
            if ($payout && !$payout->isNewRecord) {
                $payout->delete();
            }
        }
        foreach ($this->testWorkers as $worker) {
            if ($worker && !$worker->isNewRecord) {
                $worker->delete();
            }
        }
        foreach ($this->testAccounts as $account) {
            if ($account && !$account->isNewRecord) {
                $account->delete();
            }
        }
        parent::_after();
    }
    
    /**
     * Test walletEx with valid wallet address
     * Validates: Requirements 4.1, 4.2
     * 
     * @test
     */
    public function testWalletExWithValidAddress()
    {
        $this->requireDatabase();
        
        // Create test account
        $account = $this->createTestAccount();
        $this->assertNotNull($account, 'Failed to create test account');
        
        // Create test workers
        $worker1 = $this->createTestWorker($account->id, 'sha256');
        $worker2 = $this->createTestWorker($account->id, 'scrypt');
        
        // Call walletEx endpoint
        $response = $this->controller->actionWalletEx($account->username);
        
        // Verify response structure
        $this->assertIsArray($response);
        $this->assertArrayNotHasKey('error', $response, 'Response should not be an error');
        
        // Verify basic wallet fields
        $this->assertArrayHasKey('currency', $response);
        $this->assertArrayHasKey('unsold', $response);
        $this->assertArrayHasKey('balance', $response);
        $this->assertArrayHasKey('unpaid', $response);
        $this->assertArrayHasKey('paid24h', $response);
        $this->assertArrayHasKey('total', $response);
        
        // Verify miners array exists
        $this->assertArrayHasKey('miners', $response);
        $this->assertIsArray($response['miners']);
    }
    
    /**
     * Test walletEx with invalid/missing address
     * Validates: Requirements 4.1, 2.3
     * 
     * @test
     */
    public function testWalletExWithMissingAddress()
    {
        $this->requireDatabase();
        
        // Call without address
        $response = $this->controller->actionWalletEx();
        
        $this->assertIsArray($response);
        $this->assertArrayHasKey('error', $response);
        $this->assertTrue($response['error']);
        $this->assertArrayHasKey('message', $response);
        $this->assertArrayHasKey('code', $response);
        $this->assertEquals(400, $response['code']);
        $this->assertStringContainsString('address', strtolower($response['message']));
    }
    
    /**
     * Test walletEx with locked wallet
     * Validates: Requirements 2.4
     * 
     * @test
     */
    public function testWalletExWithLockedWallet()
    {
        $this->requireDatabase();
        
        // Create locked account
        $account = $this->createTestAccount();
        $account->is_locked = 1;
        $account->save();
        
        // Call walletEx endpoint
        $response = $this->controller->actionWalletEx($account->username);
        
        $this->assertIsArray($response);
        $this->assertArrayHasKey('error', $response);
        $this->assertTrue($response['error']);
        $this->assertEquals(404, $response['code']);
    }
    
    /**
     * Test worker data formatting
     * Validates: Requirements 4.3
     * 
     * @test
     */
    public function testWorkerDataFormatting()
    {
        $this->requireDatabase();
        
        // Create test account and workers
        $account = $this->createTestAccount();
        $worker = $this->createTestWorker($account->id, 'sha256');
        
        // Call walletEx endpoint
        $response = $this->controller->actionWalletEx($account->username);
        
        $this->assertIsArray($response);
        $this->assertArrayHasKey('miners', $response);
        $this->assertNotEmpty($response['miners']);
        
        // Verify worker data structure
        $minerData = $response['miners'][0];
        
        $requiredFields = [
            'version' => 'string',
            'password' => 'string',
            'ID' => 'string',
            'algo' => 'string',
            'difficulty' => 'double',
            'subscribe' => 'integer',
            'accepted' => 'double',
            'rejected' => 'double'
        ];
        
        foreach ($requiredFields as $field => $expectedType) {
            $this->assertArrayHasKey($field, $minerData, "Field '$field' is missing");
            
            if ($expectedType === 'string') {
                $this->assertIsString($minerData[$field], "Field '$field' should be string");
            } elseif ($expectedType === 'integer') {
                $this->assertIsInt($minerData[$field], "Field '$field' should be integer");
            } elseif ($expectedType === 'double') {
                $this->assertTrue(
                    is_numeric($minerData[$field]),
                    "Field '$field' should be numeric (got " . gettype($minerData[$field]) . ")"
                );
            }
        }
    }
    
    /**
     * Test payout history inclusion when enabled
     * Validates: Requirements 4.4, 4.5
     * 
     * @test
     */
    public function testPayoutHistoryWhenEnabled()
    {
        $this->requireDatabase();
        
        // Check if YIIMP_API_PAYOUTS is defined and enabled
        if (!defined('YIIMP_API_PAYOUTS') || !YIIMP_API_PAYOUTS) {
            $this->markTestSkipped('YIIMP_API_PAYOUTS is not enabled');
        }
        
        // Create test account
        $account = $this->createTestAccount();
        
        // Create test payouts
        $payout1 = $this->createTestPayout($account->id);
        $payout2 = $this->createTestPayout($account->id);
        
        // Call walletEx endpoint
        $response = $this->controller->actionWalletEx($account->username);
        
        $this->assertIsArray($response);
        $this->assertArrayHasKey('payouts', $response);
        $this->assertIsArray($response['payouts']);
        
        // Verify payout data structure if payouts exist
        if (!empty($response['payouts'])) {
            $payoutData = $response['payouts'][0];
            
            $this->assertArrayHasKey('time', $payoutData);
            $this->assertArrayHasKey('amount', $payoutData);
            $this->assertArrayHasKey('tx', $payoutData);
            
            $this->assertIsInt($payoutData['time']);
            $this->assertIsString($payoutData['amount']);
            $this->assertIsString($payoutData['tx']);
        }
    }
    
    /**
     * Test that walletEx is superset of wallet
     * Validates: Requirements 4.1, 4.2
     * 
     * @test
     */
    public function testWalletExIsSupersetOfWallet()
    {
        $this->requireDatabase();
        
        // Create test account
        $account = $this->createTestAccount();
        
        // Call both endpoints
        $walletResponse = $this->controller->actionWallet($account->username);
        $walletExResponse = $this->controller->actionWalletEx($account->username);
        
        $this->assertIsArray($walletResponse);
        $this->assertIsArray($walletExResponse);
        
        // Verify all wallet fields are present in walletEx
        $walletFields = ['currency', 'unsold', 'balance', 'unpaid', 'paid24h', 'total'];
        
        foreach ($walletFields as $field) {
            $this->assertArrayHasKey($field, $walletExResponse, "WalletEx missing field: $field");
            
            // Values should match (allowing for small floating point differences)
            if (is_numeric($walletResponse[$field]) && is_numeric($walletExResponse[$field])) {
                $this->assertEqualsWithDelta(
                    $walletResponse[$field],
                    $walletExResponse[$field],
                    0.00000001,
                    "Field '$field' values don't match between wallet and walletEx"
                );
            } else {
                $this->assertEquals(
                    $walletResponse[$field],
                    $walletExResponse[$field],
                    "Field '$field' values don't match between wallet and walletEx"
                );
            }
        }
        
        // Verify walletEx has additional fields
        $this->assertArrayHasKey('miners', $walletExResponse);
    }
    
    /**
     * Test response is valid JSON
     * 
     * @test
     */
    public function testWalletExResponseIsValidJson()
    {
        $this->requireDatabase();
        
        $account = $this->createTestAccount();
        $response = $this->controller->actionWalletEx($account->username);
        
        // Encode to JSON
        $json = json_encode($response);
        
        $this->assertNotFalse($json, 'Response cannot be encoded to JSON');
        $this->assertJson($json, 'Response is not valid JSON');
        
        // Decode back and verify structure is preserved
        $decoded = json_decode($json, true);
        $this->assertEquals($response, $decoded, 'JSON encode/decode does not preserve structure');
    }
    
    // Helper methods
    
    /**
     * Create a test account
     * 
     * @return Accounts|null
     */
    protected function createTestAccount()
    {
        $coin = Coins::find()->where(['enable' => 1])->orderBy('RAND()')->one();
        if (!$coin) {
            return null;
        }
        
        $account = new Accounts();
        $account->username = 'test_walletex_' . bin2hex(random_bytes(8));
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
     * Create a test worker
     * 
     * @param int $userid
     * @param string $algo
     * @return Workers|null
     */
    protected function createTestWorker($userid, $algo = 'sha256')
    {
        $worker = new Workers();
        $worker->userid = $userid;
        $worker->name = 'test_worker_' . bin2hex(random_bytes(4));
        $worker->worker = 'worker_' . bin2hex(random_bytes(4));
        $worker->algo = $algo;
        $worker->version = 'test/1.0';
        $worker->password = 'x';
        $worker->difficulty = rand(1, 1000);
        $worker->subscribe = 1;
        $worker->time = time();
        
        if ($worker->save()) {
            $this->testWorkers[] = $worker;
            return $worker;
        }
        
        return null;
    }
    
    /**
     * Create a test payout
     * 
     * @param int $accountId
     * @return Payouts|null
     */
    protected function createTestPayout($accountId)
    {
        $account = Accounts::findOne($accountId);
        if (!$account) {
            return null;
        }
        
        $payout = new Payouts();
        $payout->account_id = $accountId;
        $payout->idcoin = $account->coinid;
        $payout->time = time() - rand(0, 86400); // Within last 24 hours
        $payout->amount = round(rand(1000, 100000) / 100000, 8);
        $payout->tx = bin2hex(random_bytes(32));
        $payout->completed = 1;
        
        if ($payout->save()) {
            $this->testPayouts[] = $payout;
            return $payout;
        }
        
        return null;
    }
}
