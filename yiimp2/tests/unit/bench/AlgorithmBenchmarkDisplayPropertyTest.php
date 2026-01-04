<?php

namespace tests\unit\bench;

use tests\helpers\DatabaseTestHelper;

use Codeception\Test\Unit;
use app\models\Benchmarks;
use app\models\BenchChips;

/**
 * Property-based tests for Algorithm Benchmark Display
 * 
 * Feature: yiimp-to-yiimp2-migration, Property 27: Algorithm benchmark display
 */
class AlgorithmBenchmarkDisplayPropertyTest extends Unit
{
    use DatabaseTestHelper;

    /**
     * Property 27: Algorithm Benchmark Display
     * 
     * For any algorithm in the benchmark database, the system should display all
     * benchmark entries for that algorithm.
     * 
     * Validates: Requirements 7.2
     * 
     * @test
     */
    public function testAlgorithmBenchmarkDisplay()
    {
        // Feature: yiimp-to-yiimp2-migration, Property 27: Algorithm benchmark display
        
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
            // Generate random algorithm name
            $algo = $this->generateRandomAlgorithm();
            
            // Create random number of benchmark entries for this algorithm
            $benchmarkCount = rand(1, 10);
            $createdBenchmarks = [];
            
            // Create test chips if needed
            $chips = $this->createTestChips(rand(1, 3));
            
            for ($j = 0; $j < $benchmarkCount; $j++) {
                $benchmark = $this->createTestBenchmark($algo, $chips);
                if ($benchmark) {
                    $createdBenchmarks[] = $benchmark;
                }
            }
            
            // Query benchmarks for this algorithm (simulating what the controller does)
            $displayedBenchmarks = Benchmarks::find()
                ->where(['algo' => $algo])
                ->all();
            
            // Verify all created benchmarks are displayed
            if (count($displayedBenchmarks) != count($createdBenchmarks)) {
                $failures[] = [
                    'iteration' => $i,
                    'algorithm' => $algo,
                    'expected_count' => count($createdBenchmarks),
                    'actual_count' => count($displayedBenchmarks),
                    'reason' => 'Benchmark count mismatch for algorithm'
                ];
            }
            
            // Verify each benchmark has required fields
            foreach ($displayedBenchmarks as $benchmark) {
                $requiredFields = ['algo', 'type', 'khps', 'time'];
                foreach ($requiredFields as $field) {
                    if ($benchmark->$field === null) {
                        $failures[] = [
                            'iteration' => $i,
                            'algorithm' => $algo,
                            'benchmark_id' => $benchmark->id,
                            'missing_field' => $field,
                            'reason' => 'Required field missing from benchmark'
                        ];
                    }
                }
                
                // Verify algorithm matches
                if ($benchmark->algo !== $algo) {
                    $failures[] = [
                        'iteration' => $i,
                        'algorithm' => $algo,
                        'benchmark_id' => $benchmark->id,
                        'actual_algo' => $benchmark->algo,
                        'reason' => 'Benchmark algorithm mismatch'
                    ];
                }
            }
            
            // Test aggregated chip benchmarks (as shown in algo view)
            $chipBenchmarks = Benchmarks::find()
                ->select([
                    'bench_chips.id',
                    'bench_chips.devicetype',
                    'bench_chips.chip',
                    'AVG(benchmarks.khps) as avg_khps',
                    'AVG(benchmarks.power) as avg_power',
                    'COUNT(benchmarks.id) as record_count',
                ])
                ->innerJoin('bench_chips', 'bench_chips.id = benchmarks.idchip')
                ->where(['benchmarks.algo' => $algo])
                ->andWhere(['>', 'benchmarks.idchip', 0])
                ->groupBy(['bench_chips.id', 'bench_chips.devicetype', 'bench_chips.chip'])
                ->asArray()
                ->all();
            
            // Verify chip benchmarks are properly aggregated
            foreach ($chipBenchmarks as $chipBench) {
                if (!isset($chipBench['avg_khps']) || $chipBench['avg_khps'] === null) {
                    $failures[] = [
                        'iteration' => $i,
                        'algorithm' => $algo,
                        'chip_id' => $chipBench['id'],
                        'reason' => 'Average hashrate missing from chip benchmark'
                    ];
                }
                
                if (!isset($chipBench['record_count']) || $chipBench['record_count'] < 1) {
                    $failures[] = [
                        'iteration' => $i,
                        'algorithm' => $algo,
                        'chip_id' => $chipBench['id'],
                        'reason' => 'Invalid record count in chip benchmark'
                    ];
                }
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
     * Generate random algorithm name
     * 
     * @return string
     */
    protected function generateRandomAlgorithm()
    {
        $algorithms = [
            'sha256', 'scrypt', 'x11', 'x13', 'x15', 'x17',
            'neoscrypt', 'lyra2v2', 'lyra2z', 'blake2s',
            'equihash', 'kawpow', 'ethash', 'yescrypt',
            'quark', 'qubit', 'skein', 'groestl', 'keccak',
            'nist5', 'c11', 'phi1612', 'tribus', 'timetravel'
        ];
        
        return $algorithms[array_rand($algorithms)];
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
     * @param BenchChips[] $chips
     * @return Benchmarks|null
     */
    protected function createTestBenchmark($algo, $chips)
    {
        $benchmark = new Benchmarks();
        $benchmark->algo = $algo;
        $benchmark->type = ['gpu', 'cpu', 'asic'][array_rand(['gpu', 'cpu', 'asic'])];
        $benchmark->khps = rand(100, 1000000) / 10; // Random hashrate
        $benchmark->device = $this->generateRandomDeviceName();
        $benchmark->vendorid = sprintf('%04x:%04x', rand(0x1000, 0xFFFF), rand(0x1000, 0xFFFF));
        $benchmark->time = time() - rand(0, 86400 * 30); // Within last 30 days
        
        // Assign to a chip if available
        if (!empty($chips)) {
            $chip = $chips[array_rand($chips)];
            $benchmark->idchip = $chip->id;
            $benchmark->chip = $chip->chip;
        } else {
            $benchmark->idchip = 0;
            $benchmark->chip = '';
        }
        
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
    
    /**
     * Generate random device name
     * 
     * @return string
     */
    protected function generateRandomDeviceName()
    {
        $devices = [
            'GeForce GTX 1080 Ti',
            'GeForce RTX 3090',
            'Radeon RX 6900 XT',
            'Radeon VII',
            'Intel Core i9-9900K',
            'AMD Ryzen 9 5950X',
            'Antminer S19 Pro',
            'Whatsminer M30S++'
        ];
        
        return $devices[array_rand($devices)];
    }
}
