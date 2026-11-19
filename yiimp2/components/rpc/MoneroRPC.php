<?php
namespace app\components\rpc;

use app\components\rpc\iRPCConnector;

/**
 * Monero / CryptoNote RPC client — updated for Monero v0.18+ (Fluorine Fermi).
 *
 * Endpoint routing (as of current Monero daemon):
 *   /json_rpc  — JSON-RPC 2.0 POST for all named daemon methods
 *   /get_height, /getheight  — plain HTTP GET (height only)
 *   /get_transactions, /send_raw_transaction — plain HTTP POST
 *   /start_mining, /stop_mining              — plain HTTP POST
 *   /*.bin                                   — binary HTTP POST
 *
 * Deprecated plain-HTTP /getinfo is intentionally NOT used here; get_info
 * is routed through /json_rpc as the canonical form.
 *
 * All wallet RPC calls go to the wallet daemon (separate port, typically
 * 18083) and always use /json_rpc.
 */
class MoneroRPC implements iRPCConnector
{
    private string $proto   = 'http';
    private string $host;
    private int    $port;
    private string $rpcPath = 'json_rpc';
    private string $username;
    private string $password;
    private int    $id = 0;

    public ?int    $status       = null;
    public ?string $error        = null;
    public ?string $raw_response = null;
    public mixed   $response     = null;

    /**
     * Maps legacy method names to their current canonical JSON-RPC equivalents.
     * Aliases are still accepted by the daemon but this ensures we always send
     * the canonical form and are not affected if aliases are ever removed.
     */
    private const METHOD_ALIASES = [
        // Daemon /json_rpc aliases
        'getinfo'                  => 'get_info',
        'getblocktemplate'         => 'get_block_template',
        'getlastblockheader'       => 'get_last_block_header',
        'getblockcount'            => 'get_block_count',
        'on_getblockhash'          => 'on_get_block_hash',
        'submitblock'              => 'submit_block',
        'getblockheaderbyhash'     => 'get_block_header_by_hash',
        'getblockheaderbyheight'   => 'get_block_header_by_height',
        'getblockheadersrange'     => 'get_block_headers_range',
        'getblock'                 => 'get_block',
        // Wallet /json_rpc aliases
        'getbalance'               => 'get_balance',
        'getaddress'               => 'get_address',
        'getheight'                => 'get_height',   // wallet-only — daemon uses plain HTTP
    ];

    /**
     * Methods that use plain-HTTP GET (daemon only).
     * Intentionally minimal: only /get_height remains as a plain-GET endpoint
     * that is still the primary form. All other info methods use /json_rpc.
     */
    private const GET_ENDPOINTS = [
        'get_height',
    ];

    /**
     * Methods that use plain-HTTP POST (daemon only).
     */
    private const POST_ENDPOINTS = [
        'get_transactions',   'gettransactions',
        'send_raw_transaction', 'sendrawtransaction',
        'start_mining',
        'stop_mining',
    ];

    /**
     * Binary methods — plain-HTTP POST with .bin suffix path.
     */
    private const BINARY_METHODS = [
        'get_blocks'            => 'get_blocks.bin',
        'getblocks'             => 'get_blocks.bin',
        'get_o_indexes'         => 'get_o_indexes.bin',
        'get_outs'              => 'get_outs.bin',
        'get_output_distribution' => 'get_output_distribution.bin',
    ];

    /**
     * Methods that receive named (associative) params wrapped in a single array element.
     * The inner array/object is unwrapped before sending.
     */
    private const NAMED_PARAM_METHODS = [
        'get_block_template', 'getblocktemplate',
        'get_payments',
        'incoming_transfers',
    ];

    /**
     * Transfer methods — params may arrive as a JSON string or single-element array.
     */
    private const TRANSFER_METHODS = [
        'transfer', 'transfer_split', 'transfer_original',
    ];

    public function __construct(
        string $host     = 'localhost',
        int    $port     = 18081,
        string $username = '',
        string $password = ''
    ) {
        $this->host     = $host;
        $this->port     = $port;
        $this->username = $username;
        $this->password = $password;
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

    public function __call(string $method, array $params = []): mixed
    {
        $this->status       = null;
        $this->error        = null;
        $this->raw_response = null;
        $this->response     = null;

        // --- Binary methods (plain-HTTP POST, *.bin path) ---
        if (isset(self::BINARY_METHODS[$method])) {
            return $this->rpcPost(self::BINARY_METHODS[$method], $params);
        }

        // --- Plain-HTTP POST methods ---
        if (in_array($method, self::POST_ENDPOINTS, true)) {
            return $this->rpcPost($method, $params);
        }

        // --- Plain-HTTP GET methods ---
        // Only /get_height remains as a legitimate GET endpoint.
        // Note: legacy callers may pass 'getheight'; we normalise it here before
        // the alias map runs so it routes to the GET path, not JSON-RPC.
        $getMethod = ($method === 'getheight') ? 'get_height' : $method;
        if (in_array($getMethod, self::GET_ENDPOINTS, true)) {
            return $this->rpcGet($getMethod, $params);
        }

        // --- Normalise old method names to canonical JSON-RPC names ---
        if (isset(self::METHOD_ALIASES[$method])) {
            $method = self::METHOD_ALIASES[$method];
        }

        // --- Unwrap named-param methods ---
        if (in_array($method, self::NAMED_PARAM_METHODS, true)) {
            if (count($params) === 1) {
                $pop = array_shift($params);
                if (is_object($pop) || is_array($pop)) {
                    $params = (object) $pop;
                }
            }
        }

        // --- Unwrap transfer param bundles ---
        if (in_array($method, self::TRANSFER_METHODS, true)) {
            if (isset($params[0]) && is_string($params[0])) {
                $params = [json_decode($params[0])];
            } elseif (count($params) === 1) {
                $pop = array_shift($params);
                if (is_object($pop) || is_array($pop)) {
                    $params = (object) $pop;
                }
            }
        }

        // --- Default: JSON-RPC 2.0 via /json_rpc ---
        return $this->jsonRpc($method, $params);
    }

    // JSON-RPC 2.0 dispatch ---------------------------------------------------

    private function jsonRpc(string $method, mixed $params): mixed
    {
        $data = [
            'jsonrpc' => '2.0',
            'id'      => $this->id++,
            'method'  => $method,
            'params'  => $params,
        ];

        $curl = curl_init("{$this->proto}://{$this->host}:{$this->port}/{$this->rpcPath}");
        curl_setopt_array($curl, [
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS     => json_encode($data),
        ]);

        $this->raw_response = curl_exec($curl);
        $this->response     = json_decode($this->raw_response, true);
        $this->status       = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlError          = curl_error($curl);
        curl_close($curl);

        if (!empty($curlError)) {
            $this->error = $curlError;
        } elseif (!empty($this->response['error'])) {
            $this->error = strtolower($this->response['error']['message'] ?? '');
        } elseif ($this->status !== 200) {
            $this->error = $this->httpStatusString($this->status);
        }

        if ($this->error) {
            return false;
        }

        return $this->response['result'] ?? null;
    }

    // Plain-HTTP GET ----------------------------------------------------------

    private function rpcGet(string $path, array $params = []): mixed
    {
        $url = "{$this->proto}://{$this->host}:{$this->port}/{$path}";
        if (!empty($params)) {
            $url .= '?ts=' . time();
            foreach ($params as $key => $val) {
                $url .= '&' . urlencode((string) $key) . '=' . urlencode((string) $val);
            }
        }

        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_HTTPGET        => true,
        ]);

        $this->raw_response = curl_exec($curl);
        $this->status       = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlError          = curl_error($curl);
        curl_close($curl);

        if (!empty($curlError)) {
            $this->error = $curlError;
        } elseif ($this->status !== 200) {
            $this->error = $this->httpStatusString($this->status);
        } else {
            $this->response = json_decode($this->raw_response, true);
        }

        return $this->response;
    }

    // Plain-HTTP POST ---------------------------------------------------------

    private function rpcPost(string $path, array $params = []): mixed
    {
        // Normalise the path (keep legacy aliases working at the network level)
        $pathAliases = [
            'gettransactions'    => 'get_transactions',
            'sendrawtransaction' => 'send_raw_transaction',
        ];
        $path = $pathAliases[$path] ?? $path;

        $curl = curl_init("{$this->proto}://{$this->host}:{$this->port}/{$path}");
        curl_setopt_array($curl, [
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        ]);

        $body = empty($params) ? '{}' : json_encode(array_pop($params) ?: (object) []);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $body);

        $this->raw_response = curl_exec($curl);
        $this->status       = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlError          = curl_error($curl);
        curl_close($curl);

        if (!empty($curlError)) {
            $this->error = $curlError;
        } elseif ($this->status !== 200) {
            $this->error = $this->httpStatusString($this->status);
        } else {
            $this->response = json_decode($this->raw_response, true);
        }

        return $this->response;
    }

    private function httpStatusString(?int $status): ?string
    {
        return match ($status) {
            400 => 'HTTP_BAD_REQUEST',
            401 => 'HTTP_UNAUTHORIZED',
            403 => 'HTTP_FORBIDDEN',
            404 => 'HTTP_NOT_FOUND',
            default => null,
        };
    }
}
