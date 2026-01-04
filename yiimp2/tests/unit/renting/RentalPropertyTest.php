<?php

namespace tests\unit\renting;

use Codeception\Test\Unit;
use app\models\Renters;
use app\models\Jobs;
use app\models\Rentertxs;

/**
 * Property-based tests for Rental System
 * 
 * Feature: yiimp-to-yiimp2-migration
 * Properties: 17-23 (Rental system properties)
 */
class RentalPropertyTest extends Unit
{
    /**
     * Property 17: Renter Account Creation
     * 
     * For any renter account creation, the system should generate a unique renter ID
     * and initialize balance tracking to zero.
     * 
     * Validates: Requirements 4.1
     * 
     * @test
     */
    public function testRenterAccountCreation()
    {
        // Feature: yiimp-to-yiimp2-migration, Property 17: Renter account creation
        
        $iterations = 100;
        $failures = [];
        $createdIds = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            // Create renter account with all required fields as per controller logic
            $renter = new Renters();
            $renter->address = $this->generateRandomAddress();
            $renter->email = 'test' . $i . '@example.com';
            $renter->setPassword('testpassword' . $i);
            $renter->generateApiKey();
            $renter->balance = 0;
            $renter->received = 0;
            $renter->spent = 0;
            $renter->unconfirmed = 0;
            
            if (!$renter->save()) {
                $failures[] = [
                    'iteration' => $i,
                    'errors' => $renter->errors,
                    'reason' => 'Failed to create renter account'
                ];
                continue;
            }
            
            // Verify unique ID generated
            if (empty($renter->id)) {
                $failures[] = [
                    'iteration' => $i,
                    'reason' => 'Renter ID not generated'
                ];
            } else {
                // Verify ID is truly unique
                if (in_array($renter->id, $createdIds)) {
                    $failures[] = [
                        'iteration' => $i,
                        'renter_id' => $renter->id,
                        'reason' => 'Duplicate renter ID generated'
                    ];
                }
                $createdIds[] = $renter->id;
            }
            
            // Verify balance initialized to zero
            if ($renter->balance != 0) {
                $failures[] = [
                    'iteration' => $i,
                    'expected_balance' => 0,
                    'actual_balance' => $renter->balance,
                    'reason' => 'Balance not initialized to zero'
                ];
            }
            
            // Verify received initialized to zero
            if ($renter->received != 0) {
                $failures[] = [
                    'iteration' => $i,
                    'expected_received' => 0,
                    'actual_received' => $renter->received,
                    'reason' => 'Received not initialized to zero'
                ];
            }
            
            // Verify spent initialized to zero
            if ($renter->spent != 0) {
                $failures[] = [
                    'iteration' => $i,
                    'expected_spent' => 0,
                    'actual_spent' => $renter->spent,
                    'reason' => 'Spent not initialized to zero'
                ];
            }
            
            // Verify unconfirmed initialized to zero
            if ($renter->unconfirmed != 0) {
                $failures[] = [
                    'iteration' => $i,
                    'expected_unconfirmed' => 0,
                    'actual_unconfirmed' => $renter->unconfirmed,
                    'reason' => 'Unconfirmed not initialized to zero'
                ];
            }
            
            // Verify API key was generated
            if (empty($renter->apikey)) {
                $failures[] = [
                    'iteration' => $i,
                    'reason' => 'API key not generated'
                ];
            }
            
            // Verify password was hashed (should not be plain text)
            if (empty($renter->password) || $renter->password === 'testpassword' . $i) {
                $failures[] = [
                    'iteration' => $i,
                    'reason' => 'Password not hashed'
                ];
            }
            
            // Verify created timestamp was set
            if (empty($renter->created)) {
                $failures[] = [
                    'iteration' => $i,
                    'reason' => 'Created timestamp not set'
                ];
            }
            
            // Clean up
            $renter->delete();
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
     * Property 18: Deposit Balance Update
     * 
     * For any deposit transaction, the renter's balance should increase by exactly
     * the deposit amount and a transaction record should be created.
     * 
     * Validates: Requirements 4.2
     * 
     * @test
     */
    public function testDepositBalanceUpdate()
    {
        // Feature: yiimp-to-yiimp2-migration, Property 18: Deposit balance update
        
        $iterations = 100;
        $failures = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            // Create renter
            $renter = $this->createTestRenter();
            $initialBalance = $renter->balance;
            $initialReceived = $renter->received;
            
            // Generate random deposit amount
            $depositAmount = round(rand(1, 10000) / 100, 8);
            
            // Process deposit using the addBalance method
            if (!$renter->addBalance($depositAmount)) {
                $failures[] = [
                    'iteration' => $i,
                    'reason' => 'Failed to update balance using addBalance method'
                ];
                $renter->delete();
                continue;
            }
            
            // Reload renter to get fresh data
            $renter->refresh();
            
            // Create transaction record
            if (!Rentertxs::createDeposit($renter->id, $depositAmount)) {
                $failures[] = [
                    'iteration' => $i,
                    'reason' => 'Failed to create transaction record'
                ];
            }
            
            // Verify balance increased by exact amount
            $expectedBalance = $initialBalance + $depositAmount;
            if (abs($renter->balance - $expectedBalance) > 0.00000001) {
                $failures[] = [
                    'iteration' => $i,
                    'initial_balance' => $initialBalance,
                    'deposit_amount' => $depositAmount,
                    'expected_balance' => $expectedBalance,
                    'actual_balance' => $renter->balance,
                    'reason' => 'Balance not increased by exact deposit amount'
                ];
            }
            
            // Verify received field increased by exact amount
            $expectedReceived = $initialReceived + $depositAmount;
            if (abs($renter->received - $expectedReceived) > 0.00000001) {
                $failures[] = [
                    'iteration' => $i,
                    'initial_received' => $initialReceived,
                    'deposit_amount' => $depositAmount,
                    'expected_received' => $expectedReceived,
                    'actual_received' => $renter->received,
                    'reason' => 'Received field not increased by exact deposit amount'
                ];
            }
            
            // Verify transaction record exists
            $savedTx = Rentertxs::findOne(['renterid' => $renter->id, 'amount' => $depositAmount, 'type' => 'deposit']);
            if ($savedTx === null) {
                $failures[] = [
                    'iteration' => $i,
                    'reason' => 'Transaction record not found'
                ];
            } else {
                // Verify transaction type is correct
                if ($savedTx->type !== 'deposit') {
                    $failures[] = [
                        'iteration' => $i,
                        'expected_type' => 'deposit',
                        'actual_type' => $savedTx->type,
                        'reason' => 'Transaction type incorrect'
                    ];
                }
                $savedTx->delete();
            }
            
            // Clean up
            $renter->delete();
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
     * Property 19: Rental Order Creation
     * 
     * For any valid rental order parameters, the system should create an order
     * record with all specified parameters.
     * 
     * Validates: Requirements 4.3
     * 
     * @test
     */
    public function testRentalOrderCreation()
    {
        // Feature: yiimp-to-yiimp2-migration, Property 19: Rental order creation
        
        $iterations = 100;
        $failures = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            // Create renter
            $renter = $this->createTestRenter();
            
            // Generate random order parameters
            $orderParams = $this->generateOrderParams($renter->id);
            
            // Create order
            $order = new Jobs();
            $order->attributes = $orderParams;
            
            if (!$order->save()) {
                $failures[] = [
                    'iteration' => $i,
                    'params' => $orderParams,
                    'errors' => $order->errors,
                    'reason' => 'Failed to create order'
                ];
                $renter->delete();
                continue;
            }
            
            // Verify all parameters persisted
            $this->verifyOrderParams($order, $orderParams, $i, $failures);
            
            // Clean up
            $order->delete();
            $renter->delete();
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
     * Property 20: Order Completion Balance Deduction
     * 
     * For any completed rental order, the renter's balance should decrease by the
     * calculated cost and the order status should be marked as complete.
     * 
     * Validates: Requirements 4.5
     * 
     * @test
     */
    public function testOrderCompletionBalanceDeduction()
    {
        // Feature: yiimp-to-yiimp2-migration, Property 20: Order completion balance deduction
        
        $iterations = 100;
        $failures = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            // Create renter with balance
            $renter = $this->createTestRenter();
            $renter->balance = round(rand(100, 1000) / 10, 8);
            $renter->save();
            $initialBalance = $renter->balance;
            
            // Create order
            $order = $this->createTestOrder($renter->id);
            $orderCost = round(rand(1, 50) / 10, 8);
            
            // Deactivate order (simulating completion)
            $order->active = 0;
            $order->save();
            
            // Deduct cost from balance using the model method
            if (!$renter->deductBalance($orderCost)) {
                $failures[] = [
                    'iteration' => $i,
                    'reason' => 'Failed to deduct balance'
                ];
                $order->delete();
                $renter->delete();
                continue;
            }
            
            // Reload renter to get fresh data
            $renter->refresh();
            
            // Verify balance decreased by cost
            $expectedBalance = $initialBalance - $orderCost;
            if (abs($renter->balance - $expectedBalance) > 0.00000001) {
                $failures[] = [
                    'iteration' => $i,
                    'initial_balance' => $initialBalance,
                    'order_cost' => $orderCost,
                    'expected_balance' => $expectedBalance,
                    'actual_balance' => $renter->balance,
                    'reason' => 'Balance not decreased by exact order cost'
                ];
            }
            
            // Verify spent field increased by cost
            $expectedSpent = $orderCost;
            if (abs($renter->spent - $expectedSpent) > 0.00000001) {
                $failures[] = [
                    'iteration' => $i,
                    'order_cost' => $orderCost,
                    'expected_spent' => $expectedSpent,
                    'actual_spent' => $renter->spent,
                    'reason' => 'Spent field not increased by order cost'
                ];
            }
            
            // Verify order marked as inactive (completed)
            $completedOrder = Jobs::findOne($order->id);
            if ($completedOrder->active !== 0) {
                $failures[] = [
                    'iteration' => $i,
                    'expected_active' => 0,
                    'actual_active' => $completedOrder->active,
                    'reason' => 'Order not marked as inactive after completion'
                ];
            }
            
            // Clean up
            $order->delete();
            $renter->delete();
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
     * Property 22: Balance History Completeness
     * 
     * For any renter account, the balance history should display all deposits,
     * order costs, and refunds.
     * 
     * Validates: Requirements 4.8
     * 
     * @test
     */
    public function testBalanceHistoryCompleteness()
    {
        // Feature: yiimp-to-yiimp2-migration, Property 22: Balance history completeness
        
        $iterations = 100;
        $failures = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            // Create renter
            $renter = $this->createTestRenter();
            
            // Create random number of transactions of different types
            $numDeposits = rand(1, 3);
            $numOrders = rand(1, 3);
            $numRefunds = rand(0, 2);
            
            $createdDeposits = [];
            $createdOrders = [];
            $createdRefunds = [];
            
            // Create deposit transactions
            for ($j = 0; $j < $numDeposits; $j++) {
                $amount = round(rand(100, 10000) / 100, 8);
                if (Rentertxs::createDeposit($renter->id, $amount, 'test_address_' . $j, 'tx_' . $j)) {
                    $createdDeposits[] = $amount;
                }
            }
            
            // Create order transactions
            for ($j = 0; $j < $numOrders; $j++) {
                $amount = round(rand(10, 1000) / 100, 8);
                $jobId = rand(1000, 9999);
                if (Rentertxs::createOrder($renter->id, $amount, $jobId)) {
                    $createdOrders[] = $amount;
                }
            }
            
            // Create refund transactions
            for ($j = 0; $j < $numRefunds; $j++) {
                $amount = round(rand(10, 500) / 100, 8);
                if (Rentertxs::createRefund($renter->id, $amount, 'Test refund ' . $j)) {
                    $createdRefunds[] = $amount;
                }
            }
            
            // Retrieve balance history (simulating what the controller does)
            $balanceHistory = Rentertxs::find()
                ->where(['renterid' => $renter->id])
                ->orderBy(['time' => SORT_DESC])
                ->all();
            
            // Verify total count matches
            $expectedCount = $numDeposits + $numOrders + $numRefunds;
            if (count($balanceHistory) !== $expectedCount) {
                $failures[] = [
                    'iteration' => $i,
                    'expected_count' => $expectedCount,
                    'actual_count' => count($balanceHistory),
                    'reason' => 'Balance history count does not match created transactions'
                ];
            }
            
            // Count transactions by type in the retrieved history
            $displayedDeposits = 0;
            $displayedOrders = 0;
            $displayedRefunds = 0;
            
            foreach ($balanceHistory as $tx) {
                // Verify transaction has required fields
                if (!isset($tx->id)) {
                    $failures[] = [
                        'iteration' => $i,
                        'reason' => 'Transaction missing ID field'
                    ];
                }
                
                if (!isset($tx->renterid)) {
                    $failures[] = [
                        'iteration' => $i,
                        'tx_id' => $tx->id,
                        'reason' => 'Transaction missing renterid field'
                    ];
                }
                
                if (!isset($tx->amount)) {
                    $failures[] = [
                        'iteration' => $i,
                        'tx_id' => $tx->id,
                        'reason' => 'Transaction missing amount field'
                    ];
                }
                
                if (!isset($tx->type)) {
                    $failures[] = [
                        'iteration' => $i,
                        'tx_id' => $tx->id,
                        'reason' => 'Transaction missing type field'
                    ];
                }
                
                if (!isset($tx->time)) {
                    $failures[] = [
                        'iteration' => $i,
                        'tx_id' => $tx->id,
                        'reason' => 'Transaction missing time field'
                    ];
                }
                
                // Verify renterid matches
                if ($tx->renterid !== $renter->id) {
                    $failures[] = [
                        'iteration' => $i,
                        'tx_id' => $tx->id,
                        'expected_renterid' => $renter->id,
                        'actual_renterid' => $tx->renterid,
                        'reason' => 'Transaction renterid does not match'
                    ];
                }
                
                // Verify amount is positive
                if ($tx->amount <= 0) {
                    $failures[] = [
                        'iteration' => $i,
                        'tx_id' => $tx->id,
                        'amount' => $tx->amount,
                        'reason' => 'Transaction amount is not positive'
                    ];
                }
                
                // Verify time is valid
                if ($tx->time <= 0) {
                    $failures[] = [
                        'iteration' => $i,
                        'tx_id' => $tx->id,
                        'time' => $tx->time,
                        'reason' => 'Transaction time is not valid'
                    ];
                }
                
                // Count by type
                if ($tx->type === Rentertxs::TYPE_DEPOSIT) {
                    $displayedDeposits++;
                } elseif ($tx->type === Rentertxs::TYPE_ORDER) {
                    $displayedOrders++;
                } elseif ($tx->type === Rentertxs::TYPE_REFUND) {
                    $displayedRefunds++;
                }
                
                // Verify type is valid
                if (!in_array($tx->type, [Rentertxs::TYPE_DEPOSIT, Rentertxs::TYPE_ORDER, Rentertxs::TYPE_REFUND, Rentertxs::TYPE_WITHDRAWAL])) {
                    $failures[] = [
                        'iteration' => $i,
                        'tx_id' => $tx->id,
                        'type' => $tx->type,
                        'reason' => 'Transaction type is not valid'
                    ];
                }
            }
            
            // Verify all deposits are displayed
            if ($displayedDeposits !== $numDeposits) {
                $failures[] = [
                    'iteration' => $i,
                    'expected_deposits' => $numDeposits,
                    'actual_deposits' => $displayedDeposits,
                    'reason' => 'Not all deposits displayed in balance history'
                ];
            }
            
            // Verify all orders are displayed
            if ($displayedOrders !== $numOrders) {
                $failures[] = [
                    'iteration' => $i,
                    'expected_orders' => $numOrders,
                    'actual_orders' => $displayedOrders,
                    'reason' => 'Not all order costs displayed in balance history'
                ];
            }
            
            // Verify all refunds are displayed
            if ($displayedRefunds !== $numRefunds) {
                $failures[] = [
                    'iteration' => $i,
                    'expected_refunds' => $numRefunds,
                    'actual_refunds' => $displayedRefunds,
                    'reason' => 'Not all refunds displayed in balance history'
                ];
            }
            
            // Clean up
            Rentertxs::deleteAll(['renterid' => $renter->id]);
            $renter->delete();
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
     * Property 23: Rental Settings Persistence
     * 
     * For any rental settings update (default pools, price limits, notification preferences),
     * the system should persist all changes to the database.
     * 
     * Validates: Requirements 4.9
     * 
     * @test
     */
    public function testRentalSettingsPersistence()
    {
        // Feature: yiimp-to-yiimp2-migration, Property 23: Rental settings persistence
        
        $iterations = 100;
        $failures = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            // Create renter
            $renter = $this->createTestRenter();
            
            // Generate random settings values
            $newSettings = [
                'custom_server' => 'pool' . rand(1, 999) . '.example.com:' . rand(3000, 4000),
                'custom_address' => $this->generateRandomAddress(),
                'email' => 'renter' . $i . '_' . rand(1000, 9999) . '@example.com',
            ];
            
            // Store original values
            $originalSettings = [
                'custom_server' => $renter->custom_server,
                'custom_address' => $renter->custom_address,
                'email' => $renter->email,
            ];
            
            // Update settings
            $renter->custom_server = $newSettings['custom_server'];
            $renter->custom_address = $newSettings['custom_address'];
            $renter->email = $newSettings['email'];
            
            if (!$renter->save()) {
                $failures[] = [
                    'iteration' => $i,
                    'errors' => $renter->errors,
                    'reason' => 'Failed to save settings'
                ];
                $renter->delete();
                continue;
            }
            
            // Reload renter from database to verify persistence
            $reloadedRenter = Renters::findOne($renter->id);
            
            if ($reloadedRenter === null) {
                $failures[] = [
                    'iteration' => $i,
                    'renter_id' => $renter->id,
                    'reason' => 'Failed to reload renter from database'
                ];
                $renter->delete();
                continue;
            }
            
            // Verify custom_server persisted
            if ($reloadedRenter->custom_server !== $newSettings['custom_server']) {
                $failures[] = [
                    'iteration' => $i,
                    'field' => 'custom_server',
                    'expected' => $newSettings['custom_server'],
                    'actual' => $reloadedRenter->custom_server,
                    'reason' => 'custom_server setting not persisted correctly'
                ];
            }
            
            // Verify custom_address persisted
            if ($reloadedRenter->custom_address !== $newSettings['custom_address']) {
                $failures[] = [
                    'iteration' => $i,
                    'field' => 'custom_address',
                    'expected' => $newSettings['custom_address'],
                    'actual' => $reloadedRenter->custom_address,
                    'reason' => 'custom_address setting not persisted correctly'
                ];
            }
            
            // Verify email persisted
            if ($reloadedRenter->email !== $newSettings['email']) {
                $failures[] = [
                    'iteration' => $i,
                    'field' => 'email',
                    'expected' => $newSettings['email'],
                    'actual' => $reloadedRenter->email,
                    'reason' => 'email setting not persisted correctly'
                ];
            }
            
            // Verify updated timestamp was updated
            if ($reloadedRenter->updated <= $renter->created) {
                $failures[] = [
                    'iteration' => $i,
                    'created' => $renter->created,
                    'updated' => $reloadedRenter->updated,
                    'reason' => 'updated timestamp not updated after settings change'
                ];
            }
            
            // Test updating individual settings (partial updates)
            $partialUpdate = [
                'custom_server' => 'newpool' . rand(1, 999) . '.example.com:' . rand(3000, 4000),
            ];
            
            $reloadedRenter->custom_server = $partialUpdate['custom_server'];
            
            if (!$reloadedRenter->save()) {
                $failures[] = [
                    'iteration' => $i,
                    'errors' => $reloadedRenter->errors,
                    'reason' => 'Failed to save partial settings update'
                ];
                $renter->delete();
                continue;
            }
            
            // Reload again to verify partial update
            $finalRenter = Renters::findOne($renter->id);
            
            // Verify partial update persisted
            if ($finalRenter->custom_server !== $partialUpdate['custom_server']) {
                $failures[] = [
                    'iteration' => $i,
                    'field' => 'custom_server',
                    'expected' => $partialUpdate['custom_server'],
                    'actual' => $finalRenter->custom_server,
                    'reason' => 'Partial settings update not persisted correctly'
                ];
            }
            
            // Verify other settings remained unchanged
            if ($finalRenter->custom_address !== $newSettings['custom_address']) {
                $failures[] = [
                    'iteration' => $i,
                    'field' => 'custom_address',
                    'expected' => $newSettings['custom_address'],
                    'actual' => $finalRenter->custom_address,
                    'reason' => 'Other settings changed during partial update'
                ];
            }
            
            if ($finalRenter->email !== $newSettings['email']) {
                $failures[] = [
                    'iteration' => $i,
                    'field' => 'email',
                    'expected' => $newSettings['email'],
                    'actual' => $finalRenter->email,
                    'reason' => 'Other settings changed during partial update'
                ];
            }
            
            // Test clearing settings (setting to null/empty)
            $finalRenter->custom_server = null;
            $finalRenter->custom_address = null;
            
            if (!$finalRenter->save()) {
                $failures[] = [
                    'iteration' => $i,
                    'errors' => $finalRenter->errors,
                    'reason' => 'Failed to save cleared settings'
                ];
                $renter->delete();
                continue;
            }
            
            // Reload to verify clearing
            $clearedRenter = Renters::findOne($renter->id);
            
            // Verify settings were cleared
            if ($clearedRenter->custom_server !== null && $clearedRenter->custom_server !== '') {
                $failures[] = [
                    'iteration' => $i,
                    'field' => 'custom_server',
                    'expected' => 'null or empty',
                    'actual' => $clearedRenter->custom_server,
                    'reason' => 'Settings not cleared correctly'
                ];
            }
            
            if ($clearedRenter->custom_address !== null && $clearedRenter->custom_address !== '') {
                $failures[] = [
                    'iteration' => $i,
                    'field' => 'custom_address',
                    'expected' => 'null or empty',
                    'actual' => $clearedRenter->custom_address,
                    'reason' => 'Settings not cleared correctly'
                ];
            }
            
            // Clean up
            $renter->delete();
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
     * Property 21: Renter Order Display
     * 
     * For any renter with orders, all orders should be displayed with status,
     * hashrate delivered, time remaining, and costs.
     * 
     * Validates: Requirements 4.7
     * 
     * @test
     */
    public function testRenterOrderDisplay()
    {
        // Feature: yiimp-to-yiimp2-migration, Property 21: Renter order display
        
        $iterations = 100;
        $failures = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            // Create renter
            $renter = $this->createTestRenter();
            
            // Create multiple orders for this renter
            $numOrders = rand(1, 5);
            $createdOrders = [];
            
            for ($j = 0; $j < $numOrders; $j++) {
                $order = $this->createTestOrder($renter->id);
                $createdOrders[] = $order;
            }
            
            // Retrieve orders for this renter (simulating what the controller does)
            $displayedOrders = Jobs::find()
                ->where(['renterid' => $renter->id])
                ->all();
            
            // Verify all created orders are displayed
            if (count($displayedOrders) !== $numOrders) {
                $failures[] = [
                    'iteration' => $i,
                    'expected_count' => $numOrders,
                    'actual_count' => count($displayedOrders),
                    'reason' => 'Not all orders displayed'
                ];
            }
            
            // Verify each order has required display fields
            foreach ($displayedOrders as $order) {
                // Verify status field exists (active field)
                if (!isset($order->active)) {
                    $failures[] = [
                        'iteration' => $i,
                        'order_id' => $order->id,
                        'reason' => 'Order missing active status field'
                    ];
                }
                
                // Verify status is a valid value (0 or 1)
                if (!in_array($order->active, [0, 1])) {
                    $failures[] = [
                        'iteration' => $i,
                        'order_id' => $order->id,
                        'active_value' => $order->active,
                        'reason' => 'Order active status has invalid value'
                    ];
                }
                
                // Verify hashrate field exists (speed)
                if (!isset($order->speed)) {
                    $failures[] = [
                        'iteration' => $i,
                        'order_id' => $order->id,
                        'reason' => 'Order missing speed (hashrate) field'
                    ];
                }
                
                // Verify hashrate is a positive number
                if ($order->speed <= 0) {
                    $failures[] = [
                        'iteration' => $i,
                        'order_id' => $order->id,
                        'speed_value' => $order->speed,
                        'reason' => 'Order speed (hashrate) is not positive'
                    ];
                }
                
                // Verify time field exists
                if (!isset($order->time)) {
                    $failures[] = [
                        'iteration' => $i,
                        'order_id' => $order->id,
                        'reason' => 'Order missing time field'
                    ];
                }
                
                // Verify time is a valid timestamp
                if ($order->time <= 0) {
                    $failures[] = [
                        'iteration' => $i,
                        'order_id' => $order->id,
                        'time_value' => $order->time,
                        'reason' => 'Order time is not a valid timestamp'
                    ];
                }
                
                // Verify cost calculation fields exist (price and speed)
                if (!isset($order->price)) {
                    $failures[] = [
                        'iteration' => $i,
                        'order_id' => $order->id,
                        'reason' => 'Order missing price field for cost calculation'
                    ];
                }
                
                // Verify price is a positive number
                if ($order->price <= 0) {
                    $failures[] = [
                        'iteration' => $i,
                        'order_id' => $order->id,
                        'price_value' => $order->price,
                        'reason' => 'Order price is not positive'
                    ];
                }
                
                // Verify cost can be calculated
                $estimatedCost = $order->price * $order->speed;
                if ($estimatedCost <= 0) {
                    $failures[] = [
                        'iteration' => $i,
                        'order_id' => $order->id,
                        'price' => $order->price,
                        'speed' => $order->speed,
                        'calculated_cost' => $estimatedCost,
                        'reason' => 'Calculated cost is not positive'
                    ];
                }
                
                // Verify algorithm field exists
                if (!isset($order->algo)) {
                    $failures[] = [
                        'iteration' => $i,
                        'order_id' => $order->id,
                        'reason' => 'Order missing algorithm field'
                    ];
                }
                
                // Verify target pool fields exist
                if (!isset($order->host) || !isset($order->port)) {
                    $failures[] = [
                        'iteration' => $i,
                        'order_id' => $order->id,
                        'reason' => 'Order missing target pool fields (host/port)'
                    ];
                }
            }
            
            // Clean up
            foreach ($createdOrders as $order) {
                $order->delete();
            }
            $renter->delete();
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
    
    // Helper methods
    
    protected function createTestRenter()
    {
        $renter = new Renters();
        $renter->address = $this->generateRandomAddress();
        $renter->balance = 0;
        $renter->save();
        return $renter;
    }
    
    protected function createTestOrder($renterId)
    {
        $order = new Jobs();
        $order->renterid = $renterId;
        $order->algo = 'sha256';
        $order->price = round(rand(1, 100) / 1000, 4);
        $order->speed = rand(1000000, 1000000000);
        $order->host = 'pool.example.com';
        $order->port = 3333;
        $order->username = 'testuser';
        $order->password = 'testpass';
        $order->ready = 0;
        $order->active = 1;
        $order->save();
        return $order;
    }
    
    protected function generateOrderParams($renterId)
    {
        $algorithms = ['sha256', 'scrypt', 'x11', 'x13', 'lyra2v2'];
        
        return [
            'renterid' => $renterId,
            'algo' => $algorithms[array_rand($algorithms)],
            'price' => round(rand(1, 100) / 1000, 4),
            'speed' => rand(1000000, 1000000000), // Using 'speed' as per Jobs model schema
            'host' => 'pool.example.com',
            'port' => rand(3000, 4000),
            'username' => 'user' . rand(1, 999),
            'password' => 'pass' . rand(1000, 9999),
            'ready' => 0,
            'active' => 0,
        ];
    }
    
    protected function verifyOrderParams($order, $params, $iteration, &$failures)
    {
        // Fields to check based on actual Jobs model schema
        $fieldsToCheck = ['renterid', 'algo', 'price', 'speed', 'host', 'port', 'username', 'password', 'ready', 'active'];
        
        foreach ($fieldsToCheck as $field) {
            if (isset($params[$field])) {
                // For numeric fields, use approximate comparison
                if (in_array($field, ['price', 'speed'])) {
                    if (abs($order->$field - $params[$field]) > 0.0001) {
                        $failures[] = [
                            'iteration' => $iteration,
                            'field' => $field,
                            'expected' => $params[$field],
                            'actual' => $order->$field,
                            'reason' => 'Order parameter not persisted correctly'
                        ];
                    }
                } else {
                    // For other fields, use exact comparison
                    if ($order->$field != $params[$field]) {
                        $failures[] = [
                            'iteration' => $iteration,
                            'field' => $field,
                            'expected' => $params[$field],
                            'actual' => $order->$field,
                            'reason' => 'Order parameter not persisted correctly'
                        ];
                    }
                }
            }
        }
        
        // Verify that ID was generated
        if (empty($order->id)) {
            $failures[] = [
                'iteration' => $iteration,
                'reason' => 'Order ID not generated'
            ];
        }
        
        // Verify that time was set (from beforeSave)
        if (empty($order->time)) {
            $failures[] = [
                'iteration' => $iteration,
                'reason' => 'Order time not set'
            ];
        }
    }
    
    protected function generateRandomAddress()
    {
        $chars = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
        $address = '';
        for ($i = 0; $i < 34; $i++) {
            $address .= $chars[rand(0, strlen($chars) - 1)];
        }
        return $address;
    }
}
