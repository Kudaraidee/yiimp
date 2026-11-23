<?php
/**
 * Poloniex Spot API v2.
 *
 * The legacy `poloniex.com/public?command=...` and `poloniex.com/tradingApi`
 * endpoints are gone. Public data now lives at `api.poloniex.com` and private
 * calls are signed with HMAC-SHA256 over METHOD\nPATH\nPARAMS.
 */
class poloniex
{
	protected $api_key = '';
	protected $api_secret = '';
	protected $public_url = 'https://api.poloniex.com';

	public function __construct()
	{
		if (is_file('/etc/yiimp/keys.php')) require_once('/etc/yiimp/keys.php');
		$this->api_key = defined('EXCH_POLONIEX_KEY') ? EXCH_POLONIEX_KEY : '';
		$this->api_secret = defined('EXCH_POLONIEX_SECRET') ? EXCH_POLONIEX_SECRET : '';
	}

	private function userAgent()
	{
		return 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 '.
			'(KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
	}

	// Python SDK uses quote(value, safe="~()*!'"); rawurlencode is close but
	// encodes the extra safe characters, so restore them.
	private function encodeComponent($value)
	{
		return str_replace(
			array('%28','%29','%2A','%21','%27'),
			array('(',')','*','!',"'"),
			rawurlencode((string) $value)
		);
	}

	private function buildQuery(array $params)
	{
		ksort($params);
		$parts = array();
		foreach ($params as $key => $value) {
			$parts[] = $this->encodeComponent($key).'='.$this->encodeComponent($value);
		}
		return implode('&', $parts);
	}

	private function retrieveJSON($url)
	{
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);
		curl_setopt($ch, CURLOPT_USERAGENT, $this->userAgent());
		curl_setopt($ch, CURLOPT_ENCODING, '');
		$res = curl_exec($ch);
		if ($res === false) {
			debuglog('poloniex: '.curl_error($ch));
			curl_close($ch);
			return null;
		}
		$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		if ($status < 200 || $status >= 300) return null;

		$json = json_decode($res, true);
		return is_array($json) ? $json : null;
	}

	/**
	 * Authenticated v2 request.
	 * $params are query parameters for GET, JSON body values otherwise.
	 */
	private function authRequest($method, $path, array $params = array(), $body = NULL)
	{
		if (empty($this->api_key) || empty($this->api_secret)) return false;

		$method = strtoupper($method);
		$timestamp = (string) (int) (microtime(true) * 1000);
		$bodyString = '';

		if ($body !== NULL && $body !== array()) {
			$bodyString = json_encode($body, JSON_UNESCAPED_SLASHES);
			$paramsAuth = 'requestBody='.$bodyString.'&signTimestamp='.$timestamp;
		} else {
			$signParams = array_merge(array('signTimestamp' => $timestamp), $params);
			$paramsAuth = $this->buildQuery($signParams);
		}

		$payload = $method."\n".$path."\n".$paramsAuth;
		$signature = base64_encode(hash_hmac('sha256', $payload, $this->api_secret, true));

		$url = $this->public_url.$path;
		if ($method === 'GET' && $params) {
			$url .= '?'.$this->buildQuery($params);
		}

		$headers = array(
			'key: '.$this->api_key,
			'signature: '.$signature,
			'signTimestamp: '.$timestamp,
		);
		if ($bodyString !== '') $headers[] = 'Content-Type: application/json';

		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);
		curl_setopt($ch, CURLOPT_USERAGENT, $this->userAgent());
		curl_setopt($ch, CURLOPT_ENCODING, '');
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		if ($method !== 'GET') {
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
			if ($bodyString !== '') curl_setopt($ch, CURLOPT_POSTFIELDS, $bodyString);
		}

		$res = curl_exec($ch);
		if ($res === false) {
			debuglog('poloniex: '.curl_error($ch));
			curl_close($ch);
			return false;
		}
		$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		if ($status < 200 || $status >= 300) {
			debuglog("poloniex: $method $path failed ($status) ".strip_data($res));
			return false;
		}

		$dec = json_decode($res, true);
		return is_array($dec) ? $dec : false;
	}

	/** Flatten /accounts/balances into currency => [available, hold]. */
	private function accountBalances()
	{
		$accounts = $this->authRequest('GET', '/accounts/balances', array('accountType' => 'SPOT'));
		$out = array();
		if (!is_array($accounts)) return $out;

		foreach ($accounts as $account) {
			foreach ($account['balances'] ?? array() as $balance) {
				$symbol = $balance['currency'] ?? '';
				if ($symbol === '') continue;
				$out[$symbol] = array(
					'available' => (float) ($balance['available'] ?? 0),
					'hold'      => (float) ($balance['hold'] ?? 0),
				);
			}
		}
		return $out;
	}

	// ── Authenticated compatibility methods ───────────────────────────────────

	public function get_balances()
	{
		$out = array();
		foreach ($this->accountBalances() as $symbol => $balance) {
			$out[$symbol] = $balance['available'];
		}
		return $out;
	}

	public function get_complete_balances()
	{
		$out = array();
		foreach ($this->accountBalances() as $symbol => $balance) {
			$out[$symbol] = array(
				'available' => $balance['available'],
				'onOrders'  => $balance['hold'],
			);
		}
		return $out;
	}

	public function get_available_balances()
	{
		return $this->get_balances();
	}

	public function get_deposit_addresses()
	{
		$res = $this->authRequest('GET', '/wallets/addresses');
		$out = array();
		if (!is_array($res)) return $out;

		foreach ($res as $key => $item) {
			if (is_array($item) && isset($item['currency'])) {
				$out[$item['currency']] = $item['address'] ?? '';
			} else if (is_string($item)) {
				$out[$key] = $item;
			}
		}
		return $out;
	}

	public function generate_address($currency)
	{
		$res = $this->authRequest('POST', '/wallets/address', array(), array('currency' => strtoupper($currency)));
		if (is_array($res) && isset($res['address'])) return array('response' => $res['address']);
		return false;
	}

	public function get_open_orders($pair)
	{
		$res = $this->authRequest('GET', '/orders', array('symbol' => strtoupper($pair)));
		$out = array();
		if (!is_array($res)) return $out;

		foreach ($res as $order) {
			$id = $order['id'] ?? ($order['orderId'] ?? NULL);
			if (!$id) continue;
			$out[] = array(
				'orderNumber' => $id,
				'rate'        => (float) ($order['price'] ?? 0),
				'amount'      => (float) ($order['quantity'] ?? 0),
				'total'       => (float) ($order['amount'] ?? 0),
				'type'        => strtolower($order['side'] ?? ''),
			);
		}
		return $out;
	}

	public function get_my_trade_history($pair)
	{
		return $this->get_open_orders($pair);
	}

	private function placeOrder($side, $pair, $rate, $amount)
	{
		$res = $this->authRequest('POST', '/orders', array(), array(
			'symbol'      => strtoupper($pair),
			'side'        => strtoupper($side),
			'type'        => 'limit',
			'timeInForce' => 'GTC',
			'price'       => (string) $rate,
			'quantity'    => (string) $amount,
		));
		if (is_array($res) && isset($res['id'])) return array('orderNumber' => $res['id']);
		return false;
	}

	public function buy($pair, $rate, $amount)  { return $this->placeOrder('buy',  $pair, $rate, $amount); }
	public function sell($pair, $rate, $amount) { return $this->placeOrder('sell', $pair, $rate, $amount); }

	public function cancel_order($pair, $order_number)
	{
		$res = $this->authRequest('DELETE', '/orders/'.rawurlencode((string) $order_number));
		if (is_array($res) && (isset($res['orderId']) || isset($res['state']))) {
			return array('success' => true);
		}
		return false;
	}

	public function withdraw($currency, $amount, $address)
	{
		$res = $this->authRequest('POST', '/wallets/withdraw', array(), array(
			'currency' => strtoupper($currency),
			'amount'   => (string) $amount,
			'address'  => $address,
		));
		if (is_array($res) && isset($res['withdrawalNumber'])) {
			return array('response' => 'Withdrew '.$res['withdrawalNumber']);
		}
		return false;
	}

	// ── Public compatibility methods ──────────────────────────────────────────

	public function get_trade_history($pair)
	{
		return $this->retrieveJSON($this->public_url.'/markets/'.rawurlencode(strtoupper($pair)).'/trades');
	}

	public function get_order_book($pair)
	{
		return $this->retrieveJSON($this->public_url.'/markets/'.rawurlencode(strtoupper($pair)).'/orderBook');
	}

	public function get_volume()
	{
		$data = $this->retrieveJSON($this->public_url.'/markets/ticker24h');
		$out = array();
		foreach ((array) $data as $ticker) {
			$symbol = $ticker['symbol'] ?? '';
			if ($symbol === '') continue;
			$out[$symbol] = array(
				'baseVolume'  => $ticker['quantity'] ?? 0,
				'quoteVolume' => $ticker['amount'] ?? 0,
			);
		}
		return $out;
	}

	public function get_ticker($pair = 'ALL')
	{
		$pair = strtoupper($pair);
		if ($pair !== 'ALL') {
			$book = $this->retrieveJSON($this->public_url.'/markets/'.rawurlencode($pair).'/orderBook');
			$priceData = $this->retrieveJSON($this->public_url.'/markets/'.rawurlencode($pair).'/price');
			$last = $priceData['price'] ?? 0;
			return array(
				'highestBid' => $book['bids'][0] ?? $last,
				'lowestAsk'  => $book['asks'][0] ?? $last,
				'last'       => $last,
			);
		}

		$prices = $this->retrieveJSON($this->public_url.'/markets/price');
		$out = array();
		foreach ((array) $prices as $item) {
			$symbol = $item['symbol'] ?? '';
			if ($symbol === '') continue;
			$price = $item['price'] ?? 0;
			$out[$symbol] = array(
				'highestBid' => $price,
				'lowestAsk'  => $price,
				'last'       => $price,
			);
		}
		return $out;
	}

	public function get_trading_pairs()
	{
		return array_keys($this->get_ticker());
	}

	public function get_currencies()
	{
		$data = $this->retrieveJSON($this->public_url.'/v2/currencies');
		$out = array();
		foreach ((array) $data as $item) {
			$symbol = $item['coin'] ?? '';
			if ($symbol === '') continue;
			$out[$symbol] = array(
				'name'     => $item['name'] ?? $symbol,
				'disabled' => empty($item['tradeEnable']),
				'delisted' => !empty($item['delisted']),
			);
		}
		return $out;
	}

	public function get_total_btc_balance()
	{
		$balances = $this->get_balances();
		$prices = $this->get_ticker();
		$total = 0;

		foreach ($balances as $coin => $amount) {
			$pair = 'BTC_'.strtoupper($coin);
			$price = $prices[$pair]['last'] ?? 0;
			if ($amount > 0) {
				$total += ($coin === 'BTC') ? (float) $amount : (float) $amount * (float) $price;
			}
			if ($coin === 'BTC' || !$price) continue;
			foreach ($this->get_open_orders($pair) as $order) {
				if ($order['type'] === 'buy') $total += (float) $order['total'];
				else if ($order['type'] === 'sell') $total += (float) $order['amount'] * (float) $price;
			}
		}

		return $total;
	}
}

//////////////////////////////////////////////////////////////////////////////////////////////////////////////

// manual update of one market
function poloniex_update_market($market)
{
	$exchange = 'poloniex';
	if (is_string($market))
	{
		$symbol = $market;
		$coin = getdbosql('db_coins', "symbol=:sym", array(':sym'=>$symbol));
		if(!$coin) return false;
		$pair = "BTC_$symbol";
		$market = getdbosql('db_markets', "coinid={$coin->id} AND name='$exchange'");
		if(!$market) return false;

	} else if (is_object($market)) {

		$coin = getdbo('db_coins', $market->coinid);
		if(!$coin) return false;
		$symbol = $coin->getOfficialSymbol();
		$pair = "BTC_$symbol";
		if (!empty($market->base_coin)) $pair = $market->base_coin.'_'.$symbol;
	}

	$t1 = microtime(true);
	$poloniex = new poloniex;
	$ticker = $poloniex->get_ticker($pair);
	if (!is_array($ticker) || !isset($ticker['highestBid'])) {
		$apims = round((microtime(true) - $t1)*1000,3);
		user()->setFlash('error', "$exchange $symbol: error after $apims ms, ".json_encode($ticker));
		return false;
	}

	$price2 = ($ticker['highestBid']+$ticker['lowestAsk'])/2;
	$market->price2 = AverageIncrement($market->price2, $price2);
	$market->price = AverageIncrement($market->price, $ticker['highestBid']);
	$market->pricetime = time();
	$market->save();

	$apims = round((microtime(true) - $t1)*1000,3);
	user()->setFlash('message', "$exchange $symbol price updated in $apims ms");

	return true;
}
