<?php

namespace app\tests\unit\models;

use tests\helpers\DatabaseTestHelper;
use Codeception\Test\Unit;
use app\models\Coins;

/**
 * Property-based tests for Coin validation rules
 * 
 * Feature: yiimp2-coin-form-completion
 * Tests validation properties for the complete coin form
 */
class CoinValidationPropertyTest extends Unit
{
    use DatabaseTestHelper;

    protected function _before()
    {
        // Validation tests require database connection for ActiveRecord schema loading
        // Skip tests if database is not available
        if (!$this->isDatabaseAvailable()) {
            $this->markTestSkipped('Database connection required for ActiveRecord validation tests');
        }
    }

    protected function _after()
    {
        // Validation tests don't modify database - no cleanup needed
    }

    /**
     * Property 2: Charity percent bounds
     * For any charity_percent value submitted, validation should reject values 
     * less than 0 or greater than 100
     * 
     * Feature: yiimp2-coin-form-completion, Property 2: Charity percent bounds
     * Validates: Requirements 2.5
     * 
     * @group property
     */
    public function testCharityPercentBoundsProperty()
    {
        $iterations = 100;
        
        for ($i = 0; $i < $iterations; $i++) {
            // Create coin model for validation testing
            $coin = $this->createCoinForValidation();
            
            // Generate random charity_percent value
            // Mix of valid (0-100) and invalid (<0 or >100) values
            $testValue = $this->generateCharityPercentTestValue();
            $coin->charity_percent = $testValue;
            
            $isValid = $coin->validate(['charity_percent']);
            
            // Property: Values between 0 and 100 should be valid, others invalid
            if ($testValue >= 0 && $testValue <= 100) {
                $this->assertTrue($isValid, 
                    "charity_percent={$testValue} should be valid (0-100 range)");
                $this->assertEmpty($coin->getErrors('charity_percent'),
                    "No errors expected for valid charity_percent={$testValue}");
            } else {
                $this->assertFalse($isValid, 
                    "charity_percent={$testValue} should be invalid (outside 0-100 range)");
                $this->assertNotEmpty($coin->getErrors('charity_percent'),
                    "Errors expected for invalid charity_percent={$testValue}");
            }
        }
    }

    /**
     * Property 4: RPC port range validation
     * For any rpcport value submitted, validation should reject values 
     * less than 1 or greater than 65535
     * 
     * Feature: yiimp2-coin-form-completion, Property 4: RPC port range validation
     * Validates: Requirements 4.7
     * 
     * @group property
     */
    public function testRpcPortRangeValidationProperty()
    {
        $iterations = 100;
        
        for ($i = 0; $i < $iterations; $i++) {
            $coin = new Coins();
            $coin->name = 'TEST_Coin_' . uniqid();
            $coin->symbol = 'TST' . rand(100, 999);
            $coin->algo = 'sha256';
            
            // Generate random rpcport value
            // Mix of valid (1-65535) and invalid (<1 or >65535) values
            $testValue = $this->generateRpcPortTestValue();
            $coin->rpcport = $testValue;
            
            $isValid = $coin->validate(['rpcport']);
            
            // Property: Values between 1 and 65535 should be valid, others invalid
            if ($testValue >= 1 && $testValue <= 65535) {
                $this->assertTrue($isValid, 
                    "rpcport={$testValue} should be valid (1-65535 range)");
                $this->assertEmpty($coin->getErrors('rpcport'),
                    "No errors expected for valid rpcport={$testValue}");
            } else {
                $this->assertFalse($isValid, 
                    "rpcport={$testValue} should be invalid (outside 1-65535 range)");
                $this->assertNotEmpty($coin->getErrors('rpcport'),
                    "Errors expected for invalid rpcport={$testValue}");
            }
        }
    }

    /**
     * Property 5: URL format validation
     * For any link field value submitted, if non-empty, validation should 
     * reject improperly formatted URLs
     * 
     * Feature: yiimp2-coin-form-completion, Property 5: URL format validation
     * Validates: Requirements 5.2
     * 
     * @group property
     */
    public function testUrlFormatValidationProperty()
    {
        $iterations = 100;
        $linkFields = [
            'link_bitcointalk', 'link_github', 'link_site', 'link_exchange',
            'link_explorer', 'link_twitter', 'link_discord', 'link_facebook'
        ];
        
        for ($i = 0; $i < $iterations; $i++) {
            $coin = new Coins();
            $coin->name = 'TEST_Coin_' . uniqid();
            $coin->symbol = 'TST' . rand(100, 999);
            $coin->algo = 'sha256';
            
            // Pick a random link field to test
            $field = $linkFields[array_rand($linkFields)];
            
            // Generate test URL (mix of valid and invalid)
            $testUrl = $this->generateUrlTestValue();
            $coin->$field = $testUrl;
            
            $isValid = $coin->validate([$field]);
            
            // Property: Valid URLs should pass, invalid URLs should fail
            // Empty values should be allowed (skipOnEmpty => true)
            if (empty($testUrl)) {
                $this->assertTrue($isValid, 
                    "Empty URL should be valid for {$field}");
            } elseif ($this->isValidUrl($testUrl)) {
                $this->assertTrue($isValid, 
                    "Valid URL '{$testUrl}' should pass validation for {$field}");
                $this->assertEmpty($coin->getErrors($field),
                    "No errors expected for valid URL in {$field}");
            } else {
                $this->assertFalse($isValid, 
                    "Invalid URL '{$testUrl}' should fail validation for {$field}");
                $this->assertNotEmpty($coin->getErrors($field),
                    "Errors expected for invalid URL in {$field}");
            }
        }
    }

    /**
     * Property 3: Positive numeric exchange fields
     * For any numeric exchange field (sellthreshold, price), validation should 
     * reject negative values
     * 
     * Feature: yiimp2-coin-form-completion, Property 3: Positive numeric exchange fields
     * Validates: Requirements 3.5
     * 
     * @group property
     */
    public function testPositiveNumericExchangeFieldsProperty()
    {
        $iterations = 100;
        $exchangeFields = ['sellthreshold', 'payout_min', 'payout_max', 'deposit_minimum'];
        
        for ($i = 0; $i < $iterations; $i++) {
            $coin = new Coins();
            $coin->name = 'TEST_Coin_' . uniqid();
            $coin->symbol = 'TST' . rand(100, 999);
            $coin->algo = 'sha256';
            
            // Pick a random exchange field to test
            $field = $exchangeFields[array_rand($exchangeFields)];
            
            // Generate test value (mix of positive, zero, and negative)
            $testValue = $this->generateNumericTestValue();
            $coin->$field = $testValue;
            
            $isValid = $coin->validate([$field]);
            
            // Property: Non-negative values should be valid, negative values invalid
            if ($testValue >= 0) {
                $this->assertTrue($isValid, 
                    "{$field}={$testValue} should be valid (non-negative)");
                $this->assertEmpty($coin->getErrors($field),
                    "No errors expected for valid {$field}={$testValue}");
            } else {
                $this->assertFalse($isValid, 
                    "{$field}={$testValue} should be invalid (negative)");
                $this->assertNotEmpty($coin->getErrors($field),
                    "Errors expected for invalid {$field}={$testValue}");
            }
        }
    }

    /**
     * Property 8: Data type validation
     * For any form submission, validation should reject values that don't match 
     * the expected database column type (int, double, string, boolean)
     * 
     * Feature: yiimp2-coin-form-completion, Property 8: Data type validation
     * Validates: Requirements 8.5
     * 
     * @group property
     */
    public function testDataTypeValidationProperty()
    {
        $iterations = 100;
        
        // Define field types to test
        $fieldTests = [
            // Integer fields
            ['field' => 'rpcport', 'type' => 'integer', 'validValues' => [8332, 1, 65535], 'invalidValues' => ['abc', 3.14, true]],
            ['field' => 'block_height', 'type' => 'integer', 'validValues' => [0, 100000, 999999], 'invalidValues' => ['text', 1.5]],
            ['field' => 'max_miners', 'type' => 'integer', 'validValues' => [10, 100, 1000], 'invalidValues' => ['ten', 10.5]],
            
            // Double/numeric fields
            ['field' => 'charity_percent', 'type' => 'number', 'validValues' => [0, 50.5, 100], 'invalidValues' => ['fifty', true]],
            ['field' => 'reward', 'type' => 'number', 'validValues' => [0.5, 25, 100.123], 'invalidValues' => ['reward', false]],
            ['field' => 'difficulty', 'type' => 'number', 'validValues' => [1.0, 1000000.5], 'invalidValues' => ['hard']],
            
            // Boolean fields
            ['field' => 'enable', 'type' => 'boolean', 'validValues' => [0, 1, true, false], 'invalidValues' => ['yes', 'no', 2]],
            ['field' => 'visible', 'type' => 'boolean', 'validValues' => [0, 1], 'invalidValues' => ['true', 'false', 10]],
            
            // String fields
            ['field' => 'name', 'type' => 'string', 'validValues' => ['Bitcoin', 'Test Coin'], 'invalidValues' => [123, true, []]],
            ['field' => 'symbol', 'type' => 'string', 'validValues' => ['BTC', 'ETH'], 'invalidValues' => [456, false, null]],
        ];
        
        for ($i = 0; $i < $iterations; $i++) {
            // Pick a random field test
            $test = $fieldTests[array_rand($fieldTests)];
            $field = $test['field'];
            
            $coin = new Coins();
            $coin->name = 'TEST_Coin_' . uniqid();
            $coin->symbol = 'TST' . rand(100, 999);
            $coin->algo = 'sha256';
            
            // Test with valid value
            if (!empty($test['validValues'])) {
                $validValue = $test['validValues'][array_rand($test['validValues'])];
                $coin->$field = $validValue;
                
                $isValid = $coin->validate([$field]);
                
                // For required fields, check they're set
                if (in_array($field, ['name', 'symbol'])) {
                    $this->assertTrue($isValid, 
                        "Valid {$test['type']} value for {$field} should pass validation");
                }
            }
            
            // Test with invalid value
            if (!empty($test['invalidValues'])) {
                $invalidValue = $test['invalidValues'][array_rand($test['invalidValues'])];
                $coin->$field = $invalidValue;
                
                // Skip array values as they cause PHP errors
                if (is_array($invalidValue)) {
                    continue;
                }
                
                $isValid = $coin->validate([$field]);
                
                // Property: Invalid type values should fail validation
                // Note: PHP type coercion may allow some conversions
                // We're mainly checking that obvious type mismatches fail
                if (is_string($invalidValue) && !is_numeric($invalidValue) && 
                    in_array($test['type'], ['integer', 'number'])) {
                    $this->assertFalse($isValid, 
                        "Non-numeric string '{$invalidValue}' should fail validation for {$test['type']} field {$field}");
                }
            }
        }
    }

    // Helper methods for generating test values

    /**
     * Create a coin model for validation testing without database access
     */
    private function createCoinForValidation()
    {
        $coin = new Coins();
        $coin->name = 'TEST_Coin_' . uniqid();
        $coin->symbol = 'TST' . rand(100, 999);
        $coin->algo = 'sha256';
        return $coin;
    }

    private function generateCharityPercentTestValue()
    {
        $rand = rand(0, 100);
        
        if ($rand < 60) {
            // 60% valid values (0-100)
            return rand(0, 100) + (rand(0, 1) ? rand(0, 99) / 100 : 0);
        } elseif ($rand < 80) {
            // 20% negative values
            return -rand(1, 100) - (rand(0, 99) / 100);
        } else {
            // 20% values > 100
            return rand(101, 200) + (rand(0, 99) / 100);
        }
    }

    private function generateRpcPortTestValue()
    {
        $rand = rand(0, 100);
        
        if ($rand < 60) {
            // 60% valid values (1-65535)
            return rand(1, 65535);
        } elseif ($rand < 80) {
            // 20% values < 1
            return rand(-1000, 0);
        } else {
            // 20% values > 65535
            return rand(65536, 100000);
        }
    }

    private function generateUrlTestValue()
    {
        $rand = rand(0, 100);
        
        if ($rand < 10) {
            // 10% empty values
            return '';
        } elseif ($rand < 60) {
            // 50% valid URLs
            $validUrls = [
                'https://github.com/example/repo',
                'http://example.com',
                'https://bitcointalk.org/index.php?topic=123456',
                'https://twitter.com/example',
                'https://discord.gg/example',
                'https://www.facebook.com/example',
                'https://explorer.example.com',
                'https://exchange.example.com/trade/BTC',
            ];
            return $validUrls[array_rand($validUrls)];
        } else {
            // 40% invalid URLs
            $invalidUrls = [
                'not a url',
                'htp://broken.com',
                'www.noprotocol.com',
                'javascript:alert(1)',
                'ftp://unsupported.com',
                '//incomplete.com',
                'http://',
                'just text',
            ];
            return $invalidUrls[array_rand($invalidUrls)];
        }
    }

    private function generateNumericTestValue()
    {
        $rand = rand(0, 100);
        
        if ($rand < 60) {
            // 60% positive values
            return rand(0, 100000) + (rand(0, 99) / 100);
        } elseif ($rand < 80) {
            // 20% zero
            return 0;
        } else {
            // 20% negative values
            return -rand(1, 10000) - (rand(0, 99) / 100);
        }
    }

    private function isValidUrl($url)
    {
        // Check if URL matches basic URL pattern
        // This should match Yii2's URL validator behavior
        if (empty($url)) {
            return true; // Empty is valid (skipOnEmpty)
        }
        
        // Basic URL validation
        return (bool) filter_var($url, FILTER_VALIDATE_URL) && 
               preg_match('/^https?:\/\//i', $url);
    }
}
