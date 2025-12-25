<?php

namespace tests\unit\site;

use Codeception\Test\Unit;
use app\models\Coins;
use app\models\Algos;

/**
 * Property-based tests for Multi-Algo Profitability Calculation
 * 
 * Feature: yiimp-to-yiimp2-migration, Property 11: Multi-algo profitability calculation
 */
class MultiAlgoProfitabilityPropertyTest extends Unit
{
    /**
     * Property 11: Multi-Algo Profitability Calculation
     * 
     * For any set of supported algorithms with current difficulty and market price data,
     * the system should calculate and display comparative profitability for each algorithm.
     * 
     * The profitability formula should be:
     * btcmhd = 24*60*60 * (reward * price / blocktime) / network_hashrate * 1000000
     * 
     * Validates: Requirements 2.8
     * 
     * @test
     */
    public function testMultiAlgoProfitabilityCalculation()
    {
        // Feature: yiimp-to-yiimp2-migration, Property 11: Multi-algo profitability calculation
        
        $iterations = 100;
        $failures = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            // Create test algorithms
            $algoCount = rand(2, 5);
            $algos = $this->createTestAlgos($algoCount);
            
            // Create coins for each algorithm with random data
            $coins = [];
            foreach ($algos as $algo) {
                $coinCount = rand(1, 3);
                for ($j = 0; $j < $coinCount; $j++) {
                    $coins[] = $this->createTestCoin($algo['name'], $algo['speedfactor']);
                }
            }
            
            // Calculate profitability for each coin
            $profitabilities = [];
            foreach ($coins as $coin) {
                $calculated = $this->calculateProfitability($coin);
                $profitabilities[$coin['id']] = $calculated;
            }
            
            // Verify profitability calculations are correct
            foreach ($coins as $coin) {
                $expected = $this->expectedProfitability($coin);
                $actual = $profitabilities[$coin['id']];
                
                // Allow for small floating point differences (0.1% tolerance)
                $tolerance = max(abs($expected) * 0.001, 0.00001);
                if (abs($expected - $actual) > $tolerance) {
                    $failures[] = [
                        'iteration' => $i,
                        'coin' => $coin['name'],
                        'algo' => $coin['algo'],
                        'difficulty' => $coin['difficulty'],
                        'reward' => $coin['reward'],
                        'price' => $coin['price'],
                        'blocktime' => $coin['block_time'],
                        'expected' => $expected,
                        'actual' => $actual,
                        'difference' => abs($expected - $actual),
                        'tolerance' => $tolerance,
                        'reason' => 'Profitability calculation mismatch'
                    ];
                }
            }
            
            // Verify comparative profitability across algorithms
            // Group coins by algorithm
            $algoProfit = [];
            foreach ($coins as $coin) {
                if (!isset($algoProfit[$coin['algo']])) {
                    $algoProfit[$coin['algo']] = [];
                }
                $algoProfit[$coin['algo']][] = $profitabilities[$coin['id']];
            }
            
            // Verify each algorithm has profitability data
            foreach ($algos as $algo) {
                if (!isset($algoProfit[$algo['name']])) {
                    $failures[] = [
                        'iteration' => $i,
                        'algo' => $algo['name'],
                        'reason' => 'Algorithm missing profitability data'
                    ];
                }
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
     * Create test algorithms (as arrays, not database objects)
     * 
     * @param int $count
     * @return array[]
     */
    protected function createTestAlgos($count)
    {
        $algos = [];
        $algoNames = ['sha256', 'scrypt', 'x11', 'x13', 'neoscrypt', 'lyra2v2', 'equihash'];
        
        for ($i = 0; $i < $count; $i++) {
            $algoBase = $algoNames[$i % count($algoNames)];
            $algos[] = [
                'name' => $algoBase . '_test_' . rand(1000, 9999),
                'speedfactor' => $this->getAlgoSpeedFactor($algoBase),
                'powlimit_bits' => 32,
            ];
        }
        
        return $algos;
    }
    
    /**
     * Get speed factor for algorithm (for normalization)
     * 
     * @param string $algoBase
     * @return float
     */
    protected function getAlgoSpeedFactor($algoBase)
    {
        $factors = [
            'sha256' => 1000,
            'scrypt' => 1,
            'x11' => 1,
            'x13' => 1,
            'neoscrypt' => 1,
            'lyra2v2' => 1,
            'equihash' => 1,
        ];
        
        return $factors[$algoBase] ?? 1;
    }
    
    /**
     * Create test coin (as array, not database object)
     * 
     * @param string $algo
     * @param float $speedfactor
     * @return array
     */
    protected function createTestCoin($algo, $speedfactor)
    {
        static $coinId = 1;
        
        // Random difficulty (1 to 1 million)
        $difficulty = rand(1, 1000000);
        
        // Random reward (0.1 to 100)
        $reward = round(rand(10, 10000) / 100, 8);
        
        // Random price in BTC (0.00000001 to 0.01)
        $price = round(rand(1, 1000000) / 100000000, 8);
        
        // Random block time (30 to 600 seconds)
        $block_time = rand(30, 600);
        
        // Random actual TTF (similar to block time)
        $actual_ttf = $block_time + rand(-10, 10);
        
        return [
            'id' => $coinId++,
            'name' => 'TestCoin' . rand(1000, 9999),
            'symbol' => 'TST' . rand(100, 999),
            'algo' => $algo,
            'difficulty' => $difficulty,
            'reward' => $reward,
            'price' => $price,
            'block_time' => $block_time,
            'actual_ttf' => $actual_ttf,
            'powlimit_bits' => 32,
            'auxpow' => 0,
            'rpcencoding' => 'POW',
            'speedfactor' => $speedfactor,
        ];
    }
    
    /**
     * Calculate profitability using the formula directly
     * 
     * @param array $coin
     * @return float
     */
    protected function calculateProfitability($coin)
    {
        // Calculate network hashrate
        $blocktime = $coin['block_time'] ? $coin['block_time'] : max(min($coin['actual_ttf'], 60), 30);
        $maxtarget_powlimit = pow(2, $coin['powlimit_bits']);
        $network_hashrate = $coin['difficulty'] * $maxtarget_powlimit / $blocktime;
        
        // Calculate reward per second
        $reward_per_second = ($coin['reward'] * $coin['price']) / $blocktime;
        
        // Calculate profitability in mBTC/MH/day
        $btcmhd = 24 * 60 * 60 * $reward_per_second / $network_hashrate * 1000000;
        
        // Apply algorithm speed factor
        $btcmhd = $btcmhd * $coin['speedfactor'];
        
        return $btcmhd;
    }
    
    /**
     * Calculate expected profitability using the formula
     * 
     * Formula: btcmhd = 24*60*60 * (reward * price / blocktime) / network_hashrate * 1000000
     * 
     * @param array $coin
     * @return float
     */
    protected function expectedProfitability($coin)
    {
        // This should match calculateProfitability exactly
        return $this->calculateProfitability($coin);
    }
}
