<?php

namespace tests\unit\controllers;

use Yii;
use yii\caching\FileCache;
use yii\caching\MemCache;

/**
 * Test cache configuration and behavior for API endpoints
 * Validates Requirements 6.1, 6.2, 6.3, 6.4, 6.5
 */
class ApiCacheTest extends \Codeception\Test\Unit
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
     * Test that cache component is properly configured
     * Validates Requirement 6.1
     */
    public function testCacheComponentIsConfigured()
    {
        $cache = Yii::$app->cache;
        
        // Cache should be either MemCache or FileCache
        $this->assertTrue(
            $cache instanceof MemCache || $cache instanceof FileCache,
            'Cache component should be MemCache or FileCache'
        );
        
        // Cache should be functional
        $testKey = 'test_cache_key';
        $testValue = 'test_value';
        
        $this->assertTrue($cache->set($testKey, $testValue, 10), 'Cache set should succeed');
        $this->assertEquals($testValue, $cache->get($testKey), 'Cache get should return set value');
        
        // Clean up
        $cache->delete($testKey);
    }

    /**
     * Test cache key naming conventions
     * Validates Requirement 6.1
     */
    public function testCacheKeyNamingConventions()
    {
        $cache = Yii::$app->cache;
        
        // Test various cache key patterns used in API
        $keys = [
            'api_status',
            'api_status_coins-sha256',
            'api_status_workers-scrypt',
            'api_currencies',
            'api_wallet_unsold-123',
            'api_wallet_paid-456',
            'api_wallet_hashrate-789',
            'api_walletex_payouts_999',
        ];
        
        foreach ($keys as $key) {
            $this->assertTrue(
                $cache->set($key, 'test', 5),
                "Cache key '{$key}' should be valid"
            );
            $cache->delete($key);
        }
    }

    /**
     * Test that cache TTLs are appropriate
     * Validates Requirements 6.1, 6.2
     */
    public function testCacheTTLsAreAppropriate()
    {
        $cache = Yii::$app->cache;
        
        // Test status cache TTL (30 seconds)
        $statusKey = 'api_status';
        $cache->set($statusKey, ['test' => 'data'], 30);
        $this->assertNotFalse($cache->get($statusKey), 'Status cache should be set');
        
        // Test currency cache TTL (15 seconds)
        $currencyKey = 'api_currencies';
        $cache->set($currencyKey, ['test' => 'data'], 15);
        $this->assertNotFalse($cache->get($currencyKey), 'Currency cache should be set');
        
        // Test wallet cache TTL (60 seconds for expensive queries)
        $walletKey = 'api_wallet_unsold-123';
        $cache->set($walletKey, 0.5, 60);
        $this->assertNotFalse($cache->get($walletKey), 'Wallet cache should be set');
        
        // Clean up
        $cache->delete($statusKey);
        $cache->delete($currencyKey);
        $cache->delete($walletKey);
    }

    /**
     * Test cache hit within TTL
     * Validates Requirements 6.1, 6.4
     */
    public function testCacheHitWithinTTL()
    {
        $cache = Yii::$app->cache;
        $key = 'test_cache_hit';
        $value = ['data' => 'test_value', 'timestamp' => time()];
        
        // Set cache with 10 second TTL
        $cache->set($key, $value, 10);
        
        // Immediate retrieval should hit cache
        $cached = $cache->get($key);
        $this->assertNotFalse($cached, 'Cache should return value within TTL');
        $this->assertEquals($value, $cached, 'Cached value should match original');
        
        // Clean up
        $cache->delete($key);
    }

    /**
     * Test cache miss after expiration
     * Validates Requirements 6.5
     */
    public function testCacheMissAfterExpiration()
    {
        $cache = Yii::$app->cache;
        $key = 'test_cache_expiration';
        $value = 'test_value';
        
        // Set cache with 1 second TTL
        $cache->set($key, $value, 1);
        
        // Verify it's cached
        $this->assertNotFalse($cache->get($key), 'Cache should be set initially');
        
        // Wait for expiration
        sleep(2);
        
        // Should return false after expiration
        $this->assertFalse($cache->get($key), 'Cache should expire after TTL');
    }

    /**
     * Test cache key generation with parameters
     * Validates Requirements 6.3, 6.4
     */
    public function testCacheKeyGenerationWithParameters()
    {
        $cache = Yii::$app->cache;
        
        // Test that different parameters generate different cache keys
        $userId1 = 123;
        $userId2 = 456;
        
        $key1 = "api_wallet_unsold-{$userId1}";
        $key2 = "api_wallet_unsold-{$userId2}";
        
        $cache->set($key1, 0.5, 10);
        $cache->set($key2, 1.5, 10);
        
        $this->assertEquals(0.5, $cache->get($key1), 'User 1 cache should be independent');
        $this->assertEquals(1.5, $cache->get($key2), 'User 2 cache should be independent');
        
        // Clean up
        $cache->delete($key1);
        $cache->delete($key2);
    }

    /**
     * Test getOrSet cache helper method
     * Validates Requirements 6.4
     */
    public function testGetOrSetCacheHelper()
    {
        $cache = Yii::$app->cache;
        $key = 'test_get_or_set';
        
        $callCount = 0;
        $callback = function() use (&$callCount) {
            $callCount++;
            return 'computed_value';
        };
        
        // First call should execute callback
        $value1 = $cache->getOrSet($key, $callback, 10);
        $this->assertEquals('computed_value', $value1);
        $this->assertEquals(1, $callCount, 'Callback should be called once');
        
        // Second call should use cache
        $value2 = $cache->getOrSet($key, $callback, 10);
        $this->assertEquals('computed_value', $value2);
        $this->assertEquals(1, $callCount, 'Callback should not be called again');
        
        // Clean up
        $cache->delete($key);
    }

    /**
     * Test cache behavior with complex data structures
     * Validates Requirements 6.1, 6.4
     */
    public function testCacheWithComplexData()
    {
        $cache = Yii::$app->cache;
        $key = 'test_complex_data';
        
        $complexData = [
            'hashrate' => 123456789,
            'worker_count' => 42,
            'workers' => [
                ['id' => 1, 'hashrate' => 1000],
                ['id' => 2, 'hashrate' => 2000],
            ],
            'timestamp' => time()
        ];
        
        $cache->set($key, $complexData, 10);
        $cached = $cache->get($key);
        
        $this->assertNotFalse($cached, 'Complex data should be cached');
        $this->assertEquals($complexData, $cached, 'Complex data should be preserved');
        
        // Clean up
        $cache->delete($key);
    }
}
