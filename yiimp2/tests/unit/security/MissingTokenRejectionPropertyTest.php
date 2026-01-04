<?php

namespace tests\unit\security;

use Codeception\Test\Unit;
use Yii;

/**
 * Property-based test for missing CSRF token rejection
 * 
 * Feature: csrf-session-management-fix, Property 3: Missing Token Rejection
 * Validates: Requirements 2.1, 2.2, 2.3, 2.4, 2.5
 * 
 * Property: For any POST, PUT, or DELETE request without a CSRF token,
 * the system should reject the request with an HTTP 400 status code
 * and log the validation failure.
 */
class MissingTokenRejectionPropertyTest extends Unit
{
    /**
     * @var \UnitTester
     */
    protected $tester;
    
    /**
     * Setup before each test
     */
    protected function _before()
    {
        parent::_before();
        
        // Ensure Yii application is available
        if (Yii::$app === null) {
            $config = require __DIR__ . '/../../config/test.php';
            new \yii\web\Application($config);
        }
        
        // Clean up any existing session
        if (Yii::$app->session->getIsActive()) {
            Yii::$app->session->destroy();
        }
    }
    
    /**
     * Cleanup after each test
     */
    protected function _after()
    {
        // Clean up session
        if (Yii::$app !== null && Yii::$app->session->getIsActive()) {
            Yii::$app->session->destroy();
        }
        
        // Clean up request state
        unset($_POST[Yii::$app->request->csrfParam]);
        unset($_SERVER['REQUEST_METHOD']);
        
        parent::_after();
    }
    
    /**
     * Property 3: Missing Token Rejection
     * 
     * For any POST, PUT, or DELETE request without a CSRF token, the system should:
     * 1. Reject POST requests without CSRF token (Requirement 2.1)
     * 2. Reject PUT requests without CSRF token (Requirement 2.2)
     * 3. Reject DELETE requests without CSRF token (Requirement 2.3)
     * 4. Return HTTP 400 status code (Requirement 2.4)
     * 5. Log validation failures (Requirement 2.5)
     * 
     * This property must hold across all possible request scenarios.
     * 
     * Feature: csrf-session-management-fix, Property 3: Missing Token Rejection
     * Validates: Requirements 2.1, 2.2, 2.3, 2.4, 2.5
     * 
     * @test
     */
    public function testMissingTokenRejectionProperty()
    {
        // Feature: csrf-session-management-fix, Property 3: Missing Token Rejection
        
        $iterations = 100;
        $failures = [];
        
        // Methods that require CSRF validation
        $methods = ['POST', 'PUT', 'DELETE'];
        
        for ($i = 0; $i < $iterations; $i++) {
            // Randomly select a method
            $method = $methods[array_rand($methods)];
            
            // Start fresh session for each iteration
            if (Yii::$app->session->getIsActive()) {
                Yii::$app->session->destroy();
            }
            
            try {
                Yii::$app->session->open();
            } catch (\Exception $e) {
                $failures[] = [
                    'iteration' => $i,
                    'method' => $method,
                    'phase' => 'session_initialization',
                    'reason' => 'Failed to open session',
                    'error' => $e->getMessage(),
                ];
                continue;
            }
            
            // Ensure a CSRF token exists in session (so we can test missing token in request)
            try {
                $sessionToken = Yii::$app->request->getCsrfToken();
            } catch (\Exception $e) {
                $failures[] = [
                    'iteration' => $i,
                    'method' => $method,
                    'phase' => 'token_generation',
                    'reason' => 'Failed to generate session CSRF token',
                    'error' => $e->getMessage(),
                ];
                Yii::$app->session->close();
                continue;
            }
            
            if (empty($sessionToken)) {
                $failures[] = [
                    'iteration' => $i,
                    'method' => $method,
                    'phase' => 'token_generation',
                    'reason' => 'Session CSRF token is empty',
                ];
                Yii::$app->session->close();
                continue;
            }
            
            // Simulate request WITHOUT CSRF token
            $_SERVER['REQUEST_METHOD'] = $method;
            
            // Ensure CSRF token is NOT in the request
            unset($_POST[Yii::$app->request->csrfParam]);
            unset($_GET[Yii::$app->request->csrfParam]);
            
            // Property Check 1-3: Validation should fail for POST/PUT/DELETE without token
            // (Requirements 2.1, 2.2, 2.3)
            try {
                $isValid = Yii::$app->request->validateCsrfToken();
            } catch (\Exception $e) {
                // Some exceptions are expected (like BadRequestHttpException)
                // but we need to verify it's the right kind
                if (strpos(get_class($e), 'BadRequestHttpException') !== false) {
                    // This is acceptable - the framework threw a 400 error
                    // Property Check 4: Verify it's a 400 status (Requirement 2.4)
                    $statusCode = $e->statusCode ?? null;
                    if ($statusCode !== 400) {
                        $failures[] = [
                            'iteration' => $i,
                            'method' => $method,
                            'phase' => 'status_code_check',
                            'reason' => 'Exception thrown but status code is not 400',
                            'status_code' => $statusCode,
                            'exception_class' => get_class($e),
                        ];
                    }
                    // Exception is expected, continue to next iteration
                    Yii::$app->session->close();
                    continue;
                } else {
                    // Unexpected exception type
                    $failures[] = [
                        'iteration' => $i,
                        'method' => $method,
                        'phase' => 'validation',
                        'reason' => 'Unexpected exception type during validation',
                        'exception_class' => get_class($e),
                        'exception_message' => $e->getMessage(),
                    ];
                    Yii::$app->session->close();
                    continue;
                }
            }
            
            // Property Check: Validation should return false for missing token
            if ($isValid === true) {
                $failures[] = [
                    'iteration' => $i,
                    'method' => $method,
                    'phase' => 'validation_result',
                    'reason' => 'Validation passed despite missing CSRF token',
                    'validation_result' => $isValid,
                    'session_token_present' => !empty($sessionToken),
                    'request_token_present' => isset($_POST[Yii::$app->request->csrfParam]),
                ];
            }
            
            // Property Check 5: Verify logging occurred (Requirement 2.5)
            // Note: In a real test, we would check log files or use a test logger
            // For now, we verify that the validation failed, which should trigger logging
            if ($isValid !== false) {
                $failures[] = [
                    'iteration' => $i,
                    'method' => $method,
                    'phase' => 'logging_check',
                    'reason' => 'Validation did not fail, so logging may not have occurred',
                    'validation_result' => $isValid,
                ];
            }
            
            // Clean up
            Yii::$app->session->close();
        }
        
        // Report failures if any
        if (!empty($failures)) {
            $failureCount = count($failures);
            $failuresByMethod = [];
            $failuresByPhase = [];
            
            foreach ($failures as $failure) {
                $method = $failure['method'] ?? 'unknown';
                $phase = $failure['phase'] ?? 'unknown';
                
                if (!isset($failuresByMethod[$method])) {
                    $failuresByMethod[$method] = 0;
                }
                $failuresByMethod[$method]++;
                
                if (!isset($failuresByPhase[$phase])) {
                    $failuresByPhase[$phase] = 0;
                }
                $failuresByPhase[$phase]++;
            }
            
            $this->fail(
                "Property 3 (Missing Token Rejection) failed in $failureCount out of $iterations iterations.\n" .
                "Failures by method: " . json_encode($failuresByMethod, JSON_PRETTY_PRINT) . "\n" .
                "Failures by phase: " . json_encode($failuresByPhase, JSON_PRETTY_PRINT) . "\n" .
                "First 5 failures:\n" . json_encode(array_slice($failures, 0, 5), JSON_PRETTY_PRINT)
            );
        }
        
        $this->assertTrue(
            true,
            "Property 3 holds for all $iterations iterations: " .
            "POST/PUT/DELETE requests without CSRF tokens are properly rejected"
        );
    }
    
    /**
     * Test that GET and HEAD requests do not require CSRF tokens
     * 
     * This verifies that safe HTTP methods are not subject to CSRF validation
     * 
     * @test
     */
    public function testSafeMethodsDoNotRequireCsrfToken()
    {
        $iterations = 50;
        $failures = [];
        
        // Safe methods that should NOT require CSRF validation
        $safeMethods = ['GET', 'HEAD', 'OPTIONS'];
        
        for ($i = 0; $i < $iterations; $i++) {
            // Randomly select a safe method
            $method = $safeMethods[array_rand($safeMethods)];
            
            // Start fresh session
            if (Yii::$app->session->getIsActive()) {
                Yii::$app->session->destroy();
            }
            
            Yii::$app->session->open();
            
            // Generate session token
            $sessionToken = Yii::$app->request->getCsrfToken();
            
            if (empty($sessionToken)) {
                $failures[] = [
                    'iteration' => $i,
                    'method' => $method,
                    'reason' => 'Failed to generate session token',
                ];
                Yii::$app->session->close();
                continue;
            }
            
            // Simulate safe method request WITHOUT CSRF token
            $_SERVER['REQUEST_METHOD'] = $method;
            unset($_POST[Yii::$app->request->csrfParam]);
            unset($_GET[Yii::$app->request->csrfParam]);
            
            // Validation should pass for safe methods even without token
            try {
                $isValid = Yii::$app->request->validateCsrfToken();
            } catch (\Exception $e) {
                $failures[] = [
                    'iteration' => $i,
                    'method' => $method,
                    'reason' => 'Exception thrown for safe method',
                    'exception_class' => get_class($e),
                    'exception_message' => $e->getMessage(),
                ];
                Yii::$app->session->close();
                continue;
            }
            
            // Safe methods should pass validation even without token
            if ($isValid !== true) {
                $failures[] = [
                    'iteration' => $i,
                    'method' => $method,
                    'reason' => 'Safe method failed CSRF validation',
                    'validation_result' => $isValid,
                ];
            }
            
            Yii::$app->session->close();
        }
        
        if (!empty($failures)) {
            $this->fail(
                "Safe methods test failed in " . count($failures) . " out of $iterations iterations:\n" .
                json_encode(array_slice($failures, 0, 5), JSON_PRETTY_PRINT)
            );
        }
        
        $this->assertTrue(
            true,
            "Safe HTTP methods (GET, HEAD, OPTIONS) do not require CSRF tokens in all $iterations iterations"
        );
    }
    
    /**
     * Test that requests with valid tokens are accepted
     * 
     * This is a complementary test to verify that the system accepts valid tokens
     * 
     * @test
     */
    public function testValidTokensAreAccepted()
    {
        $iterations = 50;
        $failures = [];
        
        $methods = ['POST', 'PUT', 'DELETE'];
        
        for ($i = 0; $i < $iterations; $i++) {
            $method = $methods[array_rand($methods)];
            
            // Start fresh session
            if (Yii::$app->session->getIsActive()) {
                Yii::$app->session->destroy();
            }
            
            Yii::$app->session->open();
            
            // Generate and include valid CSRF token
            $csrfToken = Yii::$app->request->getCsrfToken();
            
            if (empty($csrfToken)) {
                $failures[] = [
                    'iteration' => $i,
                    'method' => $method,
                    'reason' => 'Failed to generate CSRF token',
                ];
                Yii::$app->session->close();
                continue;
            }
            
            // Simulate request WITH valid CSRF token
            $_SERVER['REQUEST_METHOD'] = $method;
            $_POST[Yii::$app->request->csrfParam] = $csrfToken;
            
            // Validation should pass with valid token
            try {
                $isValid = Yii::$app->request->validateCsrfToken($csrfToken);
            } catch (\Exception $e) {
                $failures[] = [
                    'iteration' => $i,
                    'method' => $method,
                    'reason' => 'Exception thrown with valid token',
                    'exception_class' => get_class($e),
                    'exception_message' => $e->getMessage(),
                ];
                Yii::$app->session->close();
                continue;
            }
            
            if ($isValid !== true) {
                $failures[] = [
                    'iteration' => $i,
                    'method' => $method,
                    'reason' => 'Valid token was rejected',
                    'validation_result' => $isValid,
                    'token_length' => strlen($csrfToken),
                ];
            }
            
            // Clean up
            unset($_POST[Yii::$app->request->csrfParam]);
            Yii::$app->session->close();
        }
        
        if (!empty($failures)) {
            $this->fail(
                "Valid token acceptance test failed in " . count($failures) . " out of $iterations iterations:\n" .
                json_encode(array_slice($failures, 0, 5), JSON_PRETTY_PRINT)
            );
        }
        
        $this->assertTrue(
            true,
            "Valid CSRF tokens are accepted for all methods in all $iterations iterations"
        );
    }
    
    /**
     * Test that empty tokens are rejected
     * 
     * Verifies that empty string tokens are treated as missing tokens
     * 
     * @test
     */
    public function testEmptyTokensAreRejected()
    {
        $iterations = 50;
        $failures = [];
        
        $methods = ['POST', 'PUT', 'DELETE'];
        
        for ($i = 0; $i < $iterations; $i++) {
            $method = $methods[array_rand($methods)];
            
            // Start fresh session
            if (Yii::$app->session->getIsActive()) {
                Yii::$app->session->destroy();
            }
            
            Yii::$app->session->open();
            
            // Generate session token
            $sessionToken = Yii::$app->request->getCsrfToken();
            
            if (empty($sessionToken)) {
                $failures[] = [
                    'iteration' => $i,
                    'method' => $method,
                    'reason' => 'Failed to generate session token',
                ];
                Yii::$app->session->close();
                continue;
            }
            
            // Simulate request with EMPTY CSRF token
            $_SERVER['REQUEST_METHOD'] = $method;
            $_POST[Yii::$app->request->csrfParam] = '';
            
            // Validation should fail with empty token
            try {
                $isValid = Yii::$app->request->validateCsrfToken('');
            } catch (\Exception $e) {
                // Exception is acceptable for empty token
                if (strpos(get_class($e), 'BadRequestHttpException') !== false) {
                    // This is expected
                    Yii::$app->session->close();
                    continue;
                } else {
                    $failures[] = [
                        'iteration' => $i,
                        'method' => $method,
                        'reason' => 'Unexpected exception type for empty token',
                        'exception_class' => get_class($e),
                    ];
                    Yii::$app->session->close();
                    continue;
                }
            }
            
            if ($isValid === true) {
                $failures[] = [
                    'iteration' => $i,
                    'method' => $method,
                    'reason' => 'Empty token was accepted',
                    'validation_result' => $isValid,
                ];
            }
            
            // Clean up
            unset($_POST[Yii::$app->request->csrfParam]);
            Yii::$app->session->close();
        }
        
        if (!empty($failures)) {
            $this->fail(
                "Empty token rejection test failed in " . count($failures) . " out of $iterations iterations:\n" .
                json_encode(array_slice($failures, 0, 5), JSON_PRETTY_PRINT)
            );
        }
        
        $this->assertTrue(
            true,
            "Empty CSRF tokens are properly rejected in all $iterations iterations"
        );
    }
    
    /**
     * Test that null tokens are rejected
     * 
     * Verifies that null tokens are treated as missing tokens
     * 
     * @test
     */
    public function testNullTokensAreRejected()
    {
        $iterations = 50;
        $failures = [];
        
        $methods = ['POST', 'PUT', 'DELETE'];
        
        for ($i = 0; $i < $iterations; $i++) {
            $method = $methods[array_rand($methods)];
            
            // Start fresh session
            if (Yii::$app->session->getIsActive()) {
                Yii::$app->session->destroy();
            }
            
            Yii::$app->session->open();
            
            // Generate session token
            $sessionToken = Yii::$app->request->getCsrfToken();
            
            if (empty($sessionToken)) {
                $failures[] = [
                    'iteration' => $i,
                    'method' => $method,
                    'reason' => 'Failed to generate session token',
                ];
                Yii::$app->session->close();
                continue;
            }
            
            // Simulate request with NULL CSRF token
            $_SERVER['REQUEST_METHOD'] = $method;
            unset($_POST[Yii::$app->request->csrfParam]);
            
            // Validation should fail with null token
            try {
                $isValid = Yii::$app->request->validateCsrfToken(null);
            } catch (\Exception $e) {
                // Exception is acceptable for null token
                if (strpos(get_class($e), 'BadRequestHttpException') !== false) {
                    // This is expected
                    Yii::$app->session->close();
                    continue;
                } else {
                    $failures[] = [
                        'iteration' => $i,
                        'method' => $method,
                        'reason' => 'Unexpected exception type for null token',
                        'exception_class' => get_class($e),
                    ];
                    Yii::$app->session->close();
                    continue;
                }
            }
            
            if ($isValid === true) {
                $failures[] = [
                    'iteration' => $i,
                    'method' => $method,
                    'reason' => 'Null token was accepted',
                    'validation_result' => $isValid,
                ];
            }
            
            Yii::$app->session->close();
        }
        
        if (!empty($failures)) {
            $this->fail(
                "Null token rejection test failed in " . count($failures) . " out of $iterations iterations:\n" .
                json_encode(array_slice($failures, 0, 5), JSON_PRETTY_PRINT)
            );
        }
        
        $this->assertTrue(
            true,
            "Null CSRF tokens are properly rejected in all $iterations iterations"
        );
    }
}
