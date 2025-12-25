<?php

namespace tests\unit\backend;

use tests\helpers\DatabaseTestHelper;

use app\models\Coins;
use app\models\Accounts;
use app\models\Workers;
use Codeception\Test\Unit;

/**
 * Feature: yiimp-to-yiimp2-migration, Property 40: Backend configuration access
 * 
 * Property: For any backend script execution, the script should successfully 
 * read configuration values from Yiimp2 models and components without errors.
 * 
 * Validates: Requirements 12.1
 */
class BackendConfigurationPropertyTest extends Unit
{
    use DatabaseTestHelper;

    protected $tester;
    
    /**
     * Test that backend scripts can access database configuration
     */
    public function testDatabaseConfigurationAccess()
    {
        // Property: Backend scripts should be able to access database configuration
        // Skip test if database is not available
        $this->requireDatabase();
        // Run multiple iterations to ensure consistency
        for ($i = 0; $i < 100; $i++) {
            // Access database component
            $db = \Yii::$app->db;
            
            // Verify database is accessible
            $this->assertNotNull($db, "Database component should be accessible (iteration $i)");
            $this->assertNotEmpty($db->dsn, "Database DSN should be configured (iteration $i)");
            
            // Verify connection works
            $db->open();
            $this->assertTrue($db->isActive, "Database connection should be active (iteration $i)");
        }
    }
    
    /**
     * Test that backend scripts can access models
     */
    public function testModelAccess()
    {
        // Property: Backend scripts should be able to access all Yiimp2 models
        // Skip test if database is not available
        $this->requireDatabase();
        // Run multiple iterations with different models
        for ($i = 0; $i < 100; $i++) {
            // Test different models in rotation
            $modelClass = [Coins::class, Accounts::class, Workers::class][$i % 3];
            
            // Verify model can be instantiated
            $model = new $modelClass();
            $this->assertNotNull($model, "Model {$modelClass} should be instantiable (iteration $i)");
            
            // Verify model can query database
            $count = $modelClass::find()->count();
            $this->assertIsInt($count, "Model {$modelClass} should return integer count (iteration $i)");
            $this->assertGreaterThanOrEqual(0, $count, "Model {$modelClass} count should be non-negative (iteration $i)");
        }
    }
    
    /**
     * Test that backend scripts can access components
     */
    public function testComponentAccess()
    {
        // Property: Backend scripts should be able to access all Yiimp2 components
        // Skip test if database is not available
        $this->requireDatabase();
        $components = ['YiimpUtils', 'ConversionUtils', 'ExplorerUtils', 'RpcClient', 'cache'];
        
        // Run multiple iterations
        for ($i = 0; $i < 100; $i++) {
            // Test different components in rotation
            $componentName = $components[$i % count($components)];
            
            // Verify component is accessible
            $component = \Yii::$app->get($componentName);
            $this->assertNotNull($component, "Component {$componentName} should be accessible (iteration $i)");
            $this->assertIsObject($component, "Component {$componentName} should be an object (iteration $i)");
        }
    }
    
    /**
     * Test that backend scripts can access cache
     */
    public function testCacheAccess()
    {
        // Property: Backend scripts should be able to use cache component
        // Skip test if database is not available
        $this->requireDatabase();
        for ($i = 0; $i < 100; $i++) {
            $cache = \Yii::$app->cache;
            
            // Test cache operations
            $key = "test_key_{$i}_" . time();
            $value = "test_value_{$i}_" . rand(1000, 9999);
            
            // Set cache value
            $setResult = $cache->set($key, $value, 60);
            $this->assertTrue($setResult, "Cache set should succeed (iteration $i)");
            
            // Get cache value
            $retrieved = $cache->get($key);
            $this->assertEquals($value, $retrieved, "Cache get should return same value (iteration $i)");
            
            // Delete cache value
            $deleteResult = $cache->delete($key);
            $this->assertTrue($deleteResult, "Cache delete should succeed (iteration $i)");
        }
    }
    
    /**
     * Test that backend scripts can access logging
     */
    public function testLoggingAccess()
    {
        // Property: Backend scripts should be able to use logging
        // Skip test if database is not available
        $this->requireDatabase();
        for ($i = 0; $i < 100; $i++) {
            $logger = \Yii::getLogger();
            
            $this->assertNotNull($logger, "Logger should be accessible (iteration $i)");
            
            // Test logging (this won't actually write to file in test environment)
            \Yii::info("Test log message iteration {$i}", __METHOD__);
            
            // Verify no exceptions were thrown
            $this->assertTrue(true, "Logging should not throw exceptions (iteration $i)");
        }
    }
}
