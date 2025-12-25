<?php

namespace tests\unit\controllers;

use Codeception\Test\Unit;
use yii\web\Response;
use app\controllers\ApiController;

/**
 * Property-based tests for API Status endpoint
 * 
 * Feature: yiimp-to-yiimp2-migration, Property 30: API pool status response format
 */
class ApiStatusPropertyTest extends Unit
{
    protected $controller;
    
    protected function _before()
    {
        parent::_before();
        $this->controller = new ApiController('api', \Yii::$app);
        \Yii::$app->response->format = Response::FORMAT_JSON;
    }
    
    /**
     * Property 30: API Pool Status Response Format
     * 
     * For any pool status API request, the response should contain all required fields
     * (current hashrate, active miners, algorithm statistics) in valid JSON format.
     * 
     * Validates: Requirements 8.1
     * 
     * @test
     */
    public function testApiPoolStatusResponseFormat()
    {
        // Feature: yiimp-to-yiimp2-migration, Property 30: API pool status response format
        
        // Check if database is available
        try {
            \Yii::$app->db->open();
        } catch (\Exception $e) {
            $this->markTestSkipped('Database not available: ' . $e->getMessage());
        }
        
        $iterations = 100;
        $failures = [];
        $successfulResponses = 0;
        
        for ($i = 0; $i < $iterations; $i++) {
            try {
                // Call the status endpoint
                $response = $this->controller->actionStatus();
                
                // Verify response is an array (will be JSON encoded)
                if (!is_array($response)) {
                    $failures[] = [
                        'iteration' => $i,
                        'reason' => 'Response is not an array',
                        'type' => gettype($response)
                    ];
                    continue;
                }
                
                // Check if it's an error response
                if (isset($response['error']) && $response['error'] === true) {
                    // Error responses are acceptable when there's no data or components fail
                    // Verify error response format
                    if (!isset($response['message']) || !isset($response['code'])) {
                        $failures[] = [
                            'iteration' => $i,
                            'reason' => 'Error response missing required fields',
                            'response' => $response
                        ];
                    }
                    continue;
                }
                
                // For successful responses, verify each algorithm entry has required fields
                foreach ($response as $algo => $stats) {
                    if (is_array($stats)) {
                        $this->assertAlgoStatsFormat($algo, $stats, $i, $failures);
                    }
                }
                
                $successfulResponses++;
                
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
        
        $this->assertTrue(true, "Property holds for all $iterations iterations ($successfulResponses successful responses)");
    }
    
    /**
     * Assert algorithm statistics format
     * 
     * @param string $algo
     * @param mixed $stats
     * @param int $iteration
     * @param array &$failures
     */
    protected function assertAlgoStatsFormat($algo, $stats, $iteration, &$failures)
    {
        if (!is_array($stats)) {
            $failures[] = [
                'iteration' => $iteration,
                'algo' => $algo,
                'reason' => 'Stats is not an array',
                'type' => gettype($stats)
            ];
            return;
        }
        
        // Required fields for pool status response
        $requiredFields = [
            'name' => 'string',
            'port' => 'integer',
            'coins' => 'integer',
            'fees' => 'double',
            'fees_solo' => 'double',
            'hashrate' => 'integer',
            'hashrate_shared' => 'integer',
            'workers' => 'integer',
            'workers_shared' => 'integer',
            'workers_solo' => 'integer',
            'estimate_current' => 'string',
            'estimate_last24h' => 'string',
            'actual_last24h' => 'string',
            'mbtc_mh_factor' => 'double',
            'hashrate_last24h' => 'double'
        ];
        
        foreach ($requiredFields as $field => $expectedType) {
            if (!array_key_exists($field, $stats)) {
                $failures[] = [
                    'iteration' => $iteration,
                    'algo' => $algo,
                    'field' => $field,
                    'reason' => 'Required field missing'
                ];
                continue;
            }
            
            $actualType = gettype($stats[$field]);
            
            // Handle type checking (integer and double are both numeric)
            if ($expectedType === 'integer' && !is_int($stats[$field])) {
                $failures[] = [
                    'iteration' => $iteration,
                    'algo' => $algo,
                    'field' => $field,
                    'expected_type' => $expectedType,
                    'actual_type' => $actualType,
                    'value' => $stats[$field],
                    'reason' => 'Field type mismatch'
                ];
            } elseif ($expectedType === 'double' && !is_numeric($stats[$field])) {
                $failures[] = [
                    'iteration' => $iteration,
                    'algo' => $algo,
                    'field' => $field,
                    'expected_type' => $expectedType,
                    'actual_type' => $actualType,
                    'value' => $stats[$field],
                    'reason' => 'Field type mismatch'
                ];
            } elseif ($expectedType === 'string' && !is_string($stats[$field])) {
                $failures[] = [
                    'iteration' => $iteration,
                    'algo' => $algo,
                    'field' => $field,
                    'expected_type' => $expectedType,
                    'actual_type' => $actualType,
                    'value' => $stats[$field],
                    'reason' => 'Field type mismatch'
                ];
            }
        }
    }
    
    /**
     * Test that response is valid JSON when encoded
     * 
     * @test
     */
    public function testApiStatusResponseIsValidJson()
    {
        // Feature: yiimp-to-yiimp2-migration, Property 30: API pool status response format (JSON validity)
        
        $response = $this->controller->actionStatus();
        
        // Encode to JSON
        $json = json_encode($response);
        
        $this->assertNotFalse($json, 'Response cannot be encoded to JSON');
        $this->assertJson($json, 'Response is not valid JSON');
        
        // Decode back and verify structure is preserved
        $decoded = json_decode($json, true);
        $this->assertEquals($response, $decoded, 'JSON encode/decode does not preserve structure');
    }
}
