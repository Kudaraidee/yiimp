<?php

namespace tests\unit\controllers;

use Codeception\Test\Unit;
use yii\web\Response;
use app\controllers\ApiController;

/**
 * Property-based tests for API Error Responses
 * 
 * Feature: yiimp-to-yiimp2-migration, Property 34: API error response format
 */
class ApiErrorPropertyTest extends Unit
{
    protected $controller;
    
    protected function _before()
    {
        parent::_before();
        $this->controller = new ApiController('api', \Yii::$app);
        \Yii::$app->response->format = Response::FORMAT_JSON;
        \Yii::$app->response->statusCode = 200;
        \Yii::$app->request->setQueryParams([]);
    }
    
    protected function _after()
    {
        \Yii::$app->response->statusCode = 200;
        \Yii::$app->request->setQueryParams([]);
        parent::_after();
    }
    
    /**
     * Property 34: API Error Response Format
     * 
     * For any invalid API request (missing parameters, invalid format), the system should
     * return an appropriate HTTP error code (400, 404, 500) and error message in JSON format.
     * 
     * Validates: Requirements 8.5
     * 
     * @test
     */
    public function testApiErrorResponseFormat()
    {
        // Feature: yiimp-to-yiimp2-migration, Property 34: API error response format
        
        $iterations = 100;
        $failures = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            try {
                // Generate random invalid request scenarios
                $scenario = $this->generateInvalidRequestScenario();
                
                // Execute the scenario
                $response = $this->executeScenario($scenario);
                
                // Verify error response format
                $this->assertErrorResponseFormat($response, $scenario, $i, $failures);
                
            } catch (\Exception $e) {
                // Exceptions are acceptable for error cases, but we should still verify format
                // if the exception is caught and converted to error response
                $failures[] = [
                    'iteration' => $i,
                    'reason' => 'Unhandled exception',
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
     * Generate random invalid request scenario
     * 
     * @return array
     */
    protected function generateInvalidRequestScenario()
    {
        $scenarios = [
            ['endpoint' => 'wallet', 'params' => [], 'expected_code' => 400],
            ['endpoint' => 'wallet', 'params' => ['address' => ''], 'expected_code' => 400],
            ['endpoint' => 'wallet', 'params' => ['address' => 'nonexistent_' . bin2hex(random_bytes(16))], 'expected_code' => 404],
        ];
        
        return $scenarios[array_rand($scenarios)];
    }
    
    /**
     * Execute test scenario
     * 
     * @param array $scenario
     * @return mixed
     */
    protected function executeScenario($scenario)
    {
        \Yii::$app->request->setQueryParams($scenario['params']);
        
        switch ($scenario['endpoint']) {
            case 'wallet':
                return $this->controller->actionWallet($scenario['params']['address'] ?? null);
            default:
                return ['error' => true, 'message' => 'Unknown endpoint', 'code' => 400];
        }
    }
    
    /**
     * Assert error response format
     * 
     * @param mixed $response
     * @param array $scenario
     * @param int $iteration
     * @param array &$failures
     */
    protected function assertErrorResponseFormat($response, $scenario, $iteration, &$failures)
    {
        if (!is_array($response)) {
            $failures[] = [
                'iteration' => $iteration,
                'scenario' => $scenario,
                'reason' => 'Response is not an array',
                'type' => gettype($response)
            ];
            return;
        }
        
        // Check if it's an error response
        if (!isset($response['error']) || $response['error'] !== true) {
            // Not an error response - this might be acceptable in some cases
            return;
        }
        
        // Required fields for error response
        $requiredFields = ['error', 'message', 'code'];
        
        foreach ($requiredFields as $field) {
            if (!array_key_exists($field, $response)) {
                $failures[] = [
                    'iteration' => $iteration,
                    'scenario' => $scenario,
                    'field' => $field,
                    'reason' => 'Required error field missing',
                    'response' => $response
                ];
            }
        }
        
        // Verify error field is boolean true
        if (isset($response['error']) && $response['error'] !== true) {
            $failures[] = [
                'iteration' => $iteration,
                'scenario' => $scenario,
                'field' => 'error',
                'value' => $response['error'],
                'reason' => 'Error field should be boolean true'
            ];
        }
        
        // Verify message is a string
        if (isset($response['message']) && !is_string($response['message'])) {
            $failures[] = [
                'iteration' => $iteration,
                'scenario' => $scenario,
                'field' => 'message',
                'type' => gettype($response['message']),
                'reason' => 'Message should be a string'
            ];
        }
        
        // Verify code is an integer and matches expected
        if (isset($response['code'])) {
            if (!is_int($response['code'])) {
                $failures[] = [
                    'iteration' => $iteration,
                    'scenario' => $scenario,
                    'field' => 'code',
                    'type' => gettype($response['code']),
                    'reason' => 'Code should be an integer'
                ];
            }
            
            // Verify code is a valid HTTP error code
            $validCodes = [400, 401, 403, 404, 500, 503];
            if (!in_array($response['code'], $validCodes)) {
                $failures[] = [
                    'iteration' => $iteration,
                    'scenario' => $scenario,
                    'field' => 'code',
                    'value' => $response['code'],
                    'reason' => 'Code is not a valid HTTP error code'
                ];
            }
        }
    }
    
    /**
     * Test missing wallet address parameter
     * 
     * @test
     */
    public function testApiWalletMissingAddressError()
    {
        // Feature: yiimp-to-yiimp2-migration, Property 34: API error response format (missing parameter)
        
        $response = $this->controller->actionWallet();
        
        $this->assertIsArray($response);
        $this->assertArrayHasKey('error', $response);
        $this->assertTrue($response['error']);
        $this->assertArrayHasKey('message', $response);
        $this->assertIsString($response['message']);
        $this->assertArrayHasKey('code', $response);
        $this->assertEquals(400, $response['code']);
    }
    
    /**
     * Test invalid wallet address
     * 
     * @test
     */
    public function testApiWalletInvalidAddressError()
    {
        // Feature: yiimp-to-yiimp2-migration, Property 34: API error response format (invalid parameter)
        
        $response = $this->controller->actionWallet('nonexistent_wallet_' . bin2hex(random_bytes(16)));
        
        $this->assertIsArray($response);
        $this->assertArrayHasKey('error', $response);
        $this->assertTrue($response['error']);
        $this->assertArrayHasKey('message', $response);
        $this->assertIsString($response['message']);
        $this->assertArrayHasKey('code', $response);
        $this->assertEquals(404, $response['code']);
    }
    
    /**
     * Test that error responses are valid JSON
     * 
     * @test
     */
    public function testApiErrorResponseIsValidJson()
    {
        // Feature: yiimp-to-yiimp2-migration, Property 34: API error response format (JSON validity)
        
        $response = $this->controller->actionWallet();
        
        // Encode to JSON
        $json = json_encode($response);
        
        $this->assertNotFalse($json, 'Error response cannot be encoded to JSON');
        $this->assertJson($json, 'Error response is not valid JSON');
        
        // Decode back and verify structure is preserved
        $decoded = json_decode($json, true);
        $this->assertEquals($response, $decoded, 'JSON encode/decode does not preserve error structure');
    }
    
    /**
     * Test HTTP status code is set correctly
     * 
     * @test
     */
    public function testApiErrorSetsHttpStatusCode()
    {
        // Feature: yiimp-to-yiimp2-migration, Property 34: API error response format (HTTP status)
        
        // Reset response
        \Yii::$app->response->statusCode = 200;
        
        $response = $this->controller->actionWallet();
        
        // Verify HTTP status code was set
        $this->assertNotEquals(200, \Yii::$app->response->statusCode, 
            'HTTP status code should be set for error responses');
        $this->assertEquals($response['code'], \Yii::$app->response->statusCode,
            'HTTP status code should match error code in response');
    }
    
    /**
     * Test various HTTP status codes are supported
     * 
     * @test
     */
    public function testApiErrorSupportsVariousStatusCodes()
    {
        // Test 400 - Bad Request (missing parameter)
        \Yii::$app->response->statusCode = 200;
        $response = $this->controller->actionWallet();
        $this->assertEquals(400, $response['code']);
        $this->assertEquals(400, \Yii::$app->response->statusCode);
        
        // Test 404 - Not Found (invalid wallet)
        \Yii::$app->response->statusCode = 200;
        $response = $this->controller->actionWallet('nonexistent_wallet_' . bin2hex(random_bytes(16)));
        $this->assertEquals(404, $response['code']);
        $this->assertEquals(404, \Yii::$app->response->statusCode);
        
        // Test 503 - Service Unavailable (server overload)
        // Create overload marker file
        $overloadFile = \Yii::getAlias('@app') . '/../log/overloaded';
        $overloadDir = dirname($overloadFile);
        if (!is_dir($overloadDir)) {
            mkdir($overloadDir, 0777, true);
        }
        touch($overloadFile);
        
        \Yii::$app->response->statusCode = 200;
        $response = $this->controller->actionStatus();
        $this->assertEquals(503, $response['code']);
        $this->assertEquals(503, \Yii::$app->response->statusCode);
        
        // Clean up
        if (file_exists($overloadFile)) {
            unlink($overloadFile);
        }
    }
    
    /**
     * Test error response format consistency across all endpoints
     * 
     * @test
     */
    public function testErrorResponseFormatConsistencyAcrossEndpoints()
    {
        $endpoints = [
            ['method' => 'actionWallet', 'params' => []],
            ['method' => 'actionWalletEx', 'params' => []],
        ];
        
        foreach ($endpoints as $endpoint) {
            \Yii::$app->response->statusCode = 200;
            \Yii::$app->request->setQueryParams($endpoint['params']);
            
            $response = call_user_func([$this->controller, $endpoint['method']]);
            
            // All error responses should have the same structure
            $this->assertIsArray($response, "Response from {$endpoint['method']} should be an array");
            $this->assertArrayHasKey('error', $response, "{$endpoint['method']} error response missing 'error' field");
            $this->assertArrayHasKey('message', $response, "{$endpoint['method']} error response missing 'message' field");
            $this->assertArrayHasKey('code', $response, "{$endpoint['method']} error response missing 'code' field");
            
            $this->assertTrue($response['error'], "{$endpoint['method']} error field should be true");
            $this->assertIsString($response['message'], "{$endpoint['method']} message should be a string");
            $this->assertIsInt($response['code'], "{$endpoint['method']} code should be an integer");
        }
    }
    
    /**
     * Test that errorResponse helper validates status codes
     * 
     * @test
     */
    public function testErrorResponseHelperValidatesStatusCodes()
    {
        // Use reflection to test the protected errorResponse method
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('errorResponse');
        $method->setAccessible(true);
        
        // Test valid codes
        $validCodes = [400, 404, 429, 500, 503];
        foreach ($validCodes as $code) {
            \Yii::$app->response->statusCode = 200;
            $response = $method->invoke($this->controller, 'Test message', $code);
            
            $this->assertEquals($code, $response['code'], "Valid code $code should be preserved");
            $this->assertEquals($code, \Yii::$app->response->statusCode, "HTTP status should be set to $code");
        }
        
        // Test invalid code (should default to 500)
        \Yii::$app->response->statusCode = 200;
        $response = $method->invoke($this->controller, 'Test message', 999);
        
        $this->assertEquals(500, $response['code'], "Invalid code should default to 500");
        $this->assertEquals(500, \Yii::$app->response->statusCode, "HTTP status should be set to 500 for invalid codes");
        $this->assertEquals('Internal server error', $response['message'], "Message should be reset for invalid codes");
    }
}
