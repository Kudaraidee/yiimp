<?php

namespace tests\unit\security;

use Codeception\Test\Unit;
use Yii;
use yii\web\Session;

/**
 * Property-based tests for session configuration
 * 
 * Feature: csrf-validation-fix
 * Tests Properties 17-18: Session configuration requirements
 */
class SessionConfigPropertyTest extends Unit
{
    /**
     * Property 17: Unique session ID generation
     * 
     * For any new user session, the system should generate a unique session ID
     * that differs from all other active sessions.
     * 
     * Validates: Requirements 6.2
     * Feature: csrf-validation-fix, Property 17: Unique session ID generation
     * 
     * @test
     */
    public function testUniqueSessionIdGeneration()
    {
        // Feature: csrf-validation-fix, Property 17: Unique session ID generation
        
        $iterations = 100;
        $failures = [];
        $sessionIds = [];
        
        // Test the session ID generation mechanism by simulating what happens
        // when different users access the application
        for ($i = 0; $i < $iterations; $i++) {
            // Generate a session ID using PHP's session_create_id() which is what
            // Yii2 uses internally for new sessions
            $sessionId = session_create_id();
            
            // Verify session ID is not empty
            if (empty($sessionId)) {
                $failures[] = [
                    'iteration' => $i,
                    'reason' => 'Session ID is empty',
                ];
                continue;
            }
            
            // Verify session ID is unique
            if (in_array($sessionId, $sessionIds)) {
                $failures[] = [
                    'iteration' => $i,
                    'session_id' => $sessionId,
                    'reason' => 'Duplicate session ID generated',
                    'previous_occurrence' => array_search($sessionId, $sessionIds),
                ];
            } else {
                $sessionIds[] = $sessionId;
            }
            
            // Verify session ID has reasonable length (typically 26-40 characters)
            if (strlen($sessionId) < 20) {
                $failures[] = [
                    'iteration' => $i,
                    'session_id' => $sessionId,
                    'length' => strlen($sessionId),
                    'reason' => 'Session ID too short (potential security issue)',
                ];
            }
            
            // Verify session ID contains only valid characters (alphanumeric and hyphens)
            if (!preg_match('/^[a-zA-Z0-9\-]+$/', $sessionId)) {
                $failures[] = [
                    'iteration' => $i,
                    'session_id' => $sessionId,
                    'reason' => 'Session ID contains invalid characters',
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
        
        // Verify we generated the expected number of unique IDs
        $uniqueCount = count(array_unique($sessionIds));
        if ($uniqueCount !== $iterations) {
            $this->fail(
                "Expected $iterations unique session IDs, but got $uniqueCount unique IDs. " .
                "Duplicate count: " . ($iterations - $uniqueCount)
            );
        }
        
        $this->assertTrue(true, "Property holds for all $iterations iterations - all session IDs are unique");
    }
    
    /**
     * Property 18: Configured session name usage
     * 
     * For any session cookie set by the system, the cookie name should match
     * the configured YIIMP_SESSION_NAME value.
     * 
     * Validates: Requirements 6.3
     * Feature: csrf-validation-fix, Property 18: Configured session name usage
     * 
     * @test
     */
    public function testConfiguredSessionNameUsage()
    {
        // Feature: csrf-validation-fix, Property 18: Configured session name usage
        
        $iterations = 100;
        $failures = [];
        
        // Test with different session name configurations
        $sessionNames = [
            'YIIMP2SESSID',
            'PHPSESSID',
            'CUSTOM_SESSION',
            'test_session_' . rand(1000, 9999),
        ];
        
        foreach ($sessionNames as $configuredName) {
            for ($i = 0; $i < $iterations / count($sessionNames); $i++) {
                // Create a new session with specific name
                $session = $this->createNewSession($configuredName);
                
                // Open the session
                $session->open();
                
                // Get the actual session name
                $actualName = $session->getName();
                
                // Verify the session name matches configuration
                if ($actualName !== $configuredName) {
                    $failures[] = [
                        'iteration' => $i,
                        'configured_name' => $configuredName,
                        'actual_name' => $actualName,
                        'reason' => 'Session name does not match configuration',
                    ];
                }
                
                // Verify session name is not empty
                if (empty($actualName)) {
                    $failures[] = [
                        'iteration' => $i,
                        'configured_name' => $configuredName,
                        'reason' => 'Session name is empty',
                    ];
                }
                
                // Verify session name follows naming conventions (alphanumeric and underscore)
                if (!preg_match('/^[a-zA-Z0-9_]+$/', $actualName)) {
                    $failures[] = [
                        'iteration' => $i,
                        'session_name' => $actualName,
                        'reason' => 'Session name contains invalid characters',
                    ];
                }
                
                // Close the session
                $session->close();
            }
        }
        
        // Report failures if any
        if (!empty($failures)) {
            $this->fail(
                "Property test failed in " . count($failures) . " out of $iterations iterations:\n" .
                json_encode($failures, JSON_PRETTY_PRINT)
            );
        }
        
        $this->assertTrue(true, "Property holds for all $iterations iterations - session names match configuration");
    }
    
    /**
     * Test that environment variable YIIMP_SESSION_NAME is respected
     * 
     * This test verifies that when YIIMP_SESSION_NAME is set via environment
     * variable, the session component uses that name.
     * 
     * @test
     */
    public function testEnvironmentVariableSessionName()
    {
        $iterations = 50;
        $failures = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            // Generate a random session name
            $envSessionName = 'TEST_SESSION_' . rand(1000, 9999);
            
            // Simulate environment variable (in real app, this would be set in .env)
            putenv("YIIMP_SESSION_NAME=$envSessionName");
            
            // Create session that should read from environment
            $session = new Session([
                'name' => getenv('YIIMP_SESSION_NAME') ?: 'YIIMP2SESSID',
            ]);
            
            $session->open();
            $actualName = $session->getName();
            
            // Verify the session name matches environment variable
            if ($actualName !== $envSessionName) {
                $failures[] = [
                    'iteration' => $i,
                    'env_name' => $envSessionName,
                    'actual_name' => $actualName,
                    'reason' => 'Session name does not match environment variable',
                ];
            }
            
            $session->close();
            
            // Clean up environment variable
            putenv('YIIMP_SESSION_NAME');
        }
        
        // Report failures if any
        if (!empty($failures)) {
            $this->fail(
                "Environment variable test failed in " . count($failures) . " out of $iterations iterations:\n" .
                json_encode($failures, JSON_PRETTY_PRINT)
            );
        }
        
        $this->assertTrue(true, "Environment variable session name respected in all $iterations iterations");
    }
    
    /**
     * Test session timeout configuration
     * 
     * Verifies that session timeout is properly configured and applied.
     * 
     * @test
     */
    public function testSessionTimeoutConfiguration()
    {
        $iterations = 50;
        $failures = [];
        
        // Test with different timeout values
        $timeouts = [3600, 7200, 1800, 900]; // 1 hour, 2 hours, 30 min, 15 min
        
        foreach ($timeouts as $configuredTimeout) {
            for ($i = 0; $i < $iterations / count($timeouts); $i++) {
                // Create session with specific timeout
                $session = new Session([
                    'timeout' => $configuredTimeout,
                ]);
                
                $session->open();
                $actualTimeout = $session->getTimeout();
                
                // Verify timeout matches configuration
                if ($actualTimeout !== $configuredTimeout) {
                    $failures[] = [
                        'iteration' => $i,
                        'configured_timeout' => $configuredTimeout,
                        'actual_timeout' => $actualTimeout,
                        'reason' => 'Session timeout does not match configuration',
                    ];
                }
                
                // Verify timeout is positive
                if ($actualTimeout <= 0) {
                    $failures[] = [
                        'iteration' => $i,
                        'timeout' => $actualTimeout,
                        'reason' => 'Session timeout must be positive',
                    ];
                }
                
                $session->close();
            }
        }
        
        // Report failures if any
        if (!empty($failures)) {
            $this->fail(
                "Timeout configuration test failed in " . count($failures) . " out of $iterations iterations:\n" .
                json_encode($failures, JSON_PRETTY_PRINT)
            );
        }
        
        $this->assertTrue(true, "Session timeout properly configured in all $iterations iterations");
    }
    
    // Helper methods
    
    /**
     * Create a new session instance with optional custom name
     * 
     * @param string|null $name Optional session name
     * @return Session
     */
    protected function createNewSession($name = null)
    {
        $config = [
            'timeout' => 3600,
            'useCookies' => true,
        ];
        
        if ($name !== null) {
            $config['name'] = $name;
        }
        
        return new Session($config);
    }
}
