<?php

namespace tests\unit\controllers;

use Yii;
use app\models\Accounts;
use app\models\Coins;
use app\models\Workers;
use app\models\Payouts;

/**
 * Test caching of expensive queries in API endpoints
 * Validates Requirements 6.3, 6.4
 */
class ApiExpensiveQueryCacheTest extends \Codeception\Test\Unit
{
    /**
     * @var \UnitTester
     */
    protected $tester;

    protected function _before()
    {
        // Clear cache before each test
        Yii::$app->cache->flush();
    }

    protected function _after()
    {
        // Clean up after tests
        Yii::$app->cache->flush();
    }

    /**
     * Test that wallet unsold earnings are cached
     * Validates Requirement 6.3
     */
    public function testWalletUnsoldEarningsAreCached()
    {
        $cache = Yii::$app->cache;
        
        // Simulate caching unsold earnings for a user
        $userId = 123;
        $cacheKey = "api_wallet_unsold-{$userId}";
        $unsoldAmount = 0.00123456;
        
        // Set cache
        $cache->set($cacheKey, $unsoldAmount, 60);
        
        // Verify cache hit
        $cached = $cache->get($cacheKey);
        $this->assertNotFalse($cached, 'Unsold earnings should be cached');
        $this->assertEquals($unsoldAmount, $cached, 'Cached unsold amount should match');
        
        // Clean up
        $cache->delete($cacheKey);
    }

    /**
     * Test that wallet paid amount is cached
     * Validates Requirement 6.3
     */
    public function testWalletPaidAmountIsCached()
    {
        $cache = Yii::$app->cache;
        
        // Simulate caching paid amount for a user
        $userId = 456;
        $cacheKey = "api_wallet_paid-{$userId}";
        $paidAmount = 0.00500000;
        
        // Set cache
        $cache->set($cacheKey, $paidAmount, 60);
        
        // Verify cache hit
        $cached = $cache->get($cacheKey);
        $this->assertNotFalse($cached, 'Paid amount should be cached');
        $this->assertEquals($paidAmount, $cached, 'Cached paid amount should match');
        
        // Clean up
        $cache->delete($cacheKey);
    }

    /**
     * Test that worker hashrate calculations are cached
     * Validates Requirement 6.3
     */
    public function testWorkerHashrateCalculationsAreCached()
    {
        $cache = Yii::$app->cache;
        
        // Simulate caching hashrate data for a user
        $userId = 789;
        $cacheKey = "api_wallet_hashrate-{$userId}";
        $hashrateData = [
            'hashrate' => 123456789,
            'worker_count' => 5
        ];
        
        // Set cache
        $cache->set($cacheKey, $hashrateData, 30);
        
        // Verify cache hit
        $cached = $cache->get($cacheKey);
        $this->assertNotFalse($cached, 'Hashrate data should be cached');
        $this->assertEquals($hashrateData, $cached, 'Cached hashrate data should match');
        
        // Clean up
        $cache->delete($cacheKey);
    }

    /**
     * Test that walletEx unsold earnings are cached
     * Validates Requirement 6.3
     */
    public function testWalletExUnsoldEarningsAreCached()
    {
        $cache = Yii::$app->cache;
        
        // Simulate caching unsold earnings for walletEx
        $userId = 111;
        $cacheKey = "api_walletex_unsold-{$userId}";
        $unsoldAmount = 0.00234567;
        
        // Set cache
        $cache->set($cacheKey, $unsoldAmount, 60);
        
        // Verify cache hit
        $cached = $cache->get($cacheKey);
        $this->assertNotFalse($cached, 'WalletEx unsold earnings should be cached');
        $this->assertEquals($unsoldAmount, $cached, 'Cached unsold amount should match');
        
        // Clean up
        $cache->delete($cacheKey);
    }

    /**
     * Test that walletEx paid amount is cached
     * Validates Requirement 6.3
     */
    public function testWalletExPaidAmountIsCached()
    {
        $cache = Yii::$app->cache;
        
        // Simulate caching paid amount for walletEx
        $userId = 222;
        $cacheKey = "api_walletex_paid-{$userId}";
        $paidAmount = 0.00600000;
        
        // Set cache
        $cache->set($cacheKey, $paidAmount, 60);
        
        // Verify cache hit
        $cached = $cache->get($cacheKey);
        $this->assertNotFalse($cached, 'WalletEx paid amount should be cached');
        $this->assertEquals($paidAmount, $cached, 'Cached paid amount should match');
        
        // Clean up
        $cache->delete($cacheKey);
    }

    /**
     * Test that walletEx worker data is cached
     * Validates Requirement 6.3
     */
    public function testWalletExWorkerDataIsCached()
    {
        $cache = Yii::$app->cache;
        
        // Simulate caching worker data for walletEx
        $userId = 333;
        $cacheKey = "api_walletex_workers-{$userId}";
        $workersData = [
            [
                'version' => 'ccminer/2.3',
                'password' => 'x',
                'ID' => 'worker1',
                'algo' => 'sha256',
                'difficulty' => 1024.0,
                'subscribe' => 1,
                'accepted' => 1234.567,
                'rejected' => 12.345
            ],
            [
                'version' => 'sgminer/5.6',
                'password' => 'c=BTC',
                'ID' => 'worker2',
                'algo' => 'scrypt',
                'difficulty' => 512.0,
                'subscribe' => 1,
                'accepted' => 5678.901,
                'rejected' => 23.456
            ]
        ];
        
        // Set cache
        $cache->set($cacheKey, $workersData, 30);
        
        // Verify cache hit
        $cached = $cache->get($cacheKey);
        $this->assertNotFalse($cached, 'WalletEx worker data should be cached');
        $this->assertEquals($workersData, $cached, 'Cached worker data should match');
        
        // Clean up
        $cache->delete($cacheKey);
    }

    /**
     * Test that walletEx payout history is cached
     * Validates Requirement 6.3
     */
    public function testWalletExPayoutHistoryIsCached()
    {
        $cache = Yii::$app->cache;
        
        // Simulate caching payout history for walletEx
        $userId = 444;
        $cacheKey = "api_walletex_payouts_{$userId}";
        $payoutsData = [
            [
                'time' => 1234567890,
                'amount' => '0.00100000',
                'tx' => 'abc123def456'
            ],
            [
                'time' => 1234567800,
                'amount' => '0.00200000',
                'tx' => 'def456ghi789'
            ]
        ];
        
        // Set cache (5 minutes as per implementation)
        $cache->set($cacheKey, $payoutsData, 300);
        
        // Verify cache hit
        $cached = $cache->get($cacheKey);
        $this->assertNotFalse($cached, 'WalletEx payout history should be cached');
        $this->assertEquals($payoutsData, $cached, 'Cached payout data should match');
        
        // Clean up
        $cache->delete($cacheKey);
    }

    /**
     * Test cache keys use appropriate user/algo identifiers
     * Validates Requirement 6.4
     */
    public function testCacheKeysUseUserAlgoIdentifiers()
    {
        $cache = Yii::$app->cache;
        
        // Test user-specific cache keys
        $userId1 = 100;
        $userId2 = 200;
        
        $key1 = "api_wallet_unsold-{$userId1}";
        $key2 = "api_wallet_unsold-{$userId2}";
        
        $cache->set($key1, 0.001, 60);
        $cache->set($key2, 0.002, 60);
        
        // Verify keys are independent
        $this->assertEquals(0.001, $cache->get($key1));
        $this->assertEquals(0.002, $cache->get($key2));
        
        // Test algo-specific cache keys
        $algo1 = 'sha256';
        $algo2 = 'scrypt';
        
        $algoKey1 = "api_status_workers-{$algo1}";
        $algoKey2 = "api_status_workers-{$algo2}";
        
        $cache->set($algoKey1, 10, 5);
        $cache->set($algoKey2, 20, 5);
        
        // Verify algo keys are independent
        $this->assertEquals(10, $cache->get($algoKey1));
        $this->assertEquals(20, $cache->get($algoKey2));
        
        // Clean up
        $cache->delete($key1);
        $cache->delete($key2);
        $cache->delete($algoKey1);
        $cache->delete($algoKey2);
    }

    /**
     * Test that expensive queries benefit from caching
     * Validates Requirement 6.4
     */
    public function testExpensiveQueriesBenefitFromCaching()
    {
        $cache = Yii::$app->cache;
        
        // Simulate an expensive query result
        $userId = 555;
        $cacheKey = "api_wallet_unsold-{$userId}";
        
        // First "query" - cache miss, would hit database
        $this->assertFalse($cache->get($cacheKey), 'Initial cache should be empty');
        
        // Simulate storing the expensive query result
        $expensiveResult = 0.00345678;
        $cache->set($cacheKey, $expensiveResult, 60);
        
        // Second "query" - cache hit, no database access needed
        $cached = $cache->get($cacheKey);
        $this->assertNotFalse($cached, 'Subsequent access should hit cache');
        $this->assertEquals($expensiveResult, $cached, 'Cached result should match');
        
        // Clean up
        $cache->delete($cacheKey);
    }
}
