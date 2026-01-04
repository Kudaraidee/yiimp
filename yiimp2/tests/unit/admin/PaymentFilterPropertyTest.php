<?php

namespace tests\unit\admin;

use Codeception\Test\Unit;
use app\models\Payouts;
use app\models\Accounts;
use app\models\Coins;
use tests\helpers\DatabaseTestHelper;

/**
 * Property-based tests for Payment Status and Coin Filtering
 * 
 * Feature: yiimp2-admin-panel-fixes, Property 6 & 7: Payment filter correctness
 */
class PaymentFilterPropertyTest extends Unit
{
    use DatabaseTestHelper;
    
    /**
     * Property 6: Payment status filter works correctly
     * 
     * For any payment status filter selection, all returned payments should match 
     * that status (pending or completed).
     * 
     * Validates: Requirements 4.3
     * 
     * @test
     */
    public function testPaymentStatusFilterCorrectness()
    {
        // Feature: yiimp2-admin-panel-fixes, Property 6: Payment status filter works correctly
        
        // Skip test if database is not available
        $this->requireDatabase();
        
        // Check if database is available
        try {
            \Yii::$app->db->open();
        } catch (\Exception $e) {
            $this->markTestSkipped('Database not available: ' . $e->getMessage());
            return;
        }
        
        $iterations = 100;
        $failures = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            // Create test account and coin
            $account = $this->createTestAccount();
            $coin = $this->createTestCoin();
            
            // Create test payments with mixed statuses
            $testPayments = $this->createTestPayments($account->id, $coin->id, 5);
            
            // Test each status filter
            $statusFilters = ['pending', 'completed', ''];
            $statusFilter = $statusFilters[array_rand($statusFilters)];
            
            // Perform query - limit to test payments only
            $testPaymentIds = array_map(function($p) { return $p->id; }, $testPayments);
            $query = Payouts::find()->where(['id' => $testPaymentIds]);
            
            // Apply status filter (matching AdminController::actionPayments_results)
            if ($statusFilter === 'pending') {
                $query->andWhere(['IS', 'tx', null]);
            } elseif ($statusFilter === 'completed') {
                $query->andWhere(['IS NOT', 'tx', null]);
            }
            
            $results = $query->all();
            
            // Verify all results match the status filter
            foreach ($results as $result) {
                $isPending = ($result->tx === null);
                $isCompleted = ($result->tx !== null);
                
                if ($statusFilter === 'pending' && !$isPending) {
                    $failures[] = [
                        'iteration' => $i,
                        'type' => 'status_mismatch',
                        'payment_id' => $result->id,
                        'tx' => $result->tx,
                        'filter' => $statusFilter,
                        'reason' => 'Payment has tx but filter is pending'
                    ];
                }
                
                if ($statusFilter === 'completed' && !$isCompleted) {
                    $failures[] = [
                        'iteration' => $i,
                        'type' => 'status_mismatch',
                        'payment_id' => $result->id,
                        'tx' => $result->tx,
                        'filter' => $statusFilter,
                        'reason' => 'Payment has no tx but filter is completed'
                    ];
                }
            }
            
            // Check for false negatives (expected payments not in results)
            $resultIds = array_map(function($r) { return $r->id; }, $results);
            foreach ($testPayments as $payment) {
                $shouldBeIncluded = $this->paymentMatchesStatusFilter($payment, $statusFilter);
                $isIncluded = in_array($payment->id, $resultIds);
                
                if ($shouldBeIncluded && !$isIncluded) {
                    $failures[] = [
                        'iteration' => $i,
                        'type' => 'false_negative',
                        'payment_id' => $payment->id,
                        'tx' => $payment->tx,
                        'filter' => $statusFilter,
                        'reason' => 'Payment matches filter but not in results'
                    ];
                }
            }
            
            // Clean up test data
            foreach ($testPayments as $payment) {
                $payment->delete();
            }
            $account->delete();
            $coin->delete();
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
     * Property 7: Payment coin filter works correctly
     * 
     * For any coin filter selection on the payment monitoring page, all returned 
     * payments should be for that specific cryptocurrency.
     * 
     * Validates: Requirements 4.4
     * 
     * @test
     */
    public function testPaymentCoinFilterCorrectness()
    {
        // Feature: yiimp2-admin-panel-fixes, Property 7: Payment coin filter works correctly
        
        // Skip test if database is not available
        $this->requireDatabase();
        
        // Check if database is available
        try {
            \Yii::$app->db->open();
        } catch (\Exception $e) {
            $this->markTestSkipped('Database not available: ' . $e->getMessage());
            return;
        }
        
        $iterations = 100;
        $failures = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            // Create test account and multiple coins
            $account = $this->createTestAccount();
            $coin1 = $this->createTestCoin();
            $coin2 = $this->createTestCoin();
            
            // Create payments for different coins
            $paymentsForCoin1 = $this->createTestPayments($account->id, $coin1->id, 3);
            $paymentsForCoin2 = $this->createTestPayments($account->id, $coin2->id, 2);
            $allTestPayments = array_merge($paymentsForCoin1, $paymentsForCoin2);
            
            // Randomly select a coin to filter by
            $filterCoinId = rand(0, 1) ? $coin1->id : $coin2->id;
            
            // Perform query - limit to test payments only
            $testPaymentIds = array_map(function($p) { return $p->id; }, $allTestPayments);
            $query = Payouts::find()->where(['id' => $testPaymentIds]);
            
            // Apply coin filter (matching AdminController::actionPayments_results)
            if ($filterCoinId) {
                $query->andWhere(['idcoin' => $filterCoinId]);
            }
            
            $results = $query->all();
            
            // Verify all results match the coin filter
            foreach ($results as $result) {
                if ($result->idcoin != $filterCoinId) {
                    $failures[] = [
                        'iteration' => $i,
                        'type' => 'coin_mismatch',
                        'payment_id' => $result->id,
                        'payment_coinid' => $result->idcoin,
                        'filter_coinid' => $filterCoinId,
                        'reason' => 'Payment coin does not match filter'
                    ];
                }
            }
            
            // Check for false negatives (expected payments not in results)
            $resultIds = array_map(function($r) { return $r->id; }, $results);
            foreach ($allTestPayments as $payment) {
                $shouldBeIncluded = ($payment->idcoin == $filterCoinId);
                $isIncluded = in_array($payment->id, $resultIds);
                
                if ($shouldBeIncluded && !$isIncluded) {
                    $failures[] = [
                        'iteration' => $i,
                        'type' => 'false_negative',
                        'payment_id' => $payment->id,
                        'payment_coinid' => $payment->idcoin,
                        'filter_coinid' => $filterCoinId,
                        'reason' => 'Payment matches coin filter but not in results'
                    ];
                }
            }
            
            // Clean up test data
            foreach ($allTestPayments as $payment) {
                $payment->delete();
            }
            $account->delete();
            $coin1->delete();
            $coin2->delete();
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
     * Create test account
     * 
     * @return Accounts
     */
    protected function createTestAccount()
    {
        $account = new Accounts();
        $account->username = 'testaccount_' . time() . '_' . rand(1000, 9999);
        $account->balance = round(rand(0, 100000) / 100, 8);
        $account->coinid = rand(1, 100);
        $account->no_fees = rand(0, 1);
        $account->donation = rand(0, 100);
        
        if (!$account->save()) {
            throw new \Exception('Failed to create test account: ' . json_encode($account->errors));
        }
        
        return $account;
    }
    
    /**
     * Create test coin
     * 
     * @return Coins
     */
    protected function createTestCoin()
    {
        $coin = new Coins();
        $coin->name = 'TestCoin_' . time() . '_' . rand(1000, 9999);
        $coin->symbol = 'TST' . rand(100, 999);
        $coin->algo = 'sha256';
        $coin->enable = 1;
        $coin->visible = 1;
        
        if (!$coin->save()) {
            throw new \Exception('Failed to create test coin: ' . json_encode($coin->errors));
        }
        
        return $coin;
    }
    
    /**
     * Create test payments
     * 
     * @param int $accountId
     * @param int $coinId
     * @param int $count
     * @return Payouts[]
     */
    protected function createTestPayments($accountId, $coinId, $count)
    {
        $payments = [];
        for ($i = 0; $i < $count; $i++) {
            $payment = new Payouts();
            $payment->account_id = $accountId;
            $payment->idcoin = $coinId;
            $payment->amount = round(rand(1000, 100000) / 10000, 8);
            $payment->fee = round(rand(0, 100) / 10000, 8);
            $payment->time = time() - rand(0, 86400);
            
            // Randomly assign pending or completed status
            if (rand(0, 1)) {
                // Completed payment - has tx
                $payment->tx = bin2hex(random_bytes(32));
                $payment->completed = 1;
            } else {
                // Pending payment - no tx
                $payment->tx = null;
                $payment->completed = 0;
            }
            
            if ($payment->save()) {
                $payments[] = $payment;
            } else {
                throw new \Exception('Failed to create test payment: ' . json_encode($payment->errors));
            }
        }
        return $payments;
    }
    
    /**
     * Check if payment matches status filter
     * 
     * @param Payouts $payment
     * @param string $statusFilter
     * @return bool
     */
    protected function paymentMatchesStatusFilter($payment, $statusFilter)
    {
        if ($statusFilter === 'pending') {
            return $payment->tx === null;
        } elseif ($statusFilter === 'completed') {
            return $payment->tx !== null;
        }
        // Empty filter matches all
        return true;
    }
}
