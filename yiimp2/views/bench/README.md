# Benchmark Feature Documentation

## Overview
The benchmark feature allows users to view and submit mining hardware performance data for various algorithms.

## Features Implemented

### 1. Benchmark Listing (`/bench`)
- View all benchmarks organized by algorithm
- Filter by algorithm
- Filter by chip/device
- Display hashrate, power consumption, and efficiency
- Pagination support

### 2. Algorithm-Specific View (`/bench/{algo}`)
- View benchmarks for a specific algorithm
- Aggregated statistics (average, min, max hashrate)
- Device performance comparison
- Shows average hashrate, power, efficiency per chip

### 3. Device Listing (`/bench/devices`)
- View all devices in the benchmark database
- Shows device type (GPU/CPU/ASIC/FPGA)
- Lists supported algorithms per device
- Recent algorithms (last 30 days)

### 4. Benchmark Submission (`/bench/submit`)
- Form to submit new benchmark data
- Required fields: Algorithm, Device Type, Hashrate
- Optional fields: Power, Chip, Frequencies, Driver info
- Automatic chip matching if chip name is provided

## Models

### Benchmarks
- Stores individual benchmark submissions
- Fields: algo, type, device, khps, power, chip info, frequencies, etc.
- Relations: BenchChip, User
- Helper methods: getFormattedHashrate(), getFormattedPower(), getEfficiency()

### BenchChips
- Stores chip/device reference data
- Fields: chip name, device type, vendor ID, performance data
- Relations: Benchmarks
- Helper methods: getDeviceTypeDisplay()

## URL Routes
- `/bench` - Main benchmark listing
- `/bench/{algo}` - Algorithm-specific benchmarks
- `/bench/devices` - Device listing
- `/bench/submit` - Submit new benchmark

## Database Tables
- `benchmarks` - Individual benchmark records
- `bench_chips` - Chip reference data

## Requirements Validated
- Requirement 7.1: Benchmark listing organized by algorithm ✓
- Requirement 7.2: Algorithm-specific benchmarks with device performance ✓
- Requirement 7.3: Device filtering ✓
- Requirement 7.4: Benchmark submission with device model, chip type, algorithm, hashrate ✓
