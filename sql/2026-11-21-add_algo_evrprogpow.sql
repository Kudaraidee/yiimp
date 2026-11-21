-- EvrProgpow (Evrmore / EVR) algo registration.
-- After applying, flush/expire the web memcache keys yaamp_algo_list,
-- yaamp_visible_algos, yaamp_unvisible_algos, yaamp_algo_ports and
-- yaamp_algo_colors so the new algo and port become visible immediately.
--
-- Add the EVR coin row through the admin UI with algo='evrprogpow' and RPC
-- credentials pointing at an Evrmore Core node with getblocktemplate enabled.
INSERT INTO `algos` (`name`, `color`, `speedfactor`, `port`, `visible`, `powlimit_bits`) VALUES
('evrprogpow', '#d8c7ff', 1, 3643, 1, NULL);
