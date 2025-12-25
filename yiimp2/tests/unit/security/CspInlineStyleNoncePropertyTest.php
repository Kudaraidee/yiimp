<?php

namespace tests\unit\security;

use Codeception\Test\Unit;
use app\components\CspView;

/**
 * Property Test: Inline styles have nonce attributes
 * 
 * Feature: yiimp2-nginx-migration, Property 4: Inline styles have nonce attributes
 * Validates: Requirements 2.2, 7.2
 * 
 * This test verifies that all inline style tags registered via registerCss
 * include the CSP nonce attribute when rendered.
 */
class CspInlineStyleNoncePropertyTest extends Unit
{
    protected $tester;
    
    /**
     * Property: For any view with inline styles registered via registerCss,
     * the rendered HTML should include nonce attributes on all inline style tags
     */
    public function testInlineStylesHaveNonceAttributes()
    {
        $iterations = 100;
        $failures = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            // Create a new CspView instance
            $view = new CspView();
            
            // Generate a random nonce
            $nonce = bin2hex(random_bytes(16));
            $view->params['cspNonce'] = $nonce;
            $view->params['cspNonceAttr'] = 'nonce="' . $nonce . '"';
            
            // Register random inline CSS
            $cssContent = $this->generateRandomCSS();
            $view->registerCss($cssContent);
            
            // Render the view
            ob_start();
            $view->beginPage();
            $view->head();
            $view->beginBody();
            echo '<div>Test content</div>';
            $view->endBody();
            $view->endPage();
            $html = ob_get_clean();
            
            // Check if inline styles have nonce attributes
            if (preg_match_all('/<style[^>]*>(.*?)<\/style>/is', $html, $matches)) {
                foreach ($matches[0] as $styleTag) {
                    // Inline style should have nonce attribute
                    if (!preg_match('/nonce\s*=\s*["\']' . preg_quote($nonce, '/') . '["\']/', $styleTag)) {
                        $failures[] = [
                            'iteration' => $i,
                            'nonce' => $nonce,
                            'styleTag' => substr($styleTag, 0, 200),
                            'reason' => 'Inline style missing nonce attribute'
                        ];
                    }
                }
            }
        }
        
        if (!empty($failures)) {
            $failureMessage = "Found inline styles without nonce attributes:\n";
            foreach (array_slice($failures, 0, 5) as $failure) {
                $failureMessage .= sprintf(
                    "Iteration %d: Expected nonce '%s' in style tag: %s...\n",
                    $failure['iteration'],
                    $failure['nonce'],
                    $failure['styleTag']
                );
            }
            $failureMessage .= sprintf("\nTotal failures: %d out of %d iterations", count($failures), $iterations);
            $this->fail($failureMessage);
        }
        
        $this->assertTrue(true, "All inline styles have nonce attributes across $iterations iterations");
    }
    
    /**
     * Property: For any CSS registered with registerCss, the style tag
     * should be properly formatted with nonce when rendered
     */
    public function testRegisterCssProducesNoncedStyleTags()
    {
        $iterations = 50;
        
        for ($i = 0; $i < $iterations; $i++) {
            $view = new CspView();
            
            // Set up nonce
            $nonce = bin2hex(random_bytes(16));
            $view->params['cspNonce'] = $nonce;
            $view->params['cspNonceAttr'] = 'nonce="' . $nonce . '"';
            
            // Register CSS
            $cssContent = $this->generateRandomCSS();
            $view->registerCss($cssContent);
            
            // Render
            ob_start();
            $view->beginPage();
            $view->head();
            $view->beginBody();
            $view->endBody();
            $view->endPage();
            $html = ob_get_clean();
            
            // Verify style tag exists and has nonce
            $this->assertMatchesRegularExpression(
                '/<style[^>]*nonce\s*=\s*["\']' . preg_quote($nonce, '/') . '["\'][^>]*>/',
                $html,
                "Iteration $i: Registered CSS should produce style tag with nonce attribute"
            );
        }
    }
    
    /**
     * Generate random CSS code for testing
     */
    private function generateRandomCSS(): string
    {
        $templates = [
            ['.test%d { color: #%06x; }', 2],
            ['#element%d { margin: %dpx; }', 2],
            ['body { font-size: %dpx; }', 1],
            ['.container%d { padding: %dpx; background: #%06x; }', 3],
            ['h%d { font-weight: bold; color: #%06x; }', 2],
        ];
        
        $selected = $templates[array_rand($templates)];
        $template = $selected[0];
        $argCount = $selected[1];
        
        $args = [];
        for ($i = 0; $i < $argCount; $i++) {
            $args[] = rand(1, 999999);
        }
        
        return sprintf($template, ...$args);
    }
}
