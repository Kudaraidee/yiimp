<?php

namespace app\components;

use Yii;

/**
 * YiimpUtils component
 * 
 * Provides utility functions for Yiimp operations
 */
class YiimpUtils
{
    /**
     * Get algorithm mBTC factor for unit conversion
     * 
     * @param string $algo Algorithm name
     * @return float Factor for mBTC conversion
     */
    public function algo_mBTC_factor($algo)
    {
        // Default factors for common algorithms
        $factors = [
            'sha256' => 1000000000, // Ph/s
            'scrypt' => 1000000,    // Th/s  
            'x11' => 1000,          // Gh/s
            'x13' => 1000,          // Gh/s
            'x15' => 1000,          // Gh/s
            'nist5' => 1000,        // Gh/s
            'neoscrypt' => 1,       // Mh/s
            'lyra2re' => 1,         // Mh/s
            'lyra2rev2' => 1,       // Mh/s
            'blake' => 1000,        // Gh/s
            'blake2s' => 1000,      // Gh/s
            'skein' => 1000,        // Gh/s
            'qubit' => 1000,        // Gh/s
            'groestl' => 1000,      // Gh/s
            'keccak' => 1000,       // Gh/s
            'equihash' => 0.001,    // Kh/s
            'kawpow' => 1,          // Mh/s
            'firopow' => 1,         // Mh/s
            'ethash' => 1,          // Mh/s
        ];
        
        return isset($factors[$algo]) ? $factors[$algo] : 1; // Default to Mh/s
    }

    /**
     * Get current algorithm from session
     * 
     * @return string Current algorithm or default
     */
    public function getCurrentAlgo()
    {
        $algo = Yii::$app->session->get('yaamp-algo');
        if (!$algo) {
            // Get first available algorithm from database
            $algo = (new \yii\db\Query())
                ->select('algo')
                ->from('coins')
                ->where(['enable' => 1, 'visible' => 1])
                ->orderBy('algo')
                ->scalar();
            
            if (!$algo) {
                $algo = 'sha256'; // Fallback
            }
            
            Yii::$app->session->set('yaamp-algo', $algo);
        }
        
        return $algo;
    }

    /**
     * Format large numbers with appropriate units
     * 
     * @param float $value The value to format
     * @return string Formatted value with unit
     */
    public function formatHashrate($value)
    {
        if ($value >= 1000000000000) {
            return round($value / 1000000000000, 2) . ' Ph/s';
        } elseif ($value >= 1000000000) {
            return round($value / 1000000000, 2) . ' Th/s';
        } elseif ($value >= 1000000) {
            return round($value / 1000000, 2) . ' Gh/s';
        } elseif ($value >= 1000) {
            return round($value / 1000, 2) . ' Mh/s';
        } else {
            return round($value, 2) . ' Kh/s';
        }
    }

    /**
     * Get all available algorithms
     * 
     * @return array List of algorithms
     */
    public function get_algos()
    {
        $algos = (new \yii\db\Query())
            ->select('algo')
            ->from('coins')
            ->where(['enable' => 1, 'visible' => 1])
            ->groupBy('algo')
            ->orderBy('algo')
            ->column();
        
        return $algos ?: [];
    }

    /**
     * Get algorithm normalization factor
     * 
     * @param string $algo Algorithm name
     * @return float Normalization factor
     */
    public function get_algo_norm($algo)
    {
        return $this->algo_mBTC_factor($algo);
    }

    /**
     * Apply Yiimp fee to price
     * 
     * @param float $price Original price
     * @param string $algo Algorithm name
     * @return float Price after fee
     */
    public function take_yiimp_fee($price, $algo)
    {
        $fee = $this->yiimp_fee($algo);
        return $price * (1 - $fee / 100);
    }

    /**
     * Get Yiimp fee percentage for algorithm
     * 
     * @param string $algo Algorithm name
     * @return float Fee percentage
     */
    public function yiimp_fee($algo)
    {
        // Default fee from configuration or 2%
        return defined('YIIMP_FEES_MINING') ? YIIMP_FEES_MINING : 2.0;
    }

    /**
     * Get Yiimp solo fee percentage for algorithm
     * 
     * @param string $algo Algorithm name
     * @return float Solo fee percentage
     */
    public function yiimp_fee_solo($algo)
    {
        // Default solo fee from configuration or 1%
        return defined('YIIMP_FEES_SOLO') ? YIIMP_FEES_SOLO : 1.0;
    }

    /**
     * Get coin hashrate
     * 
     * @param int $coinId Coin ID
     * @return float Coin hashrate
     */
    public function coin_rate($coinId)
    {
        $hashrate = (new \yii\db\Query())
            ->select('SUM(difficulty)')
            ->from('workers')
            ->where(['coinid' => $coinId])
            ->andWhere(['>', 'time', time() - 300]) // Last 5 minutes
            ->scalar();
        
        return $hashrate ?: 0;
    }

    /**
     * Get coin shared hashrate
     * 
     * @param int $coinId Coin ID
     * @return float Coin shared hashrate
     */
    public function coin_shared_rate($coinId)
    {
        $hashrate = (new \yii\db\Query())
            ->select('SUM(difficulty)')
            ->from('workers')
            ->where(['coinid' => $coinId])
            ->andWhere(['>', 'time', time() - 300]) // Last 5 minutes
            ->andWhere(['not like', 'password', 'm=solo'])
            ->scalar();
        
        return $hashrate ?: 0;
    }

    /**
     * Get coin solo hashrate
     * 
     * @param int $coinId Coin ID
     * @return float Coin solo hashrate
     */
    public function coin_solo_rate($coinId)
    {
        $hashrate = (new \yii\db\Query())
            ->select('SUM(difficulty)')
            ->from('workers')
            ->where(['coinid' => $coinId])
            ->andWhere(['>', 'time', time() - 300]) // Last 5 minutes
            ->andWhere(['like', 'password', 'm=solo'])
            ->scalar();
        
        return $hashrate ?: 0;
    }

    /**
     * Get coin network hashrate
     * 
     * @param object $coin Coin model
     * @return float Network hashrate
     */
    public function coin_nethash($coin)
    {
        return $coin->network_hash ?: 0;
    }

    /**
     * Calculate Yiimp profitability for coin
     * 
     * @param object $coin Coin model
     * @return float Profitability in mBTC/MH/day
     */
    public function yiimp_profitability($coin)
    {
        if (!$coin->price || !$coin->difficulty) {
            return 0;
        }
        
        $algo_factor = $this->algo_mBTC_factor($coin->algo);
        $profitability = ($coin->price * $algo_factor * 1000000) / max($coin->difficulty, 1);
        
        return $this->take_yiimp_fee($profitability, $coin->algo);
    }

    /**
     * Get hashrate constant for algorithm
     * Used for share difficulty calculations
     * 
     * @param string|null $algo Algorithm name
     * @return int Hashrate constant (target)
     */
    public function hashrate_constant($algo = null)
    {
        switch ($algo) {
            case 'equihash96':
            case 'equihash125':
            case 'equihash144':
            case 'equihash192':
            case 'equihash':
                $target = 0x0000000004000000;
                break;
            default:
                $target = 0x0000040000000000; // pow(2, 42)
                break;
        }
        return $target;
    }

    /**
     * Get hashrate step interval in seconds
     * Used for calculating hashrate from shares
     * 
     * @return int Interval in seconds (default 300 = 5 minutes)
     */
    public function hashrate_step()
    {
        return 300;
    }

    /**
     * Get user by wallet address
     * 
     * @param string $address Wallet address
     * @return object|null User account or null
     */
    public function getuserbyaddress($address)
    {
        if (empty($address)) {
            return null;
        }
        
        return \app\models\Accounts::find()
            ->where(['username' => $address])
            ->one();
    }

    /**
     * Format bitcoin value to string
     * 
     * @param float $value Value to format
     * @return string Formatted value
     */
    public function bitcoinvaluetoa($value)
    {
        return Yii::$app->ConversionUtils->bitcoinvaluetoa($value);
    }

    /**
     * Get algorithm parameter from request
     * 
     * @return string Algorithm from query param or empty string
     */
    public function get_algo_param()
    {
        $algo = Yii::$app->request->get('algo', '');
        return is_string($algo) ? trim($algo) : '';
    }

    /**
     * Calculate difficulty from block hash
     * 
     * @param object $coin Coin model
     * @param string $hash Block hash
     * @return float Difficulty
     */
    public function hash_to_difficulty($coin, $hash)
    {
        // Simplified difficulty calculation from hash
        if (empty($hash)) {
            return 0;
        }
        
        // Count leading zeros
        $zeros = 0;
        for ($i = 0; $i < strlen($hash); $i++) {
            if ($hash[$i] === '0') {
                $zeros++;
            } else {
                break;
            }
        }
        
        // Approximate difficulty based on leading zeros
        return pow(16, $zeros);
    }

    /**
     * Get rented hashrate for algorithm
     * 
     * @param string $algo Algorithm name
     * @return float Rented hashrate
     */
    public function rented_rate($algo)
    {
        // Query jobs table for rented hashrate
        $hashrate = (new \yii\db\Query())
            ->select('SUM(speed)')
            ->from('jobs')
            ->where(['algo' => $algo, 'active' => 1])
            ->scalar();
        
        return $hashrate ?: 0;
    }

    /**
     * Get market URL for coin
     * 
     * @param object $coin Coin model
     * @param string $marketName Market name
     * @return string Market URL
     */
    public function getMarketUrl($coin, $marketName)
    {
        $symbol = $coin->symbol ?? '';
        
        $urls = [
            'bittrex' => "https://bittrex.com/Market/Index?MarketName=BTC-{$symbol}",
            'poloniex' => "https://poloniex.com/exchange#btc_{$symbol}",
            'cryptopia' => "https://www.cryptopia.co.nz/Exchange/?market={$symbol}_BTC",
            'yobit' => "https://yobit.net/en/trade/{$symbol}/BTC",
            'binance' => "https://www.binance.com/en/trade/{$symbol}_BTC",
            'kucoin' => "https://www.kucoin.com/trade/{$symbol}-BTC",
            'tradeogre' => "https://tradeogre.com/exchange/BTC-{$symbol}",
            'graviex' => "https://graviex.net/markets/{$symbol}btc",
            'crex24' => "https://crex24.com/exchange/{$symbol}-BTC",
            'stex' => "https://app.stex.com/en/trade/pair/BTC/{$symbol}",
            'southxchange' => "https://www.southxchange.com/Market/Book/{$symbol}/BTC",
            'coinsmarkets' => "https://coinsmarkets.com/trade-BTC-{$symbol}.htm",
        ];
        
        $marketLower = strtolower($marketName);
        return $urls[$marketLower] ?? '#';
    }

    /**
     * Get total pool hashrate
     * 
     * @param string|null $algo Algorithm name (optional, null for all)
     * @return float Total pool hashrate
     */
    public function pool_rate($algo = null)
    {
        $query = (new \yii\db\Query())
            ->select('SUM(difficulty)')
            ->from('workers')
            ->andWhere(['>', 'time', time() - 300]); // Last 5 minutes
        
        if ($algo && $algo !== 'all') {
            $query->andWhere(['algo' => $algo]);
        }
        
        $hashrate = $query->scalar();
        
        // Convert difficulty to hashrate (simplified)
        $target = $this->hashrate_constant($algo);
        $interval = $this->hashrate_step();
        
        return $hashrate ? ($hashrate * $target / $interval / 1000) : 0;
    }

    /**
     * Get pool hashrate for PoW only
     * 
     * @param string|null $algo Algorithm name
     * @return float Pool PoW hashrate
     */
    public function pool_rate_pow($algo = null)
    {
        // Same as pool_rate but could filter for PoW-only coins
        return $this->pool_rate($algo);
    }
}