/**
 * Visual regression tests for admin pages
 * 
 * Feature: csp-inline-styles-removal, Property 2: Visual appearance preservation
 * Validates: Requirements 3.1, 3.2, 3.3
 * 
 * Tests:
 * - Test coin list with algorithm colors
 * - Verify warning cell styling
 * - Test table row highlighting
 */

const { test, expect } = require('@playwright/test');
const {
  waitForPageLoad,
  hideDynamicContent,
  VIEWPORTS,
  checkInlineStyles,
} = require('../utils/visual-test-helpers');

// Admin credentials for test environment
const ADMIN_USER = process.env.ADMIN_USER || 'admin';
const ADMIN_PASS = process.env.ADMIN_PASS || 'admin';

test.describe('Admin Pages Visual Regression', () => {

  // Helper to login to admin panel
  async function loginToAdmin(page) {
    await page.goto('/admin');
    
    // Check if already logged in
    const loginForm = page.locator('form[action*="login"], #login-form, .login-form');
    if (await loginForm.count() > 0) {
      // Fill login form
      await page.fill('input[name="username"], input[name="LoginForm[username]"]', ADMIN_USER);
      await page.fill('input[name="password"], input[name="LoginForm[password]"]', ADMIN_PASS);
      await page.click('button[type="submit"], input[type="submit"]');
      await waitForPageLoad(page);
    }
  }

  test('admin coin list renders correctly at desktop viewport', async ({ page }) => {
    await page.setViewportSize(VIEWPORTS.desktop);
    await loginToAdmin(page);
    await page.goto('/admin/coin');
    await waitForPageLoad(page);
    await hideDynamicContent(page);
    
    await expect(page).toHaveScreenshot('admin-coin-list-desktop.png', {
      fullPage: true,
      maxDiffPixelRatio: 0.001,
    });
  });

  test('admin coin list renders correctly at tablet viewport', async ({ page }) => {
    await page.setViewportSize(VIEWPORTS.tablet);
    await loginToAdmin(page);
    await page.goto('/admin/coin');
    await waitForPageLoad(page);
    await hideDynamicContent(page);
    
    await expect(page).toHaveScreenshot('admin-coin-list-tablet.png', {
      fullPage: true,
      maxDiffPixelRatio: 0.001,
    });
  });

  test('algorithm colors use CSS classes', async ({ page }) => {
    await loginToAdmin(page);
    await page.goto('/admin/coin');
    await waitForPageLoad(page);
    
    // Find rows with algorithm background colors
    const algoRows = page.locator('[class*="algo-bg-"], tr[data-algo], .algo-row');
    
    if (await algoRows.count() > 0) {
      const rowStyles = await algoRows.first().evaluate((el) => {
        const computed = window.getComputedStyle(el);
        return {
          backgroundColor: computed.backgroundColor,
          hasInlineStyle: el.hasAttribute('style'),
          inlineStyle: el.getAttribute('style'),
          className: el.className,
        };
      });
      
      // Should have algo-bg class, not inline background-color
      if (rowStyles.hasInlineStyle) {
        expect(rowStyles.inlineStyle).not.toContain('background-color');
      }
      
      // Take screenshot of algorithm colored rows
      await expect(algoRows.first()).toHaveScreenshot('admin-algo-row.png');
    }
  });

  test('warning cell styling uses CSS class', async ({ page }) => {
    await loginToAdmin(page);
    await page.goto('/admin/coin');
    await waitForPageLoad(page);
    
    // Find warning cells (price warnings, etc.)
    const warningCells = page.locator('.price-warning, .warning-cell, td.warning');
    
    if (await warningCells.count() > 0) {
      const cellStyles = await warningCells.first().evaluate((el) => {
        const computed = window.getComputedStyle(el);
        return {
          backgroundColor: computed.backgroundColor,
          hasInlineStyle: el.hasAttribute('style'),
          inlineStyle: el.getAttribute('style'),
          className: el.className,
        };
      });
      
      // Should use CSS class for warning styling
      expect(cellStyles.className).toMatch(/price-warning|warning/);
      
      if (cellStyles.hasInlineStyle) {
        expect(cellStyles.inlineStyle).not.toContain('background-color');
      }
      
      await expect(warningCells.first()).toHaveScreenshot('admin-warning-cell.png');
    }
  });

  test('table row highlighting works correctly', async ({ page }) => {
    await loginToAdmin(page);
    await page.goto('/admin/coin');
    await waitForPageLoad(page);
    
    // Find table rows
    const tableRows = page.locator('table tbody tr');
    
    if (await tableRows.count() > 0) {
      // Hover over a row to trigger highlight
      await tableRows.first().hover();
      await page.waitForTimeout(200);
      
      const rowStyles = await tableRows.first().evaluate((el) => {
        const computed = window.getComputedStyle(el);
        return {
          backgroundColor: computed.backgroundColor,
          hasInlineStyle: el.hasAttribute('style'),
        };
      });
      
      // Should not have inline style for hover
      expect(rowStyles.hasInlineStyle).toBe(false);
    }
  });

  test('coin wallet results page renders correctly', async ({ page }) => {
    await page.setViewportSize(VIEWPORTS.desktop);
    await loginToAdmin(page);
    await page.goto('/admin/coinwallet');
    await waitForPageLoad(page);
    await hideDynamicContent(page);
    
    await expect(page).toHaveScreenshot('admin-coinwallet.png', {
      fullPage: true,
      maxDiffPixelRatio: 0.001,
    });
  });

  test('common results page renders correctly', async ({ page }) => {
    await page.setViewportSize(VIEWPORTS.desktop);
    await loginToAdmin(page);
    await page.goto('/admin/common');
    await waitForPageLoad(page);
    await hideDynamicContent(page);
    
    await expect(page).toHaveScreenshot('admin-common.png', {
      fullPage: true,
      maxDiffPixelRatio: 0.001,
    });
  });

  test('coin creation form renders correctly', async ({ page }) => {
    await page.setViewportSize(VIEWPORTS.desktop);
    await loginToAdmin(page);
    await page.goto('/admin/coin/create');
    await waitForPageLoad(page);
    
    await expect(page).toHaveScreenshot('admin-coin-create.png', {
      fullPage: true,
      maxDiffPixelRatio: 0.001,
    });
  });

  test('coin update form renders correctly', async ({ page }) => {
    await page.setViewportSize(VIEWPORTS.desktop);
    await loginToAdmin(page);
    
    // Navigate to coin list and click first coin to edit
    await page.goto('/admin/coin');
    await waitForPageLoad(page);
    
    const editLink = page.locator('a[href*="/admin/coin/update"], .edit-link, a:has-text("Edit")');
    if (await editLink.count() > 0) {
      await editLink.first().click();
      await waitForPageLoad(page);
      
      await expect(page).toHaveScreenshot('admin-coin-update.png', {
        fullPage: true,
        maxDiffPixelRatio: 0.001,
      });
    }
  });

  test('form validation styling uses CSS classes', async ({ page }) => {
    await loginToAdmin(page);
    await page.goto('/admin/coin/create');
    await waitForPageLoad(page);
    
    // Submit empty form to trigger validation
    const submitButton = page.locator('button[type="submit"], input[type="submit"]');
    if (await submitButton.count() > 0) {
      await submitButton.click();
      await page.waitForTimeout(500);
      
      // Check for validation error styling
      const errorFields = page.locator('.has-error, .is-invalid, .field-error');
      
      if (await errorFields.count() > 0) {
        const fieldStyles = await errorFields.first().evaluate((el) => {
          return {
            hasInlineStyle: el.hasAttribute('style'),
            inlineStyle: el.getAttribute('style'),
            className: el.className,
          };
        });
        
        // Should use CSS class for error styling
        expect(fieldStyles.className).toMatch(/has-error|is-invalid|field-error/);
        
        if (fieldStyles.hasInlineStyle) {
          expect(fieldStyles.inlineStyle).not.toContain('background-color');
          expect(fieldStyles.inlineStyle).not.toContain('border-color');
        }
        
        await expect(errorFields.first()).toHaveScreenshot('admin-form-error.png');
      }
    }
  });

  test('admin navigation renders correctly', async ({ page }) => {
    await loginToAdmin(page);
    await page.goto('/admin');
    await waitForPageLoad(page);
    
    const adminNav = page.locator('.admin-nav, .sidebar, nav.admin');
    
    if (await adminNav.count() > 0) {
      await expect(adminNav.first()).toHaveScreenshot('admin-navigation.png', {
        maxDiffPixelRatio: 0.001,
      });
    }
  });

  test('no inline styles present on admin coin list', async ({ page }) => {
    await loginToAdmin(page);
    await page.goto('/admin/coin');
    await waitForPageLoad(page);
    
    const inlineStyles = await checkInlineStyles(page);
    
    if (inlineStyles.count > 0) {
      console.log('Found inline styles:', JSON.stringify(inlineStyles.elements, null, 2));
    }
    
    expect(inlineStyles.count).toBe(0);
  });

  test('no CSP violations on admin pages', async ({ page }) => {
    const violations = [];
    page.on('console', (msg) => {
      const text = msg.text();
      if (text.includes('Content Security Policy') || 
          text.includes('CSP') ||
          text.includes('style-src')) {
        violations.push(text);
      }
    });
    
    await loginToAdmin(page);
    await page.goto('/admin/coin');
    await waitForPageLoad(page);
    
    expect(violations).toHaveLength(0);
  });

  test('dropdown menus render without inline styles', async ({ page }) => {
    await loginToAdmin(page);
    await page.goto('/admin/coin/create');
    await waitForPageLoad(page);
    
    // Find dropdown/select elements
    const dropdowns = page.locator('select, .dropdown, .select2');
    
    if (await dropdowns.count() > 0) {
      const dropdownStyles = await dropdowns.first().evaluate((el) => {
        return {
          hasInlineStyle: el.hasAttribute('style'),
          inlineStyle: el.getAttribute('style'),
        };
      });
      
      // Should not have inline positioning styles
      if (dropdownStyles.hasInlineStyle) {
        expect(dropdownStyles.inlineStyle).not.toContain('position');
        expect(dropdownStyles.inlineStyle).not.toContain('top');
        expect(dropdownStyles.inlineStyle).not.toContain('left');
      }
    }
  });
});
