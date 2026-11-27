-- Parallax (LAX) native XHash pool registration.
--
-- Algo id 62 and coin id 15 are fixed so runtime config, logs and restart
-- lists can refer to them before the migration is applied.
--
-- Parallax is an Ethereum-style chain, so the stratum uses the shared
-- ethash work/protocol module with a chain-specific engine. Mining RPCs are
-- xhash_getWork / xhash_submitWork; block queries stay in the eth_ namespace.
-- XHash epoch length is 720 blocks (consensus, not configurable here).
--
-- Difficulty follows the same 2^32 share scale used by ethash/etchash:
--   target = 2^224 / difficulty
-- The node advertises a 2^256 boundary; the stratum converts it to the
-- miner-visible difficulty and back without changing that convention.
-- powlimit_bits = 0 makes the web hashrate estimate difficulty / block_time,
-- i.e. 4.72e12 / 600s ~= 7.9 GH/s at height 66139.
--
-- Parallax has a 100-block coinbase maturity. The official Go node does not
-- support HTTP basic auth, so RPC credentials stay blank and HTTP/WS are bound
-- to loopback only. The local RPC endpoint is 127.0.0.1:8555 (8545 is already
-- used by the Larissa node); P2P is 32110 and WebSocket 8556. The pool payout
-- address reuses the existing EVM key from the LRS coin row.
--
-- After applying, expire the web memcache keys yaamp_algo_list,
-- yaamp_visible_algos, yaamp_unvisible_algos, yaamp_algo_ports,
-- yaamp_algo_colors, yaamp_coin_list, api_status and
-- xhash_results_current_results.
INSERT IGNORE INTO `algos` (`id`, `name`, `color`, `speedfactor`, `port`, `visible`, `powlimit_bits`)
VALUES (62, 'xhash', '#b39ddb', 1, 3647, 1, 0);

INSERT IGNORE INTO `coins`
  (`id`, `name`, `symbol`, `algo`, `rpcencoding`, `reward`, `price`, `enable`,
   `visible`, `auto_exchange`, `installed`, `auto_ready`, `rpchost`, `rpcport`,
   `rpcuser`, `rpcpasswd`, `rpccurl`, `hasgetinfo`, `hassubmitblock`,
   `usesegwit`, `auxpow`, `txfee`, `block_time`, `mature_blocks`,
   `master_wallet`, `network_hash`)
VALUES
  (15, 'Parallax', 'LAX', 'xhash', 'GETH', 50, 0, 1,
   1, 0, 1, 1, '127.0.0.1', 8555,
   '', '', 1, 0, 0,
   0, 0, 0, 600, 100,
   '0x084aA43e293a9945AEEA0E1C038766dd26A4329a', 0);
