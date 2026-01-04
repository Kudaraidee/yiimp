# Coin CRUD Property Test

## Overview

This test validates **Property 1: Coin CRUD Data Integrity** from the design document.

**Property Statement:**
> For any coin data (name, symbol, algorithm, RPC configuration), when performing create, update, or delete operations, the system should correctly persist all fields to the database and maintain referential integrity with related records.

**Validates:** Requirements 1.2

## Test Implementation

The test is implemented in `CoinCrudPropertyTest.php` and performs the following:

1. **Generates random coin data** - Creates realistic coin configurations with random:
   - Name and symbol
   - Algorithm (from supported list)
   - RPC configuration (host, port, user, password)
   - Master wallet address
   - Various flags and settings

2. **Tests CREATE operation** - Verifies that:
   - New coin records can be created
   - All fields are saved correctly
   - The coin can be retrieved after creation

3. **Tests READ operation** - Verifies that:
   - Saved coins can be retrieved by ID
   - All field values match the original data

4. **Tests UPDATE operation** - Verifies that:
   - Existing coins can be updated
   - Updated values persist correctly
   - No data corruption occurs

5. **Tests DELETE operation** - Verifies that:
   - Coins can be deleted
   - Deleted coins no longer exist in the database

## Running the Test

### Prerequisites

The test requires a MySQL/MariaDB database connection. You must:

1. **Create a test database:**
```bash
mysql -u root -p
CREATE DATABASE yaamp_test;
GRANT ALL PRIVILEGES ON yaamp_test.* TO 'test'@'localhost' IDENTIFIED BY 'test';
FLUSH PRIVILEGES;
```

2. **Import the schema:**
```bash
mysql -u test -p yaamp_test < sql/2024-03-06-complete_export.sql.gz
```

3. **Configure test database** in `tests/_data/serverconfig.test.php`:
```php
define('YAAMP_DBHOST', 'localhost');
define('YAAMP_DBNAME', 'yaamp_test');
define('YAAMP_DBUSER', 'test');
define('YAAMP_DBPASSWORD', 'test');
```

### Execute the Test

```bash
cd yiimp2
vendor/bin/codecept run unit admin/CoinCrudPropertyTest
```

### Expected Output

```
Codeception PHP Testing Framework
Tests.unit Tests (1)
✔ CoinCrudPropertyTest: Coin crud data integrity (X.XXs)

Time: XX.XXX, Memory: XX.XX MB
OK (1 test, X assertions)
```

## Test Iterations

The test runs **100 iterations** with randomly generated coin data to ensure the property holds across a wide range of inputs.

## Failure Reporting

If the property fails, the test will report:
- Iteration number where failure occurred
- Operation that failed (create, read, update, delete)
- Expected vs actual values
- Detailed error messages

## Database Cleanup

The test cleans up after itself by deleting created coins. However, if the test is interrupted, you may need to manually clean the test database:

```bash
mysql -u test -p yaamp_test
DELETE FROM coins WHERE name LIKE 'TestCoin%';
```

## Integration with CI/CD

For continuous integration, ensure:
1. Test database is available
2. Database credentials are configured
3. Schema is up to date
4. Test has appropriate permissions

## Notes

- This is a **property-based test** that verifies universal correctness properties
- It tests with random data to catch edge cases
- Database operations are real (not mocked) to ensure actual CRUD integrity
- The test validates backward compatibility with existing Yiimp database schema
