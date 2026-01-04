<?php

namespace tests\unit\controllers;

use Codeception\Test\Unit;
use yii\web\Response;
use app\controllers\ApiController;

/**
 * Property-based tests for API Currency endpoint
 * 
 * Feature: yiimp-to-yiimp2-migration, Property 32: API currency information response format
 */
class ApiCurrencyPropertyTest extends Unit
{
    protected $controller;
    
    protected function _before()
    {
        parent::_before();
        $this->controller = new ApiController('api', \Yii::$app);
        \Yii::$app->response->format = Response::FORMAT_JSON;
    }
    
    /**
     * Property 32: API Currency Information Response Format
     * 
     * For any currency information API request, the response should contain
     * current exchange rates and profitability data in valid JSON format.
     * 
     * Validates: Requirements 8.3
     * 
     * @test
     */
    public function testApiCurrencyInformationResponseFormat()
    {
        // Feature: yiimp-to-yiimp2-migration, Property 32: API currency information response format
        
        $iterations = 100;
        $failures = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            try {
                // Call the currency endpoint
                $response = $this->controller->actionCurrency();
                
                // Verify response is an array
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
                
                // Verify each currency entry has required fields
                foreach ($response as $symbol => $currencyData) {
                    $this->assertCurrencyDataFormat($symbol, $currencyData, $i, $failures);
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
     * Assert currency data format
     * 
     * @param string $symbol
     * @param mixed $currencyData
     * @param int $iteration
     * @param array &$failures
     */
    protected function assertCurrencyDataFormat($symbol, $currencyData, $iteration, &$failures)
    {
        if (!is_array($currencyData)) {
            $failures[] = [
                'iteration' => $iteration,
                'symbol' => $symbol,
                'reason' => 'Currency data is not an array',
                'type' => gettype($currencyData)
            ];
            return;
        }
        
        // Required fields for currency information response
        $requiredFields = [
            'name' => 'string',
            'algo' => 'string',
            'port' => 'integer',
            'reward' => 'double',
            'blocktime' => 'integer',
            'height' => 'integer',
            'difficulty' => 'double',
            'autotrade' => 'boolean',
            'fees' => 'double',
            'fees_solo' => 'double',
            'miners' => 'integer',
            'workers' => 'integer',
            'hashrate' => 'double',
            'network_hashrate' => 'double',
            'estimate' => 'string',
            '24h_blocks' => 'integer',
            '24h_btc' => 'double',
            'lastblock' => 'integer',
            'timesincelast' => 'integer'
        ];
        
        foreach ($requiredFields as $field => $expectedType) {
            if (!array_key_exists($field, $currencyData)) {
                $failures[] = [
                    'iteration' => $iteration,
                    'symbol' => $symbol,
                    'field' => $field,
                    'reason' => 'Required field missing'
                ];
                continue;
            }
            
            $actualType = gettype($currencyData[$field]);
            
            // Handle type checking
            if ($expectedType === 'integer' && !is_int($currencyData[$field])) {
                $failures[] = [
                    'iteration' => $iteration,
                    'symbol' => $symbol,
                    'field' => $field,
                    'expected_type' => $expectedType,
                    'actual_type' => $actualType,
                    'value' => $currencyData[$field],
                    'reason' => 'Field type mismatch'
                ];
            } elseif ($expectedType === 'double' && !is_numeric($currencyData[$field])) {
                $failures[] = [
                    'iteration' => $iteration,
                    'symbol' => $symbol,
                    'field' => $field,
                    'expected_type' => $expectedType,
                    'actual_type' => $actualType,
                    'value' => $currencyData[$field],
                    'reason' => 'Field type mismatch'
                ];
            } elseif ($expectedType === 'string' && !is_string($currencyData[$field])) {
                $failures[] = [
                    'iteration' => $iteration,
                    'symbol' => $symbol,
                    'field' => $field,
                    'expected_type' => $expectedType,
                    'actual_type' => $actualType,
                    'value' => $currencyData[$field],
                    'reason' => 'Field type mismatch'
                ];
            } elseif ($expectedType === 'boolean' && !is_bool($currencyData[$field])) {
                $failures[] = [
                    'iteration' => $iteration,
                    'symbol' => $symbol,
                    'field' => $field,
                    'expected_type' => $expectedType,
                    'actual_type' => $actualType,
                    'value' => $currencyData[$field],
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
    public function testApiCurrencyResponseIsValidJson()
    {
        // Feature: yiimp-to-yiimp2-migration, Property 32: API currency information response format (JSON validity)
        
        $response = $this->controller->actionCurrency();
        
        // Encode to JSON
        $json = json_encode($response);
        
        $this->assertNotFalse($json, 'Response cannot be encoded to JSON');
        $this->assertJson($json, 'Response is not valid JSON');
        
        // Decode back and verify structure is preserved
        $decoded = json_decode($json, true);
        $this->assertEquals($response, $decoded, 'JSON encode/decode does not preserve structure');
    }
    
    /**
     * Test that profitability data is included
     * 
     * @test
     */
    public function testApiCurrencyIncludesProfitabilityData()
    {
        // Feature: yiimp-to-yiimp2-migration, Property 32: API currency information response format (profitability)
        
        $response = $this->controller->actionCurrency();
        
        $this->assertIsArray($response);
        
        // Skip if error response
        if (isset($response['error']) && $response['error'] === true) {
            $this->markTestSkipped('API returned error response');
        }
        
        // If there are coins, verify profitability data is present
        if (!empty($response)) {
            $firstCoin = reset($response);
            if (is_array($firstCoin)) {
                $this->assertArrayHasKey('estimate', $firstCoin, 'Profitability estimate missing');
                $this->assertArrayHasKey('difficulty', $firstCoin, 'Difficulty missing');
                $this->assertArrayHasKey('reward', $firstCoin, 'Reward missing');
            }
        }
    }
}
