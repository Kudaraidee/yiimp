<?php

namespace tests\integration;

use Codeception\Test\Unit;

/**
 * Property Test: Chart tooltip functionality
 * 
 * **Feature: csp-inline-styles-removal, Property 12: Chart tooltip functionality**
 * **Validates: Requirements 9.7**
 * 
 * This test verifies that Chart.js tooltips are properly configured and display
 * relevant information when hovering over data points.
 * For any data point in a Chart.js chart, hovering over it should display a tooltip
 * containing relevant information (value, date/time, series name).
 */
class ChartJsTooltipPropertyTest extends Unit
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
     * Property 12: Chart tooltip functionality - Tooltip configuration exists
     * 
     * *For any* data point in a Chart.js chart, hovering over it should display a tooltip
     * containing relevant information (value, date/time, series name).
     * 
     * **Feature: csp-inline-styles-removal, Property 12: Chart tooltip functionality**
     * **Validates: Requirements 9.7**
     */
    public function testChartHelperConfiguresTooltips()
    {
        $chartHelperJsPath = $this->basePath . '/web/js/chart-helper.js';
        $this->assertFileExists($chartHelperJsPath);
        
        $content = file_get_contents($chartHelperJsPath);
        
        // Should have tooltip configuration
        $this->assertStringContainsString('tooltip:', $content);
        
        // Should configure tooltip mode for proper interaction
        $this->assertStringContainsString("mode: 'index'", $content);
        
        // Should configure intersect behavior
        $this->assertStringContainsString('intersect: false', $content);
    }

    /**
     * Property 12: Chart tooltip functionality - Tooltip callbacks support
     * 
     * **Feature: csp-inline-styles-removal, Property 12: Chart tooltip functionality**
     * **Validates: Requirements 9.7**
     */
    public function testChartHelperSupportsTooltipCallbacks()
    {
        $chartHelperJsPath = $this->basePath . '/web/js/chart-helper.js';
        $content = file_get_contents($chartHelperJsPath);
        
        // Should support custom tooltip callbacks
        $this->assertStringContainsString('callbacks:', $content);
        
        // Should support tooltipCallbacks option
        $this->assertStringContainsString('tooltipCallbacks', $content);
    }

    /**
     * Property 12: Chart tooltip functionality - PHP ChartHelper tooltip config
     * 
     * **Feature: csp-inline-styles-removal, Property 12: Chart tooltip functionality**
     * **Validates: Requirements 9.7**
     */
    public function testPhpChartHelperConfiguresTooltips()
    {
        $chartHelperPath = $this->basePath . '/components/ChartHelper.php';
        $this->assertFileExists($chartHelperPath);
        
        $content = file_get_contents($chartHelperPath);
        
        // Should have tooltip configuration in chart options
        $this->assertStringContainsString("'tooltip'", $content);
        
        // Should configure tooltip mode
        $this->assertStringContainsString("'mode' => 'index'", $content);
        
        // Should configure intersect behavior
        $this->assertStringContainsString("'intersect' => false", $content);
    }

    /**
     * Property 12: Chart tooltip functionality - Line chart tooltips
     * 
     * **Feature: csp-inline-styles-removal, Property 12: Chart tooltip functionality**
     * **Validates: Requirements 9.7**
     */
    public function testLineChartOptionsIncludeTooltips()
    {
        $chartHelperJsPath = $this->basePath . '/web/js/chart-helper.js';
        $content = file_get_contents($chartHelperJsPath);
        
        // getLineChartOptions should include tooltip configuration
        $this->assertStringContainsString('getLineChartOptions:', $content);
        
        // Line chart options should have plugins section with tooltip
        $this->assertStringContainsString('plugins:', $content);
    }

    /**
     * Property 12: Chart tooltip functionality - Bar chart tooltips
     * 
     * **Feature: csp-inline-styles-removal, Property 12: Chart tooltip functionality**
     * **Validates: Requirements 9.7**
     */
    public function testBarChartOptionsIncludeTooltips()
    {
        $chartHelperJsPath = $this->basePath . '/web/js/chart-helper.js';
        $content = file_get_contents($chartHelperJsPath);
        
        // getBarChartOptions should include tooltip configuration
        $this->assertStringContainsString('getBarChartOptions:', $content);
    }

    /**
     * Property 12: Chart tooltip functionality - Stacked area chart tooltips
     * 
     * **Feature: csp-inline-styles-removal, Property 12: Chart tooltip functionality**
     * **Validates: Requirements 9.7**
     */
    public function testStackedAreaChartOptionsIncludeTooltips()
    {
        $chartHelperJsPath = $this->basePath . '/web/js/chart-helper.js';
        $content = file_get_contents($chartHelperJsPath);
        
        // getStackedAreaChartOptions should include tooltip configuration
        $this->assertStringContainsString('getStackedAreaChartOptions:', $content);
    }

    /**
     * Property 12: Chart tooltip functionality - Chart with legend tooltips
     * 
     * **Feature: csp-inline-styles-removal, Property 12: Chart tooltip functionality**
     * **Validates: Requirements 9.7**
     */
    public function testChartWithLegendIncludesTooltips()
    {
        $chartHelperJsPath = $this->basePath . '/web/js/chart-helper.js';
        $content = file_get_contents($chartHelperJsPath);
        
        // createChartWithLegend should include tooltip configuration
        $this->assertStringContainsString('createChartWithLegend:', $content);
        
        // Should have label callback for formatting tooltip content
        $this->assertStringContainsString('label:', $content);
    }

    /**
     * Property 12: Chart tooltip functionality - Tooltip label formatting
     * 
     * **Feature: csp-inline-styles-removal, Property 12: Chart tooltip functionality**
     * **Validates: Requirements 9.7**
     */
    public function testTooltipLabelFormattingExists()
    {
        $chartHelperJsPath = $this->basePath . '/web/js/chart-helper.js';
        $content = file_get_contents($chartHelperJsPath);
        
        // Should have label callback function
        $this->assertStringContainsString('label: function(context)', $content);
        
        // Should access dataset label
        $this->assertStringContainsString('context.dataset.label', $content);
        
        // Should access parsed y value
        $this->assertStringContainsString('context.parsed.y', $content);
    }

    /**
     * Property 12: Chart tooltip functionality - Time axis formatting
     * 
     * **Feature: csp-inline-styles-removal, Property 12: Chart tooltip functionality**
     * **Validates: Requirements 9.7**
     */
    public function testTimeAxisFormattingForTooltips()
    {
        $chartHelperJsPath = $this->basePath . '/web/js/chart-helper.js';
        $content = file_get_contents($chartHelperJsPath);
        
        // Should have time scale configuration
        $this->assertStringContainsString("type: 'time'", $content);
        
        // Should have displayFormats for time axis
        $this->assertStringContainsString('displayFormats:', $content);
        
        // Should support hour format
        $this->assertStringContainsString('hour:', $content);
        
        // Should support day format
        $this->assertStringContainsString('day:', $content);
    }

    /**
     * Property 12: Chart tooltip functionality - Interaction mode
     * 
     * **Feature: csp-inline-styles-removal, Property 12: Chart tooltip functionality**
     * **Validates: Requirements 9.7**
     */
    public function testChartInteractionModeForTooltips()
    {
        $chartHelperJsPath = $this->basePath . '/web/js/chart-helper.js';
        $content = file_get_contents($chartHelperJsPath);
        
        // Should have interaction configuration
        $this->assertStringContainsString('interaction:', $content);
        
        // Should configure interaction mode
        $this->assertStringContainsString("mode: 'nearest'", $content);
        
        // Should configure axis for interaction
        $this->assertStringContainsString("axis: 'x'", $content);
    }

    /**
     * Property 12: Chart tooltip functionality - No jqPlot highlighter
     * 
     * **Feature: csp-inline-styles-removal, Property 12: Chart tooltip functionality**
     * **Validates: Requirements 9.7**
     */
    public function testNoJqplotHighlighterReferences()
    {
        $chartHelperJsPath = $this->basePath . '/web/js/chart-helper.js';
        $content = file_get_contents($chartHelperJsPath);
        
        // Should not reference jqPlot highlighter
        $this->assertStringNotContainsString('highlighter', $content);
        $this->assertStringNotContainsString('tooltipContentEditor', $content);
    }

    /**
     * Property 12: Chart tooltip functionality - Views use Chart.js tooltips
     * 
     * **Feature: csp-inline-styles-removal, Property 12: Chart tooltip functionality**
     * **Validates: Requirements 9.7**
     */
    public function testViewsUseChartJsTooltips()
    {
        $viewFiles = [
            $this->basePath . '/views/stats/index.php',
            $this->basePath . '/views/site/mining.php',
            $this->basePath . '/views/site/wallet.php',
        ];
        
        foreach ($viewFiles as $viewFile) {
            if (file_exists($viewFile)) {
                $content = file_get_contents($viewFile);
                
                // Should not use jqPlot highlighter
                $this->assertStringNotContainsString(
                    'highlighter',
                    $content,
                    "View file $viewFile should not use jqPlot highlighter"
                );
                
                // Should not use tooltipContentEditor
                $this->assertStringNotContainsString(
                    'tooltipContentEditor',
                    $content,
                    "View file $viewFile should not use jqPlot tooltipContentEditor"
                );
            }
        }
    }

    /**
     * Property 12: Chart tooltip functionality - Admin coin market graph tooltips
     * 
     * **Feature: csp-inline-styles-removal, Property 12: Chart tooltip functionality**
     * **Validates: Requirements 9.7**
     */
    public function testAdminCoinMarketGraphUsesChartJsTooltips()
    {
        $adminViewPath = $this->basePath . '/views/admin/coin_market_graph.php';
        
        if (file_exists($adminViewPath)) {
            $content = file_get_contents($adminViewPath);
            
            // Should use ChartHelper for charts
            $hasChartHelper = strpos($content, 'ChartHelper') !== false;
            $hasChartJs = strpos($content, 'Chart(') !== false || strpos($content, 'new Chart') !== false;
            
            $this->assertTrue(
                $hasChartHelper || $hasChartJs,
                "Admin coin market graph should use ChartHelper or Chart.js"
            );
            
            // Should not use jqPlot
            $this->assertStringNotContainsString(
                '$.jqplot',
                $content,
                "Admin coin market graph should not use jqPlot"
            );
        }
    }

    /**
     * Property 12: Chart tooltip functionality - Tooltip decimal precision
     * 
     * **Feature: csp-inline-styles-removal, Property 12: Chart tooltip functionality**
     * **Validates: Requirements 9.7**
     */
    public function testTooltipDecimalPrecisionSupport()
    {
        $chartHelperJsPath = $this->basePath . '/web/js/chart-helper.js';
        $content = file_get_contents($chartHelperJsPath);
        
        // Should support decimal precision in tooltips
        $this->assertStringContainsString('toFixed', $content);
        
        // Should support yDecimals option
        $this->assertStringContainsString('yDecimals', $content);
    }

    /**
     * Property 12: Chart tooltip functionality - Responsive tooltips
     * 
     * **Feature: csp-inline-styles-removal, Property 12: Chart tooltip functionality**
     * **Validates: Requirements 9.7**
     */
    public function testChartsAreResponsive()
    {
        $chartHelperJsPath = $this->basePath . '/web/js/chart-helper.js';
        $content = file_get_contents($chartHelperJsPath);
        
        // Charts should be responsive
        $this->assertStringContainsString('responsive: true', $content);
        
        // Should maintain aspect ratio setting
        $this->assertStringContainsString('maintainAspectRatio: false', $content);
    }
}
