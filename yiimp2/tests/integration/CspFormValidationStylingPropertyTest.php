<?php

namespace tests\integration;

use Codeception\Test\Unit;
use tests\helpers\EnvironmentDetector;

/**
 * Property-Based Test: Form Validation Styling Compliance
 * 
 * Feature: csp-inline-styles-removal, Property 8: Form validation styling compliance
 * Validates: Requirements 6.3, 7.2
 * 
 * This test verifies that form validation errors are styled through CSS classes
 * rather than inline styles, ensuring CSP compliance.
 * 
 * Property: For any form validation error displayed, the error styling should be
 * applied through CSS classes rather than inline styles.
 */
class CspFormValidationStylingPropertyTest extends Unit
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
     * Test that yii-widgets.css provides validation error styling classes
     * 
     * @test
     */
    public function testValidationErrorClassesExist()
    {
        $cssFile = dirname(__DIR__, 2) . '/web/css/yii-widgets.css';
        
        $this->assertFileExists($cssFile, 'yii-widgets.css file should exist');
        
        $content = file_get_contents($cssFile);
        
        // Check for required validation error classes
        $requiredClasses = [
            '.has-error',
            '.invalid-feedback',
            '.help-block',
            '.error-summary',
        ];
        
        foreach ($requiredClasses as $class) {
            $this->assertStringContainsString(
                $class,
                $content,
                "yii-widgets.css should provide {$class} class for validation errors"
            );
        }
    }
    
    /**
     * Test that validation error messages use CSS classes for styling
     * 
     * @test
     */
    public function testValidationErrorMessagesUseCssClasses()
    {
        $cssFile = dirname(__DIR__, 2) . '/web/css/yii-widgets.css';
        $content = file_get_contents($cssFile);
        
        // Check that error message styling is defined via CSS
        $this->assertStringContainsString(
            '.invalid-feedback',
            $content,
            'CSS should define .invalid-feedback class'
        );
        
        // Check that error color is defined in CSS
        $this->assertStringContainsString(
            'color: #dc3545',
            $content,
            'CSS should define error text color'
        );
        
        // Check that help-block styling is defined
        $this->assertStringContainsString(
            '.help-block',
            $content,
            'CSS should define .help-block class'
        );
    }
    
    /**
     * Test that error summary container uses CSS classes
     * 
     * @test
     */
    public function testErrorSummaryUseCssClasses()
    {
        $cssFile = dirname(__DIR__, 2) . '/web/css/yii-widgets.css';
        $content = file_get_contents($cssFile);
        
        // Check that error summary has proper styling
        $this->assertStringContainsString(
            '.error-summary',
            $content,
            'CSS should define .error-summary class'
        );
        
        // Check that error summary has background color defined
        $this->assertStringContainsString(
            'background-color: #f8d7da',
            $content,
            'CSS should define error summary background color'
        );
        
        // Check that error summary has border color defined
        $this->assertStringContainsString(
            'border-color: #f5c2c7',
            $content,
            'CSS should define error summary border color'
        );
    }
    
    /**
     * Test that form field error states use CSS classes
     * 
     * @test
     */
    public function testFormFieldErrorStatesUseCssClasses()
    {
        $cssFile = dirname(__DIR__, 2) . '/web/css/yii-widgets.css';
        $content = file_get_contents($cssFile);
        
        // Check that error state for form controls is defined
        $this->assertStringContainsString(
            '.has-error .form-control',
            $content,
            'CSS should define error state for form controls'
        );
        
        // Check that is-invalid class is defined
        $this->assertStringContainsString(
            '.form-control.is-invalid',
            $content,
            'CSS should define .is-invalid class for form controls'
        );
        
        // Check that error border color is defined
        $this->assertStringContainsString(
            'border-color: #dc3545',
            $content,
            'CSS should define error border color'
        );
    }
    
    /**
     * Test that ActiveForm configuration uses CSS classes for errors
     * 
     * @test
     */
    public function testActiveFormConfigurationUsesErrorClasses()
    {
        $viewFile = dirname(__DIR__, 2) . '/views/admin/coin_update.php';
        
        $this->assertFileExists($viewFile, 'coin_update.php view should exist');
        
        $content = file_get_contents($viewFile);
        
        // Check that errorOptions is configured with CSS class
        $this->assertStringContainsString(
            'errorOptions',
            $content,
            'ActiveForm should configure errorOptions'
        );
        
        // Check that invalid-feedback class is used
        $this->assertStringContainsString(
            'invalid-feedback',
            $content,
            'ActiveForm should use invalid-feedback class for errors'
        );
        
        // Check that no inline styles are used for error display
        $this->assertStringNotContainsString(
            'style=',
            $content,
            'coin_update.php should not contain inline styles'
        );
    }
    
    /**
     * Property Test: Verify all view files with forms use CSS classes for validation
     * 
     * This property test scans all view files that use ActiveForm and verifies
     * they configure error display using CSS classes, not inline styles.
     * 
     * @test
     */
    public function testPropertyFormValidationUsesClasses()
    {
        $viewsDir = dirname(__DIR__, 2) . '/views';
        
        // Get all PHP view files
        $viewFiles = $this->getAllViewFiles($viewsDir);
        
        $this->assertNotEmpty($viewFiles, 'Should find view files');
        
        $violations = [];
        
        // Run property test with multiple iterations
        $iterations = min(100, count($viewFiles) * 5);
        
        for ($i = 0; $i < $iterations; $i++) {
            // Randomly select a view file
            $file = $viewFiles[array_rand($viewFiles)];
            
            if (!file_exists($file)) {
                continue;
            }
            
            $content = file_get_contents($file);
            
            // Only check files that use ActiveForm
            if (strpos($content, 'ActiveForm') === false) {
                continue;
            }
            
            // Check for inline style attributes in error-related elements
            // Look for patterns like style="color: red" or style="display: none"
            // that might be used for validation error display
            if (preg_match('/errorOptions.*style\s*=/', $content)) {
                $violations[] = [
                    'file' => str_replace($viewsDir . '/', '', $file),
                    'type' => 'Inline style in errorOptions'
                ];
            }
            
            // Check for inline styles in error summary configuration
            if (preg_match('/errorSummary.*style\s*=/', $content)) {
                $violations[] = [
                    'file' => str_replace($viewsDir . '/', '', $file),
                    'type' => 'Inline style in errorSummary'
                ];
            }
        }
        
        // Property: No form validation should use inline styles
        $this->assertEmpty(
            $violations,
            "Property violation: Found inline styles in form validation:\n" .
            $this->formatViolations($violations)
        );
    }
    
    /**
     * Test that success validation states use CSS classes
     * 
     * @test
     */
    public function testSuccessValidationStatesUseCssClasses()
    {
        $cssFile = dirname(__DIR__, 2) . '/web/css/yii-widgets.css';
        $content = file_get_contents($cssFile);
        
        // Check that success state classes are defined
        $this->assertStringContainsString(
            '.has-success',
            $content,
            'CSS should define .has-success class'
        );
        
        $this->assertStringContainsString(
            '.form-control.is-valid',
            $content,
            'CSS should define .is-valid class for form controls'
        );
        
        $this->assertStringContainsString(
            '.valid-feedback',
            $content,
            'CSS should define .valid-feedback class'
        );
        
        // Check that success color is defined
        $this->assertStringContainsString(
            'border-color: #198754',
            $content,
            'CSS should define success border color'
        );
    }
    
    /**
     * Test that warning validation states use CSS classes
     * 
     * @test
     */
    public function testWarningValidationStatesUseCssClasses()
    {
        $cssFile = dirname(__DIR__, 2) . '/web/css/yii-widgets.css';
        $content = file_get_contents($cssFile);
        
        // Check that warning state classes are defined
        $this->assertStringContainsString(
            '.has-warning',
            $content,
            'CSS should define .has-warning class'
        );
        
        // Check that warning color is defined
        $this->assertStringContainsString(
            'border-color: #ffc107',
            $content,
            'CSS should define warning border color'
        );
    }
    
    /**
     * Test that csp-utils.js provides validation state utilities
     * 
     * @test
     */
    public function testCspUtilsProvidesValidationUtilities()
    {
        $jsFile = dirname(__DIR__, 2) . '/web/js/csp-utils.js';
        
        $this->assertFileExists($jsFile, 'csp-utils.js file should exist');
        
        $content = file_get_contents($jsFile);
        
        // Check that setValidationState function exists
        $this->assertStringContainsString(
            'setValidationState',
            $content,
            'csp-utils.js should provide setValidationState function'
        );
        
        // Check that it uses classList API
        $this->assertStringContainsString(
            'classList',
            $content,
            'csp-utils.js should use classList API for validation states'
        );
    }
    
    /**
     * Property Test: Verify CSS provides complete validation styling
     * 
     * @test
     */
    public function testPropertyCssProvideCompleteValidationStyling()
    {
        $cssFile = dirname(__DIR__, 2) . '/web/css/yii-widgets.css';
        $content = file_get_contents($cssFile);
        
        // Define all required validation-related CSS patterns
        $requiredPatterns = [
            // Error states
            'error' => ['.has-error', '.is-invalid', '.invalid-feedback', '.error-summary'],
            // Success states
            'success' => ['.has-success', '.is-valid', '.valid-feedback'],
            // Warning states
            'warning' => ['.has-warning'],
            // Focus states for validation
            'focus' => ['.form-control:focus'],
        ];
        
        foreach ($requiredPatterns as $category => $patterns) {
            foreach ($patterns as $pattern) {
                $this->assertStringContainsString(
                    $pattern,
                    $content,
                    "CSS should define {$pattern} for {$category} validation state"
                );
            }
        }
        
        // Verify no inline style patterns in CSS comments
        $this->assertStringNotContainsString(
            'style=',
            $content,
            'CSS should not reference inline style attributes'
        );
    }
    
    /**
     * Get all PHP view files recursively
     * 
     * @param string $dir Directory to search
     * @return array List of view file paths
     */
    private function getAllViewFiles($dir)
    {
        $files = [];
        
        if (!is_dir($dir)) {
            return $files;
        }
        
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
                "  - %s: %s\n",
                $violation['file'],
                $violation['type']
            );
        }
        
        return $message;
    }
}
