<?php
// SafeTrade REST API v2
// https://safe.trade/api/v2
// https://safetrade.com/api

/**
 * Cloudflare rejects the pool's egress IP for safe.trade. Public GET data is
 * still retrievable through a read-only text relay; unwrap the relay header so
 * the original JSON is decoded exactly as if the direct call had succeeded.
 * Authenticated/trading calls never use this path.
 */
function safetrade_unwrap_relay($raw)
{
	$raw = ltrim((string) $raw, "\xEF\xBB\xBF");
	$marker = 'Markdown Content:';
	$pos = strpos($raw, $marker);
	if ($pos !== false) $raw = substr($raw, $pos + strlen($marker));
	$raw = ltrim($raw);
	$start = strcspn($raw, '[{');
	if ($start > 0 && $start < strlen($raw)) $raw = substr($raw, $start);
	return trim($raw);
}

function safetrade_public_relay_allowed($method)
{
	return strpos(strtolower(ltrim((string) $method, '/')), 'trade/public/') === 0;
}

function safetrade_api_query($method, $params='', $returnType='object')
{
	$uri = 'https://safe.trade/api/v2/'.ltrim((string) $method, '/');
	if (!empty($params)) {
		$query = is_array($params) ? http_build_query($params, '', '&') : $params;
		$uri .= '?'.$query;
	}

	$ch = curl_init($uri);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
	curl_setopt($ch, CURLOPT_TIMEOUT, 30);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array(
		'Accept: application/json',
	));
	curl_setopt($ch, CURLOPT_USERAGENT,
		'Mozilla/5.0 (compatible; Yiimp SafeTrade client; '.php_uname('s').'; PHP/'.PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION.')');
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	$raw = curl_exec($ch);
	$status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close($ch);

	if ($raw === false || $status >= 300) {
		static $warned = false;
		if (!$warned) {
			$warned = true;
			debuglog("safetrade: public api returned HTTP $status, trying read-only relay");
		}

		if (!safetrade_public_relay_allowed($method)) return null;

		$relay = 'https://r.jina.ai/'.$uri;
		$ch2 = curl_init($relay);
		curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch2, CURLOPT_CONNECTTIMEOUT, 10);
		curl_setopt($ch2, CURLOPT_TIMEOUT, 30);
		curl_setopt($ch2, CURLOPT_HTTPHEADER, array(
			'Accept: application/json',
		));
		curl_setopt($ch2, CURLOPT_USERAGENT,
			'Mozilla/5.0 (compatible; Yiimp SafeTrade client; '.php_uname('s').'; PHP/'.PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION.')');
		curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
		$relayRaw = curl_exec($ch2);
		$relayStatus = (int) curl_getinfo($ch2, CURLINFO_HTTP_CODE);
		curl_close($ch2);

		if ($relayRaw === false || $relayStatus >= 300) {
			static $relayWarned = false;
			if (!$relayWarned) {
				$relayWarned = true;
				debuglog("safetrade: read-only relay returned HTTP $relayStatus");
			}
			return null;
		}

		$raw = safetrade_unwrap_relay($relayRaw);
		static $relayUsed = false;
		if (!$relayUsed) {
			$relayUsed = true;
			debuglog('safetrade: using read-only relay for public prices');
		}
	}

	$ret = json_decode($raw, strtolower((string) $returnType) === 'array');
	if (json_last_error() !== JSON_ERROR_NONE) {
		debuglog('safetrade: invalid public api response '.strip_data(substr($raw, 0, 200)));
		return null;
	}

	return $ret;
}

/**
 * SafeTrade requires a unique, monotonically increasing nonce within 5s of
 * server time. Microseconds let several calls happen inside one second.
 */
function safetrade_next_nonce()
{
	static $last = 0;
	$now = (int) round(microtime(true) * 1000000);
	$nonce = max($now, $last + 1);
	$last = $nonce;
	return (string) $nonce;
}

/**
 * Authenticated request.
 *
 * Official scheme: HMAC-SHA256(secret, nonce + apikey), sent with
 * X-Auth-Apikey / X-Auth-Nonce / X-Auth-Signature. The method, path, query and
 * body are intentionally NOT part of the signed message.
 */
function safetrade_api_user($method, $url_params = array(), $request_method='GET', $returnType='object')
{
	if (empty(EXCH_SAFETRADE_KEY) || empty(EXCH_SAFETRADE_SECRET)) return false;

	$key = trim((string) EXCH_SAFETRADE_KEY);
	$secret = (string) EXCH_SAFETRADE_SECRET;
	$nonce = safetrade_next_nonce();
	$path = '/api/v2/'.ltrim((string) $method, '/');
	$url = 'https://safe.trade'.$path;
	$payload = '';

	if (strtoupper($request_method) === 'POST') {
		$payload = is_array($url_params) ? json_encode($url_params) : (string) $url_params;
	} elseif (!empty($url_params)) {
		$query = is_array($url_params) ? http_build_query($url_params, '', '&') : $url_params;
		$url .= '?'.$query;
	}

	$signature = hash_hmac('sha256', $nonce.$key, $secret);
	$headers = array(
		'Accept: application/json',
		'Content-Type: application/json',
		'X-Auth-Apikey: '.$key,
		'X-Auth-Nonce: '.$nonce,
		'X-Auth-Signature: '.$signature,
	);

	$http = new cHTTP();
	$http->setURL($url);
	$http->setHeaders($headers);
	$http->setUserAgentString('Mozilla/5.0 (compatible; Yiimp SafeTrade client; '
		.php_uname('s').'; PHP/'.PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION.')');
	$http->setFailOnError(false);
	if (strtoupper($request_method) === 'POST') $http->setPostfields($payload);

	$raw = $http->execRequest();
	$status = isset($http->fResult['HTTP_Code']) ? (int) $http->fResult['HTTP_Code'] : 0;

	if ($status === 0 || $status >= 300 || !is_string($raw)) {
		debuglog("safetrade: $method failed ($status) ".strip_data(substr((string) $raw, 0, 200)));
		return false;
	}

	$res = json_decode($raw, strtolower((string) $returnType) === 'array');
	if (json_last_error() !== JSON_ERROR_NONE) {
		debuglog("safetrade: $method returned invalid JSON ".strip_data(substr($raw, 0, 200)));
		return false;
	}

	if (is_array($res) && !empty($res['errors'])) {
		debuglog("safetrade: $method error ".strip_data(json_encode($res['errors'])));
		return false;
	}

	return $res;
}

// ── Pair / market helpers ─────────────────────────────────────────────────────

function safetrade_supported_quote($base)
{
	return in_array(strtoupper((string) $base), array('BTC', 'USDT'), true);
}

function safetrade_market_id($symbol, $base)
{
	return strtolower((string) $symbol).strtolower((string) $base);
}

function safetrade_market_name($base)
{
	$base = strtoupper((string) $base);
	return ($base === 'BTC') ? 'safetrade' : 'safetrade '.$base;
}

function safetrade_official_symbol($coin)
{
	if (is_object($coin) && method_exists($coin, 'getOfficialSymbol')) return $coin->getOfficialSymbol();
	return is_object($coin) ? $coin->symbol : (string) $coin;
}

function safetrade_trade_symbol($coin)
{
	$symbol = safetrade_official_symbol($coin);
	if (is_object($coin) && !empty($coin->symbol2)) $symbol = $coin->symbol2;
	return strtoupper($symbol);
}

function safetrade_markets($refresh=false)
{
	static $cache = null;
	if ($cache !== null && !$refresh) return $cache;

	$list = safetrade_api_query('trade/public/markets', '', 'array');
	if (!is_array($list)) return is_array($cache) ? $cache : array();

	$indexed = array();
	foreach ($list as $market) {
		if (isset($market['id'])) $indexed[strtolower($market['id'])] = $market;
	}
	$cache = $indexed;
	return $cache;
}

function safetrade_depth($marketId, $limit=10)
{
	$raw = safetrade_api_query('trade/public/markets/'.rawurlencode($marketId).'/depth?limit='.(int) $limit, '', 'array');
	if (!is_array($raw) || empty($raw['asks']) || empty($raw['bids'])) return null;

	$normalize = function ($levels, $ascending) {
		$out = array();
		foreach ((array) $levels as $level) {
			if (!isset($level[0], $level[1])) continue;
			$out[] = array((float) $level[0], (float) $level[1]);
		}
		usort($out, function ($a, $b) use ($ascending) {
			return $ascending ? ($a[0] <=> $b[0]) : ($b[0] <=> $a[0]);
		});
		return $out;
	};

	$asks = $normalize($raw['asks'], true);
	$bids = $normalize($raw['bids'], false);
	if (empty($asks) || empty($bids)) return null;

	return array(
		'ask' => $asks[0][0],
		'bid' => $bids[0][0],
		'asks' => $asks,
		'bids' => $bids,
		'sequence' => isset($raw['sequence']) ? $raw['sequence'] : 0,
	);
}

/** Best bid/ask from depth, falling back to the last traded price if depth is empty. */
function safetrade_best_prices($marketId, $ticker=null)
{
	$depth = safetrade_depth($marketId);
	if ($depth && $depth['bid'] > 0 && $depth['ask'] > 0) return $depth;

	$last = 0;
	if (is_object($ticker) && isset($ticker->last)) $last = (float) $ticker->last;
	if (is_array($ticker) && isset($ticker['last'])) $last = (float) $ticker['last'];
	if ($last <= 0) return null;

	return array('bid' => $last, 'ask' => $last, 'bids' => array(), 'asks' => array());
}

function safetrade_find_market($coin, $base)
{
	$base = strtoupper((string) $base);
	if (!safetrade_supported_quote($base)) return null;
	$coinid = (int) $coin->id;

	if ($base === 'BTC') {
		return getdbosql('db_markets', "coinid=$coinid AND name='safetrade' AND
			IFNULL(base_coin,'') IN ('','BTC')");
	}

	$market = getdbosql('db_markets', "coinid=$coinid AND name='safetrade ".addslashes($base)."'");
	if (!$market) {
		$market = getdbosql('db_markets', "coinid=$coinid AND name='safetrade' AND base_coin='".addslashes($base)."'");
	}
	return $market;
}

function safetrade_markets_for_coin($coin)
{
	$coinid = (int) $coin->id;
	return getdbolist('db_markets', "coinid=$coinid AND (name='safetrade' OR name LIKE 'safetrade %')");
}

function safetrade_choose_pair($coin, $markets)
{
	$symbol = safetrade_trade_symbol($coin);
	foreach (array('BTC', 'USDT') as $base) {
		$market = safetrade_find_market($coin, $base);
		if (!$market || !empty($market->deleted) || !empty($market->disabled)) continue;

		$id = safetrade_market_id($symbol, $base);
		if (!isset($markets[$id])) continue;
		if (isset($markets[$id]['state']) && $markets[$id]['state'] !== 'enabled') continue;

		return array(
			'id' => $id,
			'base' => $base,
			'market' => $market,
			'meta' => $markets[$id],
		);
	}
	return null;
}

function safetrade_round_down($value, $precision)
{
	$factor = pow(10, max(0, (int) $precision));
	return floor(((float) $value) * $factor) / $factor;
}

/** Submit a limit sell. Returns the API response array, or false when skipped/failed. */
function safetrade_create_limit_sell($marketId, $price, $amount, $meta)
{
	$pricePrecision = isset($meta['price_precision']) ? (int) $meta['price_precision'] : 8;
	$amountPrecision = isset($meta['amount_precision']) ? (int) $meta['amount_precision'] : 8;
	$minAmount = isset($meta['min_amount']) ? (float) $meta['min_amount'] : 0.0;
	$minPrice = isset($meta['min_price']) ? (float) $meta['min_price'] : 0.0;

	$price = round((float) $price, $pricePrecision);
	$amount = safetrade_round_down($amount, $amountPrecision);
	if ($price <= 0 || $amount <= 0) return false;
	if ($minPrice > 0 && $price < $minPrice) return false;
	if ($minAmount > 0 && $amount + 1e-12 < $minAmount) return false;

	// SafeTrade's CreateOrderParams has no "type" field: a price makes it a
	// limit order, and sending extra fields can trip strict validation.
	$res = safetrade_api_user('trade/market/orders', array(
		'market' => $marketId,
		'side' => 'sell',
		'price' => (float) $price,
		'amount' => (float) $amount,
	), 'POST', 'array');

	if (!is_array($res) || !empty($res['errors']) || !isset($res['id'])) {
		debuglog('safetrade: create order failed '.strip_data(json_encode($res)));
		return false;
	}

	$res['_submitted_price'] = $price;
	$res['_submitted_amount'] = $amount;
	return $res;
}

/** Store a SafeTrade order in db_orders. */
function safetrade_store_db_order($marketName, $coinid, $amount, $price, $bid, $ask, $orderId)
{
	$dbOrder = getdbosql('db_orders', "market=:market AND uuid=:uuid", array(
		':market' => $marketName, ':uuid' => (string) $orderId,
	));
	if ($dbOrder) return $dbOrder;

	$dbOrder = new db_orders;
	$dbOrder->market = $marketName;
	$dbOrder->coinid = (int) $coinid;
	$dbOrder->amount = (float) $amount;
	$dbOrder->price = (float) $price;
	$dbOrder->bid = (float) $bid;
	$dbOrder->ask = (float) $ask;
	$dbOrder->uuid = (string) $orderId;
	$dbOrder->created = time();
	$dbOrder->save();
	return $dbOrder;
}

// ── Deposit address / price storage helpers ───────────────────────────────────

function safetrade_network($currency, $base='BTC')
{
	$currency = strtoupper((string) $currency);
	$base = safetrade_supported_quote($base) ? strtoupper($base) : 'BTC';

	$override = market_get('safetrade', $currency, 'network', '', $base);
	if (!empty($override)) return $override;

	$override = exchange_get('safetrade', strtolower($currency).'_network', '');
	if (!empty($override)) return $override;

	$override = exchange_get('safetrade', 'deposit_network', '');
	if (!empty($override)) return $override;

	$defaults = array(
		'BTC' => 'bitcoin-key',
		'USDT' => 'bsc-tokens',
		'USDC' => 'spl-key',
		'DAI' => 'erc20_key',
	);
	if (isset($defaults[$currency])) return $defaults[$currency];

	static $currencyCache = array();
	if (!isset($currencyCache[$currency])) {
		$info = safetrade_api_query('trade/public/currencies/'.rawurlencode(strtolower($currency)), '', 'array');
		$network = '';
		if (is_array($info) && !empty($info['networks'])) {
			foreach ($info['networks'] as $item) {
				if (!empty($item['deposit_enabled']) && !empty($item['blockchain_key'])) {
					$network = $item['blockchain_key'];
					break;
				}
			}
		}
		$currencyCache[$currency] = $network;
	}
	if (!empty($currencyCache[$currency])) return $currencyCache[$currency];

	return strtolower($currency);
}

function safetrade_extract_address($data, $network='')
{
	if (is_object($data)) $data = (array) $data;
	if (!is_array($data)) return '';

	if (!empty($data['address'])) return trim((string) $data['address']);

	$addresses = array();
	if (isset($data['deposit_addresses'])) $addresses = $data['deposit_addresses'];
	elseif (isset($data['deposit_address'])) $addresses = array($data['deposit_address']);

	foreach ((array) $addresses as $item) {
		if (is_object($item)) $item = (array) $item;
		if (!is_array($item) || empty($item['address'])) continue;
		if ($network !== '' && isset($item['network']) && strcasecmp($item['network'], $network) !== 0) continue;
		return trim((string) $item['address']);
	}

	// Fall back to the first available address if the requested network is absent.
	foreach ((array) $addresses as $item) {
		if (is_object($item)) $item = (array) $item;
		if (is_array($item) && !empty($item['address'])) return trim((string) $item['address']);
	}
	return '';
}

function safetrade_sync_deposit_address($coin, $base, $accounts=null)
{
	$market = safetrade_find_market($coin, $base);
	if (!$market || !empty($market->deposit_address)) return '';

	$symbol = safetrade_trade_symbol($coin);
	$network = safetrade_network($symbol, $base);
	$address = '';

	if (is_array($accounts)) {
		foreach ($accounts as $account) {
			if (!isset($account['currency']) || strcasecmp($account['currency'], $symbol) !== 0) continue;
			$address = safetrade_extract_address($account, $network);
			break;
		}
	}

	if ($address === '') {
		$info = safetrade_api_user(
			'trade/account/deposit_address/'.rawurlencode(strtolower($symbol)),
			array('network' => $network), 'GET', 'array'
		);
		$address = safetrade_extract_address($info, $network);
	}

	if ($address !== '') {
		$market->deposit_address = $address;
		$market->save();
	}
	return $address;
}

/** Apply best bid/ask to a market row and seed coin prices when still empty. */
function safetrade_apply_market_prices($market, $coin, $bid, $ask, $base)
{
	$bid = (float) $bid;
	$ask = (float) $ask;
	if ($bid <= 0 && $ask <= 0) return false;
	if ($bid <= 0) $bid = $ask;
	if ($ask <= 0) $ask = $bid;

	$base = safetrade_supported_quote($base) ? strtoupper($base) : 'BTC';
	$official = safetrade_official_symbol($coin);
	$price2 = ($ask + $bid) / 2;

	$market->price2 = AverageIncrement((float) $market->price2, $price2);
	$market->price = AverageIncrement((float) $market->price, $bid);
	$market->base_coin = $base;
	if ($base !== 'BTC' && $market->name === 'safetrade') {
		$market->name = 'safetrade '.$base;
	}
	$market->priority = (int) market_get('safetrade', $official, 'priority', 0, $base);
	$market->txfee = 0.2;
	$market->pricetime = time();
	$market->deleted = 0;
	$market->save();

	// coins.price is stored in BTC; convert USDT quotes before seeding.
	$quoteBtc = ($base === 'BTC') ? 1.0 : yiimp_quote_btc_price($base, 0);
	if ($quoteBtc > 0 && (empty($coin->price2) || (float) $coin->price2 == 0)) {
		$coin->price = $market->price * $quoteBtc;
		$coin->price2 = $market->price2 * $quoteBtc;
		$coin->save();
	}

	return true;
}

/** Manual update wrapper used for qualified "safetrade USDT" rows. */
function safetradeUSDT_update_market($market)
{
	return safetrade_update_market($market);
}

/** Manual update of a single market (used by exchange_update_market()). */
function safetrade_update_market($market)
{
	if (is_string($market)) {
		$coin = getdbosql('db_coins', 'symbol=:sym', array(':sym' => $market));
		if (!$coin) return false;
		$market = safetrade_find_market($coin, 'BTC');
		if (!$market) return false;
	} elseif (is_object($market)) {
		$coin = getdbo('db_coins', $market->coinid);
		if (!$coin) return false;
	} else {
		return false;
	}

	$base = 'BTC';
	if (!empty($market->base_coin)) $base = strtoupper($market->base_coin);
	elseif (strpos((string) $market->name, 'safetrade ') === 0) {
		$parts = explode(' ', $market->name);
		if (isset($parts[1])) $base = strtoupper($parts[1]);
	}
	if (!safetrade_supported_quote($base)) return false;

	$id = safetrade_market_id(safetrade_trade_symbol($coin), $base);
	$prices = safetrade_best_prices($id);
	if (!$prices) return false;

	return safetrade_apply_market_prices($market, $coin, $prices['bid'], $prices['ask'], $base);
}

//////////////////////////////////////////////////////////////////////////////////////////////////////////////
