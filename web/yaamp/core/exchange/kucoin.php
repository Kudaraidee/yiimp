<?php

// KuCoin REST API v2 (Classic/Spot). The retired v1 API is unavailable.
// https://www.kucoin.com/docs-new/rest/spot-trading/market-data/get-all-tickers

function kucoin_result_valid($obj, $method='')
{
	if (!is_object($obj) || !isset($obj->data)) return false;
	if (isset($obj->code) && (string) $obj->code !== '200000') return false;
	return true;
}

// https://api.kucoin.com/api/v1/symbols?market=BTC

function kucoin_api_query($method, $params='', $returnType='object')
{
	$exchange = 'kucoin';
	$url = "https://api.kucoin.com/api/v1/".ltrim($method, '/');
	if (!empty($params))
		$url .= "?$params";

	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
	curl_setopt($ch, CURLOPT_TIMEOUT, 30);
	curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; KuCoin API PHP client; '.php_uname('s').'; PHP/'.phpversion().')');
	curl_setopt($ch, CURLOPT_ENCODING , '');

	$res = curl_exec($ch);
	if($res === false) {
		$e = curl_error($ch);
		debuglog("$exchange: $method $e");
		curl_close($ch);
		return false;
	}

	if ($returnType == 'object')
		$ret = json_decode($res);
	else
		$ret = json_decode($res,true);

	if(!is_object($ret) && !is_array($ret)) {
		$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		debuglog("$exchange: $method failed ($status) ".strip_data($res));
	}
	curl_close($ch);
	return $ret;
}

// https://www.kucoin.com/docs-new/authentication
// $endpoint is a full v2 path such as /api/v1/accounts

function kucoin_api_user($endpoint, $params=NULL, $httpMethod='GET')
{
	$exchange = 'kucoin';
	require_once('/etc/yiimp/keys.php');
	if (!defined('EXCH_KUCOIN_KEY')) define('EXCH_KUCOIN_KEY', '');
	if (!defined('EXCH_KUCOIN_SECRET')) define('EXCH_KUCOIN_SECRET', '');
	if (!defined('EXCH_KUCOIN_PASSPHRASE')) define('EXCH_KUCOIN_PASSPHRASE', '');
	if (!defined('EXCH_KUCOIN_KEY_VERSION')) define('EXCH_KUCOIN_KEY_VERSION', '2');

	if (empty(EXCH_KUCOIN_KEY) || empty(EXCH_KUCOIN_SECRET) || empty(EXCH_KUCOIN_PASSPHRASE)) return false;

	$api_host = 'https://api.kucoin.com';
	$params = empty($params) ? array() : $params;
	$httpMethod = strtoupper($httpMethod);
	$body = '';

	if (!empty($params)) {
		if ($httpMethod == 'POST') {
			$body = json_encode($params, JSON_UNESCAPED_SLASHES);
		} else {
			$endpoint .= (strpos($endpoint, '?') === false ? '?' : '&').http_build_query($params);
		}
	}

	$timestamp = (string) (int) (microtime(true) * 1000);
	$toSign = $timestamp.$httpMethod.$endpoint.$body;
	$sign = base64_encode(hash_hmac('sha256', $toSign, EXCH_KUCOIN_SECRET, true));
	$pass = base64_encode(hash_hmac('sha256', EXCH_KUCOIN_PASSPHRASE, EXCH_KUCOIN_SECRET, true));

	$headers = array(
		'Content-Type: application/json',
		'KC-API-KEY: '.EXCH_KUCOIN_KEY,
		'KC-API-SIGN: '.$sign,
		'KC-API-TIMESTAMP: '.$timestamp,
		'KC-API-PASSPHRASE: '.$pass,
		'KC-API-KEY-VERSION: '.EXCH_KUCOIN_KEY_VERSION,
	);

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $api_host.$endpoint);
	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
	if ($httpMethod == 'POST') {
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
	} else if ($httpMethod == 'DELETE') {
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
		if ($body !== '') curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
	}
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
	curl_setopt($ch, CURLOPT_TIMEOUT, 30);
	curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; KuCoin API PHP client; '.php_uname('s').'; PHP/'.phpversion().')');
	curl_setopt($ch, CURLOPT_ENCODING , '');

	$res = curl_exec($ch);
	if($res === false) {
		$e = curl_error($ch);
		debuglog("$exchange: $endpoint $e");
		curl_close($ch);
		return false;
	}

	$result = json_decode($res);
	if(!is_object($result) && !is_array($result)) {
		$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		debuglog("$exchange: $endpoint failed ($status) ".strip_data($res));
	}

	curl_close($ch);
	return $result;
}

//////////////////////////////////////////////////////////////////////////////////////////////////////////////

// manual update of one market
function kucoin_update_market($market)
{
	$exchange = 'kucoin';
	if (is_string($market))
	{
		$symbol = $market;
		$coin = getdbosql('db_coins', "symbol=:sym", array(':sym'=>$symbol));
		if(!$coin) return false;
		$pair = $symbol.'-BTC';
		$market = getdbosql('db_markets', "coinid={$coin->id} AND name='$exchange'");
		if(!$market) return false;

	} else if (is_object($market)) {

		$coin = getdbo('db_coins', $market->coinid);
		if(!$coin) return false;
		$symbol = $coin->getOfficialSymbol();
		$pair = $symbol.'-BTC';
		if (!empty($market->base_coin)) $pair = $symbol.'-'.$market->base_coin;
	}

	$t1 = microtime(true);
	// v1 "$pair/open/tick" is gone; v2 market/stats exposes buy and sell.
	$query = kucoin_api_query('market/stats', 'symbol='.urlencode($pair));
	if(!kucoin_result_valid($query)) return false;
	$ticker = $query->data;

	$price2 = ((double) $ticker->buy + (double)$ticker->sell)/2;
	$market->price2 = AverageIncrement($market->price2, $price2);
	$market->price = AverageIncrement($market->price, $ticker->buy);
	$market->pricetime = time();
	$market->save();

	$apims = round((microtime(true) - $t1)*1000,3);
	user()->setFlash('message', "$exchange $symbol price updated in $apims ms");

	return true;
}
