<?php

namespace tests\unit\security;

use Codeception\Test\Unit;
use Yii;

/**
 * Property-based test for CSRF token consistency within session
 * 
 * Feature: csrf-session-management-fix, Property 2: CSRF Token Consistency Within Session
 * Validates: Requirements 1.5
 * 
 * Property: For any session, when multiple requests are made, the base CSRF token
 * should remain constant throughout the session lifetime.
 */
class CsrfTokenConsistencyPropertyTest extends Unit
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
        
        parent::_after();
    }
    
    /**
     * Property 2: CSRF Token Consistency Within Session
     * 
     * For any session, when multiple requests are made, the CSRF token should:
     * 1. Remain constant throughout the session (Requirement 1.5)
     * 2. Return the same value on repeated calls to getCsrfToken()
     * 3. Not change unless explicitly regenerated
     * 
     * This property must hold across all possible session scenarios.
     * 
     * Feature: csrf-session-management-fix, Property 2: CSRF Token Consistency Within Session
     * Validates: Requirements 1.5
     * 
     * @test
     */
    public function testCsrfTokenConsistencyWithinSessionProperty()
    {
        // Feature: csrf-session-management-fix, Property 2: CSRF Token Consistency Within Session
        
        $iterations = 100;
        $failures = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            // Start fresh session
            if (Yii::$app->session->getIsActive()) {
                Yii::$app->session->destroy();
            }
            
            try {
                Yii::$app->session->open();
            } catch (\Exception $e) {
                $failures[] = [
                    'iteration' => $i,
                    'phase' => 'session_initialization',
                    'reason' => 'Failed to open session',
                    'error' => $e->getMessage(),
                ];
                continue;
            }
            
            // Get initial CSRF token
            try {
                $initialToken = Yii::$app->request->getCsrfToken();
            } catch (\Exception $e) {
                $failures[] = [
                    'iteration' => $i,
                    'phase' => 'initial_token_generation',
                    'reason' => 'Failed to generate initial CSRF token',
                    'error' => $e->getMessage(),
                ];
                Yii::$app->session->close();
                continue;
            }
            
            if (empty($initialToken)) {
                $failures[] = [
                    'iteration' => $i,
                    'phase' => 'initial_token_generation',
                    'reason' => 'Initial CSRF token is empty',
                ];
                Yii::$app->session->close();
                continue;
            }
            
            // Simulate multiple requests within the same session
            // Random number of requests between 3 and 20
            $requestCount = rand(3, 20);
            $tokens = [$initialToken];
            
            for ($j = 0; $j < $requestCount; $j++) {
                try {
                    $token = Yii::$app->request->getCsrfToken();
                } catch (\Exception $e) {
                    $failures[] = [
                        'iteration' => $i,
                        'request' => $j,
                        'phase' => 'token_retrieval',
                        'reason' => 'Failed to retrieve CSRF token',
                        'error' => $e->getMessage(),
                    ];
                    break;
                }
                
                if (empty($token)) {
                    $failures[] = [
                        'iteration' => $i,
                        'request' => $j,
                        'phase' => 'token_retrieval',
                        'reason' => 'Retrieved CSRF token is empty',
                    ];
                    break;
                }
                
                $tokens[] = $token;
            }
            
            // Property Check: All tokens should be identical (Requirement 1.5)
            // This verifies that the token remains constant throughout the session
            $uniqueTokens = array_unique($tokens);
            
            if (count($uniqueTokens) !== 1) {
                $failures[] = [
                    'iteration' => $i,
                    'phase' => 'consistency_check',
                    'reason' => 'CSRF token changed within same session',
                    'unique_token_count' => count($uniqueTokens),
                    'total_requests' => count($tokens),
                    'first_token' => substr($tokens[0], 0, 20) . '...',
                    'last_token' => substr($tokens[count($tokens) - 1], 0, 20) . '...',
                    'all_unique_tokens' => array_map(function($t) {
                        return substr($t, 0, 20) . '...';
                    }, array_values($uniqueTokens)),
                ];
            }
            
            // Additional check: Verify token is still the same after some time
            // Simulate time passing (though we can't actually wait in tests)
            // Instead, we'll do some operations that might affect session state
            Yii::$app->session->set('test_key_' . $i, 'test_value_' . rand(1000, 9999));
            Yii::$app->session->get('test_key_' . $i);
            
            try {
                $finalToken = Yii::$app->request->getCsrfToken();
            } catch (\Exception $e) {
                $failures[] = [
                    'iteration' => $i,
                    'phase' => 'final_token_check',
                    'reason' => 'Failed to retrieve final CSRF token',
                    'error' => $e->getMessage(),
                ];
                Yii::$app->session->close();
                continue;
            }
            
            if ($finalToken !== $initialToken) {
                $failures[] = [
                    'iteration' => $i,
                    'phase' => 'final_token_check',
                    'reason' => 'CSRF token changed after session operations',
                    'initial_token' => substr($initialToken, 0, 20) . '...',
                    'final_token' => substr($finalToken, 0, 20) . '...',
                ];
            }
            
            // Clean up
            Yii::$app->session->close();
        }
        
        // Report failures if any
        if (!empty($failures)) {
            $failureCount = count($failures);
            $failuresByPhase = [];
            foreach ($failures as $failure) {
                $phase = $failure['phase'] ?? 'unknown';
                if (!isset($failuresByPhase[$phase])) {
                    $failuresByPhase[$phase] = 0;
                }
                $failuresByPhase[$phase]++;
            }
            
            $this->fail(
                "Property 2 (CSRF Token Consistency Within Session) failed in $failureCount out of $iterations iterations.\n" .
                "Failures by phase: " . json_encode($failuresByPhase, JSON_PRETTY_PRINT) . "\n" .
                "First 5 failures:\n" . json_encode(array_slice($failures, 0, 5), JSON_PRETTY_PRINT)
            );
        }
        
        $this->assertTrue(
            true,
            "Property 2 holds for all $iterations iterations: " .
            "CSRF tokens remain consistent throughout session lifetime"
        );
    }
    
    /**
     * Test CSRF token consistency across different session operations
     * 
     * Verifies that common session operations don't affect token consistency
     * 
     * @test
     */
    public function testCsrfTokenConsistencyAcrossSessionOperations()
    {
        $iterations = 50;
        $failures = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            // Start fresh session
            if (Yii::$app->session->getIsActive()) {
                Yii::$app->session->destroy();
            }
            
            Yii::$app->session->open();
            
            // Get initial token
            $initialToken = Yii::$app->request->getCsrfToken();
            
            if (empty($initialToken)) {
                $failures[] = [
                    'iteration' => $i,
                    'reason' => 'Failed to generate initial token',
                ];
                Yii::$app->session->close();
                continue;
            }
            
            // Perform various session operations
            $operations = [
                'set_data' => function() use ($i) {
                    Yii::$app->session->set('test_' . $i, 'value_' . rand(1000, 9999));
                },
                'get_data' => function() use ($i) {
                    Yii::$app->session->get('test_' . $i);
                },
                'remove_data' => function() use ($i) {
                    Yii::$app->session->remove('test_' . $i);
                },
                'has_data' => function() use ($i) {
                    Yii::$app->session->has('test_' . $i);
                },
                'set_flash' => function() use ($i) {
                    Yii::$app->session->setFlash('flash_' . $i, 'message_' . rand(1000, 9999));
                },
                'get_flash' => function() use ($i) {
                    Yii::$app->session->getFlash('flash_' . $i);
                },
            ];
            
            // Randomly perform 5-10 operations
            $operationCount = rand(5, 10);
            $operationNames = array_keys($operations);
            
            for ($j = 0; $j < $operationCount; $j++) {
                $opName = $operationNames[array_rand($operationNames)];
                try {
                    $operations[$opName]();
                } catch (\Exception $e) {
                    // Some operations might fail (e.g., getting non-existent data)
                    // This is acceptable
                }
                
                // Check token after each operation
                $tokenAfterOp = Yii::$app->request->getCsrfToken();
                
                if ($tokenAfterOp !== $initialToken) {
                    $failures[] = [
                        'iteration' => $i,
                        'operation' => $opName,
                        'operation_number' => $j,
                        'reason' => 'Token changed after session operation',
                        'initial_token' => substr($initialToken, 0, 20) . '...',
                        'token_after_op' => substr($tokenAfterOp, 0, 20) . '...',
                    ];
                    break;
                }
            }
            
            Yii::$app->session->close();
        }
        
        if (!empty($failures)) {
            $this->fail(
                "Token consistency across operations failed in " . count($failures) . " out of $iterations iterations:\n" .
                json_encode(array_slice($failures, 0, 5), JSON_PRETTY_PRINT)
            );
        }
        
        $this->assertTrue(
            true,
            "CSRF token remains consistent across session operations in all $iterations iterations"
        );
    }
    
    /**
     * Test that token consistency is maintained across session close/reopen
     * 
     * Verifies that closing and reopening a session preserves the CSRF token
     * 
     * @test
     */
    public function testCsrfTokenConsistencyAcrossSessionCloseReopen()
    {
        $iterations = 50;
        $failures = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            // Start fresh session
            if (Yii::$app->session->getIsActive()) {
                Yii::$app->session->destroy();
            }
            
            Yii::$app->session->open();
            
            // Get initial token
            $initialToken = Yii::$app->request->getCsrfToken();
            $sessionId = Yii::$app->session->getId();
            
            if (empty($initialToken)) {
                $failures[] = [
                    'iteration' => $i,
                    'reason' => 'Failed to generate initial token',
                ];
                Yii::$app->session->close();
                continue;
            }
            
            // Close session
            Yii::$app->session->close();
            
            // Reopen session with same ID (simulating subsequent request)
            Yii::$app->session->setId($sessionId);
            Yii::$app->session->open();
            
            // Get token again
            $tokenAfterReopen = Yii::$app->request->getCsrfToken();
            
            if ($tokenAfterReopen !== $initialToken) {
                $failures[] = [
                    'iteration' => $i,
                    'reason' => 'Token changed after session close/reopen',
                    'initial_token' => substr($initialToken, 0, 20) . '...',
                    'token_after_reopen' => substr($tokenAfterReopen, 0, 20) . '...',
                    'session_id' => $sessionId,
                ];
            }
            
            // Perform multiple close/reopen cycles
            $cycles = rand(2, 5);
            for ($j = 0; $j < $cycles; $j++) {
                Yii::$app->session->close();
                Yii::$app->session->setId($sessionId);
                Yii::$app->session->open();
                
                $tokenAfterCycle = Yii::$app->request->getCsrfToken();
                
                if ($tokenAfterCycle !== $initialToken) {
                    $failures[] = [
                        'iteration' => $i,
                        'cycle' => $j,
                        'reason' => 'Token changed after close/reopen cycle',
                        'initial_token' => substr($initialToken, 0, 20) . '...',
                        'token_after_cycle' => substr($tokenAfterCycle, 0, 20) . '...',
                    ];
                    break;
                }
            }
            
            Yii::$app->session->close();
        }
        
        if (!empty($failures)) {
            $this->fail(
                "Token consistency across close/reopen failed in " . count($failures) . " out of $iterations iterations:\n" .
                json_encode(array_slice($failures, 0, 5), JSON_PRETTY_PRINT)
            );
        }
        
        $this->assertTrue(
            true,
            "CSRF token remains consistent across session close/reopen in all $iterations iterations"
        );
    }
    
}
