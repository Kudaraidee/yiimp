-- NEB nebhash PoW pool registration (neb-pool/1 stratum protocol).
-- Algo id 60 and coin id 13 are fixed so runtime config/logs/restart lists
-- can refer to them before the migration is applied.
--
-- The NEB node has no Bitcoin-style getblocktemplate/validateaddress RPC.
-- The stratum connects to the node's work service on 127.0.0.1:3441 and uses
-- the JSON-RPC getinfo method only for chain id/height. PoW, targets, chain
-- shares and block submission are handled by stratum/nebhash/.
--
-- Only the pinned NMD2/Kepler+TESS dataset shipped with node v1.0.4 is
-- supported. Legacy V1 model frames are rejected; a pool must run node
-- v1.0.4 with a matching neb-datasets.bin.
--
-- After applying, expire the web memcache keys yaamp_algo_list,
-- yaamp_visible_algos, yaamp_unvisible_algos, yaamp_algo_ports,
-- yaamp_algo_colors, yaamp_coin_list, api_status,
-- all_results_current_results, nebhash_results_current_results and
-- yiimp-explorer-diff-NEB (both raw and md5-hashed forms).
INSERT IGNORE INTO `algos` (`id`, `name`, `color`, `speedfactor`, `port`, `visible`, `powlimit_bits`)
VALUES (60, 'nebhash', '#7fd6ff', 1, 3645, 1, NULL);

INSERT IGNORE INTO `coins`
  (`id`, `name`, `symbol`, `algo`, `rpcencoding`, `reward`, `price`, `enable`,
   `visible`, `auto_exchange`, `installed`, `auto_ready`, `rpchost`, `rpcport`,
   `rpccurl`, `hasgetinfo`, `hassubmitblock`, `usesegwit`, `auxpow`, `txfee`,
   `block_time`, `network_hash`)
VALUES
  (13, 'Neb', 'NEB', 'nebhash', 'BTC', 20, 0, 1,
   1, 0, 1, 1, '127.0.0.1', 8432,
   1, 0, 0, 0, 0, 0,
   60, 0);
