<?php

namespace tests\unit\components;

use Codeception\Test\Unit;
use app\components\CspHelper;
use Yii;

/**
 * Property-based tests for CspHelper component
 * 
 * Feature: csp-inline-scripts-removal, Property 4: CspHelper generates valid nonces
 * Validates: Requirements 2.2, 2.5
 */
class CspHelperPropertyTest extends Unit
{
    protected function _before()
    {
        parent::_before();
    }
    
    protected function _after()
    {
        parent::_after();
    }
    
    /**
     * Property 4: CspHelper generates valid nonces
     * 
     * For any call to CspHelper::beginScript(), the returned string should contain 
     * a nonce attribute with a non-empty value that matches the current request's nonce.
     * 
     * Feature: csp-inline-scripts-removal, Property 4: CspHelper generates valid nonces
     * Validates: Requirements 2.2, 2.5
     * 
     * @test
     */
    public function testCspHelperGeneratesValidNonces()
    {
        $iterations = 100;
        $failures = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            try {
                // Generate a random nonce value
                $nonce = $this->generateRandomNonce();
                
                // Mock the CspNonceManager component with the generated nonce
                $mockNonceManager = $this->getMockBuilder(\app\components\CspNonceManager::class)
                    ->onlyMethods(['getNonce'])
                    ->getMock();
                
                $mockNonceManager->method('getNonce')
                    ->willReturn($nonce);
                
                Yii::$app->set('cspNonce', $mockNonceManager);
                
                // Test beginScript()
                $scriptTag = CspHelper::beginScript();
                
                // Verify the script tag contains a nonce attribute
                if (strpos($scriptTag, 'nonce=') === false) {
                    $failures[] = [
                        'iteration' => $i,
                        'nonce' => $nonce,
                        'reason' => 'Script tag does not contain nonce attribute',
                        'scriptTag' => $scriptTag
                    ];
                    continue;
                }
                
                // Extract the nonce value from the script tag
                if (preg_match('/nonce="([^"]*)"/', $scriptTag, $matches)) {
                    $extractedNonce = $matches[1];
                    
                    // Verify the nonce is non-empty (unless the original was null/empty)
                    if ($nonce !== null && $nonce !== '' && empty($extractedNonce)) {
                        $failures[] = [
                            'iteration' => $i,
                            'nonce' => $nonce,
                            'reason' => 'Extracted nonce is empty but original was not',
                            'extractedNonce' => $extractedNonce,
                            'scriptTag' => $scriptTag
                        ];
                        continue;
                    }
                    
                    // Verify the nonce matches the current request's nonce
                    // Note: HTML entities should be decoded for comparison
                    $expectedNonce = htmlspecialchars($nonce, ENT_QUOTES, 'UTF-8');
                    if ($extractedNonce !== $expectedNonce) {
                        $failures[] = [
                            'iteration' => $i,
                            'nonce' => $nonce,
                            'reason' => 'Extracted nonce does not match expected nonce',
                            'extractedNonce' => $extractedNonce,
                            'expectedNonce' => $expectedNonce,
                            'scriptTag' => $scriptTag
                        ];
                        continue;
                    }
                } else {
                    $failures[] = [
                        'iteration' => $i,
                        'nonce' => $nonce,
                        'reason' => 'Could not extract nonce from script tag',
                        'scriptTag' => $scriptTag
                    ];
                    continue;
                }
                
                // Test script() method as well
                $js = "console.log('test');";
                $completeScript = CspHelper::script($js);
                
                // Verify the complete script also contains the nonce
                if (strpos($completeScript, 'nonce=') === false) {
                    $failures[] = [
                        'iteration' => $i,
                        'nonce' => $nonce,
                        'reason' => 'Complete script does not contain nonce attribute',
                        'completeScript' => $completeScript
                    ];
                    continue;
                }
                
                // Verify the nonce in the complete script matches
                if (preg_match('/nonce="([^"]*)"/', $completeScript, $matches)) {
                    $extractedNonce = $matches[1];
                    $expectedNonce = htmlspecialchars($nonce, ENT_QUOTES, 'UTF-8');
                    
                    if ($extractedNonce !== $expectedNonce) {
                        $failures[] = [
                            'iteration' => $i,
                            'nonce' => $nonce,
                            'reason' => 'Nonce in complete script does not match expected',
                            'extractedNonce' => $extractedNonce,
                            'expectedNonce' => $expectedNonce,
                            'completeScript' => $completeScript
                        ];
                    }
                }
                
                // Test with additional options
                $scriptTagWithOptions = CspHelper::beginScript(['type' => 'module']);
                
                // Verify nonce is still present with options
                if (strpos($scriptTagWithOptions, 'nonce=') === false) {
                    $failures[] = [
                        'iteration' => $i,
                        'nonce' => $nonce,
                        'reason' => 'Script tag with options does not contain nonce',
                        'scriptTag' => $scriptTagWithOptions
                    ];
                    continue;
                }
                
                // Verify the nonce matches even with options
                if (preg_match('/nonce="([^"]*)"/', $scriptTagWithOptions, $matches)) {
                    $extractedNonce = $matches[1];
                    $expectedNonce = htmlspecialchars($nonce, ENT_QUOTES, 'UTF-8');
                    
                    if ($extractedNonce !== $expectedNonce) {
                        $failures[] = [
                            'iteration' => $i,
                            'nonce' => $nonce,
                            'reason' => 'Nonce with options does not match expected',
                            'extractedNonce' => $extractedNonce,
                            'expectedNonce' => $expectedNonce,
                            'scriptTag' => $scriptTagWithOptions
                        ];
                    }
                }
                
            } catch (\Exception $e) {
                $failures[] = [
                    'iteration' => $i,
                    'reason' => 'Test execution failed',
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
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
     * Test that nonce is properly HTML-escaped
     * 
     * Feature: csp-inline-scripts-removal, Property 4: CspHelper generates valid nonces (HTML escaping)
     * Validates: Requirements 2.2, 2.5
     * 
     * @test
     */
    public function testNonceHtmlEscaping()
    {
        $iterations = 50;
        $failures = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            try {
                // Generate a nonce with special characters
                $nonce = $this->generateNonceWithSpecialChars();
                
                // Mock the CspNonceManager
                $mockNonceManager = $this->getMockBuilder(\app\components\CspNonceManager::class)
                    ->onlyMethods(['getNonce'])
                    ->getMock();
                
                $mockNonceManager->method('getNonce')
                    ->willReturn($nonce);
                
                Yii::$app->set('cspNonce', $mockNonceManager);
                
                // Generate script tag
                $scriptTag = CspHelper::beginScript();
                
                // Verify HTML entities are properly escaped
                $expectedNonce = htmlspecialchars($nonce, ENT_QUOTES, 'UTF-8');
                
                if (strpos($scriptTag, 'nonce="' . $expectedNonce . '"') === false) {
                    $failures[] = [
                        'iteration' => $i,
                        'nonce' => $nonce,
                        'reason' => 'Nonce not properly HTML-escaped',
                        'expectedNonce' => $expectedNonce,
                        'scriptTag' => $scriptTag
                    ];
                    continue;
                }
                
                // Verify dangerous characters are NOT present in their raw form
                // (they should be escaped as HTML entities)
                $dangerousPatterns = [
                    // Check for unescaped quotes that would break the attribute
                    '/nonce="[^"]*"[^"]*"/' => 'Unescaped quote in nonce value',
                    // Check for unescaped < or > that would break HTML
                    '/nonce="[^"]*<(?!&)/' => 'Unescaped < in nonce value',
                    '/nonce="[^"]*>(?!&)/' => 'Unescaped > in nonce value',
                ];
                
                foreach ($dangerousPatterns as $pattern => $message) {
                    if (preg_match($pattern, $scriptTag)) {
                        $failures[] = [
                            'iteration' => $i,
                            'nonce' => $nonce,
                            'reason' => $message,
                            'scriptTag' => $scriptTag
                        ];
                        break;
                    }
                }
                
            } catch (\Exception $e) {
                $failures[] = [
                    'iteration' => $i,
                    'reason' => 'Test execution failed',
                    'message' => $e->getMessage()
                ];
            }
        }
        
        if (!empty($failures)) {
            $this->fail(
                "HTML escaping property test failed in " . count($failures) . " out of $iterations iterations:\n" .
                json_encode($failures, JSON_PRETTY_PRINT)
            );
        }
        
        $this->assertTrue(true, "HTML escaping property holds for all $iterations iterations");
    }
    
    /**
     * Test behavior when nonce is not available
     * 
     * Feature: csp-inline-scripts-removal, Property 4: CspHelper generates valid nonces (graceful degradation)
     * Validates: Requirements 2.2, 2.5
     * 
     * @test
     */
    public function testGracefulDegradationWithoutNonce()
    {
        $iterations = 20;
        $failures = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            try {
                // Remove the cspNonce component
                if (Yii::$app->has('cspNonce')) {
                    Yii::$app->clear('cspNonce');
                }
                
                // Clear view params
                if (isset(Yii::$app->view->params['cspNonce'])) {
                    unset(Yii::$app->view->params['cspNonce']);
                }
                
                // Generate script tag without nonce available
                $scriptTag = CspHelper::beginScript();
                
                // Should still generate a valid script tag
                if (!preg_match('/^<script[^>]*>$/', $scriptTag)) {
                    $failures[] = [
                        'iteration' => $i,
                        'reason' => 'Invalid script tag format without nonce',
                        'scriptTag' => $scriptTag
                    ];
                    continue;
                }
                
                // Should not contain nonce attribute when not available
                if (strpos($scriptTag, 'nonce=') !== false) {
                    $failures[] = [
                        'iteration' => $i,
                        'reason' => 'Script tag contains nonce when none available',
                        'scriptTag' => $scriptTag
                    ];
                }
                
                // Test with options
                $scriptTagWithOptions = CspHelper::beginScript(['type' => 'module']);
                
                // Should still include the options
                if (strpos($scriptTagWithOptions, 'type="module"') === false) {
                    $failures[] = [
                        'iteration' => $i,
                        'reason' => 'Options not included when nonce unavailable',
                        'scriptTag' => $scriptTagWithOptions
                    ];
                }
                
            } catch (\Exception $e) {
                $failures[] = [
                    'iteration' => $i,
                    'reason' => 'Test execution failed',
                    'message' => $e->getMessage()
                ];
            }
        }
        
        if (!empty($failures)) {
            $this->fail(
                "Graceful degradation property test failed in " . count($failures) . " out of $iterations iterations:\n" .
                json_encode($failures, JSON_PRETTY_PRINT)
            );
        }
        
        $this->assertTrue(true, "Graceful degradation property holds for all $iterations iterations");
    }
    
    /**
     * Generate a random nonce value
     * 
     * @return string
     */
    protected function generateRandomNonce()
    {
        $types = [
            // Standard base64-like nonces
            function() { return base64_encode(random_bytes(16)); },
            function() { return bin2hex(random_bytes(16)); },
            function() { return uniqid('nonce-', true); },
            // UUID-like nonces
            function() { return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                mt_rand(0, 0xffff),
                mt_rand(0, 0x0fff) | 0x4000,
                mt_rand(0, 0x3fff) | 0x8000,
                mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
            ); },
            // Simple alphanumeric
            function() { return 'nonce-' . substr(str_shuffle('abcdefghijklmnopqrstuvwxyz0123456789'), 0, 20); },
            // Short nonces
            function() { return 'abc123'; },
            function() { return 'test'; },
            // Long nonces
            function() { return str_repeat('a', 100); },
            // Numeric
            function() { return (string) mt_rand(100000, 999999); },
        ];
        
        $generator = $types[array_rand($types)];
        return $generator();
    }
    
    /**
     * Generate a nonce with special characters for HTML escaping tests
     * 
     * @return string
     */
    protected function generateNonceWithSpecialChars()
    {
        $types = [
            // HTML special characters
            function() { return 'test"nonce'; },
            function() { return 'test<script>'; },
            function() { return 'test&nonce'; },
            function() { return "test'nonce"; },
            function() { return 'test>nonce<'; },
            // Multiple special characters
            function() { return 'test"<>&\'nonce'; },
            // URL-encoded characters
            function() { return 'test%20nonce'; },
            function() { return 'test%3Cscript%3E'; },
            // Mixed
            function() { return 'nonce-"test"<script>alert(1)</script>'; },
        ];
        
        $generator = $types[array_rand($types)];
        return $generator();
    }
    
    /**
     * Test nonce consistency across multiple calls
     * 
     * Feature: csp-inline-scripts-removal, Property 4: CspHelper generates valid nonces (consistency)
     * Validates: Requirements 2.2, 2.5
     * 
     * @test
     */
    public function testNonceConsistencyAcrossMultipleCalls()
    {
        $iterations = 50;
        $failures = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            try {
                // Generate a random nonce
                $nonce = $this->generateRandomNonce();
                
                // Mock the CspNonceManager
                $mockNonceManager = $this->getMockBuilder(\app\components\CspNonceManager::class)
                    ->onlyMethods(['getNonce'])
                    ->getMock();
                
                $mockNonceManager->method('getNonce')
                    ->willReturn($nonce);
                
                Yii::$app->set('cspNonce', $mockNonceManager);
                
                // Call beginScript() multiple times
                $scriptTag1 = CspHelper::beginScript();
                $scriptTag2 = CspHelper::beginScript();
                $scriptTag3 = CspHelper::beginScript(['type' => 'module']);
                
                // Extract nonces from all three calls
                $nonces = [];
                foreach ([$scriptTag1, $scriptTag2, $scriptTag3] as $tag) {
                    if (preg_match('/nonce="([^"]*)"/', $tag, $matches)) {
                        $nonces[] = $matches[1];
                    }
                }
                
                // All nonces should be identical
                if (count(array_unique($nonces)) !== 1) {
                    $failures[] = [
                        'iteration' => $i,
                        'nonce' => $nonce,
                        'reason' => 'Nonces are not consistent across multiple calls',
                        'extractedNonces' => $nonces,
                        'scriptTags' => [$scriptTag1, $scriptTag2, $scriptTag3]
                    ];
                }
                
                // All should match the expected nonce
                $expectedNonce = htmlspecialchars($nonce, ENT_QUOTES, 'UTF-8');
                foreach ($nonces as $extractedNonce) {
                    if ($extractedNonce !== $expectedNonce) {
                        $failures[] = [
                            'iteration' => $i,
                            'nonce' => $nonce,
                            'reason' => 'Extracted nonce does not match expected',
                            'extractedNonce' => $extractedNonce,
                            'expectedNonce' => $expectedNonce
                        ];
                        break;
                    }
                }
                
            } catch (\Exception $e) {
                $failures[] = [
                    'iteration' => $i,
                    'reason' => 'Test execution failed',
                    'message' => $e->getMessage()
                ];
            }
        }
        
        if (!empty($failures)) {
            $this->fail(
                "Nonce consistency property test failed in " . count($failures) . " out of $iterations iterations:\n" .
                json_encode($failures, JSON_PRETTY_PRINT)
            );
        }
        
        $this->assertTrue(true, "Nonce consistency property holds for all $iterations iterations");
    }
}
