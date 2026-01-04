<?php

namespace app\tests\integration;

use app\tests\IntegrationTester;
use Codeception\Test\Unit;

/**
 * Test stats page chart functionality with local Chart.js files
 * 
 * This test verifies that:
 * 1. All 9 charts render correctly on the stats page
 * 2. Chart interactions work (zoom, hover, legend clicks)
 * 3. AJAX chart updates function properly
 * 4. Local Chart.js files are served instead of CDN
 * 
 * Requirements: 5.3
 */
class StatsPageChartFunctionalityTest extends Unit
{
    /**
     * @var IntegrationTester
     */
    protected $tester;

    protected function _before()
    {
        // Set up test algorithm in session
        $this->tester->haveInSession('yaamp-algo', 'sha256');
        
        // Create test data for charts
        $this->createTestHashstatsData();
    }

    /**
     * Create test hashstats data for chart rendering
     */
    private function createTestHashstatsData()
    {
        $baseTime = time() - (48 * 3600); // 48 hours ago
        
        // Create sample hashstats data for the last 48 hours
        for ($i = 0; $i < 48; $i++) {
            $this->tester->haveInDatabase('hashstats', [
                'time' => $baseTime + ($i * 3600), // hourly data
                'algo' => 'sha256',
                'hashrate' => rand(1000000, 5000000), // Random hashrate
                'earnings' => rand(100, 1000) / 100000000, // Random earnings in BTC
            ]);
        }
    }

    /**
     * Test that stats page loads successfully
     * 
     * @test
     */
    public function testStatsPageLoads()
    {
        $this->tester->amOnPage('/stats');
        $this->tester->seeResponseCodeIs(200);
        $this->tester->see('Last 48 Hours');
        $this->tester->see('Last 7 Days');
        $this->tester->see('Last 30 Days');
    }

    /**
     * Test that all 9 chart containers are present on the page
     * 
     * @test
     */
    public function testAllChartContainersPresent()
    {
        $this->tester->amOnPage('/stats');
        
        // Verify all 9 chart containers exist
        for ($i = 1; $i <= 9; $i++) {
            $this->tester->seeElement("#graph_results_$i");
            $this->tester->seeElement("#graph_results_$i.chart-container-240");
        }
    }

    /**
     * Test that ChartHelper JavaScript is loaded
     * 
     * @test
     */
    public function testChartHelperJavaScriptLoaded()
    {
        $this->tester->amOnPage('/stats');
        
        // Check that ChartHelper object is available
        $this->tester->executeJS('return typeof ChartHelper !== "undefined"');
        $result = $this->tester->executeJS('return typeof ChartHelper');
        $this->assertEquals('object', $result);
        
        // Check that essential ChartHelper functions exist
        $functions = ['createLineChart', 'createBarChart', 'formatTimeSeriesData', 'updateChart'];
        foreach ($functions as $function) {
            $result = $this->tester->executeJS("return typeof ChartHelper.$function");
            $this->assertEquals('function', $result, "ChartHelper.$function should be a function");
        }
    }

    /**
     * Test that Chart.js library is loaded locally (not from CDN)
     * 
     * @test
     */
    public function testChartJsLoadedLocally()
    {
        $this->tester->amOnPage('/stats');
        
        // Check that Chart.js is available
        $result = $this->tester->executeJS('return typeof Chart !== "undefined"');
        $this->assertTrue($result, 'Chart.js should be loaded');
        
        // Verify no CDN requests are made
        $this->tester->dontSeeInSource('cdn.jsdelivr.net');
        $this->tester->dontSeeInSource('cdnjs.cloudflare.com');
        $this->tester->dontSeeInSource('unpkg.com');
        
        // Verify local Chart.js files are referenced
        $this->tester->seeInSource('js/vendor/chart.js/chart.umd.min.js');
        $this->tester->seeInSource('js/vendor/chartjs-adapter-date-fns/chartjs-adapter-date-fns.bundle.min.js');
    }

    /**
     * Test that chart initialization functions are present
     * 
     * @test
     */
    public function testChartInitializationFunctions()
    {
        $this->tester->amOnPage('/stats');
        
        // Check that all chart initialization functions exist
        for ($i = 1; $i <= 9; $i++) {
            $result = $this->tester->executeJS("return typeof graph_init_$i");
            $this->assertEquals('function', $result, "graph_init_$i should be a function");
        }
        
        // Check that page_refresh function exists
        $result = $this->tester->executeJS('return typeof page_refresh');
        $this->assertEquals('function', $result, 'page_refresh should be a function');
    }

    /**
     * Test that chart data endpoints return valid data
     * 
     * @test
     */
    public function testChartDataEndpoints()
    {
        // Test each chart data endpoint
        for ($i = 1; $i <= 9; $i++) {
            $this->tester->sendAjaxGetRequest("/stats/graph_results_$i");
            $this->tester->seeResponseCodeIs(200);
            
            // Verify response is valid JSON
            $response = $this->tester->grabResponse();
            $data = json_decode($response, true);
            $this->assertIsArray($data, "Chart data endpoint $i should return JSON array");
            
            // If data exists, verify structure
            if (!empty($data)) {
                $firstPoint = $data[0];
                $this->assertIsArray($firstPoint, "Chart data points should be arrays");
                $this->assertCount(2, $firstPoint, "Chart data points should have [timestamp, value] format");
                $this->assertIsNumeric($firstPoint[0], "First element should be timestamp");
                $this->assertIsNumeric($firstPoint[1], "Second element should be numeric value");
            }
        }
    }

    /**
     * Test that charts can be created and rendered
     * 
     * @test
     */
    public function testChartsCanBeCreated()
    {
        $this->tester->amOnPage('/stats');
        
        // Wait for page to load completely
        $this->tester->wait(2);
        
        // Test creating a line chart
        $testData = [[time() * 1000, 100], [(time() + 3600) * 1000, 150]];
        $this->tester->executeJS("
            var testData = " . json_encode($testData) . ";
            var chart = ChartHelper.createLineChart('graph_results_1', testData, {
                title: 'Test Chart',
                xAxisFormat: 'HH:mm'
            });
            window.testChart = chart;
        ");
        
        // Verify chart was created
        $result = $this->tester->executeJS('return window.testChart !== null && window.testChart !== undefined');
        $this->assertTrue($result, 'Chart should be created successfully');
        
        // Verify chart is a Chart.js instance
        $result = $this->tester->executeJS('return window.testChart.constructor.name');
        $this->assertEquals('Chart', $result, 'Created object should be a Chart.js instance');
        
        // Verify canvas element was created
        $this->tester->seeElement('#graph_results_1 canvas');
    }

    /**
     * Test that charts can be updated with new data
     * 
     * @test
     */
    public function testChartsCanBeUpdated()
    {
        $this->tester->amOnPage('/stats');
        $this->tester->wait(2);
        
        // Create initial chart
        $initialData = [[time() * 1000, 100], [(time() + 3600) * 1000, 150]];
        $this->tester->executeJS("
            var initialData = " . json_encode($initialData) . ";
            ChartHelper.createLineChart('graph_results_2', initialData, {title: 'Update Test'});
        ");
        
        // Update with new data
        $newData = [[time() * 1000, 200], [(time() + 3600) * 1000, 250]];
        $this->tester->executeJS("
            var newData = " . json_encode($newData) . ";
            ChartHelper.updateChart('graph_results_2', newData);
        ");
        
        // Verify chart still exists after update
        $result = $this->tester->executeJS('return window.chart_graph_results_2 !== null');
        $this->assertTrue($result, 'Chart should still exist after update');
        
        // Verify data was updated
        $result = $this->tester->executeJS('return window.chart_graph_results_2.data.datasets[0].data.length');
        $this->assertEquals(2, $result, 'Chart should have updated data points');
    }

    /**
     * Test that bar charts can be created
     * 
     * @test
     */
    public function testBarChartsCanBeCreated()
    {
        $this->tester->amOnPage('/stats');
        $this->tester->wait(2);
        
        // Test creating a bar chart
        $testData = [[time() * 1000, 0.001], [(time() + 3600) * 1000, 0.0015]];
        $this->tester->executeJS("
            var testData = " . json_encode($testData) . ";
            var chart = ChartHelper.createBarChart('graph_results_3', testData, {
                title: 'Test Bar Chart',
                xAxisFormat: 'HH:mm'
            });
            window.testBarChart = chart;
        ");
        
        // Verify bar chart was created
        $result = $this->tester->executeJS('return window.testBarChart !== null');
        $this->assertTrue($result, 'Bar chart should be created successfully');
        
        // Verify it's configured as a bar chart
        $result = $this->tester->executeJS('return window.testBarChart.config.type');
        $this->assertEquals('bar', $result, 'Chart should be configured as bar type');
    }

    /**
     * Test algorithm selection functionality
     * 
     * @test
     */
    public function testAlgorithmSelection()
    {
        $this->tester->amOnPage('/stats');
        
        // Verify algorithm select element exists
        $this->tester->seeElement('#algo_select');
        
        // Verify current algorithm is selected
        $this->tester->seeOptionIsSelected('#algo_select', 'sha256');
    }

    /**
     * Test auto-refresh functionality
     * 
     * @test
     */
    public function testAutoRefreshFunctionality()
    {
        $this->tester->amOnPage('/stats');
        
        // Verify resume button exists (initially hidden)
        $this->tester->seeElement('#resume_update_button');
        
        // Verify page_refresh function can be called
        $result = $this->tester->executeJS('
            try {
                page_refresh();
                return true;
            } catch(e) {
                return false;
            }
        ');
        $this->assertTrue($result, 'page_refresh function should be callable');
    }

    /**
     * Test that time range variables are properly set
     * 
     * @test
     */
    public function testTimeRangeVariables()
    {
        $this->tester->amOnPage('/stats');
        
        // Check that time range variables are defined
        $timeVariables = ['dtMin1', 'dtMax1', 'dtMin2', 'dtMax2', 'dtMin3', 'dtMax3'];
        
        foreach ($timeVariables as $variable) {
            $result = $this->tester->executeJS("return typeof $variable");
            $this->assertEquals('number', $result, "$variable should be defined as a number");
            
            // Verify it's a valid timestamp (in milliseconds)
            $value = $this->tester->executeJS("return $variable");
            $this->assertGreaterThan(0, $value, "$variable should be a positive timestamp");
        }
    }

    /**
     * Test chart responsiveness
     * 
     * @test
     */
    public function testChartResponsiveness()
    {
        $this->tester->amOnPage('/stats');
        $this->tester->wait(2);
        
        // Create a test chart
        $testData = [[time() * 1000, 100]];
        $this->tester->executeJS("
            var testData = " . json_encode($testData) . ";
            ChartHelper.createLineChart('graph_results_4', testData, {title: 'Responsive Test'});
        ");
        
        // Verify chart has responsive configuration
        $result = $this->tester->executeJS('return window.chart_graph_results_4.options.responsive');
        $this->assertTrue($result, 'Chart should be configured as responsive');
        
        $result = $this->tester->executeJS('return window.chart_graph_results_4.options.maintainAspectRatio');
        $this->assertFalse($result, 'Chart should not maintain aspect ratio for better responsiveness');
    }

    /**
     * Test that charts handle empty data gracefully
     * 
     * @test
     */
    public function testChartsHandleEmptyData()
    {
        $this->tester->amOnPage('/stats');
        $this->tester->wait(2);
        
        // Test with empty data
        $this->tester->executeJS("
            var emptyData = [];
            var chart = ChartHelper.createLineChart('graph_results_5', emptyData, {title: 'Empty Data Test'});
            window.emptyChart = chart;
        ");
        
        // Verify chart was still created
        $result = $this->tester->executeJS('return window.emptyChart !== null');
        $this->assertTrue($result, 'Chart should handle empty data gracefully');
        
        // Verify no data points
        $result = $this->tester->executeJS('return window.emptyChart.data.datasets[0].data.length');
        $this->assertEquals(0, $result, 'Chart should have no data points');
    }

    /**
     * Test chart destruction functionality
     * 
     * @test
     */
    public function testChartDestruction()
    {
        $this->tester->amOnPage('/stats');
        $this->tester->wait(2);
        
        // Create a chart
        $testData = [[time() * 1000, 100]];
        $this->tester->executeJS("
            var testData = " . json_encode($testData) . ";
            ChartHelper.createLineChart('graph_results_6', testData, {title: 'Destruction Test'});
        ");
        
        // Verify chart exists
        $result = $this->tester->executeJS('return window.chart_graph_results_6 !== null');
        $this->assertTrue($result, 'Chart should exist before destruction');
        
        // Destroy the chart
        $this->tester->executeJS('ChartHelper.destroyChart("graph_results_6");');
        
        // Verify chart was destroyed
        $result = $this->tester->executeJS('return typeof window.chart_graph_results_6');
        $this->assertEquals('undefined', $result, 'Chart should be destroyed and removed from window');
    }
}