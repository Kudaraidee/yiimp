<?php

namespace tests\integration;

use Codeception\Test\Unit;

/**
 * Integration Test: Coin Creation Form CSP Compliance
 * 
 * Feature: csp-inline-styles-removal
 * Validates: Requirements 6.5, 7.1, 7.2, 7.4, 7.5
 * 
 * This test verifies that the coin creation form:
 * - Renders without inline styles
 * - Displays validation errors using CSS classes
 * - Has properly functioning algorithm dropdown
 * - Is fully CSP-compliant
 */
class CoinCreationFormCspTest extends Unit
{
    /**
     * @var \IntegrationTester
     */
    protected $tester;
    
    /**
     * Setup before each test
     * 
     * Note: These tests are file-based and don't require a running server,
     * so they can run in any environment.
     */
    protected function _before()
    {
        // These tests are file-based and can run in any environment
        // No need to skip based on environment
    }
    
    /**
     * Test that coin_update.php view file contains no inline styles
     * 
     * Validates: Requirements 7.1, 7.5
     * 
     * @test
     */
    public function testCoinUpdateViewHasNoInlineStyles()
    {
        $viewFile = dirname(__DIR__, 2) . '/views/admin/coin_update.php';
        
        $this->assertFileExists($viewFile, 'coin_update.php view should exist');
        
        $content = file_get_contents($viewFile);
        
        // Check for inline style attributes
        $this->assertStringNotContainsString(
            'style=',
            $content,
            'coin_update.php should not contain any inline style attributes'
        );
        
        // Check for inline style in PHP echo statements
        $this->assertDoesNotMatchRegularExpression(
            '/echo\s+[^;]*style\s*=/i',
            $content,
            'coin_update.php should not echo inline styles'
        );
    }
    
    /**
     * Test that coin_update.php registers CspCompliantAsset
     * 
     * Validates: Requirements 7.1
     * 
     * @test
     */
    public function testCoinUpdateViewRegistersCspCompliantAsset()
    {
        $viewFile = dirname(__DIR__, 2) . '/views/admin/coin_update.php';
        $content = file_get_contents($viewFile);
        
        // Check that CspCompliantAsset is imported
        $this->assertStringContainsString(
            'use app\assets\CspCompliantAsset',
            $content,
            'coin_update.php should import CspCompliantAsset'
        );
        
        // Check that CspCompliantAsset is registered
        $this->assertStringContainsString(
            'CspCompliantAsset::register',
            $content,
            'coin_update.php should register CspCompliantAsset'
        );
    }
    
    /**
     * Test that ActiveForm is configured with CSS classes for validation
     * 
     * Validates: Requirements 7.1, 7.2
     * 
     * @test
     */
    public function testActiveFormConfiguredWithCssClasses()
    {
        $viewFile = dirname(__DIR__, 2) . '/views/admin/coin_update.php';
        $content = file_get_contents($viewFile);
        
        // Check that ActiveForm uses fieldConfig
        $this->assertStringContainsString(
            'fieldConfig',
            $content,
            'ActiveForm should use fieldConfig for CSS class configuration'
        );
        
        // Check that errorOptions uses CSS class
        $this->assertStringContainsString(
            'errorOptions',
            $content,
            'ActiveForm should configure errorOptions'
        );
        
        // Check that invalid-feedback class is used for errors
        $this->assertStringContainsString(
            'invalid-feedback',
            $content,
            'ActiveForm should use invalid-feedback class for error messages'
        );
        
        // Check that form-control class is used for inputs
        $this->assertStringContainsString(
            'form-control',
            $content,
            'ActiveForm should use form-control class for inputs'
        );
        
        // Check that form-group class is used for field wrappers
        $this->assertStringContainsString(
            'form-group',
            $content,
            'ActiveForm should use form-group class for field wrappers'
        );
        
        // Check that form-label class is used for labels
        $this->assertStringContainsString(
            'form-label',
            $content,
            'ActiveForm should use form-label class for labels'
        );
    }
    
    /**
     * Test that algorithm dropdown uses dropDownList widget
     * 
     * Validates: Requirements 7.4
     * 
     * @test
     */
    public function testAlgorithmDropdownUsesDropDownList()
    {
        $viewFile = dirname(__DIR__, 2) . '/views/admin/coin_update.php';
        $content = file_get_contents($viewFile);
        
        // Check that dropDownList is used for algo field
        $this->assertStringContainsString(
            'dropDownList',
            $content,
            'Algorithm field should use dropDownList widget'
        );
        
        // Check that Algos model is used for dropdown options
        $this->assertStringContainsString(
            'Algos::find()',
            $content,
            'Algorithm dropdown should use Algos model for options'
        );
        
        // Check that ArrayHelper::map is used for dropdown data
        $this->assertStringContainsString(
            'ArrayHelper::map',
            $content,
            'Algorithm dropdown should use ArrayHelper::map for data'
        );
    }
    
    /**
     * Test that yii-widgets.css provides all required form classes
     * 
     * Validates: Requirements 7.1, 7.2
     * 
     * @test
     */
    public function testYiiWidgetsCssProvidesRequiredFormClasses()
    {
        $cssFile = dirname(__DIR__, 2) . '/web/css/yii-widgets.css';
        
        $this->assertFileExists($cssFile, 'yii-widgets.css should exist');
        
        $content = file_get_contents($cssFile);
        
        // Required classes for form rendering
        $requiredClasses = [
            '.form-control',
            '.form-control:focus',
            '.form-control:disabled',
            '.form-label',
            '.control-label',
            '.btn',
            '.btn-primary',
            'fieldset',
            'fieldset.inlineLabels',
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
     * Test that yii-widgets.css provides validation error classes
     * 
     * Validates: Requirements 7.2
     * 
     * @test
     */
    public function testYiiWidgetsCssProvidesValidationErrorClasses()
    {
        $cssFile = dirname(__DIR__, 2) . '/web/css/yii-widgets.css';
        $content = file_get_contents($cssFile);
        
        // Required validation error classes
        $requiredClasses = [
            '.has-error',
            '.is-invalid',
            '.invalid-feedback',
            '.help-block',
            '.error-summary',
        ];
        
        foreach ($requiredClasses as $class) {
            $this->assertStringContainsString(
                $class,
                $content,
                "yii-widgets.css should provide {$class} validation class"
            );
        }
        
        // Check that error border color is defined
        $this->assertStringContainsString(
            'border-color: #dc3545',
            $content,
            'yii-widgets.css should define error border color'
        );
        
        // Check that error text color is defined
        $this->assertStringContainsString(
            'color: #dc3545',
            $content,
            'yii-widgets.css should define error text color'
        );
    }
    
    /**
     * Test that yii-widgets.css provides dropdown styling classes
     * 
     * Validates: Requirements 7.4
     * 
     * @test
     */
    public function testYiiWidgetsCssProvidesDropdownClasses()
    {
        $cssFile = dirname(__DIR__, 2) . '/web/css/yii-widgets.css';
        $content = file_get_contents($cssFile);
        
        // Required dropdown classes
        $requiredClasses = [
            'select.form-control',
            '.dropdown-menu',
            '.dropdown-item',
        ];
        
        foreach ($requiredClasses as $class) {
            $this->assertStringContainsString(
                $class,
                $content,
                "yii-widgets.css should provide {$class} dropdown class"
            );
        }
        
        // Check that dropdown positioning is defined
        $this->assertMatchesRegularExpression(
            '/\.dropdown-menu\s*\{[^}]*position\s*:\s*absolute/s',
            $content,
            'Dropdown menu should have absolute positioning'
        );
        
        // Check that dropdown z-index is defined
        $this->assertMatchesRegularExpression(
            '/\.dropdown-menu\s*\{[^}]*z-index\s*:\s*\d+/s',
            $content,
            'Dropdown menu should have z-index defined'
        );
    }
    
    /**
     * Test that csp-utils.js provides form validation utilities
     * 
     * Validates: Requirements 7.2
     * 
     * @test
     */
    public function testCspUtilsProvidesFormValidationUtilities()
    {
        $jsFile = dirname(__DIR__, 2) . '/web/js/csp-utils.js';
        
        $this->assertFileExists($jsFile, 'csp-utils.js should exist');
        
        $content = file_get_contents($jsFile);
        
        // Check for validation state utilities
        $this->assertStringContainsString(
            'setValidationState',
            $content,
            'csp-utils.js should provide setValidationState function'
        );
        
        // Check for FormUtils object
        $this->assertStringContainsString(
            'FormUtils',
            $content,
            'csp-utils.js should provide FormUtils object'
        );
        
        // Check for showFieldError function
        $this->assertStringContainsString(
            'showFieldError',
            $content,
            'csp-utils.js should provide showFieldError function'
        );
        
        // Check for clearFieldError function
        $this->assertStringContainsString(
            'clearFieldError',
            $content,
            'csp-utils.js should provide clearFieldError function'
        );
        
        // Check that classList API is used (not direct style manipulation)
        $this->assertStringContainsString(
            'classList',
            $content,
            'csp-utils.js should use classList API for style manipulation'
        );
        
        // Check that no direct style manipulation is used
        $this->assertStringNotContainsString(
            '.style.',
            $content,
            'csp-utils.js should not use direct style manipulation'
        );
    }
    
    /**
     * Test that CspCompliantAsset bundle is properly configured
     * 
     * Validates: Requirements 7.1
     * 
     * @test
     */
    public function testCspCompliantAssetBundleConfiguration()
    {
        $assetFile = dirname(__DIR__, 2) . '/assets/CspCompliantAsset.php';
        
        $this->assertFileExists($assetFile, 'CspCompliantAsset.php should exist');
        
        $content = file_get_contents($assetFile);
        
        // Check that it extends AssetBundle
        $this->assertStringContainsString(
            'extends AssetBundle',
            $content,
            'CspCompliantAsset should extend AssetBundle'
        );
        
        // Check that yii-widgets.css is registered
        $this->assertStringContainsString(
            'yii-widgets.css',
            $content,
            'CspCompliantAsset should register yii-widgets.css'
        );
        
        // Check that csp-utils.js is registered
        $this->assertStringContainsString(
            'csp-utils.js',
            $content,
            'CspCompliantAsset should register csp-utils.js'
        );
        
        // Check that it depends on YiiAsset
        $this->assertStringContainsString(
            'YiiAsset',
            $content,
            'CspCompliantAsset should depend on YiiAsset'
        );
        
        // Check that it depends on BootstrapAsset
        $this->assertStringContainsString(
            'BootstrapAsset',
            $content,
            'CspCompliantAsset should depend on BootstrapAsset'
        );
    }
    
    /**
     * Test that form submit button uses CSS classes
     * 
     * Validates: Requirements 7.1
     * 
     * @test
     */
    public function testFormSubmitButtonUsesCssClasses()
    {
        $viewFile = dirname(__DIR__, 2) . '/views/admin/coin_update.php';
        $content = file_get_contents($viewFile);
        
        // Check that submitButton uses btn class
        $this->assertStringContainsString(
            'btn btn-primary',
            $content,
            'Submit button should use btn btn-primary classes'
        );
        
        // Check that Html::submitButton is used
        $this->assertStringContainsString(
            'Html::submitButton',
            $content,
            'Form should use Html::submitButton'
        );
    }
    
    /**
     * Test that form uses yiimp-form class
     * 
     * Validates: Requirements 7.1
     * 
     * @test
     */
    public function testFormUsesYiimpFormClass()
    {
        $viewFile = dirname(__DIR__, 2) . '/views/admin/coin_update.php';
        $content = file_get_contents($viewFile);
        
        // Check that form has yiimp-form class
        $this->assertStringContainsString(
            'yiimp-form',
            $content,
            'Form should use yiimp-form class'
        );
    }
    
    /**
     * Test that error summary is configured
     * 
     * Validates: Requirements 7.2
     * 
     * @test
     */
    public function testErrorSummaryIsConfigured()
    {
        $viewFile = dirname(__DIR__, 2) . '/views/admin/coin_update.php';
        $content = file_get_contents($viewFile);
        
        // Check that errorSummary is used
        $this->assertStringContainsString(
            'errorSummary',
            $content,
            'Form should use errorSummary for displaying validation errors'
        );
    }
    
    /**
     * Property Test: Verify all admin form views are CSP-compliant
     * 
     * This property test scans all admin view files that use ActiveForm
     * and verifies they don't contain inline style attributes.
     * 
     * Validates: Requirements 7.5
     * 
     * @test
     */
    public function testPropertyAdminFormViewsAreCspCompliant()
    {
        $adminViewsDir = dirname(__DIR__, 2) . '/views/admin';
        
        // Get all PHP view files in admin directory
        $viewFiles = $this->getAllViewFiles($adminViewsDir);
        
        $this->assertNotEmpty($viewFiles, 'Should find admin view files');
        
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
            
            // Check for inline style attributes
            if (preg_match_all('/style\s*=\s*["\'][^"\']*["\']/', $content, $matches)) {
                foreach ($matches[0] as $match) {
                    $violations[] = [
                        'file' => str_replace($adminViewsDir . '/', '', $file),
                        'pattern' => $match,
                        'type' => 'Inline style in admin form view'
                    ];
                }
            }
        }
        
        // Property: No admin form view should contain inline styles
        $this->assertEmpty(
            $violations,
            "Property violation: Found inline styles in admin form views:\n" .
            $this->formatViolations($violations)
        );
    }
    
    /**
     * Test that client validation is enabled
     * 
     * Validates: Requirements 7.2
     * 
     * @test
     */
    public function testClientValidationIsEnabled()
    {
        $viewFile = dirname(__DIR__, 2) . '/views/admin/coin_update.php';
        $content = file_get_contents($viewFile);
        
        // Check that enableClientValidation is set to true
        $this->assertStringContainsString(
            'enableClientValidation',
            $content,
            'ActiveForm should have enableClientValidation configured'
        );
        
        // Check that validateOnSubmit is set to true
        $this->assertStringContainsString(
            'validateOnSubmit',
            $content,
            'ActiveForm should have validateOnSubmit configured'
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
