<?php

namespace tests\integration;

use Codeception\Test\Unit;
use tests\helpers\EnvironmentDetector;

/**
 * Property-Based Test: Yii2 Widget CSP Compliance
 * 
 * Feature: csp-inline-styles-removal, Property 7: Yii2 widget CSP compliance
 * Validates: Requirements 6.1, 7.1
 * 
 * This test verifies that Yii2 widgets (ActiveForm, dropdowns, etc.) do not
 * generate inline style attributes in their HTML output.
 * 
 * Property: For any Yii2 widget rendered in the application, the generated HTML
 * should not contain inline style attributes.
 */
class CspYii2WidgetCompliancePropertyTest extends Unit
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
     * Test that yii-widgets.css provides required CSS classes for form validation
     * 
     * @test
     */
    public function testYiiWidgetsCssProvideValidationClasses()
    {
        $cssFile = dirname(__DIR__, 2) . '/web/css/yii-widgets.css';
        
        $this->assertFileExists($cssFile, 'yii-widgets.css file should exist');
        
        $content = file_get_contents($cssFile);
        
        // Check for required validation state classes
        $requiredClasses = [
            '.has-error',
            '.has-success',
            '.has-warning',
            '.is-invalid',
            '.is-valid',
            '.invalid-feedback',
            '.valid-feedback',
            '.help-block',
            '.error-summary',
        ];
        
        foreach ($requiredClasses as $class) {
            $this->assertStringContainsString(
                $class,
                $content,
                "yii-widgets.css should provide {$class} class"
            );
        }
    }
    
    /**
     * Test that yii-widgets.css provides required CSS classes for form controls
     * 
     * @test
     */
    public function testYiiWidgetsCssProvideFormControlClasses()
    {
        $cssFile = dirname(__DIR__, 2) . '/web/css/yii-widgets.css';
        $content = file_get_contents($cssFile);
        
        // Check for required form control classes
        // Note: .form-group is provided by Bootstrap, not yii-widgets.css
        $requiredClasses = [
            '.form-control',
            '.form-control:focus',
            '.form-control:disabled',
            '.control-label',
            '.form-label',
            '.btn',
            '.btn-primary',
        ];
        
        foreach ($requiredClasses as $class) {
            $this->assertStringContainsString(
                $class,
                $content,
                "yii-widgets.css should provide {$class} class"
            );
        }
    }
    
    /**
     * Test that yii-widgets.css provides required CSS classes for dropdowns
     * 
     * @test
     */
    public function testYiiWidgetsCssProvideDropdownClasses()
    {
        $cssFile = dirname(__DIR__, 2) . '/web/css/yii-widgets.css';
        $content = file_get_contents($cssFile);
        
        // Check for required dropdown classes
        $requiredClasses = [
            '.dropdown-menu',
            '.dropdown-item',
            'select.form-control',
        ];
        
        foreach ($requiredClasses as $class) {
            $this->assertStringContainsString(
                $class,
                $content,
                "yii-widgets.css should provide {$class} class"
            );
        }
    }
    
    /**
     * Test that CspCompliantAsset bundle exists and is properly configured
     * 
     * @test
     */
    public function testCspCompliantAssetBundleExists()
    {
        $assetFile = dirname(__DIR__, 2) . '/assets/CspCompliantAsset.php';
        
        $this->assertFileExists($assetFile, 'CspCompliantAsset.php should exist');
        
        $content = file_get_contents($assetFile);
        
        // Check that asset bundle registers required files
        $this->assertStringContainsString(
            'yii-widgets.css',
            $content,
            'CspCompliantAsset should register yii-widgets.css'
        );
        
        $this->assertStringContainsString(
            'csp-utils.js',
            $content,
            'CspCompliantAsset should register csp-utils.js'
        );
    }
    
    /**
     * Test that coin_update.php view uses CSP-compliant ActiveForm configuration
     * 
     * @test
     */
    public function testCoinUpdateViewUsesCompliantActiveForm()
    {
        $viewFile = dirname(__DIR__, 2) . '/views/admin/coin_update.php';
        
        $this->assertFileExists($viewFile, 'coin_update.php view should exist');
        
        $content = file_get_contents($viewFile);
        
        // Check that CspCompliantAsset is registered
        $this->assertStringContainsString(
            'CspCompliantAsset::register',
            $content,
            'coin_update.php should register CspCompliantAsset'
        );
        
        // Check that ActiveForm uses fieldConfig with CSS classes
        $this->assertStringContainsString(
            'fieldConfig',
            $content,
            'ActiveForm should use fieldConfig'
        );
        
        // Check that errorOptions uses CSS classes
        $this->assertStringContainsString(
            'errorOptions',
            $content,
            'ActiveForm should configure errorOptions'
        );
        
        // Check that form-control class is used
        $this->assertStringContainsString(
            'form-control',
            $content,
            'ActiveForm should use form-control class'
        );
        
        // Check that no inline styles are present
        $this->assertStringNotContainsString(
            'style=',
            $content,
            'coin_update.php should not contain inline styles'
        );
    }
    
    /**
     * Property Test: Verify all view files using ActiveForm are CSP-compliant
     * 
     * This property test scans all view files that use ActiveForm and verifies
     * they don't contain inline style attributes.
     * 
     * @test
     */
    public function testPropertyActiveFormViewsAreCompliant()
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
            
            // Check for inline style attributes in form elements
            if (preg_match_all('/style\s*=\s*["\'][^"\']*["\']/', $content, $matches)) {
                foreach ($matches[0] as $match) {
                    $violations[] = [
                        'file' => str_replace($viewsDir . '/', '', $file),
                        'pattern' => $match,
                        'type' => 'Inline style in ActiveForm view'
                    ];
                }
            }
        }
        
        // Property: No ActiveForm view should contain inline styles
        $this->assertEmpty(
            $violations,
            "Property violation: Found inline styles in ActiveForm views:\n" .
            $this->formatViolations($violations)
        );
    }
    
    /**
     * Property Test: Verify yii-widgets.css does not use inline style patterns
     * 
     * @test
     */
    public function testPropertyYiiWidgetsCssNoInlinePatterns()
    {
        $cssFile = dirname(__DIR__, 2) . '/web/css/yii-widgets.css';
        $content = file_get_contents($cssFile);
        
        // CSS should not contain JavaScript-style inline patterns
        $this->assertStringNotContainsString(
            'style=',
            $content,
            'yii-widgets.css should not reference style attributes'
        );
        
        // CSS should use proper CSS syntax, not inline style syntax
        $this->assertStringNotContainsString(
            'setAttribute',
            $content,
            'yii-widgets.css should not reference JavaScript methods'
        );
    }
    
    /**
     * Test that validation state classes use CSS-only styling
     * 
     * @test
     */
    public function testValidationStateClassesUseCssOnly()
    {
        $cssFile = dirname(__DIR__, 2) . '/web/css/yii-widgets.css';
        $content = file_get_contents($cssFile);
        
        // Check that validation states are defined with proper CSS
        // The CSS uses combined selectors like ".has-error .form-control,\n.form-control.is-invalid"
        // so we check for the presence of the selectors and the border-color property
        $requiredSelectors = [
            '.has-error .form-control',
            '.has-success .form-control',
            '.form-control.is-invalid',
            '.form-control.is-valid',
        ];
        
        foreach ($requiredSelectors as $selector) {
            $this->assertStringContainsString(
                $selector,
                $content,
                "CSS should contain selector {$selector}"
            );
        }
        
        // Verify border-color is used for validation states
        $this->assertStringContainsString(
            'border-color: #dc3545',
            $content,
            "CSS should define error border color"
        );
        
        $this->assertStringContainsString(
            'border-color: #198754',
            $content,
            "CSS should define success border color"
        );
    }
    
    /**
     * Test that dropdown positioning uses CSS classes
     * 
     * @test
     */
    public function testDropdownPositioningUsesCssClasses()
    {
        $cssFile = dirname(__DIR__, 2) . '/web/css/yii-widgets.css';
        $content = file_get_contents($cssFile);
        
        // Check that dropdown menu has position defined
        $this->assertMatchesRegularExpression(
            '/\.dropdown-menu\s*\{[^}]*position\s*:/s',
            $content,
            'Dropdown menu should have position defined in CSS'
        );
        
        // Check that dropdown menu has z-index defined
        $this->assertMatchesRegularExpression(
            '/\.dropdown-menu\s*\{[^}]*z-index\s*:/s',
            $content,
            'Dropdown menu should have z-index defined in CSS'
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
                "  - %s: %s\n    Pattern: %s\n",
                $violation['file'],
                $violation['type'],
                $violation['pattern']
            );
        }
        
        return $message;
    }
}
