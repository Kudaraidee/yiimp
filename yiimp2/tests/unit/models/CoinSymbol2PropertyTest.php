<?php

namespace app\tests\unit\models;

use Codeception\Test\Unit;
use app\models\Coins;

/**
 * Test coin class that doesn't require database access
 * This allows us to test the getOfficialSymbol() logic without database connection
 */
class TestCoin extends Coins
{
    public $symbol;
    public $symbol2;
    
    /**
     * Override tableName to prevent database schema loading
     */
    public static function tableName()
    {
        return 'coins'; // Return table name but don't actually access it
    }
    
    /**
     * Override to prevent database access during instantiation
     */
    public function init()
    {
        // Don't call parent::init() to avoid ActiveRecord initialization
    }
}

/**
 * Property-based test for Coin symbol2 precedence
 * 
 * Feature: yiimp2-coin-form-completion
 * Tests that symbol2 takes precedence over symbol when displaying coin symbol
 */
class CoinSymbol2PropertyTest extends Unit
{
    protected function _before()
    {
        // This test doesn't require database access - it only tests the getOfficialSymbol() logic
    }

    protected function _after()
    {
        // No cleanup needed
    }

    /**
     * Property 1: Symbol2 takes precedence
     * For any coin with a non-empty symbol2 value, displaying the coin symbol 
     * should show symbol2 instead of symbol
     * 
     * Feature: yiimp2-coin-form-completion, Property 1: Symbol2 takes precedence
     * Validates: Requirements 1.2
     * 
     * @group property
     */
    public function testSymbol2TakesPrecedenceProperty()
    {
        $iterations = 100;
        
        for ($i = 0; $i < $iterations; $i++) {
            // Create a mock coin object to avoid database access
            $coin = $this->createMockCoin();
            
            // Generate random symbol and symbol2 values
            $symbol = $this->generateRandomSymbol();
            $symbol2 = $this->generateRandomSymbol2();
            
            $coin->symbol = $symbol;
            $coin->symbol2 = $symbol2;
            
            // Get the official symbol
            $officialSymbol = $coin->getOfficialSymbol();
            
            // Property: If symbol2 has a meaningful value (not empty/null/whitespace), it should be returned
            // Otherwise, symbol should be returned
            $symbol2Trimmed = trim((string)$symbol2);
            if (!empty($symbol2Trimmed)) {
                $this->assertEquals($symbol2, $officialSymbol,
                    "When symbol2='{$symbol2}' is set, getOfficialSymbol() should return symbol2, not symbol='{$symbol}'");
            } else {
                $this->assertEquals($symbol, $officialSymbol,
                    "When symbol2 is empty/null/whitespace, getOfficialSymbol() should return symbol='{$symbol}'");
            }
            
            // Also test the symbol_show virtual property
            $symbolShow = $coin->getSymbol_show();
            $this->assertEquals($officialSymbol, $symbolShow,
                "symbol_show should match getOfficialSymbol()");
        }
    }

    /**
     * Test that symbol2 precedence works with various edge cases
     * 
     * @group property
     */
    public function testSymbol2PrecedenceEdgeCases()
    {
        // Test case 1: Both symbol and symbol2 are set
        $coin = $this->createMockCoin();
        $coin->symbol = 'BTC';
        $coin->symbol2 = 'XBT';
        $this->assertEquals('XBT', $coin->getOfficialSymbol(),
            "symbol2 should take precedence when both are set");
        
        // Test case 2: Only symbol is set
        $coin = $this->createMockCoin();
        $coin->symbol = 'BTC';
        $coin->symbol2 = '';
        $this->assertEquals('BTC', $coin->getOfficialSymbol(),
            "symbol should be used when symbol2 is empty string");
        
        // Test case 3: symbol2 is null
        $coin = $this->createMockCoin();
        $coin->symbol = 'BTC';
        $coin->symbol2 = null;
        $this->assertEquals('BTC', $coin->getOfficialSymbol(),
            "symbol should be used when symbol2 is null");
        
        // Test case 4: symbol2 is whitespace only
        $coin = $this->createMockCoin();
        $coin->symbol = 'BTC';
        $coin->symbol2 = '   ';
        $this->assertEquals('BTC', $coin->getOfficialSymbol(),
            "symbol should be used when symbol2 is only whitespace");
        
        // Test case 5: symbol2 is '0' (edge case for empty check)
        $coin = $this->createMockCoin();
        $coin->symbol = 'BTC';
        $coin->symbol2 = '0';
        $this->assertEquals('0', $coin->getOfficialSymbol(),
            "symbol2='0' should be treated as non-empty and take precedence");
    }

    // Helper methods for generating test values

    /**
     * Create a test Coins object that doesn't require database access
     */
    private function createMockCoin()
    {
        return new TestCoin();
    }

    /**
     * Generate a random symbol (1-16 characters)
     */
    private function generateRandomSymbol()
    {
        $length = rand(1, 16);
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $symbol = '';
        
        for ($i = 0; $i < $length; $i++) {
            $symbol .= $characters[rand(0, strlen($characters) - 1)];
        }
        
        return $symbol;
    }

    /**
     * Generate a random symbol2 value (can be empty, null, or a valid symbol)
     */
    private function generateRandomSymbol2()
    {
        $rand = rand(0, 100);
        
        if ($rand < 30) {
            // 30% empty string
            return '';
        } elseif ($rand < 35) {
            // 5% null
            return null;
        } elseif ($rand < 40) {
            // 5% whitespace
            return '   ';
        } else {
            // 60% valid symbol
            return $this->generateRandomSymbol();
        }
    }
}
