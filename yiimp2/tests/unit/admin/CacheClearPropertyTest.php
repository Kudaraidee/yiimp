<?php

namespace tests\unit\admin;

use Codeception\Test\Unit;
use Yii;

/**
 * Property-based tests for Cache Clear Operation
 * 
 * Feature: yiimp-to-yiimp2-migration, Property 4: Cache clear operation
 */
class CacheClearPropertyTest extends Unit
{
    /**
     * Property 4: Cache Clear Operation
     * 
     * For any memcached clear operation, all cached data should be removed and
     * subsequent requests should fetch fresh data from the database.
     * 
     * Validates: Requirements 1.11
     * 
     * @test
     */
    public function testCacheClearOperation()
    {
        // Feature: yiimp-to-yiimp2-migration, Property 4: Cache clear operation
        
        // Check if cache component is available
        if (!Yii::$app->has('cache')) {
            $this->markTestSkipped('Cache component not configured');
            return;
        }
        
        $cache = Yii::$app->cache;
        
        $iterations = 100;
        $failures = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            // Generate random cache keys and values
            $numKeys = rand(5, 20);
            $testData = [];
            
            for ($j = 0; $j < $numKeys; $j++) {
                $key = 'test_cache_key_' . $i . '_' . $j . '_' . uniqid();
                $value = $this->generateRandomCacheValue();
                $testData[$key] = $value;
            }
            
            // Step 1: Store data in cache
            foreach ($testData as $key => $value) {
                $setResult = $cache->set($key, $value, 3600);
                if (!$setResult) {
                    $failures[] = [
                        'iteration' => $i,
                        'step' => 'set_cache',
                        'key' => $key,
                        'reason' => 'Failed to set cache value'
                    ];
                    continue 2; // Skip to next iteration
                }
            }
            
            // Step 2: Verify data is in cache
            foreach ($testData as $key => $expectedValue) {
                $cachedValue = $cache->get($key);
                if ($cachedValue === false) {
                    $failures[] = [
                        'iteration' => $i,
                        'step' => 'verify_before_clear',
                        'key' => $key,
                        'reason' => 'Cache value not found after setting'
                    ];
                }
            }
            
            // Step 3: Clear cache (flush all)
            $flushResult = $cache->flush();
            if (!$flushResult) {
                $failures[] = [
                    'iteration' => $i,
                    'step' => 'flush_cache',
                    'reason' => 'Cache flush operation returned false'
                ];
                // Clean up test keys manually
                foreach ($testData as $key => $value) {
                    $cache->delete($key);
                }
                continue;
            }
            
            // Step 4: Verify all cached data is removed
            foreach ($testData as $key => $expectedValue) {
                $cachedValue = $cache->get($key);
                if ($cachedValue !== false) {
                    $failures[] = [
                        'iteration' => $i,
                        'step' => 'verify_after_clear',
                        'key' => $key,
                        'cached_value' => $cachedValue,
                        'reason' => 'Cache value still exists after flush'
                    ];
                }
            }
            
            // Step 5: Test that new data can be cached after clear
            $newKey = 'test_after_clear_' . $i . '_' . uniqid();
            $newValue = $this->generateRandomCacheValue();
            
            $setResult = $cache->set($newKey, $newValue, 3600);
            if (!$setResult) {
                $failures[] = [
                    'iteration' => $i,
                    'step' => 'set_after_clear',
                    'key' => $newKey,
                    'reason' => 'Failed to set cache value after flush'
                ];
            } else {
                // Verify new data is retrievable
                $retrievedValue = $cache->get($newKey);
                if ($retrievedValue === false) {
                    $failures[] = [
                        'iteration' => $i,
                        'step' => 'get_after_clear',
                        'key' => $newKey,
                        'reason' => 'Failed to retrieve cache value after flush'
                    ];
                } elseif (!$this->valuesAreEqual($retrievedValue, $newValue)) {
                    $failures[] = [
                        'iteration' => $i,
                        'step' => 'verify_after_clear_value',
                        'key' => $newKey,
                        'expected' => $newValue,
                        'actual' => $retrievedValue,
                        'reason' => 'Retrieved value does not match set value after flush'
                    ];
                }
                
                // Clean up new test key
                $cache->delete($newKey);
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
     * Compare two values for equality (handles arrays and objects)
     * 
     * @param mixed $value1
     * @param mixed $value2
     * @return bool
     */
    protected function valuesAreEqual($value1, $value2)
    {
        // Handle arrays
        if (is_array($value1) && is_array($value2)) {
            if (count($value1) !== count($value2)) {
                return false;
            }
            foreach ($value1 as $key => $val) {
                if (!isset($value2[$key]) || !$this->valuesAreEqual($val, $value2[$key])) {
                    return false;
                }
            }
            return true;
        }
        
        // Handle objects
        if (is_object($value1) && is_object($value2)) {
            $arr1 = (array) $value1;
            $arr2 = (array) $value2;
            return $this->valuesAreEqual($arr1, $arr2);
        }
        
        // Handle scalar values
        return $value1 === $value2;
    }
    
    /**
     * Generate random cache value
     * 
     * @return mixed
     */
    protected function generateRandomCacheValue()
    {
        $types = ['string', 'int', 'float', 'array', 'object'];
        $type = $types[array_rand($types)];
        
        switch ($type) {
            case 'string':
                return 'test_value_' . uniqid() . '_' . rand(1000, 9999);
                
            case 'int':
                return rand(1, 1000000);
                
            case 'float':
                return round(rand(1, 1000000) / 100, 2);
                
            case 'array':
                return [
                    'id' => rand(1, 1000),
                    'name' => 'test_' . uniqid(),
                    'value' => rand(1, 100),
                    'timestamp' => time(),
                ];
                
            case 'object':
                $obj = new \stdClass();
                $obj->id = rand(1, 1000);
                $obj->name = 'test_' . uniqid();
                $obj->value = rand(1, 100);
                return $obj;
                
            default:
                return 'default_value';
        }
    }
}
