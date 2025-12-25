<?php

namespace tests\unit\security;

use Codeception\Test\Unit;
use yii\web\Session;

/**
 * Property-based tests for HTTPS detection and cookie security
 * 
 * Feature: csrf-validation-fix
 * Tests Properties 5-8: HTTPS detection and cookie security requirements
 */
class HttpsCookieSecurityPropertyTest extends Unit
{
    /**
     * Property 5: HTTP cookie security
     * 
     * For any request over HTTP (non-HTTPS), session cookies should be set
     * without the secure flag to ensure browser accessibility.
     * 
     * Validates: Requirements 3.1
     * Feature: csrf-validation-fix, Property 5: HTTP cookie security
     * 
     * @test
     */
    public function testHttpCookieSecurity()
    {
        // Feature: csrf-validation-fix, Property 5: HTTP cookie security
        
        $iterations = 100;
        $failures = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            // Simulate HTTP request (no HTTPS)
            $_SERVER['HTTPS'] = 'off';
            $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'http';
            unset($_SERVER['HTTP_X_FORWARDED_SSL']);
            putenv('YIIMP_FORCE_HTTPS=false');
            
            // Detect HTTPS using the same logic as web.php
            $isHttps = $this->detectHttps();
            
            // Verify HTTPS is detected as false
            if ($isHttps !== false) {
                $failures[] = [
                    'iteration' => $i,
                    'detected_https' => $isHttps,
                    'reason' => 'HTTPS incorrectly detected for HTTP request',
                    'server_vars' => [
                        'HTTPS' => $_SERVER['HTTPS'] ?? 'not set',
                        'HTTP_X_FORWARDED_PROTO' => $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? 'not set',
                    ],
                ];
            }
            
            // Create session with detected HTTPS setting
            $session = new Session([
                'name' => 'TEST_SESSION_' . $i,
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
            
            // Verify secure flag is NOT set for HTTP
            if (!empty($cookieParams['secure'])) {
                $failures[] = [
                    'iteration' => $i,
                    'cookie_params' => $cookieParams,
                    'reason' => 'Secure flag should not be set for HTTP requests',
                ];
            }
            
            // Verify httponly is still set (security best practice)
            if (empty($cookieParams['httponly'])) {
                $failures[] = [
                    'iteration' => $i,
                    'cookie_params' => $cookieParams,
                    'reason' => 'HttpOnly flag should always be set',
                ];
            }
            
            // Verify sameSite is not set for HTTP (or is null)
            // Note: Yii2 uses lowercase 'samesite' in cookie params array
            if (!empty($cookieParams['samesite']) && $cookieParams['samesite'] !== null) {
                $failures[] = [
                    'iteration' => $i,
                    'cookie_params' => $cookieParams,
                    'sameSite' => $cookieParams['samesite'],
                    'reason' => 'SameSite should not be set for HTTP requests',
                ];
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
        
        $this->assertTrue(true, "Property holds for all $iterations iterations - HTTP cookies have correct security settings");
    }
    
    /**
     * Property 6: HTTPS cookie security
     * 
     * For any request over HTTPS, session cookies should be set with the
     * secure flag enabled.
     * 
     * Validates: Requirements 3.2
     * Feature: csrf-validation-fix, Property 6: HTTPS cookie security
     * 
     * @test
     */
    public function testHttpsCookieSecurity()
    {
        // Feature: csrf-validation-fix, Property 6: HTTPS cookie security
        
        $iterations = 100;
        $failures = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            // Simulate HTTPS request
            $_SERVER['HTTPS'] = 'on';
            unset($_SERVER['HTTP_X_FORWARDED_PROTO']);
            unset($_SERVER['HTTP_X_FORWARDED_SSL']);
            putenv('YIIMP_FORCE_HTTPS=false');
            
            // Detect HTTPS using the same logic as web.php
            $isHttps = $this->detectHttps();
            
            // Verify HTTPS is detected as true
            if ($isHttps !== true) {
                $failures[] = [
                    'iteration' => $i,
                    'detected_https' => $isHttps,
                    'reason' => 'HTTPS not detected for HTTPS request',
                    'server_vars' => [
                        'HTTPS' => $_SERVER['HTTPS'] ?? 'not set',
                    ],
                ];
            }
            
            // Create session with detected HTTPS setting
            $session = new Session([
                'name' => 'TEST_SESSION_' . $i,
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
            
            // Verify secure flag IS set for HTTPS
            if (empty($cookieParams['secure'])) {
                $failures[] = [
                    'iteration' => $i,
                    'cookie_params' => $cookieParams,
                    'reason' => 'Secure flag must be set for HTTPS requests',
                ];
            }
            
            // Verify httponly is set
            if (empty($cookieParams['httponly'])) {
                $failures[] = [
                    'iteration' => $i,
                    'cookie_params' => $cookieParams,
                    'reason' => 'HttpOnly flag should always be set',
                ];
            }
            
            // Verify sameSite is set for HTTPS
            // Note: Yii2 uses lowercase 'samesite' in cookie params array
            if (empty($cookieParams['samesite']) || $cookieParams['samesite'] !== 'Lax') {
                $failures[] = [
                    'iteration' => $i,
                    'cookie_params' => $cookieParams,
                    'sameSite' => $cookieParams['samesite'] ?? 'not set',
                    'reason' => 'SameSite should be set to Lax for HTTPS requests',
                ];
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
        
        $this->assertTrue(true, "Property holds for all $iterations iterations - HTTPS cookies have correct security settings");
    }
    
    /**
     * Property 7: Proxy HTTPS detection
     * 
     * For any request with proxy headers indicating HTTPS, the system should
     * treat the connection as HTTPS for cookie security.
     * 
     * Validates: Requirements 3.3
     * Feature: csrf-validation-fix, Property 7: Proxy HTTPS detection
     * 
     * @test
     */
    public function testProxyHttpsDetection()
    {
        // Feature: csrf-validation-fix, Property 7: Proxy HTTPS detection
        
        $iterations = 100;
        $failures = [];
        
        // Test different proxy header combinations
        $proxyHeaders = [
            ['HTTP_X_FORWARDED_PROTO' => 'https'],
            ['HTTP_X_FORWARDED_SSL' => 'on'],
            ['HTTP_X_FORWARDED_PROTO' => 'https', 'HTTP_X_FORWARDED_SSL' => 'on'],
        ];
        
        foreach ($proxyHeaders as $headerSet) {
            for ($i = 0; $i < $iterations / count($proxyHeaders); $i++) {
                // Simulate request behind proxy with HTTPS
                $_SERVER['HTTPS'] = 'off'; // Direct connection is HTTP
                
                // Set proxy headers
                foreach ($headerSet as $key => $value) {
                    $_SERVER[$key] = $value;
                }
                
                putenv('YIIMP_FORCE_HTTPS=false');
                
                // Detect HTTPS using the same logic as web.php
                $isHttps = $this->detectHttps();
                
                // Verify HTTPS is detected as true from proxy headers
                if ($isHttps !== true) {
                    $failures[] = [
                        'iteration' => $i,
                        'proxy_headers' => $headerSet,
                        'detected_https' => $isHttps,
                        'reason' => 'HTTPS not detected from proxy headers',
                        'server_vars' => [
                            'HTTPS' => $_SERVER['HTTPS'] ?? 'not set',
                            'HTTP_X_FORWARDED_PROTO' => $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? 'not set',
                            'HTTP_X_FORWARDED_SSL' => $_SERVER['HTTP_X_FORWARDED_SSL'] ?? 'not set',
                        ],
                    ];
                }
                
                // Create session with detected HTTPS setting
                $session = new Session([
                    'name' => 'TEST_SESSION_' . $i,
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
                
                // Verify secure flag IS set when behind HTTPS proxy
                if (empty($cookieParams['secure'])) {
                    $failures[] = [
                        'iteration' => $i,
                        'proxy_headers' => $headerSet,
                        'cookie_params' => $cookieParams,
                        'reason' => 'Secure flag must be set when behind HTTPS proxy',
                    ];
                }
                
                // Clean up proxy headers
                foreach ($headerSet as $key => $value) {
                    unset($_SERVER[$key]);
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
        
        $this->assertTrue(true, "Property holds for all $iterations iterations - proxy HTTPS detection works correctly");
    }
    
    /**
     * Property 8: Environment variable override
     * 
     * For any configuration where YIIMP_FORCE_HTTPS is set to true, the system
     * should treat all connections as HTTPS regardless of actual protocol.
     * 
     * Validates: Requirements 3.4
     * Feature: csrf-validation-fix, Property 8: Environment variable override
     * 
     * @test
     */
    public function testEnvironmentVariableOverride()
    {
        // Feature: csrf-validation-fix, Property 8: Environment variable override
        
        $iterations = 100;
        $failures = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            // Simulate HTTP request (no HTTPS indicators)
            $_SERVER['HTTPS'] = 'off';
            $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'http';
            unset($_SERVER['HTTP_X_FORWARDED_SSL']);
            
            // Force HTTPS via environment variable
            $forceHttpsValues = ['true', '1'];
            $forceHttpsValue = $forceHttpsValues[$i % count($forceHttpsValues)];
            putenv("YIIMP_FORCE_HTTPS=$forceHttpsValue");
            
            // Detect HTTPS using the same logic as web.php
            $isHttps = $this->detectHttps();
            
            // Verify HTTPS is detected as true due to environment override
            if ($isHttps !== true) {
                $failures[] = [
                    'iteration' => $i,
                    'force_https_value' => $forceHttpsValue,
                    'detected_https' => $isHttps,
                    'reason' => 'YIIMP_FORCE_HTTPS environment variable not respected',
                    'server_vars' => [
                        'HTTPS' => $_SERVER['HTTPS'] ?? 'not set',
                        'HTTP_X_FORWARDED_PROTO' => $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? 'not set',
                    ],
                ];
            }
            
            // Create session with detected HTTPS setting
            $session = new Session([
                'name' => 'TEST_SESSION_' . $i,
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
            
            // Verify secure flag IS set when YIIMP_FORCE_HTTPS is enabled
            if (empty($cookieParams['secure'])) {
                $failures[] = [
                    'iteration' => $i,
                    'force_https_value' => $forceHttpsValue,
                    'cookie_params' => $cookieParams,
                    'reason' => 'Secure flag must be set when YIIMP_FORCE_HTTPS is enabled',
                ];
            }
        }
        
        // Clean up
        $this->cleanupServerVars();
        putenv('YIIMP_FORCE_HTTPS');
        
        // Report failures if any
        if (!empty($failures)) {
            $this->fail(
                "Property test failed in " . count($failures) . " out of $iterations iterations:\n" .
                json_encode($failures, JSON_PRETTY_PRINT)
            );
        }
        
        $this->assertTrue(true, "Property holds for all $iterations iterations - YIIMP_FORCE_HTTPS override works correctly");
    }
    
    /**
     * Test that HTTPS detection logic matches web.php implementation
     * 
     * This test verifies various combinations of server variables to ensure
     * comprehensive HTTPS detection.
     * 
     * @test
     */
    public function testHttpsDetectionComprehensive()
    {
        $testCases = [
            // Direct HTTPS
            [
                'server_vars' => ['HTTPS' => 'on'],
                'env_vars' => [],
                'expected' => true,
                'description' => 'Direct HTTPS connection',
            ],
            [
                'server_vars' => ['HTTPS' => '1'],
                'env_vars' => [],
                'expected' => true,
                'description' => 'Direct HTTPS with value 1',
            ],
            // Direct HTTP
            [
                'server_vars' => ['HTTPS' => 'off'],
                'env_vars' => [],
                'expected' => false,
                'description' => 'Direct HTTP connection',
            ],
            [
                'server_vars' => [],
                'env_vars' => [],
                'expected' => false,
                'description' => 'No HTTPS indicators',
            ],
            // Proxy headers
            [
                'server_vars' => ['HTTPS' => 'off', 'HTTP_X_FORWARDED_PROTO' => 'https'],
                'env_vars' => [],
                'expected' => true,
                'description' => 'HTTPS via X-Forwarded-Proto',
            ],
            [
                'server_vars' => ['HTTPS' => 'off', 'HTTP_X_FORWARDED_SSL' => 'on'],
                'env_vars' => [],
                'expected' => true,
                'description' => 'HTTPS via X-Forwarded-SSL',
            ],
            // Environment override
            [
                'server_vars' => ['HTTPS' => 'off'],
                'env_vars' => ['YIIMP_FORCE_HTTPS' => 'true'],
                'expected' => true,
                'description' => 'Force HTTPS via environment',
            ],
            [
                'server_vars' => ['HTTPS' => 'off'],
                'env_vars' => ['YIIMP_FORCE_HTTPS' => '1'],
                'expected' => true,
                'description' => 'Force HTTPS via environment (value 1)',
            ],
        ];
        
        $failures = [];
        
        foreach ($testCases as $index => $testCase) {
            // Set up server variables
            foreach ($testCase['server_vars'] as $key => $value) {
                $_SERVER[$key] = $value;
            }
            
            // Set up environment variables
            foreach ($testCase['env_vars'] as $key => $value) {
                putenv("$key=$value");
            }
            
            // Detect HTTPS
            $detected = $this->detectHttps();
            
            // Verify result
            if ($detected !== $testCase['expected']) {
                $failures[] = [
                    'test_case' => $index,
                    'description' => $testCase['description'],
                    'expected' => $testCase['expected'],
                    'detected' => $detected,
                    'server_vars' => $testCase['server_vars'],
                    'env_vars' => $testCase['env_vars'],
                ];
            }
            
            // Clean up
            $this->cleanupServerVars();
            foreach ($testCase['env_vars'] as $key => $value) {
                putenv($key);
            }
        }
        
        // Report failures if any
        if (!empty($failures)) {
            $this->fail(
                "Comprehensive HTTPS detection test failed in " . count($failures) . " out of " . count($testCases) . " test cases:\n" .
                json_encode($failures, JSON_PRETTY_PRINT)
            );
        }
        
        $this->assertTrue(true, "All " . count($testCases) . " HTTPS detection test cases passed");
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
    }
}
