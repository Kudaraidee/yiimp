<?php

namespace tests\unit\controllers;

use Codeception\Test\Unit;
use yii\web\Response;
use app\controllers\ApiController;

/**
 * Property-based tests for API Blocks endpoint
 * 
 * Feature: yiimp-to-yiimp2-migration, Property 33: API block information response format
 */
class ApiBlocksPropertyTest extends Unit
{
    protected $controller;
    
    protected function _before()
    {
        parent::_before();
        $this->controller = new ApiController('api', \Yii::$app);
        \Yii::$app->response->format = Response::FORMAT_JSON;
    }
    
    /**
     * Property 33: API Block Information Response Format
     * 
     * For any block information API request, the response should contain
     * recent blocks with timestamps and rewards in valid JSON format.
     * 
     * Validates: Requirements 8.4
     * 
     * @test
     */
    public function testApiBlockInformationResponseFormat()
    {
        // Feature: yiimp-to-yiimp2-migration, Property 33: API block information response format
        
        $iterations = 100;
        $failures = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            try {
                // Call the blocks endpoint with random limit
                $limit = rand(1, 100);
                \Yii::$app->request->setQueryParams(['limit' => $limit]);
                
                $response = $this->controller->actionBlocks();
                
                // Verify response is an array
                if (!is_array($response)) {
                    $failures[] = [
                        'iteration' => $i,
                        'reason' => 'Response is not an array',
                        'type' => gettype($response)
                    ];
                    continue;
                }
                
                // Verify limit is respected
                if (count($response) > $limit) {
                    $failures[] = [
                        'iteration' => $i,
                        'reason' => 'Response exceeds requested limit',
                        'limit' => $limit,
                        'actual_count' => count($response)
                    ];
                }
                
                // Verify each block entry has required fields
                foreach ($response as $index => $blockData) {
                    $this->assertBlockDataFormat($blockData, $i, $index, $failures);
                }
                
            } catch (\Exception $e) {
                $failures[] = [
                    'iteration' => $i,
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
     * Assert block data format
     * 
     * @param mixed $blockData
     * @param int $iteration
     * @param int $index
     * @param array &$failures
     */
    protected function assertBlockDataFormat($blockData, $iteration, $index, &$failures)
    {
        if (!is_array($blockData)) {
            $failures[] = [
                'iteration' => $iteration,
                'block_index' => $index,
                'reason' => 'Block data is not an array',
                'type' => gettype($blockData)
            ];
            return;
        }
        
        // Required fields for block information response
        $requiredFields = [
            'height' => 'integer',
            'blockhash' => 'string',
            'coin' => 'string',
            'algo' => 'string',
            'amount' => 'double',
            'difficulty' => 'double',
            'time' => 'integer',
            'timestamp' => 'string',
            'confirmations' => 'integer',
            'txhash' => 'string',
            'reward' => 'string'
        ];
        
        foreach ($requiredFields as $field => $expectedType) {
            if (!array_key_exists($field, $blockData)) {
                $failures[] = [
                    'iteration' => $iteration,
                    'block_index' => $index,
                    'field' => $field,
                    'reason' => 'Required field missing'
                ];
                continue;
            }
            
            $actualType = gettype($blockData[$field]);
            
            // Handle type checking
            if ($expectedType === 'integer' && !is_int($blockData[$field])) {
                $failures[] = [
                    'iteration' => $iteration,
                    'block_index' => $index,
                    'field' => $field,
                    'expected_type' => $expectedType,
                    'actual_type' => $actualType,
                    'value' => $blockData[$field],
                    'reason' => 'Field type mismatch'
                ];
            } elseif ($expectedType === 'double' && !is_numeric($blockData[$field])) {
                $failures[] = [
                    'iteration' => $iteration,
                    'block_index' => $index,
                    'field' => $field,
                    'expected_type' => $expectedType,
                    'actual_type' => $actualType,
                    'value' => $blockData[$field],
                    'reason' => 'Field type mismatch'
                ];
            } elseif ($expectedType === 'string' && !is_string($blockData[$field])) {
                $failures[] = [
                    'iteration' => $iteration,
                    'block_index' => $index,
                    'field' => $field,
                    'expected_type' => $expectedType,
                    'actual_type' => $actualType,
                    'value' => $blockData[$field],
                    'reason' => 'Field type mismatch'
                ];
            }
        }
        
        // Verify timestamp format
        if (isset($blockData['timestamp'])) {
            $timestamp = strtotime($blockData['timestamp']);
            if ($timestamp === false) {
                $failures[] = [
                    'iteration' => $iteration,
                    'block_index' => $index,
                    'field' => 'timestamp',
                    'value' => $blockData['timestamp'],
                    'reason' => 'Invalid timestamp format'
                ];
            }
        }
    }
    
    /**
     * Test that response is valid JSON when encoded
     * 
     * @test
     */
    public function testApiBlocksResponseIsValidJson()
    {
        // Feature: yiimp-to-yiimp2-migration, Property 33: API block information response format (JSON validity)
        
        $response = $this->controller->actionBlocks();
        
        // Encode to JSON
        $json = json_encode($response);
        
        $this->assertNotFalse($json, 'Response cannot be encoded to JSON');
        $this->assertJson($json, 'Response is not valid JSON');
        
        // Decode back and verify structure is preserved
        $decoded = json_decode($json, true);
        $this->assertEquals($response, $decoded, 'JSON encode/decode does not preserve structure');
    }
    
    /**
     * Test that blocks are ordered by time descending
     * 
     * @test
     */
    public function testApiBlocksOrderedByTimeDescending()
    {
        // Feature: yiimp-to-yiimp2-migration, Property 33: API block information response format (ordering)
        
        $response = $this->controller->actionBlocks();
        
        $this->assertIsArray($response);
        
        // If there are multiple blocks, verify ordering
        if (count($response) > 1) {
            $previousTime = PHP_INT_MAX;
            foreach ($response as $block) {
                $this->assertArrayHasKey('time', $block);
                $this->assertLessThanOrEqual($previousTime, $block['time'], 
                    'Blocks are not ordered by time descending');
                $previousTime = $block['time'];
            }
        }
    }
    
    /**
     * Test limit parameter validation
     * 
     * @test
     */
    public function testApiBlocksLimitParameterValidation()
    {
        // Feature: yiimp-to-yiimp2-migration, Property 33: API block information response format (limit validation)
        
        // Test with limit = 0 (should default to minimum 1)
        \Yii::$app->request->setQueryParams(['limit' => 0]);
        $response = $this->controller->actionBlocks();
        $this->assertIsArray($response);
        $this->assertLessThanOrEqual(1, count($response));
        
        // Test with limit > 100 (should cap at 100)
        \Yii::$app->request->setQueryParams(['limit' => 200]);
        $response = $this->controller->actionBlocks();
        $this->assertIsArray($response);
        $this->assertLessThanOrEqual(100, count($response));
        
        // Test with valid limit
        \Yii::$app->request->setQueryParams(['limit' => 10]);
        $response = $this->controller->actionBlocks();
        $this->assertIsArray($response);
        $this->assertLessThanOrEqual(10, count($response));
    }
}
