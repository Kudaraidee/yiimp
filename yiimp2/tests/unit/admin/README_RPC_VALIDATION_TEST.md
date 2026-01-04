# RPC Validation Property Test

## Overview

This test validates **Property 6: RPC Configuration Completeness** from the yiimp2-routing-database-alignment specification.

**Validates Requirements:** 3.1, 5.5

## What It Tests

The property-based test verifies that:

1. **Enabled coins have all required RPC fields**: For any enabled coin in the database, all required RPC fields (host, port, user, password) should be non-null
2. **RPC validation catches missing fields**: The RPC validation component should explicitly report which fields are missing when a coin configuration is incomplete

## Test Properties

### Property 6a: Enabled Coins Have Required RPC Fields

```
For any enabled coin in the database, all required RPC fields 
(rpchost, rpcport, rpcuser, rpcpasswd) should be non-null, 
and RPC validation should correctly identify whether all fields are present.
```

**Test Strategy:**
- Generate 100 random coin configurations with varying RPC field completeness
- For enabled coins, verify that RPC validation correctly identifies missing fields
- Ensure validation succeeds only when all required fields are present

### Property 6b: RPC Validation Catches Missing Fields

```
For any coin configuration with missing RPC fields, the RPC validation 
should explicitly report which fields are missing.
```

**Test Strategy:**
- Generate 50 coin configurations, each missing a specific RPC field
- Verify that RPC validation fails for coins with missing fields
- Ensure validation error messages are informative

## Required RPC Fields

The following fields are required for RPC connectivity:

- `rpchost` - Hostname or IP address of the coin daemon
- `rpcport` - Port number for RPC connection (1-65535)
- `rpcuser` - RPC username for authentication
- `rpcpasswd` - RPC password for authentication

## Running the Test

### Prerequisites

This test requires:
1. A working MySQL/MariaDB database connection
2. The `coins` table must exist in the database
3. The RpcClient component must be configured in the application

### Run the Test

```bash
cd yiimp2
php vendor/bin/codecept run unit tests/unit/admin/RpcValidationPropertyTest.php --verbose
```

### Expected Output

When database is available:
```
✓ RpcValidationPropertyTest: Enabled coins have required rpc fields (X.XXs)
✓ RpcValidationPropertyTest: Rpc validation catches missing fields (X.XXs)
```

When database is not available:
```
S RpcValidationPropertyTest: Enabled coins have required rpc fields (0.00s)
S RpcValidationPropertyTest: Rpc validation catches missing fields (0.00s)

There were 2 skipped tests:
- Database connection not available
```

## Test Implementation Details

### Random Data Generation

The test generates random coin configurations with:
- Random coin names and symbols
- Random algorithms (sha256, scrypt, x11, etc.)
- Random RPC hosts (localhost, 127.0.0.1, or random IPs)
- Random RPC ports (8000-9999)
- Random RPC credentials
- Random enable status (0 or 1)

### Validation Logic

The test uses the `RpcClient::validateConnection()` method to verify:
1. All required fields are present
2. Missing fields are properly reported
3. Validation succeeds only with complete configuration

### Cleanup

The test automatically cleans up all test coins after each iteration to prevent database pollution.

## Integration with Specification

This test directly implements the correctness property defined in the design document:

**Property 6: RPC Configuration Completeness**
> For any enabled coin in the database, all required RPC fields (host, port, user, password) should be non-null

**Validates Requirements:**
- 3.1: WHEN the admin panel queries coin daemon information THEN the system SHALL use the correct RPC credentials from the database
- 5.5: WHEN the admin panel updates coin settings THEN the system SHALL validate RPC connectivity before enabling the coin

## Troubleshooting

### Test Skipped - Database Not Available

If you see this message, ensure:
1. MySQL/MariaDB is running
2. Database credentials in `tests/_data/serverconfig.test.php` are correct
3. The `yaamp` database exists
4. The `coins` table exists

### Test Fails - RPC Validation Issues

If the test fails, check:
1. The `RpcClient` component is properly configured
2. The `validateConnection()` method correctly checks all required fields
3. Error messages properly identify missing fields

## Related Files

- **Test File**: `yiimp2/tests/unit/admin/RpcValidationPropertyTest.php`
- **Component**: `yiimp2/components/RpcClient.php`
- **Model**: `yiimp2/models/Coins.php`
- **Specification**: `.kiro/specs/yiimp2-routing-database-alignment/design.md`
- **Requirements**: `.kiro/specs/yiimp2-routing-database-alignment/requirements.md`
