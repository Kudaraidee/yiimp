# Cache Clear Property Test

## Overview

This test validates **Property 4: Cache Clear Operation** from the Yiimp2 migration design document.

## Property Statement

*For any* memcached clear operation, all cached data should be removed and subsequent requests should fetch fresh data from the database.

**Validates: Requirements 1.11**

## Test Implementation

### File
`tests/unit/admin/CacheClearPropertyTest.php`

### Test Method
`testCacheClearOperation()`

### Test Strategy

The test runs 100 iterations, each performing the following steps:

1. **Generate Random Cache Data**: Creates 5-20 random cache entries with various data types (strings, integers, floats, arrays, objects)

2. **Store Data in Cache**: Sets all generated entries in the cache with a 1-hour TTL

3. **Verify Data Before Clear**: Confirms all entries are retrievable from cache

4. **Execute Cache Flush**: Calls `Yii::$app->cache->flush()` to clear all cached data

5. **Verify Data After Clear**: Confirms all previously cached entries are no longer retrievable (return `false`)

6. **Test Cache Functionality After Clear**: 
   - Sets a new cache entry
   - Verifies it can be retrieved
   - Confirms the value matches what was set

### Data Types Tested

The test generates random values of the following types:
- **String**: Random strings with unique identifiers
- **Integer**: Random integers from 1 to 1,000,000
- **Float**: Random floats with 2 decimal places
- **Array**: Associative arrays with id, name, value, and timestamp
- **Object**: stdClass objects with id, name, and value properties

### Success Criteria

The property holds if:
- All cache entries are successfully stored
- All entries are retrievable before flush
- Cache flush operation returns `true`
- All entries are NOT retrievable after flush (return `false`)
- New entries can be stored and retrieved after flush
- Retrieved values match the stored values (using deep equality comparison)

### Failure Reporting

If any iteration fails, the test reports:
- Iteration number
- Step where failure occurred
- Cache key involved
- Expected vs actual values (if applicable)
- Reason for failure

## Running the Test

```bash
# Run this specific test
vendor/bin/codecept run unit admin/CacheClearPropertyTest

# Run with verbose output
vendor/bin/codecept run unit admin/CacheClearPropertyTest --debug

# Run all admin property tests
vendor/bin/codecept run unit admin
```

## Cache Configuration

The test uses the cache component configured in `config/test.php`:
- **Test Environment**: Uses `FileCache` for testing
- **Production Environment**: Uses `MemCache` (Memcached)

The test automatically skips if the cache component is not configured.

## Implementation Notes

### Value Comparison

The test uses a custom `valuesAreEqual()` method to properly compare cached values:
- Handles arrays recursively
- Handles objects by converting to arrays
- Uses strict equality (`===`) for scalar values

This is necessary because PHP's `!==` operator doesn't work well for comparing complex data structures retrieved from cache.

### Cache Backend Compatibility

The test is designed to work with any Yii2 cache backend:
- FileCache (used in tests)
- MemCache (used in production)
- RedisCache
- ApcCache
- DbCache

All backends must support the `flush()` operation for the test to pass.

## Related Requirements

This test validates:
- **Requirement 1.11**: "WHEN the administrator manages memcached THEN the system SHALL provide cache inspection and clearing functionality"

## Related Code

- **Controller**: `app\controllers\AdminController::actionClear_cache()`
- **View**: `views/admin/memcached.php`
- **Cache Component**: Configured in `config/web.php` and `config/test.php`

## Test Results

✅ **Status**: PASSING  
✅ **Iterations**: 100  
✅ **Property**: Holds for all iterations
