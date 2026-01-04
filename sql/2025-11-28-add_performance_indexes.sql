-- ============================================================================
-- Add Performance Indexes for Admin Panel Queries
-- Date: 2025-11-28
-- 
-- This migration adds indexes to improve query performance for:
-- - Time-based worker queries (active workers, worker monitoring)
-- - Time-based share queries (share statistics, hashrate calculations)
-- - Time-based block queries (recent blocks, block statistics)
-- - Time-based payment queries (payment monitoring)
-- - User filtering queries (banned users, locked accounts)
-- - Foreign key columns (referential integrity and join performance)
-- ============================================================================

-- Workers table indexes
-- Add time index for active worker queries (WHERE time > X)
ALTER TABLE `workers` ADD INDEX `idx_time` (`time`);

-- Add composite index for user worker lookups with time filter
ALTER TABLE `workers` ADD INDEX `idx_userid_time` (`userid`, `time`);

-- Add composite index for algorithm filtering with time
ALTER TABLE `workers` ADD INDEX `idx_algo_time` (`algo`, `time`);

-- Payouts table indexes
-- Add time index for time-based payment queries
ALTER TABLE `payouts` ADD INDEX `idx_time` (`time`);

-- Add composite index for completed status with time
ALTER TABLE `payouts` ADD INDEX `idx_completed_time` (`completed`, `time`);

-- Earnings table indexes
-- Add mature_time index for matured earnings queries
ALTER TABLE `earnings` ADD INDEX `idx_mature_time` (`mature_time`);

-- Add composite index for coinid with create_time
ALTER TABLE `earnings` ADD INDEX `idx_coinid_create_time` (`coinid`, `create_time`);

-- Accounts table indexes
-- Add is_locked index for filtering banned users
ALTER TABLE `accounts` ADD INDEX `idx_is_locked` (`is_locked`);

-- Add composite index for coinid with balance
ALTER TABLE `accounts` ADD INDEX `idx_coinid_balance` (`coinid`, `balance`);

-- Blocks table indexes
-- Add composite index for time with category (for filtering by status)
ALTER TABLE `blocks` ADD INDEX `idx_time_category` (`time`, `category`);

-- Add composite index for coin_id with time
ALTER TABLE `blocks` ADD INDEX `idx_coin_id_time` (`coin_id`, `time`);

-- Add composite index for userid with time
ALTER TABLE `blocks` ADD INDEX `idx_userid_time` (`userid`, `time`);

-- Shares table indexes
-- Add composite index for time with valid (for valid share queries)
ALTER TABLE `shares` ADD INDEX `idx_time_valid` (`time`, `valid`);

-- Add composite index for userid with time
ALTER TABLE `shares` ADD INDEX `idx_userid_time` (`userid`, `time`);

-- Add composite index for coinid with time
ALTER TABLE `shares` ADD INDEX `idx_coinid_time` (`coinid`, `time`);

-- Add composite index for algo with time
ALTER TABLE `shares` ADD INDEX `idx_algo_time` (`algo`, `time`);

-- Markets table indexes
-- Add composite index for coinid with lasttraded
ALTER TABLE `markets` ADD INDEX `idx_coinid_lasttraded` (`coinid`, `lasttraded`);

-- Balanceuser table indexes
-- Add composite index for userid with time
ALTER TABLE `balanceuser` ADD INDEX `idx_userid_time` (`userid`, `time`);

-- Hashuser table indexes
-- Add composite index for userid with time and algo
ALTER TABLE `hashuser` ADD INDEX `idx_userid_time_algo` (`userid`, `time`, `algo`);

-- Hashrate table indexes
-- Add composite index for algo with time
ALTER TABLE `hashrate` ADD INDEX `idx_algo_time` (`algo`, `time`);

-- ============================================================================
-- Index Addition Complete
-- 
-- These indexes will significantly improve performance for:
-- 1. Active worker queries (time-based filtering)
-- 2. Share statistics and hashrate calculations
-- 3. Block monitoring and statistics
-- 4. Payment processing and monitoring
-- 5. User management (filtering by locked status)
-- 6. Algorithm-specific queries
-- 7. Time-range queries across all tables
-- ============================================================================
