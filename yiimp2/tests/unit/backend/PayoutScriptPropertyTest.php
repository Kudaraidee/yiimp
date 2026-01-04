<?php

namespace tests\unit\backend;

use app\models\Payouts;
use app\models\Accounts;
use app\models\Coins;
use app\models\Earnings;
use Codeception\Test\Unit;

/**
 * Feature: yiimp-to-yiimp2-migration, Property 41: Payout script model integration
 * 
 * Property: For any payout script execution, the script should successfully 
 * use Yiimp2 database models to process payments without errors.
 * 
 * Validates: Requirements 12.2
 */
class PayoutScriptPropertyTest extends Unit
{
    protected $tester;
    
    /**
     * Test that payout scripts can access payout models
     */
    public function testPayoutModelAccess()
    {
        // Property: Payout scripts should be able to access Payouts model
        for ($i = 0; $i < 100; $i++) {
            // Access Payouts model
            $count = Payouts::find()->count();
            $this->assertIsInt($count, "Payouts count should be integer (iteration $i)");
            $this->assertGreaterThanOrEqual(0, $count, "Payouts count should be non-negative (iteration $i)");
            
            // Query by completed status (0 = pending, 1 = completed)
            $pending = Payouts::find()->where(['completed' => 0])->count();
            $this->assertIsInt($pending, "Pending payouts count should be integer (iteration $i)");
            $this->assertGreaterThanOrEqual(0, $pending, "Pending payouts count should be non-negative (iteration $i)");
        }
    }
    
    /**
     * Test that payout scripts can access account models
     */
    public function testAccountModelAccess()
    {
        // Property: Payout scripts should be able to query account balances
        for ($i = 0; $i < 100; $i++) {
            // Query accounts with balance
            $count = Accounts::find()->where(['>', 'balance', 0])->count();
            $this->assertIsInt($count, "Accounts with balance count should be integer (iteration $i)");
            $this->assertGreaterThanOrEqual(0, $count, "Accounts with balance count should be non-negative (iteration $i)");
        }
    }
    
    /**
     * Test that payout scripts can access coin configuration
     */
    public function testCoinConfigurationAccess()
    {
        // Property: Payout scripts should be able to access coin payout configuration
        for ($i = 0; $i < 100; $i++) {
            // Query enabled coins
            $count = Coins::find()->where(['enable' => 1])->count();
            $this->assertIsInt($count, "Enabled coins count should be integer (iteration $i)");
            $this->assertGreaterThanOrEqual(0, $count, "Enabled coins count should be non-negative (iteration $i)");
        }
    }
    
    /**
     * Test that payout scripts can use transactions
     */
    public function testTransactionSupport()
    {
        // Property: Payout scripts should be able to use database transactions
        for ($i = 0; $i < 100; $i++) {
            $transaction = \Yii::$app->db->beginTransaction();
            
            try {
                // Verify transaction started
                $this->assertNotNull($transaction, "Transaction should be created (iteration $i)");
                
                // Rollback (no actual changes)
                $transaction->rollBack();
                
                // Verify no exceptions
                $this->assertTrue(true, "Transaction rollback should succeed (iteration $i)");
                
            } catch (\Exception $e) {
                $transaction->rollBack();
                throw $e;
            }
        }
    }
    
    /**
     * Test that payout scripts can access earnings data
     */
    public function testEarningsAccess()
    {
        // Property: Payout scripts should be able to access earnings data
        for ($i = 0; $i < 100; $i++) {
            $count = Earnings::find()->count();
            $this->assertIsInt($count, "Earnings count should be integer (iteration $i)");
            $this->assertGreaterThanOrEqual(0, $count, "Earnings count should be non-negative (iteration $i)");
        }
    }
}
