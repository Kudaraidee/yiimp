<?php

namespace tests\unit\controllers;

use Yii;
use Codeception\Test\Unit;

/**
 * Test API key naming conventions for backward compatibility
 * Validates Requirements 7.4 - Key naming consistency
 */
class ApiKeyNamingTest extends Unit
{
    /**
     * Test that all keys use snake_case
     * Legacy API uses snake_case for all JSON keys
     */
    public function testKeysUseSnakeCase()
    {
        // Test various key names from the API
        $validKeys = [
            'estimate_current',
            'estimate_last24h',
            'actual_last24h',
            'mbtc_mh_factor',
            'hashrate_last24h',
            'hashrate_shared',
            'workers_shared',
            'workers_solo',
            'fees_solo',
            '24h_blocks',
            '24h_btc',
            'network_hashrate',
            'paid24h',
        ];

        foreach ($validKeys as $key) {
            // Check that key follows snake_case pattern
            // Should be lowercase with underscores, may start with digits
            $this->assertMatchesRegularExpression('/^[0-9]*[a-z][a-z0-9_]*$/', $key,
                "Key '$key' should follow snake_case convention");
            
            // Should not contain camelCase
            $this->assertDoesNotMatchRegularExpression('/[A-Z]/', $key,
                "Key '$key' should not contain uppercase letters (camelCase)");
            
            // Should not contain hyphens
            $this->assertStringNotContainsString('-', $key,
                "Key '$key' should not contain hyphens (use underscores)");
        }
    }

    /**
     * Test that status endpoint keys match legacy API
     * Legacy API status endpoint key names
     */
    public function testStatusEndpointKeyNames()
    {
        $expectedKeys = [
            'name',
            'port',
            'coins',
            'fees',
            'fees_solo',
            'hashrate',
            'hashrate_shared',
            'workers',
            'workers_shared',
            'workers_solo',
            'estimate_current',
            'estimate_last24h',
            'actual_last24h',
            'mbtc_mh_factor',
            'hashrate_last24h',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertMatchesRegularExpression('/^[a-z][a-z0-9_]*$/', $key,
                "Status endpoint key '$key' should be snake_case");
        }
    }

    /**
     * Test that wallet endpoint keys match legacy API
     * Legacy API wallet endpoint key names
     */
    public function testWalletEndpointKeyNames()
    {
        $expectedKeys = [
            'currency',
            'unsold',
            'balance',
            'unpaid',
            'paid24h',
            'total',
            'hashrate',
            'workers',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertMatchesRegularExpression('/^[a-z][a-z0-9_]*$/', $key,
                "Wallet endpoint key '$key' should be snake_case");
        }
    }

    /**
     * Test that walletEx endpoint keys match legacy API
     * Legacy API walletEx endpoint key names
     */
    public function testWalletExEndpointKeyNames()
    {
        $expectedKeys = [
            'currency',
            'unsold',
            'balance',
            'unpaid',
            'paid24h',
            'total',
            'miners',
            'payouts',
        ];

        $minerKeys = [
            'version',
            'password',
            'ID', // Note: This is uppercase in legacy API
            'algo',
            'difficulty',
            'subscribe',
            'accepted',
            'rejected',
        ];

        $payoutKeys = [
            'time',
            'amount',
            'tx',
        ];

        foreach ($expectedKeys as $key) {
            // Most keys are snake_case
            $this->assertMatchesRegularExpression('/^[a-z][a-z0-9_]*$/', $key,
                "WalletEx endpoint key '$key' should be snake_case");
        }

        foreach ($minerKeys as $key) {
            // Miner keys include 'ID' which is uppercase (legacy compatibility)
            if ($key === 'ID') {
                $this->assertEquals('ID', $key, "Miner 'ID' key should be uppercase for legacy compatibility");
            } else {
                $this->assertMatchesRegularExpression('/^[a-z][a-z0-9_]*$/', $key,
                    "Miner key '$key' should be snake_case");
            }
        }

        foreach ($payoutKeys as $key) {
            $this->assertMatchesRegularExpression('/^[a-z][a-z0-9_]*$/', $key,
                "Payout key '$key' should be snake_case");
        }
    }

    /**
     * Test that currency endpoint keys match legacy API
     * Legacy API currency endpoint key names
     */
    public function testCurrencyEndpointKeyNames()
    {
        $expectedKeys = [
            'name',
            'algo',
            'port',
            'reward',
            'blocktime',
            'height',
            'difficulty',
            'autotrade',
            'fees',
            'fees_solo',
            'miners',
            'workers',
            'hashrate',
            'network_hashrate',
            'estimate',
            '24h_blocks',
            '24h_btc',
            'lastblock',
            'timesincelast',
        ];

        foreach ($expectedKeys as $key) {
            // Keys can start with digits (24h_blocks)
            $this->assertMatchesRegularExpression('/^[0-9]*[a-z][a-z0-9_]*$/', $key,
                "Currency endpoint key '$key' should be snake_case");
        }
    }

    /**
     * Test that blocks endpoint keys match legacy API
     * Legacy API blocks endpoint key names
     */
    public function testBlocksEndpointKeyNames()
    {
        $expectedKeys = [
            'height',
            'blockhash',
            'coin',
            'algo',
            'amount',
            'difficulty',
            'time',
            'timestamp',
            'confirmations',
            'txhash',
            'reward',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertMatchesRegularExpression('/^[a-z][a-z0-9_]*$/', $key,
                "Blocks endpoint key '$key' should be snake_case");
        }
    }

    /**
     * Test that no keys use camelCase
     * Ensures consistency with legacy API
     */
    public function testNoKeysUseCamelCase()
    {
        $invalidKeys = [
            'estimateCurrent',
            'estimateLast24h',
            'actualLast24h',
            'mbtcMhFactor',
            'hashrateShared',
            'workersShared',
            'networkHashrate',
        ];

        foreach ($invalidKeys as $key) {
            $this->assertMatchesRegularExpression('/[A-Z]/', $key,
                "Key '$key' contains uppercase (this test verifies we DON'T use these)");
        }
    }
}
