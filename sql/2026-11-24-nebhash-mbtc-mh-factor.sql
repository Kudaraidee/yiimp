-- NEB hashrates are reported on the GH/s scale and its 24h estimate is quoted
-- per GH, so expose the matching mBTC/MH factor through /api/status.
UPDATE `algos` SET `speedfactor` = 1000 WHERE `name` = 'nebhash';

-- Expire the cached algo/API snapshots after applying.
-- YIIMP_algo_list / yaamp_algo_list / api_status
