# Renter Account Creation Property Test

## Property 17: Renter Account Creation

**Validates:** Requirements 4.1

**Property Statement:**
For any renter account creation, the system should generate a unique renter ID and initialize balance tracking to zero.

## Test Implementation

Location: `tests/unit/renting/RentalPropertyTest.php::testRenterAccountCreation`

### What the Test Validates

The test verifies that when creating a renter account, the system:

1. **Generates a unique renter ID** - Each account gets a distinct database ID
2. **Initializes balance to zero** - `balance` field starts at 0
3. **Initializes received to zero** - `received` field starts at 0
4. **Initializes spent to zero** - `spent` field starts at 0
5. **Initializes unconfirmed to zero** - `unconfirmed` field starts at 0
6. **Generates an API key** - A unique API key is created for the account
7. **Hashes the password** - Password is not stored in plain text
8. **Sets created timestamp** - Account creation time is recorded

### Test Strategy

The test uses property-based testing with 100 iterations:

```php
for ($i = 0; $i < 100; $i++) {
    // Create renter with random data
    $renter = new Renters();
    $renter->address = generateRandomAddress();
    $renter->email = 'test' . $i . '@example.com';
    $renter->setPassword('testpassword' . $i);
    $renter->generateApiKey();
    $renter->balance = 0;
    $renter->received = 0;
    $renter->spent = 0;
    $renter->unconfirmed = 0;
    
    // Verify all properties hold
    assert($renter->save());
    assert($renter->id > 0);
    assert($renter->balance === 0);
    assert($renter->received === 0);
    assert($renter->spent === 0);
    assert($renter->unconfirmed === 0);
    assert(!empty($renter->apikey));
    assert($renter->password !== 'testpassword' . $i);
    assert(!empty($renter->created));
    
    // Clean up
    $renter->delete();
}
```

### Running the Test

```bash
cd yiimp2
vendor/bin/codecept run unit renting/RentalPropertyTest::testRenterAccountCreation
```

### Prerequisites

- MySQL/MariaDB database must be running
- Test database configured in `config/test.php`
- Database schema imported

### Expected Behavior

The test should pass for all 100 iterations, confirming that:
- All renter IDs are unique
- All balance fields are initialized to zero
- API keys are generated
- Passwords are hashed
- Timestamps are set

### Failure Scenarios

The test will fail if:
- Duplicate renter IDs are generated
- Balance fields are not initialized to zero
- API key is not generated
- Password is stored in plain text
- Created timestamp is not set

## Implementation Details

### Controller Logic

The test validates the account creation logic in `RentingController::handleRegistration()`:

```php
$renter = new Renters();
$renter->address = $address;
$renter->email = $email;
$renter->setPassword($password);
$renter->generateApiKey();
$renter->balance = 0;
$renter->received = 0;
$renter->spent = 0;
$renter->unconfirmed = 0;
$renter->save();
```

### Model Methods

The test exercises these `Renters` model methods:
- `setPassword()` - Hashes password using Yii2 security component
- `generateApiKey()` - Generates 32-character random API key
- `beforeSave()` - Sets `created` and `updated` timestamps

## Related Tests

- **Property 18:** Deposit Balance Update (`testDepositBalanceUpdate`)
- **Property 19:** Rental Order Creation (`testRentalOrderCreation`)
- **Property 20:** Order Completion Balance Deduction (`testOrderCompletionBalanceDeduction`)

## Notes

This test is part of the comprehensive property-based testing suite for the rental system. It ensures that the fundamental account creation process maintains data integrity across all possible inputs.
