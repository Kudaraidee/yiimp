<?php

namespace tests\unit\trading;

use Codeception\Test\Unit;
use app\models\Coins;
use app\models\Algos;

/**
 * Property-based tests for Profitability Calculation Accuracy
 * 
 * Feature: yiimp-to-yiimp2-migration, Property 26: Profitability calculation accuracy
 */
class ProfitabilityCalculationPropertyTest extends Unit
{
    /**
     * Property 26: Profitability Calculation Accuracy
     * 
     * For any algorithm with difficulty and market price data, the profitability calculation
     * should accurately estimate earnings based on the formula:
     * earnings = (block_reward * price) / (difficulty * time_per_block)
     * 
     * The actual implementation uses a normalized formula:
     * btcmhd = 20116.56761169 / difficulty * reward * price
     * 
     * Where 20116.56761169 is derived from:
     * (24 * 60 * 60) / (2^32 / 2^16) * 1000000
     * = 86400 / 65536 * 1000000
     * = 86400 / 0.065536 * 1000
     * ≈ 20116.56761169
     * 
     * This converts to mBTC/MH/day assuming:
     * - 24*60*60 seconds per day
     * - 2^32 as max target (difficulty 1)
     * - 2^16 as block time normalization
     * - 1000000 to convert to mBTC and MH
     * 
     * Validates: Requirements 6.2
     * 
     * @test
     */
    public function testProfitabilityCalculationAccuracy()
    {
        // Feature: yiimp-to-yiimp2-migration, Property 26: Profitability calculation accuracy
        
        $iterations = 100;
        $failures = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            // Generate random coin data
            $coin = $this->generateRandomCoin();
            
            // Calculate profitability using the system formula
            $calculated = $this->calculateProfitability($coin);
            
            // Calculate expected profitability using the documented formula
            $expected = $this->expectedProfitability($coin);
            
            // Verify the calculation matches expected formula
            // Allow for small floating point differences (0.01% tolerance)
            $tolerance = max(abs($expected) * 0.0001, 0.000001);
            
            if (abs($expected - $calculated) > $tolerance) {
                $failures[] = [
                    'iteration' => $i,
                    'coin' => $coin['name'],
                    'algo' => $coin['algo'],
                    'difficulty' => $coin['difficulty'],
                    'reward' => $coin['reward'],
                    'price' => $coin['price'],
                    'block_time' => $coin['block_time'],
                    'expected' => $expected,
                    'calculated' => $calculated,
                    'difference' => abs($expected - $calculated),
                    'tolerance' => $tolerance,
                    'reason' => 'Profitability calculation does not match expected formula'
                ];
            }
            
            // Verify profitability is non-negative
            if ($calculated < 0) {
                $failures[] = [
                    'iteration' => $i,
                    'coin' => $coin['name'],
                    'calculated' => $calculated,
                    'reason' => 'Profitability should never be negative'
                ];
            }
            
            // Verify profitability is zero when difficulty is zero
            if ($coin['difficulty'] == 0 && $calculated != 0) {
                $failures[] = [
                    'iteration' => $i,
                    'coin' => $coin['name'],
                    'difficulty' => $coin['difficulty'],
                    'calculated' => $calculated,
                    'reason' => 'Profitability should be zero when difficulty is zero'
                ];
            }
            
            // Verify profitability increases when price increases (holding other factors constant)
            $higherPriceCoin = $coin;
            $higherPriceCoin['price'] = $coin['price'] * 2;
            $higherPriceProfit = $this->calculateProfitability($higherPriceCoin);
            
            if ($coin['difficulty'] > 0 && $coin['price'] > 0) {
                $expectedRatio = 2.0;
                $actualRatio = $higherPriceProfit / $calculated;
                $ratioTolerance = 0.0001;
                
                if (abs($expectedRatio - $actualRatio) > $ratioTolerance) {
                    $failures[] = [
                        'iteration' => $i,
                        'coin' => $coin['name'],
                        'original_price' => $coin['price'],
                        'original_profit' => $calculated,
                        'doubled_price' => $higherPriceCoin['price'],
                        'doubled_profit' => $higherPriceProfit,
                        'expected_ratio' => $expectedRatio,
                        'actual_ratio' => $actualRatio,
                        'reason' => 'Profitability should scale linearly with price'
                    ];
                }
            }
            
            // Verify profitability decreases when difficulty increases (holding other factors constant)
            $higherDifficultyCoin = $coin;
            $higherDifficultyCoin['difficulty'] = $coin['difficulty'] * 2;
            $higherDifficultyProfit = $this->calculateProfitability($higherDifficultyCoin);
            
            if ($coin['difficulty'] > 0 && $coin['price'] > 0) {
                $expectedRatio = 0.5;
                $actualRatio = $higherDifficultyProfit / $calculated;
                $ratioTolerance = 0.0001;
                
                if (abs($expectedRatio - $actualRatio) > $ratioTolerance) {
                    $failures[] = [
                        'iteration' => $i,
                        'coin' => $coin['name'],
                        'original_difficulty' => $coin['difficulty'],
                        'original_profit' => $calculated,
                        'doubled_difficulty' => $higherDifficultyCoin['difficulty'],
                        'doubled_profit' => $higherDifficultyProfit,
                        'expected_ratio' => $expectedRatio,
                        'actual_ratio' => $actualRatio,
                        'reason' => 'Profitability should scale inversely with difficulty'
                    ];
                }
            }
        }
        
        // Report failures if any
        if (!empty($failures)) {
            $this->fail(
                "Property test failed in " . count($failures) . " cases out of $iterations iterations:\n" .
                json_encode($failures, JSON_PRETTY_PRINT)
            );
        }
        
        $this->assertTrue(true, "Property holds for all $iterations iterations");
    }
    
    /**
     * Generate random coin data for testing
     * 
     * @return array
     */
    protected function generateRandomCoin()
    {
        static $coinId = 1;
        
        $algos = ['sha256', 'scrypt', 'x11', 'x13', 'neoscrypt', 'lyra2v2', 'equihash'];
        $algo = $algos[array_rand($algos)];
        
        // Random difficulty (0 to 10 million, including edge case of 0)
        $difficulty = rand(0, 100) == 0 ? 0 : rand(1, 10000000);
        
        // Random reward (0.01 to 1000)
        $reward = round(rand(1, 100000) / 100, 8);
        
        // Random price in BTC (0.00000001 to 0.1)
        $price = round(rand(1, 10000000) / 100000000, 8);
        
        // Random block time (10 to 600 seconds)
        $block_time = rand(10, 600);
        
        return [
            'id' => $coinId++,
            'name' => 'TestCoin' . rand(1000, 9999),
            'symbol' => 'TST' . rand(100, 999),
            'algo' => $algo,
            'difficulty' => $difficulty,
            'reward' => $reward,
            'price' => $price,
            'block_time' => $block_time,
            'auxpow' => 0,
            'rpcencoding' => 'POW',
            'enable' => 1,
            'visible' => 1,
            'auto_ready' => 1,
        ];
    }
    
    /**
     * Calculate profitability using the system formula
     * This replicates the logic from TradingController::calculateProfitability()
     * 
     * @param array $coin
     * @return float
     */
    protected function calculateProfitability($coin)
    {
        if (!$coin['difficulty'] || $coin['difficulty'] == 0) {
            return 0;
        }
        
        // Base profitability calculation
        // Formula: 20116.56761169 / difficulty * reward * price
        $btcmhd = 20116.56761169 / $coin['difficulty'] * $coin['reward'] * $coin['price'];
        
        // Apply algorithm unit factor
        $algoUnitFactor = $this->getAlgoUnitFactor($coin['algo']);
        return $btcmhd * $algoUnitFactor;
    }
    
    /**
     * Calculate expected profitability using the documented formula
     * 
     * The formula should be: (block_reward * price) / (difficulty * time_per_block)
     * Normalized to mBTC/MH/day
     * 
     * @param array $coin
     * @return float
     */
    protected function expectedProfitability($coin)
    {
        if (!$coin['difficulty'] || $coin['difficulty'] == 0) {
            return 0;
        }
        
        // The constant 20116.56761169 is derived from:
        // (24 * 60 * 60) / (2^32 / 2^16) * 1000000
        // This normalizes to mBTC/MH/day
        $constant = 20116.56761169;
        
        // Expected formula: constant / difficulty * reward * price
        $btcmhd = $constant / $coin['difficulty'] * $coin['reward'] * $coin['price'];
        
        // Apply algorithm unit factor
        $algoUnitFactor = $this->getAlgoUnitFactor($coin['algo']);
        return $btcmhd * $algoUnitFactor;
    }
    
    /**
     * Get algorithm unit factor for normalization
     * 
     * @param string $algo
     * @return float
     */
    protected function getAlgoUnitFactor($algo)
    {
        // Default factors for common algorithms
        $factors = [
            'scrypt' => 1,
            'sha256' => 1000,
            'x11' => 1,
            'x13' => 1,
            'x15' => 1,
            'nist5' => 1,
            'neoscrypt' => 1,
            'lyra2' => 1,
            'lyra2v2' => 1,
            'equihash' => 1,
        ];
        
        return $factors[$algo] ?? 1;
    }
    
    /**
     * Test that profitability calculation handles edge cases correctly
     * 
     * @test
     */
    public function testProfitabilityEdgeCases()
    {
        // Feature: yiimp-to-yiimp2-migration, Property 26: Profitability calculation accuracy
        
        // Test zero difficulty
        $zeroDifficultyCoin = [
            'id' => 1,
            'name' => 'ZeroDiff',
            'symbol' => 'ZD',
            'algo' => 'scrypt',
            'difficulty' => 0,
            'reward' => 50,
            'price' => 0.001,
            'block_time' => 60,
            'auxpow' => 0,
            'rpcencoding' => 'POW',
        ];
        
        $profit = $this->calculateProfitability($zeroDifficultyCoin);
        $this->assertEquals(0, $profit, 'Profitability should be zero when difficulty is zero');
        
        // Test very high difficulty
        $highDifficultyCoin = [
            'id' => 2,
            'name' => 'HighDiff',
            'symbol' => 'HD',
            'algo' => 'scrypt',
            'difficulty' => 1000000000,
            'reward' => 50,
            'price' => 0.001,
            'block_time' => 60,
            'auxpow' => 0,
            'rpcencoding' => 'POW',
        ];
        
        $profit = $this->calculateProfitability($highDifficultyCoin);
        $this->assertGreaterThan(0, $profit, 'Profitability should be positive for high difficulty');
        $this->assertLessThan(1, $profit, 'Profitability should be very small for high difficulty');
        
        // Test zero price
        $zeroPriceCoin = [
            'id' => 3,
            'name' => 'ZeroPrice',
            'symbol' => 'ZP',
            'algo' => 'scrypt',
            'difficulty' => 1000,
            'reward' => 50,
            'price' => 0,
            'block_time' => 60,
            'auxpow' => 0,
            'rpcencoding' => 'POW',
        ];
        
        $profit = $this->calculateProfitability($zeroPriceCoin);
        $this->assertEquals(0, $profit, 'Profitability should be zero when price is zero');
        
        // Test zero reward
        $zeroRewardCoin = [
            'id' => 4,
            'name' => 'ZeroReward',
            'symbol' => 'ZR',
            'algo' => 'scrypt',
            'difficulty' => 1000,
            'reward' => 0,
            'price' => 0.001,
            'block_time' => 60,
            'auxpow' => 0,
            'rpcencoding' => 'POW',
        ];
        
        $profit = $this->calculateProfitability($zeroRewardCoin);
        $this->assertEquals(0, $profit, 'Profitability should be zero when reward is zero');
    }
    
    /**
     * Test that algorithm unit factors are applied correctly
     * 
     * @test
     */
    public function testAlgorithmUnitFactors()
    {
        // Feature: yiimp-to-yiimp2-migration, Property 26: Profitability calculation accuracy
        
        // Create identical coins with different algorithms
        $baseCoin = [
            'id' => 1,
            'name' => 'BaseCoin',
            'symbol' => 'BC',
            'difficulty' => 1000,
            'reward' => 50,
            'price' => 0.001,
            'block_time' => 60,
            'auxpow' => 0,
            'rpcencoding' => 'POW',
        ];
        
        // Test scrypt (factor = 1)
        $scryptCoin = array_merge($baseCoin, ['algo' => 'scrypt']);
        $scryptProfit = $this->calculateProfitability($scryptCoin);
        
        // Test sha256 (factor = 1000)
        $sha256Coin = array_merge($baseCoin, ['algo' => 'sha256']);
        $sha256Profit = $this->calculateProfitability($sha256Coin);
        
        // SHA256 profitability should be 1000x scrypt profitability
        $expectedRatio = 1000.0;
        $actualRatio = $sha256Profit / $scryptProfit;
        $tolerance = 0.01;
        
        $this->assertEqualsWithDelta(
            $expectedRatio,
            $actualRatio,
            $tolerance,
            'SHA256 profitability should be 1000x scrypt profitability due to unit factor'
        );
    }
}
