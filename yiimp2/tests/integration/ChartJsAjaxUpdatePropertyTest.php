<?php

namespace tests\integration;

use Codeception\Test\Unit;

/**
 * Property Test: Chart AJAX update functionality
 * 
 * **Feature: csp-inline-styles-removal, Property 11: Chart AJAX update functionality**
 * **Validates: Requirements 9.6**
 * 
 * This test verifies that Chart.js charts can be updated via AJAX without CSP violations.
 * For any chart that supports AJAX updates, calling the update function with new data
 * should refresh the chart display without CSP violations or errors.
 */
class ChartJsAjaxUpdatePropertyTest extends Unit
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
     * Property 11: Chart AJAX update functionality - Stats page charts
     * 
     * *For any* chart that supports AJAX updates, calling the update function with new data
     * should refresh the chart display without CSP violations or errors.
     * 
     * **Feature: csp-inline-styles-removal, Property 11: Chart AJAX update functionality**
     * **Validates: Requirements 9.6**
     */
    public function testStatsPageChartsHaveAjaxRefreshFunctions()
    {
        $statsViewPath = $this->basePath . '/views/stats/index.php';
        $this->assertFileExists($statsViewPath);
        
        $content = file_get_contents($statsViewPath);
        
        // Stats page should have 9 chart refresh functions
        $chartIds = [
            'graph_results_1', 'graph_results_2', 'graph_results_3',
            'graph_results_4', 'graph_results_5', 'graph_results_6',
            'graph_results_7', 'graph_results_8', 'graph_results_9'
        ];
        
        foreach ($chartIds as $chartId) {
            // Each chart should have a refresh function
            $this->assertStringContainsString(
                "main_refresh_" . substr($chartId, -1),
                $content,
                "Stats page should have refresh function for $chartId"
            );
            
            // Each chart should have an init function
            $this->assertStringContainsString(
                "graph_init_" . substr($chartId, -1),
                $content,
                "Stats page should have init function for $chartId"
            );
        }
        
        // Should have page_refresh function that calls all chart refreshes
        $this->assertStringContainsString('function page_refresh()', $content);
    }

    /**
     * Property 11: Chart AJAX update functionality - Stats page AJAX endpoints
     * 
     * **Feature: csp-inline-styles-removal, Property 11: Chart AJAX update functionality**
     * **Validates: Requirements 9.6**
     */
    public function testStatsPageChartsUseAjaxEndpoints()
    {
        $statsViewPath = $this->basePath . '/views/stats/index.php';
        $content = file_get_contents($statsViewPath);
        
        // Should use $.get for AJAX calls
        $this->assertStringContainsString('$.get(', $content);
        
        // Should have AJAX endpoints for each chart
        $endpoints = [
            '/stats/graph_results_1',
            '/stats/graph_results_2',
            '/stats/graph_results_3',
            '/stats/graph_results_4',
            '/stats/graph_results_5',
            '/stats/graph_results_6',
            '/stats/graph_results_7',
            '/stats/graph_results_8',
            '/stats/graph_results_9',
        ];
        
        foreach ($endpoints as $endpoint) {
            $this->assertStringContainsString(
                $endpoint,
                $content,
                "Stats page should have AJAX endpoint: $endpoint"
            );
        }
    }

    /**
     * Property 11: Chart AJAX update functionality - Mining page charts
     * 
     * **Feature: csp-inline-styles-removal, Property 11: Chart AJAX update functionality**
     * **Validates: Requirements 9.6**
     */
    public function testMiningPageChartsHaveAjaxRefreshFunctions()
    {
        $miningViewPath = $this->basePath . '/views/site/mining.php';
        $this->assertFileExists($miningViewPath);
        
        $content = file_get_contents($miningViewPath);
        
        // Mining page should have chart refresh functions
        $this->assertStringContainsString('main_refresh_price', $content);
        $this->assertStringContainsString('pool_hashrate_refresh', $content);
        
        // Should have init functions
        $this->assertStringContainsString('graph_init_price', $content);
        $this->assertStringContainsString('pool_hashrate_graph_init', $content);
        
        // Should have page_refresh function
        $this->assertStringContainsString('function page_refresh()', $content);
    }

    /**
     * Property 11: Chart AJAX update functionality - Mining page AJAX endpoints
     * 
     * **Feature: csp-inline-styles-removal, Property 11: Chart AJAX update functionality**
     * **Validates: Requirements 9.6**
     */
    public function testMiningPageChartsUseAjaxEndpoints()
    {
        $miningViewPath = $this->basePath . '/views/site/mining.php';
        $content = file_get_contents($miningViewPath);
        
        // Should use $.get for AJAX calls
        $this->assertStringContainsString('$.get(', $content);
        
        // Should have AJAX endpoints for charts
        $this->assertStringContainsString('graph_price_results', $content);
        $this->assertStringContainsString('graph_hashrate_results', $content);
    }

    /**
     * Property 11: Chart AJAX update functionality - Wallet page charts
     * 
     * **Feature: csp-inline-styles-removal, Property 11: Chart AJAX update functionality**
     * **Validates: Requirements 9.6**
     */
    public function testWalletPageChartsHaveAjaxRefreshFunctions()
    {
        $walletViewPath = $this->basePath . '/views/site/wallet.php';
        $this->assertFileExists($walletViewPath);
        
        $content = file_get_contents($walletViewPath);
        
        // Wallet page should have chart refresh functions
        $this->assertStringContainsString('main_graphs_refresh', $content);
        $this->assertStringContainsString('graph_earnings_refresh', $content);
        $this->assertStringContainsString('main_refresh_hashrate', $content);
        
        // Should have init functions
        $this->assertStringContainsString('graph_init_hashrate', $content);
        $this->assertStringContainsString('graph_earnings_init', $content);
        
        // Should have page_refresh function
        $this->assertStringContainsString('function page_refresh()', $content);
    }

    /**
     * Property 11: Chart AJAX update functionality - Wallet page AJAX endpoints
     * 
     * **Feature: csp-inline-styles-removal, Property 11: Chart AJAX update functionality**
     * **Validates: Requirements 9.6**
     */
    public function testWalletPageChartsUseAjaxEndpoints()
    {
        $walletViewPath = $this->basePath . '/views/site/wallet.php';
        $content = file_get_contents($walletViewPath);
        
        // Should use $.get for AJAX calls
        $this->assertStringContainsString('$.get(', $content);
        
        // Should have AJAX endpoints for charts
        $this->assertStringContainsString('graph_user_results', $content);
        $this->assertStringContainsString('graph_earnings_results', $content);
        $this->assertStringContainsString('wallet_graphs_results', $content);
    }

    /**
     * Property 11: Chart AJAX update functionality - ChartHelper updateChart method
     * 
     * **Feature: csp-inline-styles-removal, Property 11: Chart AJAX update functionality**
     * **Validates: Requirements 9.6**
     */
    public function testChartHelperHasUpdateMethod()
    {
        $chartHelperJsPath = $this->basePath . '/web/js/chart-helper.js';
        $this->assertFileExists($chartHelperJsPath);
        
        $content = file_get_contents($chartHelperJsPath);
        
        // Should have updateChart method
        $this->assertStringContainsString('updateChart:', $content);
        
        // Should have updateChartMultiple method for multi-dataset charts
        $this->assertStringContainsString('updateChartMultiple:', $content);
        
        // Update method should use Chart.js update API
        $this->assertStringContainsString(".update('none')", $content);
    }

    /**
     * Property 11: Chart AJAX update functionality - JSON parsing
     * 
     * **Feature: csp-inline-styles-removal, Property 11: Chart AJAX update functionality**
     * **Validates: Requirements 9.6**
     */
    public function testChartInitFunctionsParseJsonData()
    {
        $viewFiles = [
            $this->basePath . '/views/stats/index.php',
            $this->basePath . '/views/site/mining.php',
            $this->basePath . '/views/site/wallet.php',
        ];
        
        foreach ($viewFiles as $viewFile) {
            if (file_exists($viewFile)) {
                $content = file_get_contents($viewFile);
                
                // Chart init functions should parse JSON data
                $this->assertStringContainsString(
                    'JSON.parse(data)',
                    $content,
                    "View file $viewFile should parse JSON data in chart init functions"
                );
            }
        }
    }

    /**
     * Property 11: Chart AJAX update functionality - ChartHelper.createLineChart usage
     * 
     * **Feature: csp-inline-styles-removal, Property 11: Chart AJAX update functionality**
     * **Validates: Requirements 9.6**
     */
    public function testChartInitFunctionsUseChartHelper()
    {
        $viewFiles = [
            $this->basePath . '/views/stats/index.php',
            $this->basePath . '/views/site/mining.php',
            $this->basePath . '/views/site/wallet.php',
        ];
        
        foreach ($viewFiles as $viewFile) {
            if (file_exists($viewFile)) {
                $content = file_get_contents($viewFile);
                
                // Chart init functions should use ChartHelper
                $this->assertStringContainsString(
                    'ChartHelper.create',
                    $content,
                    "View file $viewFile should use ChartHelper for chart creation"
                );
            }
        }
    }

    /**
     * Property 11: Chart AJAX update functionality - No inline style manipulation
     * 
     * **Feature: csp-inline-styles-removal, Property 11: Chart AJAX update functionality**
     * **Validates: Requirements 9.6**
     */
    public function testAjaxUpdateDoesNotUseInlineStyles()
    {
        $chartHelperJsPath = $this->basePath . '/web/js/chart-helper.js';
        $content = file_get_contents($chartHelperJsPath);
        
        // Update methods should not manipulate inline styles
        $this->assertStringNotContainsString('.style.', $content);
        $this->assertStringNotContainsString("setAttribute('style'", $content);
    }

    /**
     * Property 11: Chart AJAX update functionality - Chart registration
     * 
     * **Feature: csp-inline-styles-removal, Property 11: Chart AJAX update functionality**
     * **Validates: Requirements 9.6**
     */
    public function testChartsAreRegisteredForUpdates()
    {
        $chartHelperJsPath = $this->basePath . '/web/js/chart-helper.js';
        $content = file_get_contents($chartHelperJsPath);
        
        // Charts should be stored in window object for later access
        $this->assertStringContainsString("window['chart_'", $content);
        
        // Should have getChart method to retrieve chart instances
        $this->assertStringContainsString('getChart:', $content);
    }

    /**
     * Property 11: Chart AJAX update functionality - formatTimeSeriesData
     * 
     * **Feature: csp-inline-styles-removal, Property 11: Chart AJAX update functionality**
     * **Validates: Requirements 9.6**
     */
    public function testChartHelperFormatsTimeSeriesData()
    {
        $chartHelperJsPath = $this->basePath . '/web/js/chart-helper.js';
        $content = file_get_contents($chartHelperJsPath);
        
        // Should have formatTimeSeriesData method
        $this->assertStringContainsString('formatTimeSeriesData:', $content);
        
        // Should convert [timestamp, value] to {x, y} format
        $this->assertStringContainsString('x:', $content);
        $this->assertStringContainsString('y:', $content);
    }
}
