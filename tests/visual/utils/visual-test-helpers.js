/**
 * Visual regression test helpers
 * 
 * Feature: csp-inline-styles-removal
 * Validates: Requirements 3.1, 3.2, 3.3
 */

const { expect } = require('@playwright/test');

/**
 * Viewport configurations for responsive testing
 */
const VIEWPORTS = {
  desktop: { width: 1920, height: 1080 },
  laptop: { width: 1366, height: 768 },
  tablet: { width: 768, height: 1024 },
  mobile: { width: 375, height: 667 },
};

/**
 * Wait for page to be fully loaded including AJAX content
 * @param {Page} page - Playwright page object
 * @param {number} timeout - Maximum wait time in ms
 */
async function waitForPageLoad(page, timeout = 10000) {
  // Wait for network to be idle
  await page.waitForLoadState('networkidle', { timeout });
  
  // Wait for any AJAX content containers to populate
  const ajaxContainers = [
    '#pool_current_results',
    '#pool_history_results',
    '#pool_coins_info',
  ];
  
  for (const selector of ajaxContainers) {
    const element = page.locator(selector);
    if (await element.count() > 0) {
      // Wait for content to appear (not just empty)
      try {
        await page.waitForFunction(
          (sel) => {
            const el = document.querySelector(sel);
            return el && el.innerHTML.trim().length > 10;
          },
          selector,
          { timeout: 5000 }
        );
      } catch (e) {
        // Container might not have content, continue
      }
    }
  }
  
  // Additional wait for CSS animations to complete
  await page.waitForTimeout(500);
}

/**
 * Hide dynamic content that changes between runs
 * Uses page.evaluate to avoid CSP violations from addStyleTag
 * @param {Page} page - Playwright page object
 */
async function hideDynamicContent(page) {
  await page.evaluate(() => {
    // Hide timestamps and dynamic values by setting visibility via classList
    const dynamicSelectors = [
      '.timestamp', '.time-ago', '.last-updated',
      '.hashrate-value', '.worker-count'
    ];
    
    dynamicSelectors.forEach(selector => {
      document.querySelectorAll(selector).forEach(el => {
        el.style.setProperty('visibility', 'hidden', 'important');
      });
    });
    
    // Disable animations by adding a class to body
    // Create a style element with a nonce if available, or use classList manipulation
    document.querySelectorAll('*').forEach(el => {
      // Store original transition/animation values and disable them
      const computedStyle = window.getComputedStyle(el);
      if (computedStyle.animationDuration !== '0s') {
        el.style.setProperty('animation-duration', '0s', 'important');
      }
      if (computedStyle.transitionDuration !== '0s') {
        el.style.setProperty('transition-duration', '0s', 'important');
      }
    });
  });
}

/**
 * Capture screenshot with consistent settings
 * @param {Page} page - Playwright page object
 * @param {string} name - Screenshot name
 * @param {Object} options - Additional options
 */
async function captureScreenshot(page, name, options = {}) {
  const defaultOptions = {
    fullPage: true,
    animations: 'disabled',
  };
  
  return page.screenshot({
    ...defaultOptions,
    ...options,
  });
}

/**
 * Compare visual appearance at multiple breakpoints
 * @param {Page} page - Playwright page object
 * @param {string} pageName - Name for the screenshot
 * @param {Array} breakpoints - Array of viewport names to test
 */
async function compareAtBreakpoints(page, pageName, breakpoints = ['desktop', 'mobile']) {
  const results = [];
  
  for (const breakpoint of breakpoints) {
    const viewport = VIEWPORTS[breakpoint];
    await page.setViewportSize(viewport);
    await page.waitForTimeout(300); // Allow layout to settle
    
    const screenshot = await captureScreenshot(page, `${pageName}-${breakpoint}`);
    results.push({
      breakpoint,
      viewport,
      screenshot,
    });
  }
  
  return results;
}

/**
 * Check for CSP violations in console
 * @param {Page} page - Playwright page object
 * @returns {Array} Array of CSP violation messages
 */
async function collectCspViolations(page) {
  const violations = [];
  
  page.on('console', (msg) => {
    const text = msg.text();
    if (text.includes('Content Security Policy') || 
        text.includes('CSP') ||
        text.includes('style-src')) {
      violations.push(text);
    }
  });
  
  return violations;
}

/**
 * Verify no inline styles exist on page
 * @param {Page} page - Playwright page object
 * @returns {Object} Result with count and elements
 */
async function checkInlineStyles(page) {
  return page.evaluate(() => {
    const elementsWithStyle = document.querySelectorAll('[style]');
    const results = [];
    
    elementsWithStyle.forEach((el) => {
      // Ignore elements with empty style attributes
      if (el.getAttribute('style').trim()) {
        results.push({
          tag: el.tagName.toLowerCase(),
          id: el.id || null,
          class: el.className || null,
          style: el.getAttribute('style'),
        });
      }
    });
    
    return {
      count: results.length,
      elements: results.slice(0, 10), // Limit to first 10
    };
  });
}

module.exports = {
  VIEWPORTS,
  waitForPageLoad,
  hideDynamicContent,
  captureScreenshot,
  compareAtBreakpoints,
  collectCspViolations,
  checkInlineStyles,
};
