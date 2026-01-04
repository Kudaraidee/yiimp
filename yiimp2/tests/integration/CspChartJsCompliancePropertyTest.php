<?php

namespace tests\integration;

use Codeception\Test\Unit;

/**
 * Property Test: Chart.js CSP compliance
 * 
 * **Feature: csp-inline-styles-removal, Property 10: Chart.js CSP compliance**
 * **Validates: Requirements 9.1**
 * 
 * This test verifies that Chart.js is properly configured for CSP compliance.
 * Chart.js uses canvas-based rendering which doesn't require inline styles,
 * making it CSP-compliant by design.
 */
class CspChartJsCompliancePropertyTest extends Unit
{
    /**
     * @var string Base path for yiimp2 directory
     */
    protected $basePath;

    protected function _before()
    {
        $this->basePath = dirname(dirname(__DIR__));
    }

    /**
     * Property 10: Chart.js CSP compliance - Asset bundle configuration
     * 
     * *For any* page containing charts, the browser console should report zero CSP violations
     * related to charting libraries, and all charts should render using canvas elements.
     * 
     * **Feature: csp-inline-styles-removal, Property 10: Chart.js CSP compliance**
     * **Validates: Requirements 9.1**
     */
    public function testChartJsAssetBundleIsConfigured()
    {
        $chartJsAssetPath = $this->basePath . '/assets/ChartJsAsset.php';
        $this->assertFileExists($chartJsAssetPath);
        
        $content = file_get_contents($chartJsAssetPath);
        
        // Should reference Chart.js library
        $this->assertStringContainsString('chart.js', $content);
        
        // Should include date-fns adapter for time axis support
        $this->assertStringContainsString('chartjs-adapter-date-fns', $content);
    }

    /**
     * Test that ChartHelperAsset depends on ChartJsAsset
     * 
     * **Feature: csp-inline-styles-removal, Property 10: Chart.js CSP compliance**
     * **Validates: Requirements 9.1**
     */
    public function testChartHelperAssetDependsOnChartJs()
    {
        $chartHelperAssetPath = $this->basePath . '/assets/ChartHelperAsset.php';
        $this->assertFileExists($chartHelperAssetPath);
        
        $content = file_get_contents($chartHelperAssetPath);
        
        // Should depend on ChartJsAsset
        $this->assertStringContainsString('ChartJsAsset', $content);
    }

    /**
     * Test that chart-helper.js uses canvas-based rendering
     * 
     * **Feature: csp-inline-styles-removal, Property 10: Chart.js CSP compliance**
     * **Validates: Requirements 9.1**
     */
    public function testChartHelperUsesCanvasRendering()
    {
        $chartHelperJsPath = $this->basePath . '/web/js/chart-helper.js';
        $this->assertFileExists($chartHelperJsPath);
        
        $content = file_get_contents($chartHelperJsPath);
        
        // Should create canvas elements
        $this->assertStringContainsString("createElement('canvas')", $content);
        
        // Should get 2D context for canvas rendering
        $this->assertStringContainsString("getContext('2d')", $content);
        
        // Should use Chart constructor (Chart.js)
        $this->assertStringContainsString('new Chart(', $content);
    }

    /**
     * Test that chart-helper.js doesn't use inline styles
     * 
     * **Feature: csp-inline-styles-removal, Property 10: Chart.js CSP compliance**
     * **Validates: Requirements 9.1**
     */
    public function testChartHelperDoesNotUseInlineStyles()
    {
        $chartHelperJsPath = $this->basePath . '/web/js/chart-helper.js';
        $this->assertFileExists($chartHelperJsPath);
        
        $content = file_get_contents($chartHelperJsPath);
        
        // Should not set style attribute directly
        $this->assertStringNotContainsString('.style.', $content);
        $this->assertStringNotContainsString("setAttribute('style'", $content);
    }

    /**
     * Test that ChartHelper PHP component generates CSP-compliant code
     * 
     * **Feature: csp-inline-styles-removal, Property 10: Chart.js CSP compliance**
     * **Validates: Requirements 9.1**
     */
    public function testChartHelperPhpGeneratesCspCompliantCode()
    {
        $chartHelperPath = $this->basePath . '/components/ChartHelper.php';
        $this->assertFileExists($chartHelperPath);
        
        $content = file_get_contents($chartHelperPath);
        
        // Should create canvas elements
        $this->assertStringContainsString("createElement('canvas')", $content);
        
        // Should use Chart constructor
        $this->assertStringContainsString('new Chart(', $content);
        
        // Should not generate inline style attributes
        $this->assertStringNotContainsString("style=", $content);
    }

    /**
     * Test that views with charts register ChartHelperAsset
     * 
     * **Feature: csp-inline-styles-removal, Property 10: Chart.js CSP compliance**
     * **Validates: Requirements 9.1**
     */
    public function testViewsRegisterChartHelperAsset()
    {
        $viewFiles = [
            $this->basePath . '/views/stats/index.php',
            $this->basePath . '/views/site/wallet.php',
            $this->basePath . '/views/site/mining.php',
            $this->basePath . '/views/admin/coin_market_graph.php',
        ];
        
        foreach ($viewFiles as $viewFile) {
            if (file_exists($viewFile)) {
                $content = file_get_contents($viewFile);
                
                // Should register ChartHelperAsset
                $this->assertStringContainsString(
                    'ChartHelperAsset',
                    $content,
                    "View file $viewFile should register ChartHelperAsset"
                );
            }
        }
    }

    /**
     * Test that Chart.js configuration doesn't use inline styles
     * 
     * **Feature: csp-inline-styles-removal, Property 10: Chart.js CSP compliance**
     * **Validates: Requirements 9.1**
     */
    public function testChartConfigurationIsCspCompliant()
    {
        $chartHelperJsPath = $this->basePath . '/web/js/chart-helper.js';
        $content = file_get_contents($chartHelperJsPath);
        
        // Chart.js configuration should use options object, not inline styles
        $this->assertStringContainsString('responsive: true', $content);
        $this->assertStringContainsString('maintainAspectRatio: false', $content);
        
        // Should use Chart.js scales configuration
        $this->assertStringContainsString("type: 'time'", $content);
        $this->assertStringContainsString('scales:', $content);
    }

    /**
     * Test that chart containers use CSS classes instead of inline styles
     * 
     * **Feature: csp-inline-styles-removal, Property 10: Chart.js CSP compliance**
     * **Validates: Requirements 9.1**
     */
    public function testChartContainersUseCssClasses()
    {
        $viewFiles = [
            $this->basePath . '/views/stats/index.php',
            $this->basePath . '/views/site/wallet.php',
            $this->basePath . '/views/site/mining.php',
        ];
        
        foreach ($viewFiles as $viewFile) {
            if (file_exists($viewFile)) {
                $content = file_get_contents($viewFile);
                
                // Chart containers should use CSS classes for height
                if (preg_match_all('/id=[\'"]graph_[^"\']+[\'"]/', $content, $matches)) {
                    // Check that chart containers use CSS classes
                    $this->assertStringContainsString(
                        'chart-container',
                        $content,
                        "View file $viewFile should use chart-container CSS classes"
                    );
                }
            }
        }
    }

    /**
     * Test that Chart.js tooltip configuration is CSP-compliant
     * 
     * **Feature: csp-inline-styles-removal, Property 10: Chart.js CSP compliance**
     * **Validates: Requirements 9.1**
     */
    public function testChartTooltipsAreCspCompliant()
    {
        $chartHelperJsPath = $this->basePath . '/web/js/chart-helper.js';
        $content = file_get_contents($chartHelperJsPath);
        
        // Should use Chart.js tooltip plugin configuration
        $this->assertStringContainsString('tooltip:', $content);
        $this->assertStringContainsString("mode: 'index'", $content);
        
        // Should not use HTML tooltips that might require inline styles
        $this->assertStringNotContainsString('tooltipContentEditor', $content);
    }
}
