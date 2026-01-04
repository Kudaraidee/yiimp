<?php

namespace app\components;

use Yii;
use yii\base\Component;
use app\components\rpc\WalletRPC;
use app\models\Coins;

/**
 * RPC Client component for coin daemon communication
 */
class RpcClient extends Component
{
    /**
     * Get RPC connection for a coin
     * 
     * @param Coins|int $coin Coin model or coin ID
     * @return WalletRPC|null
     */
    public function getConnection($coin)
    {
        if (is_int($coin)) {
            $coin = Coins::findOne($coin);
        }
        
        if (!$coin) {
            Yii::error([
                'message' => 'Coin not found',
                'coin_id' => is_int($coin) ? $coin : null,
            ], 'app\components\RpcClient');
            return null;
        }
        
        try {
            return new WalletRPC($coin);
        } catch (\Exception $e) {
            Yii::error([
                'message' => 'Failed to connect to coin daemon',
                'coin' => $coin->symbol,
                'coin_id' => $coin->id,
                'rpc_host' => $coin->rpchost ?? 'not set',
                'rpc_port' => $coin->rpcport ?? 'not set',
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
            ], 'app\components\RpcClient');
            return null;
        }
    }
    
    /**
     * Execute RPC call with error handling and logging
     * 
     * @param Coins|int $coin Coin model or coin ID
     * @param string $method RPC method name
     * @param array $params RPC method parameters
     * @return mixed|null
     */
    public function call($coin, $method, $params = [])
    {
        $rpc = $this->getConnection($coin);
        
        if (!$rpc) {
            return null;
        }
        
        if (is_int($coin)) {
            $coin = Coins::findOne($coin);
        }
        
        $startTime = microtime(true);
        
        try {
            $result = $rpc->$method(...$params);
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            
            // Log successful RPC calls at info level
            Yii::info([
                'coin' => $coin->symbol,
                'method' => $method,
                'params' => $params,
                'duration_ms' => $duration,
                'status' => 'success'
            ], 'app\components\RpcClient');
            
            return $result;
        } catch (\Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            
            // Log failed RPC calls at error level with full details
            Yii::error([
                'coin' => $coin->symbol,
                'method' => $method,
                'params' => $params,
                'duration_ms' => $duration,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'exception_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => YII_DEBUG ? $e->getTraceAsString() : null,
            ], 'app\components\RpcClient');
            
            return null;
        }
    }
    
    /**
     * Get block information
     * 
     * @param Coins|int $coin
     * @param string $hash Block hash
     * @return array|null
     */
    public function getBlock($coin, $hash)
    {
        return $this->call($coin, 'getblock', [$hash]);
    }
    
    /**
     * Get transaction information
     * 
     * @param Coins|int $coin
     * @param string $txid Transaction ID
     * @return array|null
     */
    public function getTransaction($coin, $txid)
    {
        return $this->call($coin, 'getrawtransaction', [$txid, true]);
    }
    
    /**
     * Get blockchain info
     * 
     * @param Coins|int $coin
     * @return array|null
     */
    public function getBlockchainInfo($coin)
    {
        return $this->call($coin, 'getblockchaininfo', []);
    }
    
    /**
     * Get mining info
     * 
     * @param Coins|int $coin
     * @return array|null
     */
    public function getMiningInfo($coin)
    {
        return $this->call($coin, 'getmininginfo', []);
    }
    
    /**
     * Get peer info
     * 
     * @param Coins|int $coin
     * @return array|null
     */
    public function getPeerInfo($coin)
    {
        return $this->call($coin, 'getpeerinfo', []);
    }
    
    /**
     * Get wallet info
     * 
     * @param Coins|int $coin
     * @return array|null
     */
    public function getWalletInfo($coin)
    {
        return $this->call($coin, 'getwalletinfo', []);
    }
    
    /**
     * Execute arbitrary RPC query (alias for call method)
     * 
     * @param Coins|int $coin Coin model or coin ID
     * @param string $method RPC method name
     * @param array $params RPC method parameters
     * @return mixed|null
     */
    public function query($coin, $method, $params = [])
    {
        return $this->call($coin, $method, $params);
    }
    
    /**
     * Validate RPC connectivity for a coin
     * 
     * @param Coins|int $coin Coin model or coin ID
     * @return array ['success' => bool, 'error' => string|null, 'info' => array|null]
     */
    public function validateConnection($coin)
    {
        if (is_int($coin)) {
            $coin = Coins::findOne($coin);
        }
        
        if (!$coin) {
            return [
                'success' => false,
                'error' => 'Coin not found',
                'info' => null,
            ];
        }
        
        // Check required RPC fields
        $missingFields = [];
        if (empty($coin->rpchost)) {
            $missingFields[] = 'rpchost';
        }
        if (empty($coin->rpcport)) {
            $missingFields[] = 'rpcport';
        }
        if (empty($coin->rpcuser)) {
            $missingFields[] = 'rpcuser';
        }
        if (empty($coin->rpcpasswd)) {
            $missingFields[] = 'rpcpasswd';
        }
        
        if (!empty($missingFields)) {
            return [
                'success' => false,
                'error' => 'Missing required RPC fields: ' . implode(', ', $missingFields),
                'info' => null,
            ];
        }
        
        // Try to connect and get basic info
        try {
            $info = $this->call($coin, 'getinfo', []);
            
            if ($info === null) {
                // Try alternative method for newer daemons
                $info = $this->call($coin, 'getblockchaininfo', []);
            }
            
            if ($info === null) {
                return [
                    'success' => false,
                    'error' => 'Unable to connect to RPC daemon. Check logs for details.',
                    'info' => null,
                ];
            }
            
            return [
                'success' => true,
                'error' => null,
                'info' => $info,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'info' => null,
            ];
        }
    }
}
