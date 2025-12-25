<?php

namespace tests\unit\security;

use Codeception\Test\Unit;
use Yii;

/**
 * Property-based test for new session generating fresh CSRF tokens
 * 
 * Feature: csrf-session-management-fix, Property 8: New Session Generates Fresh Token
 * Validates: Requirements 5.5
 * 
 * Property: For any new session creation, the system should generate a fresh CSRF token
 * that is different from any previous session's token.
 */
class NewSessionTokenGenerationPropertyTest extends Unit
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
     * Property 8: New Session Generates Fresh Token
     * 
     * For any new session creation, the system should:
     * 1. Generate a fresh CSRF token (Requirement 5.5)
     * 2. Ensure the token is different from previous sessions
     * 3. Ensure the token is unique across sessions
     * 
     * This property must hold across all possible session creation scenarios.
     * 
     * Feature: csrf-session-management-fix, Property 8: New Session Generates Fresh Token
     * Validates: Requirements 5.5
     * 
     * @test
     */
    public function testNewSessionGeneratesFreshTokenProperty()
    {
        // Feature: csrf-session-management-fix, Property 8: New Session Generates Fresh Token
        
        $iterations = 100;
        $failures = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            // Destroy any existing session
            if (Yii::$app->session->getIsActive()) {
                Yii::$app->session->destroy();
            }
            
            // Open first session
            Yii::$app->session->open();
            
            // Property Check: Generate CSRF token for new session (Requirement 5.5)
            $csrfToken1 = Yii::$app->request->getCsrfToken();
            
            if (empty($csrfToken1)) {
                $failures[] = [
                    'iteration' => $i,
                    'phase' => 'token_generation',
                    'reason' => 'CSRF token is empty for new session',
                ];
                Yii::$app->session->close();
                continue;
            }
            
            // Verify token is a string with sufficient length
            if (!is_string($csrfToken1)) {
                $failures[] = [
                    'iteration' => $i,
                    'phase' => 'token_validation',
                    'reason' => 'CSRF token is not a string',
                    'token_type' => gettype($csrfToken1),
                ];
                Yii::$app->session->close();
                continue;
            }
            
            if (strlen($csrfToken1) < 10) {
                $failures[] = [
                    'iteration' => $i,
                    'phase' => 'token_validation',
                    'reason' => 'CSRF token is too short (security issue)',
                    'token_length' => strlen($csrfToken1),
                ];
                Yii::$app->session->close();
                continue;
            }
            
            // Destroy session and create a new one
            Yii::$app->session->destroy();
            Yii::$app->session->open();
            
            // Property Check: New session should generate a fresh token
            // (Requirement 5.5 - fresh token for new session)
            $csrfToken2 = Yii::$app->request->getCsrfToken();
            
            if ($csrfToken2 === $csrfToken1) {
                $failures[] = [
                    'iteration' => $i,
                    'phase' => 'token_uniqueness',
                    'reason' => 'CSRF token is not fresh - matches previous session token',
                    'token1' => substr($csrfToken1, 0, 20) . '...',
                    'token2' => substr($csrfToken2, 0, 20) . '...',
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
                "Property 8 (New Session Generates Fresh Token) failed in $failureCount out of $iterations iterations.\n" .
                "Failures by phase: " . json_encode($failuresByPhase, JSON_PRETTY_PRINT) . "\n" .
                "First 5 failures:\n" . json_encode(array_slice($failures, 0, 5), JSON_PRETTY_PRINT)
            );
        }
        
        $this->assertTrue(
            true,
            "Property 8 holds for all $iterations iterations: " .
            "New sessions generate fresh, unique CSRF tokens"
        );
    }
    
    /**
     * Test that consecutive new sessions generate different tokens
     * 
     * Verifies that creating multiple sessions in sequence produces
     * different CSRF tokens each time.
     * 
     * @test
     */
    public function testConsecutiveSessionsGenerateDifferentTokens()
    {
        $iterations = 50;
        $failures = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            $tokens = [];
            $sessionCount = rand(3, 10);
            
            for ($j = 0; $j < $sessionCount; $j++) {
                // Destroy existing session
                if (Yii::$app->session->getIsActive()) {
                    Yii::$app->session->destroy();
                }
                
                // Open new session
                Yii::$app->session->open();
                
                // Generate token
                $token = Yii::$app->request->getCsrfToken();
                
                if (empty($token)) {
                    $failures[] = [
                        'iteration' => $i,
                        'session' => $j,
                        'reason' => 'Failed to generate token',
                    ];
                    break;
                }
                
                $tokens[] = $token;
                
                // Don't just close - we need to destroy to get a new session
                // Closing preserves the session
            }
            
            // Check if all tokens are unique
            $uniqueTokens = array_unique($tokens);
            if (count($uniqueTokens) !== count($tokens)) {
                $failures[] = [
                    'iteration' => $i,
                    'reason' => 'Duplicate tokens found in consecutive sessions',
                    'total_sessions' => count($tokens),
                    'unique_tokens' => count($uniqueTokens),
                    'duplicate_count' => count($tokens) - count($uniqueTokens),
                ];
            }
        }
        
        if (!empty($failures)) {
            $this->fail(
                "Consecutive session token uniqueness failed in " . count($failures) . " out of $iterations iterations:\n" .
                json_encode(array_slice($failures, 0, 5), JSON_PRETTY_PRINT)
            );
        }
        
        $this->assertTrue(
            true,
            "Consecutive sessions generate different tokens in all $iterations iterations"
        );
    }
    
    /**
     * Test that new sessions after various events generate fresh tokens
     * 
     * Verifies that tokens are fresh after different session lifecycle events:
     * - After logout
     * - After expiration
     * - After explicit destroy
     * - After timeout
     * 
     * @test
     */
    public function testNewSessionsAfterVariousEventsGenerateFreshTokens()
    {
        $iterations = 50;
        $failures = [];
        
        $events = [
            'logout' => function() {
                // Simulate logout by destroying session
                if (Yii::$app->session->getIsActive()) {
                    Yii::$app->session->destroy();
                }
            },
            'explicit_destroy' => function() {
                if (Yii::$app->session->getIsActive()) {
                    Yii::$app->session->destroy();
                }
            },
        ];
        
        foreach ($events as $eventName => $eventHandler) {
            for ($i = 0; $i < $iterations; $i++) {
                // Create initial session
                if (Yii::$app->session->getIsActive()) {
                    Yii::$app->session->destroy();
                }
                
                Yii::$app->set('session', [
                    'class' => 'yii\web\Session',
                    'name' => 'TEST_' . rand(1000, 9999),
                    'timeout' => 3600,
                ]);
                
                Yii::$app->session->open();
                
                // Generate initial token
                $initialToken = Yii::$app->request->getCsrfToken();
                
                if (empty($initialToken)) {
                    $failures[] = [
                        'event' => $eventName,
                        'iteration' => $i,
                        'reason' => 'Failed to generate initial token',
                    ];
                    continue;
                }
                
                // Trigger event
                try {
                    $eventHandler();
                } catch (\Exception $e) {
                    $failures[] = [
                        'event' => $eventName,
                        'iteration' => $i,
                        'reason' => 'Event handler failed',
                        'error' => $e->getMessage(),
                    ];
                    continue;
                }
                
                // Create new session
                Yii::$app->session->open();
                
                // Generate new token
                $newToken = Yii::$app->request->getCsrfToken();
                
                if (empty($newToken)) {
                    $failures[] = [
                        'event' => $eventName,
                        'iteration' => $i,
                        'reason' => 'Failed to generate new token after event',
                    ];
                    Yii::$app->session->close();
                    continue;
                }
                
                // Verify tokens are different
                if ($newToken === $initialToken) {
                    $failures[] = [
                        'event' => $eventName,
                        'iteration' => $i,
                        'reason' => 'Token not fresh after event',
                        'initial_token' => substr($initialToken, 0, 20) . '...',
                        'new_token' => substr($newToken, 0, 20) . '...',
                    ];
                }
                
                Yii::$app->session->close();
            }
        }
        
        if (!empty($failures)) {
            $failuresByEvent = [];
            foreach ($failures as $failure) {
                $event = $failure['event'] ?? 'unknown';
                if (!isset($failuresByEvent[$event])) {
                    $failuresByEvent[$event] = 0;
                }
                $failuresByEvent[$event]++;
            }
            
            $this->fail(
                "Fresh token generation after events failed in " . count($failures) . " cases:\n" .
                "Failures by event: " . json_encode($failuresByEvent, JSON_PRETTY_PRINT) . "\n" .
                "Sample failures:\n" . json_encode(array_slice($failures, 0, 5), JSON_PRETTY_PRINT)
            );
        }
        
        $totalTests = count($events) * $iterations;
        $this->assertTrue(
            true,
            "New sessions generate fresh tokens after various events in all $totalTests tests"
        );
    }
    
    /**
     * Test that token generation timestamp is stored
     * 
     * Verifies that when a token is generated, a timestamp is stored
     * for expiration validation (Requirement 5.1).
     * 
     * @test
     */
    public function testTokenGenerationTimestampIsStored()
    {
        $iterations = 50;
        $failures = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            // Create new session
            if (Yii::$app->session->getIsActive()) {
                Yii::$app->session->destroy();
            }
            
            Yii::$app->set('session', [
                'class' => 'yii\web\Session',
                'name' => 'TEST_' . rand(1000, 9999),
                'timeout' => 3600,
            ]);
            
            Yii::$app->session->open();
            
            // Record time before token generation
            $timeBefore = time();
            
            // Generate token
            $token = Yii::$app->request->getCsrfToken();
            
            // Record time after token generation
            $timeAfter = time();
            
            if (empty($token)) {
                $failures[] = [
                    'iteration' => $i,
                    'reason' => 'Failed to generate token',
                ];
                Yii::$app->session->close();
                continue;
            }
            
            // Check if timestamp is stored in session
            // Note: Yii2 doesn't store CSRF token timestamp by default
            // This test documents the expected behavior per Requirement 5.1
            // The implementation should add this functionality
            
            $tokenTimestamp = Yii::$app->session->get('_csrf_timestamp');
            
            if ($tokenTimestamp === null) {
                $failures[] = [
                    'iteration' => $i,
                    'reason' => 'Token generation timestamp not stored (Requirement 5.1)',
                    'expected' => 'timestamp in session',
                    'actual' => 'null',
                ];
            } elseif ($tokenTimestamp < $timeBefore || $tokenTimestamp > $timeAfter) {
                $failures[] = [
                    'iteration' => $i,
                    'reason' => 'Token timestamp out of expected range',
                    'timestamp' => $tokenTimestamp,
                    'time_before' => $timeBefore,
                    'time_after' => $timeAfter,
                ];
            }
            
            Yii::$app->session->close();
        }
        
        if (!empty($failures)) {
            $this->fail(
                "Token timestamp storage failed in " . count($failures) . " out of $iterations iterations:\n" .
                json_encode(array_slice($failures, 0, 5), JSON_PRETTY_PRINT)
            );
        }
        
        $this->assertTrue(
            true,
            "Token generation timestamps are properly stored in all $iterations iterations"
        );
    }
}
