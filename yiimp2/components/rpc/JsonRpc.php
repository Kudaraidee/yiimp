<?php
namespace app\components\rpc;

/**
 * Thrown when a JSON-RPC server returns an error response or is unreachable.
 */
class RpcException extends \Exception
{
    public function __toString(): string
    {
        $prefix = $this->code > 0 ? "[{$this->code}]: " : '';
        return 'RPC: ' . trim($prefix . $this->message);
    }
}

/**
 * Minimal JSON-RPC 2.0 HTTP client used as the base for EthereumRPC.
 */
class JsonRpc
{
    protected string $host;
    protected int $port;
    protected string $version;
    protected int $id = 0;

    public ?string $error = null;

    public function __construct(string $host, int $port, string $version = '2.0')
    {
        $this->host    = $host;
        $this->port    = $port;
        $this->version = $version;
    }

    /**
     * Send a JSON-RPC request and return the decoded response object.
     *
     * @throws RpcException on network failure or server-side error
     */
    public function request(string $method, array $params = []): object
    {
        $data = [
            'jsonrpc' => $this->version,
            'id'      => $this->id++,
            'method'  => $method,
            'params'  => $params,
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $this->host,
            CURLOPT_PORT           => $this->port,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($data),
        ]);

        $ret = curl_exec($ch);
        curl_close($ch);

        if ($ret === false) {
            throw new RpcException('Server did not respond');
        }

        $formatted = json_decode($ret);
        if (isset($formatted->error)) {
            $err     = $formatted->error;
            $message = $err->message ?? json_encode($err);
            $code    = (int) ($err->code ?? 0);
            $this->error = $message;
            throw new RpcException($message, $code);
        }

        return $formatted;
    }
}
