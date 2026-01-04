<?php

namespace tests\unit\security;

use Codeception\Test\Unit;
use Yii;

/**
 * Property-based test for session expiration invalidating CSRF tokens
 * 
 * Feature: csrf-session-management-fix, Property 6: Session Expiration Invalidates Tokens
 * Validates: Requirements 5.3
 * 
 * Property: For any session, when the session expires, attempting to validate
 * the associated CSRF token should fail.
 */
class SessionExpirationPropertyTest extends Unit
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
     * Property 6: Session Expiration Invalidates Tokens
     * 
     * For any session, when the session expires, attempting to validate
     * the associated CSRF token should fail (Requirement 5.3).
     * 
     * This property must hold across all possible session scenarios.
     * 
     * We test this by simulating session expiration through session destruction
     * rather than waiting for actual timeout, which would make tests too slow.
     * 
     * Feature: csrf-session-management-fix, Property 6: Session Expiration Invalidates Tokens
     * Validates: Requirements 5.3
     * 
     * @test
     */
    public function testSessionExpirationInvalidatesTokensProperty()
    {
        // Feature: csrf-session-management-fix, Property 6: Session Expiration Invalidates Tokens
        
        $iterations = 100;
        $failures = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            // Start fresh session
            if (Yii::$app->session->getIsActive()) {
                Yii::$app->session->destroy();
            }
            
            Yii::$app->session->open();
            
            // Generate CSRF token
            $csrfToken = Yii::$app->request->getCsrfToken();
            
            if (empty($csrfToken)) {
                $failures[] = [
                    'iteration' => $i,
                    'phase' => 'token_generation',
                    'reason' => 'CSRF token is empty',
                ];
                Yii::$app->session->close();
                continue;
            }
            
            // Store some test data to verify it's cleared
            $testKey = 'test_data_' . $i;
            $testValue = 'test_value_' . rand(1000, 9999);
            Yii::$app->session->set($testKey, $testValue);
            
            // Store session ID for verification
            $sessionId = Yii::$app->session->getId();
            
            // Property Check: Simulate session expiration by destroying the session
            // This is equivalent to session timeout - all data should be cleared
            // (Requirement 5.3)
            Yii::$app->session->destroy();
            
            // Start a new session (simulating a new request after expiration)
            Yii::$app->session->open();
            
            // Verify that the old session data is gone
            $retrievedData = Yii::$app->session->get($testKey);
            if ($retrievedData !== null) {
                $failures[] = [
                    'iteration' => $i,
                    'phase' => 'data_invalidation',
                    'reason' => 'Session data persisted after expiration',
                    'test_key' => $testKey,
                    'expected' => null,
                    'actual' => $retrievedData,
                ];
            }
            
            // Verify that a new CSRF token is generated (different from old one)
            $newToken = Yii::$app->request->getCsrfToken();
            
            if (empty($newToken)) {
                $failures[] = [
                    'iteration' => $i,
                    'phase' => 'new_token_generation',
                    'reason' => 'Failed to generate new CSRF token after expiration',
                ];
            } elseif ($newToken === $csrfToken) {
                $failures[] = [
                    'iteration' => $i,
                    'phase' => 'token_invalidation',
                    'reason' => 'CSRF token remained the same after session expiration',
                    'original_token' => substr($csrfToken, 0, 20) . '...',
                    'new_token' => substr($newToken, 0, 20) . '...',
                ];
            }
            
            // Verify session ID changed (or at least token changed)
            $newSessionId = Yii::$app->session->getId();
            // Note: Session ID might not change in file-based sessions, but token should
            
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
                "Property 6 (Session Expiration Invalidates Tokens) failed in $failureCount out of $iterations iterations.\n" .
                "Failures by phase: " . json_encode($failuresByPhase, JSON_PRETTY_PRINT) . "\n" .
                "First 5 failures:\n" . json_encode(array_slice($failures, 0, 5), JSON_PRETTY_PRINT)
            );
        }
        
        $this->assertTrue(
            true,
            "Property 6 holds for all $iterations iterations: " .
            "Session expiration properly invalidates CSRF tokens"
        );
    }
    
    /**
     * Test that expired sessions create new CSRF tokens
     * 
     * Verifies that after session expiration, a new token is generated
     * rather than reusing the old one.
     * 
     * @test
     */
    public function testExpiredSessionsGenerateNewTokens()
    {
        $iterations = 50;
        $failures = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            // Start fresh session
            if (Yii::$app->session->getIsActive()) {
                Yii::$app->session->destroy();
            }
            
            Yii::$app->session->open();
            
            // Generate initial token
            $initialToken = Yii::$app->request->getCsrfToken();
            
            if (empty($initialToken)) {
                $failures[] = [
                    'iteration' => $i,
                    'reason' => 'Failed to generate initial token',
                ];
                Yii::$app->session->close();
                continue;
            }
            
            // Simulate expiration by destroying session
            Yii::$app->session->destroy();
            
            // Start new session
            Yii::$app->session->open();
            
            // Get new token
            $newToken = Yii::$app->request->getCsrfToken();
            
            // Tokens should be different (new session created)
            if ($newToken === $initialToken) {
                $failures[] = [
                    'iteration' => $i,
                    'reason' => 'Token did not change after session expiration',
                    'initial_token' => substr($initialToken, 0, 20) . '...',
                    'new_token' => substr($newToken, 0, 20) . '...',
                ];
            }
            
            Yii::$app->session->close();
        }
        
        if (!empty($failures)) {
            $this->fail(
                "New token generation after expiration failed in " . count($failures) . " out of $iterations iterations:\n" .
                json_encode(array_slice($failures, 0, 5), JSON_PRETTY_PRINT)
            );
        }
        
        $this->assertTrue(
            true,
            "Expired sessions generate new tokens in all $iterations iterations"
        );
    }
    
    /**
     * Test that session expiration clears all session data including CSRF token
     * 
     * Verifies that when a session expires, all data including the CSRF token
     * is properly cleared from storage.
     * 
     * @test
     */
    public function testSessionExpirationClearsAllData()
    {
        $iterations = 50;
        $failures = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            // Start fresh session
            if (Yii::$app->session->getIsActive()) {
                Yii::$app->session->destroy();
            }
            
            Yii::$app->session->open();
            
            // Store some data and generate token
            $testKey = 'test_key_' . $i;
            $testValue = 'test_value_' . rand(1000, 9999);
            Yii::$app->session->set($testKey, $testValue);
            
            $csrfToken = Yii::$app->request->getCsrfToken();
            
            // Simulate expiration by destroying session
            Yii::$app->session->destroy();
            
            // Start new session
            Yii::$app->session->open();
            
            // Check if data was cleared
            $retrievedValue = Yii::$app->session->get($testKey);
            
            if ($retrievedValue === $testValue) {
                $failures[] = [
                    'iteration' => $i,
                    'reason' => 'Session data not cleared after expiration',
                    'test_key' => $testKey,
                    'expected' => null,
                    'actual' => $retrievedValue,
                ];
            }
            
            // Check if CSRF token was cleared (new token should be different)
            $newToken = Yii::$app->request->getCsrfToken();
            if ($newToken === $csrfToken) {
                $failures[] = [
                    'iteration' => $i,
                    'reason' => 'CSRF token not cleared after expiration',
                ];
            }
            
            Yii::$app->session->close();
        }
        
        if (!empty($failures)) {
            $this->fail(
                "Session data clearing after expiration failed in " . count($failures) . " out of $iterations iterations:\n" .
                json_encode(array_slice($failures, 0, 5), JSON_PRETTY_PRINT)
            );
        }
        
        $this->assertTrue(
            true,
            "Session expiration clears all data in all $iterations iterations"
        );
    }
}
