# Trading Controller Property Tests

## Overview

This directory contains property-based tests for the Trading controller functionality, specifically focusing on profitability calculations.

## Property 26: Profitability Calculation Accuracy

**File:** `ProfitabilityCalculationPropertyTest.php`

**Validates:** Requirements 6.2 - "WHEN a user views mining profitability THEN the system SHALL calculate and display estimated earnings per algorithm based on current difficulty and market prices"

### What It Tests

The property test verifies that the profitability calculation accurately estimates mining earnings using the formula:

```
btcmhd = 20116.56761169 / difficulty * reward * price * algo_unit_factor
```

Where:
- `btcmhd` = profitability in mBTC/MH/day
- `20116.56761169` = normalization constant derived from (24*60*60) / (2^32 / 2^16) * 1000000
- `difficulty` = current network difficulty
- `reward` = block reward
- `price` = coin price in BTC
- `algo_unit_factor` = algorithm-specific normalization (e.g., 1000 for SHA256, 1 for Scrypt)

### Test Coverage

The property test includes:

1. **Formula Accuracy** (100 iterations)
   - Generates random coin data (difficulty, reward, price, block time)
   - Verifies calculated profitability matches expected formula
   - Allows 0.01% tolerance for floating-point precision

2. **Mathematical Properties**
   - Profitability is always non-negative
   - Profitability is zero when difficulty is zero
   - Profitability scales linearly with price (2x price = 2x profit)
   - Profitability scales inversely with difficulty (2x difficulty = 0.5x profit)

3. **Edge Cases**
   - Zero difficulty → zero profitability
   - Very high difficulty → small positive profitability
   - Zero price → zero profitability
   - Zero reward → zero profitability

4. **Algorithm Unit Factors**
   - SHA256 profitability is 1000x Scrypt profitability (due to unit factor)
   - Other algorithms use factor of 1

### Running the Tests

```bash
# Run all trading tests
vendor/bin/codecept run unit trading

# Run only profitability tests
vendor/bin/codecept run unit trading/ProfitabilityCalculationPropertyTest

# Run with verbose output
vendor/bin/codecept run unit trading/ProfitabilityCalculationPropertyTest --verbose
```

### Implementation Notes

The test replicates the logic from `TradingController::calculateProfitability()` to ensure the implementation matches the specification. The normalization constant `20116.56761169` converts the raw calculation to mBTC/MH/day units, making profitability values comparable across different algorithms and coins.

### Related Tests

- **Property 11:** Multi-Algo Profitability Calculation (`tests/unit/site/MultiAlgoProfitabilityPropertyTest.php`)
  - Tests comparative profitability across multiple algorithms
  - Focuses on displaying profitability for all algorithms simultaneously
  - Property 26 focuses on the accuracy of individual coin profitability calculations
