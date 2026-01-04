# Backend Integration Property Tests

This directory contains property-based tests for backend script integration with Yiimp2.

## Overview

These tests verify that backend scripts can successfully use Yiimp2 models and components for:

- Configuration access (Property 40)
- Payout processing (Property 41)
- Exchange operations (Property 42)
- Coin management (Property 43)

## Running Tests

### Prerequisites

These tests require a full Yiimp deployment environment with:

1. `/etc/yiimp/serverconfig.php` configured
2. MySQL/MariaDB database with Yiimp schema
3. Database populated with test data
4. Memcache running (if configured)

### In Development Environment

In a development environment without the full deployment, you can use the console test commands instead:

```bash
# Test all backend integration
./yii test/all

# Test specific integrations
./yii test/models
./yii test/components
./yii payout-test/check
./yii exchange-test/check
./yii coin-test/check
```

### In Production/Test Environment

Once deployed, run the property tests:

```bash
vendor/bin/codecept run unit tests/unit/backend/
```

## Test Structure

Each property test runs 100 iterations to verify consistency across multiple executions. This follows the property-based testing approach where properties should hold true for all valid inputs.

### BackendConfigurationPropertyTest

Tests Property 40: Backend configuration access

- Verifies database configuration is accessible
- Verifies models can be instantiated and queried
- Verifies components are accessible
- Verifies cache operations work
- Verifies logging is accessible

### PayoutScriptPropertyTest

Tests Property 41: Payout script model integration

- Verifies payout models can be accessed
- Verifies account balance queries work
- Verifies transaction support works
- Verifies payout relations are correct

### ExchangeScriptPropertyTest

Tests Property 42: Exchange script component integration

- Verifies exchange models can be accessed
- Verifies RPC components are accessible
- Verifies market data queries work
- Verifies balance tracking works

### CoinManagementPropertyTest

Tests Property 43: Coin management script integration

- Verifies coin models can be accessed
- Verifies blockchain data queries work
- Verifies coin status updates work
- Verifies algorithm configuration is accessible

## Known Limitations

These tests cannot run in a development environment without:

- The serverconfig.php file at `/etc/yiimp/serverconfig.php`
- A configured database connection
- Test data in the database

This is expected and by design - these are integration tests that verify the full system works together.

## Alternative Testing

For development without full deployment, use the console test commands which provide similar verification but with more detailed output and error handling.
