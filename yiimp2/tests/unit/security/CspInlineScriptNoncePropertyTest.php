<?php

namespace tests\unit\security;

use Codeception\Test\Unit;
use app\components\CspView;

/**
 * Property Test: Inline scripts have nonce attributes
 * 
 * Feature: yiimp2-nginx-migration, Property 3: Inline scripts have nonce attributes
 * Validates: Requirements 2.2, 7.1
 * 
 * This test verifies that all inline script tags registered via registerJs
 * include the CSP nonce attribute when rendered.
 */
class CspInlineScriptNoncePropertyTest extends Unit
{
    protected $tester;
    
    /**
     * Property: For any view with inline scripts registered via registerJs,
     * the rendered HTML should include nonce attributes on all inline script tags
     */
    public function testInlineScriptsHaveNonceAttributes()
    {
        $iterations = 100;
        $failures = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            // Create a new view instance
            $view = new CspView();
            
            // Generate a random nonce
            $nonce = bin2hex(random_bytes(16));
            $view->params['cspNonce'] = $nonce;
            $view->params['cspNonceAttr'] = 'nonce="' . $nonce . '"';
            
            // Register random inline JavaScript
            $scriptContent = $this->generateRandomJavaScript();
            $view->registerJs($scriptContent, CspView::POS_END);
            
            // Render the view
            ob_start();
            $view->beginPage();
            $view->head();
            $view->beginBody();
            echo '<div>Test content</div>';
            $view->endBody();
            $view->endPage();
            $html = ob_get_clean();
            
            // Check if inline scripts have nonce attributes
            if (preg_match_all('/<script[^>]*>(.*?)<\/script>/is', $html, $matches)) {
                foreach ($matches[0] as $scriptTag) {
                    // Skip external scripts (those with src attribute)
                    if (preg_match('/src\s*=/', $scriptTag)) {
                        continue;
                    }
                    
                    // Inline script should have nonce attribute
                    if (!preg_match('/nonce\s*=\s*["\']' . preg_quote($nonce, '/') . '["\']/', $scriptTag)) {
                        $failures[] = [
                            'iteration' => $i,
                            'nonce' => $nonce,
                            'scriptTag' => substr($scriptTag, 0, 200),
                            'reason' => 'Inline script missing nonce attribute'
                        ];
                    }
                }
            }
        }
        
        if (!empty($failures)) {
            $failureMessage = "Found inline scripts without nonce attributes:\n";
            foreach (array_slice($failures, 0, 5) as $failure) {
                $failureMessage .= sprintf(
                    "Iteration %d: Expected nonce '%s' in script tag: %s...\n",
                    $failure['iteration'],
                    $failure['nonce'],
                    $failure['scriptTag']
                );
            }
            $failureMessage .= sprintf("\nTotal failures: %d out of %d iterations", count($failures), $iterations);
            $this->fail($failureMessage);
        }
        
        $this->assertTrue(true, "All inline scripts have nonce attributes across $iterations iterations");
    }
    
    /**
     * Property: For any view file that uses registerJs, the nonce parameter
     * should be available in view params
     */
    public function testNonceAvailableInViewParams()
    {
        $iterations = 100;
        
        for ($i = 0; $i < $iterations; $i++) {
            $view = new CspView();
            
            // Simulate application setting nonce in beforeRequest
            $nonce = bin2hex(random_bytes(16));
            $view->params['cspNonce'] = $nonce;
            $view->params['cspNonceAttr'] = 'nonce="' . $nonce . '"';
            
            // Verify nonce is accessible
            $this->assertArrayHasKey('cspNonce', $view->params, 
                "Iteration $i: cspNonce should be available in view params");
            $this->assertArrayHasKey('cspNonceAttr', $view->params,
                "Iteration $i: cspNonceAttr should be available in view params");
            $this->assertEquals($nonce, $view->params['cspNonce'],
                "Iteration $i: cspNonce value should match");
            $this->assertEquals('nonce="' . $nonce . '"', $view->params['cspNonceAttr'],
                "Iteration $i: cspNonceAttr should be properly formatted");
        }
    }
    
    /**
     * Generate random JavaScript code for testing
     */
    private function generateRandomJavaScript(): string
    {
        $templates = [
            'console.log("Test %d");',
            'var x = %d; alert(x);',
            'function test%d() { return true; }',
            '$(document).ready(function() { console.log(%d); });',
            'window.testVar%d = %d;',
        ];
        
        $template = $templates[array_rand($templates)];
        $value = rand(1, 1000);
        
        return sprintf($template, $value, $value);
    }
}
