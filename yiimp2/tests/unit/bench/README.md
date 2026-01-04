# Benchmark Property Tests

This directory contains property-based tests for the benchmark functionality in Yiimp2.

## Tests

### AlgorithmBenchmarkDisplayPropertyTest

**Property 27: Algorithm Benchmark Display**

Tests that for any algorithm in the benchmark database, the system displays all benchmark entries for that algorithm.

**What it validates:**
- All benchmarks for a given algorithm are retrieved and displayed
- Each benchmark contains required fields (algo, type, khps, time)
- Algorithm filtering works correctly
- Chip-based aggregation produces valid results
- Average hashrate and record counts are calculated correctly

**Test approach:**
- Generates random algorithm names from a pool of common mining algorithms
- Creates random benchmark entries with associated chip data
- Verifies that all created benchmarks are retrieved when querying by algorithm
- Tests aggregated chip benchmarks (as shown in the algo view)
- Validates that required fields are present and correct

**Requirements validated:** 7.2

**Iterations:** 100

## Running the Tests

```bash
# Run all benchmark tests
vendor/bin/codecept run unit bench

# Run specific test
vendor/bin/codecept run unit bench/AlgorithmBenchmarkDisplayPropertyTest

# Run with verbose output
vendor/bin/codecept run unit bench/AlgorithmBenchmarkDisplayPropertyTest --verbose
```

## Database Requirements

These tests require a working database connection. If the database is not available, the tests will be skipped with an appropriate message.

The tests use the test database configuration from `config/test.php`.

## Test Data

The tests create temporary data:
- Random algorithm names (sha256, scrypt, x11, etc.)
- Random chip entries with device types (GPU, CPU, ASIC, FPGA)
- Random benchmark entries with hashrate, power, and other metrics

All test data is cleaned up after each iteration.
