<?php

namespace tests\unit\bench;

use tests\helpers\DatabaseTestHelper;

use Codeception\Test\Unit;
use app\models\Benchmarks;
use app\models\BenchChips;

/**
 * Property-based tests for Benchmark Data Persistence
 * 
 * Feature: yiimp-to-yiimp2-migration, Property 29: Benchmark data persistence
 */
class BenchmarkDataPersistencePropertyTest extends Unit
{
    use DatabaseTestHelper;

    /**
     * Property 29: Benchmark Data Persistence
     * 
     * For any benchmark submission, all required fields (device model, chip type,
     * algorithm, measured hashrate) should be stored in the database.
     * 
     * Validates: Requirements 7.4
     * 
     * @test
     */
    public function testBenchmarkDataPersistence()
    {
        // Feature: yiimp-to-yiimp2-migration, Property 29: Benchmark data persistence
        
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
            // Generate random benchmark data
            $algo = $this->generateRandomAlgorithm();
            $deviceModel = $this->generateRandomDeviceName();
            $chipType = $this->generateRandomChipName();
            $measuredHashrate = $this->generateRandomHashrate();
            
            // Create chip if needed
            $chip = $this->createTestChip($chipType);
            
            // Create benchmark with all required fields
            $benchmark = new Benchmarks();
            $benchmark->algo = $algo;
            $benchmark->type = $this->generateRandomDeviceType();
            $benchmark->khps = $measuredHashrate;
            $benchmark->device = $deviceModel;
            $benchmark->chip = $chipType;
            $benchmark->time = time();
            
            // Add optional fields to test comprehensive persistence
            $benchmark->vendorid = sprintf('%04x:%04x', rand(0x1000, 0xFFFF), rand(0x1000, 0xFFFF));
            $benchmark->power = rand(50, 350);
            $benchmark->intensity = rand(10, 25) + (rand(0, 99) / 100);
            $benchmark->freq = rand(1000, 2500);
            $benchmark->memf = rand(4000, 8000);
            $benchmark->client = $this->generateRandomClient();
            $benchmark->os = $this->generateRandomOS();
            $benchmark->driver = $this->generateRandomDriver();
            
            if ($chip) {
                $benchmark->idchip = $chip->id;
            }
            
            // Store original values for comparison
            $originalData = [
                'algo' => $benchmark->algo,
                'type' => $benchmark->type,
                'khps' => $benchmark->khps,
                'device' => $benchmark->device,
                'chip' => $benchmark->chip,
                'vendorid' => $benchmark->vendorid,
                'power' => $benchmark->power,
                'intensity' => $benchmark->intensity,
                'freq' => $benchmark->freq,
                'memf' => $benchmark->memf,
                'client' => $benchmark->client,
                'os' => $benchmark->os,
                'driver' => $benchmark->driver,
                'idchip' => $benchmark->idchip,
            ];
            
            // Save benchmark
            if (!$benchmark->save()) {
                $failures[] = [
                    'iteration' => $i,
                    'reason' => 'Failed to save benchmark',
                    'errors' => $benchmark->errors,
                    'data' => $originalData
                ];
                
                // Clean up chip if created
                if ($chip) {
                    $chip->delete();
                }
                continue;
            }
            
            $savedId = $benchmark->id;
            
            // Retrieve benchmark from database
            $retrievedBenchmark = Benchmarks::findOne($savedId);
            
            if (!$retrievedBenchmark) {
                $failures[] = [
                    'iteration' => $i,
                    'benchmark_id' => $savedId,
                    'reason' => 'Failed to retrieve saved benchmark from database'
                ];
                
                // Clean up
                $benchmark->delete();
                if ($chip) {
                    $chip->delete();
                }
                continue;
            }
            
            // Verify all required fields are persisted correctly
            // Requirement 7.4: device model, chip type, algorithm, and measured hashrate
            
            // 1. Algorithm
            if ($retrievedBenchmark->algo !== $originalData['algo']) {
                $failures[] = [
                    'iteration' => $i,
                    'benchmark_id' => $savedId,
                    'field' => 'algo',
                    'expected' => $originalData['algo'],
                    'actual' => $retrievedBenchmark->algo,
                    'reason' => 'Algorithm not persisted correctly'
                ];
            }
            
            // 2. Device model
            if ($retrievedBenchmark->device !== $originalData['device']) {
                $failures[] = [
                    'iteration' => $i,
                    'benchmark_id' => $savedId,
                    'field' => 'device',
                    'expected' => $originalData['device'],
                    'actual' => $retrievedBenchmark->device,
                    'reason' => 'Device model not persisted correctly'
                ];
            }
            
            // 3. Chip type
            if ($retrievedBenchmark->chip !== $originalData['chip']) {
                $failures[] = [
                    'iteration' => $i,
                    'benchmark_id' => $savedId,
                    'field' => 'chip',
                    'expected' => $originalData['chip'],
                    'actual' => $retrievedBenchmark->chip,
                    'reason' => 'Chip type not persisted correctly'
                ];
            }
            
            // 4. Measured hashrate
            if (abs($retrievedBenchmark->khps - $originalData['khps']) > 0.01) {
                $failures[] = [
                    'iteration' => $i,
                    'benchmark_id' => $savedId,
                    'field' => 'khps',
                    'expected' => $originalData['khps'],
                    'actual' => $retrievedBenchmark->khps,
                    'reason' => 'Measured hashrate not persisted correctly'
                ];
            }
            
            // Verify device type is persisted
            if ($retrievedBenchmark->type !== $originalData['type']) {
                $failures[] = [
                    'iteration' => $i,
                    'benchmark_id' => $savedId,
                    'field' => 'type',
                    'expected' => $originalData['type'],
                    'actual' => $retrievedBenchmark->type,
                    'reason' => 'Device type not persisted correctly'
                ];
            }
            
            // Verify optional fields are persisted correctly
            if ($retrievedBenchmark->vendorid !== $originalData['vendorid']) {
                $failures[] = [
                    'iteration' => $i,
                    'benchmark_id' => $savedId,
                    'field' => 'vendorid',
                    'expected' => $originalData['vendorid'],
                    'actual' => $retrievedBenchmark->vendorid,
                    'reason' => 'Vendor ID not persisted correctly'
                ];
            }
            
            if ($retrievedBenchmark->power !== $originalData['power']) {
                $failures[] = [
                    'iteration' => $i,
                    'benchmark_id' => $savedId,
                    'field' => 'power',
                    'expected' => $originalData['power'],
                    'actual' => $retrievedBenchmark->power,
                    'reason' => 'Power not persisted correctly'
                ];
            }
            
            if (abs($retrievedBenchmark->intensity - $originalData['intensity']) > 0.01) {
                $failures[] = [
                    'iteration' => $i,
                    'benchmark_id' => $savedId,
                    'field' => 'intensity',
                    'expected' => $originalData['intensity'],
                    'actual' => $retrievedBenchmark->intensity,
                    'reason' => 'Intensity not persisted correctly'
                ];
            }
            
            if ($retrievedBenchmark->freq !== $originalData['freq']) {
                $failures[] = [
                    'iteration' => $i,
                    'benchmark_id' => $savedId,
                    'field' => 'freq',
                    'expected' => $originalData['freq'],
                    'actual' => $retrievedBenchmark->freq,
                    'reason' => 'Frequency not persisted correctly'
                ];
            }
            
            if ($retrievedBenchmark->memf !== $originalData['memf']) {
                $failures[] = [
                    'iteration' => $i,
                    'benchmark_id' => $savedId,
                    'field' => 'memf',
                    'expected' => $originalData['memf'],
                    'actual' => $retrievedBenchmark->memf,
                    'reason' => 'Memory frequency not persisted correctly'
                ];
            }
            
            if ($retrievedBenchmark->client !== $originalData['client']) {
                $failures[] = [
                    'iteration' => $i,
                    'benchmark_id' => $savedId,
                    'field' => 'client',
                    'expected' => $originalData['client'],
                    'actual' => $retrievedBenchmark->client,
                    'reason' => 'Client not persisted correctly'
                ];
            }
            
            if ($retrievedBenchmark->os !== $originalData['os']) {
                $failures[] = [
                    'iteration' => $i,
                    'benchmark_id' => $savedId,
                    'field' => 'os',
                    'expected' => $originalData['os'],
                    'actual' => $retrievedBenchmark->os,
                    'reason' => 'OS not persisted correctly'
                ];
            }
            
            if ($retrievedBenchmark->driver !== $originalData['driver']) {
                $failures[] = [
                    'iteration' => $i,
                    'benchmark_id' => $savedId,
                    'field' => 'driver',
                    'expected' => $originalData['driver'],
                    'actual' => $retrievedBenchmark->driver,
                    'reason' => 'Driver not persisted correctly'
                ];
            }
            
            if ($retrievedBenchmark->idchip !== $originalData['idchip']) {
                $failures[] = [
                    'iteration' => $i,
                    'benchmark_id' => $savedId,
                    'field' => 'idchip',
                    'expected' => $originalData['idchip'],
                    'actual' => $retrievedBenchmark->idchip,
                    'reason' => 'Chip ID not persisted correctly'
                ];
            }
            
            // Test that benchmark can be queried by required fields
            $queryByAlgo = Benchmarks::find()
                ->where(['algo' => $originalData['algo'], 'id' => $savedId])
                ->one();
            
            if (!$queryByAlgo) {
                $failures[] = [
                    'iteration' => $i,
                    'benchmark_id' => $savedId,
                    'reason' => 'Cannot query benchmark by algorithm'
                ];
            }
            
            $queryByDevice = Benchmarks::find()
                ->where(['device' => $originalData['device'], 'id' => $savedId])
                ->one();
            
            if (!$queryByDevice) {
                $failures[] = [
                    'iteration' => $i,
                    'benchmark_id' => $savedId,
                    'reason' => 'Cannot query benchmark by device'
                ];
            }
            
            if ($chip) {
                $queryByChip = Benchmarks::find()
                    ->where(['idchip' => $chip->id, 'id' => $savedId])
                    ->one();
                
                if (!$queryByChip) {
                    $failures[] = [
                        'iteration' => $i,
                        'benchmark_id' => $savedId,
                        'reason' => 'Cannot query benchmark by chip ID'
                    ];
                }
                
                // Test relation to chip
                if ($retrievedBenchmark->benchChip === null) {
                    $failures[] = [
                        'iteration' => $i,
                        'benchmark_id' => $savedId,
                        'reason' => 'Benchmark relation to chip not working'
                    ];
                } elseif ($retrievedBenchmark->benchChip->id !== $chip->id) {
                    $failures[] = [
                        'iteration' => $i,
                        'benchmark_id' => $savedId,
                        'expected_chip_id' => $chip->id,
                        'actual_chip_id' => $retrievedBenchmark->benchChip->id,
                        'reason' => 'Benchmark chip relation returns wrong chip'
                    ];
                }
            }
            
            // Test round-trip: update and verify persistence
            $newHashrate = $this->generateRandomHashrate();
            $retrievedBenchmark->khps = $newHashrate;
            
            if (!$retrievedBenchmark->save()) {
                $failures[] = [
                    'iteration' => $i,
                    'benchmark_id' => $savedId,
                    'reason' => 'Failed to update benchmark',
                    'errors' => $retrievedBenchmark->errors
                ];
            } else {
                // Retrieve again and verify update
                $updatedBenchmark = Benchmarks::findOne($savedId);
                
                if (abs($updatedBenchmark->khps - $newHashrate) > 0.01) {
                    $failures[] = [
                        'iteration' => $i,
                        'benchmark_id' => $savedId,
                        'expected_hashrate' => $newHashrate,
                        'actual_hashrate' => $updatedBenchmark->khps,
                        'reason' => 'Benchmark update not persisted correctly'
                    ];
                }
            }
            
            // Clean up
            $retrievedBenchmark->delete();
            if ($chip) {
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
     * Generate random device name
     * 
     * @return string
     */
    protected function generateRandomDeviceName()
    {
        $devices = [
            'GeForce GTX 1080 Ti',
            'GeForce RTX 3090',
            'GeForce RTX 4090',
            'Radeon RX 6900 XT',
            'Radeon RX 7900 XTX',
            'Radeon VII',
            'Intel Core i9-9900K',
            'AMD Ryzen 9 5950X',
            'AMD Ryzen 9 7950X',
            'Antminer S19 Pro',
            'Whatsminer M30S++',
            'Bitmain Antminer L7',
            'Innosilicon A11 Pro'
        ];
        
        return $devices[array_rand($devices)];
    }
    
    /**
     * Generate random chip name
     * 
     * @return string
     */
    protected function generateRandomChipName()
    {
        $vendors = ['NVIDIA', 'AMD', 'Intel', 'Bitmain', 'Innosilicon'];
        $models = ['GTX', 'RTX', 'RX', 'Vega', 'Radeon', 'S', 'T', 'A', 'L'];
        $numbers = [1050, 1060, 1070, 1080, 2060, 2070, 2080, 3060, 3070, 3080, 3090, 
                    4070, 4080, 4090, 5700, 6700, 6800, 6900, 7900];
        
        $vendor = $vendors[array_rand($vendors)];
        $model = $models[array_rand($models)];
        $number = $numbers[array_rand($numbers)];
        
        return "$vendor $model $number";
    }
    
    /**
     * Generate random device type
     * 
     * @return string
     */
    protected function generateRandomDeviceType()
    {
        $types = ['gpu', 'cpu', 'asic'];
        return $types[array_rand($types)];
    }
    
    /**
     * Generate random hashrate (kH/s)
     * 
     * @return float
     */
    protected function generateRandomHashrate()
    {
        // Generate hashrate between 100 kH/s and 10 GH/s
        return rand(100, 10000000) / 10.0;
    }
    
    /**
     * Generate random client name
     * 
     * @return string
     */
    protected function generateRandomClient()
    {
        $clients = [
            'ccminer', 'sgminer', 'cgminer', 'ethminer', 
            'xmrig', 'lolminer', 't-rex', 'nbminer',
            'phoenixminer', 'claymore', 'bminer', 'gminer'
        ];
        
        $client = $clients[array_rand($clients)];
        $version = rand(1, 5) . '.' . rand(0, 9) . '.' . rand(0, 9);
        
        return "$client $version";
    }
    
    /**
     * Generate random OS
     * 
     * @return string
     */
    protected function generateRandomOS()
    {
        $oses = ['linux', 'windows', 'macos', 'hiveos', 'nicehash'];
        return $oses[array_rand($oses)];
    }
    
    /**
     * Generate random driver version
     * 
     * @return string
     */
    protected function generateRandomDriver()
    {
        $drivers = [
            'NVIDIA ' . rand(450, 550) . '.' . rand(10, 99),
            'AMD ' . rand(21, 23) . '.' . rand(1, 12) . '.' . rand(1, 9),
            'Mesa ' . rand(20, 23) . '.' . rand(0, 3) . '.' . rand(0, 9),
        ];
        
        return $drivers[array_rand($drivers)];
    }
    
    /**
     * Create test chip
     * 
     * @param string $chipName
     * @return BenchChips|null
     */
    protected function createTestChip($chipName)
    {
        $chip = new BenchChips();
        $chip->devicetype = $this->generateRandomDeviceType();
        $chip->chip = $chipName;
        $chip->vendorid = sprintf('%04x:%04x', rand(0x1000, 0xFFFF), rand(0x1000, 0xFFFF));
        $chip->year = rand(2015, 2024);
        $chip->maxtdp = rand(50, 350);
        
        if ($chip->save()) {
            return $chip;
        }
        
        return null;
    }
}
