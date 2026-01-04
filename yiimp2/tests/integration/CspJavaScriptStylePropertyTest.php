<?php

namespace tests\integration;

use Codeception\Test\Unit;
use tests\helpers\EnvironmentDetector;

/**
 * Property-Based Test: JavaScript Style Manipulation Compliance
 * 
 * Feature: csp-inline-styles-removal, Property 6: JavaScript style manipulation compliance
 * Validates: Requirements 6.4
 * 
 * This test verifies that JavaScript code does not manipulate element styles
 * directly through the style attribute, but instead uses CSS class manipulation
 * which is CSP-compliant.
 * 
 * Property: For any JavaScript code in the application, it should use CSS class
 * manipulation (classList.add/remove/toggle) instead of direct style attribute
 * modification (.style. or setAttribute('style')).
 */
class CspJavaScriptStylePropertyTest extends Unit
{
    /**
     * @var \IntegrationTester
     */
    protected $tester;
    
    /**
     * Skip test if not in appropriate environment
     */
    protected function _before()
    {
        if (!EnvironmentDetector::shouldRunIntegrationTests()) {
            $this->markTestSkipped('Integration tests disabled in this environment');
        }
    }
    
    /**
     * Test that custom JavaScript files do not use direct style manipulation
     * 
     * @test
     */
    public function testCustomJavaScriptUsesClassListApi()
    {
        // Use relative path from test directory
        $jsDir = dirname(__DIR__, 2) . '/web/js';
        
        // Get all custom JavaScript files (excluding vendor libraries)
        $customJsFiles = [
            $jsDir . '/admin.js',
            $jsDir . '/auto_refresh.js',
            $jsDir . '/bookmark-autoload.js',
            $jsDir . '/bookmarks.js',
            $jsDir . '/chart-helpers.js',
            $jsDir . '/gridview-enhanced.js',
            $jsDir . '/csp-utils.js',
        ];
        
        $violations = [];
        
        foreach ($customJsFiles as $file) {
            if (!file_exists($file)) {
                continue;
            }
            
            $content = file_get_contents($file);
            $lines = explode("\n", $content);
            
            foreach ($lines as $lineNum => $line) {
                // Skip comments
                if (preg_match('/^\s*\/\//', $line) || preg_match('/^\s*\*/', $line)) {
                    continue;
                }
                
                // Check for direct style manipulation patterns
                // Pattern 1: element.style.property = value
                if (preg_match('/\.style\.\w+\s*=/', $line)) {
                    $violations[] = [
                        'file' => basename($file),
                        'line' => $lineNum + 1,
                        'code' => trim($line),
                        'pattern' => 'Direct style property assignment'
                    ];
                }
                
                // Pattern 2: element.setAttribute('style', ...)
                if (preg_match('/\.setAttribute\s*\(\s*[\'"]style[\'"]/', $line)) {
                    $violations[] = [
                        'file' => basename($file),
                        'line' => $lineNum + 1,
                        'code' => trim($line),
                        'pattern' => 'setAttribute with style attribute'
                    ];
                }
                
                // Pattern 3: element.style.cssText = ...
                if (preg_match('/\.style\.cssText\s*=/', $line)) {
                    $violations[] = [
                        'file' => basename($file),
                        'line' => $lineNum + 1,
                        'code' => trim($line),
                        'pattern' => 'Direct cssText manipulation'
                    ];
                }
            }
        }
        
        // Assert no violations found
        $this->assertEmpty(
            $violations,
            "Found direct style manipulation in JavaScript files:\n" . 
            $this->formatViolations($violations)
        );
    }
    
    /**
     * Test that csp-utils.js provides required utility functions
     * 
     * @test
     */
    public function testCspUtilsProvideRequiredFunctions()
    {
        $cspUtilsFile = dirname(__DIR__, 2) . '/web/js/csp-utils.js';
        
        $this->assertFileExists($cspUtilsFile, 'csp-utils.js file should exist');
        
        $content = file_get_contents($cspUtilsFile);
        
        // Check for required utility functions
        $requiredFunctions = [
            'addClass',
            'removeClass',
            'toggleClass',
            'hasClass',
            'show',
            'hide',
            'setValidationState',
        ];
        
        foreach ($requiredFunctions as $function) {
            $this->assertStringContainsString(
                $function . ':',
                $content,
                "csp-utils.js should provide {$function} function"
            );
        }
        
        // Check that StyleUtils is exported to global scope
        $this->assertStringContainsString(
            'window.StyleUtils',
            $content,
            'StyleUtils should be exported to global scope'
        );
    }
    
    /**
     * Test that StyleUtils functions use classList API
     * 
     * @test
     */
    public function testStyleUtilsUsesClassListApi()
    {
        $cspUtilsFile = dirname(__DIR__, 2) . '/web/js/csp-utils.js';
        $content = file_get_contents($cspUtilsFile);
        
        // Verify that StyleUtils uses classList API
        $this->assertStringContainsString(
            'classList.add',
            $content,
            'StyleUtils should use classList.add'
        );
        
        $this->assertStringContainsString(
            'classList.remove',
            $content,
            'StyleUtils should use classList.remove'
        );
        
        $this->assertStringContainsString(
            'classList.toggle',
            $content,
            'StyleUtils should use classList.toggle'
        );
        
        // Verify that StyleUtils does NOT use direct style manipulation
        // (except in documentation/comments)
        $lines = explode("\n", $content);
        $codeLines = array_filter($lines, function($line) {
            // Filter out comments and documentation
            return !preg_match('/^\s*\/\//', $line) && 
                   !preg_match('/^\s*\*/', $line) &&
                   !preg_match('/^\s*\/\*/', $line);
        });
        
        $codeContent = implode("\n", $codeLines);
        
        // StyleUtils should not use .style. in actual code
        $this->assertStringNotContainsString(
            '.style.',
            $codeContent,
            'StyleUtils should not use direct style manipulation in implementation'
        );
    }
    
    /**
     * Property Test: Verify no inline style manipulation across random JavaScript files
     * 
     * This property test generates random selections of JavaScript files and verifies
     * that none of them use direct style manipulation patterns.
     * 
     * @test
     */
    public function testPropertyNoInlineStyleManipulationInAnyJsFile()
    {
        // Use relative path from test directory
        $jsDir = dirname(__DIR__, 2) . '/web/js';
        
        // Get all JavaScript files (excluding vendor directories)
        $allJsFiles = $this->getAllCustomJsFiles($jsDir);
        
        $this->assertNotEmpty($allJsFiles, 'Should find custom JavaScript files');
        
        // Run property test with multiple iterations
        $iterations = min(100, count($allJsFiles) * 10);
        $violations = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            // Randomly select a file
            $file = $allJsFiles[array_rand($allJsFiles)];
            
            if (!file_exists($file)) {
                continue;
            }
            
            $content = file_get_contents($file);
            $lines = explode("\n", $content);
            
            foreach ($lines as $lineNum => $line) {
                // Skip comments and documentation
                if (preg_match('/^\s*\/\//', $line) || 
                    preg_match('/^\s*\*/', $line) ||
                    preg_match('/^\s*\/\*/', $line)) {
                    continue;
                }
                
                // Check for CSP-violating patterns
                if (preg_match('/\.style\.\w+\s*=/', $line) ||
                    preg_match('/\.setAttribute\s*\(\s*[\'"]style[\'"]/', $line) ||
                    preg_match('/\.style\.cssText\s*=/', $line)) {
                    
                    $violations[] = [
                        'file' => str_replace($jsDir . '/', '', $file),
                        'line' => $lineNum + 1,
                        'code' => trim($line)
                    ];
                }
            }
        }
        
        // Property: No JavaScript file should contain direct style manipulation
        $this->assertEmpty(
            $violations,
            "Property violation: Found direct style manipulation in JavaScript files:\n" .
            $this->formatViolations($violations)
        );
    }
    
    /**
     * Get all custom JavaScript files (excluding vendor libraries)
     * 
     * @param string $dir Directory to search
     * @return array List of JavaScript file paths
     */
    private function getAllCustomJsFiles($dir)
    {
        $files = [];
        
        if (!is_dir($dir)) {
            return $files;
        }
        
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'js') {
                $path = $file->getPathname();
                
                // Exclude vendor libraries and minified files
                if (strpos($path, 'vendor') === false &&
                    strpos($path, 'node_modules') === false &&
                    strpos($path, '.min.js') === false &&
                    strpos($path, 'jquery') === false &&
                    strpos($path, 'jqplot') === false) {
                    
                    $files[] = $path;
                }
            }
        }
        
        return $files;
    }
    
    /**
     * Format violations for error message
     * 
     * @param array $violations List of violations
     * @return string Formatted violation message
     */
    private function formatViolations($violations)
    {
        if (empty($violations)) {
            return '';
        }
        
        $message = '';
        foreach ($violations as $violation) {
            $message .= sprintf(
                "  - %s:%d - %s\n    Code: %s\n",
                $violation['file'],
                $violation['line'],
                $violation['pattern'] ?? 'Direct style manipulation',
                $violation['code']
            );
        }
        
        return $message;
    }
}
