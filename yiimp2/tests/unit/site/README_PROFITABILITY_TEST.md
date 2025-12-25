# Multi-Algo Profitability Property Test

## Overview

This test validates **Property 11: Multi-Algo Profitability Calculation** from the design document.

## What It Tests

The test verifies that for any set of supported algorithms with current difficulty and market price data, the system correctly calculates comparative profitability for each algorithm.

## Profitability Formula

The profitability calculation uses the following formula:

```
btcmhd = 24*60*60 * (reward * price / blocktime) / network_hashrate * 1000000 * speedfactor
```

Where:
- `reward`: Block reward for the coin
- `price`: Current market price in BTC
- `blocktime`: Time between blocks in seconds
- `network_hashrate`: Network hash rate calculated as `difficulty * 2^powlimit_bits / blocktime`
- `speedfactor`: Algorithm-specific normalization factor (e.g., 1000 for SHA256, 1 for Scrypt)
- Result is in mBTC/MH/day (or GH/day for fast algos like SHA256)

## Test Approach

The test uses property-based testing with 100 iterations:

1. **Generate Random Test Data**: For each iteration, creates 2-5 random algorithms with 1-3 coins each
2. **Calculate Profitability**: Applies the profitability formula to each coin
3. **Verify Accuracy**: Ensures calculated values match expected values within 0.1% tolerance
4. **Verify Completeness**: Ensures all algorithms have profitability data

## Test Data Generation

- **Algorithms**: Random selection from common algorithms (SHA256, Scrypt, X11, X13, Neoscrypt, Lyra2v2, Equihash)
- **Difficulty**: Random values from 1 to 1,000,000
- **Reward**: Random values from 0.1 to 100 coins
- **Price**: Random BTC prices from 0.00000001 to 0.01
- **Block Time**: Random values from 30 to 600 seconds

## Running the Test

```bash
cd yiimp2
vendor/bin/codecept run unit tests/unit/site/MultiAlgoProfitabilityPropertyTest.php
```

## Requirements Validated

- **Requirement 2.8**: Multi-algo statistics display with comparative profitability

## Design Property

- **Property 11**: Multi-algo profitability calculation

## Implementation Notes

- Test runs without requiring database connection (uses in-memory data structures)
- Validates the core profitability calculation logic
- Ensures consistency across different algorithms and coin parameters
- Tolerance of 0.1% accounts for floating-point arithmetic precision
