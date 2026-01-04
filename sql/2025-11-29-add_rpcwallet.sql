-- Add rpcwallet field for Bitcoin Core 0.17+ compatibility
-- Coins like Briskcoin 3.0 require wallet name in RPC path
-- This migration consolidates the add-rpcwallet-field.sql into the standard migration format

ALTER TABLE `coins` ADD `rpcwallet` varchar(64) DEFAULT NULL AFTER `rpcport`;

-- Example: For Briskcoin 3.0, set rpcwallet to 'pool' or your wallet name
-- UPDATE coins SET rpcwallet='pool' WHERE symbol='BKC';
