<?php

namespace tests\unit\bench;

use tests\helpers\DatabaseTestHelper;

use Codeception\Test\Unit;
use app\models\Benchmarks;
use app\models\BenchChips;

/**
 * Property-based tests for Device Filter Accuracy
 * 
 * Feature: yiimp-to-yiimp2-migration, Property 28: Device filter accuracy
 */
class DeviceFilterPropertyTest extends Unit
{
    use DatabaseTestHelper;

    /**
     * Property 28: Device Filter Accuracy
     * 
     * For any device filter applied to benchmarks, the system should return only
     * benchmark entries matching that specific device.
     * 
     * Validates: Requirements 7.3
     * 
     * @test
     */
    public function testDeviceFilterAccuracy()
    {
        // Feature: yiimp-to-yiimp2-migration, Property 28: Device filter accuracy
        
        // Skip test if database is not available
        
        $this->requireDatabase();
        
        // Check if database is available
        try {
            \Yii::$app->db->open();
        } catch (\Exception $e) {
            $this->markTestSkipped(
                'Database connection not available. ' .
                'This test requires a working database connection. ' .
                'Error: ' . $e->getMessage()
            );
            return;
        }
        
        $iterations = 100;
        $failures = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            // Create multiple chips (devices) with different characteristics
            $chipCount = rand(2, 5);
            $chips = $this->createTestChips($chipCount);
            
            if (empty($chips)) {
                continue; // Skip if chip creation failed
            }
            
            // Create benchmarks for each chip across multiple algorithms
            $createdBenchmarks = [];
            $algorithmCount = rand(2, 5);
            $algorithms = $this->generateRandomAlgorithms($algorithmCount);
            
            foreach ($chips as $chip) {
                foreach ($algorithms as $algo) {
                    // Create 1-3 benchmarks per chip per algorithm
                    $benchCount = rand(1, 3);
                    for ($j = 0; $j < $benchCount; $j++) {
                        $benchmark = $this->createTestBenchmark($algo, $chip);
                        if ($benchmark) {
                            $createdBenchmarks[] = $benchmark;
                        }
                    }
                }
            }
            
            // Test filtering by each chip (device)
            foreach ($chips as $targetChip) {
                // Apply device filter (simulating what the controller does)
                $filteredBenchmarks = Benchmarks::find()
                    ->where(['idchip' => $targetChip->id])
                    ->all();
                
                // Verify all returned benchmarks belong to the target device
                foreach ($filteredBenchmarks as $benchmark) {
                    if ($benchmark->idchip != $targetChip->id) {
                        $failures[] = [
                            'iteration' => $i,
                            'target_chip_id' => $targetChip->id,
                            'target_chip_name' => $targetChip->chip,
                            'benchmark_id' => $benchmark->id,
                            'actual_chip_id' => $benchmark->idchip,
                            'reason' => 'Benchmark does not match filtered device'
                        ];
                    }
                }
                
                // Verify no benchmarks from other devices are included
                $expectedCount = 0;
                foreach ($createdBenchmarks as $benchmark) {
                    if ($benchmark->idchip == $targetChip->id) {
                        $expectedCount++;
                    }
                }
                
                if (count($filteredBenchmarks) != $expectedCount) {
                    $failures[] = [
                        'iteration' => $i,
                        'target_chip_id' => $targetChip->id,
                        'target_chip_name' => $targetChip->chip,
                        'expected_count' => $expectedCount,
                        'actual_count' => count($filteredBenchmarks),
                        'reason' => 'Filtered benchmark count mismatch'
                    ];
                }
                
                // Test combined filter: device + algorithm
                foreach ($algorithms as $algo) {
                    $combinedFiltered = Benchmarks::find()
                        ->where(['idchip' => $targetChip->id, 'algo' => $algo])
                        ->all();
                    
                    // Verify all results match both filters
                    foreach ($combinedFiltered as $benchmark) {
                        if ($benchmark->idchip != $targetChip->id) {
                            $failures[] = [
                                'iteration' => $i,
                                'target_chip_id' => $targetChip->id,
                                'target_algo' => $algo,
                                'benchmark_id' => $benchmark->id,
                                'actual_chip_id' => $benchmark->idchip,
                                'reason' => 'Combined filter: device mismatch'
                            ];
                        }
                        
                        if ($benchmark->algo !== $algo) {
                            $failures[] = [
                                'iteration' => $i,
                                'target_chip_id' => $targetChip->id,
                                'target_algo' => $algo,
                                'benchmark_id' => $benchmark->id,
                                'actual_algo' => $benchmark->algo,
                                'reason' => 'Combined filter: algorithm mismatch'
                            ];
                        }
                    }
                    
                    // Count expected results for combined filter
                    $expectedCombinedCount = 0;
                    foreach ($createdBenchmarks as $benchmark) {
                        if ($benchmark->idchip == $targetChip->id && $benchmark->algo === $algo) {
                            $expectedCombinedCount++;
                        }
                    }
                    
                    if (count($combinedFiltered) != $expectedCombinedCount) {
                        $failures[] = [
                            'iteration' => $i,
                            'target_chip_id' => $targetChip->id,
                            'target_algo' => $algo,
                            'expected_count' => $expectedCombinedCount,
                            'actual_count' => count($combinedFiltered),
                            'reason' => 'Combined filter: count mismatch'
                        ];
                    }
                }
            }
            
            // Test that filtering by non-existent device returns empty results
            $nonExistentChipId = 999999;
            $emptyResults = Benchmarks::find()
                ->where(['idchip' => $nonExistentChipId])
                ->all();
            
            if (!empty($emptyResults)) {
                $failures[] = [
                    'iteration' => $i,
                    'non_existent_chip_id' => $nonExistentChipId,
                    'result_count' => count($emptyResults),
                    'reason' => 'Non-existent device filter should return empty results'
                ];
            }
            
            // Clean up
            foreach ($createdBenchmarks as $benchmark) {
                $benchmark->delete();
            }
            foreach ($chips as $chip) {
                $chip->delete();
            }
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
     * Generate random algorithm names
     * 
     * @param int $count
     * @return array
     */
    protected function generateRandomAlgorithms($count)
    {
        $allAlgorithms = [
            'sha256', 'scrypt', 'x11', 'x13', 'x15', 'x17',
            'neoscrypt', 'lyra2v2', 'lyra2z', 'blake2s',
            'equihash', 'kawpow', 'ethash', 'yescrypt',
            'quark', 'qubit', 'skein', 'groestl', 'keccak',
            'nist5', 'c11', 'phi1612', 'tribus', 'timetravel'
        ];
        
        shuffle($allAlgorithms);
        return array_slice($allAlgorithms, 0, $count);
    }
    
    /**
     * Create test chips
     * 
     * @param int $count
     * @return BenchChips[]
     */
    protected function createTestChips($count)
    {
        $chips = [];
        $deviceTypes = ['gpu', 'cpu', 'asic', 'fpga'];
        
        for ($i = 0; $i < $count; $i++) {
            $chip = new BenchChips();
            $chip->devicetype = $deviceTypes[array_rand($deviceTypes)];
            $chip->chip = $this->generateRandomChipName();
            $chip->vendorid = sprintf('%04x:%04x', rand(0x1000, 0xFFFF), rand(0x1000, 0xFFFF));
            $chip->year = rand(2015, 2024);
            $chip->maxtdp = rand(50, 350);
            
            if ($chip->save()) {
                $chips[] = $chip;
            }
        }
        
        return $chips;
    }
    
    /**
     * Generate random chip name
     * 
     * @return string
     */
    protected function generateRandomChipName()
    {
        $vendors = ['NVIDIA', 'AMD', 'Intel', 'Bitmain', 'Innosilicon'];
        $models = ['GTX', 'RTX', 'RX', 'Vega', 'Radeon', 'S', 'T', 'A'];
        $numbers = [1050, 1060, 1070, 1080, 2060, 2070, 2080, 3060, 3070, 3080, 3090, 
                    4070, 4080, 4090, 5700, 6700, 6800, 6900, 7900];
        
        $vendor = $vendors[array_rand($vendors)];
        $model = $models[array_rand($models)];
        $number = $numbers[array_rand($numbers)];
        
        return "$vendor $model $number";
    }
    
    /**
     * Create test benchmark
     * 
     * @param string $algo
     * @param BenchChips $chip
     * @return Benchmarks|null
     */
    protected function createTestBenchmark($algo, $chip)
    {
        $benchmark = new Benchmarks();
        $benchmark->algo = $algo;
        $benchmark->type = $chip->devicetype;
        $benchmark->khps = rand(100, 1000000) / 10; // Random hashrate
        $benchmark->device = $chip->chip;
        $benchmark->vendorid = $chip->vendorid;
        $benchmark->chip = $chip->chip;
        $benchmark->idchip = $chip->id;
        $benchmark->time = time() - rand(0, 86400 * 30); // Within last 30 days
        
        // Optional fields
        $benchmark->power = rand(0, 1) ? rand(50, 350) : null;
        $benchmark->intensity = rand(0, 1) ? rand(10, 25) : null;
        $benchmark->freq = rand(0, 1) ? rand(1000, 2500) : null;
        $benchmark->memf = rand(0, 1) ? rand(4000, 8000) : null;
        
        if ($benchmark->save()) {
            return $benchmark;
        }
        
        return null;
    }
}
