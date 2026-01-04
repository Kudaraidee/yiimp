/**
 * Playwright configuration for visual regression testing
 * 
 * Feature: csp-inline-styles-removal
 * Validates: Requirements 3.1, 3.2, 3.3
 */

const { defineConfig, devices } = require('@playwright/test');

module.exports = defineConfig({
  testDir: './specs',
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  workers: process.env.CI ? 1 : undefined,
  reporter: [
    ['html', { outputFolder: 'playwright-report' }],
    ['list']
  ],
  
  // Snapshot configuration for visual regression
  expect: {
    toHaveScreenshot: {
      // 0.1% pixel difference tolerance
      maxDiffPixelRatio: 0.001,
      // Allow small anti-aliasing differences
      threshold: 0.2,
    },
    toMatchSnapshot: {
      maxDiffPixelRatio: 0.001,
    },
  },
  
  // Output directories
  outputDir: 'test-results',
  snapshotDir: 'snapshots',
  
  use: {
    // Base URL for the test environment
    baseURL: process.env.TEST_BASE_URL || 'http://localhost:8090',
    
    // Capture trace on failure
    trace: 'on-first-retry',
    
    // Screenshot on failure
    screenshot: 'only-on-failure',
    
    // Video on failure
    video: 'on-first-retry',
  },

  projects: [
    // Desktop Chrome
    {
      name: 'desktop-chrome',
      use: { 
        ...devices['Desktop Chrome'],
        viewport: { width: 1920, height: 1080 },
      },
    },
    
    // Desktop Firefox
    {
      name: 'desktop-firefox',
      use: { 
        ...devices['Desktop Firefox'],
        viewport: { width: 1920, height: 1080 },
      },
    },
    
    // Mobile viewport
    {
      name: 'mobile-chrome',
      use: { 
        ...devices['Pixel 5'],
      },
    },
    
    // Tablet viewport
    {
      name: 'tablet',
      use: {
        viewport: { width: 768, height: 1024 },
        userAgent: 'Mozilla/5.0 (iPad; CPU OS 15_0 like Mac OS X) AppleWebKit/605.1.15',
      },
    },
  ],
});
