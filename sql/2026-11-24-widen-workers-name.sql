-- NEB neb1 payout addresses are 62-character bech32m strings. Yiimp stores
-- the full address in workers.name (as well as accounts.username), but the
-- legacy column was varchar(52). Widen it so NEB authorize does not fail with
-- MySQL error 1406 and close the miner's socket.
ALTER TABLE `workers` MODIFY COLUMN `name` varchar(128) DEFAULT NULL;
