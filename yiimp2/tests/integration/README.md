# Integration Tests

## Overview

Integration tests verify end-to-end workflows and ensure components work together correctly. These tests simulate real user interactions and validate complete feature functionality.

## Available Test Suites

### CSRF Validation Integration Tests
**File**: `CsrfValidationIntegrationTest.php`  
**Documentation**: [CSRF_INTEGRATION_TESTS.md](CSRF_INTEGRATION_TESTS.md)

Tests comprehensive CSRF validation workflows including:
- End-to-end coin creation
- Session expiration handling
- HTTP/HTTPS cookie behavior
- Diagnostic page access control
- Environment variable configuration
- Multiple tab handling
- Validation failure scenarios
- Token lifecycle management

### Coin Form Workflow Tests
**File**: `CoinFormWorkflowTest.php`

Tests complete coin form operations including:
- Creating new coins with all tab data
- Updating existing coins
- Validation error handling
- Tab navigation and state preservation

### Admin Workflow Tests
**File**: `AdminWorkflowTest.php`

Tests admin panel workflows including:
- Coin management
- User management
- Payment monitoring
- Worker monitoring
- AJAX endpoint functionality

### API Compatibility Tests
**File**: `ApiCompatibilityTest.php`

Tests API endpoint compatibility and functionality.

## Running Tests

### Run All Integration Tests
```bash
cd yiimp2
php vendor/bin/codecept run integration
```

### Run Specific Test Suite
```bash
cd yiimp2
php vendor/bin/codecept run integration CsrfValidationIntegrationTest
```

### Run Specific Test Method
```bash
cd yiimp2
php vendor/bin/codecept run integration CsrfValidationIntegrationTest:testEndToEndCoinCreationWorkflow
```

### Run with Verbose Output
```bash
cd yiimp2
php vendor/bin/codecept run integration --debug
```

### Run with Coverage
```bash
cd yiimp2
php vendor/bin/codecept run integration --coverage --coverage-html
```

## Test Environment Setup

### Prerequisites

1. **Database**: Test database must be configured and accessible
2. **PHP Extensions**: Required extensions must be installed
3. **Permissions**: Session storage must be writable
4. **Environment Variables**: Required variables must be set

### Configuration

Test configuration is located in `config/test.php`. Key settings:

```php
return [
    'id' => 'yiimp2-tests',
    'components' => [
        'db' => [
            'dsn' => 'mysql:host=localhost;dbname=yaamp_test',
            // ... other db config
        ],
        'session' => [
            'class' => 'yii\web\Session',
            // ... session config
        ],
    ],
];
```

### Environment Variables

Set these variables for testing:

```bash
export YIIMP_DEBUG=true
export YIIMP_COOKIE_VALIDATION_KEY=test_key_12345678901234567890123456789012
export YIIMP_SESSION_NAME=YIIMP2SESSID_TEST
export YIIMP_SESSION_TIMEOUT=3600
```

Or use `.env` file:

```env
YIIMP_DEBUG=true
YIIMP_COOKIE_VALIDATION_KEY=test_key_12345678901234567890123456789012
YIIMP_SESSION_NAME=YIIMP2SESSID_TEST
YIIMP_SESSION_TIMEOUT=3600
```

## Test Database Setup

### Create Test Database
```sql
CREATE DATABASE yaamp_test;
GRANT ALL PRIVILEGES ON yaamp_test.* TO 'yaamp'@'localhost';
```

### Import Schema
```bash
mysql yaamp_test < sql/yiimp2-init.sql
```

### Seed Test Data (Optional)
```bash
mysql yaamp_test < sql/seed-test-data.sql
```

## Continuous Integration

### GitHub Actions Example

```yaml
name: Integration Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    
    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: root
          MYSQL_DATABASE: yaamp_test
        ports:
          - 3306:3306
        options: --health-cmd="mysqladmin ping" --health-interval=10s --health-timeout=5s --health-retries=3
    
    steps:
      - uses: actions/checkout@v2
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.1'
          extensions: mbstring, intl, mysql, memcache
      
      - name: Install Dependencies
        run: |
          cd yiimp2
          composer install
      
      - name: Setup Database
        run: |
          mysql -h 127.0.0.1 -u root -proot yaamp_test < sql/yiimp2-init.sql
      
      - name: Run Integration Tests
        env:
          YIIMP_DEBUG: true
          YIIMP_COOKIE_VALIDATION_KEY: test_key_12345678901234567890123456789012
        run: |
          cd yiimp2
          php vendor/bin/codecept run integration
```

## Troubleshooting

### Common Issues

#### Database Connection Errors
**Error**: `SQLSTATE[HY000] [2002] Connection refused`

**Solutions**:
1. Verify database is running: `systemctl status mysql`
2. Check database credentials in `config/test.php`
3. Ensure test database exists: `mysql -e "SHOW DATABASES;"`
4. Check firewall settings

#### Session Errors
**Error**: `Failed to write session data`

**Solutions**:
1. Check session save path permissions: `ls -la /var/lib/php/sessions`
2. Ensure PHP session extension is loaded: `php -m | grep session`
3. Verify session configuration in `config/test.php`

#### CSRF Token Errors
**Error**: `CSRF token should be stored in session`

**Solutions**:
1. Verify session is properly initialized
2. Check cookie validation key is set
3. Ensure CSRF validation is enabled in config
4. Verify session storage is writable

#### Environment Variable Errors
**Error**: Environment variables not being read

**Solutions**:
1. Set variables in shell: `export YIIMP_DEBUG=true`
2. Use `.env` file in project root
3. Set variables in `config/test.php`
4. Verify variables with: `php -r "echo getenv('YIIMP_DEBUG');"`

### Debug Mode

Enable debug mode for detailed error information:

```bash
cd yiimp2
php vendor/bin/codecept run integration --debug --verbose
```

### Test Isolation

If tests interfere with each other:

1. Use transactions for database tests
2. Clean up test data in tearDown methods
3. Reset session between tests
4. Use unique identifiers for test data

## Best Practices

### Writing Integration Tests

1. **Test Real Workflows**: Simulate actual user interactions
2. **Use Realistic Data**: Test with data similar to production
3. **Verify End Results**: Check final state, not just intermediate steps
4. **Clean Up**: Always clean up test data
5. **Document Tests**: Add clear comments and documentation

### Test Organization

1. **Group Related Tests**: Keep related tests in same file
2. **Use Descriptive Names**: Test method names should describe what they test
3. **Follow Conventions**: Use consistent naming and structure
4. **Add Documentation**: Document complex test scenarios

### Performance

1. **Minimize Database Calls**: Use transactions when possible
2. **Reuse Test Data**: Create test data once, use multiple times
3. **Skip Slow Tests**: Mark slow tests for optional execution
4. **Use Test Doubles**: Mock external services

## Test Coverage

View test coverage reports:

```bash
cd yiimp2
php vendor/bin/codecept run integration --coverage --coverage-html
```

Coverage reports are generated in `tests/_output/coverage/`.

## Related Documentation

- [CSRF Integration Tests](CSRF_INTEGRATION_TESTS.md)
- [Admin Workflow Tests](ADMIN_WORKFLOW_TESTS.md)
- [Unit Tests](../unit/README.md)
- [Testing Guide](../../docs/TESTING.md)

## Contributing

When adding new features:

1. Write integration tests for new workflows
2. Update existing tests if behavior changes
3. Document new tests in this README
4. Ensure all tests pass before submitting PR

## Support

For issues with integration tests:

1. Check this README for troubleshooting steps
2. Review test-specific documentation
3. Check test output for error details
4. Consult Codeception documentation: https://codeception.com/docs/
