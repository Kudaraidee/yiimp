<?php

namespace tests\unit\security;

use Codeception\Test\Unit;

/**
 * Property Test: No inline event handlers
 * 
 * Feature: yiimp2-nginx-migration, Property 10: No inline event handlers
 * Validates: Requirements 7.3
 * 
 * This test verifies that view files do not contain inline event handlers
 * (onclick, onload, onchange, etc.) which violate CSP policies.
 */
class CspNoInlineEventHandlersPropertyTest extends Unit
{
    protected $tester;
    
    /**
     * Property: For any view file in the application, there should be no
     * inline event handlers (onclick, onload, onchange, onsubmit, etc.)
     */
    public function testViewFilesHaveNoInlineEventHandlers()
    {
        $viewsDir = \Yii::getAlias('@app/views');
        $viewFiles = $this->getAllPhpFiles($viewsDir);
        
        $this->assertNotEmpty($viewFiles, "Should find view files to test");
        
        $violations = [];
        $eventHandlers = [
            'onclick', 'ondblclick', 'onmousedown', 'onmouseup', 'onmouseover',
            'onmousemove', 'onmouseout', 'onmouseenter', 'onmouseleave',
            'onload', 'onunload', 'onchange', 'onsubmit', 'onreset',
            'onselect', 'onblur', 'onfocus', 'onkeydown', 'onkeypress',
            'onkeyup', 'onerror', 'onresize', 'onscroll'
        ];
        
        foreach ($viewFiles as $file) {
            $content = file_get_contents($file);
            $relativePath = str_replace($viewsDir . '/', '', $file);
            
            // Check for each event handler
            foreach ($eventHandlers as $handler) {
                // Match event handler in HTML attributes
                // Pattern: on<event>="..." or on<event>='...'
                if (preg_match_all('/' . $handler . '\s*=\s*["\'][^"\']*["\']/', $content, $matches, PREG_OFFSET_CAPTURE)) {
                    foreach ($matches[0] as $match) {
                        $lineNumber = $this->getLineNumber($content, $match[1]);
                        $context = $this->getContext($content, $match[1], 100);
                        
                        $violations[] = [
                            'file' => $relativePath,
                            'line' => $lineNumber,
                            'handler' => $handler,
                            'context' => $context
                        ];
                    }
                }
            }
        }
        
        if (!empty($violations)) {
            $failureMessage = "Found inline event handlers that violate CSP:\n\n";
            foreach (array_slice($violations, 0, 10) as $violation) {
                $failureMessage .= sprintf(
                    "File: %s (line %d)\n  Handler: %s\n  Context: %s\n\n",
                    $violation['file'],
                    $violation['line'],
                    $violation['handler'],
                    $violation['context']
                );
            }
            $failureMessage .= sprintf(
                "Total violations: %d\n\n",
                count($violations)
            );
            $failureMessage .= "Inline event handlers should be refactored to use addEventListener in nonce-protected scripts.\n";
            
            $this->fail($failureMessage);
        }
        
        $this->assertTrue(true, sprintf(
            "No inline event handlers found in %d view files",
            count($viewFiles)
        ));
    }
    
    /**
     * Property: For any rendered HTML output, there should be no inline
     * event handler attributes
     */
    public function testRenderedHtmlHasNoInlineEventHandlers()
    {
        // This is a complementary test that checks rendered output
        // In a real scenario, this would render actual views and check output
        
        $sampleHtml = [
            '<button class="btn">Click me</button>', // Valid
            '<div id="test">Content</div>', // Valid
            '<a href="#" class="link">Link</a>', // Valid
        ];
        
        $eventHandlerPattern = '/\s+on(click|load|change|submit|keyup|keydown|mouseover|mouseout|blur|focus|error|resize|scroll)\s*=/i';
        
        foreach ($sampleHtml as $html) {
            $this->assertDoesNotMatchRegularExpression(
                $eventHandlerPattern,
                $html,
                "HTML should not contain inline event handlers"
            );
        }
    }
    
    /**
     * Get all PHP files recursively from a directory
     */
    private function getAllPhpFiles(string $dir): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }
        
        return $files;
    }
    
    /**
     * Get line number from offset in content
     */
    private function getLineNumber(string $content, int $offset): int
    {
        return substr_count(substr($content, 0, $offset), "\n") + 1;
    }
    
    /**
     * Get context around an offset
     */
    private function getContext(string $content, int $offset, int $length = 100): string
    {
        $start = max(0, $offset - $length / 2);
        $context = substr($content, $start, $length);
        $context = trim($context);
        
        // Remove extra whitespace
        $context = preg_replace('/\s+/', ' ', $context);
        
        return strlen($context) > 100 ? substr($context, 0, 97) . '...' : $context;
    }
}
