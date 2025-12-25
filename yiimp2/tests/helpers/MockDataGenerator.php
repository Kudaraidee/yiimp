<?php

namespace tests\helpers;

/**
 * Generates realistic mock data for testing
 * 
 * Provides methods to generate various types of test data
 * that conform to the same validation rules as real data.
 */
class MockDataGenerator
{
    /**
     * Generate mock coin data
     * 
     * @param array $overrides Optional field overrides
     * @return array
     */
    public static function generateCoin(array $overrides = []): array
    {
        $algorithms = ['sha256', 'scrypt', 'x11', 'x13', 'x15', 'quark', 'lyra2v2', 'equihash', 'yescrypt'];
        $symbols = ['BTC', 'LTC', 'DOGE', 'XMR', 'ZEC', 'DASH', 'ETH', 'TEST'];
        
        $defaults = [
            'name' => 'TestCoin' . rand(1000, 9999),
            'symbol' => $symbols[array_rand($symbols)] . rand(1, 99),
            'symbol2' => 'T' . rand(100, 999),
            'algo' => $algorithms[array_rand($algorithms)],
            'rpchost' => '127.0.0.1',
            'rpcport' => rand(8000, 9000),
            'rpcuser' => 'user' . rand(1, 999),
            'rpcpasswd' => 'pass' . rand(1000, 9999),
            'rpcencoding' => 'hex',
            'master_wallet' => self::generateAddress(),
            'enable' => rand(0, 1),
            'visible' => rand(0, 1),
            'auto_ready' => rand(0, 1),
            'pool_ttf' => rand(60, 3600),
            'actual_ttf' => rand(60, 3600),
            'network_ttf' => rand(60, 3600),
            'difficulty' => round(rand(1000, 1000000) / 100, 2),
            'reward' => round(rand(1, 100) / 10, 2),
            'price' => round(rand(1, 10000) / 100000, 8),
            'block_height' => rand(1, 1000000),
            'network_hash' => rand(1000000, 1000000000000),
            'txfee' => round(rand(1, 100) / 10000, 4),
        ];
        
        return array_merge($defaults, $overrides);
    }
    
    /**
     * Generate mock block data
     * 
     * @param array $overrides Optional field overrides
     * @return array
     */
    public static function generateBlock(array $overrides = []): array
    {
        $defaults = [
            'height' => rand(1, 1000000),
            'blockhash' => self::generateHash(),
            'coin_id' => rand(1, 100),
            'userid' => rand(1, 1000),
            'workerid' => rand(1, 5000),
            'category' => 'immature',
            'difficulty' => round(rand(1000, 1000000) / 100, 2),
            'amount' => round(rand(1, 100) / 10, 2),
            'confirmations' => rand(0, 120),
            'time' => time() - rand(0, 86400),
            'algo' => 'sha256',
        ];
        
        return array_merge($defaults, $overrides);
    }
    
    /**
     * Generate mock worker data
     * 
     * @param array $overrides Optional field overrides
     * @return array
     */
    public static function generateWorker(array $overrides = []): array
    {
        $defaults = [
            'userid' => rand(1, 1000),
            'name' => 'worker' . rand(1, 999),
            'password' => 'x',
            'algo' => 'sha256',
            'difficulty' => round(rand(1, 1000) / 10, 2),
            'subscribe' => 1,
        ];
        
        return array_merge($defaults, $overrides);
    }
    
    /**
     * Generate mock payout data
     * 
     * @param array $overrides Optional field overrides
     * @return array
     */
    public static function generatePayout(array $overrides = []): array
    {
        $defaults = [
            'account_id' => rand(1, 1000),
            'coin_id' => rand(1, 100),
            'amount' => round(rand(1, 1000) / 100, 2),
            'address' => self::generateAddress(),
            'time' => time() - rand(0, 86400),
            'tx' => self::generateHash(),
            'completed' => rand(0, 1),
        ];
        
        return array_merge($defaults, $overrides);
    }
    
    /**
     * Generate mock account/user data
     * 
     * @param array $overrides Optional field overrides
     * @return array
     */
    public static function generateAccount(array $overrides = []): array
    {
        $defaults = [
            'username' => 'user' . rand(1000, 9999),
            'coinsymbol' => 'BTC',
            'balance' => round(rand(0, 10000) / 100, 8),
            'earn' => round(rand(0, 10000) / 100, 8),
            'no_fees' => 0,
            'donation' => 0,
        ];
        
        return array_merge($defaults, $overrides);
    }
    
    /**
     * Generate random cryptocurrency address
     * 
     * @param int $length Address length (default 34)
     * @return string
     */
    public static function generateAddress(int $length = 34): string
    {
        $chars = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
        $address = '';
        for ($i = 0; $i < $length; $i++) {
            $address .= $chars[rand(0, strlen($chars) - 1)];
        }
        return $address;
    }
    
    /**
     * Generate random hash (block hash, transaction hash, etc.)
     * 
     * @param int $length Hash length in bytes (default 32 for 64 hex chars)
     * @return string
     */
    public static function generateHash(int $length = 32): string
    {
        return bin2hex(random_bytes($length));
    }
    
    /**
     * Generate random IP address
     * 
     * @param bool $v6 Generate IPv6 address (default false for IPv4)
     * @return string
     */
    public static function generateIpAddress(bool $v6 = false): string
    {
        if ($v6) {
            return sprintf(
                '%04x:%04x:%04x:%04x:%04x:%04x:%04x:%04x',
                rand(0, 65535), rand(0, 65535), rand(0, 65535), rand(0, 65535),
                rand(0, 65535), rand(0, 65535), rand(0, 65535), rand(0, 65535)
            );
        }
        
        return sprintf('%d.%d.%d.%d', rand(1, 255), rand(0, 255), rand(0, 255), rand(1, 255));
    }
    
    /**
     * Generate array of mock coins
     * 
     * @param int $count Number of coins to generate
     * @return array
     */
    public static function generateCoins(int $count): array
    {
        $coins = [];
        for ($i = 0; $i < $count; $i++) {
            $coins[] = self::generateCoin(['id' => $i + 1]);
        }
        return $coins;
    }
    
    /**
     * Generate array of mock blocks
     * 
     * @param int $count Number of blocks to generate
     * @return array
     */
    public static function generateBlocks(int $count): array
    {
        $blocks = [];
        for ($i = 0; $i < $count; $i++) {
            $blocks[] = self::generateBlock(['id' => $i + 1, 'height' => $i + 1]);
        }
        return $blocks;
    }
}
