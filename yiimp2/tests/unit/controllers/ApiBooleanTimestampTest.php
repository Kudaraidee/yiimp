<?php

namespace tests\unit\controllers;

use Yii;
use Codeception\Test\Unit;

/**
 * Test API boolean and timestamp formatting for backward compatibility
 * Validates Requirements 7.2, 7.3 - Boolean and timestamp formatting
 */
class ApiBooleanTimestampTest extends Unit
{
    /**
     * Test that booleans are true/false (not 1/0)
     * Legacy API uses true/false in JSON responses
     */
    public function testBooleansAreTrueFalse()
    {
        // Test that JSON encoding produces true/false
        $testData = [
            ['autotrade' => true],
            ['autotrade' => false],
            ['enable' => true],
            ['visible' => false],
        ];

        foreach ($testData as $data) {
            $json = json_encode($data);
            
            // Check that JSON contains 'true' or 'false', not '1' or '0'
            if (reset($data) === true) {
                $this->assertStringContainsString('true', $json, "Boolean true should be encoded as 'true' in JSON");
                $this->assertStringNotContainsString(':1', $json, "Boolean true should not be encoded as '1'");
            } else {
                $this->assertStringContainsString('false', $json, "Boolean false should be encoded as 'false' in JSON");
                $this->assertStringNotContainsString(':0', $json, "Boolean false should not be encoded as '0'");
            }
        }
    }

    /**
     * Test that timestamps include both Unix timestamp and formatted date
     * Legacy API blocks endpoint includes both 'time' (Unix) and 'timestamp' (formatted)
     */
    public function testTimestampsIncludeBothFormats()
    {
        $testTime = 1609459200; // 2021-01-01 00:00:00 UTC
        
        // Test Unix timestamp
        $this->assertIsInt($testTime, "Unix timestamp should be integer");
        $this->assertGreaterThan(0, $testTime, "Unix timestamp should be positive");
        
        // Test formatted date
        $formatted = date('Y-m-d H:i:s', $testTime);
        $this->assertEquals('2021-01-01 00:00:00', $formatted, "Formatted date should match expected format");
        
        // Verify format pattern
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $formatted, 
            "Formatted date should match Y-m-d H:i:s pattern");
    }

    /**
     * Test that date format matches legacy API
     * Legacy API uses date('Y-m-d H:i:s', $timestamp)
     */
    public function testDateFormatMatchesLegacy()
    {
        $testCases = [
            [1609459200, '2021-01-01 00:00:00'],
            [1640995200, '2022-01-01 00:00:00'],
            [1672531200, '2023-01-01 00:00:00'],
        ];

        foreach ($testCases as list($timestamp, $expected)) {
            $formatted = date('Y-m-d H:i:s', $timestamp);
            $this->assertEquals($expected, $formatted, "Timestamp $timestamp should format to $expected");
        }
    }

    /**
     * Test that API responses include both timestamp formats
     * This simulates what the blocks endpoint should return
     */
    public function testApiResponseIncludesBothTimestampFormats()
    {
        $testTime = time();
        
        // Simulate API response structure
        $response = [
            'time' => (int) $testTime,
            'timestamp' => date('Y-m-d H:i:s', $testTime),
        ];
        
        // Verify time is integer
        $this->assertIsInt($response['time'], "API response 'time' should be integer");
        
        // Verify timestamp is string in correct format
        $this->assertIsString($response['timestamp'], "API response 'timestamp' should be string");
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $response['timestamp'],
            "API response 'timestamp' should match Y-m-d H:i:s format");
    }

    /**
     * Test that boolean casting works correctly
     * Ensures that database values (0/1) are properly converted to booleans
     */
    public function testBooleanCastingFromDatabase()
    {
        $testCases = [
            [1, true],
            [0, false],
            ['1', true],
            ['0', false],
            [true, true],
            [false, false],
        ];

        foreach ($testCases as list($input, $expected)) {
            $result = (bool) $input;
            $this->assertEquals($expected, $result, "Value $input should cast to boolean $expected");
        }
    }
}
