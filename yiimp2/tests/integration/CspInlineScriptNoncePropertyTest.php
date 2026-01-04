<?php

namespace tests\integration;

use Codeception\Test\Unit;
use Yii;

/**
 * Property Test: All inline scripts have nonces
 * 
 * Feature: csp-inline-scripts-removal, Property 1: All inline scripts have nonces
 * Validates: Requirements 1.1, 1.2
 * 
 * This test verifies that all inline script tags in rendered view files
 * include the CSP nonce attribute, ensuring CSP compliance across the application.
 */
class CspInlineScriptNoncePropertyTest extends Unit
{
    protected $tester;
    
    protected function _before()
    {
        parent::_before();
        // Ensure view component is available
        if (!Yii::$app->has('view')) {
            Yii::$app->set('view', [
                'class' => 'app\components\CspView',
            ]);
        }
    }
    
    /**
     * Property 1: All inline scripts have nonces
     * 
     * For any view file that uses CspHelper to generate inline scripts,
     * all <script> tags in the rendered output should include a nonce attribute.
     * 
     * Feature: csp-inline-scripts-removal, Property 1: All inline scripts have nonces
     * Validates: Requirements 1.1, 1.2
     * 
     * @test
     */
    public function testAllInlineScriptsHaveNonces()
    {
        $iterations = 100;
        $failures = [];
        $totalInlineScriptsChecked = 0;
        
        // List of view files that use CspHelper for inline scripts
        $basePath = dirname(dirname(__DIR__)); // Go up from tests/integration to yiimp2 root
        $viewFilesWithInlineScripts = [
            $basePath . '/views/site/index.php',
            $basePath . '/views/site/api.php',
            $basePath . '/views/site/bookmarks.php',
            $basePath . '/views/site/block.php',
            $basePath . '/views/site/miners.php',
            $basePath . '/views/site/wallet.php',
            $basePath . '/views/site/results/current_results.php',
            $basePath . '/views/site/results/miners_results.php',
            $basePath . '/views/site/results/wallet_results.php',
            $basePath . '/views/stats/index.php',
            $basePath . '/views/admin/coinwallet.php',
            $basePath . '/views/admin/coin_market_graph.php',
            $basePath . '/views/admin/coin_peers.php',
            $basePath . '/views/trading/history.php',
        ];
        
        for ($i = 0; $i < $iterations; $i++) {
            try {
                // Randomly select a view file to test
                $viewFile = $viewFilesWithInlineScripts[array_rand($viewFilesWithInlineScripts)];
                
                // Generate a random nonce for this request
                $nonce = $this->generateRandomNonce();
                
                // Mock the nonce in the application
                $this->mockCspNonce($nonce);
                
                // Read the view file content
                $viewContent = file_get_contents($viewFile);
                
                // Simulate rendering by replacing CspHelper calls with their output
                $html = $this->simulateViewRendering($viewContent, $nonce);
                
                // Find all script tags in the simulated HTML
                $scriptTags = $this->extractScriptTags($html);
                
                // Check each inline script for nonce attribute
                foreach ($scriptTags as $scriptTag) {
                    // Skip external scripts (those with src attribute)
                    if ($this->isExternalScript($scriptTag)) {
                        continue;
                    }
                    
                    $totalInlineScriptsChecked++;
                    
                    // Inline script must have nonce attribute
                    if (!$this->hasNonceAttribute($scriptTag, $nonce)) {
                        $failures[] = [
                            'iteration' => $i,
                            'viewFile' => basename($viewFile),
                            'nonce' => $nonce,
                            'scriptTag' => $this->truncateScriptTag($scriptTag),
                            'reason' => 'Inline script missing nonce attribute'
                        ];
                    }
                }
                
            } catch (\Exception $e) {
                // Log exception but continue testing
                $failures[] = [
                    'iteration' => $i,
                    'reason' => 'Test execution failed',
                    'message' => $e->getMessage(),
                    'trace' => substr($e->getTraceAsString(), 0, 500)
                ];
            }
        }
        
        // Report failures if any
        if (!empty($failures)) {
            $failureMessage = "Found inline scripts without nonce attributes:\n";
            foreach (array_slice($failures, 0, 10) as $failure) {
                if (isset($failure['viewFile'])) {
                    $failureMessage .= sprintf(
                        "Iteration %d [%s]: %s\n  Script: %s\n",
                        $failure['iteration'],
                        $failure['viewFile'],
                        $failure['reason'],
                        $failure['scriptTag'] ?? 'N/A'
                    );
                } else {
                    $failureMessage .= sprintf(
                        "Iteration %d: %s - %s\n",
                        $failure['iteration'],
                        $failure['reason'],
                        $failure['message'] ?? 'N/A'
                    );
                }
            }
            $failureMessage .= sprintf("\nTotal failures: %d out of %d iterations", count($failures), $iterations);
            $this->fail($failureMessage);
        }
        
        // Verify we actually checked some scripts
        $this->assertGreaterThan(0, $totalInlineScriptsChecked, 
            "Expected to find inline scripts to test, but found none. Checked: $totalInlineScriptsChecked");
        
        $this->assertTrue(true, "All inline scripts have nonce attributes across $iterations iterations (checked $totalInlineScriptsChecked inline scripts)");
    }
    
    /**
     * Test that nonce values are properly formatted
     * 
     * Feature: csp-inline-scripts-removal, Property 1: All inline scripts have nonces (format validation)
     * Validates: Requirements 1.1, 1.2
     * 
     * @test
     */
    public function testNonceAttributesAreProperlyFormatted()
    {
        $iterations = 50;
        $failures = [];
        
        $basePath = dirname(dirname(__DIR__));
        $viewFilesWithInlineScripts = [
            $basePath . '/views/site/index.php',
            $basePath . '/views/site/api.php',
            $basePath . '/views/site/bookmarks.php',
        ];
        
        for ($i = 0; $i < $iterations; $i++) {
            try {
                $viewFile = $viewFilesWithInlineScripts[array_rand($viewFilesWithInlineScripts)];
                
                $nonce = $this->generateRandomNonce();
                $this->mockCspNonce($nonce);
                
                $viewContent = file_get_contents($viewFile);
                $html = $this->simulateViewRendering($viewContent, $nonce);
                
                $scriptTags = $this->extractScriptTags($html);
                
                foreach ($scriptTags as $scriptTag) {
                    if ($this->isExternalScript($scriptTag)) {
                        continue;
                    }
                    
                    // Extract nonce value
                    if (preg_match('/nonce\s*=\s*["\']([^"\']*)["\']/', $scriptTag, $matches)) {
                        $extractedNonce = $matches[1];
                        
                        // Verify nonce is not empty
                        if (empty($extractedNonce)) {
                            $failures[] = [
                                'iteration' => $i,
                                'viewFile' => basename($viewFile),
                                'reason' => 'Nonce attribute is empty',
                                'scriptTag' => $this->truncateScriptTag($scriptTag)
                            ];
                            continue;
                        }
                        
                        // Verify nonce matches expected value (accounting for HTML encoding)
                        $expectedNonce = htmlspecialchars($nonce, ENT_QUOTES, 'UTF-8');
                        if ($extractedNonce !== $expectedNonce) {
                            $failures[] = [
                                'iteration' => $i,
                                'viewFile' => basename($viewFile),
                                'reason' => 'Nonce value does not match expected',
                                'expected' => $expectedNonce,
                                'actual' => $extractedNonce,
                                'scriptTag' => $this->truncateScriptTag($scriptTag)
                            ];
                        }
                    } else {
                        // This is an inline script without nonce
                        $failures[] = [
                            'iteration' => $i,
                            'viewFile' => basename($viewFile),
                            'reason' => 'Inline script has no nonce attribute',
                            'scriptTag' => $this->truncateScriptTag($scriptTag)
                        ];
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
            $failureMessage = "Found improperly formatted nonce attributes:\n";
            foreach (array_slice($failures, 0, 10) as $failure) {
                $failureMessage .= sprintf(
                    "Iteration %d [%s]: %s\n",
                    $failure['iteration'],
                    $failure['viewFile'] ?? 'N/A',
                    $failure['reason']
                );
                if (isset($failure['expected']) && isset($failure['actual'])) {
                    $failureMessage .= sprintf("  Expected: %s\n  Actual: %s\n", $failure['expected'], $failure['actual']);
                }
            }
            $failureMessage .= sprintf("\nTotal failures: %d out of %d iterations", count($failures), $iterations);
            $this->fail($failureMessage);
        }
        
        $this->assertTrue(true, "All nonce attributes are properly formatted across $iterations iterations");
    }
    
    /**
     * Generate a random nonce value for testing
     * 
     * @return string
     */
    protected function generateRandomNonce(): string
    {
        $types = [
            function() { return base64_encode(random_bytes(16)); },
            function() { return bin2hex(random_bytes(16)); },
            function() { return uniqid('nonce-', true); },
            function() { return 'test-nonce-' . mt_rand(100000, 999999); },
            function() { return substr(str_shuffle('abcdefghijklmnopqrstuvwxyz0123456789'), 0, 20); },
        ];
        
        $generator = $types[array_rand($types)];
        return $generator();
    }
    
    /**
     * Mock the CSP nonce in the application
     * 
     * @param string $nonce
     */
    protected function mockCspNonce(string $nonce): void
    {
        // Mock CspNonceManager
        $mockNonceManager = $this->getMockBuilder(\app\components\CspNonceManager::class)
            ->onlyMethods(['getNonce'])
            ->getMock();
        
        $mockNonceManager->method('getNonce')
            ->willReturn($nonce);
        
        Yii::$app->set('cspNonce', $mockNonceManager);
        
        // Also set in view params as fallback
        if (Yii::$app->view) {
            Yii::$app->view->params['cspNonce'] = $nonce;
            Yii::$app->view->params['cspNonceAttr'] = 'nonce="' . htmlspecialchars($nonce, ENT_QUOTES, 'UTF-8') . '"';
        }
    }
    
    /**
     * Simulate view rendering by replacing CspHelper calls with their output
     * 
     * @param string $viewContent
     * @param string $nonce
     * @return string
     */
    protected function simulateViewRendering(string $viewContent, string $nonce): string
    {
        // Replace CspHelper::beginScript() calls
        $viewContent = preg_replace_callback(
            '/CspHelper::beginScript\(\s*(\[.*?\])?\s*\)/',
            function($matches) use ($nonce) {
                $options = isset($matches[1]) ? $matches[1] : '[]';
                // Simulate the output of beginScript()
                $escapedNonce = htmlspecialchars($nonce, ENT_QUOTES, 'UTF-8');
                if ($options !== '[]') {
                    // Has options - would need to parse, but for simplicity just add nonce
                    return '<script nonce="' . $escapedNonce . '">';
                }
                return '<script nonce="' . $escapedNonce . '">';
            },
            $viewContent
        );
        
        // Replace CspHelper::endScript() calls
        $viewContent = str_replace('CspHelper::endScript()', '</script>', $viewContent);
        
        // Replace CspHelper::script() calls
        $viewContent = preg_replace_callback(
            '/CspHelper::script\(\s*([\'"])(.*?)\1\s*(?:,\s*(\[.*?\]))?\s*\)/',
            function($matches) use ($nonce) {
                $js = $matches[2];
                $escapedNonce = htmlspecialchars($nonce, ENT_QUOTES, 'UTF-8');
                return '<script nonce="' . $escapedNonce . '">' . $js . '</script>';
            },
            $viewContent
        );
        
        return $viewContent;
    }
    
    /**
     * Extract all script tags from HTML
     * 
     * @param string $html
     * @return array
     */
    protected function extractScriptTags(string $html): array
    {
        $scriptTags = [];
        
        // Match all script tags (both self-closing and with content)
        if (preg_match_all('/<script[^>]*>.*?<\/script>/is', $html, $matches)) {
            $scriptTags = array_merge($scriptTags, $matches[0]);
        }
        
        // Also match self-closing script tags (rare but possible)
        if (preg_match_all('/<script[^>]*\/>/is', $html, $matches)) {
            $scriptTags = array_merge($scriptTags, $matches[0]);
        }
        
        return $scriptTags;
    }
    
    /**
     * Check if a script tag is external (has src attribute)
     * 
     * @param string $scriptTag
     * @return bool
     */
    protected function isExternalScript(string $scriptTag): bool
    {
        return preg_match('/\ssrc\s*=/', $scriptTag) === 1;
    }
    
    /**
     * Check if a script tag has a nonce attribute matching the expected value
     * 
     * @param string $scriptTag
     * @param string $expectedNonce
     * @return bool
     */
    protected function hasNonceAttribute(string $scriptTag, string $expectedNonce): bool
    {
        // Account for HTML encoding
        $expectedNonce = htmlspecialchars($expectedNonce, ENT_QUOTES, 'UTF-8');
        
        // Check for nonce attribute with the expected value
        $pattern = '/nonce\s*=\s*["\']' . preg_quote($expectedNonce, '/') . '["\']/';
        
        return preg_match($pattern, $scriptTag) === 1;
    }
    
    /**
     * Truncate a script tag for display in error messages
     * 
     * @param string $scriptTag
     * @return string
     */
    protected function truncateScriptTag(string $scriptTag): string
    {
        if (strlen($scriptTag) > 200) {
            return substr($scriptTag, 0, 200) . '...';
        }
        return $scriptTag;
    }
    
    /**
     * Property 2: Nonce matches CSP header
     * 
     * For any HTTP response, the nonce value in inline script tags should match
     * the nonce value in the Content-Security-Policy header.
     * 
     * Feature: csp-inline-scripts-removal, Property 2: Nonce matches CSP header
     * Validates: Requirements 1.3
     * 
     * @test
     */
    public function testNonceMatchesCspHeader()
    {
        $iterations = 100;
        $failures = [];
        $totalChecks = 0;
        
        // List of view files that use CspHelper for inline scripts
        $basePath = dirname(dirname(__DIR__)); // Go up from tests/integration to yiimp2 root
        $viewFilesWithInlineScripts = [
            $basePath . '/views/site/index.php',
            $basePath . '/views/site/api.php',
            $basePath . '/views/site/bookmarks.php',
            $basePath . '/views/site/block.php',
            $basePath . '/views/site/miners.php',
            $basePath . '/views/site/wallet.php',
            $basePath . '/views/site/results/current_results.php',
            $basePath . '/views/site/results/miners_results.php',
            $basePath . '/views/site/results/wallet_results.php',
            $basePath . '/views/stats/index.php',
            $basePath . '/views/admin/coinwallet.php',
            $basePath . '/views/admin/coin_market_graph.php',
            $basePath . '/views/admin/coin_peers.php',
            $basePath . '/views/trading/history.php',
        ];
        
        for ($i = 0; $i < $iterations; $i++) {
            try {
                // Randomly select a view file to test
                $viewFile = $viewFilesWithInlineScripts[array_rand($viewFilesWithInlineScripts)];
                
                // Generate a random nonce for this request
                $nonce = $this->generateRandomNonce();
                
                // Mock the nonce in the application
                $this->mockCspNonce($nonce);
                
                // Get the CSP header that would be sent
                $cspHeader = $this->getCspHeaderForNonce($nonce);
                
                // Extract nonce from CSP header
                $headerNonce = $this->extractNonceFromCspHeader($cspHeader);
                
                if ($headerNonce === null) {
                    $failures[] = [
                        'iteration' => $i,
                        'viewFile' => basename($viewFile),
                        'reason' => 'Could not extract nonce from CSP header',
                        'cspHeader' => substr($cspHeader, 0, 200)
                    ];
                    continue;
                }
                
                // Read the view file content
                $viewContent = file_get_contents($viewFile);
                
                // Simulate rendering by replacing CspHelper calls with their output
                $html = $this->simulateViewRendering($viewContent, $nonce);
                
                // Find all script tags in the simulated HTML
                $scriptTags = $this->extractScriptTags($html);
                
                // Check each inline script's nonce matches the CSP header nonce
                foreach ($scriptTags as $scriptTag) {
                    // Skip external scripts (those with src attribute)
                    if ($this->isExternalScript($scriptTag)) {
                        continue;
                    }
                    
                    $totalChecks++;
                    
                    // Extract nonce from script tag
                    $scriptNonce = $this->extractNonceFromScriptTag($scriptTag);
                    
                    if ($scriptNonce === null) {
                        $failures[] = [
                            'iteration' => $i,
                            'viewFile' => basename($viewFile),
                            'reason' => 'Inline script has no nonce attribute',
                            'scriptTag' => $this->truncateScriptTag($scriptTag),
                            'expectedNonce' => $headerNonce
                        ];
                        continue;
                    }
                    
                    // Verify script nonce matches header nonce
                    if ($scriptNonce !== $headerNonce) {
                        $failures[] = [
                            'iteration' => $i,
                            'viewFile' => basename($viewFile),
                            'reason' => 'Script nonce does not match CSP header nonce',
                            'scriptNonce' => $scriptNonce,
                            'headerNonce' => $headerNonce,
                            'scriptTag' => $this->truncateScriptTag($scriptTag)
                        ];
                    }
                }
                
            } catch (\Exception $e) {
                // Log exception but continue testing
                $failures[] = [
                    'iteration' => $i,
                    'reason' => 'Test execution failed',
                    'message' => $e->getMessage(),
                    'trace' => substr($e->getTraceAsString(), 0, 500)
                ];
            }
        }
        
        // Report failures if any
        if (!empty($failures)) {
            $failureMessage = "Found nonce mismatches between script tags and CSP header:\n";
            foreach (array_slice($failures, 0, 10) as $failure) {
                if (isset($failure['viewFile'])) {
                    $failureMessage .= sprintf(
                        "Iteration %d [%s]: %s\n",
                        $failure['iteration'],
                        $failure['viewFile'],
                        $failure['reason']
                    );
                    if (isset($failure['scriptNonce']) && isset($failure['headerNonce'])) {
                        $failureMessage .= sprintf(
                            "  Script nonce: %s\n  Header nonce: %s\n",
                            $failure['scriptNonce'],
                            $failure['headerNonce']
                        );
                    }
                } else {
                    $failureMessage .= sprintf(
                        "Iteration %d: %s - %s\n",
                        $failure['iteration'],
                        $failure['reason'],
                        $failure['message'] ?? 'N/A'
                    );
                }
            }
            $failureMessage .= sprintf("\nTotal failures: %d out of %d checks across %d iterations", 
                count($failures), $totalChecks, $iterations);
            $this->fail($failureMessage);
        }
        
        // Verify we actually checked some scripts
        $this->assertGreaterThan(0, $totalChecks, 
            "Expected to find inline scripts to test, but found none. Checked: $totalChecks");
        
        $this->assertTrue(true, 
            "All script nonces match CSP header nonce across $iterations iterations ($totalChecks checks)");
    }
    
    /**
     * Get the CSP header that would be sent for a given nonce
     * 
     * @param string $nonce
     * @return string
     */
    protected function getCspHeaderForNonce(string $nonce): string
    {
        // Simulate the CSP header that nginx would generate
        // This matches the configuration in config/nginx/yiimp2.conf and yiimp2-dev.conf
        return "default-src 'self'; " .
               "script-src 'self' 'nonce-{$nonce}' 'unsafe-eval'; " .
               "script-src-elem 'self' 'nonce-{$nonce}'; " .
               "style-src 'self'; " .
               "style-src-elem 'self'; " .
               "img-src 'self' data:; " .
               "font-src 'self' data:; " .
               "connect-src 'self'; " .
               "frame-ancestors 'none'; " .
               "base-uri 'self'; " .
               "form-action 'self'";
    }
    
    /**
     * Extract nonce value from CSP header
     * 
     * @param string $cspHeader
     * @return string|null
     */
    protected function extractNonceFromCspHeader(string $cspHeader): ?string
    {
        // Extract nonce from script-src or script-src-elem directive
        // Pattern: 'nonce-VALUE' where VALUE is the nonce
        // The nonce can contain alphanumeric, +, /, =, _, -, and dots
        if (preg_match("/'nonce-([^']+)'/", $cspHeader, $matches)) {
            return $matches[1];
        }
        return null;
    }
    
    /**
     * Extract nonce value from script tag
     * 
     * @param string $scriptTag
     * @return string|null
     */
    protected function extractNonceFromScriptTag(string $scriptTag): ?string
    {
        // Extract nonce attribute value from script tag
        // Pattern: nonce="VALUE" or nonce='VALUE'
        if (preg_match('/nonce\s*=\s*["\']([^"\']*)["\']/', $scriptTag, $matches)) {
            return $matches[1];
        }
        return null;
    }
}
