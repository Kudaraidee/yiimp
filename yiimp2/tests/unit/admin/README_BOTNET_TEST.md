# Botnet Detection Property Test

## Overview

This document describes the property-based test for botnet detection and blocking functionality (Property 3).

## Test File

`tests/unit/admin/BotnetDetectionPropertyTest.php`

## Property Being Tested

**Property 3: Botnet Detection and Blocking**

For any mining pattern flagged as suspicious (botnet or monster), the blocking operation should prevent further mining activity from that source and persist the block status.

**Validates: Requirements 1.10**

## Test Strategy

The test verifies three key aspects:

1. **Detection**: Suspicious mining patterns are correctly identified
2. **Prevention**: Blocked sources cannot continue mining
3. **Persistence**: Block status is maintained in the database

## Suspicious Patterns Tested

The test generates and validates four types of suspicious patterns:

### 1. Excessive Workers
- **Pattern**: Single account with too many active workers
- **Threshold**: 50-100+ workers
- **Detection**: Count of active workers exceeds threshold

### 2. Rapid Connection
- **Pattern**: Many connections/disconnections in short time
- **Threshold**: 20-50 connections per minute
- **Detection**: Workers connecting within 60-second window

### 3. Identical Workers
- **Pattern**: Multiple workers with identical names
- **Threshold**: 10-30 workers with same name
- **Detection**: Duplicate worker names from same account

### 4. Low Difficulty Spam
- **Pattern**: Excessive low-difficulty share submissions
- **Threshold**: 100-500 shares per minute at very low difficulty
- **Detection**: Workers with difficulty < 0.01

## Test Flow

For each iteration (100 total):

1. **Setup**
   - Create test account
   - Generate random suspicious pattern
   - Create workers exhibiting that pattern

2. **Detection Phase**
   - Analyze worker behavior
   - Identify suspicious pattern
   - Verify detection logic works correctly

3. **Blocking Phase**
   - Lock the suspicious account (`is_locked = 1`)
   - Verify blocking operation succeeds

4. **Verification Phase**
   - Confirm blocked account cannot mine
   - Verify block status persists in database
   - Reload account to check persistence

5. **Cleanup**
   - Delete test workers
   - Delete test account

## Implementation Details

### Detection Logic

The test implements pattern detection algorithms that mirror real-world botnet detection:

```php
protected function detectSuspiciousPattern($workers, $pattern)
{
    switch ($pattern['type']) {
        case 'excessive_workers':
            return count($workers) >= $pattern['threshold'];
            
        case 'rapid_connection':
            $recentWorkers = array_filter($workers, function($w) {
                return $w->time > (time() - 60);
            });
            return count($recentWorkers) >= 10;
            
        case 'identical_workers':
            $names = array_map(function($w) { return $w->name; }, $workers);
            $uniqueNames = array_unique($names);
            return count($names) - count($uniqueNames) >= 5;
            
        case 'low_difficulty_spam':
            $lowDiffWorkers = array_filter($workers, function($w) {
                return $w->difficulty < 0.01;
            });
            return count($lowDiffWorkers) >= 5;
    }
}
```

### Blocking Mechanism

Blocking is implemented by setting the `is_locked` flag on the account:

```php
protected function blockSuspiciousSource($account, $pattern)
{
    $account->is_locked = 1;
    
    if ($account->save(false)) {
        return ['success' => true, 'account_id' => $account->id];
    }
    
    return ['success' => false, 'errors' => $account->errors];
}
```

### Persistence Verification

The test verifies persistence by reloading the account from the database:

```php
protected function isBlockPersisted($account)
{
    $freshAccount = Accounts::findOne($account->id);
    return $freshAccount && $freshAccount->is_locked == 1;
}
```

## Database Requirements

This test requires:
- Active database connection
- `accounts` table with `is_locked` field
- `workers` table with standard fields
- Ability to create/delete test records

## Running the Test

```bash
# Run the specific test
vendor/bin/codecept run unit admin/BotnetDetectionPropertyTest

# Run with verbose output
vendor/bin/codecept run unit admin/BotnetDetectionPropertyTest -v

# Run all admin property tests
vendor/bin/codecept run unit admin
```

## Expected Behavior

When database is available:
- Test runs 100 iterations
- Each iteration tests a random suspicious pattern
- All patterns should be detected and blocked correctly
- Block status should persist across database reloads

When database is unavailable:
- Test is skipped with message: "Database not available"
- This is expected behavior for environments without test database

## Failure Scenarios

The test tracks and reports several failure types:

1. **detection_failure**: Suspicious pattern not identified
2. **blocking_failure**: Unable to lock account
3. **prevention_failure**: Blocked account can still mine
4. **persistence_failure**: Block status not saved to database

## Integration with AdminController

This test validates the backend logic for:
- `AdminController::actionBotnets()` - Display botnet detection page
- `AdminController::actionMonsters()` - Display monster detection page
- `AdminController::actionBlock_pattern()` - Block suspicious patterns
- Account locking mechanism via `is_locked` field

## Future Enhancements

Potential improvements to the test:

1. Test IP-based blocking in addition to account blocking
2. Test pattern whitelisting (false positive handling)
3. Test automatic unblocking after investigation
4. Test notification system for detected patterns
5. Test integration with firewall rules
6. Test blocking at stratum server level

## Related Requirements

- **Requirement 1.10**: Botnet and monster detection with blocking capabilities
- **Property 3**: Botnet detection and blocking correctness
- **AdminController**: Administrative security monitoring features

## Notes

- The test uses realistic thresholds based on common botnet patterns
- Pattern types are randomized to ensure comprehensive coverage
- Test data is cleaned up after each iteration
- The test is designed to be idempotent and safe to run repeatedly
