<?php

namespace tests\unit\models;

use Codeception\Test\Unit;
use app\models\Nicehash;

/**
 * Property-based test for NiceHash share attribution
 * 
 * Feature: yiimp-to-yiimp2-migration, Property 25
 * 
 * Note: Share attribution is primarily handled by the C++ stratum server.
 * This test verifies that the Nicehash model correctly tracks share statistics
 * that are written by the stratum server.
 */
class NicehashShareAttributionTest extends Unit
{
    /**
     * Property 25: NiceHash Share Attribution
     * 
     * For any share submitted by NiceHash miners, the share should be correctly 
     * attributed to the corresponding NiceHash order.
     * 
     * Validates: Requirements 5.4
     * 
     * This test verifies that:
     * 1. NiceHash orders can track accepted and rejected shares
     * 2. Share counts are properly maintained
     * 3. Multiple orders can track shares independently
     * 
     * @test
     */
    public function testNicehashShareAttribution()
    {
        // Feature: yiimp-to-yiimp2-migration, Property 25: NiceHash share attribution
        
        $iterations = 100;
        $failures = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            // Generate random share submission data
            $shareData = $this->generateRandomShareData();
            
            // Create or find NiceHash order
            $order = $this->createOrFindOrder($shareData['algo'], $shareData['orderid']);
            
            if ($order === null) {
                $failures[] = [
                    'iteration' => $i,
                    'reason' => 'Failed to create or find order',
                    'data' => $shareData
                ];
                continue;
            }
            
            // Record initial share counts
            $initialAccepted = $order->accepted ?? 0;
            $initialRejected = $order->rejected ?? 0;
            
            // Simulate share attribution (what stratum server would do)
            if ($shareData['accepted']) {
                $order->accepted = $initialAccepted + $shareData['share_count'];
            } else {
                $order->rejected = $initialRejected + $shareData['share_count'];
            }
            
            // Save the updated order
            if (!$order->save()) {
                $failures[] = [
                    'iteration' => $i,
                    'reason' => 'Failed to save share attribution',
                    'data' => $shareData,
                    'errors' => $order->errors
                ];
                continue;
            }
            
            // Verify share attribution
            $updatedOrder = Nicehash::findOne($order->id);
            
            if ($shareData['accepted']) {
                $expectedAccepted = $initialAccepted + $shareData['share_count'];
                if (abs($updatedOrder->accepted - $expectedAccepted) > 0.01) {
                    $failures[] = [
                        'iteration' => $i,
                        'reason' => 'Accepted shares not correctly attributed',
                        'expected' => $expectedAccepted,
                        'actual' => $updatedOrder->accepted,
                        'data' => $shareData
                    ];
                }
            } else {
                $expectedRejected = $initialRejected + $shareData['share_count'];
                if (abs($updatedOrder->rejected - $expectedRejected) > 0.01) {
                    $failures[] = [
                        'iteration' => $i,
                        'reason' => 'Rejected shares not correctly attributed',
                        'expected' => $expectedRejected,
                        'actual' => $updatedOrder->rejected,
                        'data' => $shareData
                    ];
                }
            }
            
            // Clean up
            $updatedOrder->delete();
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
     * Test that shares are attributed to the correct order by order ID
     * 
     * @test
     */
    public function testShareAttributionByOrderId()
    {
        // Feature: yiimp-to-yiimp2-migration, Property 25: NiceHash share attribution (by order ID)
        
        // Create two different orders for the same algorithm
        $order1 = new Nicehash();
        $order1->algo = 'sha256';
        $order1->orderid = 100001;
        $order1->accepted = 0;
        $order1->rejected = 0;
        $order1->save();
        
        $order2 = new Nicehash();
        $order2->algo = 'sha256';
        $order2->orderid = 100002;
        $order2->accepted = 0;
        $order2->rejected = 0;
        $order2->save();
        
        // Attribute shares to order 1
        $order1->accepted = 1000;
        $order1->save();
        
        // Attribute shares to order 2
        $order2->accepted = 2000;
        $order2->save();
        
        // Verify shares are correctly separated
        $retrieved1 = Nicehash::findByOrderId(100001);
        $retrieved2 = Nicehash::findByOrderId(100002);
        
        $this->assertEquals(1000, $retrieved1->accepted, 'Order 1 shares incorrect');
        $this->assertEquals(2000, $retrieved2->accepted, 'Order 2 shares incorrect');
        
        // Verify they're independent
        $this->assertNotEquals($retrieved1->accepted, $retrieved2->accepted);
        
        // Clean up
        $retrieved1->delete();
        $retrieved2->delete();
    }
    
    /**
     * Test that share statistics are correctly calculated
     * 
     * @test
     */
    public function testShareStatisticsCalculation()
    {
        // Feature: yiimp-to-yiimp2-migration, Property 25: NiceHash share attribution (statistics)
        
        $order = new Nicehash();
        $order->algo = 'x11';
        $order->orderid = 200001;
        $order->accepted = 9500;
        $order->rejected = 500;
        $order->save();
        
        // Test total shares
        $this->assertEquals(10000, $order->getTotalShares());
        
        // Test acceptance rate
        $acceptanceRate = $order->getAcceptanceRate();
        $this->assertEquals(95.0, $acceptanceRate, '', 0.01);
        
        // Test formatted acceptance rate
        $formatted = $order->getFormattedAcceptanceRate();
        $this->assertStringContainsString('95', $formatted);
        $this->assertStringContainsString('%', $formatted);
        
        // Clean up
        $order->delete();
    }
    
    /**
     * Test that multiple algorithms can track shares independently
     * 
     * @test
     */
    public function testMultiAlgorithmShareAttribution()
    {
        // Feature: yiimp-to-yiimp2-migration, Property 25: NiceHash share attribution (multi-algo)
        
        $algorithms = ['sha256', 'scrypt', 'x11', 'equihash'];
        $orders = [];
        
        // Create orders for different algorithms
        foreach ($algorithms as $algo) {
            $order = new Nicehash();
            $order->algo = $algo;
            $order->orderid = rand(300000, 399999);
            $order->accepted = rand(1000, 10000);
            $order->rejected = rand(0, 1000);
            $order->save();
            
            $orders[$algo] = [
                'id' => $order->id,
                'accepted' => $order->accepted,
                'rejected' => $order->rejected,
            ];
        }
        
        // Verify each algorithm's shares are tracked independently
        foreach ($algorithms as $algo) {
            $order = Nicehash::findOne($orders[$algo]['id']);
            
            $this->assertEquals($algo, $order->algo);
            $this->assertEquals($orders[$algo]['accepted'], $order->accepted);
            $this->assertEquals($orders[$algo]['rejected'], $order->rejected);
            
            // Clean up
            $order->delete();
        }
    }
    
    /**
     * Generate random share submission data
     * 
     * @return array
     */
    protected function generateRandomShareData()
    {
        $algorithms = ['sha256', 'scrypt', 'x11', 'x13', 'x15', 'quark', 'lyra2v2', 'equihash'];
        
        return [
            'algo' => $algorithms[array_rand($algorithms)],
            'orderid' => rand(100000, 999999),
            'share_count' => rand(1, 100),
            'accepted' => (bool) rand(0, 1), // true = accepted, false = rejected
        ];
    }
    
    /**
     * Create or find a NiceHash order
     * 
     * @param string $algo
     * @param int $orderid
     * @return Nicehash|null
     */
    protected function createOrFindOrder($algo, $orderid)
    {
        $order = Nicehash::findByOrderId($orderid);
        
        if ($order === null) {
            $order = new Nicehash();
            $order->algo = $algo;
            $order->orderid = $orderid;
            $order->accepted = 0;
            $order->rejected = 0;
            $order->active = 1;
            
            if (!$order->save()) {
                return null;
            }
        }
        
        return $order;
    }
}
