# Benchmark Data Persistence Property Test

## Overview

This test validates **Property 29: Benchmark Data Persistence** from the yiimp-to-yiimp2-migration specification.

## Property Statement

**For any** benchmark submission, all required fields (device model, chip type, algorithm, measured hashrate) should be stored in the database.

**Validates:** Requirements 7.4

## Requirement 7.4

> WHEN the system stores benchmarks THEN the system SHALL record device model, chip type, algorithm, and measured hashrate

## Test Strategy

The property test uses a generative approach to verify data persistence:

1. **Generate Random Benchmark Data**: Creates random but valid benchmark data including:
   - Algorithm (sha256, scrypt, x11, etc.)
   - Device model (GPU, CPU, ASIC names)
   - Chip type (NVIDIA RTX 3090, AMD RX 6900 XT, etc.)
   - Measured hashrate (100 kH/s to 10 GH/s)
   - Optional fields (power, frequency, driver, etc.)

2. **Create and Save**: Saves the benchmark to the database using the Benchmarks model

3. **Retrieve and Verify**: Retrieves the saved benchmark and verifies:
   - All required fields are persisted correctly
   - Algorithm matches original
   - Device model matches original
   - Chip type matches original
   - Measured hashrate matches original (within floating point tolerance)
   - Optional fields are persisted correctly
   - Benchmark can be queried by required fields
   - Relations to BenchChips work correctly

4. **Update and Verify**: Tests round-trip persistence by updating the hashrate and verifying the update persists

5. **Clean Up**: Deletes test data after verification

## Test Configuration

- **Iterations**: 100 random test cases
- **Database**: Requires working database connection (skips if unavailable)
- **Models**: Tests Benchmarks and BenchChips models
- **Tolerance**: 0.01 for floating point comparisons

## Required Fields (Requirement 7.4)

1. **Algorithm** (`algo`): Mining algorithm name
2. **Device Model** (`device`): Hardware device name
3. **Chip Type** (`chip`): Specific chip/processor name
4. **Measured Hashrate** (`khps`): Performance in kH/s

## Additional Fields Tested

- `type`: Device type (gpu, cpu, asic)
- `vendorid`: Hardware vendor ID
- `power`: Power consumption in watts
- `intensity`: Mining intensity setting
- `freq`: GPU core frequency
- `memf`: GPU memory frequency
- `client`: Mining software name and version
- `os`: Operating system
- `driver`: Driver version
- `idchip`: Foreign key to bench_chips table

## Test Execution

```bash
# Run the benchmark persistence property test
vendor/bin/codecept run unit tests/unit/bench/BenchmarkDataPersistencePropertyTest.php

# Run with verbose output
vendor/bin/codecept run unit tests/unit/bench/BenchmarkDataPersistencePropertyTest.php --verbose

# Run all benchmark tests
vendor/bin/codecept run unit tests/unit/bench/
```

## Expected Behavior

### Success Case
- All 100 iterations pass
- All required fields persist correctly
- All optional fields persist correctly
- Benchmarks can be queried by any field
- Relations work correctly
- Updates persist correctly

### Failure Cases

The test will fail if:
- Required fields are not persisted
- Field values change during save/retrieve cycle
- Benchmarks cannot be queried by required fields
- Relations to BenchChips don't work
- Updates don't persist

### Skip Case
- Test is skipped if database connection is not available
- This is expected in environments without database setup

## Implementation Notes

### Data Generators

The test includes generators for realistic test data:

- **Algorithms**: 24 common mining algorithms
- **Devices**: 13 realistic device names (GPUs, CPUs, ASICs)
- **Chips**: Generated from vendor + model + number combinations
- **Hashrates**: Random values between 100 kH/s and 10 GH/s
- **Clients**: 12 common mining software names with versions
- **Operating Systems**: linux, windows, macos, hiveos, nicehash
- **Drivers**: NVIDIA, AMD, and Mesa driver versions

### Floating Point Comparison

The test uses a tolerance of 0.01 for floating point comparisons (hashrate and intensity) to account for database precision limitations.

### Relation Testing

The test verifies that the `benchChip` relation works correctly by:
1. Creating a BenchChips record
2. Associating the benchmark with the chip via `idchip`
3. Retrieving the benchmark and accessing `benchChip` relation
4. Verifying the relation returns the correct chip

### Query Testing

The test verifies benchmarks can be queried by:
- Algorithm
- Device model
- Chip ID

This ensures indexes and queries work correctly for the benchmark display features.

## Related Tests

- `AlgorithmBenchmarkDisplayPropertyTest.php`: Tests Property 27 (algorithm benchmark display)
- `DeviceFilterPropertyTest.php`: Tests Property 28 (device filter accuracy)

## Related Code

- `app\models\Benchmarks`: Benchmark ActiveRecord model
- `app\models\BenchChips`: Chip data ActiveRecord model
- `app\controllers\BenchController`: Benchmark display and submission controller
- `app\controllers\BenchController::actionSubmit()`: Benchmark submission handler

## Maintenance

When modifying benchmark functionality:

1. Update this test if new required fields are added
2. Update generators if new algorithms or devices are supported
3. Ensure validation rules in Benchmarks model match test expectations
4. Run this test after schema changes to verify compatibility
