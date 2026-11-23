-- NEB neb1 payout addresses are 62-character bech32m strings. Yiimp stores
-- the full address in workers.name (as well as accounts.username), but the
-- legacy column was varchar(52). Widen it so NEB authorize does not fail with
-- MySQL error 1406 and close the miner's socket.
ALTER TABLE `workers` MODIFY COLUMN `name` varchar(128) DEFAULT NULL;

-- The NEB coin row can predate the INSERT IGNORE in the nebhash migration when
-- the web crawler discovers a symbol before the pool is registered. Ensure an
-- existing row is enabled so stratum includes it in g_list_coind and accepts
-- local bech32m validation on the dedicated nebhash listener.
UPDATE `coins` SET `enable` = 1 WHERE `id` = 13 AND `symbol` = 'NEB';
UPDATE `coins` SET `master_wallet` = 'neb1vnf9ulnttzvdmrn58ymqqthmc074pegvc0ay8en8gnepwdwq2y3s2vyazg'
  WHERE `id` = 13 AND `symbol` = 'NEB' AND (`master_wallet` IS NULL OR `master_wallet` = '');
