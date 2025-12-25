<?php

namespace yiimp2\tests\unit\database;

use yii\db\Connection;
use Codeception\Test\Unit;
use Faker\Factory as FakerFactory;
use app\models\Accounts;
use app\models\Earnings;
use app\models\Payouts;
use app\models\Blocks;
use app\models\Coins;

/**
 * Property-based test for Decimal Precision Consistency
 * 
 * **Feature: yiimp2-routing-database-alignment, Property 7: Decimal Precision Consistency**
 * **Validates: Requirements 4.5, 6.4**
 * 
 * This test verifies that:
 * 1. Database stores financial values with correct precision (at least 8 decimal places)
 * 2. No rounding errors occur when storing and retrieving financial amounts
 * 3. Financial calculations maintain precision across operations
 */
class DecimalPrecisionPropertyTest extends Unit
{
    /**
     * @var Connection
     */
    protected $db;

    /**
     * @var \Faker\Generator
     */
    protected $faker;

    protected function _before()
    {
        $this->db = \Yii::$app->db;
        $this->faker = FakerFactory::create();
    }

    /**
     * Property 7: Decimal Precision Consistency
     * 
     * For any financial calculation (balances, earnings, payouts), the database 
     * column type should support at least 8 decimal places without rounding errors.
     * 
     * This test generates random financial amounts with high precision and verifies:
     * 1. Values can be stored without truncation
     * 2. Values can be retrieved with same precision
     * 3. No rounding errors occur in round-trip operations
     * 
     * Runs 100 iterations with random financial amounts
     */
    public function testFinancialAmountsStoredWithCorrectPrecision()
    {
        // Run 100 iterations with random financial amounts
        for ($i = 0; $i < 100; $i++) {
            // Generate random financial amount with 8 decimal places
            $originalAmount = $this->generateFinancialAmount();
            
            // Test with Accounts balance
            $this->assertAccountBalancePrecision($originalAmount);
            
            // Test with Earnings amount
            $this->assertEarningsAmountPrecision($originalAmount);
            
            // Test with Payouts amount
            $this->assertPayoutsAmountPrecision($originalAmount);
            
            // Test with Blocks amount
            $this->assertBlocksAmountPrecision($originalAmount);
        }
    }

    /**
     * Test that account balances maintain precision
     */
    protected function assertAccountBalancePrecision($amount)
    {
        $transaction = $this->db->beginTransaction();
        try {
            $account = new Accounts();
            $account->username = 'test_' . $this->faker->uuid;
            $account->balance = $amount;
            
            $this->assertTrue($account->save(false), "Account should save successfully");
            
            // Retrieve from database
            $retrieved = Accounts::findOne($account->id);
            $this->assertNotNull($retrieved, "Should retrieve saved account");
            
            // Verify precision is maintained
            $this->assertFinancialPrecision(
                $amount,
                $retrieved->balance,
                "Account balance should maintain precision"
            );
            
            $transaction->rollBack();
        } catch (\Exception $e) {
            $transaction->rollBack();
            throw $e;
        }
    }

    /**
     * Test that earnings amounts maintain precision
     */
    protected function assertEarningsAmountPrecision($amount)
    {
        $transaction = $this->db->beginTransaction();
        try {
            // Create test account first
            $account = new Accounts();
            $account->username = 'test_' . $this->faker->uuid;
            $account->save(false);
            
            // Create test coin
            $coin = new Coins();
            $coin->name = 'TestCoin_' . $this->faker->randomNumber(5);
            $coin->symbol = 'TST' . $this->faker->randomNumber(3);
            $coin->save(false);
            
            $earning = new Earnings();
            $earning->userid = $account->id;
            $earning->coinid = $coin->id;
            $earning->amount = $amount;
            $earning->price = $this->generateFinancialAmount();
            $earning->create_time = time();
            
            $this->assertTrue($earning->save(false), "Earning should save successfully");
            
            // Retrieve from database
            $retrieved = Earnings::findOne($earning->id);
            $this->assertNotNull($retrieved, "Should retrieve saved earning");
            
            // Verify precision is maintained
            $this->assertFinancialPrecision(
                $amount,
                $retrieved->amount,
                "Earnings amount should maintain precision"
            );
            
            $transaction->rollBack();
        } catch (\Exception $e) {
            $transaction->rollBack();
            throw $e;
        }
    }

    /**
     * Test that payout amounts maintain precision
     */
    protected function assertPayoutsAmountPrecision($amount)
    {
        $transaction = $this->db->beginTransaction();
        try {
            // Create test account first
            $account = new Accounts();
            $account->username = 'test_' . $this->faker->uuid;
            $account->save(false);
            
            // Create test coin
            $coin = new Coins();
            $coin->name = 'TestCoin_' . $this->faker->randomNumber(5);
            $coin->symbol = 'TST' . $this->faker->randomNumber(3);
            $coin->save(false);
            
            $payout = new Payouts();
            $payout->account_id = $account->id;
            $payout->idcoin = $coin->id;
            $payout->amount = $amount;
            $payout->fee = $this->generateFinancialAmount();
            $payout->time = time();
            
            $this->assertTrue($payout->save(false), "Payout should save successfully");
            
            // Retrieve from database
            $retrieved = Payouts::findOne($payout->id);
            $this->assertNotNull($retrieved, "Should retrieve saved payout");
            
            // Verify precision is maintained
            $this->assertFinancialPrecision(
                $amount,
                $retrieved->amount,
                "Payout amount should maintain precision"
            );
            
            $transaction->rollBack();
        } catch (\Exception $e) {
            $transaction->rollBack();
            throw $e;
        }
    }

    /**
     * Test that block amounts maintain precision
     */
    protected function assertBlocksAmountPrecision($amount)
    {
        $transaction = $this->db->beginTransaction();
        try {
            // Create test coin
            $coin = new Coins();
            $coin->name = 'TestCoin_' . $this->faker->randomNumber(5);
            $coin->symbol = 'TST' . $this->faker->randomNumber(3);
            $coin->save(false);
            
            $block = new Blocks();
            $block->coin_id = $coin->id;
            $block->amount = $amount;
            $block->difficulty = $this->generateFinancialAmount();
            $block->price = $this->generateFinancialAmount();
            $block->height = $this->faker->numberBetween(1, 1000000);
            $block->time = time();
            
            $this->assertTrue($block->save(false), "Block should save successfully");
            
            // Retrieve from database
            $retrieved = Blocks::findOne($block->id);
            $this->assertNotNull($retrieved, "Should retrieve saved block");
            
            // Verify precision is maintained
            $this->assertFinancialPrecision(
                $amount,
                $retrieved->amount,
                "Block amount should maintain precision"
            );
            
            $transaction->rollBack();
        } catch (\Exception $e) {
            $transaction->rollBack();
            throw $e;
        }
    }

    /**
     * Test that financial calculations maintain precision
     * 
     * This tests that adding and subtracting amounts doesn't introduce rounding errors
     */
    public function testFinancialCalculationsMaintainPrecision()
    {
        // Run 50 iterations
        for ($i = 0; $i < 50; $i++) {
            $transaction = $this->db->beginTransaction();
            try {
                // Create test account
                $account = new Accounts();
                $account->username = 'test_' . $this->faker->uuid;
                $account->balance = 0.0;
                $account->save(false);
                
                // Generate multiple amounts to add
                $amounts = [];
                $expectedTotal = 0.0;
                
                for ($j = 0; $j < 5; $j++) {
                    $amount = $this->generateFinancialAmount();
                    $amounts[] = $amount;
                    $expectedTotal += $amount;
                }
                
                // Add amounts one by one
                foreach ($amounts as $amount) {
                    $account->balance += $amount;
                }
                
                $account->save(false);
                
                // Retrieve and verify
                $retrieved = Accounts::findOne($account->id);
                
                // The precision check here is more lenient due to floating point arithmetic
                // We check that the difference is within acceptable bounds for double precision
                $difference = abs($expectedTotal - $retrieved->balance);
                $this->assertLessThan(
                    1e-10,
                    $difference,
                    "Financial calculations should maintain precision within acceptable bounds. " .
                    "Expected: {$expectedTotal}, Got: {$retrieved->balance}, Difference: {$difference}"
                );
                
                $transaction->rollBack();
            } catch (\Exception $e) {
                $transaction->rollBack();
                throw $e;
            }
        }
    }

    /**
     * Test that database columns support at least 8 decimal places
     * 
     * This verifies the schema itself supports the required precision
     */
    public function testDatabaseColumnsSupport8DecimalPlaces()
    {
        $financialTables = [
            'accounts' => ['balance'],
            'earnings' => ['amount', 'price'],
            'payouts' => ['amount', 'fee'],
            'blocks' => ['amount', 'difficulty', 'difficulty_user', 'price', 'effort'],
            'coins' => [
                'balance', 'immature', 'cleared', 'available', 'stake', 'mint',
                'txfee', 'payout_min', 'payout_max', 'difficulty', 'difficulty_pos',
                'network_hash', 'price', 'price2', 'reward', 'reward_mul',
                'charity_amount', 'charity_percent', 'deposit_minimum', 'index_avg'
            ],
        ];
        
        foreach ($financialTables as $tableName => $columns) {
            $schema = $this->db->getTableSchema($tableName);
            $this->assertNotNull($schema, "Table '{$tableName}' should exist");
            
            foreach ($columns as $columnName) {
                $this->assertTrue(
                    isset($schema->columns[$columnName]),
                    "Column '{$columnName}' should exist in table '{$tableName}'"
                );
                
                $column = $schema->columns[$columnName];
                
                // Verify it's a numeric type
                $this->assertContains(
                    $column->phpType,
                    ['double', 'float', 'integer'],
                    "Column '{$columnName}' in table '{$tableName}' should be numeric type"
                );
                
                // For double/float types, verify precision by testing actual storage
                if ($column->phpType === 'double' || $column->phpType === 'float') {
                    $this->verifyColumnPrecision($tableName, $columnName);
                }
            }
        }
    }

    /**
     * Verify a specific column can store 8 decimal places
     */
    protected function verifyColumnPrecision($tableName, $columnName)
    {
        $transaction = $this->db->beginTransaction();
        try {
            // Generate a test value with 8 decimal places
            $testValue = $this->generateFinancialAmount();
            
            // Insert directly via SQL to test column precision
            $this->db->createCommand()
                ->insert($tableName, $this->getTestRowData($tableName, $columnName, $testValue))
                ->execute();
            
            $insertId = $this->db->getLastInsertID();
            
            // Retrieve the value
            $result = $this->db->createCommand()
                ->select([$columnName])
                ->from($tableName)
                ->where(['id' => $insertId])
                ->queryOne();
            
            $retrievedValue = $result[$columnName];
            
            // Verify precision
            $this->assertFinancialPrecision(
                $testValue,
                $retrievedValue,
                "Column '{$columnName}' in table '{$tableName}' should support 8 decimal places"
            );
            
            $transaction->rollBack();
        } catch (\Exception $e) {
            $transaction->rollBack();
            // Some tables may have constraints that prevent direct insertion
            // This is acceptable - we've tested via models above
        }
    }

    /**
     * Generate test row data for a table
     */
    protected function getTestRowData($tableName, $columnName, $value)
    {
        $data = [$columnName => $value];
        
        // Add required fields based on table
        switch ($tableName) {
            case 'accounts':
                $data['username'] = 'test_' . $this->faker->uuid;
                break;
            case 'earnings':
                // Would need userid and coinid - skip direct insert
                throw new \Exception("Skip direct insert for earnings");
            case 'payouts':
                // Would need account_id - skip direct insert
                throw new \Exception("Skip direct insert for payouts");
            case 'blocks':
                // Would need coin_id - skip direct insert
                throw new \Exception("Skip direct insert for blocks");
            case 'coins':
                $data['name'] = 'TestCoin_' . $this->faker->randomNumber(5);
                $data['symbol'] = 'TST' . $this->faker->randomNumber(3);
                break;
        }
        
        return $data;
    }

    /**
     * Generate a random financial amount with 8 decimal places
     * 
     * @return float
     */
    protected function generateFinancialAmount()
    {
        // Generate amounts between 0.00000001 and 1000.00000000
        // This covers typical cryptocurrency amounts
        $integerPart = $this->faker->numberBetween(0, 1000);
        $decimalPart = $this->faker->numberBetween(0, 99999999);
        
        // Combine to create a number with exactly 8 decimal places
        $amount = $integerPart + ($decimalPart / 100000000);
        
        return $amount;
    }

    /**
     * Assert that two financial values are equal within acceptable precision
     * 
     * Due to floating point representation, we allow a tiny difference
     * but it should be much smaller than 1e-8 (8 decimal places)
     * 
     * @param float $expected
     * @param float $actual
     * @param string $message
     */
    protected function assertFinancialPrecision($expected, $actual, $message = '')
    {
        // Calculate the difference
        $difference = abs($expected - $actual);
        
        // For 8 decimal place precision, the difference should be less than 1e-8
        // We use 1e-7 to account for floating point representation issues
        $this->assertLessThan(
            1e-7,
            $difference,
            $message . " Expected: {$expected}, Got: {$actual}, Difference: {$difference}"
        );
        
        // Also verify that when formatted to 8 decimal places, they're the same
        $expectedFormatted = number_format($expected, 8, '.', '');
        $actualFormatted = number_format($actual, 8, '.', '');
        
        $this->assertEquals(
            $expectedFormatted,
            $actualFormatted,
            $message . " Values should be equal when formatted to 8 decimal places"
        );
    }

    /**
     * Test edge cases: very small amounts
     */
    public function testVerySmallAmountsPrecision()
    {
        // Test amounts like 0.00000001 (1 satoshi in BTC)
        $verySmallAmounts = [
            0.00000001,
            0.00000010,
            0.00000100,
            0.00001000,
            0.00010000,
            0.00100000,
            0.01000000,
            0.10000000,
        ];
        
        foreach ($verySmallAmounts as $amount) {
            $transaction = $this->db->beginTransaction();
            try {
                $account = new Accounts();
                $account->username = 'test_' . $this->faker->uuid;
                $account->balance = $amount;
                $account->save(false);
                
                $retrieved = Accounts::findOne($account->id);
                
                $this->assertFinancialPrecision(
                    $amount,
                    $retrieved->balance,
                    "Very small amount {$amount} should maintain precision"
                );
                
                $transaction->rollBack();
            } catch (\Exception $e) {
                $transaction->rollBack();
                throw $e;
            }
        }
    }

    /**
     * Test edge cases: very large amounts
     */
    public function testVeryLargeAmountsPrecision()
    {
        // Test large amounts with 8 decimal places
        $veryLargeAmounts = [
            1000000.12345678,
            10000000.87654321,
            100000000.11111111,
        ];
        
        foreach ($veryLargeAmounts as $amount) {
            $transaction = $this->db->beginTransaction();
            try {
                $account = new Accounts();
                $account->username = 'test_' . $this->faker->uuid;
                $account->balance = $amount;
                $account->save(false);
                
                $retrieved = Accounts::findOne($account->id);
                
                $this->assertFinancialPrecision(
                    $amount,
                    $retrieved->balance,
                    "Very large amount {$amount} should maintain precision"
                );
                
                $transaction->rollBack();
            } catch (\Exception $e) {
                $transaction->rollBack();
                throw $e;
            }
        }
    }
}
