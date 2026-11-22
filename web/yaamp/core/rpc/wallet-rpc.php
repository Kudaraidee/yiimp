<?php
/**
 * Common yiimp Wallet RPC object
 */
class WalletRPC {

	public $type = 'Bitcoin';
	protected $rpc;
	protected $rpc_wallet;
	protected $hasGetInfo = false;

	// cache
	protected $account;
	protected $accounts;
	protected $coin;
	protected $info;
	protected $height = 0;
	protected $ethHead = null;

	// Information and debugging
	public $error;
	// public $status;
	// public $raw_response;
	// public $response;

	function __construct($userOrCoin, $pw='', $host='localhost', $port=8332, $url=null)
	{
		if (is_object($userOrCoin)) {

			$coin = $userOrCoin;
			switch ($coin->rpcencoding) {
			case 'GETH':
				$this->type = 'Ethereum';
				$this->coin = $coin;
				$this->account = empty($coin->account) ? $coin->master_wallet : $coin->account;
				$this->rpc = new Ethereum($coin->rpchost, $coin->rpcport);
				break;
			case 'XMR':
				$this->type = 'CryptoNote';
				$this->rpc = new CryptoRPC($coin->rpchost, $coin->rpcport, $coin->rpcuser, $coin->rpcpasswd);
				// for now, assume the wallet in on localhost, and daemon set in the db
				$this->rpc_wallet = new CryptoRPC("127.0.0.1", $coin->rpcport, $coin->rpcuser, $coin->rpcpasswd);
				$this->coin = $coin;
				break;
			default:
				$this->type = 'Bitcoin';
				$this->rpc = new Bitcoin($coin->rpcuser, $coin->rpcpasswd, $coin->rpchost, $coin->rpcport, $url);
				$this->hasGetInfo = $coin->hasgetinfo;
			}

		} else {
			// backward compat
			$user = $userOrCoin;
			$this->rpc = new Bitcoin($user, $pw, $host, $port, $url);
		}
	}

	/**
	 * Ethereum RPC returns its own field names. Map them onto the Bitcoin-like
	 * shape expected by the explorer views while keeping the original keys, so
	 * backend callers that already use Ethereum fields are unaffected.
	 */
	protected function ethExplorerBlock($block)
	{
		if (!is_object($block) && !is_array($block)) return false;

		$result = is_object($block) ? (array) $block : $block;
		$number = (int) $this->rpc->decode_hex((string) arraySafeVal($block, 'number', '0x0'));
		if (!isset($this->ethHead)) $this->ethHead = $this->rpc->eth_blockNumber();
		$head = $this->ethHead;

		$result['height'] = $number;
		$result['confirmations'] = is_numeric($head) ? max(0, ((int)$head) - $number + 1) : 0;
		$result['time'] = (int) $this->rpc->decode_hex((string) arraySafeVal($block, 'timestamp', '0x0'));
		$result['difficulty'] = $this->rpc->decode_hex((string) arraySafeVal($block, 'difficulty', '0x0'));
		$result['version'] = (int) $this->rpc->decode_hex((string) arraySafeVal($block, 'version', '0x0'));
		$result['size'] = (int) $this->rpc->decode_hex((string) arraySafeVal($block, 'size', '0x0'));
		$result['bits'] = arraySafeVal($block, 'bits', '');
		$result['merkleroot'] = arraySafeVal($block, 'transactionsRoot', '');
		$result['previousblockhash'] = arraySafeVal($block, 'parentHash', '');

		$txhashes = array();
		$rawtxs = arraySafeVal($block, 'transactions', array());
		if (is_array($rawtxs)) foreach ($rawtxs as $tx) {
			$hash = (is_object($tx) || is_array($tx)) ? arraySafeVal($tx, 'hash') : $tx;
			if (!empty($hash)) $txhashes[] = $hash;
		}
		$result['tx'] = $txhashes;

		return $result;
	}

	protected function ethExplorerTx($tx)
	{
		if (!is_object($tx) && !is_array($tx)) return false;

		$result = is_object($tx) ? (array) $tx : $tx;
		$value = (double) $this->rpc->decode_hex((string) arraySafeVal($tx, 'value', '0x0')) / 1e18;
		$to = arraySafeVal($tx, 'to', '');
		if (empty($to)) $to = arraySafeVal($tx, 'contractAddress', '');

		$result['txid'] = arraySafeVal($tx, 'hash', '');
		$result['hex'] = arraySafeVal($tx, 'hex', '');
		$result['vout'] = array(array(
			'value' => $value,
			'scriptPubKey' => array('addresses' => array($to)),
		));
		$result['vin'] = array(array('addr' => arraySafeVal($tx, 'from', '')));

		return $result;
	}

	/**
	 * Geth's admin_peers does not report the remote peer height. Public nodes
	 * usually answer eth_blockNumber on their RPC port, so query it directly.
	 */
	protected function ethPeerHeight($addr, $localChain, $deadline)
	{
		$host = '';
		if (preg_match('/^(\d{1,3}(?:\.\d{1,3}){3}):\d+$/', $addr, $m)) {
			$host = $m[1];
		} elseif (preg_match('/^\[([^\]]+)\]:\d+$/', $addr, $m)) {
			$host = $m[1];
		}
		if ($host === '' || filter_var($host, FILTER_VALIDATE_IP,
				FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
			return null;
		}
		if (!function_exists('curl_init') || microtime(true) >= $deadline) return null;

		$payload = json_encode(array(
			array('jsonrpc' => '2.0', 'id' => 1, 'method' => 'eth_blockNumber', 'params' => array()),
			array('jsonrpc' => '2.0', 'id' => 2, 'method' => 'eth_chainId', 'params' => array()),
		));
		$ch = curl_init('http://'.$host.':8545/');
		curl_setopt_array($ch, array(
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => $payload,
			CURLOPT_HTTPHEADER => array('Content-Type: application/json'),
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_CONNECTTIMEOUT_MS => 700,
			CURLOPT_TIMEOUT_MS => 1500,
		));
		$resp = curl_exec($ch);
		curl_close($ch);
		if (!is_string($resp)) return null;
		$data = json_decode($resp, true);
		if (!is_array($data)) return null;

		$block = null; $chain = null;
		foreach ($data as $row) {
			if (!is_array($row)) continue;
			if (isset($row['id']) && $row['id'] == 1) $block = isset($row['result']) ? $row['result'] : null;
			if (isset($row['id']) && $row['id'] == 2) $chain = isset($row['result']) ? $row['result'] : null;
		}
		if (!is_string($block) || !preg_match('/^0x[0-9a-f]+$/i', $block)) return null;
		$expected = null;
		if (is_string($localChain) && preg_match('/^0x[0-9a-f]+$/i', $localChain)) {
			$expected = (int) hexdec($localChain);
		} elseif (is_numeric($localChain)) {
			$expected = (int) $localChain;
		}
		if ($expected !== null && is_string($chain) && preg_match('/^0x[0-9a-f]+$/i', $chain)) {
			if (hexdec($chain) !== $expected) return null;
		}
		return (int) hexdec($block);
	}

	function __call($method, $params)
	{
		if (stripos($method, "dump") !== false || stripos($method, "backupwallet") !== false) {
			$this->error = "$method not authorized!";
			debuglog("$method rpc method is not authorized!");
			return false;
		}

		if ($this->type == 'Ethereum') {
			// A mining node does not need to expose a local wallet. Use the
			// configured pool address instead of eth_accounts() when present.
			if (!isset($this->accounts)) {
				$accounts = $this->rpc->eth_accounts();
				$this->error = $this->rpc->error;
				if (is_array($accounts)) {
					// A mining node can expose no local wallet accounts while
					// still paying block rewards to the configured pool address.
					if (!empty($this->account)) {
						$known = array_map('strtolower', $accounts);
						if (!in_array(strtolower($this->account), $known, true)) {
							$accounts[] = $this->account;
						}
					}
					$this->accounts = $accounts;
				} elseif (!empty($this->account)) {
					$this->accounts = array($this->account);
					$this->error = '';
				}
			}
			if (!is_array($this->accounts)) {
				// if wallet is stopped
				return false;
			}
			// convert common methods used by yiimp
			switch ($method) {
			case 'getaccountaddress':
				if (!empty($params[0]))
					return $params[0];
				return $this->account;
			case 'getinfo':
				if (!isset($this->info)) {
					$info = array();
					$info['accounts'] = array();
					$balances = 0;

					foreach ($this->accounts as $addr) {
						// web3.fromWei(eth.getBalance("0x..."), "ether")
						$balance = (double) $this->rpc->eth_getBalance($addr,'latest', true);
						$balance /= 1e18;
						$balances += $balance;
						$info['accounts'][$addr] = $balance;
					}
					$info['balance'] = $balances;
					$this->height = $this->height ? $this->height : $this->rpc->eth_blockNumber();
					$info['blocks'] = $this->height;
					$block = $this->rpc->eth_getBlockByNumber('latest', false);
					$info['difficulty'] = $this->rpc->decode_hex(objSafeVal($block, 'difficulty', 0));
					$info['gasprice'] = (double) $this->rpc->eth_gasPrice();
					$info['gasprice'] /= 1e18;
					$info['connections'] = $this->rpc->net_peerCount();
					$info['version'] = $this->rpc->web3_clientVersion();
					$this->info = $info;
				}
				return $this->info;
			case 'getdifficulty':
				$this->error = $this->rpc->error;
				$block = $this->rpc->eth_getBlockByNumber('latest', false);
				$difficulty = objSafeVal($block, 'difficulty', 0);
				return $this->rpc->decode_hex($difficulty);
			case 'getmininginfo':
				$info = array();
				$this->height = $this->height ? $this->height : $this->rpc->eth_blockNumber();
				$info['blocks'] = $this->height;
				$block = $this->rpc->eth_getBlockByNumber('latest', false);
				$difficulty = objSafeVal($block, 'difficulty', 0);
				$info['difficulty'] = $this->rpc->decode_hex($difficulty);
				$info['generate'] = $this->rpc->eth_mining();
				$info['errors'] = '';
				$this->error = $this->rpc->error;
				return $info;
			case 'getblock':
				$hash = arraySafeVal($params,0);
				$block = $this->rpc->eth_getBlockByHash($hash, true);
				$this->error = $this->rpc->error;
				return $this->ethExplorerBlock($block);
			case 'getblockhash':
				$n = arraySafeVal($params,0);
				// eth_getBlockByNumber requires a hex quantity, not a decimal height.
				if (is_numeric($n)) $n = '0x'.dechex((int)$n);
				$block = $this->rpc->eth_getBlockByNumber($n, false);
				$this->error = $this->rpc->error;
				if (!$block) return false;
				return arraySafeVal($block, 'hash');
			case 'gettransaction':
			case 'getrawtransaction':
				$txid = arraySafeVal($params,0,'');
				$tx = $this->rpc->eth_getTransactionByHash($txid);
				if (!$tx) {
					// Ethereum has no coinbase transaction. Backend block accounting
					// passes the block hash for PoW rewards, so synthesize the
					// Bitcoin-like generated transaction from the coin's reward.
					$block = $this->rpc->eth_getBlockByHash($txid, false);
					if ($block) {
						$mapped = $this->ethExplorerBlock($block);
						$this->error = NULL;
						return array(
							'txid' => $txid,
							'blockhash' => $txid,
							'time' => arraySafeVal($mapped, 'time', 0),
							'confirmations' => arraySafeVal($mapped, 'confirmations', 0),
							'details' => array(array(
								'category' => 'generate',
								// Yii AR exposes columns via __get, so arraySafeVal() cannot see them.
								'amount' => (double) ($this->coin ? $this->coin->reward : 0),
							)),
						);
					}
				}
				$this->error = $this->rpc->error;
				return $this->ethExplorerTx($tx);
			case 'getwork':
				return false; //$this->rpc->eth_getWork(); auto enable miner!
			// todo...
			case 'getpeerinfo':
				// Geth hides its peer list behind the optional admin namespace. Try
				// admin_peers, then fall back to the peer count + client string so
				// the admin peers page is not blank on mining nodes.
				$saved = $this->error;
				$peers = array();
				$localChain = $this->rpc->ether_request('eth_chainId', array());
				$deadline = microtime(true) + 4.0;
				$raw = $this->rpc->ether_request('admin_peers', array());
				if (is_array($raw)) {
					foreach (array_slice($raw, 0, 12) as $p) {
						// JSON-RPC decodes each peer as stdClass, some nodes as array.
						if (is_object($p)) $p = (array) $p;
						if (!is_array($p)) continue;
						$caps = isset($p['caps']) && is_array($p['caps']) ? implode(' ', $p['caps']) : '';
						$network = isset($p['network']) ? $p['network'] : array();
						if (is_object($network)) $network = (array) $network;
						$node = isset($network['remoteAddress']) ? $network['remoteAddress'] :
							(isset($p['listenAddr']) ? $p['listenAddr'] :
							(isset($p['ip']) ? $p['ip'] : (isset($p['enode']) ? $p['enode'] : '')));
						$height = $this->ethPeerHeight($node, $localChain, $deadline);
						$peers[] = array(
							'addr' => $node, 'subver' => isset($p['name']) ? $p['name'] : '',
							'currentheight' => $height !== null ? $height : 0, 'pingtime' => '', 'services' => $caps,
							'conntime' => time(), 'startingheight' => '',
							'lastrecv' => time(), 'lastsend' => time(),
							'bytesrecv' => 0, 'bytessent' => 0, 'synthetic' => false,
						);
					}
				}
				if (empty($peers)) {
					$count = $this->rpc->net_peerCount();
					$version = $this->rpc->web3_clientVersion();
					$peers[] = array(
						'addr' => 'Peer count: '.(int)$count,
						'subver' => is_string($version) ? $version : '',
						'currentheight' => 0, 'pingtime' => '', 'services' => '',
						'conntime' => time(), 'startingheight' => '',
						'lastrecv' => time(), 'lastsend' => time(),
						'bytesrecv' => 0, 'bytessent' => 0, 'synthetic' => true,
					);
				}
				$this->error = $saved;
				return $peers;
			case 'listtransactions':
				$txs = array();
				return $txs;
			case 'listsinceblock':
				$txs = array();
				return $txs;
			default:
				$res = $this->rpc->ether_request($method,$params);
				$this->error = $this->rpc->error;
				return $res;
			}
		}

		// XMR & BBR
		else if ($this->type == 'CryptoNote')
		{
			// convert some rpc methods used by yiimp (and handle wallet rpc, very limited)
			switch ($method) {
			case "getinfo":
				$res = $this->rpc->getinfo();
				$res["blocks"] = arraySafeVal($res,"height");
				$res["connections"] = arraySafeVal($res,"white_peerlist_size");
				$balances = $this->rpc_wallet->getbalance();
				$res["balance"] = arraySafeVal($balances,"unlocked_balance") / 1e12;
				$res["pending"] = arraySafeVal($balances,"balance",0) / 1e12 - $res["balance"];
				$ver = arraySafeVal($res,"mi");
				$res["version"] = (int) sprintf("%02d%02d%02d%02d", // BBR
					arraySafeVal($ver,"ver_major"), arraySafeVal($ver,"ver_minor"), arraySafeVal($ver,"ver_revision"), arraySafeVal($ver,"build_no"));
				$this->error = $this->rpc_wallet->error.$this->rpc->error;
				unset($res["mi"]);
				break;
			case "getmininginfo":
				$res = $this->rpc->getinfo();
				$res["networkhps"] = arraySafeVal($res,"current_network_hashrate_50");
				unset($res["current_network_hashrate_50"]);
				unset($res["current_network_hashrate_350"]);
				unset($res["mi"]);
				unset($res["grey_peerlist_size"]);
				unset($res["white_peerlist_size"]);
				unset($res["incoming_connections_count"]);
				unset($res["outgoing_connections_count"]);
				unset($res["max_net_seen_height"]);
				unset($res["synchronization_start_height"]);
				unset($res["transactions_cnt_per_day"]);
				unset($res["transactions_volume_per_day"]);
				unset($res["mi"]);
				$data = $this->rpc->getlastblockheader();
				$header = arraySafeVal($data,"block_header");
				$res["reward"] = (double) arraySafeVal($header,"reward") / 1e12;
				$this->error = $this->rpc->error;
				break;
			case "getnetworkinfo":
				$res = $this->rpc->getinfo();
				$res["connections"] = arraySafeVal($res,"white_peerlist_size");
				$res["networkhps"] = arraySafeVal($res,"current_network_hashrate_50");
				unset($res["current_network_hashrate_50"]);
				unset($res["current_network_hashrate_350"]);
				unset($res["mi"]);
				$this->error = $this->rpc->error;
				break;
			case 'getaccountaddress':
				$res = $this->rpc_wallet->getaddress();
				$res = objSafeVal($res, "address");
				$this->error = $this->rpc_wallet->error;
				break;
			case 'getblocktemplate':
				$gbt_params = array(
					"wallet_address" => $this->coin->master_wallet,
					"reserve_size"   => 8, // extra data
					"alias_details"  => null,
				);
				$res = $this->rpc->getblocktemplate($gbt_params);
				$data = $this->rpc->getlastblockheader();
				$header = arraySafeVal($data,"block_header");
				$res["coinbase"] = (double) arraySafeVal($header,"reward") / 1e4;
				$res["reward"] = (double) arraySafeVal($header,"reward") / 1e12;
				$this->error = $this->rpc->error;
				break;
			case "getbalance":
				$res = $this->rpc_wallet->getbalance();
				$this->error = $this->rpc_wallet->error;
				$res = arraySafeVal($res,"unlocked_balance") / 1e12;
				break;
			case "getbalances":
				$res = $this->rpc_wallet->getbalance();
				$this->error = $this->rpc_wallet->error;
				break;
			case 'listtransactions':
				// make it as close as possible as bitcoin rpc... sigh (todo: xmr-rpc function)
				$txs = array();
				$named_params = array('transfer_type' => 'all');
				$res = $this->rpc_wallet->incoming_transfers($named_params);
				$res = isset($res['transfers']) ? $res['transfers'] : array();
				$this->error = $this->rpc_wallet->error;
				foreach ($res as $k=>$tx) {
					$tx['category'] = 'receive';
					$tx['txid'] = $tx['tx_hash'];
					$tx['amount'] = $tx['amount'] / 1e12;
					$raw = $this->rpc->gettransactions(array(
						'txs_hashes' => array($tx['tx_hash']),
						'decode_as_json' => true
					));
					$raw = reset(arraySafeVal($raw,'txs',array()));
					if (!empty($raw)) {
						$k = (double) $raw['block_height'] + ($k/1000.0);
						unset($raw['as_hex']);
						unset($raw['tx_hash']);
						//$raw['json'] = json_decode($raw['as_json']);
						unset($raw['as_json']);
					}
					$tx = array_merge($tx, $raw);
					unset($tx['tx_hash']);
					$k = sprintf("%015.4F", $k); // sort key
					$txs[$k] = $tx;
				}
				$named_params = array('min_block_height' => 1);
				$res = $this->rpc_wallet->get_bulk_payments($named_params);
				$res = isset($res['payments']) ? $res['payments'] : array();
				foreach ($res as $k=>$tx) {
					$tx['category'] = 'send';
					$k = (double) $raw['block_height'] + 0.5 + ($k/1000.0); // sort key
					$tx['txid'] = $tx['tx_hash'];
					$tx['amount'] = $tx['amount'] / 1e12;
					$raw = $this->rpc->gettransactions(array(
						'txs_hashes' => array($tx['tx_hash']),
						'decode_as_json' => true
					));
					$raw = reset(arraySafeVal($raw,'txs',array()));
					if (!empty($raw)) {
						unset($raw['as_hex']);
						unset($raw['tx_hash']);
						//$raw['json'] = json_decode($raw['as_json']);
						unset($raw['as_json']);
					}
					$tx = array_merge($tx, $raw);
					unset($tx['tx_hash']);
					$k = sprintf("%015.4F", $k);
					$txs[$k] = $tx;
				}
				krsort($txs);
				$res = array_values($txs);
				break;
			case 'getaddress':
				$res = $this->rpc_wallet->getaddress();
				$this->error = $this->rpc_wallet->error;
				break;
			case 'get_bulk_payments':
				$res = $this->rpc_wallet->get_bulk_payments();
				$this->error = $this->rpc_wallet->error;
				break;
			case 'get_payments':
				$named_params = array(
					"payment_id"=>arraySafeVal($params, 0)
				);
				$res = $this->rpc_wallet->get_payments($named_params);
				$this->error = $this->rpc_wallet->error;
				break;
			case 'get_transfers': // deprecated ?
				$res = $this->rpc_wallet->get_transfers();
				$this->error = $this->rpc_wallet->error;
				break;
			case 'incoming_transfers': // deprecated ?
				$named_params = array(
					"transfer_type"=>arraySafeVal($params, 0)
				);
				$res = $this->rpc_wallet->incoming_transfers($named_params);
				$this->error = $this->rpc_wallet->error;
				break;
			case 'sendtoaddress':
				// 3rd param is "payment id"
				$destination = array(
					"address"=>arraySafeVal($params, 0),
					"amount"=> (double) arraySafeVal($params, 1) * 1e12,
				);
				$named_params = array(
					"mixin"=>0,
					"destinations" => array((object)$destination),
					"payment_id" => arraySafeVal($params, 2),
				);
				$res = $this->rpc_wallet->transfer($named_params);
				$this->error = $this->rpc_wallet->error;
				break;
			case 'sendmany':
				$destinations = array();
				foreach ($params as $dest) {
					foreach ($dest as $addr => $amount) {
						$data = array("amount" => (double) $amount * 1e12, "address"=>$addr);
						$destinations[] = (object) $data;
					}
				}
				$named_params = array(
					"mixin"=>arraySafeVal($params, 0, 0),
					"destinations"=>$destinations,
				);
				$res = $this->rpc_wallet->transfer($named_params);
				$this->error = $this->rpc_wallet->error;
				break;
			case 'transfer':
			case 'transfer_original':
				$destination = array(
					"address"=> arraySafeVal($params, 1),
					"amount"=> (double) arraySafeVal($params, 2, 0) * 1e12,
					// also: "fee" "unlock_time"
				);
				$destinations = array();
				$destinations[] = (object)$destination;
				$named_params = array(
					"mixin" => arraySafeVal($params, 0, 0),
					"destinations" => $destinations,
					"payment_id" => arraySafeVal($params, 3),
				);
				$res = $this->rpc_wallet->transfer($named_params);
				$this->error = $this->rpc_wallet->error;
				break;
			case 'reset':
				$res = $this->rpc_wallet->reset();
				$this->error = $this->rpc_wallet->error;
				break;
			case 'store':
				$res = $this->rpc_wallet->store();
				$this->error = $this->rpc_wallet->error;
				break;
			case 'gettransactions':
				$named_params = array(
					"txs_hashes" => array(arraySafeVal($params, 0, array())),
					'decode_as_json' => true
				);
				$res = $this->rpc->gettransactions($named_params);
				unset($res['txs_as_hex']); // dup
				unset($res['txs_as_json']); // dup
				$this->error = $this->rpc->error;
				return $res;
			default:
				// default to daemon
				$res = $this->rpc->__call($method,$params);
				$this->error = $this->rpc->error;
			}

			return $res;
		}

		// Bitcoin RPC
        	switch ($method) {
			case 'getinfo':
				if ($this->hasGetInfo) {
					$res = $this->rpc->__call($method,$params);
				} else {
					$miningInfo = $this->rpc->getmininginfo();
					if (!$miningInfo) { $res = false; break; }
					$res["blocks"] = arraySafeVal($miningInfo,"blocks");
					$res["difficulty"] = arraySafeVal($miningInfo,"difficulty");
					$res["testnet"] = "main" != arraySafeVal($miningInfo,"chain");
					$walletInfo = $this->rpc->getwalletinfo();
					$res["walletversion"] = arraySafeVal($walletInfo,"walletversion");
					$res["balance"] = arraySafeVal($walletInfo,"balance");
					$res["keypoololdest"] = arraySafeVal($walletInfo,"keypoololdest");
					$res["keypoolsize"] = arraySafeVal($walletInfo,"keypoolsize");
					$res["paytxfee"] = arraySafeVal($walletInfo,"paytxfee");
					$networkInfo = $this->rpc->getnetworkinfo();
					$res["version"] = arraySafeVal($networkInfo,"version");
					$res["protocolversion"] = arraySafeVal($networkInfo,"protocolversion");
					$res["timeoffset"] = arraySafeVal($networkInfo,"timeoffset");
					$res["connections"] = arraySafeVal($networkInfo,"connections");
//                    			$res["proxy"] = arraySafeVal($networkInfo,"networks")[0]["proxy"];
					$res["relayfee"] = arraySafeVal($networkInfo,"relayfee");
				}
				break;
			default:
				$res = $this->rpc->__call($method,$params);
        	}

		$this->error = $this->rpc->error;
		return $res;
	}

	function __get($prop)
	{
		return $this->rpc->$prop;
	}

	function __set($prop, $value)
	{
		//debuglog("wallet set $prop ".json_encode($value));
		$this->rpc->$prop = $value;
	}

	function execute($query)
	{
		$result = '';

		if (!empty($query)) try {

			// if its a raw json query...
			if (strpos($query,"{") !== false && json_decode($query)) {
				try {
					debuglog($query);
					$result = $this->rpc->request_json($query);
				} catch (Exception $e) {
					$result = false;
				}
				return $result;
			}

			$params = explode(' ', trim($query));
			$command = array_shift($params);

			$p = array();
			foreach ($params as $param) {
				if ($param === 'true' || $param === 'false') {
					$param = $param === 'true' ? true : false;
				}
				else if (strpos($param, '0x') === 0)
					$param = "$param"; // eth hex crap
				else
					$param = (is_numeric($param)) ? 0 + $param : trim($param,'"');
				$p[] = $param;
			}

			switch (count($params)) {
			case 0:
				$result = $this->$command();
				break;
			case 1:
				$result = $this->$command($p[0]);
				break;
			case 2:
				$result = $this->$command($p[0], $p[1]);
				break;
			case 3:
				$result = $this->$command($p[0], $p[1], $p[2]);
				break;
			case 4:
				$result = $this->$command($p[0], $p[1], $p[2], $p[3]);
				break;
			case 5:
				$result = $this->$command($p[0], $p[1], $p[2], $p[3], $p[4]);
				break;
			case 6:
				$result = $this->$command($p[0], $p[1], $p[2], $p[3], $p[4], $p[5]);
				break;
			case 7:
				$result = $this->$command($p[0], $p[1], $p[2], $p[3], $p[4], $p[5], $p[6]);
				break;
			case 8:
				$result = $this->$command($p[0], $p[1], $p[2], $p[3], $p[4], $p[5], $p[6], $p[7]);
				break;
			default:
				$result = 'error: too much parameters';
			}

		} catch (Exception $e) {
			$result = false;
		}

		return $result;
	}

}
