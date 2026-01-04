# Decimal Precision Property Test

## Overview

This property-based test validates that financial amounts in the yiimp2 database maintain correct decimal precision (at least 8 decimal places) without rounding errors.

## Test Coverage

**Feature**: yiimp2-routing-database-alignment, Property 7: Decimal Precision Consistency  
**Validates**: Requirements 4.5, 6.4

### What It Tests

1. **Financial Amount Storage**: Verifies that random financial amounts with 8 decimal places can be stored and retrieved without precision loss
2. **Multiple Models**: Tests precision across all financial models:
   - `Accounts` (balance)
   - `Earnings` (amount, price)
   - `Payouts` (amount, fee)
   - `Blocks` (amount, difficulty, price, effort)
   - `Coins` (balance, prices, fees, rewards, etc.)

3. **Financial Calculations**: Verifies that adding multiple amounts maintains precision within acceptable floating-point bounds

4. **Database Schema**: Validates that all financial columns use appropriate numeric types (double/float)

5. **Edge Cases**:
   - Very small amounts (0.00000001 - 1 satoshi in BTC)
   - Very large amounts (millions with 8 decimal places)

## Test Strategy

The test uses property-based testing with 100+ iterations of random financial amounts to ensure:

- Values between 0.00000001 and 1000.00000000 are tested
- Both integer and fractional parts are randomized
- Round-trip storage (save → retrieve) maintains precision
- Precision is verified to within 1e-7 (accounting for floating-point representation)
- Formatted values match when rounded to 8 decimal places

## Why 8 Decimal Places?

Cryptocurrency amounts typically require 8 decimal places of precision:
- Bitcoin and most cryptocurrencies use 8 decimal places
- 1 satoshi = 0.00000001 BTC
- Mining pool payouts need this precision to avoid rounding errors

## Database Column Types

The yiimp2 database uses MySQL `double` type for financial columns:
- `double` provides approximately 15-17 significant decimal digits
- This is sufficient for 8 decimal place precision
- PHP represents these as `float` (double precision)

## Running the Test

```bash
cd yiimp2
php vendor/bin/codecept run unit database/DecimalPrecisionPropertyTest
```

### Prerequisites

- MySQL/MariaDB must be running
- Database must be initialized with `sql/yiimp2-init.sql`
- Database credentials configured in `yiimp2/config/test.php`

## Test Methods

### `testFinancialAmountsStoredWithCorrectPrecision()`
Main property test - runs 100 iterations testing all financial models with random amounts.

### `testFinancialCalculationsMaintainPrecision()`
Tests that adding multiple amounts doesn't introduce cumulative rounding errors (50 iterations).

### `testDatabaseColumnsSupport8DecimalPlaces()`
Schema validation - verifies all financial columns use numeric types.

### `testVerySmallAmountsPrecision()`
Edge case testing for amounts like 0.00000001 (1 satoshi).

### `testVeryLargeAmountsPrecision()`
Edge case testing for large amounts with 8 decimal places.

## Precision Tolerance

The test uses two precision checks:

1. **Absolute Difference**: Must be less than 1e-7
   - Accounts for floating-point representation limitations
   - Ensures precision to 7 decimal places minimum

2. **Formatted Comparison**: Values must match when formatted to 8 decimal places
   - Uses `number_format($value, 8, '.', '')`
   - Ensures user-visible precision is maintained

## Common Issues

### Database Connection Refused
**Error**: `SQLSTATE[HY000] [2002] Connection refused`  
**Solution**: Start MySQL/MariaDB service and verify database configuration

### Precision Failures
If precision tests fail, check:
- Database column types (should be `double` not `float`)
- PHP version (should support 64-bit floats)
- Database driver (should be PDO MySQL)

## Related Requirements

- **Requirement 4.5**: "WHEN the admin panel displays earnings THEN the system SHALL calculate totals with correct decimal precision"
- **Requirement 6.4**: "WHEN yiimp2 calculates earnings THEN the system SHALL have proper decimal column types for financial precision"

## Implementation Notes

- All financial models use `double` type in PHP
- Database uses MySQL `double` type (8 bytes, ~15-17 digits precision)
- Validation rules use `'number'` validator for financial fields
- Tests use transactions and rollback to avoid polluting test database
