# Worker and Earnings Display Property Tests

This directory contains property-based tests for worker and earnings display functionality.

## Test File

- `WorkerEarningsDisplayPropertyTest.php` - Property tests for Properties 6, 7, 8, and 9

## Properties Tested

### Property 6: Worker Detail Display
**Validates: Requirements 2.3**

For any worker record, the worker details should display all required fields:
- Hashrate (numeric)
- Shares submitted (numeric)
- Last activity timestamp (valid timestamp)

The test creates random workers and verifies that all required fields are present and valid.

### Property 7: Earnings Display Completeness
**Validates: Requirements 2.4**

For any miner account, the earnings display should show all required fields:
- Unpaid balance (numeric)
- Total paid (numeric)
- Breakdown by coin (array)

The test creates random accounts with earnings and verifies the display includes all required information.

### Property 8: Payout History Completeness
**Validates: Requirements 2.5**

For any miner with payout records, all payouts should be displayed with:
- Transaction IDs (non-empty string)
- Amounts (numeric, positive)
- Timestamps (valid timestamp)

The test creates random payouts and verifies all are displayed with complete information.

### Property 9: Block History Display
**Validates: Requirements 2.6**

For any miner with found blocks, all blocks should be displayed with:
- Confirmation status (numeric)
- Rewards (numeric, positive)
- Category (valid category: generate, immature, orphan, new)

The test creates random blocks and verifies all are displayed with complete information.

## Running the Tests

```bash
# Run all worker/earnings property tests
vendor/bin/codecept run unit tests/unit/site/WorkerEarningsDisplayPropertyTest.php

# Run specific property test
vendor/bin/codecept run unit tests/unit/site/WorkerEarningsDisplayPropertyTest.php:testWorkerDetailDisplay
vendor/bin/codecept run unit tests/unit/site/WorkerEarningsDisplayPropertyTest.php:testEarningsDisplayCompleteness
vendor/bin/codecept run unit tests/unit/site/WorkerEarningsDisplayPropertyTest.php:testPayoutHistoryCompleteness
vendor/bin/codecept run unit tests/unit/site/WorkerEarningsDisplayPropertyTest.php:testBlockHistoryDisplay

# Run with verbose output
vendor/bin/codecept run unit tests/unit/site/WorkerEarningsDisplayPropertyTest.php --verbose
```

## Test Configuration

These tests require:
- Working database connection (tests will skip if unavailable)
- Yii2 application properly configured
- Database tables: accounts, workers, earnings, payouts, blocks, coins

## Test Iterations

Each property test runs **100 iterations** with randomly generated data to ensure the properties hold across a wide range of inputs.

## Test Data Generation

The tests generate random:
- Wallet addresses (34 character base58 strings)
- Worker names and configurations
- Earnings amounts and timestamps
- Payout transactions and amounts
- Block hashes, heights, and confirmations
- Coin configurations

## Expected Behavior

When database is available:
- Tests should pass with 100 iterations each
- All required fields should be present in display data
- All numeric fields should be valid numbers
- All timestamps should be valid Unix timestamps
- All arrays should contain expected number of elements

When database is unavailable:
- Tests will be skipped with appropriate message
- No failures will be reported

## Troubleshooting

If tests fail:
1. Check database connection configuration in `config/test.php`
2. Verify database tables exist and have correct schema
3. Check test output for specific failure details
4. Review the failing iteration data in JSON output

## Integration with Yiimp2

These tests validate that the Yiimp2 frontend correctly displays:
- Worker statistics on wallet dashboard
- Earnings breakdown by coin
- Payout history with transaction links
- Block history with confirmation status

The tests ensure backward compatibility with the existing Yiimp database schema while providing modern Yii2 functionality.
