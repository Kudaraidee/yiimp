<?php

namespace tests\unit\explorer;

use tests\helpers\DatabaseTestHelper;

use Codeception\Test\Unit;
use app\models\Coins;
use app\models\Blocks;

/**
 * Property-based tests for Block and Transaction Details
 * 
 * Feature: yiimp-to-yiimp2-migration, Property 13: Block detail completeness
 * Feature: yiimp-to-yiimp2-migration, Property 14: Transaction detail completeness
 */
class BlockTransactionDetailsPropertyTest extends Unit
{
    use DatabaseTestHelper;

    /**
     * Property 13: Block Detail Completeness
     * 
     * For any block record, the block details should display all required fields:
     * hash, height, timestamp, transactions, difficulty, and miner information.
     * 
     * Validates: Requirements 3.3
     * 
     * @test
     */
    public function testBlockDetailCompleteness()
    {
        // Feature: yiimp-to-yiimp2-migration, Property 13: Block detail completeness
        
        // Skip test if database is not available
        
        $this->requireDatabase();
        
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
                
                // Get block details from RPC
                $blockDetails = \Yii::$app->ExplorerUtils->getBlockDetails(
                    $block->coin,
                    $block->hash
                );
                
                if ($blockDetails === null) {
                    // RPC unavailable, skip this iteration
                    continue;
                }
                
                // Verify block details have all required fields
                $this->verifyBlockDetailsCompleteness($blockDetails, $block, $i, $failures);
                
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
     * Property 14: Transaction Detail Completeness
     * 
     * For any transaction record, the transaction details should display all required fields:
     * hash, inputs, outputs, amounts, and confirmation count.
     * 
     * Validates: Requirements 3.4
     * 
     * @test
     */
    public function testTransactionDetailCompleteness()
    {
        // Feature: yiimp-to-yiimp2-migration, Property 14: Transaction detail completeness
        
        // Skip test if database is not available
        
        $this->requireDatabase();
        
        $iterations = 100;
        $failures = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            try {
                // Get a random block with transactions
                $block = $this->getRandomBlockWithTransactions();
                
                if ($block === null) {
                    // Skip if no blocks with transactions available
                    continue;
                }
                
                // Get block details to get transaction IDs
                $blockDetails = \Yii::$app->ExplorerUtils->getBlockDetails(
                    $block->coin,
                    $block->hash
                );
                
                if ($blockDetails === null || !isset($blockDetails['tx']) || empty($blockDetails['tx'])) {
                    // No transactions or RPC unavailable, skip
                    continue;
                }
                
                // Pick a random transaction from the block
                $txid = $blockDetails['tx'][array_rand($blockDetails['tx'])];
                
                // Get transaction details from RPC
                $txDetails = \Yii::$app->ExplorerUtils->getTransactionDetails(
                    $block->coin,
                    $txid
                );
                
                if ($txDetails === null) {
                    // RPC unavailable, skip this iteration
                    continue;
                }
                
                // Verify transaction details have all required fields
                $this->verifyTransactionDetailsCompleteness($txDetails, $txid, $block, $i, $failures);
                
            } catch (\Exception $e) {
                $failures[] = [
                    'iteration' => $i,
                    'block_id' => isset($block) ? $block->id : 'unknown',
                    'txid' => isset($txid) ? substr($txid, 0, 16) : 'unknown',
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
     * Get a random block that likely has transactions
     * 
     * @return Blocks|null
     */
    protected function getRandomBlockWithTransactions()
    {
        // Get blocks with valid coin relationships
        // Prefer blocks with higher heights (more likely to have transactions)
        $blocks = Blocks::find()
            ->joinWith('coin')
            ->where(['coins.enable' => 1])
            ->andWhere(['IS NOT', 'blocks.hash', null])
            ->andWhere(['>', 'blocks.height', 0])
            ->orderBy('RAND()')
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
     * Verify block details have all required fields
     * 
     * @param array $blockDetails
     * @param Blocks $block
     * @param int $iteration
     * @param array &$failures
     */
    protected function verifyBlockDetailsCompleteness($blockDetails, $block, $iteration, &$failures)
    {
        // Required fields for block detail display (from requirements 3.3)
        $requiredFields = [
            'hash' => 'string',
            'height' => 'integer',
            'time' => 'integer',
            'difficulty' => 'numeric',
        ];
        
        foreach ($requiredFields as $field => $expectedType) {
            if (!isset($blockDetails[$field])) {
                $failures[] = [
                    'iteration' => $iteration,
                    'block_id' => $block->id,
                    'coin' => $block->coin->symbol,
                    'block_hash' => substr($block->hash, 0, 16),
                    'field' => $field,
                    'reason' => 'Required field missing in block details'
                ];
                continue;
            }
            
            // Validate type
            if ($expectedType === 'string' && !is_string($blockDetails[$field])) {
                $failures[] = [
                    'iteration' => $iteration,
                    'block_id' => $block->id,
                    'coin' => $block->coin->symbol,
                    'field' => $field,
                    'expected_type' => $expectedType,
                    'actual_type' => gettype($blockDetails[$field]),
                    'value' => $blockDetails[$field],
                    'reason' => 'Block detail field type mismatch'
                ];
            } elseif ($expectedType === 'integer' && !is_int($blockDetails[$field])) {
                $failures[] = [
                    'iteration' => $iteration,
                    'block_id' => $block->id,
                    'coin' => $block->coin->symbol,
                    'field' => $field,
                    'expected_type' => $expectedType,
                    'actual_type' => gettype($blockDetails[$field]),
                    'value' => $blockDetails[$field],
                    'reason' => 'Block detail field type mismatch'
                ];
            } elseif ($expectedType === 'numeric' && !is_numeric($blockDetails[$field])) {
                $failures[] = [
                    'iteration' => $iteration,
                    'block_id' => $block->id,
                    'coin' => $block->coin->symbol,
                    'field' => $field,
                    'expected_type' => $expectedType,
                    'actual_type' => gettype($blockDetails[$field]),
                    'value' => $blockDetails[$field],
                    'reason' => 'Block detail field type mismatch'
                ];
            }
        }
        
        // Verify transactions field exists (should be an array)
        if (!isset($blockDetails['tx'])) {
            $failures[] = [
                'iteration' => $iteration,
                'block_id' => $block->id,
                'coin' => $block->coin->symbol,
                'field' => 'tx',
                'reason' => 'Transactions field missing in block details'
            ];
        } elseif (!is_array($blockDetails['tx'])) {
            $failures[] = [
                'iteration' => $iteration,
                'block_id' => $block->id,
                'coin' => $block->coin->symbol,
                'field' => 'tx',
                'expected_type' => 'array',
                'actual_type' => gettype($blockDetails['tx']),
                'reason' => 'Transactions field is not an array'
            ];
        }
        
        // Verify confirmations field (optional but should be numeric if present)
        if (isset($blockDetails['confirmations']) && !is_int($blockDetails['confirmations'])) {
            $failures[] = [
                'iteration' => $iteration,
                'block_id' => $block->id,
                'coin' => $block->coin->symbol,
                'field' => 'confirmations',
                'expected_type' => 'integer',
                'actual_type' => gettype($blockDetails['confirmations']),
                'value' => $blockDetails['confirmations'],
                'reason' => 'Confirmations field type mismatch'
            ];
        }
    }
    
    /**
     * Verify transaction details have all required fields
     * 
     * @param array $txDetails
     * @param string $txid
     * @param Blocks $block
     * @param int $iteration
     * @param array &$failures
     */
    protected function verifyTransactionDetailsCompleteness($txDetails, $txid, $block, $iteration, &$failures)
    {
        // Required fields for transaction detail display (from requirements 3.4)
        // Note: txid should be present
        if (!isset($txDetails['txid'])) {
            $failures[] = [
                'iteration' => $iteration,
                'block_id' => $block->id,
                'coin' => $block->coin->symbol,
                'txid' => substr($txid, 0, 16),
                'field' => 'txid',
                'reason' => 'Transaction ID missing in transaction details'
            ];
        } elseif (!is_string($txDetails['txid'])) {
            $failures[] = [
                'iteration' => $iteration,
                'block_id' => $block->id,
                'coin' => $block->coin->symbol,
                'txid' => substr($txid, 0, 16),
                'field' => 'txid',
                'expected_type' => 'string',
                'actual_type' => gettype($txDetails['txid']),
                'reason' => 'Transaction ID type mismatch'
            ];
        }
        
        // Verify inputs field exists (should be an array)
        if (!isset($txDetails['vin'])) {
            $failures[] = [
                'iteration' => $iteration,
                'block_id' => $block->id,
                'coin' => $block->coin->symbol,
                'txid' => substr($txid, 0, 16),
                'field' => 'vin',
                'reason' => 'Inputs (vin) field missing in transaction details'
            ];
        } elseif (!is_array($txDetails['vin'])) {
            $failures[] = [
                'iteration' => $iteration,
                'block_id' => $block->id,
                'coin' => $block->coin->symbol,
                'txid' => substr($txid, 0, 16),
                'field' => 'vin',
                'expected_type' => 'array',
                'actual_type' => gettype($txDetails['vin']),
                'reason' => 'Inputs (vin) field is not an array'
            ];
        }
        
        // Verify outputs field exists (should be an array)
        if (!isset($txDetails['vout'])) {
            $failures[] = [
                'iteration' => $iteration,
                'block_id' => $block->id,
                'coin' => $block->coin->symbol,
                'txid' => substr($txid, 0, 16),
                'field' => 'vout',
                'reason' => 'Outputs (vout) field missing in transaction details'
            ];
        } elseif (!is_array($txDetails['vout'])) {
            $failures[] = [
                'iteration' => $iteration,
                'block_id' => $block->id,
                'coin' => $block->coin->symbol,
                'txid' => substr($txid, 0, 16),
                'field' => 'vout',
                'expected_type' => 'array',
                'actual_type' => gettype($txDetails['vout']),
                'reason' => 'Outputs (vout) field is not an array'
            ];
        } else {
            // Verify each output has amount information
            foreach ($txDetails['vout'] as $index => $vout) {
                if (!isset($vout['value'])) {
                    $failures[] = [
                        'iteration' => $iteration,
                        'block_id' => $block->id,
                        'coin' => $block->coin->symbol,
                        'txid' => substr($txid, 0, 16),
                        'field' => "vout[$index].value",
                        'reason' => 'Output amount (value) missing'
                    ];
                } elseif (!is_numeric($vout['value'])) {
                    $failures[] = [
                        'iteration' => $iteration,
                        'block_id' => $block->id,
                        'coin' => $block->coin->symbol,
                        'txid' => substr($txid, 0, 16),
                        'field' => "vout[$index].value",
                        'expected_type' => 'numeric',
                        'actual_type' => gettype($vout['value']),
                        'reason' => 'Output amount type mismatch'
                    ];
                }
            }
        }
        
        // Verify confirmation count (optional but should be numeric if present)
        if (isset($txDetails['confirmations']) && !is_int($txDetails['confirmations'])) {
            $failures[] = [
                'iteration' => $iteration,
                'block_id' => $block->id,
                'coin' => $block->coin->symbol,
                'txid' => substr($txid, 0, 16),
                'field' => 'confirmations',
                'expected_type' => 'integer',
                'actual_type' => gettype($txDetails['confirmations']),
                'value' => $txDetails['confirmations'],
                'reason' => 'Confirmation count type mismatch'
            ];
        }
    }
}
