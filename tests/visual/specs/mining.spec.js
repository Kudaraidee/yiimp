/**
 * Visual regression tests for mining page
 * 
 * Feature: csp-inline-styles-removal, Property 2: Visual appearance preservation
 * Validates: Requirements 3.1, 3.2, 3.3
 * 
 * Tests:
 * - Test chart rendering with different algorithms
 * - Verify resume button styling
 * - Test responsive layouts
 */

const { test, expect } = require('@playwright/test');
const {
  waitForPageLoad,
  hideDynamicContent,
  VIEWPORTS,
  checkInlineStyles,
} = require('../utils/visual-test-helpers');

test.describe('Mining Page Visual Regression', () => {

  test('mining page renders correctly at desktop viewport', async ({ page }) => {
    await page.setViewportSize(VIEWPORTS.desktop);
    await page.goto('/site/mining');
    await waitForPageLoad(page);
    await hideDynamicContent(page);
    
    await expect(page).toHaveScreenshot('mining-desktop.png', {
      fullPage: true,
      maxDiffPixelRatio: 0.001,
    });
  });

  test('mining page renders correctly at mobile viewport', async ({ page }) => {
    await page.setViewportSize(VIEWPORTS.mobile);
    await page.goto('/site/mining');
    await waitForPageLoad(page);
    await hideDynamicContent(page);
    
    await expect(page).toHaveScreenshot('mining-mobile.png', {
      fullPage: true,
      maxDiffPixelRatio: 0.001,
    });
  });

  test('mining page renders correctly at tablet viewport', async ({ page }) => {
    await page.setViewportSize(VIEWPORTS.tablet);
    await page.goto('/site/mining');
    await waitForPageLoad(page);
    await hideDynamicContent(page);
    
    await expect(page).toHaveScreenshot('mining-tablet.png', {
      fullPage: true,
      maxDiffPixelRatio: 0.001,
    });
  });

  test('chart container has correct height styling', async ({ page }) => {
    await page.goto('/site/mining');
    await waitForPageLoad(page);
    
    // Find chart containers
    const chartContainers = page.locator('.chart-container-240, .chart-container-200, .chart-container-160, [class*="chart-container"]');
    
    if (await chartContainers.count() > 0) {
      // Verify chart container has CSS class-based height
      const containerStyles = await chartContainers.first().evaluate((el) => {
        const computed = window.getComputedStyle(el);
        return {
          height: computed.height,
          minHeight: computed.minHeight,
          hasInlineStyle: el.hasAttribute('style'),
          inlineStyle: el.getAttribute('style'),
        };
      });
      
      // Should not have inline style for height
      if (containerStyles.hasInlineStyle) {
        expect(containerStyles.inlineStyle).not.toContain('height');
      }
      
      // Take screenshot of chart area
      await expect(chartContainers.first()).toHaveScreenshot('mining-chart-container.png', {
        maxDiffPixelRatio: 0.001,
      });
    }
  });

  test('resume update button styling is correct', async ({ page }) => {
    await page.goto('/site/mining');
    await waitForPageLoad(page);
    
    // Find resume button
    const resumeButton = page.locator('.resume-update-button, #resume_update, button:has-text("Resume")');
    
    if (await resumeButton.count() > 0) {
      const buttonStyles = await resumeButton.first().evaluate((el) => {
        const computed = window.getComputedStyle(el);
        return {
          backgroundColor: computed.backgroundColor,
          color: computed.color,
          cursor: computed.cursor,
          borderRadius: computed.borderRadius,
          hasInlineStyle: el.hasAttribute('style'),
        };
      });
      
      // Button should use CSS class, not inline style
      expect(buttonStyles.hasInlineStyle).toBe(false);
      expect(buttonStyles.cursor).toBe('pointer');
      
      // Take screenshot of button
      await expect(resumeButton.first()).toHaveScreenshot('mining-resume-button.png');
    }
  });

  test('algorithm selection renders correctly', async ({ page }) => {
    await page.goto('/site/mining');
    await waitForPageLoad(page);
    
    // Find algorithm selector or list
    const algoSelector = page.locator('.algo-selector, .algorithm-list, select[name*="algo"]');
    
    if (await algoSelector.count() > 0) {
      await expect(algoSelector.first()).toHaveScreenshot('mining-algo-selector.png', {
        maxDiffPixelRatio: 0.001,
      });
    }
  });

  test('algorithm rows have correct background colors via CSS classes', async ({ page }) => {
    await page.goto('/site/mining');
    await waitForPageLoad(page);
    
    // Find rows with algorithm background colors
    const algoRows = page.locator('[class*="algo-bg-"], .algo-row');
    
    if (await algoRows.count() > 0) {
      // Verify colors are applied via CSS class, not inline style
      const rowStyles = await algoRows.first().evaluate((el) => {
        const computed = window.getComputedStyle(el);
        return {
          backgroundColor: computed.backgroundColor,
          hasInlineStyle: el.hasAttribute('style'),
          inlineStyle: el.getAttribute('style'),
          className: el.className,
        };
      });
      
      // Should have algo-bg class
      expect(rowStyles.className).toMatch(/algo-bg-|algo-row/);
      
      // Should not have inline background-color
      if (rowStyles.hasInlineStyle) {
        expect(rowStyles.inlineStyle).not.toContain('background-color');
      }
    }
  });

  test('no inline styles present on mining page', async ({ page }) => {
    await page.goto('/site/mining');
    await waitForPageLoad(page);
    
    const inlineStyles = await checkInlineStyles(page);
    
    if (inlineStyles.count > 0) {
      console.log('Found inline styles:', JSON.stringify(inlineStyles.elements, null, 2));
    }
    
    expect(inlineStyles.count).toBe(0);
  });

  test('no CSP violations on mining page', async ({ page }) => {
    const violations = [];
    page.on('console', (msg) => {
      const text = msg.text();
      if (text.includes('Content Security Policy') || 
          text.includes('CSP') ||
          text.includes('style-src')) {
        violations.push(text);
      }
    });
    
    await page.goto('/site/mining');
    await waitForPageLoad(page);
    
    expect(violations).toHaveLength(0);
  });

  test('mining statistics table renders correctly', async ({ page }) => {
    await page.goto('/site/mining');
    await waitForPageLoad(page);
    await hideDynamicContent(page);
    
    // Find statistics table
    const statsTable = page.locator('table.table, .mining-stats, .pool-stats');
    
    if (await statsTable.count() > 0) {
      await expect(statsTable.first()).toHaveScreenshot('mining-stats-table.png', {
        maxDiffPixelRatio: 0.001,
      });
    }
  });

  test('responsive layout adjusts correctly', async ({ page }) => {
    await page.goto('/site/mining');
    await waitForPageLoad(page);
    
    // Test layout at different breakpoints
    const breakpoints = [
      { name: 'xl', width: 1400 },
      { name: 'lg', width: 1200 },
      { name: 'md', width: 992 },
      { name: 'sm', width: 768 },
      { name: 'xs', width: 576 },
    ];
    
    for (const bp of breakpoints) {
      await page.setViewportSize({ width: bp.width, height: 800 });
      await page.waitForTimeout(300);
      await hideDynamicContent(page);
      
      await expect(page).toHaveScreenshot(`mining-responsive-${bp.name}.png`, {
        fullPage: true,
        maxDiffPixelRatio: 0.001,
      });
    }
  });
});
