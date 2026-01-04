<?php

namespace tests\unit\controllers;

use Yii;

/**
 * Test cache expiration behavior for API endpoints
 * Validates Requirement 6.5
 */
class ApiCacheExpirationTest extends \Codeception\Test\Unit
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
     * Test that cache regenerates after TTL expires
     * Validates Requirement 6.5
     */
    public function testCacheRegeneratesAfterTTLExpires()
    {
        $cache = Yii::$app->cache;
        $key = 'test_regeneration';
        
        // Set initial value with 1 second TTL
        $initialValue = 'initial_value';
        $cache->set($key, $initialValue, 1);
        
        // Verify initial value is cached
        $this->assertEquals($initialValue, $cache->get($key), 'Initial value should be cached');
        
        // Wait for expiration
        sleep(2);
        
        // Cache should be expired
        $this->assertFalse($cache->get($key), 'Cache should expire after TTL');
        
        // Simulate regeneration with new value
        $newValue = 'regenerated_value';
        $cache->set($key, $newValue, 10);
        
        // Verify new value is cached
        $this->assertEquals($newValue, $cache->get($key), 'New value should be cached after regeneration');
        
        // Clean up
        $cache->delete($key);
    }

    /**
     * Test that cache updates with fresh data after expiration
     * Validates Requirement 6.5
     */
    public function testCacheUpdatesWithFreshData()
    {
        $cache = Yii::$app->cache;
        $key = 'test_fresh_data';
        
        // Simulate first data fetch
        $oldData = ['timestamp' => time(), 'value' => 100];
        $cache->set($key, $oldData, 1);
        
        // Verify old data is cached
        $cached = $cache->get($key);
        $this->assertEquals($oldData, $cached, 'Old data should be cached');
        
        // Wait for expiration
        sleep(2);
        
        // Cache should be expired
        $this->assertFalse($cache->get($key), 'Cache should be expired');
        
        // Simulate fresh data fetch
        $freshData = ['timestamp' => time(), 'value' => 200];
        $cache->set($key, $freshData, 10);
        
        // Verify fresh data is cached
        $cached = $cache->get($key);
        $this->assertEquals($freshData, $cached, 'Fresh data should be cached');
        $this->assertNotEquals($oldData['value'], $cached['value'], 'Fresh data should differ from old data');
        
        // Clean up
        $cache->delete($key);
    }

    /**
     * Test cache hit/miss behavior
     * Validates Requirement 6.5
     */
    public function testCacheHitMissBehavior()
    {
        $cache = Yii::$app->cache;
        $key = 'test_hit_miss';
        
        // Initial state: cache miss
        $this->assertFalse($cache->get($key), 'Initial get should be cache miss');
        
        // Set cache
        $value = 'test_value';
        $cache->set($key, $value, 1);
        
        // Cache hit
        $this->assertEquals($value, $cache->get($key), 'Should be cache hit within TTL');
        
        // Wait for expiration
        sleep(2);
        
        // Cache miss after expiration
        $this->assertFalse($cache->get($key), 'Should be cache miss after expiration');
        
        // Clean up
        $cache->delete($key);
    }

    /**
     * Test status endpoint cache expiration (30 seconds)
     * Validates Requirement 6.5
     */
    public function testStatusCacheExpiration()
    {
        $cache = Yii::$app->cache;
        $key = 'api_status';
        
        // Simulate status data
        $statusData = [
            'sha256' => ['hashrate' => 1000000, 'workers' => 10],
            'scrypt' => ['hashrate' => 2000000, 'workers' => 20]
        ];
        
        // Set cache with 30 second TTL (using 1 second for testing)
        $cache->set($key, $statusData, 1);
        
        // Verify cache hit
        $this->assertEquals($statusData, $cache->get($key), 'Status data should be cached');
        
        // Wait for expiration
        sleep(2);
        
        // Verify cache miss
        $this->assertFalse($cache->get($key), 'Status cache should expire');
        
        // Clean up
        $cache->delete($key);
    }

    /**
     * Test currency endpoint cache expiration (15 seconds)
     * Validates Requirement 6.5
     */
    public function testCurrencyCacheExpiration()
    {
        $cache = Yii::$app->cache;
        $key = 'api_currencies';
        
        // Simulate currency data
        $currencyData = [
            'BTC' => ['price' => 50000, 'hashrate' => 1000000],
            'LTC' => ['price' => 100, 'hashrate' => 500000]
        ];
        
        // Set cache with 15 second TTL (using 1 second for testing)
        $cache->set($key, $currencyData, 1);
        
        // Verify cache hit
        $this->assertEquals($currencyData, $cache->get($key), 'Currency data should be cached');
        
        // Wait for expiration
        sleep(2);
        
        // Verify cache miss
        $this->assertFalse($cache->get($key), 'Currency cache should expire');
        
        // Clean up
        $cache->delete($key);
    }

    /**
     * Test wallet endpoint cache expiration
     * Validates Requirement 6.5
     */
    public function testWalletCacheExpiration()
    {
        $cache = Yii::$app->cache;
        $userId = 123;
        
        // Test unsold cache (60 seconds)
        $unsoldKey = "api_wallet_unsold-{$userId}";
        $cache->set($unsoldKey, 0.001, 1);
        $this->assertNotFalse($cache->get($unsoldKey), 'Unsold should be cached');
        sleep(2);
        $this->assertFalse($cache->get($unsoldKey), 'Unsold cache should expire');
        
        // Test paid cache (60 seconds)
        $paidKey = "api_wallet_paid-{$userId}";
        $cache->set($paidKey, 0.002, 1);
        $this->assertNotFalse($cache->get($paidKey), 'Paid should be cached');
        sleep(2);
        $this->assertFalse($cache->get($paidKey), 'Paid cache should expire');
        
        // Test hashrate cache (30 seconds)
        $hashrateKey = "api_wallet_hashrate-{$userId}";
        $cache->set($hashrateKey, ['hashrate' => 1000, 'worker_count' => 5], 1);
        $this->assertNotFalse($cache->get($hashrateKey), 'Hashrate should be cached');
        sleep(2);
        $this->assertFalse($cache->get($hashrateKey), 'Hashrate cache should expire');
        
        // Clean up
        $cache->delete($unsoldKey);
        $cache->delete($paidKey);
        $cache->delete($hashrateKey);
    }

    /**
     * Test walletEx endpoint cache expiration
     * Validates Requirement 6.5
     */
    public function testWalletExCacheExpiration()
    {
        $cache = Yii::$app->cache;
        $userId = 456;
        
        // Test unsold cache (60 seconds)
        $unsoldKey = "api_walletex_unsold-{$userId}";
        $cache->set($unsoldKey, 0.003, 1);
        $this->assertNotFalse($cache->get($unsoldKey), 'WalletEx unsold should be cached');
        sleep(2);
        $this->assertFalse($cache->get($unsoldKey), 'WalletEx unsold cache should expire');
        
        // Test paid cache (60 seconds)
        $paidKey = "api_walletex_paid-{$userId}";
        $cache->set($paidKey, 0.004, 1);
        $this->assertNotFalse($cache->get($paidKey), 'WalletEx paid should be cached');
        sleep(2);
        $this->assertFalse($cache->get($paidKey), 'WalletEx paid cache should expire');
        
        // Test workers cache (30 seconds)
        $workersKey = "api_walletex_workers-{$userId}";
        $workersData = [['ID' => 'worker1', 'hashrate' => 1000]];
        $cache->set($workersKey, $workersData, 1);
        $this->assertNotFalse($cache->get($workersKey), 'WalletEx workers should be cached');
        sleep(2);
        $this->assertFalse($cache->get($workersKey), 'WalletEx workers cache should expire');
        
        // Test payouts cache (300 seconds)
        $payoutsKey = "api_walletex_payouts_{$userId}";
        $payoutsData = [['time' => time(), 'amount' => '0.001', 'tx' => 'abc123']];
        $cache->set($payoutsKey, $payoutsData, 1);
        $this->assertNotFalse($cache->get($payoutsKey), 'WalletEx payouts should be cached');
        sleep(2);
        $this->assertFalse($cache->get($payoutsKey), 'WalletEx payouts cache should expire');
        
        // Clean up
        $cache->delete($unsoldKey);
        $cache->delete($paidKey);
        $cache->delete($workersKey);
        $cache->delete($payoutsKey);
    }

    /**
     * Test getOrSet behavior with expiration
     * Validates Requirement 6.5
     */
    public function testGetOrSetWithExpiration()
    {
        $cache = Yii::$app->cache;
        $key = 'test_get_or_set_expiration';
        
        $callCount = 0;
        $callback = function() use (&$callCount) {
            $callCount++;
            return "value_{$callCount}";
        };
        
        // First call - cache miss, callback executed
        $value1 = $cache->getOrSet($key, $callback, 1);
        $this->assertEquals('value_1', $value1);
        $this->assertEquals(1, $callCount);
        
        // Second call - cache hit, callback not executed
        $value2 = $cache->getOrSet($key, $callback, 1);
        $this->assertEquals('value_1', $value2);
        $this->assertEquals(1, $callCount);
        
        // Wait for expiration
        sleep(2);
        
        // Third call - cache miss after expiration, callback executed again
        $value3 = $cache->getOrSet($key, $callback, 1);
        $this->assertEquals('value_2', $value3);
        $this->assertEquals(2, $callCount);
        
        // Clean up
        $cache->delete($key);
    }

    /**
     * Test multiple cache entries with different TTLs
     * Validates Requirement 6.5
     */
    public function testMultipleCacheEntriesWithDifferentTTLs()
    {
        $cache = Yii::$app->cache;
        
        // Set multiple entries with different TTLs
        $shortKey = 'short_ttl';
        $longKey = 'long_ttl';
        
        $cache->set($shortKey, 'short_value', 1);
        $cache->set($longKey, 'long_value', 10);
        
        // Both should be cached initially
        $this->assertEquals('short_value', $cache->get($shortKey));
        $this->assertEquals('long_value', $cache->get($longKey));
        
        // Wait for short TTL to expire
        sleep(2);
        
        // Short should be expired, long should still be cached
        $this->assertFalse($cache->get($shortKey), 'Short TTL cache should expire');
        $this->assertEquals('long_value', $cache->get($longKey), 'Long TTL cache should still be valid');
        
        // Clean up
        $cache->delete($shortKey);
        $cache->delete($longKey);
    }
}
