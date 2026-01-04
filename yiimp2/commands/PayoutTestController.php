<?php
/**
 * Payout test console command
 * 
 * This command tests that payout scripts can use Yiimp2 models for payment processing
 * 
 * Usage:
 *   ./yii payout-test/check - Check payout model access
 *   ./yii payout-test/process - Simulate payment processing
 */

namespace app\commands;

use yii\console\Controller;
use yii\console\ExitCode;
use app\models\Payouts;
use app\models\Accounts;
use app\models\Coins;
use app\models\Earnings;

class PayoutTestController extends Controller
{
    /**
     * Check that payout-related models can be accessed
     * @return int Exit code
     */
    public function actionCheck()
    {
        echo "Testing payout script model access...\n\n";
        
        try {
            // Test Payouts model
            echo "Testing Payouts model...\n";
            $payoutCount = Payouts::find()->count();
            echo "  Total payouts in database: {$payoutCount}\n";
            
            $pendingPayouts = Payouts::find()
                ->where(['status' => 0])
                ->count();
            echo "  Pending payouts: {$pendingPayouts}\n";
            
            $completedPayouts = Payouts::find()
                ->where(['status' => 1])
                ->count();
            echo "  Completed payouts: {$completedPayouts}\n";
            
            // Test Accounts model for balance queries
            echo "\nTesting Accounts model for balance queries...\n";
            $accountsWithBalance = Accounts::find()
                ->where(['>', 'balance', 0])
                ->count();
            echo "  Accounts with balance > 0: {$accountsWithBalance}\n";
            
            // Test Coins model for payout configuration
            echo "\nTesting Coins model for payout configuration...\n";
            $enabledCoins = Coins::find()
                ->where(['enable' => 1])
                ->count();
            echo "  Enabled coins: {$enabledCoins}\n";
            
            // Test Earnings model
            echo "\nTesting Earnings model...\n";
            $earningsCount = Earnings::find()->count();
            echo "  Total earnings records: {$earningsCount}\n";
            
            echo "\n✓ Payout model access test passed!\n";
            return ExitCode::OK;
            
        } catch (\Exception $e) {
            echo "\n✗ Payout model test failed: " . $e->getMessage() . "\n";
            echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }
    
    /**
     * Simulate payment processing workflow
     * @return int Exit code
     */
    public function actionProcess()
    {
        echo "Testing payment processing workflow with Yiimp2 models...\n\n";
        
        try {
            // Step 1: Query accounts with sufficient balance
            echo "Step 1: Querying accounts with balance...\n";
            $accounts = Accounts::find()
                ->where(['>', 'balance', 0])
                ->limit(5)
                ->all();
            echo "  Found " . count($accounts) . " accounts with balance\n";
            
            foreach ($accounts as $account) {
                echo "    Account ID {$account->id}: balance = {$account->balance}\n";
            }
            
            // Step 2: Query coin configuration
            echo "\nStep 2: Querying coin payout configuration...\n";
            $coins = Coins::find()
                ->where(['enable' => 1])
                ->limit(3)
                ->all();
            echo "  Found " . count($coins) . " enabled coins\n";
            
            foreach ($coins as $coin) {
                echo "    {$coin->symbol}: auto_ready = {$coin->auto_ready}\n";
            }
            
            // Step 3: Query recent payouts
            echo "\nStep 3: Querying recent payouts...\n";
            $recentPayouts = Payouts::find()
                ->orderBy(['time' => SORT_DESC])
                ->limit(5)
                ->all();
            echo "  Found " . count($recentPayouts) . " recent payouts\n";
            
            foreach ($recentPayouts as $payout) {
                echo "    Payout ID {$payout->id}: amount = {$payout->amount}, status = {$payout->status}\n";
            }
            
            // Step 4: Test transaction (read-only, no actual changes)
            echo "\nStep 4: Testing transaction support...\n";
            $transaction = \Yii::$app->db->beginTransaction();
            try {
                echo "  Transaction started\n";
                
                // Simulate balance check
                $testAccount = Accounts::find()->one();
                if ($testAccount) {
                    $originalBalance = $testAccount->balance;
                    echo "  Test account balance: {$originalBalance}\n";
                }
                
                // Rollback (we don't want to make actual changes)
                $transaction->rollBack();
                echo "  Transaction rolled back (no changes made)\n";
                
            } catch (\Exception $e) {
                $transaction->rollBack();
                throw $e;
            }
            
            echo "\n✓ Payment processing workflow test passed!\n";
            echo "\nNote: This was a read-only test. No actual payments were processed.\n";
            return ExitCode::OK;
            
        } catch (\Exception $e) {
            echo "\n✗ Payment processing test failed: " . $e->getMessage() . "\n";
            echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }
    
    /**
     * Test payout model relations
     * @return int Exit code
     */
    public function actionRelations()
    {
        echo "Testing payout model relations...\n\n";
        
        try {
            // Test Payout -> Account relation
            echo "Testing Payout -> Account relation...\n";
            $payout = Payouts::find()->one();
            if ($payout) {
                $account = $payout->account;
                if ($account) {
                    echo "  ✓ Payout {$payout->id} -> Account {$account->id}\n";
                } else {
                    echo "  ⚠ Payout {$payout->id} has no associated account\n";
                }
            } else {
                echo "  ⚠ No payouts found in database\n";
            }
            
            // Test Payout -> Coin relation
            echo "\nTesting Payout -> Coin relation...\n";
            if ($payout) {
                $coin = $payout->coin;
                if ($coin) {
                    echo "  ✓ Payout {$payout->id} -> Coin {$coin->symbol}\n";
                } else {
                    echo "  ⚠ Payout {$payout->id} has no associated coin\n";
                }
            }
            
            // Test Account -> Payouts relation
            echo "\nTesting Account -> Payouts relation...\n";
            $account = Accounts::find()->one();
            if ($account) {
                $payouts = $account->payouts;
                echo "  ✓ Account {$account->id} has " . count($payouts) . " payouts\n";
            } else {
                echo "  ⚠ No accounts found in database\n";
            }
            
            echo "\n✓ Payout relation test passed!\n";
            return ExitCode::OK;
            
        } catch (\Exception $e) {
            echo "\n✗ Payout relation test failed: " . $e->getMessage() . "\n";
            echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }
}
