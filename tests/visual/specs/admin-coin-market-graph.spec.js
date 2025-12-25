const { test, expect } = require('@playwright/test');
const { setupTestEnvironment } = require('../utils/test-data-setup');

/**
 * Admin Coin Market Graph Functionality Tests
 * 
 * This test suite verifies that:
 * 1. Admin coin market page loads and displays price/balance charts
 * 2. Chart resizing and responsive behavior works correctly
 * 3. Chart data updates work correctly
 * 4. Local Chart.js files are used instead of CDN
 * 
 * Requirements: 5.3
 */

test.describe('Admin Coin Market Graph Functionality', () => {
  let hasCoins = false;
  let testCoinId = null;
  
  test.beforeAll(async ({ browser }) => {
    // Setup test environment with login and test data
    const page = await browser.newPage();
    const testEnv = await setupTestEnvironment(page);
    hasCoins = testEnv.hasAlgorithms; // If algorithms exist, coins exist
    testCoinId = testEnv.coinId;
    
    if (hasCoins && testCoinId) {
      console.log(`Coins available for testing, using coin ID: ${testCoinId}`);
    } else {
      console.log('No coins available or admin access restricted - tests will be skipped');
    }
    
    await page.close();
  });

  test.beforeEach(async ({ page }) => {
    // Skip all tests if no coins are available or admin access is restricted
    if (!hasCoins || !testCoinId) {
      test.skip('No coins available or admin access restricted - need coins in database and admin access');
    }
  });

  test('should load admin coin market graph page successfully', async ({ page }) => {
    await page.goto(`/admin/coin_market_graph/${testCoinId}`);
    await page.waitForLoadState('networkidle');
    
    // Verify page loads without errors
    expect(page.url()).toContain('coin_market_graph');
    
    // Wait for Chart.js to be available
    await page.waitForFunction(() => typeof window.Chart !== 'undefined');
    await page.waitForFunction(() => typeof window.ChartHelper !== 'undefined');
  });

  test('should have price and balance chart containers', async ({ page }) => {
    await page.goto(`/admin/coin_market_graph/${testCoinId}`);
    await page.waitForLoadState('networkidle');
    
    // Verify both chart containers exist
    const priceContainer = page.locator('#graph_history_price');
    const balanceContainer = page.locator('#graph_history_balance');
    
    await expect(priceContainer).toBeVisible();
    await expect(balanceContainer).toBeVisible();
    
    // Verify they have the correct CSS classes
    await expect(priceContainer).toHaveClass(/graph/);
    await expect(balanceContainer).toHaveClass(/graph/);
  });

  test('should load Chart.js locally (not from CDN)', async ({ page }) => {
    const requests = [];
    page.on('request', request => requests.push(request.url()));
    
    await page.goto(`/admin/coin_market_graph/${testCoinId}`);
    await page.waitForLoadState('networkidle');
    
    // Check that Chart.js is available
    const chartAvailable = await page.evaluate(() => typeof window.Chart !== 'undefined');
    expect(chartAvailable).toBe(true);
    
    // Verify no CDN requests were made
    const cdnRequests = requests.filter(url => 
      url.includes('cdn.jsdelivr.net') || 
      url.includes('cdnjs.cloudflare.com') || 
      url.includes('unpkg.com')
    );
    
    expect(cdnRequests).toHaveLength(0);
    
    // Verify local Chart.js files are loaded
    const localChartRequests = requests.filter(url => 
      url.includes('js/vendor/chart.js/chart.umd.min.js') ||
      url.includes('js/vendor/chartjs-adapter-date-fns/chartjs-adapter-date-fns.bundle.min.js')
    );
    
    expect(localChartRequests.length).toBeGreaterThan(0);
  });

  test('should have chart refresh functions defined', async ({ page }) => {
    await page.goto(`/admin/coin_market_graph/${testCoinId}`);
    await page.waitForLoadState('networkidle');
    
    // Verify chart functions exist
    const functions = ['graph_refresh', 'graph_resized', 'graph_price_data', 'graph_balance_data'];
    
    for (const func of functions) {
      const funcType = await page.evaluate((funcName) => typeof window[funcName], func);
      expect(funcType).toBe('function');
    }
  });

  test('should create price chart successfully', async ({ page }) => {
    await page.goto(`/admin/coin_market_graph/${testCoinId}`);
    await page.waitForLoadState('networkidle');
    
    // Wait for initial chart load
    await page.waitForTimeout(2000);
    
    // Test creating a price chart with mock data
    const mockPriceData = {
      data: [[[Date.now(), 0.001], [Date.now() + 3600000, 0.0015]]],
      labels: ['Price'],
      rangeMin: 0,
      rangeMax: 0.002
    };
    
    await page.evaluate((data) => {
      window.graph_price_data(JSON.stringify(data));
    }, mockPriceData);
    
    // Verify price chart was created
    const priceChartExists = await page.evaluate(() => window.price_graph !== null && window.price_graph !== undefined);
    expect(priceChartExists).toBe(true);
    
    // Verify canvas element was created
    const priceCanvas = page.locator('#graph_history_price canvas');
    await expect(priceCanvas).toBeVisible();
    
    // Verify it's a Chart.js instance
    const isChartInstance = await page.evaluate(() => window.price_graph.constructor.name === 'Chart');
    expect(isChartInstance).toBe(true);
  });

  test('should create balance chart successfully', async ({ page }) => {
    await page.goto(`/admin/coin_market_graph/${testCoinId}`);
    await page.waitForLoadState('networkidle');
    
    // Wait for initial chart load
    await page.waitForTimeout(2000);
    
    // Test creating a balance chart with mock data
    const mockBalanceData = {
      data: [
        [[Date.now(), 100], [Date.now() + 3600000, 150]],
        [[Date.now(), 50], [Date.now() + 3600000, 75]]
      ],
      labels: ['Available', 'Immature'],
      rangeMin: 0,
      rangeMax: 200
    };
    
    await page.evaluate((data) => {
      window.graph_balance_data(JSON.stringify(data));
    }, mockBalanceData);
    
    // Verify balance chart was created
    const balanceChartExists = await page.evaluate(() => window.balance_graph !== null && window.balance_graph !== undefined);
    expect(balanceChartExists).toBe(true);
    
    // Verify canvas element was created
    const balanceCanvas = page.locator('#graph_history_balance canvas');
    await expect(balanceCanvas).toBeVisible();
    
    // Verify it's configured as a stacked chart
    const isStacked = await page.evaluate(() => window.balance_graph.options.scales.y.stacked);
    expect(isStacked).toBe(true);
  });

  test('should handle chart resizing', async ({ page }) => {
    await page.goto(`/admin/coin_market_graph/${testCoinId}`);
    await page.waitForLoadState('networkidle');
    
    // Test chart resizing function
    const canCallResize = await page.evaluate(() => {
      try {
        if (typeof graph_resized === 'function') {
          window.graph_resized();
          return true;
        }
        return false;
      } catch(e) {
        return false;
      }
    });
    expect(canCallResize).toBe(true);
    
    // Verify that graph_need_update flag is set
    const needsUpdate = await page.evaluate(() => window.graph_need_update);
    expect(needsUpdate).toBe(true);
  });

  test('should have responsive chart configuration', async ({ page }) => {
    await page.goto(`/admin/coin_market_graph/${testCoinId}`);
    await page.waitForLoadState('networkidle');
    
    // Create test charts
    const mockData = {
      data: [[[Date.now(), 100]]],
      labels: ['Test'],
      rangeMin: 0,
      rangeMax: 200
    };
    
    await page.evaluate((data) => {
      window.graph_price_data(JSON.stringify(data));
      window.graph_balance_data(JSON.stringify(data));
    }, mockData);
    
    // Verify charts have responsive configuration
    const priceResponsive = await page.evaluate(() => window.price_graph && window.price_graph.options.responsive);
    const balanceResponsive = await page.evaluate(() => window.balance_graph && window.balance_graph.options.responsive);
    
    expect(priceResponsive).toBe(true);
    expect(balanceResponsive).toBe(true);
    
    const priceMaintainAspect = await page.evaluate(() => window.price_graph && window.price_graph.options.maintainAspectRatio);
    const balanceMaintainAspect = await page.evaluate(() => window.balance_graph && window.balance_graph.options.maintainAspectRatio);
    
    expect(priceMaintainAspect).toBe(false);
    expect(balanceMaintainAspect).toBe(false);
  });

  test('should have proper chart titles and legends', async ({ page }) => {
    await page.goto(`/admin/coin_market_graph/${testCoinId}`);
    await page.waitForLoadState('networkidle');
    
    // Create test charts
    const mockPriceData = {
      data: [[[Date.now(), 0.001]]],
      labels: ['BTC Price'],
      rangeMin: 0,
      rangeMax: 0.002
    };
    
    const mockBalanceData = {
      data: [[[Date.now(), 100]], [[Date.now(), 50]]],
      labels: ['Available', 'Immature'],
      rangeMin: 0,
      rangeMax: 200
    };
    
    await page.evaluate((priceData, balanceData) => {
      window.graph_price_data(JSON.stringify(priceData));
      window.graph_balance_data(JSON.stringify(balanceData));
    }, mockPriceData, mockBalanceData);
    
    // Verify chart titles
    const priceTitle = await page.evaluate(() => 
      window.price_graph && window.price_graph.options.plugins.title.text
    );
    const balanceTitle = await page.evaluate(() => 
      window.balance_graph && window.balance_graph.options.plugins.title.text
    );
    
    expect(priceTitle).toBe('Price history');
    expect(balanceTitle).toBe('Balances');
    
    // Verify legends are displayed
    const priceLegend = await page.evaluate(() => 
      window.price_graph && window.price_graph.options.plugins.legend.display
    );
    const balanceLegend = await page.evaluate(() => 
      window.balance_graph && window.balance_graph.options.plugins.legend.display
    );
    
    expect(priceLegend).toBe(true);
    expect(balanceLegend).toBe(true);
  });

  test('should handle chart interactions and tooltips', async ({ page }) => {
    await page.goto(`/admin/coin_market_graph/${testCoinId}`);
    await page.waitForLoadState('networkidle');
    
    // Create test chart with data
    const mockData = {
      data: [[[Date.now(), 100], [Date.now() + 3600000, 150]]],
      labels: ['Test Series'],
      rangeMin: 0,
      rangeMax: 200
    };
    
    await page.evaluate((data) => {
      window.graph_price_data(JSON.stringify(data));
    }, mockData);
    
    // Test hover interaction on price chart
    const priceCanvas = page.locator('#graph_history_price canvas');
    await expect(priceCanvas).toBeVisible();
    
    await priceCanvas.hover();
    
    // Verify chart still exists after interaction
    const chartStillExists = await page.evaluate(() => window.price_graph !== null);
    expect(chartStillExists).toBe(true);
    
    // Verify tooltip configuration
    const tooltipMode = await page.evaluate(() => 
      window.price_graph && window.price_graph.options.plugins.tooltip.mode
    );
    expect(tooltipMode).toBe('index');
  });

  test('should handle legend click interactions', async ({ page }) => {
    await page.goto(`/admin/coin_market_graph/${testCoinId}`);
    await page.waitForLoadState('networkidle');
    
    // Create test chart with multiple series
    const mockData = {
      data: [
        [[Date.now(), 100], [Date.now() + 3600000, 150]],
        [[Date.now(), 50], [Date.now() + 3600000, 75]]
      ],
      labels: ['Series 1', 'Series 2'],
      rangeMin: 0,
      rangeMax: 200
    };
    
    await page.evaluate((data) => {
      window.graph_balance_data(JSON.stringify(data));
    }, mockData);
    
    // Verify chart has multiple datasets
    const datasetCount = await page.evaluate(() => 
      window.balance_graph && window.balance_graph.data.datasets.length
    );
    expect(datasetCount).toBe(2);
    
    // Verify legend click handler exists
    const hasLegendClick = await page.evaluate(() => 
      window.balance_graph && 
      typeof window.balance_graph.options.plugins.legend.onClick === 'function'
    );
    expect(hasLegendClick).toBe(true);
  });

  test('should destroy and recreate charts properly', async ({ page }) => {
    await page.goto(`/admin/coin_market_graph/${testCoinId}`);
    await page.waitForLoadState('networkidle');
    
    // Create initial charts
    const mockData = {
      data: [[[Date.now(), 100]]],
      labels: ['Test'],
      rangeMin: 0,
      rangeMax: 200
    };
    
    await page.evaluate((data) => {
      window.graph_price_data(JSON.stringify(data));
      window.graph_balance_data(JSON.stringify(data));
    }, mockData);
    
    // Verify charts exist
    const chartsExist = await page.evaluate(() => 
      window.price_graph !== null && window.balance_graph !== null
    );
    expect(chartsExist).toBe(true);
    
    // Recreate charts (should destroy old ones first)
    const newMockData = {
      data: [[[Date.now(), 200]]],
      labels: ['New Test'],
      rangeMin: 0,
      rangeMax: 300
    };
    
    await page.evaluate((data) => {
      window.graph_price_data(JSON.stringify(data));
      window.graph_balance_data(JSON.stringify(data));
    }, newMockData);
    
    // Verify charts still exist (new instances)
    const newChartsExist = await page.evaluate(() => 
      window.price_graph !== null && window.balance_graph !== null
    );
    expect(newChartsExist).toBe(true);
  });

  test('should handle empty or invalid data gracefully', async ({ page }) => {
    await page.goto(`/admin/coin_market_graph/${testCoinId}`);
    await page.waitForLoadState('networkidle');
    
    // Test with empty data
    const emptyData = {
      data: [[]],
      labels: ['Empty'],
      rangeMin: 0,
      rangeMax: 100
    };
    
    await page.evaluate((data) => {
      window.graph_price_data(JSON.stringify(data));
    }, emptyData);
    
    // Verify chart was still created
    const chartExists = await page.evaluate(() => window.price_graph !== null);
    expect(chartExists).toBe(true);
    
    // Verify canvas exists
    const canvas = page.locator('#graph_history_price canvas');
    await expect(canvas).toBeVisible();
  });

  test('should have proper time axis configuration', async ({ page }) => {
    await page.goto(`/admin/coin_market_graph/${testCoinId}`);
    await page.waitForLoadState('networkidle');
    
    // Create test chart
    const mockData = {
      data: [[[Date.now(), 100]]],
      labels: ['Test'],
      rangeMin: 0,
      rangeMax: 200
    };
    
    await page.evaluate((data) => {
      window.graph_price_data(JSON.stringify(data));
    }, mockData);
    
    // Verify time axis configuration
    const xAxisType = await page.evaluate(() => 
      window.price_graph && window.price_graph.options.scales.x.type
    );
    expect(xAxisType).toBe('time');
    
    // Verify time display formats
    const timeFormats = await page.evaluate(() => 
      window.price_graph && window.price_graph.options.scales.x.time.displayFormats
    );
    expect(timeFormats).toHaveProperty('hour', 'HH:mm');
    expect(timeFormats).toHaveProperty('day', 'MMM d');
  });
});