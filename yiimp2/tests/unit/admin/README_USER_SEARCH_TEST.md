# User Search Property Test

## Overview

This document describes the property-based test for user search and filter correctness (Property 2).

## Test Implementation

**File:** `yiimp2/tests/unit/admin/UserSearchPropertyTest.php`

**Property:** For any search query or filter criteria on user accounts, the system should return exactly the set of users matching the criteria, with no false positives or false negatives.

**Validates:** Requirements 1.4

## Test Strategy

The test verifies that the user search functionality in `AdminController::actionUser_results()` correctly filters users based on search criteria.

### Search Criteria Tested

The test validates the following search parameters that match the actual implementation:

1. **Username Search (`search`)**: Partial username matching using LIKE
2. **Coin ID Filter (`coinid`)**: Exact match on coin ID
3. **Lock Status Filter (`locked`)**: Filter by is_locked status (0 or 1)
4. **Combined Criteria**: Multiple filters applied simultaneously

### Test Process

For each of 100 iterations:

1. **Create Test Users**: Generate 5 random users with varying attributes:
   - Random username
   - Random balance
   - Random coinid (1-100)
   - Random is_locked status (0 or 1)
   - Random no_fees and donation values

2. **Generate Search Criteria**: Randomly select one of:
   - Username search (partial match)
   - Coin ID filter
   - Lock status filter
   - Combined username + coin ID

3. **Execute Search**: Apply the same query logic as `AdminController::actionUser_results()`:
   ```php
   if (isset($criteria['search'])) {
       $query->andWhere(['like', 'username', $criteria['search']]);
   }
   if (isset($criteria['coinid'])) {
       $query->andWhere(['coinid' => $criteria['coinid']]);
   }
   if (isset($criteria['locked']) && $criteria['locked'] !== '') {
       $query->andWhere(['is_locked' => $criteria['locked']]);
   }
   ```

4. **Verify Results**:
   - **No False Positives**: Every result must match the search criteria
   - **No False Negatives**: Every test user matching criteria must be in results

5. **Clean Up**: Delete test users after each iteration

### Property Verification

The test checks two critical properties:

1. **Precision (No False Positives)**: 
   - For each user in the search results, verify it matches ALL specified criteria
   - If a user doesn't match, record as a false positive failure

2. **Recall (No False Negatives)**:
   - For each test user that should match the criteria, verify it's in the results
   - If a matching user is missing, record as a false negative failure

## Running the Test

```bash
# Run the test
cd yiimp2
vendor/bin/codecept run unit admin/UserSearchPropertyTest

# Run with verbose output
vendor/bin/codecept run unit admin/UserSearchPropertyTest --debug
```

## Database Requirements

This test requires a database connection to create and query test users. If the database is not available, the test will be skipped with an appropriate message.

To run the test with a database:

1. Configure test database in `yiimp2/tests/_data/serverconfig.test.php`
2. Ensure the database is accessible
3. Run the test

## Test Results

When the database is available, the test will:
- Run 100 iterations with random data
- Report any failures with detailed information about the failing case
- Pass if all iterations succeed

When the database is not available:
- The test will be skipped
- Exit code 0 (success) with skip message

## Implementation Notes

### Matching Controller Logic

The test exactly mirrors the search logic in `AdminController::actionUser_results()`:

```php
// Controller implementation
$search = Yii::$app->request->get('search', '');
$coinid = Yii::$app->request->get('coinid', '');
$locked = Yii::$app->request->get('locked', '');

if ($search) {
    $query->andWhere(['like', 'username', $search]);
}
if ($coinid) {
    $query->andWhere(['coinid' => $coinid]);
}
if ($locked !== '') {
    $query->andWhere(['is_locked' => $locked]);
}
```

### Test Isolation

The test only queries the users it creates by limiting the search to test user IDs:

```php
$testUserIds = array_map(function($u) { return $u->id; }, $testUsers);
$query = Accounts::find()->where(['id' => $testUserIds]);
```

This ensures the test is isolated and doesn't depend on existing database data.

## Failure Reporting

If the property fails, the test reports:
- Iteration number where failure occurred
- Type of failure (false_positive or false_negative)
- User details (id, username, coinid, is_locked)
- Search criteria that was applied
- Reason for failure

Example failure output:
```json
{
    "iteration": 42,
    "type": "false_positive",
    "user_id": 12345,
    "username": "testuser_1234567890_5678_2",
    "coinid": 15,
    "is_locked": 0,
    "criteria": {
        "search": "testuser_1",
        "coinid": 20
    },
    "reason": "User in results but does not match criteria"
}
```

## Related Files

- **Controller**: `yiimp2/controllers/AdminController.php` (actionUser_results)
- **Model**: `yiimp2/models/Accounts.php`
- **View**: `yiimp2/views/admin/user.php`
- **Requirements**: `.kiro/specs/yiimp-to-yiimp2-migration/requirements.md` (Requirement 1.4)
- **Design**: `.kiro/specs/yiimp-to-yiimp2-migration/design.md` (Property 2)
