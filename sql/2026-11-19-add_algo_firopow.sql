-- Firopow (Kiiro) algo registration.
-- After applying, flush/expire the web memcache keys yaamp_algo_list,
-- yaamp_visible_algos, yaamp_unvisible_algos, yaamp_algo_ports and
-- yaamp_algo_colors so the new algo and port become visible immediately.
--
-- Add the Kiiro coin row through the admin UI with algo='firopow' and
-- RPC credentials pointing at a Firopow-capable Kiiro node.
INSERT INTO `algos` (`name`, `color`, `speedfactor`, `port`, `visible`, `powlimit_bits`) VALUES
('firopow', '#e2d0d2', 1, 3641, 1, NULL);
