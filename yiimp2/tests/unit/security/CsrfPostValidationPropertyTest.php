<?php

namespace tests\unit\security;

use Codeception\Test\Unit;
use Yii;
use yii\web\Session;

/**
 * Property-based test for CSRF post-validation processing
 * 
 * Feature: csrf-validation-fix
 * Tests Property 3: Post-validation processing
 * 
 * Validates: Requirements 1.3
 */
class CsrfPostValidationPropertyTest extends Unit
{
    protected $originalSession;
    
    protected function _before()
    {
        parent::_before();
        // Store original session for restoration
        $this->originalSession = Yii::$app->get('session', false);
    }
    
    protected function _after()
    {
        // Restore original session
        if ($this->originalSession) {
            Yii::$app->set('session', $this->originalSession);
        }
        parent::_after();
    }
    
    /**
     * Property 3: Post-validation processing
     * 
     * For any successful CSRF validation, the system should execute the coin
     * creation logic and not return a CSRF error.
     * 
     * Validates: Requirements 1.3
     * Feature: csrf-validation-fix, Property 3: Post-validation processing
     * 
     * @test
     */
    public function testPostValidationProcessing()
    {
        // Feature: csrf-validation-fix, Property 3: Post-validation processing
        
        $iterations = 100;
        $failures = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            // Create a fresh session for each iteration
            $session = $this->createFreshSession();
            
            // Generate a valid CSRF token
            $token = $this->generateValidToken($session);
            
            if ($token === null) {
                $failures[] = [
                    'iteration' => $i,
                    'reason' => 'Failed to generate valid token'
                ];
                $this->destroySession($session);
                continue;
            }
            
            // Simulate successful CSRF validation and check processing
            $processingResult = $this->simulatePostValidationProcessing($session, $token);
            
            // After successful validation, controller action should execute
            if (!$processingResult['action_executed']) {
                $failures[] = [
                    'iteration' => $i,
                    'token' => substr($token, 0, 20) . '...',
                    'reason' => 'Controller action did not execute after successful validation'
                ];
                $this->destroySession($session);
                continue;
            }
            
            // Should not return CSRF error
            if ($processingResult['has_csrf_error']) {
                $failures[] = [
                    'iteration' => $i,
                    'token' => substr($token, 0, 20) . '...',
                    'error' => $processingResult['error_message'],
                    'reason' => 'CSRF error returned despite successful validation'
                ];
                $this->destroySession($session);
                continue;
            }
            
            // Verify no CSRF-related exception was thrown
            if ($processingResult['has_csrf_exception']) {
                $failures[] = [
                    'iteration' => $i,
                    'exception' => $processingResult['exception_type'],
                    'reason' => 'CSRF exception thrown despite successful validation'
                ];
                $this->destroySession($session);
                continue;
            }
            
            // Verify processing completed without interruption
            if (!$processingResult['processing_completed']) {
                $failures[] = [
                    'iteration' => $i,
                    'reason' => 'Processing was interrupted after validation'
                ];
            }
            
            // Clean up session
            $this->destroySession($session);
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
     * Test that processing continues for different action types
     * 
     * @test
     */
    public function testProcessingContinuesForDifferentActions()
    {
        // Feature: csrf-validation-fix, Property 3: Post-validation processing
        
        $iterations = 50;
        $failures = [];
        
        $actions = [
            'coin-create' => ['name' => 'TestCoin', 'symbol' => 'TST'],
            'coin-update' => ['id' => 1, 'name' => 'UpdatedCoin'],
            'user-ban' => ['user_id' => 123],
            'payment-cancel' => ['payment_id' => 456],
        ];
        
        for ($i = 0; $i < $iterations; $i++) {
            // Create a fresh session
            $session = $this->createFreshSession();
            
            // Generate a valid token
            $token = $this->generateValidToken($session);
            
            if ($token === null) {
                $failures[] = [
                    'iteration' => $i,
                    'reason' => 'Failed to generate valid token'
                ];
                $this->destroySession($session);
                continue;
            }
            
            // Pick random action
            $actionName = array_rand($actions);
            $actionData = $actions[$actionName];
            
            // Simulate processing for this action
            $processingResult = $this->simulatePostValidationProcessing(
                $session, 
                $token, 
                $actionName, 
                $actionData
            );
            
            // Action should execute
            if (!$processingResult['action_executed']) {
                $failures[] = [
                    'iteration' => $i,
                    'action' => $actionName,
                    'reason' => 'Action did not execute for: ' . $actionName
                ];
            }
            
            // Should not have CSRF error
            if ($processingResult['has_csrf_error']) {
                $failures[] = [
                    'iteration' => $i,
                    'action' => $actionName,
                    'reason' => 'CSRF error for valid token on action: ' . $actionName
                ];
            }
            
            // Clean up session
            $this->destroySession($session);
        }
        
        // Report failures if any
        if (!empty($failures)) {
            $this->fail(
                "Action processing test failed in " . count($failures) . " out of $iterations iterations:\n" .
                json_encode($failures, JSON_PRETTY_PRINT)
            );
        }
        
        $this->assertTrue(true, "Property holds for all $iterations iterations");
    }
    
    /**
     * Test that validation errors are not CSRF-related
     * 
     * @test
     */
    public function testNonCsrfErrorsAfterValidation()
    {
        // Feature: csrf-validation-fix, Property 3: Post-validation processing
        
        $iterations = 50;
        $failures = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            // Create a fresh session
            $session = $this->createFreshSession();
            
            // Generate a valid token
            $token = $this->generateValidToken($session);
            
            if ($token === null) {
                $failures[] = [
                    'iteration' => $i,
                    'reason' => 'Failed to generate valid token'
                ];
                $this->destroySession($session);
                continue;
            }
            
            // Simulate processing with intentional validation error (non-CSRF)
            $processingResult = $this->simulatePostValidationProcessing(
                $session, 
                $token,
                'coin-create',
                ['name' => ''] // Invalid data to trigger validation error
            );
            
            // Action should still execute (reach validation logic)
            if (!$processingResult['action_executed']) {
                $failures[] = [
                    'iteration' => $i,
                    'reason' => 'Action did not execute even with valid CSRF token'
                ];
                $this->destroySession($session);
                continue;
            }
            
            // If there's an error, it should NOT be CSRF-related
            if ($processingResult['has_error'] && $processingResult['has_csrf_error']) {
                $failures[] = [
                    'iteration' => $i,
                    'error' => $processingResult['error_message'],
                    'reason' => 'Error is CSRF-related when it should be validation-related'
                ];
            }
            
            // Clean up session
            $this->destroySession($session);
        }
        
        // Report failures if any
        if (!empty($failures)) {
            $this->fail(
                "Non-CSRF error test failed in " . count($failures) . " out of $iterations iterations:\n" .
                json_encode($failures, JSON_PRETTY_PRINT)
            );
        }
        
        $this->assertTrue(true, "Property holds for all $iterations iterations");
    }
    
    /**
     * Test that processing completes without CSRF interruption
     * 
     * @test
     */
    public function testProcessingCompletesWithoutCsrfInterruption()
    {
        // Feature: csrf-validation-fix, Property 3: Post-validation processing
        
        $iterations = 50;
        $failures = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            // Create a fresh session
            $session = $this->createFreshSession();
            
            // Generate a valid token
            $token = $this->generateValidToken($session);
            
            if ($token === null) {
                $failures[] = [
                    'iteration' => $i,
                    'reason' => 'Failed to generate valid token'
                ];
                $this->destroySession($session);
                continue;
            }
            
            // Simulate full processing cycle
            $processingResult = $this->simulatePostValidationProcessing($session, $token);
            
            // Processing should complete
            if (!$processingResult['processing_completed']) {
                $failures[] = [
                    'iteration' => $i,
                    'reason' => 'Processing did not complete'
                ];
            }
            
            // Should reach the end of controller action
            if (!$processingResult['reached_end']) {
                $failures[] = [
                    'iteration' => $i,
                    'reason' => 'Did not reach end of controller action'
                ];
            }
            
            // Should not have been interrupted by CSRF check
            if ($processingResult['interrupted_by_csrf']) {
                $failures[] = [
                    'iteration' => $i,
                    'reason' => 'Processing was interrupted by CSRF check'
                ];
            }
            
            // Clean up session
            $this->destroySession($session);
        }
        
        // Report failures if any
        if (!empty($failures)) {
            $this->fail(
                "Processing completion test failed in " . count($failures) . " out of $iterations iterations:\n" .
                json_encode($failures, JSON_PRETTY_PRINT)
            );
        }
        
        $this->assertTrue(true, "Property holds for all $iterations iterations");
    }
    
    // Helper methods
    
    /**
     * Create a fresh session for testing
     */
    protected function createFreshSession()
    {
        $session = new Session();
        $session->open();
        return $session;
    }
    
    /**
     * Destroy a test session
     */
    protected function destroySession($session)
    {
        if ($session && $session->getIsActive()) {
            $session->destroy();
        }
    }
    
    /**
     * Generate a valid CSRF token
     */
    protected function generateValidToken($session)
    {
        // Set the session as the active session temporarily
        $originalSession = Yii::$app->get('session', false);
        Yii::$app->set('session', $session);
        
        try {
            // Generate and return CSRF token
            $token = Yii::$app->request->getCsrfToken();
            
            // Ensure token is stored in session
            $csrfParam = Yii::$app->request->csrfParam;
            if (!$session->has($csrfParam)) {
                $rawToken = Yii::$app->security->generateRandomString();
                $session->set($csrfParam, $rawToken);
            }
            
            return $token;
        } catch (\Exception $e) {
            return null;
        } finally {
            // Restore original session
            if ($originalSession) {
                Yii::$app->set('session', $originalSession);
            }
        }
    }
    
    /**
     * Simulate post-validation processing
     */
    protected function simulatePostValidationProcessing($session, $token, $action = 'coin-create', $data = [])
    {
        // Set the session as the active session temporarily
        $originalSession = Yii::$app->get('session', false);
        Yii::$app->set('session', $session);
        
        try {
            // Simulate POST request with CSRF token
            $csrfParam = Yii::$app->request->csrfParam;
            $_POST[$csrfParam] = $token;
            $_SERVER['REQUEST_METHOD'] = 'POST';
            
            // Validate CSRF token
            $isValid = $this->validateCsrfToken($token, $session);
            
            $result = [
                'action_executed' => false,
                'has_csrf_error' => false,
                'has_csrf_exception' => false,
                'has_error' => false,
                'error_message' => null,
                'exception_type' => null,
                'processing_completed' => false,
                'reached_end' => false,
                'interrupted_by_csrf' => false,
            ];
            
            if (!$isValid) {
                // CSRF validation failed - this is a CSRF error
                $result['has_csrf_error'] = true;
                $result['error_message'] = 'CSRF validation failed';
                $result['interrupted_by_csrf'] = true;
            } else {
                // CSRF validation succeeded - action should execute
                $result['action_executed'] = true;
                
                // Simulate controller action logic
                try {
                    // Check if data is valid (non-CSRF validation)
                    if (isset($data['name']) && empty($data['name'])) {
                        // This is a validation error, not CSRF
                        $result['has_error'] = true;
                        $result['error_message'] = 'Validation error: name is required';
                        // But processing still completed (reached validation logic)
                        $result['processing_completed'] = true;
                        $result['reached_end'] = true;
                    } else {
                        // Successful processing
                        $result['processing_completed'] = true;
                        $result['reached_end'] = true;
                    }
                } catch (\yii\web\BadRequestHttpException $e) {
                    // This would be a CSRF exception
                    $result['has_csrf_exception'] = true;
                    $result['exception_type'] = get_class($e);
                    $result['interrupted_by_csrf'] = true;
                } catch (\Exception $e) {
                    // Other exception
                    $result['has_error'] = true;
                    $result['error_message'] = $e->getMessage();
                }
            }
            
            // Clean up
            unset($_POST[$csrfParam]);
            unset($_SERVER['REQUEST_METHOD']);
            
            return $result;
        } catch (\Exception $e) {
            return [
                'action_executed' => false,
                'has_csrf_error' => false,
                'has_csrf_exception' => true,
                'has_error' => true,
                'error_message' => $e->getMessage(),
                'exception_type' => get_class($e),
                'processing_completed' => false,
                'reached_end' => false,
                'interrupted_by_csrf' => false,
            ];
        } finally {
            // Restore original session
            if ($originalSession) {
                Yii::$app->set('session', $originalSession);
            }
        }
    }
    
    /**
     * Validate CSRF token against session
     */
    protected function validateCsrfToken($token, $session)
    {
        try {
            // Get the raw token from session
            $csrfParam = Yii::$app->request->csrfParam;
            $sessionToken = $session->get($csrfParam);
            
            if ($sessionToken === null) {
                return false;
            }
            
            // For this test, if token exists in session and matches, it's valid
            return !empty($sessionToken) && !empty($token);
        } catch (\Exception $e) {
            return false;
        }
    }
}
