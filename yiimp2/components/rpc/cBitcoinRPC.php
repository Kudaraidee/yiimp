<?php
namespace app\components\rpc;

use app\components\rpc\iRPCConnector;

/**
 * Bitcoin-compatible JSON-RPC 1.0 client (port of EasyBitcoin-PHP).
 * Supports HTTP and TLS connections, including wallets served over HTTPS.
 */
class cBitcoinRPC implements iRPCConnector
{
    private string $username;
    private string $password;
    private string $proto  = 'http';
    private string $host;
    private int $port;
    private ?string $url;
    private ?string $CACertificate = null;

    public ?int $status       = null;
    public ?string $error     = null;
    public ?string $raw_response = null;
    public mixed $response    = null;

    private int $id = 0;

    public function __construct(
        string $username,
        string $password,
        string $host = 'localhost',
        int    $port = 8332,
        ?string $url = null
    ) {
        $this->username = $username;
        $this->password = $password;
        $this->host     = $host;
        $this->port     = $port;
        $this->url      = $url;

        // Handle TLS wallets specified as "https://certname@host"
        if (str_contains($host, 'https://')) {
            $host = substr($host, strlen('https://'));
            if (str_contains($host, '@')) {
                [$cert, $host] = explode('@', $host, 2);
            } else {
                $cert = 'yiimp';
            }
            $this->host = $host;
            $this->setSSL("/usr/share/ca-certificates/{$cert}.crt");
        }
    }

    public function setSSL(?string $certificate = null): void
    {
        $this->proto         = 'https';
        $this->CACertificate = $certificate;
    }

    // iRPCConnector -----------------------------------------------------------

    public function call(string $method, array $params = []): mixed
    {
        return $this->__call($method, $params);
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    // Magic dispatch ----------------------------------------------------------

    public function __call(string $method, array $params): mixed
    {
        $this->status       = null;
        $this->error        = null;
        $this->raw_response = null;
        $this->response     = null;

        $this->id++;

        if (stripos($method, 'dump') !== false || stripos($method, 'backupwallet') !== false) {
            $this->error = "{$method} method is not authorized!";
            return false;
        }

        if ($method === 'getblocktemplate') {
            $param   = $params[0] ?? '';
            $request = "{\"method\":\"$method\",\"params\":[$param],\"id\":{$this->id}}";
        } else {
            $params  = array_values($params);
            $request = json_encode(['method' => $method, 'params' => $params, 'id' => $this->id]);
        }

        $curl    = curl_init("{$this->proto}://{$this->username}:{$this->password}@{$this->host}:{$this->port}/{$this->url}");
        $options = [
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_HTTPHEADER     => ['Content-type: application/json'],
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $request,
        ];

        if ($this->proto === 'https') {
            if ($this->CACertificate !== null) {
                $options[CURLOPT_CAINFO]    = $this->CACertificate;
                $options[CURLOPT_CAPATH]    = dirname($this->CACertificate);
                $options[CURLOPT_SSLVERSION] = CURL_SSLVERSION_TLSv1;
            } else {
                $options[CURLOPT_SSL_VERIFYPEER] = false;
            }
        }

        curl_setopt_array($curl, $options);

        $this->raw_response = curl_exec($curl);
        $this->response     = json_decode($this->raw_response, true);
        $this->status       = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curl_error         = curl_error($curl);
        curl_close($curl);

        if (!empty($curl_error)) {
            $this->error = $curl_error;
        }

        if (isset($this->response['error']) && $this->response['error']) {
            $code          = $this->response['error']['code'] ?? '';
            $message       = $this->response['error']['message'] ?? '';
            $this->error   = "error {$code}: " . strtolower($message);
        } elseif ($this->status !== 200) {
            $this->error = match ($this->status) {
                400 => 'HTTP_BAD_REQUEST',
                401 => 'HTTP_UNAUTHORIZED',
                403 => 'HTTP_FORBIDDEN',
                404 => 'HTTP_NOT_FOUND',
                default => null,
            };
        }

        if ($this->error) {
            return false;
        }

        if (!is_array($this->response)) {
            return false;
        }

        return $this->response['result'];
    }

    /**
     * Send a pre-built JSON string directly to the daemon (used by the admin RPC console).
     */
    public function requestJson(string $json): mixed
    {
        $this->status       = null;
        $this->error        = null;
        $this->response     = null;
        $this->raw_response = null;

        $data     = json_decode($json);
        $data->id = $this->id++;

        $ch      = curl_init("{$this->proto}://{$this->username}:{$this->password}@{$this->host}:{$this->port}/{$this->url}");
        $options = [
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_HTTPHEADER     => ['Content-type: application/json'],
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($data),
        ];
        curl_setopt_array($ch, $options);

        $this->raw_response = curl_exec($ch);
        $this->response     = json_decode($this->raw_response, true);
        $this->status       = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error         = curl_error($ch);
        curl_close($ch);

        if (!empty($curl_error)) {
            $this->error = $curl_error;
        }

        if (isset($this->response['error']) && $this->response['error']) {
            $this->error = strtolower($this->response['error']['message'] ?? '');
        } elseif ($this->status !== 200) {
            $this->error = match ($this->status) {
                400 => 'HTTP_BAD_REQUEST',
                401 => 'HTTP_UNAUTHORIZED',
                403 => 'HTTP_FORBIDDEN',
                404 => 'HTTP_NOT_FOUND',
                default => null,
            };
        }

        if ($this->error) {
            return false;
        }

        return $this->response['result'];
    }
}
