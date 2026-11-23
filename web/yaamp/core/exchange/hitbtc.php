<?php
// HitBTC REST API v3. v1 (`/api/1/public/*`, nonce/apikey signing) is retired.
// https://hitbtc.com/api

function hitbtc_browser_user_agent()
{
	return 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) '.
		'Chrome/120.0.0.0 Safari/537.36';
}

// $method accepts a v3 public path relative to /api/3/, with legacy aliases
// "symbols" and "<PAIR>/ticker" still supported.
function hitbtc_api_query($method, $params='', $returnType='object')
{
	$method = ltrim($method, '/');

	if ($method === 'symbols') {
		$endpoint = 'public/symbol';
	} else if ($method === 'ticker') {
		$endpoint = 'public/ticker';
	} else if (preg_match('#^([A-Z0-9]+)/ticker$#i', $method, $m)) {
		$endpoint = 'public/ticker';
		$params = 'symbols='.urlencode(strtoupper($m[1]));
	} else {
		$endpoint = 'public/'.$method;
	}

	$url = 'https://api.hitbtc.com/api/3/'.$endpoint;
	if (!empty($params))
		$url .= '?'.$params;

	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
	curl_setopt($ch, CURLOPT_TIMEOUT, 30);
	// HitBTC blocks non-browser user agents on public endpoints.
	curl_setopt($ch, CURLOPT_USERAGENT, hitbtc_browser_user_agent());
	curl_setopt($ch, CURLOPT_ENCODING, '');

	$execResult = curl_exec($ch);
	if ($execResult === false) {
		debuglog('hitbtc: '.curl_error($ch));
		curl_close($ch);
		return false;
	}

	$ret = $returnType == 'object' ? json_decode($execResult) : json_decode($execResult, true);
	if (!is_object($ret) && !is_array($ret)) {
		$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		debuglog("hitbtc: $endpoint failed ($status) ".strip_data($execResult));
	}
	curl_close($ch);

	return $ret;
}

// v3 auth: Basic base64(apiKey:secretKey). $endpoint is relative to /api/3/.
function hitbtc_api_user($endpoint, $params=NULL, $httpMethod='GET')
{
	require_once('/etc/yiimp/keys.php');
	if (!defined('EXCH_HITBTC_KEY')) define('EXCH_HITBTC_KEY', '');
	if (!defined('EXCH_HITBTC_SECRET')) define('EXCH_HITBTC_SECRET', '');

	if (empty(EXCH_HITBTC_KEY) || empty(EXCH_HITBTC_SECRET)) return false;

	$httpMethod = strtoupper($httpMethod);
	$endpoint = ltrim($endpoint, '/');
	$url = 'https://api.hitbtc.com/api/3/'.$endpoint;

	$body = '';
	if ($httpMethod === 'GET') {
		if (!empty($params)) $url .= '?'.http_build_query($params);
	} else if (!empty($params)) {
		$body = json_encode($params, JSON_UNESCAPED_SLASHES);
	}

	$auth = base64_encode(EXCH_HITBTC_KEY.':'.EXCH_HITBTC_SECRET);
	$headers = array(
		'Authorization: Basic '.$auth,
		'Content-Type: application/json; charset=utf-8',
	);

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
	curl_setopt($ch, CURLOPT_TIMEOUT, 30);
	curl_setopt($ch, CURLOPT_USERAGENT, hitbtc_browser_user_agent());
	curl_setopt($ch, CURLOPT_ENCODING, '');

	if ($httpMethod !== 'GET') {
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $httpMethod);
		if ($body !== '') curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
	}

	$res = curl_exec($ch);
	if ($res === false) {
		$e = curl_error($ch);
		debuglog("hitbtc: $endpoint $e");
		curl_close($ch);
		return false;
	}

	$result = json_decode($res);
	if (!is_object($result) && !is_array($result)) {
		$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		debuglog("hitbtc: $endpoint failed ($status) ".strip_data($res));
	}

	curl_close($ch);
	return $result;
}

//////////////////////////////////////////////////////////////////////////////////////////////////////////////

// manual update of one market
function hitbtc_update_market($market)
{
	$exchange = 'hitbtc';
	if (is_string($market))
	{
		$symbol = $market;
		$coin = getdbosql('db_coins', "symbol=:sym", array(':sym'=>$symbol));
		if(!$coin) return false;
		$pair = 'BTC'.$symbol;
		$market = getdbosql('db_markets', "coinid={$coin->id} AND name='$exchange'");
		if(!$market) return false;

	} else if (is_object($market)) {

		$coin = getdbo('db_coins', $market->coinid);
		if(!$coin) return false;
		$symbol = $coin->getOfficialSymbol();
		$pair = 'BTC'.$symbol;
		if (!empty($market->base_coin)) $pair = $market->base_coin.$symbol;
	}

	$t1 = microtime(true);
	// v3 returns a map keyed by symbol, e.g. {"LTCBTC":{"bid":"...","ask":"..."}}
	$tickers = hitbtc_api_query('ticker', 'symbols='.urlencode($pair));
	$ticker = is_object($tickers) ? ($tickers->{$pair} ?? NULL) : NULL;
	if(!is_object($ticker) || !isset($ticker->ask)) return false;

	$price2 = ((double) $ticker->bid + (double)$ticker->ask)/2;
	$market->price2 = AverageIncrement($market->price2, $price2);
	$market->price = AverageIncrement($market->price, $ticker->bid);
	$market->pricetime = time();
	$market->save();

	$apims = round((microtime(true) - $t1)*1000,3);
	user()->setFlash('message', "$exchange $symbol price updated in $apims ms");

	return true;
}
