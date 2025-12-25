<?php

namespace app\components;

use Yii;
use yii\base\Component;
use app\models\Coins;

/**
 * ExplorerUtils component for blockchain exploration utilities
 */
class ExplorerUtils extends Component
{
    /**
     * Get block details from RPC
     * 
     * @param Coins $coin Coin model
     * @param string|null $hash Block hash
     * @param int|null $height Block height
     * @return array|null Block details or null on error
     */
    public function getBlockDetails($coin, $hash = null, $height = null)
    {
        $rpc = Yii::$app->RpcClient->getConnection($coin);
        
        if (!$rpc) {
            Yii::error("ExplorerUtils: Failed to connect to {$coin->symbol} RPC", __METHOD__);
            return null;
        }
        
        try {
            // If height is provided, get hash first
            if ($height !== null && $hash === null) {
                $hash = $rpc->getblockhash($height);
                if (!$hash) {
                    Yii::warning("ExplorerUtils: Block not found at height {$height} for {$coin->symbol}", __METHOD__);
                    return null;
                }
            }
            
            if (empty($hash)) {
                Yii::warning("ExplorerUtils: No hash or height provided for {$coin->symbol}", __METHOD__);
                return null;
            }
            
            // Get block details
            $block = $rpc->getblock($hash);
            
            if (!$block) {
                Yii::warning("ExplorerUtils: Block {$hash} not found for {$coin->symbol}", __METHOD__);
                return null;
            }
            
            // Ensure we have the hash in the result
            if (!isset($block['hash'])) {
                $block['hash'] = $hash;
            }
            
            return $block;
            
        } catch (\Exception $e) {
            Yii::error("ExplorerUtils: Error getting block details for {$coin->symbol}: {$e->getMessage()}", __METHOD__);
            return null;
        }
    }
    
    /**
     * Get transaction details from RPC
     * 
     * @param Coins $coin Coin model
     * @param string $txid Transaction ID
     * @return array|null Transaction details or null on error
     */
    public function getTransactionDetails($coin, $txid)
    {
        $rpc = Yii::$app->RpcClient->getConnection($coin);
        
        if (!$rpc) {
            Yii::error("ExplorerUtils: Failed to connect to {$coin->symbol} RPC", __METHOD__);
            return null;
        }
        
        try {
            // Try getrawtransaction first (with verbose=1)
            $tx = $rpc->getrawtransaction($txid, 1);
            
            if (!$tx) {
                // Fallback to gettransaction for wallet transactions
                $tx = $rpc->gettransaction($txid);
            }
            
            if (!$tx) {
                Yii::warning("ExplorerUtils: Transaction {$txid} not found for {$coin->symbol}", __METHOD__);
                return null;
            }
            
            return $tx;
            
        } catch (\Exception $e) {
            Yii::error("ExplorerUtils: Error getting transaction details for {$coin->symbol}: {$e->getMessage()}", __METHOD__);
            return null;
        }
    }
    
    /**
     * Search for a hash and determine if it's a block or transaction
     * 
     * @param Coins $coin Coin model
     * @param string $query Hash to search for
     * @return string|null 'block', 'transaction', or null if not found
     */
    public function searchHash($coin, $query)
    {
        $rpc = Yii::$app->RpcClient->getConnection($coin);
        
        if (!$rpc) {
            Yii::error("ExplorerUtils: Failed to connect to {$coin->symbol} RPC", __METHOD__);
            return null;
        }
        
        try {
            // First, try as a block hash
            $block = $rpc->getblock($query);
            if ($block) {
                return 'block';
            }
        } catch (\Exception $e) {
            // Not a block, continue
        }
        
        try {
            // Try as a transaction
            $tx = $rpc->getrawtransaction($query, 1);
            if (!$tx) {
                $tx = $rpc->gettransaction($query);
            }
            if ($tx) {
                return 'transaction';
            }
        } catch (\Exception $e) {
            // Not a transaction either
        }
        
        Yii::warning("ExplorerUtils: Hash {$query} not found for {$coin->symbol}", __METHOD__);
        return null;
    }
    
    /**
     * Get peer connection information
     * 
     * @param Coins $coin Coin model
     * @return array|null Array of peer information or null on error
     */
    public function getPeerInfo($coin)
    {
        $rpc = Yii::$app->RpcClient->getConnection($coin);
        
        if (!$rpc) {
            Yii::error("ExplorerUtils: Failed to connect to {$coin->symbol} RPC", __METHOD__);
            return null;
        }
        
        try {
            $peers = $rpc->getpeerinfo();
            
            if (!$peers) {
                Yii::warning("ExplorerUtils: No peer info available for {$coin->symbol}", __METHOD__);
                return [];
            }
            
            return $peers;
            
        } catch (\Exception $e) {
            Yii::error("ExplorerUtils: Error getting peer info for {$coin->symbol}: {$e->getMessage()}", __METHOD__);
            return null;
        }
    }
    
    /**
     * Get blockchain statistics for graphing
     * 
     * @param Coins $coin Coin model
     * @param int $days Number of days to retrieve (default 30)
     * @return array|null Array of statistics or null on error
     */
    public function getBlockchainStats($coin, $days = 30)
    {
        try {
            // Get blockchain info from RPC
            $rpc = Yii::$app->RpcClient->getConnection($coin);
            
            if (!$rpc) {
                Yii::error("ExplorerUtils: Failed to connect to {$coin->symbol} RPC", __METHOD__);
                return null;
            }
            
            $info = $rpc->getblockchaininfo();
            if (!$info) {
                $info = $rpc->getinfo();
            }
            if (!$info) {
                $info = $rpc->getmininginfo();
            }
            
            if (!$info) {
                Yii::warning("ExplorerUtils: No blockchain info available for {$coin->symbol}", __METHOD__);
                return null;
            }
            
            // Get historical data from blocks table
            $startTime = time() - ($days * 24 * 60 * 60);
            
            $blocks = \app\models\Blocks::find()
                ->where(['coinid' => $coin->id])
                ->andWhere(['>=', 'time', $startTime])
                ->orderBy(['time' => SORT_ASC])
                ->all();
            
            $stats = [
                'current' => $info,
                'history' => [],
            ];
            
            // Group blocks by day for graphing
            foreach ($blocks as $block) {
                $day = date('Y-m-d', $block->time);
                
                if (!isset($stats['history'][$day])) {
                    $stats['history'][$day] = [
                        'date' => $day,
                        'blocks' => 0,
                        'difficulty' => [],
                        'hashrate' => [],
                    ];
                }
                
                $stats['history'][$day]['blocks']++;
                $stats['history'][$day]['difficulty'][] = $block->difficulty;
            }
            
            // Calculate averages
            foreach ($stats['history'] as $day => &$data) {
                if (!empty($data['difficulty'])) {
                    $data['avg_difficulty'] = array_sum($data['difficulty']) / count($data['difficulty']);
                }
                unset($data['difficulty']);
            }
            
            return $stats;
            
        } catch (\Exception $e) {
            Yii::error("ExplorerUtils: Error getting blockchain stats for {$coin->symbol}: {$e->getMessage()}", __METHOD__);
            return null;
        }
    }
    
    /**
     * Get recent blocks for a coin
     * 
     * @param Coins $coin Coin model
     * @param int $limit Number of blocks to retrieve (default 20)
     * @return array Array of block information
     */
    public function getRecentBlocks($coin, $limit = 20)
    {
        try {
            $blocks = \app\models\Blocks::find()
                ->where(['coinid' => $coin->id])
                ->orderBy(['height' => SORT_DESC])
                ->limit($limit)
                ->all();
            
            return $blocks;
            
        } catch (\Exception $e) {
            Yii::error("ExplorerUtils: Error getting recent blocks for {$coin->symbol}: {$e->getMessage()}", __METHOD__);
            return [];
        }
    }
    
    /**
     * Get recent transactions for a coin from recent blocks
     * 
     * @param Coins $coin Coin model
     * @param int $limit Number of transactions to retrieve (default 20)
     * @return array Array of transaction information
     */
    public function getRecentTransactions($coin, $limit = 20)
    {
        try {
            $rpc = Yii::$app->RpcClient->getConnection($coin);
            
            if (!$rpc) {
                return [];
            }
            
            // Get recent blocks
            $blocks = $this->getRecentBlocks($coin, 10);
            
            $transactions = [];
            
            foreach ($blocks as $block) {
                if (count($transactions) >= $limit) {
                    break;
                }
                
                // Get block details from RPC to get transaction list
                $blockDetails = $this->getBlockDetails($coin, $block->hash);
                
                if ($blockDetails && isset($blockDetails['tx'])) {
                    foreach ($blockDetails['tx'] as $txid) {
                        if (count($transactions) >= $limit) {
                            break;
                        }
                        
                        $transactions[] = [
                            'txid' => $txid,
                            'height' => $block->height,
                            'time' => $block->time,
                        ];
                    }
                }
            }
            
            return $transactions;
            
        } catch (\Exception $e) {
            Yii::error("ExplorerUtils: Error getting recent transactions for {$coin->symbol}: {$e->getMessage()}", __METHOD__);
            return [];
        }
    }
}
