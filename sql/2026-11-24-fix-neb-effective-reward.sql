-- NEB coinbase emits 20 NEB total, but 1 NEB is paid to a dev locktype-2
-- output that the pool does not control. The effective miner/pool reward is
-- 19 NEB; align the registered coin with the amount actually credited by
-- BackendBlockFind1() from the pool-owned coinbase output.
UPDATE `coins`
SET `reward` = 19
WHERE `symbol` = 'NEB' AND `algo` = 'nebhash' AND `id` = 13;
