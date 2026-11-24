-- Meraki (Telestai / TLS) PoW pool registration.
-- Algo id 61 and coin id 14 are fixed so runtime configs, logs and restart
-- lists can refer to them before the migration is applied.
--
-- Telestai Core 3.0.0 provides getblocktemplate/submitblock with a Meraki
-- ProgPoW 0.9.4 variant. The stratum verifies PoW itself with the isolated
-- libmeraki port under stratum/meraki/ and submits standard Bitcoin-style
-- 80-byte headers with nHeight/nNonce64/mix_hash extensions.
--
-- The coinbase emits 468 TLS at the current subsidy. Telestai enforces a
-- 117 TLS development output to TesBmcgLQsowvYEYPXpSHkkapoTbVV7Xfe, so the
-- pool controls 351 TLS. `reward` is that effective miner/pool amount,
-- matching the coinbase output credited by the backend.
--
-- RPC credentials are deliberately blank here. The runtime setup script
-- writes the generated credentials and the new pool payout wallet after the
-- Telestai node is available. The mainnet source defaults are
-- 127.0.0.1:8766 (P2P 8767).
--
-- After applying, expire the web memcache keys yaamp_algo_list,
-- yaamp_visible_algos, yaamp_unvisible_algos, yaamp_algo_ports,
-- yaamp_algo_colors, yaamp_coin_list, api_status,
-- all_results_current_results, meraki_results_current_results and
-- yiimp-explorer-diff-TLS (both raw and md5-hashed forms).
INSERT IGNORE INTO `algos` (`id`, `name`, `color`, `speedfactor`, `port`, `visible`, `powlimit_bits`)
VALUES (61, 'meraki', '#ffd166', 1, 3646, 1, NULL);

INSERT IGNORE INTO `coins`
  (`id`, `name`, `symbol`, `algo`, `rpcencoding`, `reward`, `price`, `enable`,
   `visible`, `auto_exchange`, `installed`, `auto_ready`, `rpchost`, `rpcport`,
   `rpcuser`, `rpcpasswd`, `rpccurl`, `hasgetinfo`, `hassubmitblock`,
   `usesegwit`, `auxpow`, `txfee`, `block_time`, `mature_blocks`,
   `master_wallet`, `link_explorer`, `network_hash`, `image`)
VALUES
  (14, 'Telestai', 'TLS', 'meraki', 'BTC', 351, 0, 1,
   1, 0, 1, 1, '127.0.0.1', 8766,
   'yiimprpc', '', 1, 0, 1,
   1, 0, 0, 60, 100,
   'AmNJHTBSSznzJDQoV8WAPAEVkFjzCPJ4qxmy6CB49V7FYE5xNzzS',
   'https://telestai.cryptoscope.io/', 0, '/images/coin-TLS.png');
