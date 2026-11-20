-- Scash (RandomX) algo registration.
-- After applying, flush/expire the web memcache keys yaamp_algo_list,
-- yaamp_visible_algos, yaamp_unvisible_algos, yaamp_algo_ports and
-- yaamp_algo_colors so the new algo and port become visible immediately.
--
-- Add the Scash coin row through the admin UI with algo='randomscash' and RPC
-- credentials pointing at a Scash Core node. The stratum verifies RandomX PoW
-- itself, so the node only needs getblocktemplate and submitblock.
INSERT INTO `algos` (`name`, `color`, `speedfactor`, `port`, `visible`, `powlimit_bits`) VALUES
('randomscash', '#d5c7f2', 1, 3642, 1, NULL);
