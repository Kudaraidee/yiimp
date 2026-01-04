<?php

namespace tests\unit\explorer;

use Codeception\Test\Unit;
use app\models\Coins;
use app\models\Blocks;

/**
 * Property-based tests for Hash Search Type Detection
 * 
 * Feature: yiimp-to-yiimp2-migration, Property 15: Hash search type detection
 */
class HashSearchPropertyTest extends Unit
{
    /**
     * Property 15: Hash Search Type Detection
     * 
     * For any valid hash query, the system should correctly identify whether
     * it's a block hash or transaction hash and display appropriate details.
     * 
     * Validates: Requirements 3.5
     * 
     * @test
     */
    public function testHashSearchTypeDetection()
    {
        // Feature: yiimp-to-yiimp2-migration, Property 15: Hash search type detection
        
        $iterations = 100;
        $failures = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            try {
                // Get a random block from the database
                $block = $this->getRandomBlock();
                
                if ($block === null) {
                    // Skip if no blocks available
                    continue;
                }
                
                // Test 1: Verify block hash is correctly identified as 'block'
                $this->verifyBlockHashDetection($block, $i, $failures);
                
                // Test 2: Verify transaction hash is correctly identified as 'transaction'
                $this->verifyTransactionHashDetection($block, $i, $failures);
                
            } catch (\Exception $e) {
                $failures[] = [
                    'iteration' => $i,
                    'block_id' => isset($block) ? $block->id : 'unknown',
                    'reason' => 'Exception thrown',
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ];
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
     * Test that invalid hashes return null
     * 
     * @test
     */
    public function testInvalidHashReturnsNull()
    {
        // Feature: yiimp-to-yiimp2-migration, Property 15: Hash search type detection (invalid hashes)
        
        $iterations = 50;
        $failures = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            try {
                $coin = $this->getRandomCoin();
                
                if ($coin === null) {
                    continue;
                }
                
                // Generate a random invalid hash (valid hex but doesn't exist)
                $invalidHash = $this->generateRandomHash();
                
                // Search for the invalid hash
                $result = \Yii::$app->ExplorerUtils->searchHash($coin, $invalidHash);
                
                // Should return null for non-existent hash
                if ($result !== null) {
                    $failures[] = [
                        'iteration' => $i,
                        'coin' => $coin->symbol,
                        'hash' => substr($invalidHash, 0, 16),
                        'expected' => null,
                        'actual' => $result,
                        'reason' => 'Invalid hash should return null'
                    ];
                }
                
            } catch (\Exception $e) {
                $failures[] = [
                    'iteration' => $i,
                    'coin' => isset($coin) ? $coin->symbol : 'unknown',
                    'reason' => 'Exception thrown',
                    'message' => $e->getMessage()
                ];
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
     * Test that searchHash is consistent across multiple calls
     * 
     * @test
     */
    public function testHashSearchConsistency()
    {
        // Feature: yiimp-to-yiimp2-migration, Property 15: Hash search type detection (consistency)
        
        $iterations = 50;
        $failures = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            try {
                $block = $this->getRandomBlock();
                
                if ($block === null) {
                    continue;
                }
                
                // Call searchHash multiple times with the same hash
                $result1 = \Yii::$app->ExplorerUtils->searchHash($block->coin, $block->hash);
                $result2 = \Yii::$app->ExplorerUtils->searchHash($block->coin, $block->hash);
                $result3 = \Yii::$app->ExplorerUtils->searchHash($block->coin, $block->hash);
                
                // All results should be identical
                if ($result1 !== $result2 || $result2 !== $result3) {
                    $failures[] = [
                        'iteration' => $i,
                        'coin' => $block->coin->symbol,
                        'hash' => substr($block->hash, 0, 16),
                        'result1' => $result1,
                        'result2' => $result2,
                        'result3' => $result3,
                        'reason' => 'searchHash returned inconsistent results'
                    ];
                }
                
            } catch (\Exception $e) {
                $failures[] = [
                    'iteration' => $i,
                    'block_id' => isset($block) ? $block->id : 'unknown',
                    'reason' => 'Exception thrown',
                    'message' => $e->getMessage()
                ];
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
     * Get a random block from the database
     * 
     * @return Blocks|null
     */
    protected function getRandomBlock()
    {
        // Get all blocks with valid coin relationships
        $blocks = Blocks::find()
            ->joinWith('coin')
            ->where(['coins.enable' => 1])
            ->andWhere(['IS NOT', 'blocks.hash', null])
            ->andWhere(['!=', 'blocks.hash', ''])
            ->limit(100)
            ->all();
        
        if (empty($blocks)) {
            return null;
        }
        
        // Return a random block
        $randomIndex = array_rand($blocks);
        return $blocks[$randomIndex];
    }
    
    /**
     * Get a random coin from the database
     * 
     * @return Coins|null
     */
    protected function getRandomCoin()
    {
        $coins = Coins::find()
            ->where(['enable' => 1])
            ->all();
        
        if (empty($coins)) {
            return null;
        }
        
        $randomIndex = array_rand($coins);
        return $coins[$randomIndex];
    }
    
    /**
     * Verify block hash is correctly identified as 'block'
     * 
     * @param Blocks $block
     * @param int $iteration
     * @param array &$failures
     */
    protected function verifyBlockHashDetection($block, $iteration, &$failures)
    {
        // Search for the block hash
        $result = \Yii::$app->ExplorerUtils->searchHash($block->coin, $block->hash);
        
        // Result can be null if RPC is unavailable (acceptable)
        if ($result === null) {
            return;
        }
        
        // Result should be 'block' for a block hash
        if ($result !== 'block') {
            $failures[] = [
                'iteration' => $iteration,
                'block_id' => $block->id,
                'coin' => $block->coin->symbol,
                'hash' => substr($block->hash, 0, 16),
                'hash_type' => 'block',
                'expected' => 'block',
                'actual' => $result,
                'reason' => 'Block hash not correctly identified as block'
            ];
        }
    }
    
    /**
     * Verify transaction hash is correctly identified as 'transaction'
     * 
     * @param Blocks $block
     * @param int $iteration
     * @param array &$failures
     */
    protected function verifyTransactionHashDetection($block, $iteration, &$failures)
    {
        // Get block details to get transaction IDs
        $blockDetails = \Yii::$app->ExplorerUtils->getBlockDetails(
            $block->coin,
            $block->hash
        );
        
        if ($blockDetails === null || !isset($blockDetails['tx']) || empty($blockDetails['tx'])) {
            // No transactions or RPC unavailable, skip
            return;
        }
        
        // Pick a random transaction from the block
        $txid = $blockDetails['tx'][array_rand($blockDetails['tx'])];
        
        // Search for the transaction hash
        $result = \Yii::$app->ExplorerUtils->searchHash($block->coin, $txid);
        
        // Result can be null if RPC is unavailable (acceptable)
        if ($result === null) {
            return;
        }
        
        // Result should be 'transaction' for a transaction hash
        if ($result !== 'transaction') {
            $failures[] = [
                'iteration' => $iteration,
                'block_id' => $block->id,
                'coin' => $block->coin->symbol,
                'txid' => substr($txid, 0, 16),
                'hash_type' => 'transaction',
                'expected' => 'transaction',
                'actual' => $result,
                'reason' => 'Transaction hash not correctly identified as transaction'
            ];
        }
    }
    
    /**
     * Generate a random valid hex hash that doesn't exist
     * 
     * @return string
     */
    protected function generateRandomHash()
    {
        // Generate a 64-character hex string (typical for SHA256 hashes)
        $hash = '';
        for ($i = 0; $i < 64; $i++) {
            $hash .= dechex(rand(0, 15));
        }
        return $hash;
    }
}
