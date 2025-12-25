<?php
/**
 * Exchange test console command
 * 
 * This command tests that exchange scripts can use Yiimp2 RPC components
 * 
 * Usage:
 *   ./yii exchange-test/check - Check exchange model access
 *   ./yii exchange-test/rpc - Test RPC component access
 *   ./yii exchange-test/markets - Test market data access
 */

namespace app\commands;

use yii\console\Controller;
use yii\console\ExitCode;
use app\models\Markets;
use app\models\Coins;
use app\models\Balances;

class ExchangeTestController extends Controller
{
    /**
     * Check that exchange-related models can be accessed
     * @return int Exit code
     */
    public function actionCheck()
    {
        echo "Testing exchange script model access...\n\n";
        
        try {
            // Test Markets model
            echo "Testing Markets model...\n";
            $marketCount = Markets::find()->count();
            echo "  Total markets in database: {$marketCount}\n";
            
            $activeMarkets = Markets::find()
                ->where(['>', 'price', 0])
                ->count();
            echo "  Active markets (price > 0): {$activeMarkets}\n";
            
            // Test Coins model for exchange configuration
            echo "\nTesting Coins model for exchange configuration...\n";
            $coinsWithMarkets = Coins::find()
                ->where(['enable' => 1])
                ->andWhere(['>', 'id', 0])
                ->count();
            echo "  Enabled coins: {$coinsWithMarkets}\n";
            
            // Test Balances model
            echo "\nTesting Balances model...\n";
            $balanceCount = Balances::find()->count();
            echo "  Total balance records: {$balanceCount}\n";
            
            echo "\n✓ Exchange model access test passed!\n";
            return ExitCode::OK;
            
        } catch (\Exception $e) {
            echo "\n✗ Exchange model test failed: " . $e->getMessage() . "\n";
            echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }
    
    /**
     * Test RPC component access for exchange operations
     * @return int Exit code
     */
    public function actionRpc()
    {
        echo "Testing RPC component access for exchange operations...\n\n";
        
        try {
            // Test RpcClient component
            echo "Testing RpcClient component...\n";
            $rpcClient = \Yii::$app->RpcClient;
            echo "  RpcClient component loaded: " . get_class($rpcClient) . "\n";
            
            // Test ExplorerUtils component (used for blockchain queries)
            echo "\nTesting ExplorerUtils component...\n";
            $explorerUtils = \Yii::$app->ExplorerUtils;
            echo "  ExplorerUtils component loaded: " . get_class($explorerUtils) . "\n";
            
            // Test that we can query coin configuration for RPC
            echo "\nTesting coin RPC configuration access...\n";
            $coin = Coins::find()
                ->where(['enable' => 1])
                ->one();
            
            if ($coin) {
                echo "  Sample coin: {$coin->symbol}\n";
                echo "    RPC host: " . ($coin->rpchost ?: 'not set') . "\n";
                echo "    RPC port: " . ($coin->rpcport ?: 'not set') . "\n";
                echo "    RPC encoding: " . ($coin->rpcencoding ?: 'not set') . "\n";
                echo "    Has master nodes: " . ($coin->hasmasternodes ? 'yes' : 'no') . "\n";
            } else {
                echo "  ⚠ No enabled coins found\n";
            }
            
            echo "\n✓ RPC component access test passed!\n";
            echo "\nNote: This test only verifies component access, not actual RPC calls.\n";
            return ExitCode::OK;
            
        } catch (\Exception $e) {
            echo "\n✗ RPC component test failed: " . $e->getMessage() . "\n";
            echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }
    
    /**
     * Test market data access
     * @return int Exit code
     */
    public function actionMarkets()
    {
        echo "Testing market data access...\n\n";
        
        try {
            // Query recent market data
            echo "Querying recent market data...\n";
            $markets = Markets::find()
                ->orderBy(['last_updated' => SORT_DESC])
                ->limit(10)
                ->all();
            
            echo "  Found " . count($markets) . " recent market entries\n\n";
            
            foreach ($markets as $market) {
                $coin = $market->coin;
                $coinSymbol = $coin ? $coin->symbol : 'Unknown';
                echo "  Market ID {$market->id}:\n";
                echo "    Coin: {$coinSymbol}\n";
                echo "    Name: {$market->name}\n";
                echo "    Price: {$market->price}\n";
                echo "    Last updated: {$market->last_updated}\n";
                echo "\n";
            }
            
            // Test market relations
            echo "Testing market relations...\n";
            $market = Markets::find()->one();
            if ($market) {
                $coin = $market->coin;
                if ($coin) {
                    echo "  ✓ Market {$market->id} -> Coin {$coin->symbol}\n";
                } else {
                    echo "  ⚠ Market {$market->id} has no associated coin\n";
                }
            } else {
                echo "  ⚠ No markets found in database\n";
            }
            
            echo "\n✓ Market data access test passed!\n";
            return ExitCode::OK;
            
        } catch (\Exception $e) {
            echo "\n✗ Market data test failed: " . $e->getMessage() . "\n";
            echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }
    
    /**
     * Test exchange balance tracking
     * @return int Exit code
     */
    public function actionBalances()
    {
        echo "Testing exchange balance tracking...\n\n";
        
        try {
            // Test balance queries
            echo "Querying exchange balances...\n";
            $balances = Balances::find()
                ->where(['>', 'balance', 0])
                ->limit(5)
                ->all();
            
            echo "  Found " . count($balances) . " non-zero balances\n";
            
            foreach ($balances as $balance) {
                echo "    Balance ID {$balance->id}: balance = {$balance->balance}\n";
            }
            
            // Test balance relations
            echo "\nTesting balance relations...\n";
            $balance = Balances::find()->one();
            if ($balance) {
                echo "  ✓ Balance record found with ID {$balance->id}\n";
            } else {
                echo "  ⚠ No balance records found in database\n";
            }
            
            echo "\n✓ Exchange balance tracking test passed!\n";
            return ExitCode::OK;
            
        } catch (\Exception $e) {
            echo "\n✗ Exchange balance test failed: " . $e->getMessage() . "\n";
            echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }
}
