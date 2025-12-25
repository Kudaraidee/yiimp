<?php

namespace tests\unit\explorer;

use Codeception\Test\Unit;
use app\controllers\ExplorerController;
use app\models\Coins;
use app\models\Blocks;

/**
 * Property-based tests for Coin Explorer Data Display
 * 
 * Feature: yiimp-to-yiimp2-migration, Property 12: Coin explorer data display
 */
class CoinExplorerDataPropertyTest extends Unit
{
    protected $controller;
    
    protected function _before()
    {
        parent::_before();
        $this->controller = new ExplorerController('explorer', \Yii::$app);
    }
    
    /**
     * Property 12: Coin Explorer Data Display
     * 
     * For any coin in the explorer, the system should display recent blocks,
     * transactions, and blockchain statistics.
     * 
     * Validates: Requirements 3.2
     * 
     * @test
     */
    public function testCoinExplorerDataDisplay()
    {
        // Feature: yiimp-to-yiimp2-migration, Property 12: Coin explorer data display
        
        $iterations = 100;
        $failures = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            try {
                // Get a random coin from the database
                $coin = $this->getRandomCoin();
                
                if ($coin === null) {
                    // Skip if no coins available
                    continue;
                }
                
                // Verify the coin explorer displays required data
                $this->verifyCoinExplorerData($coin, $i, $failures);
                
            } catch (\Exception $e) {
                $failures[] = [
                    'iteration' => $i,
                    'coin' => isset($coin) ? $coin->symbol : 'unknown',
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
     * Get a random coin from the database
     * 
     * @return Coins|null
     */
    protected function getRandomCoin()
    {
        // Get all visible coins with explorer enabled
        $coins = Coins::find()
            ->where(['visible' => 1, 'enable' => 1])
            ->andWhere(['or', ['no_explorer' => 0], ['no_explorer' => null]])
            ->all();
        
        if (empty($coins)) {
            return null;
        }
        
        // Return a random coin
        $randomIndex = array_rand($coins);
        return $coins[$randomIndex];
    }
    
    /**
     * Verify coin explorer displays required data
     * 
     * @param Coins $coin
     * @param int $iteration
     * @param array &$failures
     */
    protected function verifyCoinExplorerData($coin, $iteration, &$failures)
    {
        // Get recent blocks
        $recentBlocks = \Yii::$app->ExplorerUtils->getRecentBlocks($coin, 10);
        
        // Verify recent blocks is an array
        if (!is_array($recentBlocks)) {
            $failures[] = [
                'iteration' => $iteration,
                'coin' => $coin->symbol,
                'field' => 'recent_blocks',
                'reason' => 'Recent blocks is not an array',
                'type' => gettype($recentBlocks)
            ];
            return;
        }
        
        // Verify each block has required fields
        foreach ($recentBlocks as $block) {
            $this->verifyBlockData($block, $coin, $iteration, $failures);
        }
        
        // Get recent transactions
        $recentTxs = \Yii::$app->ExplorerUtils->getRecentTransactions($coin, 10);
        
        // Verify recent transactions is an array
        if (!is_array($recentTxs)) {
            $failures[] = [
                'iteration' => $iteration,
                'coin' => $coin->symbol,
                'field' => 'recent_transactions',
                'reason' => 'Recent transactions is not an array',
                'type' => gettype($recentTxs)
            ];
            return;
        }
        
        // Verify each transaction has required fields
        foreach ($recentTxs as $tx) {
            $this->verifyTransactionData($tx, $coin, $iteration, $failures);
        }
        
        // Get blockchain statistics
        $stats = \Yii::$app->ExplorerUtils->getBlockchainStats($coin, 7);
        
        // Verify blockchain statistics
        $this->verifyBlockchainStats($stats, $coin, $iteration, $failures);
    }
    
    /**
     * Verify block data has required fields
     * 
     * @param mixed $block
     * @param Coins $coin
     * @param int $iteration
     * @param array &$failures
     */
    protected function verifyBlockData($block, $coin, $iteration, &$failures)
    {
        // Block should be a Blocks model instance
        if (!($block instanceof Blocks)) {
            $failures[] = [
                'iteration' => $iteration,
                'coin' => $coin->symbol,
                'field' => 'block',
                'reason' => 'Block is not a Blocks model instance',
                'type' => gettype($block)
            ];
            return;
        }
        
        // Required fields for block display
        $requiredFields = [
            'height' => 'integer',
            'hash' => 'string',
            'time' => 'integer',
            'confirmations' => 'integer'
        ];
        
        foreach ($requiredFields as $field => $expectedType) {
            if (!isset($block->$field)) {
                $failures[] = [
                    'iteration' => $iteration,
                    'coin' => $coin->symbol,
                    'block_height' => isset($block->height) ? $block->height : 'unknown',
                    'field' => $field,
                    'reason' => 'Required field missing in block'
                ];
                continue;
            }
            
            $actualType = gettype($block->$field);
            
            // Validate type
            if ($expectedType === 'integer' && !is_int($block->$field)) {
                $failures[] = [
                    'iteration' => $iteration,
                    'coin' => $coin->symbol,
                    'block_height' => $block->height,
                    'field' => $field,
                    'expected_type' => $expectedType,
                    'actual_type' => $actualType,
                    'value' => $block->$field,
                    'reason' => 'Block field type mismatch'
                ];
            } elseif ($expectedType === 'string' && !is_string($block->$field)) {
                $failures[] = [
                    'iteration' => $iteration,
                    'coin' => $coin->symbol,
                    'block_height' => $block->height,
                    'field' => $field,
                    'expected_type' => $expectedType,
                    'actual_type' => $actualType,
                    'value' => $block->$field,
                    'reason' => 'Block field type mismatch'
                ];
            }
        }
    }
    
    /**
     * Verify transaction data has required fields
     * 
     * @param mixed $tx
     * @param Coins $coin
     * @param int $iteration
     * @param array &$failures
     */
    protected function verifyTransactionData($tx, $coin, $iteration, &$failures)
    {
        // Transaction should be an array
        if (!is_array($tx)) {
            $failures[] = [
                'iteration' => $iteration,
                'coin' => $coin->symbol,
                'field' => 'transaction',
                'reason' => 'Transaction is not an array',
                'type' => gettype($tx)
            ];
            return;
        }
        
        // Required fields for transaction display
        $requiredFields = [
            'txid' => 'string',
            'height' => 'integer',
            'time' => 'integer'
        ];
        
        foreach ($requiredFields as $field => $expectedType) {
            if (!array_key_exists($field, $tx)) {
                $failures[] = [
                    'iteration' => $iteration,
                    'coin' => $coin->symbol,
                    'txid' => isset($tx['txid']) ? substr($tx['txid'], 0, 16) : 'unknown',
                    'field' => $field,
                    'reason' => 'Required field missing in transaction'
                ];
                continue;
            }
            
            $actualType = gettype($tx[$field]);
            
            // Validate type
            if ($expectedType === 'integer' && !is_int($tx[$field])) {
                $failures[] = [
                    'iteration' => $iteration,
                    'coin' => $coin->symbol,
                    'txid' => substr($tx['txid'], 0, 16),
                    'field' => $field,
                    'expected_type' => $expectedType,
                    'actual_type' => $actualType,
                    'value' => $tx[$field],
                    'reason' => 'Transaction field type mismatch'
                ];
            } elseif ($expectedType === 'string' && !is_string($tx[$field])) {
                $failures[] = [
                    'iteration' => $iteration,
                    'coin' => $coin->symbol,
                    'txid' => substr($tx['txid'], 0, 16),
                    'field' => $field,
                    'expected_type' => $expectedType,
                    'actual_type' => $actualType,
                    'value' => $tx[$field],
                    'reason' => 'Transaction field type mismatch'
                ];
            }
        }
    }
    
    /**
     * Verify blockchain statistics has required structure
     * 
     * @param mixed $stats
     * @param Coins $coin
     * @param int $iteration
     * @param array &$failures
     */
    protected function verifyBlockchainStats($stats, $coin, $iteration, &$failures)
    {
        // Stats can be null if RPC is unavailable (acceptable)
        if ($stats === null) {
            return;
        }
        
        // Stats should be an array
        if (!is_array($stats)) {
            $failures[] = [
                'iteration' => $iteration,
                'coin' => $coin->symbol,
                'field' => 'blockchain_stats',
                'reason' => 'Blockchain stats is not an array',
                'type' => gettype($stats)
            ];
            return;
        }
        
        // Should have 'current' and 'history' keys
        if (!array_key_exists('current', $stats)) {
            $failures[] = [
                'iteration' => $iteration,
                'coin' => $coin->symbol,
                'field' => 'blockchain_stats.current',
                'reason' => 'Missing current blockchain info'
            ];
        }
        
        if (!array_key_exists('history', $stats)) {
            $failures[] = [
                'iteration' => $iteration,
                'coin' => $coin->symbol,
                'field' => 'blockchain_stats.history',
                'reason' => 'Missing blockchain history'
            ];
        }
        
        // Verify history is an array
        if (isset($stats['history']) && !is_array($stats['history'])) {
            $failures[] = [
                'iteration' => $iteration,
                'coin' => $coin->symbol,
                'field' => 'blockchain_stats.history',
                'reason' => 'History is not an array',
                'type' => gettype($stats['history'])
            ];
        }
    }
    
    /**
     * Test that coin explorer displays data for coins with blocks
     * 
     * @test
     */
    public function testCoinExplorerDisplaysDataForCoinsWithBlocks()
    {
        // Feature: yiimp-to-yiimp2-migration, Property 12: Coin explorer data display (with blocks)
        
        // Get coins that have blocks
        $coinsWithBlocks = Coins::find()
            ->joinWith('blocks')
            ->where(['coins.visible' => 1, 'coins.enable' => 1])
            ->andWhere(['or', ['coins.no_explorer' => 0], ['coins.no_explorer' => null]])
            ->groupBy('coins.id')
            ->having('COUNT(blocks.id) > 0')
            ->all();
        
        if (empty($coinsWithBlocks)) {
            $this->markTestSkipped('No coins with blocks found in database');
            return;
        }
        
        foreach ($coinsWithBlocks as $coin) {
            // Get recent blocks
            $recentBlocks = \Yii::$app->ExplorerUtils->getRecentBlocks($coin, 10);
            
            // Should have at least one block
            $this->assertNotEmpty(
                $recentBlocks,
                "Coin {$coin->symbol} has blocks but getRecentBlocks returned empty"
            );
            
            // Each block should have required fields
            foreach ($recentBlocks as $block) {
                $this->assertInstanceOf(
                    Blocks::class,
                    $block,
                    "Block should be instance of Blocks model"
                );
                
                $this->assertNotNull($block->height, "Block height should not be null");
                $this->assertNotNull($block->hash, "Block hash should not be null");
                $this->assertNotNull($block->time, "Block time should not be null");
                $this->assertNotNull($block->confirmations, "Block confirmations should not be null");
            }
        }
    }
    
    /**
     * Test that blockchain statistics structure is consistent
     * 
     * @test
     */
    public function testBlockchainStatsStructureIsConsistent()
    {
        // Feature: yiimp-to-yiimp2-migration, Property 12: Coin explorer data display (stats structure)
        
        $iterations = 50;
        
        for ($i = 0; $i < $iterations; $i++) {
            $coin = $this->getRandomCoin();
            
            if ($coin === null) {
                continue;
            }
            
            $stats = \Yii::$app->ExplorerUtils->getBlockchainStats($coin, 7);
            
            // Stats can be null if RPC unavailable
            if ($stats === null) {
                continue;
            }
            
            // Should be an array
            $this->assertIsArray($stats, "Blockchain stats should be an array for {$coin->symbol}");
            
            // Should have expected keys
            $this->assertArrayHasKey('current', $stats, "Stats should have 'current' key for {$coin->symbol}");
            $this->assertArrayHasKey('history', $stats, "Stats should have 'history' key for {$coin->symbol}");
            
            // History should be an array
            $this->assertIsArray($stats['history'], "Stats history should be an array for {$coin->symbol}");
        }
    }
}
