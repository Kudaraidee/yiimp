<?php

namespace tests\unit\models;

use tests\helpers\DatabaseTestHelper;

use Codeception\Test\Unit;
use app\models\Nicehash;

/**
 * Property-based tests for Nicehash model
 * 
 * Feature: yiimp-to-yiimp2-migration
 */
class NicehashPropertyTest extends Unit
{
    use DatabaseTestHelper;

    /**
     * Property 24: NiceHash Order Tracking
     * 
     * For any NiceHash order, the system should store order ID, algorithm, 
     * and hashrate separately from regular miner data.
     * 
     * Validates: Requirements 5.2
     * 
     * @test
     */
    public function testNicehashOrderTracking()
    {
        // Feature: yiimp-to-yiimp2-migration, Property 24: NiceHash order tracking
        
        // Skip test if database is not available
        
        $this->requireDatabase();
        
        // This property test verifies that NiceHash orders maintain their data integrity
        // We test with multiple random order configurations
        
        $iterations = 100;
        $failures = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            // Generate random NiceHash order data
            $orderData = $this->generateRandomOrderData();
            
            // Create a new NiceHash order
            $order = new Nicehash();
            $order->attributes = $orderData;
            
            // Validate the model
            if (!$order->validate()) {
                $failures[] = [
                    'iteration' => $i,
                    'data' => $orderData,
                    'errors' => $order->errors,
                    'reason' => 'Validation failed for valid order data'
                ];
                continue;
            }
            
            // Save the order
            if (!$order->save()) {
                $failures[] = [
                    'iteration' => $i,
                    'data' => $orderData,
                    'errors' => $order->errors,
                    'reason' => 'Failed to save valid order'
                ];
                continue;
            }
            
            // Retrieve the order from database
            $savedOrder = Nicehash::findOne($order->id);
            
            if ($savedOrder === null) {
                $failures[] = [
                    'iteration' => $i,
                    'data' => $orderData,
                    'reason' => 'Order not found after save'
                ];
                continue;
            }
            
            // Verify all required fields are preserved
            $this->assertOrderDataIntegrity($savedOrder, $orderData, $i, $failures);
            
            // Clean up
            $savedOrder->delete();
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
     * Generate random NiceHash order data
     * 
     * @return array
     */
    protected function generateRandomOrderData()
    {
        $algorithms = ['sha256', 'scrypt', 'x11', 'x13', 'x15', 'quark', 'lyra2v2', 'equihash'];
        
        return [
            'active' => rand(0, 1),
            'orderid' => rand(1, 999999),
            'last_decrease' => time() - rand(0, 86400),
            'algo' => $algorithms[array_rand($algorithms)],
            'btc' => round(rand(0, 100000) / 100000, 8),
            'price' => round(rand(1, 10000) / 10000, 4),
            'speed' => rand(1000000, 1000000000000),
            'workers' => rand(0, 100),
            'accepted' => rand(0, 1000000),
            'rejected' => rand(0, 10000),
        ];
    }
    
    /**
     * Assert that order data integrity is maintained
     * 
     * @param Nicehash $savedOrder
     * @param array $originalData
     * @param int $iteration
     * @param array &$failures
     */
    protected function assertOrderDataIntegrity($savedOrder, $originalData, $iteration, &$failures)
    {
        // Check order ID
        if ($savedOrder->orderid != $originalData['orderid']) {
            $failures[] = [
                'iteration' => $iteration,
                'field' => 'orderid',
                'expected' => $originalData['orderid'],
                'actual' => $savedOrder->orderid,
                'reason' => 'Order ID not preserved'
            ];
        }
        
        // Check algorithm
        if ($savedOrder->algo !== $originalData['algo']) {
            $failures[] = [
                'iteration' => $iteration,
                'field' => 'algo',
                'expected' => $originalData['algo'],
                'actual' => $savedOrder->algo,
                'reason' => 'Algorithm not preserved'
            ];
        }
        
        // Check speed (hashrate)
        if (abs($savedOrder->speed - $originalData['speed']) > 0.01) {
            $failures[] = [
                'iteration' => $iteration,
                'field' => 'speed',
                'expected' => $originalData['speed'],
                'actual' => $savedOrder->speed,
                'reason' => 'Speed (hashrate) not preserved'
            ];
        }
        
        // Check active status
        if ($savedOrder->active != $originalData['active']) {
            $failures[] = [
                'iteration' => $iteration,
                'field' => 'active',
                'expected' => $originalData['active'],
                'actual' => $savedOrder->active,
                'reason' => 'Active status not preserved'
            ];
        }
        
        // Check BTC balance
        if (abs($savedOrder->btc - $originalData['btc']) > 0.00000001) {
            $failures[] = [
                'iteration' => $iteration,
                'field' => 'btc',
                'expected' => $originalData['btc'],
                'actual' => $savedOrder->btc,
                'reason' => 'BTC balance not preserved'
            ];
        }
        
        // Check price
        if (abs($savedOrder->price - $originalData['price']) > 0.0001) {
            $failures[] = [
                'iteration' => $iteration,
                'field' => 'price',
                'expected' => $originalData['price'],
                'actual' => $savedOrder->price,
                'reason' => 'Price not preserved'
            ];
        }
        
        // Check workers count
        if ($savedOrder->workers != $originalData['workers']) {
            $failures[] = [
                'iteration' => $iteration,
                'field' => 'workers',
                'expected' => $originalData['workers'],
                'actual' => $savedOrder->workers,
                'reason' => 'Workers count not preserved'
            ];
        }
        
        // Check accepted shares
        if (abs($savedOrder->accepted - $originalData['accepted']) > 0.01) {
            $failures[] = [
                'iteration' => $iteration,
                'field' => 'accepted',
                'expected' => $originalData['accepted'],
                'actual' => $savedOrder->accepted,
                'reason' => 'Accepted shares not preserved'
            ];
        }
        
        // Check rejected shares
        if (abs($savedOrder->rejected - $originalData['rejected']) > 0.01) {
            $failures[] = [
                'iteration' => $iteration,
                'field' => 'rejected',
                'expected' => $originalData['rejected'],
                'actual' => $savedOrder->rejected,
                'reason' => 'Rejected shares not preserved'
            ];
        }
    }
    
    /**
     * Test that NiceHash orders are stored separately from regular miner data
     * 
     * This verifies that the nicehash table is distinct and doesn't interfere
     * with other mining pool tables
     * 
     * @test
     */
    public function testNicehashOrderSeparation()
    {
        // Feature: yiimp-to-yiimp2-migration, Property 24: NiceHash order tracking (separation)
        
        // Skip test if database is not available
        
        $this->requireDatabase();
        
        // Create a NiceHash order
        $order = new Nicehash();
        $order->algo = 'sha256';
        $order->orderid = 999999;
        $order->speed = 1000000000;
        $order->active = 1;
        
        $this->assertTrue($order->save(), 'Failed to save NiceHash order');
        
        // Verify it's stored in the nicehash table
        $this->assertEquals('nicehash', Nicehash::tableName());
        
        // Verify we can query it independently
        $found = Nicehash::findByOrderId(999999);
        $this->assertNotNull($found, 'Order not found by order ID');
        $this->assertEquals('sha256', $found->algo);
        $this->assertEquals(1000000000, $found->speed);
        
        // Clean up
        $found->delete();
    }
}
