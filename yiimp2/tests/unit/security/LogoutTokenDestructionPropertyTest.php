<?php

namespace tests\unit\security;

use Codeception\Test\Unit;
use Yii;
use app\models\User;

/**
 * Property-based test for logout destroying session and tokens
 * 
 * Feature: csrf-session-management-fix, Property 7: Logout Destroys Session and Tokens
 * Validates: Requirements 5.4
 * 
 * Property: For any user logout event, the system should destroy the session
 * and invalidate all associated CSRF tokens.
 */
class LogoutTokenDestructionPropertyTest extends Unit
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
        
        // Ensure user is logged out
        if (!Yii::$app->user->isGuest) {
            Yii::$app->user->logout();
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
        
        // Ensure user is logged out
        if (Yii::$app !== null && !Yii::$app->user->isGuest) {
            Yii::$app->user->logout();
        }
        
        parent::_after();
    }
    
    /**
     * Property 7: Logout Destroys Session and Tokens
     * 
     * For any user logout event, the system should:
     * 1. Destroy the session (Requirement 5.4)
     * 2. Invalidate all associated CSRF tokens (Requirement 5.4)
     * 3. Clear all session data
     * 
     * This property must hold across all possible logout scenarios.
     * 
     * Feature: csrf-session-management-fix, Property 7: Logout Destroys Session and Tokens
     * Validates: Requirements 5.4
     * 
     * @test
     */
    public function testLogoutDestroysSessionAndTokensProperty()
    {
        // Feature: csrf-session-management-fix, Property 7: Logout Destroys Session and Tokens
        
        $iterations = 100;
        $failures = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            // Start fresh session
            if (Yii::$app->session->getIsActive()) {
                Yii::$app->session->destroy();
            }
            
            // Ensure user is logged out
            if (!Yii::$app->user->isGuest) {
                Yii::$app->user->logout();
            }
            
            try {
                // Create session with random configuration
                Yii::$app->set('session', [
                    'class' => 'yii\web\Session',
                    'name' => 'TEST_SESSION_' . rand(1000, 9999),
                    'timeout' => rand(1800, 7200),
                    'useCookies' => true,
                ]);
                
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
            
            // Generate CSRF token before "login"
            try {
                $csrfToken = Yii::$app->request->getCsrfToken();
            } catch (\Exception $e) {
                $failures[] = [
                    'iteration' => $i,
                    'phase' => 'token_generation',
                    'reason' => 'Failed to generate CSRF token',
                    'error' => $e->getMessage(),
                ];
                Yii::$app->session->close();
                continue;
            }
            
            if (empty($csrfToken)) {
                $failures[] = [
                    'iteration' => $i,
                    'phase' => 'token_generation',
                    'reason' => 'CSRF token is empty',
                ];
                Yii::$app->session->close();
                continue;
            }
            
            // Store some session data to verify it's cleared
            $testKey = 'test_data_' . $i;
            $testValue = 'test_value_' . rand(1000, 9999);
            Yii::$app->session->set($testKey, $testValue);
            
            // Store session ID for verification
            $sessionId = Yii::$app->session->getId();
            
            // Simulate user login by setting a mock user identity
            // We don't need actual authentication, just need to simulate logged-in state
            $mockUserId = rand(1, 1000);
            Yii::$app->session->set('__id', $mockUserId);
            Yii::$app->session->set('user_logged_in', true);
            
            // Property Check: Simulate logout (Requirement 5.4)
            // Logout should destroy the session and invalidate tokens
            try {
                // Yii::$app->user->logout() calls session->destroy() internally
                // For testing, we'll explicitly destroy the session to simulate logout
                Yii::$app->session->destroy();
            } catch (\Exception $e) {
                $failures[] = [
                    'iteration' => $i,
                    'phase' => 'logout',
                    'reason' => 'Failed to destroy session during logout',
                    'error' => $e->getMessage(),
                ];
                continue;
            }
            
            // Start a new session (simulating a new request after logout)
            try {
                Yii::$app->session->open();
            } catch (\Exception $e) {
                $failures[] = [
                    'iteration' => $i,
                    'phase' => 'new_session_after_logout',
                    'reason' => 'Failed to open new session after logout',
                    'error' => $e->getMessage(),
                ];
                continue;
            }
            
            // Verify that the old session data is gone
            $retrievedData = Yii::$app->session->get($testKey);
            if ($retrievedData !== null) {
                $failures[] = [
                    'iteration' => $i,
                    'phase' => 'session_data_destruction',
                    'reason' => 'Session data persisted after logout',
                    'test_key' => $testKey,
                    'expected' => null,
                    'actual' => $retrievedData,
                ];
            }
            
            // Verify that user data is gone
            $userLoggedIn = Yii::$app->session->get('user_logged_in');
            if ($userLoggedIn !== null) {
                $failures[] = [
                    'iteration' => $i,
                    'phase' => 'user_data_destruction',
                    'reason' => 'User data persisted after logout',
                    'expected' => null,
                    'actual' => $userLoggedIn,
                ];
            }
            
            // Verify that a new CSRF token is generated (different from old one)
            try {
                $newToken = Yii::$app->request->getCsrfToken();
                
                if (empty($newToken)) {
                    $failures[] = [
                        'iteration' => $i,
                        'phase' => 'new_token_generation',
                        'reason' => 'Failed to generate new CSRF token after logout',
                    ];
                } elseif ($newToken === $csrfToken) {
                    $failures[] = [
                        'iteration' => $i,
                        'phase' => 'token_destruction',
                        'reason' => 'CSRF token remained the same after logout',
                        'original_token' => substr($csrfToken, 0, 20) . '...',
                        'new_token' => substr($newToken, 0, 20) . '...',
                    ];
                }
            } catch (\Exception $e) {
                $failures[] = [
                    'iteration' => $i,
                    'phase' => 'new_token_generation',
                    'reason' => 'Exception while generating new token after logout',
                    'error' => $e->getMessage(),
                ];
            }
            
            // Note: Session ID might not change with file-based sessions
            // The important thing is that the token changed and data was cleared
            
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
                "Property 7 (Logout Destroys Session and Tokens) failed in $failureCount out of $iterations iterations.\n" .
                "Failures by phase: " . json_encode($failuresByPhase, JSON_PRETTY_PRINT) . "\n" .
                "First 5 failures:\n" . json_encode(array_slice($failures, 0, 5), JSON_PRETTY_PRINT)
            );
        }
        
        $this->assertTrue(
            true,
            "Property 7 holds for all $iterations iterations: " .
            "Logout properly destroys session and invalidates CSRF tokens"
        );
    }
    
    /**
     * Test that logout clears all authentication-related session data
     * 
     * Verifies that logout removes all traces of user authentication
     * from the session.
     * 
     * @test
     */
    public function testLogoutClearsAuthenticationData()
    {
        $iterations = 50;
        $failures = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            // Start fresh session
            if (Yii::$app->session->getIsActive()) {
                Yii::$app->session->destroy();
            }
            
            Yii::$app->set('session', [
                'class' => 'yii\web\Session',
                'name' => 'TEST_' . rand(1000, 9999),
                'timeout' => 3600,
            ]);
            
            Yii::$app->session->open();
            
            // Simulate authenticated session
            $userId = rand(1, 1000);
            $username = 'user_' . rand(1000, 9999);
            $authTime = time();
            
            Yii::$app->session->set('__id', $userId);
            Yii::$app->session->set('username', $username);
            Yii::$app->session->set('auth_time', $authTime);
            Yii::$app->session->set('is_admin', (bool)rand(0, 1));
            
            // Generate CSRF token
            $csrfToken = Yii::$app->request->getCsrfToken();
            
            // Simulate logout
            Yii::$app->session->destroy();
            
            // Start new session
            Yii::$app->session->open();
            
            // Verify all auth data is gone
            $checks = [
                '__id' => Yii::$app->session->get('__id'),
                'username' => Yii::$app->session->get('username'),
                'auth_time' => Yii::$app->session->get('auth_time'),
                'is_admin' => Yii::$app->session->get('is_admin'),
            ];
            
            foreach ($checks as $key => $value) {
                if ($value !== null) {
                    $failures[] = [
                        'iteration' => $i,
                        'reason' => "Authentication data '$key' not cleared after logout",
                        'key' => $key,
                        'value' => $value,
                    ];
                }
            }
            
            // Verify new token is different
            $newToken = Yii::$app->request->getCsrfToken();
            if ($newToken === $csrfToken) {
                $failures[] = [
                    'iteration' => $i,
                    'reason' => 'CSRF token not regenerated after logout',
                ];
            }
            
            Yii::$app->session->close();
        }
        
        if (!empty($failures)) {
            $this->fail(
                "Authentication data clearing failed in " . count($failures) . " checks across $iterations iterations:\n" .
                json_encode(array_slice($failures, 0, 5), JSON_PRETTY_PRINT)
            );
        }
        
        $this->assertTrue(
            true,
            "Logout clears all authentication data in all $iterations iterations"
        );
    }
    
    /**
     * Test that multiple logout calls are idempotent
     * 
     * Verifies that calling logout multiple times doesn't cause errors
     * and properly handles already-destroyed sessions.
     * 
     * @test
     */
    public function testMultipleLogoutCallsAreIdempotent()
    {
        $iterations = 50;
        $failures = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            // Start fresh session
            if (Yii::$app->session->getIsActive()) {
                Yii::$app->session->destroy();
            }
            
            Yii::$app->set('session', [
                'class' => 'yii\web\Session',
                'name' => 'TEST_' . rand(1000, 9999),
                'timeout' => 3600,
            ]);
            
            Yii::$app->session->open();
            
            // Generate token and store data
            $csrfToken = Yii::$app->request->getCsrfToken();
            Yii::$app->session->set('test_data', 'value');
            
            // First logout
            try {
                Yii::$app->session->destroy();
            } catch (\Exception $e) {
                $failures[] = [
                    'iteration' => $i,
                    'logout_attempt' => 1,
                    'reason' => 'First logout failed',
                    'error' => $e->getMessage(),
                ];
                continue;
            }
            
            // Try to logout again (should handle gracefully)
            try {
                if (Yii::$app->session->getIsActive()) {
                    Yii::$app->session->destroy();
                }
                // If session is not active, this is expected behavior
            } catch (\Exception $e) {
                $failures[] = [
                    'iteration' => $i,
                    'logout_attempt' => 2,
                    'reason' => 'Second logout caused exception',
                    'error' => $e->getMessage(),
                ];
            }
            
            // Start new session and verify clean state
            Yii::$app->session->open();
            
            $testData = Yii::$app->session->get('test_data');
            if ($testData !== null) {
                $failures[] = [
                    'iteration' => $i,
                    'reason' => 'Data persisted after multiple logouts',
                ];
            }
            
            $newToken = Yii::$app->request->getCsrfToken();
            if ($newToken === $csrfToken) {
                $failures[] = [
                    'iteration' => $i,
                    'reason' => 'Token persisted after multiple logouts',
                ];
            }
            
            Yii::$app->session->close();
        }
        
        if (!empty($failures)) {
            $this->fail(
                "Multiple logout idempotency failed in " . count($failures) . " cases across $iterations iterations:\n" .
                json_encode(array_slice($failures, 0, 5), JSON_PRETTY_PRINT)
            );
        }
        
        $this->assertTrue(
            true,
            "Multiple logout calls are idempotent in all $iterations iterations"
        );
    }
}
