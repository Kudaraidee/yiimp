/**
 * Visual regression tests for wallet page
 * 
 * Feature: csp-inline-styles-removal, Property 2: Visual appearance preservation
 * Validates: Requirements 3.1, 3.2, 3.3
 * 
 * Tests:
 * - Test with and without wallet data
 * - Verify selected row highlighting
 * - Test chart legend styling
 */

const { test, expect } = require('@playwright/test');
const {
  waitForPageLoad,
  hideDynamicContent,
  VIEWPORTS,
  checkInlineStyles,
} = require('../utils/visual-test-helpers');

// Test wallet addresses (use test addresses that may exist in test data)
const TEST_WALLET = 'TEST_WALLET_ADDRESS';
const EMPTY_WALLET = 'NONEXISTENT_WALLET_12345';

test.describe('Wallet Page Visual Regression', () => {

  test('wallet page renders correctly at desktop viewport', async ({ page }) => {
    await page.setViewportSize(VIEWPORTS.desktop);
    await page.goto(`/site/wallet?address=${TEST_WALLET}`);
    await waitForPageLoad(page);
    await hideDynamicContent(page);
    
    await expect(page).toHaveScreenshot('wallet-desktop.png', {
      fullPage: true,
      maxDiffPixelRatio: 0.001,
    });
  });

  test('wallet page renders correctly at mobile viewport', async ({ page }) => {
    await page.setViewportSize(VIEWPORTS.mobile);
    await page.goto(`/site/wallet?address=${TEST_WALLET}`);
    await waitForPageLoad(page);
    await hideDynamicContent(page);
    
    await expect(page).toHaveScreenshot('wallet-mobile.png', {
      fullPage: true,
      maxDiffPixelRatio: 0.001,
    });
  });

  test('wallet page renders correctly at tablet viewport', async ({ page }) => {
    await page.setViewportSize(VIEWPORTS.tablet);
    await page.goto(`/site/wallet?address=${TEST_WALLET}`);
    await waitForPageLoad(page);
    await hideDynamicContent(page);
    
    await expect(page).toHaveScreenshot('wallet-tablet.png', {
      fullPage: true,
      maxDiffPixelRatio: 0.001,
    });
  });

  test('wallet page without data renders correctly', async ({ page }) => {
    await page.setViewportSize(VIEWPORTS.desktop);
    await page.goto(`/site/wallet?address=${EMPTY_WALLET}`);
    await waitForPageLoad(page);
    await hideDynamicContent(page);
    
    await expect(page).toHaveScreenshot('wallet-empty.png', {
      fullPage: true,
      maxDiffPixelRatio: 0.001,
    });
  });

  test('selected row highlighting uses CSS class', async ({ page }) => {
    await page.goto(`/site/wallet?address=${TEST_WALLET}`);
    await waitForPageLoad(page);
    
    // Find selectable rows
    const selectableRows = page.locator('tr.selectable, tr[data-selectable], tbody tr');
    
    if (await selectableRows.count() > 0) {
      // Click to select a row
      await selectableRows.first().click();
      await page.waitForTimeout(300);
      
      // Find selected row
      const selectedRow = page.locator('tr.row-selected, tr.selected, tr.active');
      
      if (await selectedRow.count() > 0) {
        const rowStyles = await selectedRow.first().evaluate((el) => {
          const computed = window.getComputedStyle(el);
          return {
            backgroundColor: computed.backgroundColor,
            hasInlineStyle: el.hasAttribute('style'),
            inlineStyle: el.getAttribute('style'),
            className: el.className,
          };
        });
        
        // Should use CSS class for selection, not inline style
        expect(rowStyles.className).toMatch(/row-selected|selected|active/);
        
        if (rowStyles.hasInlineStyle) {
          expect(rowStyles.inlineStyle).not.toContain('background-color');
        }
        
        // Take screenshot of selected row
        await expect(selectedRow.first()).toHaveScreenshot('wallet-selected-row.png');
      }
    }
  });

  test('chart container has correct height styling', async ({ page }) => {
    await page.goto(`/site/wallet?address=${TEST_WALLET}`);
    await waitForPageLoad(page);
    
    const chartContainers = page.locator('.chart-container-240, .chart-container-200, [class*="chart-container"]');
    
    if (await chartContainers.count() > 0) {
      const containerStyles = await chartContainers.first().evaluate((el) => {
        const computed = window.getComputedStyle(el);
        return {
          height: computed.height,
          hasInlineStyle: el.hasAttribute('style'),
          inlineStyle: el.getAttribute('style'),
        };
      });
      
      // Should not have inline style for height
      if (containerStyles.hasInlineStyle) {
        expect(containerStyles.inlineStyle).not.toContain('height');
      }
      
      await expect(chartContainers.first()).toHaveScreenshot('wallet-chart-container.png', {
        maxDiffPixelRatio: 0.001,
      });
    }
  });

  test('chart legend styling uses CSS classes', async ({ page }) => {
    await page.goto(`/site/wallet?address=${TEST_WALLET}`);
    await waitForPageLoad(page);
    
    // Find chart legend
    const legend = page.locator('.chart-legend, .legend');
    
    if (await legend.count() > 0) {
      const legendStyles = await legend.first().evaluate((el) => {
        const computed = window.getComputedStyle(el);
        return {
          float: computed.float,
          fontSize: computed.fontSize,
          hasInlineStyle: el.hasAttribute('style'),
          inlineStyle: el.getAttribute('style'),
        };
      });
      
      // Should not have inline float or font-size
      if (legendStyles.hasInlineStyle) {
        expect(legendStyles.inlineStyle).not.toContain('float');
        expect(legendStyles.inlineStyle).not.toContain('font-size');
      }
      
      await expect(legend.first()).toHaveScreenshot('wallet-chart-legend.png', {
        maxDiffPixelRatio: 0.001,
      });
    }
  });

  test('resume update button styling is correct', async ({ page }) => {
    await page.goto(`/site/wallet?address=${TEST_WALLET}`);
    await waitForPageLoad(page);
    
    const resumeButton = page.locator('.resume-update-button, #resume_update');
    
    if (await resumeButton.count() > 0) {
      const buttonStyles = await resumeButton.first().evaluate((el) => {
        const computed = window.getComputedStyle(el);
        return {
          backgroundColor: computed.backgroundColor,
          color: computed.color,
          cursor: computed.cursor,
          hasInlineStyle: el.hasAttribute('style'),
        };
      });
      
      expect(buttonStyles.hasInlineStyle).toBe(false);
      expect(buttonStyles.cursor).toBe('pointer');
      
      await expect(resumeButton.first()).toHaveScreenshot('wallet-resume-button.png');
    }
  });

  test('wallet balance section renders correctly', async ({ page }) => {
    await page.goto(`/site/wallet?address=${TEST_WALLET}`);
    await waitForPageLoad(page);
    await hideDynamicContent(page);
    
    const balanceSection = page.locator('.wallet-balance, .balance-info, .account-balance');
    
    if (await balanceSection.count() > 0) {
      await expect(balanceSection.first()).toHaveScreenshot('wallet-balance-section.png', {
        maxDiffPixelRatio: 0.001,
      });
    }
  });

  test('miners table renders correctly', async ({ page }) => {
    await page.goto(`/site/wallet?address=${TEST_WALLET}`);
    await waitForPageLoad(page);
    await hideDynamicContent(page);
    
    const minersTable = page.locator('.miners-table, #wallet_miners_results, table.miners');
    
    if (await minersTable.count() > 0) {
      await expect(minersTable.first()).toHaveScreenshot('wallet-miners-table.png', {
        maxDiffPixelRatio: 0.001,
      });
    }
  });

  test('text-small utility class is applied correctly', async ({ page }) => {
    await page.goto(`/site/wallet?address=${TEST_WALLET}`);
    await waitForPageLoad(page);
    
    const smallTextElements = page.locator('.text-small, .small-text');
    
    if (await smallTextElements.count() > 0) {
      const textStyles = await smallTextElements.first().evaluate((el) => {
        const computed = window.getComputedStyle(el);
        return {
          fontSize: computed.fontSize,
          hasInlineStyle: el.hasAttribute('style'),
          inlineStyle: el.getAttribute('style'),
        };
      });
      
      // Should not have inline font-size
      if (textStyles.hasInlineStyle) {
        expect(textStyles.inlineStyle).not.toContain('font-size');
      }
    }
  });

  test('no inline styles present on wallet page', async ({ page }) => {
    await page.goto(`/site/wallet?address=${TEST_WALLET}`);
    await waitForPageLoad(page);
    
    const inlineStyles = await checkInlineStyles(page);
    
    if (inlineStyles.count > 0) {
      console.log('Found inline styles:', JSON.stringify(inlineStyles.elements, null, 2));
    }
    
    expect(inlineStyles.count).toBe(0);
  });

  test('no CSP violations on wallet page', async ({ page }) => {
    const violations = [];
    page.on('console', (msg) => {
      const text = msg.text();
      if (text.includes('Content Security Policy') || 
          text.includes('CSP') ||
          text.includes('style-src')) {
        violations.push(text);
      }
    });
    
    await page.goto(`/site/wallet?address=${TEST_WALLET}`);
    await waitForPageLoad(page);
    
    expect(violations).toHaveLength(0);
  });
});
