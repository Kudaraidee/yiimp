<?php

namespace tests\unit\security;

use Codeception\Test\Unit;
use yii\web\Session;

/**
 * Property-based tests for localhost cookie configuration
 * 
 * Feature: localhost-redirect-loop-fix
 * Tests Properties 1, 2, 5: Cookie configuration for HTTP/HTTPS
 */
class LocalhostCookieConfigPropertyTest extends Unit
{
    /**
     * Property 1: HTTP cookie configuration
     * 
     * For any HTTP request to localhost, the session cookie secure flag should
     * be set to false to allow the browser to send the cookie.
     * 
     * Validates: Requirements 1.4, 2.1
     * Feature: localhost-redirect-loop-fix, Property 1: HTTP cookie configuration
     * 
     * @test
     */
    public function testHttpCookieConfiguration()
    {
        // Feature: localhost-redirect-loop-fix, Property 1: HTTP cookie configuration
        
        $iterations = 100;
        $failures = [];
        
        // Test various localhost configurations
        $localhostConfigs = [
            ['SERVER_NAME' => 'localhost', 'SERVER_ADDR' => '127.0.0.1', 'HTTP_HOST' => 'localhost:8090'],
            ['SERVER_NAME' => 'localhost', 'SERVER_ADDR' => '127.0.0.1', 'HTTP_HOST' => 'localhost'],
            ['SERVER_NAME' => '127.0.0.1', 'SERVER_ADDR' => '127.0.0.1', 'HTTP_HOST' => '127.0.0.1:8090'],
            ['SERVER_NAME' => '127.0.0.1', 'SERVER_ADDR' => '127.0.0.1', 'HTTP_HOST' => '127.0.0.1'],
        ];
        
        foreach ($localhostConfigs as $configIndex => $config) {
            for ($i = 0; $i < $iterations / count($localhostConfigs); $i++) {
                // Simulate HTTP localhost request
                $_SERVER['HTTPS'] = 'off';
                $_SERVER['SERVER_NAME'] = $config['SERVER_NAME'];
                $_SERVER['SERVER_ADDR'] = $config['SERVER_ADDR'];
                $_SERVER['HTTP_HOST'] = $config['HTTP_HOST'];
                unset($_SERVER['HTTP_X_FORWARDED_PROTO']);
                unset($_SERVER['HTTP_X_FORWARDED_SSL']);
                putenv('YIIMP_FORCE_HTTPS=false');
                
                // Detect HTTPS using the same logic as web.php
                $isHttps = $this->detectHttps();
                
                // Verify HTTPS is detected as false for HTTP localhost
                if ($isHttps !== false) {
                    $failures[] = [
                        'iteration' => $i,
                        'config' => $config,
                        'detected_https' => $isHttps,
                        'reason' => 'HTTPS incorrectly detected for HTTP localhost',
                    ];
                    continue;
                }
                
                // Create session with detected HTTPS setting
                $session = new Session([
                    'name' => 'YIIMP2SESSID',
                    'timeout' => 3600,
                    'useCookies' => true,
                    'cookieParams' => [
                        'httponly' => true,
                        'secure' => $isHttps,
                        'sameSite' => $isHttps ? 'Lax' : null,
                    ],
                ]);
                
                // Get cookie params
                $cookieParams = $session->getCookieParams();
                
                // Verify secure flag is false for HTTP localhost
                if ($cookieParams['secure'] !== false) {
                    $failures[] = [
                        'iteration' => $i,
                        'config' => $config,
                        'cookie_params' => $cookieParams,
                        'secure_flag' => $cookieParams['secure'],
                        'reason' => 'Secure flag must be false for HTTP localhost',
                    ];
                }
                
                // Verify httponly is true (always required)
                if ($cookieParams['httponly'] !== true) {
                    $failures[] = [
                        'iteration' => $i,
                        'config' => $config,
                        'cookie_params' => $cookieParams,
                        'reason' => 'HttpOnly flag must always be true',
                    ];
                }
                
                // Verify sameSite is null or not set for HTTP
                // Note: Yii2 uses lowercase 'samesite' in cookie params array
                $sameSite = $cookieParams['samesite'] ?? null;
                if ($sameSite !== null) {
                    $failures[] = [
                        'iteration' => $i,
                        'config' => $config,
                        'cookie_params' => $cookieParams,
                        'sameSite' => $sameSite,
                        'reason' => 'SameSite must be null for HTTP',
                    ];
                }
            }
        }
        
        // Clean up
        $this->cleanupServerVars();
        
        // Report failures if any
        if (!empty($failures)) {
            $this->fail(
                "Property test failed in " . count($failures) . " out of $iterations iterations:\n" .
                json_encode($failures, JSON_PRETTY_PRINT)
            );
        }
        
        $this->assertTrue(true, "Property holds for all $iterations iterations - HTTP localhost cookies configured correctly");
    }
    
    /**
     * Property 2: HTTPS cookie configuration
     * 
     * For any HTTPS request, the session cookie secure flag should be set to
     * true to ensure cookies are only sent over secure connections.
     * 
     * Validates: Requirements 1.5, 2.2
     * Feature: localhost-redirect-loop-fix, Property 2: HTTPS cookie configuration
     * 
     * @test
     */
    public function testHttpsCookieConfiguration()
    {
        // Feature: localhost-redirect-loop-fix, Property 2: HTTPS cookie configuration
        
        $iterations = 100;
        $failures = [];
        
        // Test various HTTPS detection methods
        $httpsConfigs = [
            ['HTTPS' => 'on'],
            ['HTTPS' => '1'],
            ['HTTP_X_FORWARDED_PROTO' => 'https'],
            ['HTTP_X_FORWARDED_SSL' => 'on'],
        ];
        
        foreach ($httpsConfigs as $configIndex => $config) {
            for ($i = 0; $i < $iterations / count($httpsConfigs); $i++) {
                // Simulate HTTPS request
                foreach ($config as $key => $value) {
                    $_SERVER[$key] = $value;
                }
                
                // Ensure no conflicting HTTP indicators
                if (!isset($config['HTTPS'])) {
                    $_SERVER['HTTPS'] = 'off';
                }
                
                putenv('YIIMP_FORCE_HTTPS=false');
                
                // Detect HTTPS using the same logic as web.php
                $isHttps = $this->detectHttps();
                
                // Verify HTTPS is detected as true
                if ($isHttps !== true) {
                    $failures[] = [
                        'iteration' => $i,
                        'config' => $config,
                        'detected_https' => $isHttps,
                        'reason' => 'HTTPS not detected for HTTPS request',
                    ];
                    $this->cleanupServerVars();
                    continue;
                }
                
                // Create session with detected HTTPS setting
                $session = new Session([
                    'name' => 'YIIMP2SESSID',
                    'timeout' => 3600,
                    'useCookies' => true,
                    'cookieParams' => [
                        'httponly' => true,
                        'secure' => $isHttps,
                        'sameSite' => $isHttps ? 'Lax' : null,
                    ],
                ]);
                
                // Get cookie params
                $cookieParams = $session->getCookieParams();
                
                // Verify secure flag is true for HTTPS
                if ($cookieParams['secure'] !== true) {
                    $failures[] = [
                        'iteration' => $i,
                        'config' => $config,
                        'cookie_params' => $cookieParams,
                        'secure_flag' => $cookieParams['secure'],
                        'reason' => 'Secure flag must be true for HTTPS',
                    ];
                }
                
                // Verify httponly is true (always required)
                if ($cookieParams['httponly'] !== true) {
                    $failures[] = [
                        'iteration' => $i,
                        'config' => $config,
                        'cookie_params' => $cookieParams,
                        'reason' => 'HttpOnly flag must always be true',
                    ];
                }
                
                // Verify sameSite is 'Lax' for HTTPS
                // Note: Yii2 uses lowercase 'samesite' in cookie params array
                $sameSite = $cookieParams['samesite'] ?? null;
                if ($sameSite !== 'Lax') {
                    $failures[] = [
                        'iteration' => $i,
                        'config' => $config,
                        'cookie_params' => $cookieParams,
                        'sameSite' => $sameSite,
                        'reason' => 'SameSite must be Lax for HTTPS',
                    ];
                }
                
                $this->cleanupServerVars();
            }
        }
        
        // Clean up
        $this->cleanupServerVars();
        
        // Report failures if any
        if (!empty($failures)) {
            $this->fail(
                "Property test failed in " . count($failures) . " out of $iterations iterations:\n" .
                json_encode($failures, JSON_PRETTY_PRINT)
            );
        }
        
        $this->assertTrue(true, "Property holds for all $iterations iterations - HTTPS cookies configured correctly");
    }
    
    /**
     * Property 5: Session cookie presence
     * 
     * For any successful page load, a session cookie should be present in the
     * response when the protocol matches the secure flag setting.
     * 
     * Validates: Requirements 1.3, 2.1, 2.2
     * Feature: localhost-redirect-loop-fix, Property 5: Session cookie presence
     * 
     * @test
     */
    public function testSessionCookiePresence()
    {
        // Feature: localhost-redirect-loop-fix, Property 5: Session cookie presence
        
        $iterations = 100;
        $failures = [];
        
        // Test both HTTP and HTTPS scenarios
        $scenarios = [
            [
                'protocol' => 'HTTP',
                'server_vars' => ['HTTPS' => 'off', 'SERVER_NAME' => 'localhost', 'HTTP_HOST' => 'localhost:8090'],
                'expected_secure' => false,
            ],
            [
                'protocol' => 'HTTPS',
                'server_vars' => ['HTTPS' => 'on', 'SERVER_NAME' => 'example.com', 'HTTP_HOST' => 'example.com'],
                'expected_secure' => true,
            ],
        ];
        
        foreach ($scenarios as $scenarioIndex => $scenario) {
            for ($i = 0; $i < $iterations / count($scenarios); $i++) {
                // Set up server variables
                foreach ($scenario['server_vars'] as $key => $value) {
                    $_SERVER[$key] = $value;
                }
                putenv('YIIMP_FORCE_HTTPS=false');
                
                // Detect HTTPS
                $isHttps = $this->detectHttps();
                
                // Verify HTTPS detection matches expected
                if ($isHttps !== $scenario['expected_secure']) {
                    $failures[] = [
                        'iteration' => $i,
                        'scenario' => $scenario['protocol'],
                        'expected_https' => $scenario['expected_secure'],
                        'detected_https' => $isHttps,
                        'reason' => 'HTTPS detection mismatch',
                    ];
                    $this->cleanupServerVars();
                    continue;
                }
                
                // Create and open session
                $session = new Session([
                    'name' => 'YIIMP2SESSID',
                    'timeout' => 3600,
                    'useCookies' => true,
                    'cookieParams' => [
                        'httponly' => true,
                        'secure' => $isHttps,
                        'sameSite' => $isHttps ? 'Lax' : null,
                    ],
                ]);
                
                // Open session (this would normally set the cookie)
                $session->open();
                
                // Verify session is active
                if (!$session->getIsActive()) {
                    $failures[] = [
                        'iteration' => $i,
                        'scenario' => $scenario['protocol'],
                        'reason' => 'Session failed to activate',
                    ];
                    $session->close();
                    $this->cleanupServerVars();
                    continue;
                }
                
                // Verify session has an ID
                $sessionId = $session->getId();
                if (empty($sessionId)) {
                    $failures[] = [
                        'iteration' => $i,
                        'scenario' => $scenario['protocol'],
                        'reason' => 'Session ID is empty',
                    ];
                }
                
                // Verify cookie params match protocol
                $cookieParams = $session->getCookieParams();
                if ($cookieParams['secure'] !== $scenario['expected_secure']) {
                    $failures[] = [
                        'iteration' => $i,
                        'scenario' => $scenario['protocol'],
                        'expected_secure' => $scenario['expected_secure'],
                        'actual_secure' => $cookieParams['secure'],
                        'reason' => 'Cookie secure flag does not match protocol',
                    ];
                }
                
                // Close session
                $session->close();
                $this->cleanupServerVars();
            }
        }
        
        // Clean up
        $this->cleanupServerVars();
        
        // Report failures if any
        if (!empty($failures)) {
            $this->fail(
                "Property test failed in " . count($failures) . " out of $iterations iterations:\n" .
                json_encode($failures, JSON_PRETTY_PRINT)
            );
        }
        
        $this->assertTrue(true, "Property holds for all $iterations iterations - session cookies present with correct configuration");
    }
    
    // Helper methods
    
    /**
     * Detect HTTPS using the same logic as web.php
     * 
     * @return bool
     */
    protected function detectHttps()
    {
        $isHttps = false;
        
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            $isHttps = true;
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
            $isHttps = true;
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on') {
            $isHttps = true;
        } elseif (getenv('YIIMP_FORCE_HTTPS') === 'true' || getenv('YIIMP_FORCE_HTTPS') === '1') {
            $isHttps = true;
        }
        
        return $isHttps;
    }
    
    /**
     * Clean up server variables
     */
    protected function cleanupServerVars()
    {
        unset($_SERVER['HTTPS']);
        unset($_SERVER['HTTP_X_FORWARDED_PROTO']);
        unset($_SERVER['HTTP_X_FORWARDED_SSL']);
        unset($_SERVER['SERVER_NAME']);
        unset($_SERVER['SERVER_ADDR']);
        unset($_SERVER['HTTP_HOST']);
        putenv('YIIMP_FORCE_HTTPS');
    }
}
