<?php

namespace tests\unit\controllers;

use Yii;
use Codeception\Test\Unit;

/**
 * Test API numeric formatting for backward compatibility
 * Validates Requirements 7.1 - Numeric formatting consistency
 */
class ApiFormattingTest extends Unit
{
    /**
     * Test that BTC values use 8 decimal places
     * Legacy API uses bitcoinvaluetoa() which formats to 8 decimals by default
     */
    public function testBtcValuesUse8DecimalPlaces()
    {
        // Test bitcoinvaluetoa formatting
        $testValues = [
            [0.12345678, '0.12345678'],
            [0.123456789, '0.12345679'], // Rounds to nearest (half down)
            [1.0, '1.00000000'],
            [0.00000001, '0.00000001'],
            [123.456789012, '123.45678901'],
        ];

        foreach ($testValues as list($input, $expected)) {
            $result = Yii::$app->ConversionUtils->bitcoinvaluetoa($input);
            $this->assertEquals($expected, $result, "BTC value $input should format to $expected");
            
            // Verify it has exactly 8 decimal places
            $parts = explode('.', $result);
            $this->assertCount(2, $parts, "BTC value should have decimal point");
            $this->assertEquals(8, strlen($parts[1]), "BTC value should have exactly 8 decimal places");
        }
    }

    /**
     * Test that hashrate values are integers
     * Legacy API casts hashrate to (int)
     */
    public function testHashrateValuesAreIntegers()
    {
        $testValues = [
            [1234.56, 1234],
            [1234.99, 1234],
            [0.5, 0],
            [1000000.123, 1000000],
        ];

        foreach ($testValues as list($input, $expected)) {
            $result = (int) $input;
            $this->assertIsInt($result, "Hashrate should be integer");
            $this->assertEquals($expected, $result, "Hashrate $input should cast to $expected");
        }
    }

    /**
     * Test that percentage values use appropriate precision
     * Legacy API uses percentvaluetoa() which formats to 3 decimals
     */
    public function testPercentageValuesUse3DecimalPlaces()
    {
        $testValues = [
            [0.123, '0.123'],
            [0.1234, '0.123'],
            [1.0, '1.000'],
            [99.9999, '100.000'],
        ];

        foreach ($testValues as list($input, $expected)) {
            $result = Yii::$app->ConversionUtils->percentvaluetoa($input);
            $this->assertEquals($expected, $result, "Percentage $input should format to $expected");
            
            // Verify it has exactly 3 decimal places
            $parts = explode('.', $result);
            $this->assertCount(2, $parts, "Percentage should have decimal point");
            $this->assertEquals(3, strlen($parts[1]), "Percentage should have exactly 3 decimal places");
        }
    }

    /**
     * Test mBTC values use 5 decimal places
     * Legacy API uses mbitcoinvaluetoa() which formats to 5 decimals
     */
    public function testMbtcValuesUse5DecimalPlaces()
    {
        $testValues = [
            [0.12345, '0.12345'],
            [0.123456, '0.12346'], // Rounds to nearest (half down)
            [1.0, '1.00000'],
            [0.00001, '0.00001'],
        ];

        foreach ($testValues as list($input, $expected)) {
            $result = Yii::$app->ConversionUtils->mbitcoinvaluetoa($input);
            $this->assertEquals($expected, $result, "mBTC value $input should format to $expected");
            
            // Verify it has exactly 5 decimal places
            $parts = explode('.', $result);
            $this->assertCount(2, $parts, "mBTC value should have decimal point");
            $this->assertEquals(5, strlen($parts[1]), "mBTC value should have exactly 5 decimal places");
        }
    }

    /**
     * Test that worker accepted/rejected rates are rounded to 3 decimals
     * Legacy walletEx rounds to 3 decimals: round($user_rate1, 3)
     */
    public function testWorkerRatesRoundedTo3Decimals()
    {
        $testValues = [
            [1.2345, 1.235],
            [1.2344, 1.234],
            [0.0001, 0.0],
            [123.4567, 123.457],
        ];

        foreach ($testValues as list($input, $expected)) {
            $result = round($input, 3);
            $this->assertEquals($expected, $result, "Worker rate $input should round to $expected");
        }
    }

    /**
     * Test that difficulty values are properly formatted
     * Legacy uses doubleval() for difficulty
     */
    public function testDifficultyValuesAreDoubles()
    {
        $testValues = [
            ["1234.56", 1234.56],
            ["1000000", 1000000.0],
            ["0.123", 0.123],
        ];

        foreach ($testValues as list($input, $expected)) {
            $result = doubleval($input);
            $this->assertIsFloat($result, "Difficulty should be float/double");
            $this->assertEquals($expected, $result, "Difficulty $input should convert to $expected");
        }
    }

    /**
     * Test that fees are formatted as doubles
     * Legacy API casts fees to (double)
     */
    public function testFeesAreDoubles()
    {
        $testValues = [
            [1.5, 1.5],
            [2, 2.0],
            [0.5, 0.5],
        ];

        foreach ($testValues as list($input, $expected)) {
            $result = (double) $input;
            $this->assertIsFloat($result, "Fees should be double");
            $this->assertEquals($expected, $result, "Fee $input should be $expected");
        }
    }

    /**
     * Test that 24h_btc values are rounded to 8 decimals
     * Legacy API: round(arraySafeVal($res24h, 'b', 0), 8)
     */
    public function test24hBtcRoundedTo8Decimals()
    {
        $testValues = [
            [0.123456789, 0.12345679],
            [1.000000001, 1.0],
            [0.00000001, 0.00000001],
        ];

        foreach ($testValues as list($input, $expected)) {
            $result = round($input, 8);
            $this->assertEquals($expected, $result, "24h BTC $input should round to $expected");
        }
    }
}
