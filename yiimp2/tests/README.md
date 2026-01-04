# Yiimp2 Tests

This directory contains tests for the Yiimp2 application.

## Test Structure

- `unit/` - Unit tests for models, components, and utilities
- `unit/models/` - Property-based tests for models

## Running Tests

### Prerequisites

1. Install Codeception and dependencies:
```bash
cd yiimp2
composer install
```

2. Build Codeception test helpers:
```bash
vendor/bin/codecept build
```

3. Configure test database in `config/test.php` (optional)

### Run All Tests

```bash
vendor/bin/codecept run
```

### Run Unit Tests Only

```bash
vendor/bin/codecept run unit
```

### Run Specific Test

```bash
vendor/bin/codecept run unit models/NicehashPropertyTest
vendor/bin/codecept run unit models/NicehashShareAttributionTest
```

### Run with Verbose Output

```bash
vendor/bin/codecept run unit --debug
```

### Run with Coverage

```bash
vendor/bin/codecept run unit --coverage --coverage-html
```

## Property-Based Tests

Property-based tests verify universal properties that should hold across all inputs. Each test:

- Runs 100 iterations with randomly generated data
- Tests that properties hold for all valid inputs
- Reports any failures with the specific input that caused the failure

### NiceHash Property Tests

#### Property 24: NiceHash Order Tracking
Tests that NiceHash orders correctly store order ID, algorithm, and hashrate separately from regular miner data.

Location: `unit/models/NicehashPropertyTest.php`

#### Property 25: NiceHash Share Attribution
Tests that shares submitted by NiceHash miners are correctly attributed to the corresponding NiceHash order.

Location: `unit/models/NicehashShareAttributionTest.php`

## Test Database

For property-based tests that require database operations, you should configure a test database:

1. Create a test database: `yaamp_test`
2. Import the schema from the main database
3. Configure the connection in `config/test.php`

Alternatively, tests can use the main database but should clean up after themselves.

## Writing New Tests

When writing new property-based tests:

1. Extend `Codeception\Test\Unit`
2. Tag tests with feature and property number in comments
3. Run at least 100 iterations
4. Generate random test data
5. Verify properties hold for all iterations
6. Clean up test data after each iteration

Example:
```php
/**
 * Property X: Description
 * 
 * For any [input], the system should [expected behavior].
 * 
 * Validates: Requirements X.Y
 * 
 * @test
 */
public function testPropertyX()
{
    // Feature: yiimp-to-yiimp2-migration, Property X: Description
    
    $iterations = 100;
    $failures = [];
    
    for ($i = 0; $i < $iterations; $i++) {
        // Generate random data
        // Test property
        // Record failures
        // Clean up
    }
    
    // Report results
}
```
