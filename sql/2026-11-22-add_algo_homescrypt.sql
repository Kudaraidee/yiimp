-- HomeScrypt v1.2 (Lumenite / LMT) algo registration.
-- After applying, flush/expire the web memcache keys yaamp_algo_list,
-- yaamp_visible_algos, yaamp_unvisible_algos, yaamp_algo_ports and
-- yaamp_algo_colors so the new algo and ports become visible immediately.
--
-- Add the LMT coin row through the admin UI (or the runtime setup script)
-- with algo='homescrypt' and RPC credentials pointing at a Lumenite Core
-- v1.2.0 node. The stratum verifies HomeScrypt PoW itself using the same
-- 16 MiB scratchpad, Scrypt seed stage and FP32 FMA chain as the node.
INSERT INTO `algos` (`name`, `color`, `speedfactor`, `port`, `visible`, `powlimit_bits`) VALUES
('homescrypt', '#9fe8c7', 1, 3644, 1, NULL);
