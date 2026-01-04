# Database Schema Compatibility Property Test

## Overview

This test implements **Property 38: Database Schema Compatibility** from the Yiimp2 migration design document.

## Property Statement

*For any* Yiimp2 model operation (read, write, update, delete), the operation should work correctly with the existing database schema without requiring schema changes.

**Validates:** Requirements 11.1

## Test Implementation

The test is located in `DatabaseSchemaCompatibilityTest.php` and verifies that all core Yiimp2 ActiveRecord models can perform complete CRUD operations on the existing database schema.

### Models Tested

The test covers the following core models:
- **Coins** - Cryptocurrency configuration
- **Accounts** - User wallet accounts
- **Workers** - Mining workers
- **Blocks** - Found blocks
- **Payouts** - Payment transactions
- **Markets** - Exchange market data
- **Algos** - Mining algorithms
- **Stratums** - Stratum server configurations

### Test Methodology

For each model, the test performs 100 iterations (12-13 per model) of the following operations:

1. **CREATE** - Generate random valid data and save to database
2. **READ** - Retrieve the saved record and verify data integrity
3. **UPDATE** - Modify a field and save changes
4. **DELETE** - Remove the record from database

### Data Integrity Verification

For each operation, the test verifies:
- All required fields are preserved correctly
- Numeric values maintain precision (within acceptable floating-point tolerance)
- String values match exactly
- Foreign key relationships are maintained
- No schema modifications are required

### Random Data Generation

The test uses random data generators for each model type to ensure comprehensive coverage:
- Random strings with unique suffixes to avoid conflicts
- Random numeric values within valid ranges
- Random selections from valid enum values
- Proper foreign key references for related models

### Database Dependency Handling

The test gracefully handles missing database connections:
- Checks database availability before running
- Skips test with informative message if database is unavailable
- Provides clear error messages for connection issues

## Running the Test

### Prerequisites

1. MySQL/MariaDB database server running
2. Test database configured in `tests/_data/serverconfig.test.php`
3. Database schema imported (use main yaamp database schema)

### Execute Test

```bash
cd yiimp2
vendor/bin/codecept run unit models/DatabaseSchemaCompatibilityTest
```

### With Verbose Output

```bash
vendor/bin/codecept run unit models/DatabaseSchemaCompatibilityTest -v
```

### With Debug Information

```bash
vendor/bin/codecept run unit models/DatabaseSchemaCompatibilityTest --debug
```

## Expected Results

### With Database Available

```
Tests: 1, Assertions: 1, Passed: 1
```

The test should complete all 100 iterations without failures, confirming that:
- All models work with existing schema
- No schema changes are required
- Data integrity is maintained across CRUD operations

### Without Database Available

```
Tests: 1, Assertions: 0, Skipped: 1
Reason: Database connection not available
```

The test will skip gracefully with an informative message.

## Failure Analysis

If the test fails, it will report:
- Which model failed
- Which iteration failed
- What operation failed (create, read, update, delete)
- Specific error message
- Expected vs actual values for data integrity failures

Example failure output:
```json
{
  "model": "Coins",
  "iteration": 42,
  "error": "Coins name mismatch",
  "expected": "TestCoin_abc123",
  "actual": "TestCoin_abc124"
}
```

## Integration with Migration

This test ensures that:
1. Yiimp2 models are fully compatible with the legacy Yiimp database schema
2. No database migrations are required for the Yiimp to Yiimp2 transition
3. Backend scripts and stratum server can continue using the same database
4. Data written by Yiimp2 is compatible with legacy Yiimp components

## Maintenance

When adding new models to Yiimp2:
1. Add the model to the `$modelTests` array
2. Implement a test method following the pattern (e.g., `testNewModelModel()`)
3. Implement a data generator (e.g., `generateRandomNewModelData()`)
4. Ensure proper cleanup of test data

## Related Tests

- `NicehashPropertyTest.php` - Tests NiceHash-specific model operations
- `BookmarkPropertyTest.php` - Tests bookmark storage operations
- Integration tests in `tests/integration/` - Test end-to-end workflows

## References

- Design Document: `.kiro/specs/yiimp-to-yiimp2-migration/design.md`
- Requirements: `.kiro/specs/yiimp-to-yiimp2-migration/requirements.md`
- Task List: `.kiro/specs/yiimp-to-yiimp2-migration/tasks.md`
