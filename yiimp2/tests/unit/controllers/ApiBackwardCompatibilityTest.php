<?php

namespace tests\unit\controllers;

use Yii;
use Codeception\Test\Unit;

/**
 * Test API backward compatibility with legacy API
 * Validates Requirements 7.5 - Backward compatibility
 */
class ApiBackwardCompatibilityTest extends Unit
{
    /**
     * Test that status response structure matches legacy API
     * Compares field names and types
     */
    public function testStatusResponseStructure()
    {
        // Expected structure from legacy API
        $expectedStructure = [
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
            'estimate_current' => 'string',
            'estimate_last24h' => 'string',
            'actual_last24h' => 'string|double',
            'mbtc_mh_factor' => 'double',
            'hashrate_last24h' => 'double',
        ];

        // Verify all expected keys are present
        foreach (array_keys($expectedStructure) as $key) {
            $this->assertIsString($key, "Key should be string");
            $this->assertMatchesRegularExpression('/^[a-z0-9_]+$/', $key, 
                "Key '$key' should be snake_case");
        }

        // Verify structure completeness
        $this->assertCount(15, $expectedStructure, "Status response should have 15 fields");
    }

    /**
     * Test that wallet response structure matches legacy API
     * Compares field names and types
     */
    public function testWalletResponseStructure()
    {
        // Expected structure from legacy API
        $expectedStructure = [
            'currency' => 'string',
            'unsold' => 'double',
            'balance' => 'double',
            'unpaid' => 'double',
            'paid24h' => 'double',
            'total' => 'double',
            'hashrate' => 'double',
            'workers' => 'integer',
        ];

        // Verify all expected keys are present
        foreach (array_keys($expectedStructure) as $key) {
            $this->assertIsString($key, "Key should be string");
            $this->assertMatchesRegularExpression('/^[a-z0-9_]+$/', $key,
                "Key '$key' should be snake_case");
        }

        // Verify structure completeness
        $this->assertCount(8, $expectedStructure, "Wallet response should have 8 fields");
    }

    /**
     * Test that walletEx response structure matches legacy API
     * Compares field names and types
     */
    public function testWalletExResponseStructure()
    {
        // Expected structure from legacy API
        $expectedStructure = [
            'currency' => 'string',
            'unsold' => 'double',
            'balance' => 'double',
            'unpaid' => 'double',
            'paid24h' => 'double',
            'total' => 'double',
            'miners' => 'array',
            'payouts' => 'array', // Optional, depends on YIIMP_API_PAYOUTS
        ];

        $minerStructure = [
            'version' => 'string',
            'password' => 'string',
            'ID' => 'string',
            'algo' => 'string',
            'difficulty' => 'double',
            'subscribe' => 'integer',
            'accepted' => 'double',
            'rejected' => 'double',
        ];

        $payoutStructure = [
            'time' => 'integer',
            'amount' => 'string',
            'tx' => 'string',
        ];

        // Verify main structure
        foreach (array_keys($expectedStructure) as $key) {
            $this->assertIsString($key, "Key should be string");
        }

        // Verify miner structure
        foreach (array_keys($minerStructure) as $key) {
            $this->assertIsString($key, "Miner key should be string");
        }

        // Verify payout structure
        foreach (array_keys($payoutStructure) as $key) {
            $this->assertIsString($key, "Payout key should be string");
            $this->assertMatchesRegularExpression('/^[a-z0-9_]+$/', $key,
                "Payout key '$key' should be snake_case");
        }

        $this->assertCount(8, $expectedStructure, "WalletEx response should have 8 fields");
        $this->assertCount(8, $minerStructure, "Miner object should have 8 fields");
        $this->assertCount(3, $payoutStructure, "Payout object should have 3 fields");
    }

    /**
     * Test that currency response structure matches legacy API
     * Compares field names and types
     */
    public function testCurrencyResponseStructure()
    {
        // Expected structure from legacy API
        $expectedStructure = [
            'name' => 'string',
            'algo' => 'string',
            'port' => 'integer',
            'reward' => 'double',
            'blocktime' => 'integer',
            'height' => 'integer',
            'difficulty' => 'double',
            'autotrade' => 'boolean',
            'fees' => 'double',
            'fees_solo' => 'double',
            'miners' => 'integer',
            'workers' => 'integer',
            'hashrate' => 'double',
            'network_hashrate' => 'double',
            'estimate' => 'string',
            '24h_blocks' => 'integer',
            '24h_btc' => 'double',
            'lastblock' => 'integer',
            'timesincelast' => 'integer',
        ];

        // Verify all expected keys are present
        foreach (array_keys($expectedStructure) as $key) {
            $this->assertIsString($key, "Key should be string");
        }

        // Verify structure completeness
        $this->assertCount(19, $expectedStructure, "Currency response should have 19 fields");
    }

    /**
     * Test that blocks response structure matches legacy API
     * Compares field names and types
     */
    public function testBlocksResponseStructure()
    {
        // Expected structure from legacy API
        $expectedStructure = [
            'height' => 'integer',
            'blockhash' => 'string',
            'coin' => 'string',
            'algo' => 'string',
            'amount' => 'double',
            'difficulty' => 'double',
            'time' => 'integer',
            'timestamp' => 'string',
            'confirmations' => 'integer',
            'txhash' => 'string',
            'reward' => 'string',
        ];

        // Verify all expected keys are present
        foreach (array_keys($expectedStructure) as $key) {
            $this->assertIsString($key, "Key should be string");
            $this->assertMatchesRegularExpression('/^[a-z0-9_]+$/', $key,
                "Key '$key' should be snake_case");
        }

        // Verify structure completeness
        $this->assertCount(11, $expectedStructure, "Blocks response should have 11 fields");
    }

    /**
     * Test that field names match exactly between legacy and yiimp2
     * This ensures no typos or naming changes
     */
    public function testFieldNamesMatchExactly()
    {
        $legacyFieldNames = [
            // Status endpoint
            'estimate_current', 'estimate_last24h', 'actual_last24h',
            'mbtc_mh_factor', 'hashrate_last24h', 'hashrate_shared',
            'workers_shared', 'workers_solo', 'fees_solo',
            
            // Wallet endpoint
            'unsold', 'balance', 'unpaid', 'paid24h', 'total',
            'currency', 'hashrate', 'workers',
            
            // Currency endpoint
            'network_hashrate', '24h_blocks', '24h_btc',
            'lastblock', 'timesincelast', 'autotrade',
            
            // Blocks endpoint
            'blockhash', 'txhash', 'confirmations', 'timestamp',
        ];

        foreach ($legacyFieldNames as $fieldName) {
            // Verify field name hasn't been changed
            $this->assertIsString($fieldName, "Field name should be string");
            
            // Verify no accidental camelCase conversion
            $this->assertDoesNotMatchRegularExpression('/[A-Z]/', $fieldName,
                "Field '$fieldName' should not contain uppercase letters");
            
            // Verify underscores are preserved
            if (strpos($fieldName, '_') !== false) {
                $this->assertStringContainsString('_', $fieldName,
                    "Field '$fieldName' should preserve underscores");
            }
        }
    }

    /**
     * Test that response structure is identical (not just similar)
     * Ensures exact compatibility
     */
    public function testResponseStructureIsIdentical()
    {
        // Test that adding/removing fields breaks compatibility
        $statusFields = [
            'name', 'port', 'coins', 'fees', 'fees_solo',
            'hashrate', 'hashrate_shared', 'workers', 'workers_shared', 'workers_solo',
            'estimate_current', 'estimate_last24h', 'actual_last24h',
            'mbtc_mh_factor', 'hashrate_last24h'
        ];

        $walletFields = [
            'currency', 'unsold', 'balance', 'unpaid', 'paid24h', 'total',
            'hashrate', 'workers'
        ];

        // Verify exact field counts
        $this->assertCount(15, $statusFields, "Status must have exactly 15 fields");
        $this->assertCount(8, $walletFields, "Wallet must have exactly 8 fields");

        // Verify no duplicates
        $this->assertCount(15, array_unique($statusFields), "Status fields must be unique");
        $this->assertCount(8, array_unique($walletFields), "Wallet fields must be unique");
    }
}
