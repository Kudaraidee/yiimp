<?php
function exbitron_api_query($method, $params='', $returnType='object')
{
	$uri = "https://api.exbitron.com/api/v1/{$method}";

    if (!empty($params)) $uri .= "?{$params}";
	$ch = curl_init($uri);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
	curl_setopt($ch, CURLOPT_TIMEOUT, 30);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	$execResult = strip_tags(curl_exec($ch));
	if ($returnType == 'object')
		$ret = json_decode($execResult);
	else
		$ret = json_decode($execResult,true);
	return $ret;
}

// just a template, needs to modify to work with api
function exbitron_api_user($method, $url_params = [], $request_method='GET', $returnType='object') {
	
	require_once('/etc/yiimp/keys.php');
	if (empty(EXCH_EXBITRON_SECRET)) return false;

	$base = 'https://api.exbitron.com'; $path = '/api/v1/'.$method;

	$request = '';

	if (is_array($url_params)) {
		ksort($url_params);
		$request = http_build_query($url_params, '', '&');
	} elseif (is_string($url_params)) {
		$request = $url_params;
	}

	$payload = '';
	if ($request_method == 'POST') {
		$uri = $base.$path;
		$payload = $request;
	}
	else {
		if ($request != '')
			$uri = $base.$path.'?'.$request;
		else
			$uri = $base.$path;
	}

	// debuglog('exbitron-api: '.var_export($message, true));
	$http_headers = [
				'Content-Type: application/json',
				'Authorization: Bearer '.EXCH_EXBITRON_SECRET
	];

	$http_request = new cHTTP();
	$http_request->setURL($uri);
	$http_request->setHeaders($http_headers);

	if ($request_method == 'POST') {
		$http_request->setPostfields($payload);
	}
	$http_request->setUserAgentString('Mozilla/4.0 (compatible; exbitron API client; '.php_uname('s').'; PHP/'.PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION.')');
	$http_request->setFailOnError(false);
	$data = $http_request->execRequest();
	if($http_request->fResult['HTTP_Code'] == 429) { // too many requests, try again
		sleep(5);
		$data = $http_request->execRequest();
	}

	if ($returnType == 'object')
		$res = json_decode($data);
	else
		$res = json_decode($data,true);
	
	$status = $http_request->fResult['HTTP_Code'];
	
	if($status >= 300) {
		debuglog("exbitron: $method failed ($status) ".strip_data($data));
		$res = false;
	}

	return $res;
}

//////////////////////////////////////////////////////////////////////////////////////////////////////////////
