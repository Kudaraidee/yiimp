/**
 * Visual regression tests for homepage
 * 
 * Feature: csp-inline-styles-removal, Property 2: Visual appearance preservation
 * Validates: Requirements 3.1, 3.2, 3.3
 * 
 * Tests:
 * - Capture screenshots at desktop and mobile breakpoints
 * - Compare before/after with pixel-diff
 * - Assert visual consistency within 0.1% tolerance
 */

const { test, expect } = require('@playwright/test');
const {
  waitForPageLoad,
  hideDynamicContent,
  VIEWPORTS,
  checkInlineStyles,
  collectCspViolations,
} = require('../utils/visual-test-helpers');

test.describe('Homepage Visual Regression', () => {
  
  test.beforeEach(async ({ page }) => {
    // Set up CSP violation collection
    const violations = [];
    page.on('console', (msg) => {
      const text = msg.text();
      if (text.includes('Content Security Policy') || text.includes('CSP')) {
        violations.push(text);
      }
    });
    page.violations = violations;
  });

  test('homepage renders correctly at desktop viewport', async ({ page }) => {
    await page.setViewportSize(VIEWPORTS.desktop);
    await page.goto('/');
    await waitForPageLoad(page);
    await hideDynamicContent(page);
    
    // Take full page screenshot
    await expect(page).toHaveScreenshot('homepage-desktop.png', {
      fullPage: true,
      maxDiffPixelRatio: 0.001,
    });
  });

  test('homepage renders correctly at mobile viewport', async ({ page }) => {
    await page.setViewportSize(VIEWPORTS.mobile);
    await page.goto('/');
    await waitForPageLoad(page);
    await hideDynamicContent(page);
    
    // Take full page screenshot
    await expect(page).toHaveScreenshot('homepage-mobile.png', {
      fullPage: true,
      maxDiffPixelRatio: 0.001,
    });
  });

  test('homepage renders correctly at tablet viewport', async ({ page }) => {
    await page.setViewportSize(VIEWPORTS.tablet);
    await page.goto('/');
    await waitForPageLoad(page);
    await hideDynamicContent(page);
    
    // Take full page screenshot
    await expect(page).toHaveScreenshot('homepage-tablet.png', {
      fullPage: true,
      maxDiffPixelRatio: 0.001,
    });
  });

  test('resume update button styling is correct', async ({ page }) => {
    await page.goto('/');
    await waitForPageLoad(page);
    
    // Find resume button if it exists
    const resumeButton = page.locator('.resume-update-button, #resume_update');
    
    if (await resumeButton.count() > 0) {
      // Verify button has correct CSS class styling
      const buttonStyles = await resumeButton.evaluate((el) => {
        const computed = window.getComputedStyle(el);
        return {
          backgroundColor: computed.backgroundColor,
          color: computed.color,
          cursor: computed.cursor,
          padding: computed.padding,
        };
      });
      
      // Button should have styling applied (not default)
      expect(buttonStyles.cursor).toBe('pointer');
      
      // Take screenshot of button area
      await expect(resumeButton).toHaveScreenshot('homepage-resume-button.png');
    }
  });

  test('pool statistics section renders correctly', async ({ page }) => {
    await page.goto('/');
    await waitForPageLoad(page);
    await hideDynamicContent(page);
    
    // Screenshot of pool current results section
    const poolCurrent = page.locator('#pool_current_results');
    if (await poolCurrent.count() > 0) {
      await expect(poolCurrent).toHaveScreenshot('homepage-pool-current.png', {
        maxDiffPixelRatio: 0.001,
      });
    }
  });

  test('no inline styles present on homepage', async ({ page }) => {
    await page.goto('/');
    await waitForPageLoad(page);
    
    const inlineStyles = await checkInlineStyles(page);
    
    // Log any found inline styles for debugging
    if (inlineStyles.count > 0) {
      console.log('Found inline styles:', JSON.stringify(inlineStyles.elements, null, 2));
    }
    
    // Assert no inline styles (Property 1: No inline styles in rendered HTML)
    expect(inlineStyles.count).toBe(0);
  });

  test('no CSP violations on homepage', async ({ page }) => {
    const violations = [];
    page.on('console', (msg) => {
      const text = msg.text();
      if (text.includes('Content Security Policy') || 
          text.includes('CSP') ||
          text.includes('style-src')) {
        violations.push(text);
      }
    });
    
    await page.goto('/');
    await waitForPageLoad(page);
    
    // Assert no CSP violations
    expect(violations).toHaveLength(0);
  });

  test('navigation elements render consistently', async ({ page }) => {
    await page.goto('/');
    await waitForPageLoad(page);
    
    // Screenshot of navigation area
    const nav = page.locator('nav, .navbar, header');
    if (await nav.count() > 0) {
      await expect(nav.first()).toHaveScreenshot('homepage-navigation.png', {
        maxDiffPixelRatio: 0.001,
      });
    }
  });

  test('footer renders consistently', async ({ page }) => {
    await page.goto('/');
    await waitForPageLoad(page);
    
    // Screenshot of footer area
    const footer = page.locator('footer, .footer');
    if (await footer.count() > 0) {
      await expect(footer.first()).toHaveScreenshot('homepage-footer.png', {
        maxDiffPixelRatio: 0.001,
      });
    }
  });
});
