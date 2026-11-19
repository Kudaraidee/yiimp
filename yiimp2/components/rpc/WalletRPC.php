<?php

namespace app\components\rpc;

use app\components\rpc\iRPCConnector;
use app\components\rpc\cBitcoinRPC;

class WalletRPC {
	public $type = 'Bitcoin';
	public $error = null;
	protected ?iRPCConnector $connector = null;
	protected $hasGetInfo = false;
	protected $coin = null;
	protected $account = '';
	protected $info = null;

	public function __construct($coin) {
		if (is_object($coin) && $coin->rpcencoding) {
			$this->coin = $coin;
			$this->account = !empty($coin->account) ? $coin->account : $coin->master_wallet;
			$this->type = ($coin->rpcencoding === 'GETH') ? 'Ethereum' : 'Bitcoin';
			$this->connector = new cBitcoinRPC($coin->rpcuser, $coin->rpcpasswd, $coin->rpchost, $coin->rpcport);
			$this->hasGetInfo = $coin->hasgetinfo;
		}
	}

	private function rpc($method, array $params = [])
	{
		if ($this->connector === null) return null;
		$result = call_user_func_array([$this->connector, $method], $params);
		if ($this->connector->error) $this->error = $this->connector->error;
		return $result;
	}

	private static function arrVal($arr, $key, $default = null)
	{
		return (is_array($arr) && isset($arr[$key])) ? $arr[$key] : $default;
	}

	private static function hexToFloat($hex)
	{
		if (is_array($hex)) $hex = self::arrVal($hex, 'result');
		if (!is_string($hex)) return 0.0;
		$hex = preg_replace('/^0x/i', '', trim($hex));
		if ($hex === '' || !ctype_xdigit($hex)) return 0.0;
		return (float) hexdec($hex);
	}

	private static function hexToInt($hex)
	{
		return (int) self::hexToFloat($hex);
	}

	// geth-style nodes do not implement getinfo. Expose the fields Yiimp expects.
	public function getinfo()
	{
		if ($this->type !== 'Ethereum') return $this->__call('getinfo', []);
		if ($this->info !== null) return $this->info;

		$blocks = self::hexToInt($this->rpc('eth_blockNumber'));
		$balance = self::hexToFloat($this->rpc('eth_getBalance', [$this->account, 'latest'])) / 1e18;
		$connections = self::hexToInt($this->rpc('net_peerCount'));
		$version = $this->rpc('web3_clientVersion');
		if (!is_string($version)) $version = '';

		$this->info = [
			'balance' => $balance,
			'blocks' => $blocks,
			'connections' => $connections,
			'errors' => $this->error ? strtolower($this->error) : '',
			'version' => $version,
		];
		return $this->info;
	}

	public function getdifficulty()
	{
		if ($this->type !== 'Ethereum') return $this->__call('getdifficulty', []);
		$height = $this->rpc('eth_blockNumber');
		$block = $this->rpc('eth_getBlockByNumber', [$height, false]);
		return self::hexToFloat(self::arrVal($block, 'difficulty'));
	}

	public function getmininginfo()
	{
		if ($this->type !== 'Ethereum') return $this->__call('getmininginfo', []);
		$info = $this->getinfo();
		return [
			'blocks' => self::arrVal($info, 'blocks', 0),
			'difficulty' => $this->getdifficulty(),
			'generate' => false,
			'errors' => self::arrVal($info, 'errors', ''),
		];
	}

	public function getaccountaddress($account = null)
	{
		if ($this->type !== 'Ethereum') return $this->__call('getaccountaddress', [$account]);
		return empty($account) ? $this->account : $account;
	}

	public function __call($name, $arguments) {
        if ($this->connector === null) return null;
        if (method_exists($this->connector, $name) || method_exists($this->connector, '__call')) {
            return call_user_func_array([$this->connector, $name], $arguments);
        } else {
            error_log("undefined method $name in wallet-rpc");
        }
    }
}
