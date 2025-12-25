/**
 * Test Data Setup Utilities
 * 
 * Helper functions for setting up test data and authentication
 */

/**
 * Attempt to login with default admin credentials
 * 
 * @param {Page} page - Playwright page object
 * @returns {Promise<boolean>} - True if login successful, false otherwise
 */
async function loginAsAdmin(page) {
  try {
    // Try different login approaches
    const loginUrls = ['/admin/login', '/admin', '/login', '/site/login'];
    
    for (const loginUrl of loginUrls) {
      try {
        await page.goto(loginUrl);
        await page.waitForLoadState('networkidle');
        
        const pageContent = await page.locator('body').textContent();
        
        // Check if we're already logged in (no login form visible)
        if (!pageContent.includes('Login') && !pageContent.includes('login') && 
            !pageContent.includes('Sign in') && !pageContent.includes('Username') &&
            !pageContent.includes('Password')) {
          console.log('Already logged in or no login required');
          return true;
        }
        
        // Look for login form fields with various selectors
        const usernameSelectors = [
          'input[name="LoginForm[username]"]',
          'input[name="username"]',
          'input[name="email"]', 
          '#loginform-username',
          '#username',
          '#email',
          'input[type="text"]',
          'input[placeholder*="username" i]',
          'input[placeholder*="email" i]'
        ];
        
        const passwordSelectors = [
          'input[name="LoginForm[password]"]',
          'input[name="password"]',
          '#loginform-password',
          '#password',
          'input[type="password"]',
          'input[placeholder*="password" i]'
        ];
        
        let usernameField = null;
        let passwordField = null;
        
        // Find username field
        for (const selector of usernameSelectors) {
          try {
            const field = page.locator(selector).first();
            if (await field.isVisible({ timeout: 1000 })) {
              usernameField = field;
              console.log(`Found username field with selector: ${selector}`);
              break;
            }
          } catch (e) {
            // Continue to next selector
          }
        }
        
        // Find password field
        for (const selector of passwordSelectors) {
          try {
            const field = page.locator(selector).first();
            if (await field.isVisible({ timeout: 1000 })) {
              passwordField = field;
              console.log(`Found password field with selector: ${selector}`);
              break;
            }
          } catch (e) {
            // Continue to next selector
          }
        }
        
        if (!usernameField || !passwordField) {
          console.log(`No login form found on ${loginUrl}`);
          continue; // Try next URL
        }
        
        // Fill login form
        await usernameField.fill('admin');
        await passwordField.fill('changeme');
        
        // Find and click submit button
        const submitSelectors = [
          'button[type="submit"]',
          'input[type="submit"]',
          'button:has-text("Login")',
          'button:has-text("Sign in")',
          'button:has-text("Submit")',
          '.btn-primary',
          '.btn[type="submit"]',
          'form button'
        ];
        
        let submitButton = null;
        for (const selector of submitSelectors) {
          try {
            const button = page.locator(selector).first();
            if (await button.isVisible({ timeout: 1000 })) {
              submitButton = button;
              console.log(`Found submit button with selector: ${selector}`);
              break;
            }
          } catch (e) {
            // Continue to next selector
          }
        }
        
        if (!submitButton) {
          console.log(`No submit button found on ${loginUrl}`);
          continue; // Try next URL
        }
        
        console.log(`Attempting login on ${loginUrl}`);
        await submitButton.click();
        await page.waitForLoadState('networkidle');
        
        // Check if login was successful
        const postLoginContent = await page.locator('body').textContent();
        const loginSuccessful = !postLoginContent.includes('Login') && 
                               !postLoginContent.includes('login') && 
                               !postLoginContent.includes('Sign in') &&
                               !postLoginContent.includes('Invalid') &&
                               !postLoginContent.includes('Error');
        
        if (loginSuccessful) {
          console.log('Login successful');
          return true;
        } else {
          console.log('Login failed - still seeing login form or error');
          // Continue to try next URL
        }
        
      } catch (error) {
        console.log(`Error trying login URL ${loginUrl}:`, error.message);
        continue; // Try next URL
      }
    }
    
    console.log('All login attempts failed');
    return false;
    
  } catch (error) {
    console.log('Login attempt failed:', error.message);
    return false;
  }
}

/**
 * Create a test coin if none exist
 * 
 * @param {Page} page - Playwright page object
 * @returns {Promise<string|null>} - Created coin ID or null if failed
 */
async function createTestCoin(page) {
  try {
    // Ensure we're logged in first
    const loginSuccess = await loginAsAdmin(page);
    if (!loginSuccess) {
      console.log('Cannot create coin - login failed');
      return null;
    }
    
    // Go to coin creation page
    await page.goto('/admin/coin_create');
    await page.waitForLoadState('networkidle');
    
    const pageContent = await page.locator('body').textContent();
    if (!pageContent.includes('Create') && !pageContent.includes('coin')) {
      console.log('Cannot access coin creation page');
      return null;
    }
    
    // Fill out coin creation form
    await page.fill('#coin_name', 'TestCoin');
    await page.fill('#coin_symbol', 'TEST');
    
    // Try to select SHA256 algorithm
    try {
      await page.selectOption('#coin_algo', 'sha256');
    } catch (e) {
      // If SHA256 not available, try the first option
      const options = await page.locator('#coin_algo option').allTextContents();
      if (options.length > 1) {
        await page.selectOption('#coin_algo', { index: 1 }); // Skip first empty option
      }
    }
    
    // Enable and make visible
    await page.check('#coin_enable');
    await page.check('#coin_visible');
    
    // Submit form
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');
    
    // Try to get the created coin ID
    await page.goto('/admin/coin');
    await page.waitForLoadState('networkidle');
    
    const coinRows = await page.locator('table tbody tr').count();
    if (coinRows > 0) {
      const firstCoinLink = await page.locator('table tbody tr:first-child a').first().getAttribute('href');
      if (firstCoinLink) {
        const match = firstCoinLink.match(/\/admin\/coin\/(\d+)/);
        if (match) {
          console.log('Created test coin with ID:', match[1]);
          return match[1];
        }
      }
    }
    
    return null;
    
  } catch (error) {
    console.log('Failed to create test coin:', error.message);
    return null;
  }
}

/**
 * Check if algorithms are available (coins exist)
 * 
 * @param {Page} page - Playwright page object
 * @returns {Promise<boolean>} - True if algorithms are available
 */
async function checkAlgorithmsAvailable(page) {
  try {
    await page.goto('/stats');
    await page.waitForLoadState('networkidle');
    
    // Check if we need to login first
    const pageContent = await page.locator('body').textContent();
    if (pageContent.includes('Login') || pageContent.includes('login')) {
      const loginSuccess = await loginAsAdmin(page);
      if (!loginSuccess) {
        return false;
      }
      
      // Go back to stats after login
      await page.goto('/stats');
      await page.waitForLoadState('networkidle');
    }
    
    // Check if algorithm select has options
    const algoSelect = page.locator('#algo_select');
    const algoSelectExists = await algoSelect.count() > 0;
    
    if (!algoSelectExists) {
      return false;
    }
    
    const algoOptions = await algoSelect.locator('option').count();
    return algoOptions > 0;
    
  } catch (error) {
    console.log('Failed to check algorithms:', error.message);
    return false;
  }
}

/**
 * Get first available coin ID
 * 
 * @param {Page} page - Playwright page object
 * @returns {Promise<string|null>} - Coin ID or null if none found
 */
async function getFirstCoinId(page) {
  try {
    // Ensure we're logged in
    const loginSuccess = await loginAsAdmin(page);
    if (!loginSuccess) {
      return null;
    }
    
    await page.goto('/admin/coin');
    await page.waitForLoadState('networkidle');
    
    const coinRows = await page.locator('table tbody tr').count();
    if (coinRows > 0) {
      const firstCoinLink = await page.locator('table tbody tr:first-child a').first().getAttribute('href');
      if (firstCoinLink) {
        const match = firstCoinLink.match(/\/admin\/coin\/(\d+)/);
        if (match) {
          return match[1];
        }
      }
    }
    
    return null;
    
  } catch (error) {
    console.log('Failed to get coin ID:', error.message);
    return null;
  }
}

/**
 * Setup test environment with login and test data
 * 
 * @param {Page} page - Playwright page object
 * @returns {Promise<{hasAlgorithms: boolean, coinId: string|null}>}
 */
async function setupTestEnvironment(page) {
  try {
    // First check if algorithms are available
    let hasAlgorithms = await checkAlgorithmsAvailable(page);
    let coinId = null;
    
    if (!hasAlgorithms) {
      // Try to create a test coin
      coinId = await createTestCoin(page);
      if (coinId) {
        // Check again for algorithms
        hasAlgorithms = await checkAlgorithmsAvailable(page);
      }
    } else {
      // Get existing coin ID
      coinId = await getFirstCoinId(page);
    }
    
    return {
      hasAlgorithms,
      coinId
    };
    
  } catch (error) {
    console.log('Failed to setup test environment:', error.message);
    return {
      hasAlgorithms: false,
      coinId: null
    };
  }
}

module.exports = {
  loginAsAdmin,
  createTestCoin,
  checkAlgorithmsAvailable,
  getFirstCoinId,
  setupTestEnvironment
};