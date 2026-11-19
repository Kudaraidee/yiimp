-- After applying, flush/expire the web memcache keys yaamp_algo_list,
-- yaamp_visible_algos, yaamp_unvisible_algos, yaamp_algo_ports and
-- yaamp_algo_colors so the new algos and ports become visible immediately.
--
-- Add coin rows through the admin UI with algo='ethash' or 'etchash' and
-- rpcencoding='GETH' for the corresponding PoW-capable Ethereum node.
INSERT INTO `algos` (`name`, `color`, `speedfactor`, `port`, `visible`, `powlimit_bits`) VALUES
('ethash', '#e2d0d2', 1, 3639, 1, NULL),
('etchash', '#e2d0d2', 1, 3640, 1, NULL);
