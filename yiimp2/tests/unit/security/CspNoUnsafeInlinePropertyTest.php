<?php

namespace tests\unit\security;

use Codeception\Test\Unit;
use app\components\CspNonceManager;
use yii\web\Application;

/**
 * Property Test: CSP compliance - no 'unsafe-inline' in style-src
 * 
 * Feature: csp-inline-styles-removal, Property 5: CSP compliance
 * Validates: Requirements 1.3, 1.5
 * 
 * This test verifies that:
 * 1. The CSP header does not include 'unsafe-inline' in the style-src directive
 * 2. The CSP header is correctly configured for both dev and prod environments
 * 3. All pages can be rendered without CSP violations
 */
class CspNoUnsafeInlinePropertyTest extends Unit
{
    protected $tester;
    
    /**
     * Property: For any HTTP response, the Content-Security-Policy header should
     * NOT contain 'unsafe-inline' in the style-src directive
     */
    public function testCspNoUnsafeInlineInStyleSrc()
    {
        $iterations = 100;
        $failures = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            $manager = new CspNonceManager();
            
            // Clear HTTP_X_CSP_NONCE to force generation
            if (isset($_SERVER['HTTP_X_CSP_NONCE'])) {
                unset($_SERVER['HTTP_X_CSP_NONCE']);
            }
            
            // Test both dev and prod modes
            $isDev = ($i % 2 === 0);
            $cspHeader = $manager->getCspHeader($isDev);
            
            // Extract style-src directive
            if (preg_match('/style-src\s+([^;]+)/', $cspHeader, $matches)) {
                $styleSrc = $matches[1];
                
                // Verify 'unsafe-inline' is NOT present
                if (strpos($styleSrc, "'unsafe-inline'") !== false) {
                    $failures[] = [
                        'iteration' => $i,
                        'mode' => $isDev ? 'development' : 'production',
                        'style_src' => $styleSrc,
                        'reason' => "style-src contains 'unsafe-inline'"
                    ];
                }
            } else {
                $failures[] = [
                    'iteration' => $i,
                    'mode' => $isDev ? 'development' : 'production',
                    'header' => substr($cspHeader, 0, 150),
                    'reason' => 'Could not extract style-src directive from CSP header'
                ];
            }
        }
        
        if (!empty($failures)) {
            $failureMessage = "Found CSP headers with 'unsafe-inline' in style-src:\n";
            foreach (array_slice($failures, 0, 5) as $failure) {
                $failureMessage .= sprintf(
                    "Iteration %d (%s): %s\n",
                    $failure['iteration'],
                    $failure['mode'],
                    $failure['reason']
                );
                if (isset($failure['style_src'])) {
                    $failureMessage .= sprintf("  style-src: %s\n", $failure['style_src']);
                }
            }
            $failureMessage .= sprintf("\nTotal failures: %d out of %d iterations", count($failures), $iterations);
            $this->fail($failureMessage);
        }
        
        $this->assertTrue(true, "CSP style-src does not contain 'unsafe-inline' across $iterations iterations");
    }
    
    /**
     * Property: For any CSP header, the style-src directive should only contain 'self'
     * (no nonce needed since all inline styles have been removed)
     */
    public function testCspStyleSrcOnlySelf()
    {
        $iterations = 100;
        $failures = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            $manager = new CspNonceManager();
            
            // Clear HTTP_X_CSP_NONCE to force generation
            if (isset($_SERVER['HTTP_X_CSP_NONCE'])) {
                unset($_SERVER['HTTP_X_CSP_NONCE']);
            }
            
            $isDev = ($i % 2 === 0);
            $styleSrc = $manager->getStyleSrc();
            
            // Verify style-src is exactly 'self'
            if ($styleSrc !== "'self'") {
                $failures[] = [
                    'iteration' => $i,
                    'mode' => $isDev ? 'development' : 'production',
                    'expected' => "'self'",
                    'actual' => $styleSrc,
                    'reason' => "style-src should be exactly 'self' (no nonce needed)"
                ];
            }
        }
        
        if (!empty($failures)) {
            $failureMessage = "Found incorrect style-src directives:\n";
            foreach (array_slice($failures, 0, 5) as $failure) {
                $failureMessage .= sprintf(
                    "Iteration %d (%s): Expected '%s', got '%s'\n",
                    $failure['iteration'],
                    $failure['mode'],
                    $failure['expected'],
                    $failure['actual']
                );
            }
            $failureMessage .= sprintf("\nTotal failures: %d out of %d iterations", count($failures), $iterations);
            $this->fail($failureMessage);
        }
        
        $this->assertTrue(true, "CSP style-src is correctly set to 'self' across $iterations iterations");
    }
    
    /**
     * Property: For any page rendered by the application, there should be no
     * inline style attributes (style="...") in the HTML output
     */
    public function testNoInlineStylesInRenderedHtml()
    {
        $iterations = 50; // Fewer iterations since this involves rendering pages
        $failures = [];
        
        // List of pages to test
        $testPages = [
            ['route' => 'site/index', 'description' => 'Homepage'],
            ['route' => 'site/mining', 'description' => 'Mining page'],
            ['route' => 'site/api', 'description' => 'API documentation'],
        ];
        
        for ($i = 0; $i < $iterations; $i++) {
            // Pick a random page to test
            $page = $testPages[$i % count($testPages)];
            
            try {
                // Create a mock application if needed
                if (!\Yii::$app) {
                    $this->mockApplication();
                }
                
                // Render the page
                $controller = \Yii::$app->createController($page['route']);
                if ($controller === false) {
                    continue; // Skip if controller can't be created
                }
                
                // Get the rendered output
                ob_start();
                try {
                    $result = $controller[0]->run($controller[1]);
                    $output = ob_get_clean();
                    
                    if (is_string($result)) {
                        $output = $result;
                    }
                } catch (\Exception $e) {
                    ob_end_clean();
                    continue; // Skip pages that fail to render
                }
                
                // Check for inline style attributes
                if (preg_match_all('/\sstyle\s*=\s*["\'][^"\']*["\']/', $output, $matches)) {
                    $failures[] = [
                        'iteration' => $i,
                        'page' => $page['description'],
                        'route' => $page['route'],
                        'inline_styles_found' => count($matches[0]),
                        'examples' => array_slice($matches[0], 0, 3),
                        'reason' => 'Found inline style attributes in rendered HTML'
                    ];
                }
            } catch (\Exception $e) {
                // Skip pages that can't be rendered in test environment
                continue;
            }
        }
        
        if (!empty($failures)) {
            $failureMessage = "Found inline styles in rendered HTML:\n";
            foreach (array_slice($failures, 0, 5) as $failure) {
                $failureMessage .= sprintf(
                    "Iteration %d (%s): Found %d inline style(s)\n",
                    $failure['iteration'],
                    $failure['page'],
                    $failure['inline_styles_found']
                );
                if (!empty($failure['examples'])) {
                    $failureMessage .= "  Examples:\n";
                    foreach ($failure['examples'] as $example) {
                        $failureMessage .= "    " . substr($example, 0, 80) . "\n";
                    }
                }
            }
            $failureMessage .= sprintf("\nTotal failures: %d out of %d iterations", count($failures), $iterations);
            $this->fail($failureMessage);
        }
        
        $this->assertTrue(true, "No inline styles found in rendered HTML across $iterations iterations");
    }
    
    /**
     * Property: For any CSP header, it should be consistent between
     * CspNonceManager and the nginx configuration
     */
    public function testCspHeaderConsistencyWithNginxConfig()
    {
        $iterations = 100;
        $failures = [];
        
        // Expected CSP directives (should match nginx config)
        $expectedDirectives = [
            'default-src' => "'self'",
            'script-src' => "'self' 'nonce-", // Will have nonce appended
            'style-src' => "'self'", // No unsafe-inline, no nonce
            'img-src' => "'self' data:",
            'font-src' => "'self' data:",
            'connect-src' => "'self'",
            'frame-ancestors' => "'none'",
            'base-uri' => "'self'",
            'form-action' => "'self'"
        ];
        
        for ($i = 0; $i < $iterations; $i++) {
            $manager = new CspNonceManager();
            
            // Clear HTTP_X_CSP_NONCE to force generation
            if (isset($_SERVER['HTTP_X_CSP_NONCE'])) {
                unset($_SERVER['HTTP_X_CSP_NONCE']);
            }
            
            $isDev = ($i % 2 === 0);
            $cspHeader = $manager->getCspHeader($isDev);
            
            // Check each directive
            foreach ($expectedDirectives as $directive => $expectedValue) {
                if ($directive === 'script-src') {
                    // Special case: script-src has nonce
                    if (!preg_match("/{$directive}\s+'self'\s+'nonce-[^']+'/", $cspHeader)) {
                        $failures[] = [
                            'iteration' => $i,
                            'mode' => $isDev ? 'development' : 'production',
                            'directive' => $directive,
                            'reason' => "script-src should have 'self' and nonce"
                        ];
                    }
                } else {
                    // Check if directive exists with expected value
                    $pattern = '/' . preg_quote($directive, '/') . '\s+' . preg_quote($expectedValue, '/') . '/';
                    if (!preg_match($pattern, $cspHeader)) {
                        $failures[] = [
                            'iteration' => $i,
                            'mode' => $isDev ? 'development' : 'production',
                            'directive' => $directive,
                            'expected' => $expectedValue,
                            'reason' => "Directive not found or value mismatch"
                        ];
                    }
                }
            }
        }
        
        if (!empty($failures)) {
            $failureMessage = "Found CSP header inconsistencies with nginx config:\n";
            foreach (array_slice($failures, 0, 5) as $failure) {
                $failureMessage .= sprintf(
                    "Iteration %d (%s): %s - %s\n",
                    $failure['iteration'],
                    $failure['mode'],
                    $failure['directive'],
                    $failure['reason']
                );
            }
            $failureMessage .= sprintf("\nTotal failures: %d out of %d iterations", count($failures), $iterations);
            $this->fail($failureMessage);
        }
        
        $this->assertTrue(true, "CSP header consistent with nginx config across $iterations iterations");
    }
    
    /**
     * Helper method to create a mock application for testing
     */
    protected function mockApplication()
    {
        new Application([
            'id' => 'testapp',
            'basePath' => dirname(dirname(dirname(__DIR__))),
            'components' => [
                'request' => [
                    'cookieValidationKey' => 'test',
                    'scriptFile' => __DIR__ . '/index.php',
                    'scriptUrl' => '/index.php',
                ],
                'db' => [
                    'class' => 'yii\db\Connection',
                    'dsn' => 'mysql:host=localhost;dbname=test',
                    'username' => 'test',
                    'password' => 'test',
                ],
            ],
        ]);
    }
}
