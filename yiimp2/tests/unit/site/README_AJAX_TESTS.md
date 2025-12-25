# AJAX Dynamic Updates Property Tests

## Overview

This document describes the property-based tests for AJAX dynamic updates (Property 48), which validates Requirements 14.5.

## Test File

`tests/unit/site/AjaxDynamicUpdatesPropertyTest.php`

## Property Being Tested

**Property 48: AJAX Dynamic Updates**

*For any* dynamic content area (wallet statistics, mining results, pool status), updates should use AJAX requests without full page reloads.

**Validates: Requirements 14.5**

## Test Coverage

The test suite includes three main test methods:

### 1. testAjaxDynamicUpdates()

The main property test that runs 100 iterations testing various AJAX endpoints:

- `actionWallet_results` - Wallet statistics
- `actionWallet_miners_results` - Wallet miners list
- `actionWallet_found_results` - Wallet found blocks
- `actionMining_results` - Mining profitability
- `actionCurrent_results` - Current pool status
- `actionFound_results` - Recently found blocks
- `actionMiners_results` - Active miners
- `actionHistory_results` - Pool history
- `actionCoins_info` - Coin information
- `actionBlock_results` - Block details

For each endpoint, the test verifies:

1. **Partial Content**: Response does not include full HTML document structure (no `<!DOCTYPE>`, `<html>`, `<head>`, etc.)
2. **No Layout Elements**: Response does not include navigation, header, or footer elements
3. **Non-Empty Content**: Response contains data when expected
4. **Idempotency**: Multiple calls return the same structure

### 2. testAjaxEndpointsReturnPartialContent()

Verifies that AJAX endpoints return partial HTML fragments without:
- DOCTYPE declarations
- HTML document tags
- Navigation elements

### 3. testAjaxEndpointsAreIdempotent()

Verifies that calling the same AJAX endpoint multiple times returns consistent structure (content may vary slightly due to timestamps, but structure remains the same).

## Running the Tests

### Prerequisites

1. **Database Connection**: Tests require a working MySQL/MariaDB database connection
2. **Test Database**: Configure test database in `tests/_data/serverconfig.test.php`
3. **Test Data**: Database should have minimal test data (coins, accounts, workers, blocks)

### Running All AJAX Tests

```bash
cd yiimp2
vendor/bin/codecept run unit tests/unit/site/AjaxDynamicUpdatesPropertyTest.php
```

### Running Specific Test

```bash
# Run main property test
vendor/bin/codecept run unit tests/unit/site/AjaxDynamicUpdatesPropertyTest.php:testAjaxDynamicUpdates

# Run partial content test
vendor/bin/codecept run unit tests/unit/site/AjaxDynamicUpdatesPropertyTest.php:testAjaxEndpointsReturnPartialContent

# Run idempotency test
vendor/bin/codecept run unit tests/unit/site/AjaxDynamicUpdatesPropertyTest.php:testAjaxEndpointsAreIdempotent
```

### Verbose Output

```bash
vendor/bin/codecept run unit tests/unit/site/AjaxDynamicUpdatesPropertyTest.php --verbose
```

## Test Behavior Without Database

When database connection is not available, tests will be **skipped** with message:
```
Database connection not available
```

This is expected behavior and indicates the tests are properly checking for prerequisites.

## Expected Results

When database is available and properly configured:

- **100 iterations** of the main property test should pass
- All AJAX endpoints should return partial HTML content
- No full page reloads should occur
- Multiple calls should return consistent structure

## Failure Scenarios

The test will fail if:

1. **Full Page HTML**: AJAX endpoint returns complete HTML document with DOCTYPE, html, head, body tags
2. **Layout Elements**: Response includes navigation, header, or footer elements
3. **Empty Response**: Endpoint returns empty content when data is expected
4. **Structure Changes**: Multiple calls return different HTML structures (not idempotent)
5. **Exceptions**: Endpoint throws unhandled exceptions

## Test Data Setup

The test automatically creates minimal test data:

- Test accounts with wallet addresses
- Test workers associated with accounts
- Test blocks for block-related endpoints
- Test coins for coin-related endpoints

All test data is cleaned up after each iteration.

## Integration with CI/CD

To run these tests in CI/CD pipeline:

1. Set up test database
2. Import minimal schema
3. Configure database credentials in test config
4. Run tests as part of unit test suite

Example:
```bash
# Setup
mysql -u root -p < sql/2024-03-06-complete_export.sql.gz
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS yaamp_test"
mysql -u root -p -e "GRANT ALL ON yaamp_test.* TO 'test'@'localhost' IDENTIFIED BY 'test'"

# Run tests
cd yiimp2
vendor/bin/codecept run unit tests/unit/site/AjaxDynamicUpdatesPropertyTest.php
```

## Related Tests

- `tests/unit/site/WalletStatisticsPropertyTest.php` - Tests wallet statistics completeness
- `tests/unit/controllers/ApiStatusPropertyTest.php` - Tests API response formats
- `tests/unit/controllers/ApiWalletPropertyTest.php` - Tests API wallet endpoints

## Property Test Tag

All tests are tagged with:
```php
// Feature: yiimp-to-yiimp2-migration, Property 48: AJAX dynamic updates
```

This tag links the test to the design document property and requirements.
