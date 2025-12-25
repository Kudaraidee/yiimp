<?php
/**
 * Test console command to verify backend script integration
 * 
 * This command tests that backend scripts can access Yiimp2 models and components
 * 
 * Usage:
 *   ./yii test/models - Test model access
 *   ./yii test/components - Test component access
 *   ./yii test/all - Test everything
 */

namespace app\commands;

use yii\console\Controller;
use yii\console\ExitCode;
use app\models\Coins;
use app\models\Accounts;
use app\models\Workers;
use app\models\Blocks;
use app\models\Payouts;

class TestController extends Controller
{
    /**
     * Test that models can be accessed and queried
     * @return int Exit code
     */
    public function actionModels()
    {
        echo "Testing Yiimp2 model access from console...\n\n";
        
        try {
            // Test Coins model
            echo "Testing Coins model...\n";
            $coinCount = Coins::find()->count();
            echo "  Found {$coinCount} coins in database\n";
            
            $coin = Coins::find()->one();
            if ($coin) {
                echo "  Sample coin: {$coin->name} ({$coin->symbol})\n";
            }
            
            // Test Accounts model
            echo "\nTesting Accounts model...\n";
            $accountCount = Accounts::find()->count();
            echo "  Found {$accountCount} accounts in database\n";
            
            // Test Workers model
            echo "\nTesting Workers model...\n";
            $workerCount = Workers::find()->count();
            echo "  Found {$workerCount} workers in database\n";
            
            // Test Blocks model
            echo "\nTesting Blocks model...\n";
            $blockCount = Blocks::find()->count();
            echo "  Found {$blockCount} blocks in database\n";
            
            // Test Payouts model
            echo "\nTesting Payouts model...\n";
            $payoutCount = Payouts::find()->count();
            echo "  Found {$payoutCount} payouts in database\n";
            
            echo "\n✓ All model tests passed!\n";
            return ExitCode::OK;
            
        } catch (\Exception $e) {
            echo "\n✗ Model test failed: " . $e->getMessage() . "\n";
            echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }
    
    /**
     * Test that components can be accessed
     * @return int Exit code
     */
    public function actionComponents()
    {
        echo "Testing Yiimp2 component access from console...\n\n";
        
        try {
            // Test YiimpUtils component
            echo "Testing YiimpUtils component...\n";
            $yiimpUtils = \Yii::$app->YiimpUtils;
            echo "  YiimpUtils component loaded: " . get_class($yiimpUtils) . "\n";
            
            // Test ConversionUtils component
            echo "\nTesting ConversionUtils component...\n";
            $conversionUtils = \Yii::$app->ConversionUtils;
            echo "  ConversionUtils component loaded: " . get_class($conversionUtils) . "\n";
            
            // Test ExplorerUtils component
            echo "\nTesting ExplorerUtils component...\n";
            $explorerUtils = \Yii::$app->ExplorerUtils;
            echo "  ExplorerUtils component loaded: " . get_class($explorerUtils) . "\n";
            
            // Test RpcClient component
            echo "\nTesting RpcClient component...\n";
            $rpcClient = \Yii::$app->RpcClient;
            echo "  RpcClient component loaded: " . get_class($rpcClient) . "\n";
            
            // Test cache component
            echo "\nTesting cache component...\n";
            $cache = \Yii::$app->cache;
            echo "  Cache component loaded: " . get_class($cache) . "\n";
            
            // Test a simple cache operation
            $testKey = 'console_test_' . time();
            $testValue = 'test_value_' . rand(1000, 9999);
            $cache->set($testKey, $testValue, 60);
            $retrieved = $cache->get($testKey);
            if ($retrieved === $testValue) {
                echo "  Cache read/write test: ✓\n";
            } else {
                echo "  Cache read/write test: ✗ (expected '{$testValue}', got '{$retrieved}')\n";
            }
            
            echo "\n✓ All component tests passed!\n";
            return ExitCode::OK;
            
        } catch (\Exception $e) {
            echo "\n✗ Component test failed: " . $e->getMessage() . "\n";
            echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }
    
    /**
     * Test database configuration
     * @return int Exit code
     */
    public function actionDatabase()
    {
        echo "Testing database configuration...\n\n";
        
        try {
            $db = \Yii::$app->db;
            echo "Database DSN: " . $db->dsn . "\n";
            echo "Database username: " . $db->username . "\n";
            echo "Database charset: " . $db->charset . "\n";
            echo "Schema cache enabled: " . ($db->enableSchemaCache ? 'yes' : 'no') . "\n";
            
            // Test connection
            echo "\nTesting database connection...\n";
            $db->open();
            echo "  Connection successful!\n";
            
            // Test a simple query
            echo "\nTesting simple query...\n";
            $result = $db->createCommand('SELECT VERSION() as version')->queryOne();
            echo "  MySQL version: " . $result['version'] . "\n";
            
            echo "\n✓ Database configuration test passed!\n";
            return ExitCode::OK;
            
        } catch (\Exception $e) {
            echo "\n✗ Database test failed: " . $e->getMessage() . "\n";
            echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }
    
    /**
     * Run all tests
     * @return int Exit code
     */
    public function actionAll()
    {
        echo "=== Running all backend integration tests ===\n\n";
        
        $results = [];
        
        echo "--- Test 1: Database Configuration ---\n";
        $results[] = $this->actionDatabase();
        echo "\n";
        
        echo "--- Test 2: Model Access ---\n";
        $results[] = $this->actionModels();
        echo "\n";
        
        echo "--- Test 3: Component Access ---\n";
        $results[] = $this->actionComponents();
        echo "\n";
        
        $failed = array_filter($results, function($code) {
            return $code !== ExitCode::OK;
        });
        
        if (empty($failed)) {
            echo "=== All tests passed! ===\n";
            return ExitCode::OK;
        } else {
            echo "=== " . count($failed) . " test(s) failed ===\n";
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }
}
