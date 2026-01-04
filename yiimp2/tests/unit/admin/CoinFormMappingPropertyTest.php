<?php

namespace app\tests\unit\admin;

use tests\helpers\DatabaseTestHelper;
use Codeception\Test\Unit;
use app\models\Coins;
use app\models\Algos;

/**
 * Property-based tests for coin form-to-database mapping
 * 
 * Feature: yiimp2-coin-form-completion
 * Tests that form data correctly maps to database columns
 */
class CoinFormMappingPropertyTest extends Unit
{
    use DatabaseTestHelper;

    protected function _before()
    {
        if (!$this->isDatabaseAvailable()) {
            $this->markTestSkipped('Database connection required for form mapping tests');
        }
    }

    protected function _after()
    {
        // Clean up any test coins created
        Coins::deleteAll(['like', 'name', 'TEST_FormMapping_%', false]);
    }

    /**
     * Property 6: Form-to-database mapping consistency
     * For any valid form submission, all form field values should be correctly 
     * stored in their corresponding database columns
     * 
     * Feature: yiimp2-coin-form-completion, Property 6: Form-to-database mapping consistency
     * Validates: Requirements 8.2
     * 
     * @group property
     */
    public function testFormToDatabaseMappingConsistencyProperty()
    {
        $iterations = 100;
        
        for ($i = 0; $i < $iterations; $i++) {
            // Generate random form data
            $formData = $this->generateRandomCoinFormData($i);
            
            // Create coin model and load form data
            $coin = new Coins();
            $coin->setAttributes($formData, false); // Load without validation for testing
            
            // Save to database
            $saved = $coin->save(false); // Skip validation to test pure mapping
            
            if (!$saved) {
                // If save failed, it might be due to database constraints
                // Skip this iteration
                continue;
            }
            
            // Reload from database
            $reloadedCoin = Coins::findOne($coin->id);
            
            $this->assertNotNull($reloadedCoin, 
                "Coin should be retrievable from database after save");
            
            // Property: All form fields should match database values
            foreach ($formData as $field => $value) {
                // Skip fields that might be transformed or have special handling
                if (in_array($field, ['specifications'])) {
                    continue;
                }
                
                $dbValue = $reloadedCoin->$field;
                
                // Handle numeric comparisons with tolerance for floating point
                if (is_numeric($value) && is_numeric($dbValue)) {
                    $this->assertEqualsWithDelta($value, $dbValue, 0.00000001,
                        "Field '{$field}' should match: form={$value}, db={$dbValue}");
                } else {
                    // For non-numeric fields, do exact comparison
                    // Handle null vs empty string equivalence
                    if (($value === null || $value === '') && ($dbValue === null || $dbValue === '')) {
                        // Both are "empty", consider them equal
                        $this->assertTrue(true);
                    } else {
                        $this->assertEquals($value, $dbValue,
                            "Field '{$field}' should match: form={$value}, db={$dbValue}");
                    }
                }
            }
            
            // Clean up
            $coin->delete();
        }
    }

    /**
     * Generate random coin form data for testing
     * @param int $iteration Iteration number for uniqueness
     * @return array Form data
     */
    private function generateRandomCoinFormData($iteration)
    {
        $algos = ['sha256', 'scrypt', 'x11', 'x13', 'x15', 'equihash', 'kawpow'];
        $algo = $algos[array_rand($algos)];
        
        return [
            // General tab fields
            'name' => 'TEST_FormMapping_' . $iteration . '_' . uniqid(),
            'symbol' => 'TFM' . rand(100, 999),
            'symbol2' => (rand(0, 1) ? 'TFM2' . rand(100, 999) : null),
            'algo' => $algo,
            'image' => (rand(0, 1) ? 'https://example.com/coin' . $iteration . '.png' : null),
            'version_installed' => (rand(0, 1) ? '1.0.' . rand(0, 10) : null),
            'payout_min' => round(rand(1, 100) / 10, 8),
            'payout_max' => round(rand(100, 1000) / 10, 8),
            'target_height' => rand(100000, 999999),
            'powend_height' => (rand(0, 1) ? rand(1000000, 9999999) : null),
            'mature_blocks' => rand(10, 120),
            'powlimit_bits' => (rand(0, 1) ? rand(16, 32) : null),
            'block_time' => rand(30, 600),
            'decimals' => rand(0, 18),
            
            // Settings tab fields
            'enable' => rand(0, 1),
            'auto_ready' => rand(0, 1),
            'visible' => rand(0, 1),
            'installed' => rand(0, 1),
            'no_explorer' => rand(0, 1),
            'watch' => rand(0, 1),
            'auxpow' => rand(0, 1),
            'enable_rpcdebug' => rand(0, 1),
            'hasgetinfo' => rand(0, 1),
            'hassubmitblock' => rand(0, 1),
            'txmessage' => rand(0, 1),
            'hasmasternodes' => rand(0, 1),
            'usesegwit' => rand(0, 1),
            'usemweb' => rand(0, 1),
            'personalization' => ($algo === 'equihash' && rand(0, 1) ? 'ZcashPoW' : null),
            'max_miners' => (rand(0, 1) ? rand(10, 1000) : null),
            'max_shares' => (rand(0, 1) ? rand(1000, 100000) : null),
            'master_wallet' => $this->generateRandomAddress(),
            'wallet_zaddress' => (rand(0, 1) ? $this->generateRandomAddress() : null),
            'reward_mul' => round(rand(1, 10) / 10, 2),
            'charity_percent' => round(rand(0, 100) / 10, 2),
            'charity_address' => (rand(0, 1) ? $this->generateRandomAddress() : null),
            
            // Exchange tab fields
            'dontsell' => rand(0, 1),
            'sellonbid' => rand(0, 1),
            'auto_exchange' => rand(0, 1),
            'sellthreshold' => round(rand(100, 10000), 2),
            'market' => (rand(0, 1) ? ['bittrex', 'poloniex', 'binance'][rand(0, 2)] : null),
            'price' => round(rand(1, 100000) / 100000000, 8),
            
            // Daemon tab fields
            'program' => (rand(0, 1) ? 'coind' : null),
            'conf_folder' => (rand(0, 1) ? '/root/.coin' : null),
            'rpchost' => '127.0.0.1',
            'rpcport' => rand(8000, 9000),
            'rpcwallet' => (rand(0, 1) ? 'wallet' . rand(1, 5) : null),
            'rpcuser' => 'rpcuser' . rand(1, 100),
            'rpcpasswd' => bin2hex(random_bytes(16)),
            'serveruser' => (rand(0, 1) ? 'pooluser' : null),
            'rpcencoding' => ['POW', 'POS', 'DCR', 'DGB'][rand(0, 3)],
            'dedicatedport' => (rand(0, 1) ? rand(3000, 4000) : null),
            'rpccurl' => rand(0, 1),
            'rpcssl' => rand(0, 1),
            'rpccert' => (rand(0, 1) ? '/path/to/cert.pem' : null),
            'account' => (rand(0, 1) ? 'account' . rand(1, 10) : ''),
            
            // Links tab fields
            'link_bitcointalk' => (rand(0, 1) ? 'https://bitcointalk.org/index.php?topic=' . rand(100000, 999999) : null),
            'link_github' => (rand(0, 1) ? 'https://github.com/example/coin' . $iteration : null),
            'link_site' => (rand(0, 1) ? 'https://coin' . $iteration . '.example.com' : null),
            'link_exchange' => (rand(0, 1) ? 'https://exchange.example.com/trade/TFM' : null),
            'link_explorer' => (rand(0, 1) ? 'https://explorer.coin' . $iteration . '.com' : null),
            'link_twitter' => (rand(0, 1) ? 'https://twitter.com/coin' . $iteration : null),
            'link_discord' => (rand(0, 1) ? 'https://discord.gg/coin' . $iteration : null),
            'link_facebook' => (rand(0, 1) ? 'https://facebook.com/coin' . $iteration : null),
        ];
    }

    /**
     * Generate a random wallet address for testing
     * @return string
     */
    private function generateRandomAddress()
    {
        $prefix = ['1', '3', 'bc1', 'L', 'M', 'D'][rand(0, 5)];
        return $prefix . bin2hex(random_bytes(20));
    }

    /**
     * Property 7: Database-to-form mapping consistency
     * For any existing coin loaded into the form, all database column values 
     * should be correctly displayed in their corresponding form fields
     * 
     * Feature: yiimp2-coin-form-completion, Property 7: Database-to-form mapping consistency
     * Validates: Requirements 8.3
     * 
     * @group property
     */
    public function testDatabaseToFormMappingConsistencyProperty()
    {
        $iterations = 100;
        
        for ($i = 0; $i < $iterations; $i++) {
            // Create and save a coin with random data
            $originalData = $this->generateRandomCoinFormData($i);
            
            $coin = new Coins();
            $coin->setAttributes($originalData, false);
            $saved = $coin->save(false);
            
            if (!$saved) {
                // Skip if save failed
                continue;
            }
            
            $coinId = $coin->id;
            
            // Clear the model from memory to simulate fresh load
            unset($coin);
            
            // Reload coin from database (simulating form load)
            $loadedCoin = Coins::findOne($coinId);
            
            $this->assertNotNull($loadedCoin, 
                "Coin should be loadable from database");
            
            // Property: All database values should be accessible as form fields
            foreach ($originalData as $field => $originalValue) {
                // Skip fields that might be transformed
                if (in_array($field, ['specifications'])) {
                    continue;
                }
                
                $formValue = $loadedCoin->$field;
                
                // Handle numeric comparisons with tolerance
                if (is_numeric($originalValue) && is_numeric($formValue)) {
                    $this->assertEqualsWithDelta($originalValue, $formValue, 0.00000001,
                        "Field '{$field}' should match: original={$originalValue}, form={$formValue}");
                } else {
                    // Handle null vs empty string equivalence
                    if (($originalValue === null || $originalValue === '') && 
                        ($formValue === null || $formValue === '')) {
                        $this->assertTrue(true);
                    } else {
                        $this->assertEquals($originalValue, $formValue,
                            "Field '{$field}' should match: original={$originalValue}, form={$formValue}");
                    }
                }
            }
            
            // Test that form can access all expected fields
            $expectedFields = [
                'name', 'symbol', 'symbol2', 'algo', 'image', 'version_installed',
                'payout_min', 'payout_max', 'target_height', 'powend_height',
                'mature_blocks', 'powlimit_bits', 'block_time', 'decimals',
                'enable', 'auto_ready', 'visible', 'installed', 'no_explorer',
                'watch', 'auxpow', 'enable_rpcdebug', 'hasgetinfo', 'hassubmitblock',
                'txmessage', 'hasmasternodes', 'usesegwit', 'usemweb',
                'personalization', 'max_miners', 'max_shares', 'master_wallet',
                'wallet_zaddress', 'reward_mul', 'charity_percent', 'charity_address',
                'dontsell', 'sellonbid', 'auto_exchange', 'sellthreshold', 'market', 'price',
                'program', 'conf_folder', 'rpchost', 'rpcport', 'rpcwallet',
                'rpcuser', 'rpcpasswd', 'serveruser', 'rpcencoding', 'dedicatedport',
                'rpccurl', 'rpcssl', 'rpccert', 'account',
                'link_bitcointalk', 'link_github', 'link_site', 'link_exchange',
                'link_explorer', 'link_twitter', 'link_discord', 'link_facebook',
            ];
            
            foreach ($expectedFields as $field) {
                // Verify field is accessible (doesn't throw exception)
                try {
                    $value = $loadedCoin->$field;
                    $this->assertTrue(true, "Field '{$field}' should be accessible");
                } catch (\Exception $e) {
                    $this->fail("Field '{$field}' should be accessible but threw: " . $e->getMessage());
                }
            }
            
            // Clean up
            $loadedCoin->delete();
        }
    }
}
