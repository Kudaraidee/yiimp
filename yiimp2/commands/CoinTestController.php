<?php
/**
 * Coin management test console command
 * 
 * This command tests that coin management scripts can use Yiimp2 models
 * 
 * Usage:
 *   ./yii coin-test/check - Check coin model access
 *   ./yii coin-test/status - Test coin status updates
 *   ./yii coin-test/blockchain - Test blockchain data access
 */

namespace app\commands;

use yii\console\Controller;
use yii\console\ExitCode;
use app\models\Coins;
use app\models\Blocks;
use app\models\Stratums;
use app\models\Algos;

class CoinTestController extends Controller
{
    /**
     * Check that coin management models can be accessed
     * @return int Exit code
     */
    public function actionCheck()
    {
        echo "Testing coin management model access...\n\n";
        
        try {
            // Test Coins model
            echo "Testing Coins model...\n";
            $coinCount = Coins::find()->count();
            echo "  Total coins in database: {$coinCount}\n";
            
            $enabledCoins = Coins::find()
                ->where(['enable' => 1])
                ->count();
            echo "  Enabled coins: {$enabledCoins}\n";
            
            $autoReadyCoins = Coins::find()
                ->where(['auto_ready' => 1])
                ->count();
            echo "  Auto-ready coins: {$autoReadyCoins}\n";
            
            // Test Blocks model
            echo "\nTesting Blocks model...\n";
            $blockCount = Blocks::find()->count();
            echo "  Total blocks in database: {$blockCount}\n";
            
            $confirmedBlocks = Blocks::find()
                ->where(['category' => 'generate'])
                ->count();
            echo "  Confirmed blocks: {$confirmedBlocks}\n";
            
            // Test Stratums model
            echo "\nTesting Stratums model...\n";
            $stratumCount = Stratums::find()->count();
            echo "  Total stratum configurations: {$stratumCount}\n";
            
            // Test Algos model
            echo "\nTesting Algos model...\n";
            $algoCount = Algos::find()->count();
            echo "  Total algorithms: {$algoCount}\n";
            
            echo "\n✓ Coin management model access test passed!\n";
            return ExitCode::OK;
            
        } catch (\Exception $e) {
            echo "\n✗ Coin management model test failed: " . $e->getMessage() . "\n";
            echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }
    
    /**
     * Test coin status updates
     * @return int Exit code
     */
    public function actionStatus()
    {
        echo "Testing coin status update operations...\n\n";
        
        try {
            // Query coin status information
            echo "Querying coin status information...\n";
            $coins = Coins::find()
                ->limit(5)
                ->all();
            
            echo "  Found " . count($coins) . " coins\n\n";
            
            foreach ($coins as $coin) {
                echo "  Coin: {$coin->symbol} ({$coin->name})\n";
                echo "    Enabled: " . ($coin->enable ? 'yes' : 'no') . "\n";
                echo "    Auto-ready: " . ($coin->auto_ready ? 'yes' : 'no') . "\n";
                echo "    Algorithm: {$coin->algo}\n";
                echo "    RPC host: " . ($coin->rpchost ?: 'not set') . "\n";
                echo "    RPC port: " . ($coin->rpcport ?: 'not set') . "\n";
                echo "    Difficulty: {$coin->difficulty}\n";
                echo "    Block height: {$coin->block_height}\n";
                echo "\n";
            }
            
            // Test read-only status check (no actual updates)
            echo "Testing status field access...\n";
            $coin = Coins::find()->one();
            if ($coin) {
                echo "  Sample coin status fields:\n";
                echo "    ID: {$coin->id}\n";
                echo "    Symbol: {$coin->symbol}\n";
                echo "    Enable: {$coin->enable}\n";
                echo "    Difficulty: {$coin->difficulty}\n";
                echo "    Block height: {$coin->block_height}\n";
                echo "    Last updated: {$coin->last_updated}\n";
            }
            
            echo "\n✓ Coin status test passed!\n";
            echo "\nNote: This was a read-only test. No status updates were made.\n";
            return ExitCode::OK;
            
        } catch (\Exception $e) {
            echo "\n✗ Coin status test failed: " . $e->getMessage() . "\n";
            echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }
    
    /**
     * Test blockchain data access
     * @return int Exit code
     */
    public function actionBlockchain()
    {
        echo "Testing blockchain data access...\n\n";
        
        try {
            // Query recent blocks
            echo "Querying recent blocks...\n";
            $blocks = Blocks::find()
                ->orderBy(['time' => SORT_DESC])
                ->limit(10)
                ->all();
            
            echo "  Found " . count($blocks) . " recent blocks\n\n";
            
            foreach ($blocks as $block) {
                $coin = $block->coin;
                $coinSymbol = $coin ? $coin->symbol : 'Unknown';
                echo "  Block {$block->height} ({$coinSymbol}):\n";
                echo "    Hash: {$block->blockhash}\n";
                echo "    Category: {$block->category}\n";
                echo "    Confirmations: {$block->confirmations}\n";
                echo "    Amount: {$block->amount}\n";
                echo "\n";
            }
            
            // Test block relations
            echo "Testing block relations...\n";
            $block = Blocks::find()->one();
            if ($block) {
                $coin = $block->coin;
                if ($coin) {
                    echo "  ✓ Block {$block->id} -> Coin {$coin->symbol}\n";
                } else {
                    echo "  ⚠ Block {$block->id} has no associated coin\n";
                }
            } else {
                echo "  ⚠ No blocks found in database\n";
            }
            
            // Test coin -> blocks relation
            echo "\nTesting coin -> blocks relation...\n";
            $coin = Coins::find()->one();
            if ($coin) {
                $blocks = $coin->blocks;
                echo "  ✓ Coin {$coin->symbol} has " . count($blocks) . " blocks\n";
            } else {
                echo "  ⚠ No coins found in database\n";
            }
            
            echo "\n✓ Blockchain data access test passed!\n";
            return ExitCode::OK;
            
        } catch (\Exception $e) {
            echo "\n✗ Blockchain data test failed: " . $e->getMessage() . "\n";
            echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }
    
    /**
     * Test algorithm and stratum configuration
     * @return int Exit code
     */
    public function actionConfig()
    {
        echo "Testing algorithm and stratum configuration...\n\n";
        
        try {
            // Query algorithm configuration
            echo "Querying algorithm configuration...\n";
            $algos = Algos::find()
                ->limit(10)
                ->all();
            
            echo "  Found " . count($algos) . " algorithms\n\n";
            
            foreach ($algos as $algo) {
                echo "  Algorithm: {$algo->name}\n";
                if (isset($algo->port)) {
                    echo "    Port: {$algo->port}\n";
                }
                echo "\n";
            }
            
            // Query stratum configuration
            echo "Querying stratum configuration...\n";
            $stratums = Stratums::find()
                ->limit(5)
                ->all();
            
            echo "  Found " . count($stratums) . " stratum configurations\n\n";
            
            foreach ($stratums as $stratum) {
                echo "  Stratum ID {$stratum->id}:\n";
                echo "    Algorithm: {$stratum->algo}\n";
                if (isset($stratum->port)) {
                    echo "    Port: {$stratum->port}\n";
                }
                echo "\n";
            }
            
            echo "\n✓ Configuration access test passed!\n";
            return ExitCode::OK;
            
        } catch (\Exception $e) {
            echo "\n✗ Configuration test failed: " . $e->getMessage() . "\n";
            echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }
}
