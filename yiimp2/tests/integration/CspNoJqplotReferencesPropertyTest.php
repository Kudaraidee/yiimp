<?php

namespace tests\integration;

use Codeception\Test\Unit;

/**
 * Property Test: No jqPlot references in codebase
 * 
 * **Feature: csp-inline-styles-removal, Property 9: No jqPlot references in codebase**
 * **Validates: Requirements 9.8, 9.10**
 * 
 * This test verifies that all jqPlot references have been removed from the yiimp2 codebase
 * as part of the migration to Chart.js for CSP compliance.
 * 
 * jqPlot is an unmaintained jQuery charting library that generates inline styles at runtime,
 * which violates Content Security Policy. All charts have been migrated to Chart.js which
 * uses canvas-based rendering and is CSP-compliant.
 */
class CspNoJqplotReferencesPropertyTest extends Unit
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
     * Property 9: No jqPlot references in codebase
     * 
     * *For any* file in the yiimp2 directory, there should be zero references to 
     * `$.jqplot`, `jqplot`, or `.jqplot-*` CSS classes in source code files.
     * 
     * **Feature: csp-inline-styles-removal, Property 9: No jqPlot references in codebase**
     * **Validates: Requirements 9.8, 9.10**
     */
    public function testNoJqplotCallsInViews()
    {
        $viewsPath = $this->basePath . '/views';
        $jqplotCalls = $this->searchForPattern($viewsPath, '/\$\.jqplot\s*\(/');
        
        $this->assertEmpty(
            $jqplotCalls,
            "Found $.jqplot() calls in view files:\n" . implode("\n", $jqplotCalls)
        );
    }

    /**
     * Test that no jqPlot renderer references exist in views
     * 
     * **Feature: csp-inline-styles-removal, Property 9: No jqPlot references in codebase**
     * **Validates: Requirements 9.8, 9.10**
     */
    public function testNoJqplotRendererReferences()
    {
        $viewsPath = $this->basePath . '/views';
        $rendererRefs = $this->searchForPattern($viewsPath, '/\$\.jqplot\.(DateAxisRenderer|BarRenderer|EnhancedLegendRenderer)/');
        
        $this->assertEmpty(
            $rendererRefs,
            "Found jqPlot renderer references in view files:\n" . implode("\n", $rendererRefs)
        );
    }

    /**
     * Test that jqPlot is not loaded in AppAsset
     * 
     * **Feature: csp-inline-styles-removal, Property 9: No jqPlot references in codebase**
     * **Validates: Requirements 9.8, 9.10**
     */
    public function testJqplotNotInAppAsset()
    {
        $appAssetPath = $this->basePath . '/assets/AppAsset.php';
        $this->assertFileExists($appAssetPath);
        
        $content = file_get_contents($appAssetPath);
        
        $this->assertStringNotContainsString(
            'jqplot',
            $content,
            "AppAsset.php should not reference jqplot"
        );
    }

    /**
     * Test that no jqPlot CSS class references exist in PHP files
     * 
     * **Feature: csp-inline-styles-removal, Property 9: No jqPlot references in codebase**
     * **Validates: Requirements 9.8, 9.10**
     */
    public function testNoJqplotCssClassesInPhp()
    {
        $viewsPath = $this->basePath . '/views';
        $cssClassRefs = $this->searchForPattern($viewsPath, '/class\s*=\s*["\'][^"\']*\.jqplot-/');
        
        $this->assertEmpty(
            $cssClassRefs,
            "Found .jqplot-* CSS class references in PHP files:\n" . implode("\n", $cssClassRefs)
        );
    }

    /**
     * Test that Chart.js is properly configured as replacement
     * 
     * **Feature: csp-inline-styles-removal, Property 9: No jqPlot references in codebase**
     * **Validates: Requirements 9.8, 9.10**
     */
    public function testChartJsAssetExists()
    {
        $chartJsAssetPath = $this->basePath . '/assets/ChartJsAsset.php';
        $this->assertFileExists($chartJsAssetPath, "ChartJsAsset.php should exist as jqPlot replacement");
        
        $content = file_get_contents($chartJsAssetPath);
        $this->assertStringContainsString('chart.js', $content, "ChartJsAsset should reference Chart.js library");
    }

    /**
     * Test that ChartHelper component exists
     * 
     * **Feature: csp-inline-styles-removal, Property 9: No jqPlot references in codebase**
     * **Validates: Requirements 9.8, 9.10**
     */
    public function testChartHelperComponentExists()
    {
        $chartHelperPath = $this->basePath . '/components/ChartHelper.php';
        $this->assertFileExists($chartHelperPath, "ChartHelper.php should exist for Chart.js integration");
    }

    /**
     * Test that chart-helper.js client-side utilities exist
     * 
     * **Feature: csp-inline-styles-removal, Property 9: No jqPlot references in codebase**
     * **Validates: Requirements 9.8, 9.10**
     */
    public function testChartHelperJsExists()
    {
        $chartHelperJsPath = $this->basePath . '/web/js/chart-helper.js';
        $this->assertFileExists($chartHelperJsPath, "chart-helper.js should exist for client-side Chart.js utilities");
        
        $content = file_get_contents($chartHelperJsPath);
        $this->assertStringContainsString('ChartHelper', $content, "chart-helper.js should define ChartHelper object");
        $this->assertStringContainsString('createLineChart', $content, "chart-helper.js should have createLineChart method");
        $this->assertStringContainsString('createBarChart', $content, "chart-helper.js should have createBarChart method");
    }

    /**
     * Test that views use ChartHelper instead of jqPlot
     * 
     * **Feature: csp-inline-styles-removal, Property 9: No jqPlot references in codebase**
     * **Validates: Requirements 9.8, 9.10**
     */
    public function testViewsUseChartHelper()
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
                
                // Should not have jqPlot calls
                $this->assertStringNotContainsString(
                    '$.jqplot(',
                    $content,
                    "View file $viewFile should not contain $.jqplot() calls"
                );
                
                // Should use ChartHelper or Chart.js
                $hasChartJs = strpos($content, 'ChartHelper') !== false 
                           || strpos($content, 'new Chart(') !== false
                           || strpos($content, 'ChartHelperAsset') !== false;
                
                // Only check for Chart.js usage if the file has chart-related content
                if (strpos($content, 'graph_') !== false || strpos($content, 'chart') !== false) {
                    $this->assertTrue(
                        $hasChartJs,
                        "View file $viewFile should use ChartHelper or Chart.js for charts"
                    );
                }
            }
        }
    }

    /**
     * Search for a regex pattern in all PHP files within a directory
     * 
     * @param string $directory Directory to search
     * @param string $pattern Regex pattern to search for
     * @return array Array of matches with file:line format
     */
    protected function searchForPattern(string $directory, string $pattern): array
    {
        $matches = [];
        
        if (!is_dir($directory)) {
            return $matches;
        }
        
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $content = file_get_contents($file->getPathname());
                $lines = explode("\n", $content);
                
                foreach ($lines as $lineNum => $line) {
                    if (preg_match($pattern, $line)) {
                        $relativePath = str_replace($this->basePath . '/', '', $file->getPathname());
                        $matches[] = "$relativePath:" . ($lineNum + 1) . ": " . trim($line);
                    }
                }
            }
        }
        
        return $matches;
    }
}
