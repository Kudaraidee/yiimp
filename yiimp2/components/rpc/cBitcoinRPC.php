<?php
namespace app\components\rpc;

use yii;
use app\components\rpc\iRPCConnector;

class cBitcoinRPC implements iRPCConnector
{
		// Configuration options
	private $username;
	private $password;
	private $proto;
	private $host;
	private $port;
	private $url;
	private $CACertificate;

	// Information and debugging
	public $status;
	public $error;
	public $raw_response;
	public $response;

	private $id = 0;

	/**
	 * @param string $username
	 * @param string $password
	 * @param string $host
	 * @param int $port
	 * @param string $proto
	 * @param string $url
	 */
	function __construct($username, $password, $host = 'localhost', $port = 8332, $url = null) {
		$this->username      = $username;
		$this->password      = $password;
		$this->host          = $host;
		$this->port          = $port;
		$this->url           = $url;

		// Set some defaults
		$this->proto         = 'http';
		$this->CACertificate = null;

		// Hack to be able to connect to wallets over TLS
		// sample coin HOST:  https://dcrd@127.0.0.1
		// where dcrd is the certificate filename.
		if (strstr($host, 'https://') !== false) {
			$host = substr($host, strlen('https://'));
			if (strstr($host, '@') !== false) {
				$parts = explode('@', $host);
				$cert = $parts[0];
				$host = $parts[1];
			} else {
				$cert = 'yiimp';
			}
			$this->host = $host;
			$this->setSSL("/usr/share/ca-certificates/$cert.crt");
		}
	}

	/**
	 * @param string|null $certificate
	 */
	function setSSL($certificate = null) {
		$this->proto         = 'https'; // force HTTPS
		$this->CACertificate = $certificate;
	}

	function __call($method, $params)
	{
		$this->status       = null;
		$this->error        = null;
		$this->raw_response = null;
		$this->response     = null;

		// The ID should be unique for each call
		$this->id++;

		if (stripos($method, 'dump') !== false || stripos($method, 'backupwallet') !== false) {
			$this->error = "$method method is not authorized!";
			return FALSE;
		}

		// If no parameters are passed, this will be an empty array
		if($method == 'getblocktemplate')
		{
			$param = isset($params[0]) ? $params[0] : '';
			$request = "{\"jsonrpc\":\"2.0\",\"method\":\"$method\",\"params\":[$param],\"id\":$this->id}";
		//  debuglog($request);
		}

		else
		{
			$params = array_values($params);

			// Build the request, it's ok that params might have any empty array
			$request = json_encode(array(
				'jsonrpc' => '2.0',
				'method' => $method,
				'params' => $params,
				'id'     => $this->id
			));
		}

		// Build the cURL session
		$curl    = curl_init("{$this->proto}://{$this->username}:{$this->password}@{$this->host}:{$this->port}/{$this->url}");
		$options = array(
			CURLOPT_CONNECTTIMEOUT => 20,
			CURLOPT_TIMEOUT        => 30,
			CURLOPT_RETURNTRANSFER => TRUE,
			CURLOPT_FOLLOWLOCATION => TRUE,
			CURLOPT_MAXREDIRS      => 10,
			CURLOPT_HTTPHEADER     => array('Content-type: application/json'),
			CURLOPT_POST           => TRUE,
			CURLOPT_POSTFIELDS     => $request
		);

		if ($this->proto == 'https') {
			// If the CA Certificate was specified we change CURL to look for it
			if ($this->CACertificate != null) {
				$options[CURLOPT_CAINFO] = $this->CACertificate;
				$options[CURLOPT_CAPATH] = DIRNAME($this->CACertificate);
				$options[CURLOPT_SSLVERSION] = 1; // TLSv1
			}
			else {
				// If not we need to assume the SSL cannot be verified so we set this flag to FALSE to allow the connection
				$options[CURLOPT_SSL_VERIFYPEER] = FALSE;
			}
		}

		curl_setopt_array($curl, $options);

		// Execute the request and decode to an array
		$this->raw_response = curl_exec($curl);
//      debuglog($this->raw_response);
		$this->response     = json_decode($this->raw_response, TRUE);

		// If the status is not 200, something is wrong
		$this->status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

		// If there was no error, this will be an empty string
		$curl_error = curl_error($curl);

		curl_close($curl);
//      debuglog($this->response);

		if (!empty($curl_error)) {
			$this->error = $curl_error;
		}

		if (isset($this->response['error']) && $this->response['error']) {
			// store wallet error
			$code = Yii::$app->ConversionUtils->arraySafeVal($this->response['error'], 'code', '');
			$message = Yii::$app->ConversionUtils->arraySafeVal($this->response['error'], 'message', '');
			$this->error = "error $code: ".strtolower($message);
		}
		elseif ($this->status != 200) {
			// If bitcoind didn't return a nice error message, we need to make our own
			switch ($this->status) {
				case 400:
					$this->error = 'HTTP_BAD_REQUEST';
					break;
				case 401:
					$this->error = 'HTTP_UNAUTHORIZED';
					break;
				case 403:
					$this->error = 'HTTP_FORBIDDEN';
					break;
				case 404:
					$this->error = 'HTTP_NOT_FOUND';
					break;
			}
		}

		if ($this->error) {
			return FALSE;
		}

		if (is_null($this->response) || (!is_array($this->response)))
		{
			return FALSE;
		}

		return $this->response['result'];
	}
}