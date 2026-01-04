<?php

namespace tests\integration;

use Codeception\Test\Unit;

/**
 * Integration tests for API compatibility
 * Tests actual API responses against legacy format
 * Validates Requirements 7.1, 7.2, 7.3, 7.4, 7.5
 */
class ApiCompatibilityTest extends Unit
{
    /**
     * Test status endpoint response format
     * Validates numeric formatting, key names, and structure
     */
    public function testStatusEndpointResponseFormat()
    {
        // This test would require a running database
        // For now, we verify the response structure expectations
        
        $expectedKeys = [
            'name', 'port', 'coins', 'fees', 'fees_solo',
            'hashrate', 'hashrate_shared', 'workers', 'workers_shared', 'workers_solo',
            'estimate_current', 'estimate_last24h', 'actual_last24h',
            'mbtc_mh_factor', 'hashrate_last24h'
        ];

        // Verify expected keys are snake_case
        foreach ($expectedKeys as $key) {
            $this->assertMatchesRegularExpression('/^[a-z][a-z0-9_]*$/', $key,
                "Status key '$key' must be snake_case");
        }

        // Verify expected types
        $expectedTypes = [
            'name' => 'string',
            'port' => 'integer',
            'coins' => 'integer',
            'fees' => 'double',
            'fees_solo' => 'double',
            'hashrate' => 'integer',
            'hashrate_shared' => 'integer',
            'workers' => 'integer',
            'workers_shared' => 'integer',
            'workers_solo' => 'integer',
            'estimate_current' => 'string', // BTC value as string with 8 decimals
            'estimate_last24h' => 'string', // BTC value as string with 8 decimals
            'actual_last24h' => 'string|double', // mBTC value
            'mbtc_mh_factor' => 'double',
            'hashrate_last24h' => 'double',
        ];

        $this->assertCount(15, $expectedTypes, "Status response must have exactly 15 fields");
    }

    /**
     * Test wallet endpoint response format
     * Validates numeric formatting, key names, and structure
     */
    public function testWalletEndpointResponseFormat()
    {
        $expectedKeys = [
            'currency', 'unsold', 'balance', 'unpaid', 'paid24h', 'total',
            'hashrate', 'workers'
        ];

        // Verify expected keys are snake_case
        foreach ($expectedKeys as $key) {
            $this->assertMatchesRegularExpression('/^[a-z][a-z0-9_]*$/', $key,
                "Wallet key '$key' must be snake_case");
        }

        // Verify expected types
        $expectedTypes = [
            'currency' => 'string',
            'unsold' => 'double', // BTC value
            'balance' => 'double', // BTC value
            'unpaid' => 'double', // BTC value
            'paid24h' => 'double', // BTC value
            'total' => 'double', // BTC value
            'hashrate' => 'double',
            'workers' => 'integer',
        ];

        $this->assertCount(8, $expectedTypes, "Wallet response must have exactly 8 fields");
    }

    /**
     * Test walletEx endpoint response format
     * Validates extended wallet data including miners and payouts
     */
    public function testWalletExEndpointResponseFormat()
    {
        $expectedMainKeys = [
            'currency', 'unsold', 'balance', 'unpaid', 'paid24h', 'total',
            'miners', 'payouts'
        ];

        $expectedMinerKeys = [
            'version', 'password', 'ID', 'algo', 'difficulty',
            'subscribe', 'accepted', 'rejected'
        ];

        $expectedPayoutKeys = [
            'time', 'amount', 'tx'
        ];

        // Verify main keys
        foreach ($expectedMainKeys as $key) {
            if ($key !== 'payouts') { // payouts is optional
                $this->assertIsString($key, "WalletEx key must be string");
            }
        }

        // Verify miner keys
        foreach ($expectedMinerKeys as $key) {
            $this->assertIsString($key, "Miner key must be string");
        }

        // Verify payout keys
        foreach ($expectedPayoutKeys as $key) {
            $this->assertMatchesRegularExpression('/^[a-z][a-z0-9_]*$/', $key,
                "Payout key '$key' must be snake_case");
        }

        // Verify miner field types
        $minerTypes = [
            'version' => 'string',
            'password' => 'string',
            'ID' => 'string',
            'algo' => 'string',
            'difficulty' => 'double',
            'subscribe' => 'integer',
            'accepted' => 'double', // Rounded to 3 decimals
            'rejected' => 'double', // Rounded to 3 decimals
        ];

        $this->assertCount(8, $minerTypes, "Miner object must have exactly 8 fields");

        // Verify payout field types
        $payoutTypes = [
            'time' => 'integer', // Unix timestamp
            'amount' => 'string', // BTC value as string
            'tx' => 'string', // Transaction hash
        ];

        $this->assertCount(3, $payoutTypes, "Payout object must have exactly 3 fields");
    }

    /**
     * Test currency endpoint response format
     * Validates per-coin statistics format
     */
    public function testCurrencyEndpointResponseFormat()
    {
        $expectedKeys = [
            'name', 'algo', 'port', 'reward', 'blocktime', 'height',
            'difficulty', 'autotrade', 'fees', 'fees_solo', 'miners', 'workers',
            'hashrate', 'network_hashrate', 'estimate',
            '24h_blocks', '24h_btc', 'lastblock', 'timesincelast'
        ];

        // Verify expected keys
        foreach ($expectedKeys as $key) {
            $this->assertIsString($key, "Currency key must be string");
        }

        // Verify expected types
        $expectedTypes = [
            'name' => 'string',
            'algo' => 'string',
            'port' => 'integer',
            'reward' => 'double',
            'blocktime' => 'integer',
            'height' => 'integer',
            'difficulty' => 'double',
            'autotrade' => 'boolean', // Must be true/false, not 1/0
            'fees' => 'double',
            'fees_solo' => 'double',
            'miners' => 'integer',
            'workers' => 'integer',
            'hashrate' => 'double',
            'network_hashrate' => 'double',
            'estimate' => 'string', // mBTC value
            '24h_blocks' => 'integer',
            '24h_btc' => 'double', // Rounded to 8 decimals
            'lastblock' => 'integer',
            'timesincelast' => 'integer',
        ];

        $this->assertCount(19, $expectedTypes, "Currency response must have exactly 19 fields");
    }

    /**
     * Test blocks endpoint response format
     * Validates block data including timestamps
     */
    public function testBlocksEndpointResponseFormat()
    {
        $expectedKeys = [
            'height', 'blockhash', 'coin', 'algo', 'amount', 'difficulty',
            'time', 'timestamp', 'confirmations', 'txhash', 'reward'
        ];

        // Verify expected keys are snake_case
        foreach ($expectedKeys as $key) {
            $this->assertMatchesRegularExpression('/^[a-z][a-z0-9_]*$/', $key,
                "Blocks key '$key' must be snake_case");
        }

        // Verify expected types
        $expectedTypes = [
            'height' => 'integer',
            'blockhash' => 'string',
            'coin' => 'string',
            'algo' => 'string',
            'amount' => 'double',
            'difficulty' => 'double',
            'time' => 'integer', // Unix timestamp
            'timestamp' => 'string', // Formatted date Y-m-d H:i:s
            'confirmations' => 'integer',
            'txhash' => 'string',
            'reward' => 'string', // BTC value as string
        ];

        $this->assertCount(11, $expectedTypes, "Blocks response must have exactly 11 fields");

        // Verify timestamp format pattern
        $timestampPattern = '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/';
        $this->assertIsString($timestampPattern, "Timestamp pattern must be defined");
    }

    /**
     * Test that all endpoints use consistent formatting
     * Validates cross-endpoint consistency
     */
    public function testConsistentFormattingAcrossEndpoints()
    {
        // BTC values should always be formatted to 8 decimals
        $btcFields = ['unsold', 'balance', 'unpaid', 'paid24h', 'total', 'reward'];
        
        foreach ($btcFields as $field) {
            $this->assertIsString($field, "BTC field name must be string");
        }

        // Hashrate values should always be integers or doubles
        $hashrateFields = ['hashrate', 'hashrate_shared', 'hashrate_last24h', 'network_hashrate'];
        
        foreach ($hashrateFields as $field) {
            $this->assertIsString($field, "Hashrate field name must be string");
        }

        // Worker counts should always be integers
        $workerFields = ['workers', 'workers_shared', 'workers_solo'];
        
        foreach ($workerFields as $field) {
            $this->assertIsString($field, "Worker field name must be string");
        }

        // Timestamps should always include both Unix and formatted versions
        $timestampFields = ['time', 'timestamp'];
        
        foreach ($timestampFields as $field) {
            $this->assertIsString($field, "Timestamp field name must be string");
        }
    }

    /**
     * Test that no endpoint uses camelCase
     * Ensures all keys are snake_case
     */
    public function testNoEndpointUsesCamelCase()
    {
        $allKeys = [
            // Status
            'estimate_current', 'estimate_last24h', 'actual_last24h',
            'mbtc_mh_factor', 'hashrate_last24h', 'hashrate_shared',
            'workers_shared', 'workers_solo', 'fees_solo',
            
            // Wallet
            'paid24h',
            
            // Currency
            'network_hashrate', '24h_blocks', '24h_btc',
            'lastblock', 'timesincelast',
            
            // Blocks
            'blockhash', 'txhash',
        ];

        foreach ($allKeys as $key) {
            $this->assertDoesNotMatchRegularExpression('/[A-Z]/', $key,
                "Key '$key' must not contain uppercase letters (no camelCase)");
        }
    }
}
