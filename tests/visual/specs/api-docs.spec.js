/**
 * Visual regression tests for API documentation page
 * 
 * Feature: csp-inline-styles-removal, Property 2: Visual appearance preservation
 * Validates: Requirements 3.1, 3.2, 3.3
 * 
 * Tests:
 * - Verify code block styling
 * - Test request/response formatting
 */

const { test, expect } = require('@playwright/test');
const {
  waitForPageLoad,
  hideDynamicContent,
  VIEWPORTS,
  checkInlineStyles,
} = require('../utils/visual-test-helpers');

test.describe('API Documentation Visual Regression', () => {

  test('API documentation page renders correctly at desktop viewport', async ({ page }) => {
    await page.setViewportSize(VIEWPORTS.desktop);
    await page.goto('/site/api');
    await waitForPageLoad(page);
    await hideDynamicContent(page);
    
    await expect(page).toHaveScreenshot('api-docs-desktop.png', {
      fullPage: true,
      maxDiffPixelRatio: 0.001,
    });
  });

  test('API documentation page renders correctly at mobile viewport', async ({ page }) => {
    await page.setViewportSize(VIEWPORTS.mobile);
    await page.goto('/site/api');
    await waitForPageLoad(page);
    await hideDynamicContent(page);
    
    await expect(page).toHaveScreenshot('api-docs-mobile.png', {
      fullPage: true,
      maxDiffPixelRatio: 0.001,
    });
  });

  test('API documentation page renders correctly at tablet viewport', async ({ page }) => {
    await page.setViewportSize(VIEWPORTS.tablet);
    await page.goto('/site/api');
    await waitForPageLoad(page);
    await hideDynamicContent(page);
    
    await expect(page).toHaveScreenshot('api-docs-tablet.png', {
      fullPage: true,
      maxDiffPixelRatio: 0.001,
    });
  });

  test('code blocks use CSS class styling', async ({ page }) => {
    await page.goto('/site/api');
    await waitForPageLoad(page);
    
    // Find code blocks
    const codeBlocks = page.locator('.api-code-block, pre, code, .code-block');
    
    if (await codeBlocks.count() > 0) {
      const blockStyles = await codeBlocks.first().evaluate((el) => {
        const computed = window.getComputedStyle(el);
        return {
          padding: computed.padding,
          fontSize: computed.fontSize,
          backgroundColor: computed.backgroundColor,
          fontFamily: computed.fontFamily,
          hasInlineStyle: el.hasAttribute('style'),
          inlineStyle: el.getAttribute('style'),
          className: el.className,
        };
      });
      
      // Should use CSS class, not inline styles
      if (blockStyles.hasInlineStyle) {
        expect(blockStyles.inlineStyle).not.toContain('padding');
        expect(blockStyles.inlineStyle).not.toContain('font-size');
        expect(blockStyles.inlineStyle).not.toContain('background-color');
      }
      
      // Take screenshot of code block
      await expect(codeBlocks.first()).toHaveScreenshot('api-code-block.png', {
        maxDiffPixelRatio: 0.001,
      });
    }
  });

  test('all code blocks have consistent styling', async ({ page }) => {
    await page.goto('/site/api');
    await waitForPageLoad(page);
    
    const codeBlocks = page.locator('.api-code-block, pre code');
    const count = await codeBlocks.count();
    
    if (count > 1) {
      // Get styles from first code block
      const firstBlockStyles = await codeBlocks.first().evaluate((el) => {
        const computed = window.getComputedStyle(el);
        return {
          padding: computed.padding,
          fontSize: computed.fontSize,
          backgroundColor: computed.backgroundColor,
        };
      });
      
      // Compare with other code blocks
      for (let i = 1; i < Math.min(count, 5); i++) {
        const blockStyles = await codeBlocks.nth(i).evaluate((el) => {
          const computed = window.getComputedStyle(el);
          return {
            padding: computed.padding,
            fontSize: computed.fontSize,
            backgroundColor: computed.backgroundColor,
          };
        });
        
        // All code blocks should have same styling (Property 3: CSS class reusability)
        expect(blockStyles.fontSize).toBe(firstBlockStyles.fontSize);
        expect(blockStyles.backgroundColor).toBe(firstBlockStyles.backgroundColor);
      }
    }
  });

  test('request examples render correctly', async ({ page }) => {
    await page.goto('/site/api');
    await waitForPageLoad(page);
    
    // Find request example sections
    const requestExamples = page.locator('.api-request, .request-example, pre:has-text("GET"), pre:has-text("POST")');
    
    if (await requestExamples.count() > 0) {
      await expect(requestExamples.first()).toHaveScreenshot('api-request-example.png', {
        maxDiffPixelRatio: 0.001,
      });
    }
  });

  test('response examples render correctly', async ({ page }) => {
    await page.goto('/site/api');
    await waitForPageLoad(page);
    
    // Find response example sections
    const responseExamples = page.locator('.api-response, .response-example, pre:has-text("{")');
    
    if (await responseExamples.count() > 0) {
      await expect(responseExamples.first()).toHaveScreenshot('api-response-example.png', {
        maxDiffPixelRatio: 0.001,
      });
    }
  });

  test('API endpoint sections render correctly', async ({ page }) => {
    await page.goto('/site/api');
    await waitForPageLoad(page);
    
    // Find endpoint sections
    const endpointSections = page.locator('.api-endpoint, .endpoint-section, h3 + p + pre');
    
    if (await endpointSections.count() > 0) {
      await expect(endpointSections.first()).toHaveScreenshot('api-endpoint-section.png', {
        maxDiffPixelRatio: 0.001,
      });
    }
  });

  test('API documentation table of contents renders correctly', async ({ page }) => {
    await page.goto('/site/api');
    await waitForPageLoad(page);
    
    // Find table of contents or navigation
    const toc = page.locator('.api-toc, .table-of-contents, nav.api-nav');
    
    if (await toc.count() > 0) {
      await expect(toc.first()).toHaveScreenshot('api-toc.png', {
        maxDiffPixelRatio: 0.001,
      });
    }
  });

  test('no inline styles present on API documentation page', async ({ page }) => {
    await page.goto('/site/api');
    await waitForPageLoad(page);
    
    const inlineStyles = await checkInlineStyles(page);
    
    if (inlineStyles.count > 0) {
      console.log('Found inline styles:', JSON.stringify(inlineStyles.elements, null, 2));
    }
    
    expect(inlineStyles.count).toBe(0);
  });

  test('no CSP violations on API documentation page', async ({ page }) => {
    const violations = [];
    page.on('console', (msg) => {
      const text = msg.text();
      if (text.includes('Content Security Policy') || 
          text.includes('CSP') ||
          text.includes('style-src')) {
        violations.push(text);
      }
    });
    
    await page.goto('/site/api');
    await waitForPageLoad(page);
    
    expect(violations).toHaveLength(0);
  });

  test('code block syntax highlighting renders correctly', async ({ page }) => {
    await page.goto('/site/api');
    await waitForPageLoad(page);
    
    // Find syntax highlighted code
    const highlightedCode = page.locator('.hljs, .highlight, .syntax-highlight');
    
    if (await highlightedCode.count() > 0) {
      const highlightStyles = await highlightedCode.first().evaluate((el) => {
        return {
          hasInlineStyle: el.hasAttribute('style'),
          inlineStyle: el.getAttribute('style'),
        };
      });
      
      // Syntax highlighting should use CSS classes, not inline styles
      if (highlightStyles.hasInlineStyle) {
        expect(highlightStyles.inlineStyle).not.toContain('color');
      }
    }
  });

  test('API documentation links render correctly', async ({ page }) => {
    await page.goto('/site/api');
    await waitForPageLoad(page);
    
    // Find API links
    const apiLinks = page.locator('a[href*="api"], .api-link');
    
    if (await apiLinks.count() > 0) {
      const linkStyles = await apiLinks.first().evaluate((el) => {
        const computed = window.getComputedStyle(el);
        return {
          color: computed.color,
          textDecoration: computed.textDecoration,
          hasInlineStyle: el.hasAttribute('style'),
        };
      });
      
      // Links should not have inline styles
      expect(linkStyles.hasInlineStyle).toBe(false);
    }
  });

  test('responsive code blocks wrap correctly on mobile', async ({ page }) => {
    await page.setViewportSize(VIEWPORTS.mobile);
    await page.goto('/site/api');
    await waitForPageLoad(page);
    
    const codeBlocks = page.locator('.api-code-block, pre');
    
    if (await codeBlocks.count() > 0) {
      const blockStyles = await codeBlocks.first().evaluate((el) => {
        const computed = window.getComputedStyle(el);
        return {
          overflowX: computed.overflowX,
          whiteSpace: computed.whiteSpace,
          maxWidth: computed.maxWidth,
        };
      });
      
      // Code blocks should handle overflow on mobile
      expect(['auto', 'scroll', 'hidden']).toContain(blockStyles.overflowX);
      
      await expect(codeBlocks.first()).toHaveScreenshot('api-code-block-mobile.png', {
        maxDiffPixelRatio: 0.001,
      });
    }
  });
});
