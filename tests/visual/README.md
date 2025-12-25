# Visual Regression Tests

This directory contains Playwright-based visual regression tests for the yiimp2 application, specifically designed to validate the CSP inline styles removal feature.

## Overview

These tests ensure that after removing inline styles and converting them to CSS classes:
1. Visual appearance is preserved (Property 2)
2. No inline styles remain in rendered HTML (Property 1)
3. No CSP violations occur (Property 5)

## Requirements

- Node.js >= 16.0.0
- Playwright browsers installed

## Installation

```bash
# Install dependencies
npm install

# Install Playwright browsers
npx playwright install
```

## Running Tests

### Run all visual regression tests

```bash
npm run test:visual
```

### Update baseline screenshots

When intentionally changing styles, update the baseline screenshots:

```bash
npm run test:visual:update
```

### View test report

```bash
npm run test:visual:report
```

### Run specific test file

```bash
npx playwright test --config=tests/visual/playwright.config.js tests/visual/specs/homepage.spec.js
```

### Run tests in specific browser

```bash
npx playwright test --config=tests/visual/playwright.config.js --project=desktop-chrome
```

## Test Structure

```
tests/visual/
├── playwright.config.js    # Playwright configuration
├── README.md               # This file
├── utils/
│   └── visual-test-helpers.js  # Shared test utilities
├── specs/
│   ├── homepage.spec.js    # Homepage visual tests
│   ├── mining.spec.js      # Mining page visual tests
│   ├── wallet.spec.js      # Wallet page visual tests
│   ├── admin.spec.js       # Admin pages visual tests
│   └── api-docs.spec.js    # API documentation visual tests
├── snapshots/              # Baseline screenshots (generated)
├── test-results/           # Test artifacts (generated)
└── playwright-report/      # HTML report (generated)
```

## Test Categories

### 1. Homepage Tests (`homepage.spec.js`)
- Desktop, mobile, and tablet viewport screenshots
- Resume update button styling
- Pool statistics section
- Navigation and footer elements
- CSP compliance checks

### 2. Mining Page Tests (`mining.spec.js`)
- Chart container height styling
- Resume button styling
- Algorithm row background colors
- Responsive layout at multiple breakpoints
- Statistics table rendering

### 3. Wallet Page Tests (`wallet.spec.js`)
- With and without wallet data
- Selected row highlighting
- Chart legend styling
- Miners table rendering
- Text-small utility class

### 4. Admin Pages Tests (`admin.spec.js`)
- Coin list with algorithm colors
- Warning cell styling
- Form validation styling
- Dropdown menus
- Table row highlighting

### 5. API Documentation Tests (`api-docs.spec.js`)
- Code block styling consistency
- Request/response formatting
- Syntax highlighting
- Responsive code blocks on mobile

## Configuration

### Environment Variables

- `TEST_BASE_URL`: Base URL for tests (default: `http://localhost:8090`)
- `ADMIN_USER`: Admin username for admin page tests
- `ADMIN_PASS`: Admin password for admin page tests

### Tolerance Settings

Visual comparison uses:
- `maxDiffPixelRatio: 0.001` (0.1% pixel difference tolerance)
- `threshold: 0.2` (anti-aliasing tolerance)

## Validating Requirements

These tests validate the following requirements from the CSP inline styles removal spec:

| Requirement | Test Coverage |
|-------------|---------------|
| 3.1 - Visual appearance preservation | All screenshot comparisons |
| 3.2 - Layout, spacing, colors match | Viewport-specific tests |
| 3.3 - Responsive breakpoints | Mobile/tablet/desktop tests |
| 1.1 - No inline styles | `checkInlineStyles()` assertions |
| 1.3 - No CSP violations | Console message monitoring |

## Troubleshooting

### Tests fail with "Screenshot comparison failed"

1. Check if the visual change is intentional
2. If intentional, update baselines: `npm run test:visual:update`
3. If not intentional, investigate the CSS changes

### Tests fail with "Element not found"

1. Verify the application is running at the configured URL
2. Check if element selectors have changed
3. Increase timeout in `waitForPageLoad()`

### CSP violation tests fail

1. Check nginx CSP configuration
2. Verify no inline styles remain in view templates
3. Check browser console for specific violation messages

## CI/CD Integration

Add to your CI pipeline:

```yaml
- name: Run visual regression tests
  run: |
    npm ci
    npx playwright install --with-deps
    npm run test:visual
  env:
    TEST_BASE_URL: http://localhost:8090
```

## Feature Reference

- **Feature**: csp-inline-styles-removal
- **Property 2**: Visual appearance preservation
- **Validates**: Requirements 3.1, 3.2, 3.3
