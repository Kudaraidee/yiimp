# Device Filter Accuracy Property Test

## Overview

This document describes the property-based test for device filter accuracy in the benchmark system.

## Property 28: Device Filter Accuracy

**Statement:** For any device filter applied to benchmarks, the system should return only benchmark entries matching that specific device.

**Validates:** Requirements 7.3

## Test Implementation

### File Location
`yiimp2/tests/unit/bench/DeviceFilterPropertyTest.php`

### Test Strategy

The test verifies that when filtering benchmarks by device (chip), the system returns only the benchmarks that belong to that specific device. This is tested across 100 iterations with randomly generated test data.

### Test Scenarios

For each iteration, the test:

1. **Creates Multiple Devices (Chips)**
   - Generates 2-5 random chips with different characteristics
   - Each chip has a unique ID, device type (GPU/CPU/ASIC/FPGA), and name

2. **Creates Benchmarks for Each Device**
   - Generates 2-5 random algorithms
   - Creates 1-3 benchmarks per device per algorithm
   - Each benchmark is associated with a specific chip ID

3. **Tests Device Filtering**
   - Filters benchmarks by each chip ID
   - Verifies all returned benchmarks belong to the target device
   - Verifies no benchmarks from other devices are included
   - Checks that the count matches expected results

4. **Tests Combined Filtering**
   - Filters by both device and algorithm
   - Verifies all results match both filter criteria
   - Validates count accuracy for combined filters

5. **Tests Edge Cases**
   - Filters by non-existent device ID
   - Verifies empty results are returned

### Verification Points

The test checks:

- **Device Match:** Every returned benchmark has the correct `idchip` value
- **Count Accuracy:** The number of returned benchmarks matches the expected count
- **Algorithm Match:** When combined with algorithm filter, both criteria are satisfied
- **No False Positives:** Benchmarks from other devices are not included
- **Empty Results:** Non-existent device IDs return empty result sets

### Test Data Generation

The test uses random data generators for:

- **Chip Names:** NVIDIA, AMD, Intel, Bitmain, Innosilicon models
- **Device Types:** GPU, CPU, ASIC, FPGA
- **Algorithms:** sha256, scrypt, x11, neoscrypt, lyra2v2, etc.
- **Hashrates:** Random values between 10 and 100,000 kH/s
- **Vendor IDs:** Random PCI vendor/device ID pairs

### Running the Test

```bash
# Run the device filter property test
cd yiimp2
vendor/bin/codecept run unit bench/DeviceFilterPropertyTest

# Run with verbose output
vendor/bin/codecept run unit bench/DeviceFilterPropertyTest -v

# Run all benchmark tests
vendor/bin/codecept run unit bench/
```

### Database Requirements

This test requires a working database connection. If the database is not available, the test will be skipped with an appropriate message. This is expected behavior for property-based tests that require database access.

### Expected Behavior

When a database is available:
- The test runs 100 iterations
- Each iteration creates test data, applies filters, and verifies results
- All test data is cleaned up after each iteration
- The test passes if all 100 iterations succeed

When a database is not available:
- The test is skipped with a clear message
- No failures are reported

## Implementation Details

### Controller Integration

The test simulates the filtering logic used in `BenchController`:

```php
// Filter by device (chip)
$query = Benchmarks::find()
    ->where(['idchip' => $chipId])
    ->all();

// Combined filter (device + algorithm)
$query = Benchmarks::find()
    ->where(['idchip' => $chipId, 'algo' => $algo])
    ->all();
```

### Model Relations

The test uses the relationship between:
- `Benchmarks` model (benchmark entries)
- `BenchChips` model (device/chip information)

Each benchmark has an `idchip` field that references a chip in the `bench_chips` table.

## Property Coverage

This test ensures that:

1. Device filtering is accurate and returns only matching benchmarks
2. Combined filters (device + algorithm) work correctly
3. No false positives are returned
4. Empty results are handled properly for non-existent devices
5. The filtering logic is consistent across all device types and algorithms

## Related Tests

- `AlgorithmBenchmarkDisplayPropertyTest.php` - Tests algorithm-based filtering
- `BenchmarkDataPersistencePropertyTest.php` - Tests benchmark data storage (task 8.8)

## Requirements Validation

This test validates **Requirement 7.3**:

> WHEN a user filters by device THEN the system SHALL display all algorithms supported by that device with expected hashrates

The test ensures that when filtering by device:
- Only benchmarks for that specific device are returned
- All benchmarks for that device are included
- The filtering works correctly across different algorithms
- Combined filters (device + algorithm) function properly
