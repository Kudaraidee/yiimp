const { test, expect } = require('@playwright/test');

test.describe('Simple Login Test', () => {
  test('should be able to login with admin credentials', async ({ page }) => {
    // Go to admin login page
    await page.goto('/admin');
    await page.waitForLoadState('networkidle');
    
    // Verify we're on login page
    await expect(page.locator('text=Admin Login')).toBeVisible();
    
    // Fill login form
    await page.fill('#loginform-username', 'admin');
    await page.fill('#loginform-password', 'changeme');
    
    // Submit form
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');
    
    // Check if we're redirected to admin dashboard
    const currentUrl = page.url();
    console.log('Current URL after login:', currentUrl);
    
    const pageContent = await page.locator('body').textContent();
    console.log('Page content includes "Login":', pageContent.includes('Login'));
    console.log('Page content includes "Admin":', pageContent.includes('Admin'));
    
    // Verify login success - should not be on login page anymore
    const isStillOnLoginPage = pageContent.includes('Please fill out the following fields to login');
    expect(isStillOnLoginPage).toBe(false);
  });
});