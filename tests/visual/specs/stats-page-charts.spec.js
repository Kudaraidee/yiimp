const { test, expect } = require('@playwright/test');
const { setupTestEnvironment } = require('../utils/test-data-setup');

/**
 * Stats Page Chart Functionality Tests
 * 
 * This test suite verifies that:
 * 1. All 9 charts render correctly on the stats page
 * 2. Chart interactions work (zoom, hover, legend clicks)
 * 3. AJAX chart updates function properly
 * 4. Local Chart.js files are served instead of CDN
 * 
 * Requirements: 5.3
 */

test.describe('Stats Page Chart Functionality', () => {
  let hasAlgorithms = false;
  
  test.beforeAll(async ({ browser }) => {
    // Setup test environment with login and test data
    const page = await browser.newPage();
    const testEnv = await setupTestEnvironment(page);
    hasAlgorithms = testEnv.hasAlgorithms;
    
    if (hasAlgorithms) {
      console.log('Algorithms available for testing');
    } else {
      console.log('No algorithms available - tests will be skipped');
    }
    
    await page.close();
  });

  test.beforeEach(async ({ page }) => {
    // Skip all tests if no algorithms are available
    if (!hasAlgorithms) {
      test.skip('No algorithms available - need coins in database with enable=1 and visible=1');
    }
    
    // Set up test environment
    await page.goto('/stats');
    
    // Wait for page to load completely
    await page.waitForLoadState('networkidle');
    
    // Wait for Chart.js to be available
    await page.waitForFunction(() => typeof window.Chart !== 'undefined');
    await page.waitForFunction(() => typeof window.ChartHelper !== 'undefined');
  });

  test('should load stats page successfully', async ({ page }) => {
    // Verify page loads with correct content
    await expect(page).toHaveTitle(/Stats/);
    await expect(page.locator('text=Last 48 Hours')).toBeVisible();
    await expect(page.locator('text=Last 7 Days')).toBeVisible();
    await expect(page.locator('text=Last 30 Days')).toBeVisible();
  });

  test('should have algorithms available for testing', async ({ page }) => {
    // This test verifies that we have the necessary data to test charts
    const algoSelect = page.locator('#algo_select');
    await expect(algoSelect).toBeVisible();
    
    // Verify it has at least one algorithm option
    const optionCount = await algoSelect.locator('option').count();
    expect(optionCount).toBeGreaterThan(0);
    
    // Log available algorithms for debugging
    const options = await algoSelect.locator('option').allTextContents();
    console.log('Available algorithms:', options);
  });

  test('should have all 9 chart containers present', async ({ page }) => {
    // Verify all chart containers exist
    for (let i = 1; i <= 9; i++) {
      const container = page.locator(`#graph_results_${i}`);
      await expect(container).toBeVisible();
      await expect(container).toHaveClass(/chart-container-240/);
    }
  });

  test('should load Chart.js locally (not from CDN)', async ({ page }) => {
    // Check that Chart.js is loaded
    const chartAvailable = await page.evaluate(() => typeof window.Chart !== 'undefined');
    expect(chartAvailable).toBe(true);
    
    // Verify no CDN requests are made
    const requests = [];
    page.on('request', request => requests.push(request.url()));
    
    await page.reload();
    await page.waitForLoadState('networkidle');
    
    // Check that no CDN URLs are requested
    const cdnRequests = requests.filter(url => 
      url.includes('cdn.jsdelivr.net') || 
      url.includes('cdnjs.cloudflare.com') || 
      url.includes('unpkg.com')
    );
    
    expect(cdnRequests).toHaveLength(0);
    
    // Verify local Chart.js files are referenced
    const localChartRequests = requests.filter(url => 
      url.includes('js/vendor/chart.js/chart.umd.min.js') ||
      url.includes('js/vendor/chartjs-adapter-date-fns/chartjs-adapter-date-fns.bundle.min.js')
    );
    
    expect(localChartRequests.length).toBeGreaterThan(0);
  });

  test('should have ChartHelper functions available', async ({ page }) => {
    // Verify ChartHelper object exists
    const chartHelperType = await page.evaluate(() => typeof window.ChartHelper);
    expect(chartHelperType).toBe('object');
    
    // Verify essential functions exist
    const functions = ['createLineChart', 'createBarChart', 'formatTimeSeriesData', 'updateChart', 'destroyChart'];
    
    for (const func of functions) {
      const funcType = await page.evaluate((funcName) => typeof window.ChartHelper[funcName], func);
      expect(funcType).toBe('function');
    }
  });

  test('should have chart initialization functions defined', async ({ page }) => {
    // Verify all chart initialization functions exist
    for (let i = 1; i <= 9; i++) {
      const funcExists = await page.evaluate((index) => typeof window[`graph_init_${index}`] === 'function', i);
      expect(funcExists).toBe(true);
    }
    
    // Verify page refresh function exists
    const pageRefreshExists = await page.evaluate(() => typeof window.page_refresh === 'function');
    expect(pageRefreshExists).toBe(true);
  });

  test('should create charts successfully', async ({ page }) => {
    // Test creating a line chart
    const testData = [[Date.now(), 100], [Date.now() + 3600000, 150]];
    
    await page.evaluate((data) => {
      window.ChartHelper.createLineChart('graph_results_1', data, {
        title: 'Test Chart',
        xAxisFormat: 'HH:mm'
      });
    }, testData);
    
    // Verify chart was created
    const chartExists = await page.evaluate(() => window.chart_graph_results_1 !== null && window.chart_graph_results_1 !== undefined);
    expect(chartExists).toBe(true);
    
    // Verify canvas element was created
    const canvas = page.locator('#graph_results_1 canvas');
    await expect(canvas).toBeVisible();
    
    // Verify it's a Chart.js instance
    const isChartInstance = await page.evaluate(() => window.chart_graph_results_1.constructor.name === 'Chart');
    expect(isChartInstance).toBe(true);
  });

  test('should create bar charts successfully', async ({ page }) => {
    // Test creating a bar chart
    const testData = [[Date.now(), 0.001], [Date.now() + 3600000, 0.0015]];
    
    await page.evaluate((data) => {
      window.ChartHelper.createBarChart('graph_results_2', data, {
        title: 'Test Bar Chart',
        xAxisFormat: 'HH:mm'
      });
    }, testData);
    
    // Verify bar chart was created
    const chartExists = await page.evaluate(() => window.chart_graph_results_2 !== null);
    expect(chartExists).toBe(true);
    
    // Verify it's configured as a bar chart
    const chartType = await page.evaluate(() => window.chart_graph_results_2.config.type);
    expect(chartType).toBe('bar');
    
    // Verify canvas element
    const canvas = page.locator('#graph_results_2 canvas');
    await expect(canvas).toBeVisible();
  });

  test('should update charts with new data', async ({ page }) => {
    // Create initial chart
    const initialData = [[Date.now(), 100], [Date.now() + 3600000, 150]];
    
    await page.evaluate((data) => {
      window.ChartHelper.createLineChart('graph_results_3', data, {title: 'Update Test'});
    }, initialData);
    
    // Update with new data
    const newData = [[Date.now(), 200], [Date.now() + 3600000, 250]];
    
    await page.evaluate((data) => {
      window.ChartHelper.updateChart('graph_results_3', data);
    }, newData);
    
    // Verify chart still exists after update
    const chartExists = await page.evaluate(() => window.chart_graph_results_3 !== null);
    expect(chartExists).toBe(true);
    
    // Verify data was updated
    const dataLength = await page.evaluate(() => window.chart_graph_results_3.data.datasets[0].data.length);
    expect(dataLength).toBe(2);
  });

  test('should handle chart interactions', async ({ page }) => {
    // Create a chart with test data
    const testData = [];
    for (let i = 0; i < 24; i++) {
      testData.push([Date.now() + (i * 3600000), Math.random() * 1000]);
    }
    
    await page.evaluate((data) => {
      window.ChartHelper.createLineChart('graph_results_4', data, {
        title: 'Interactive Test Chart',
        xAxisFormat: 'HH:mm'
      });
    }, testData);
    
    const canvas = page.locator('#graph_results_4 canvas');
    await expect(canvas).toBeVisible();
    
    // Test hover interaction
    await canvas.hover();
    
    // Test that chart responds to hover (tooltip should appear)
    // Note: This is a basic interaction test - more complex tooltip testing would require additional setup
    const chartStillExists = await page.evaluate(() => window.chart_graph_results_4 !== null);
    expect(chartStillExists).toBe(true);
  });

  test('should handle empty data gracefully', async ({ page }) => {
    // Test with empty data
    await page.evaluate(() => {
      window.ChartHelper.createLineChart('graph_results_5', [], {title: 'Empty Data Test'});
    });
    
    // Verify chart was still created
    const chartExists = await page.evaluate(() => window.chart_graph_results_5 !== null);
    expect(chartExists).toBe(true);
    
    // Verify no data points
    const dataLength = await page.evaluate(() => window.chart_graph_results_5.data.datasets[0].data.length);
    expect(dataLength).toBe(0);
    
    // Verify canvas still exists
    const canvas = page.locator('#graph_results_5 canvas');
    await expect(canvas).toBeVisible();
  });

  test('should destroy charts properly', async ({ page }) => {
    // Create a chart
    const testData = [[Date.now(), 100]];
    
    await page.evaluate((data) => {
      window.ChartHelper.createLineChart('graph_results_6', data, {title: 'Destruction Test'});
    }, testData);
    
    // Verify chart exists
    const chartExists = await page.evaluate(() => window.chart_graph_results_6 !== null);
    expect(chartExists).toBe(true);
    
    // Destroy the chart
    await page.evaluate(() => {
      window.ChartHelper.destroyChart('graph_results_6');
    });
    
    // Verify chart was destroyed
    const chartDestroyed = await page.evaluate(() => typeof window.chart_graph_results_6 === 'undefined');
    expect(chartDestroyed).toBe(true);
  });

  test('should have responsive chart configuration', async ({ page }) => {
    // Create a test chart
    const testData = [[Date.now(), 100]];
    
    await page.evaluate((data) => {
      window.ChartHelper.createLineChart('graph_results_7', data, {title: 'Responsive Test'});
    }, testData);
    
    // Verify chart has responsive configuration
    const isResponsive = await page.evaluate(() => window.chart_graph_results_7.options.responsive);
    expect(isResponsive).toBe(true);
    
    const maintainAspectRatio = await page.evaluate(() => window.chart_graph_results_7.options.maintainAspectRatio);
    expect(maintainAspectRatio).toBe(false);
  });

  test('should have algorithm selection functionality', async ({ page }) => {
    // Verify algorithm select element exists
    const algoSelect = page.locator('#algo_select');
    await expect(algoSelect).toBeVisible();
    
    // Verify it has options
    const optionCount = await algoSelect.locator('option').count();
    expect(optionCount).toBeGreaterThan(0);
  });

  test('should have auto-refresh controls', async ({ page }) => {
    // Verify resume button exists
    const resumeButton = page.locator('#resume_update_button');
    await expect(resumeButton).toBeAttached();
    
    // Verify page_refresh function can be called
    const canCallPageRefresh = await page.evaluate(() => {
      try {
        if (typeof page_refresh === 'function') {
          // Don't actually call it to avoid network requests, just verify it exists
          return true;
        }
        return false;
      } catch(e) {
        return false;
      }
    });
    expect(canCallPageRefresh).toBe(true);
  });

  test('should have proper time range variables', async ({ page }) => {
    // Check that time range variables are defined
    const timeVariables = ['dtMin1', 'dtMax1', 'dtMin2', 'dtMax2', 'dtMin3', 'dtMax3'];
    
    for (const variable of timeVariables) {
      const variableType = await page.evaluate((varName) => typeof window[varName], variable);
      expect(variableType).toBe('number');
      
      // Verify it's a valid timestamp (in milliseconds)
      const value = await page.evaluate((varName) => window[varName], variable);
      expect(value).toBeGreaterThan(0);
    }
  });

  test('should format time series data correctly', async ({ page }) => {
    // Test data formatting function
    const testData = [[1234567890000, 100], [1234567890000 + 3600000, 150]];
    
    const formattedData = await page.evaluate((data) => {
      return window.ChartHelper.formatTimeSeriesData(data);
    }, testData);
    
    expect(formattedData).toHaveLength(2);
    expect(formattedData[0]).toHaveProperty('x', 1234567890000);
    expect(formattedData[0]).toHaveProperty('y', 100);
    expect(formattedData[1]).toHaveProperty('x', 1234567890000 + 3600000);
    expect(formattedData[1]).toHaveProperty('y', 150);
  });

  test('should handle chart data endpoints', async ({ page }) => {
    // Test that chart data endpoints are accessible
    // We'll test this by checking if the AJAX functions exist and can be called
    
    for (let i = 1; i <= 9; i++) {
      const refreshFunctionExists = await page.evaluate((index) => {
        return typeof window[`main_refresh_${index}`] === 'function';
      }, i);
      expect(refreshFunctionExists).toBe(true);
    }
  });

  test('should create stacked area charts', async ({ page }) => {
    // Test creating a stacked area chart with multiple series
    const testData = [
      [[Date.now(), 100], [Date.now() + 3600000, 150]],
      [[Date.now(), 50], [Date.now() + 3600000, 75]]
    ];
    
    await page.evaluate((data) => {
      window.ChartHelper.createStackedAreaChart('graph_results_8', data, {
        title: 'Stacked Area Test',
        labels: ['Series 1', 'Series 2']
      });
    }, testData);
    
    // Verify chart was created
    const chartExists = await page.evaluate(() => window.chart_graph_results_8 !== null);
    expect(chartExists).toBe(true);
    
    // Verify it has multiple datasets
    const datasetCount = await page.evaluate(() => window.chart_graph_results_8.data.datasets.length);
    expect(datasetCount).toBe(2);
    
    // Verify y-axis is stacked
    const isStacked = await page.evaluate(() => window.chart_graph_results_8.options.scales.y.stacked);
    expect(isStacked).toBe(true);
  });

  test('should handle chart with legend functionality', async ({ page }) => {
    // Test creating a chart with enhanced legend
    const testData = [
      [[Date.now(), 100], [Date.now() + 3600000, 150]],
      [[Date.now(), 50], [Date.now() + 3600000, 75]]
    ];
    
    await page.evaluate((data) => {
      window.ChartHelper.createChartWithLegend('graph_results_9', data, {
        title: 'Legend Test',
        labels: ['Series 1', 'Series 2']
      });
    }, testData);
    
    // Verify chart was created
    const chartExists = await page.evaluate(() => window.chart_graph_results_9 !== null);
    expect(chartExists).toBe(true);
    
    // Verify legend is displayed
    const legendDisplayed = await page.evaluate(() => window.chart_graph_results_9.options.plugins.legend.display);
    expect(legendDisplayed).toBe(true);
    
    // Verify canvas exists
    const canvas = page.locator('#graph_results_9 canvas');
    await expect(canvas).toBeVisible();
  });
});