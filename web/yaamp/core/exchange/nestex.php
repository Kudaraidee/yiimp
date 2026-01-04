<?php
//info from public api
function nestex_api_query($method = 'cg/tickers', $params = '', $returnType = 'array')
{
	$uri = "https://trade.nestex.one/api/{$method}";
	if (!empty($params)) {
		$uri .= "?{$params}";
	}

	$ch = curl_init($uri);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
	curl_setopt($ch, CURLOPT_TIMEOUT, 30);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

	$execResult = strip_tags(curl_exec($ch));
	if ($returnType == 'object') {
		$ret = json_decode($execResult);
	} else {
		$ret = json_decode($execResult, true); // default
	}

	return $ret;
}


//////////////////////////////////////////////////////////////////////////////////////////////////////////////
// for private api once available 

function nestex_api_user($method, $url_params = [], $request_method = 'GET', $returnType = 'array') {

	require_once('/etc/yiimp/keys.php');
	if (!defined('EXCH_NESTEX_SECRET') || empty(EXCH_NESTEX_SECRET)) return false;
	if (!defined('EXCH_NESTEX_KEY') || empty(EXCH_NESTEX_KEY)) return false;
	if (!is_array($url_params)) return false;

	$base = 'https://trade.nestex.one'; 
	$path = '/api/v2/' . $method;

	$request = '';

	$auth_info = [
		"apikey" =>  EXCH_NESTEX_KEY,
		"apisecret" => EXCH_NESTEX_SECRET
	];

	$request_params = array_merge($auth_info, $url_params);
	ksort($request_params);
	$request = http_build_query($request_params, '', '&');

	if ($request_method == 'POST') {
		$uri = $base . $path;
		$payload = json_encode($request_params);
	} else {
		$uri = $base . $path . (!empty($request) ? '?' . $request : '');
		$payload = '';
	}

	$http_headers = [
		'Content-Type: application/json',
	];

	$http_request = new cHTTP();
	$http_request->setURL($uri);
	$http_request->setHeaders($http_headers);

	if ($request_method == 'POST') {
		$http_request->setPostfields($payload);
	}

	$http_request->setUserAgentString('Mozilla/4.0 (compatible; nestex API client; ' . php_uname('s') . '; PHP/' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . ')');
	$http_request->setFailOnError(false);

	$data = $http_request->execRequest();

	if ($http_request->fResult['HTTP_Code'] == 429) {
		sleep(5);
		$data = $http_request->execRequest();
	}

	$status = $http_request->fResult['HTTP_Code'];

	if ($status >= 300) {
		debuglog("nestex: $method failed ($status) " . strip_data($data));
		return false;
	}

	if ($returnType == 'object') {
		return json_decode($data);
	} else {
		return json_decode($data, true);
	}
}

