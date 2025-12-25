<?php

namespace tests\unit\components;

use Codeception\Test\Unit;
use app\components\DynamicStyleManager;

/**
 * Unit tests for DynamicStyleManager component
 * 
 * Tests the helper class for generating CSP-compliant dynamic styles.
 */
class DynamicStyleManagerTest extends Unit
{
    protected function _before()
    {
        parent::_before();
    }
    
    protected function _after()
    {
        parent::_after();
    }
    
    /**
     * Test generateAlgoColorVars with valid algorithm data
     * 
     * @test
     */
    public function testGenerateAlgoColorVarsWithValidData()
    {
        $algos = [
            ['name' => 'sha256', 'color' => '#ffcc00'],
            ['name' => 'scrypt', 'color' => '#00ccff'],
            ['name' => 'x11', 'color' => '#ff00cc'],
        ];
        
        $result = DynamicStyleManager::generateAlgoColorVars($algos);
        
        $this->assertStringContainsString('--algo-color-sha256: #ffcc00;', $result);
        $this->assertStringContainsString('--algo-color-scrypt: #00ccff;', $result);
        $this->assertStringContainsString('--algo-color-x11: #ff00cc;', $result);
    }
    
    /**
     * Test generateAlgoColorVars with empty array
     * 
     * @test
     */
    public function testGenerateAlgoColorVarsWithEmptyArray()
    {
        $result = DynamicStyleManager::generateAlgoColorVars([]);
        
        $this->assertEquals('', $result);
    }
    
    /**
     * Test generateAlgoColorVars with missing keys
     * 
     * @test
     */
    public function testGenerateAlgoColorVarsWithMissingKeys()
    {
        $algos = [
            ['name' => 'sha256'], // Missing color
            ['color' => '#ffcc00'], // Missing name
            ['name' => 'scrypt', 'color' => '#00ccff'], // Valid
        ];
        
        $result = DynamicStyleManager::generateAlgoColorVars($algos);
        
        // Should only include the valid entry
        $this->assertStringContainsString('--algo-color-scrypt: #00ccff;', $result);
        $this->assertStringNotContainsString('--algo-color-sha256', $result);
    }
    
    /**
     * Test generateAlgoColorVars sanitizes algorithm names
     * 
     * @test
     */
    public function testGenerateAlgoColorVarsSanitizesNames()
    {
        $algos = [
            ['name' => 'SHA-256', 'color' => '#ffcc00'], // Uppercase
            ['name' => 'yespower_ARWN', 'color' => '#00ccff'], // Underscore
            ['name' => 'x11 gost', 'color' => '#ff00cc'], // Space
        ];
        
        $result = DynamicStyleManager::generateAlgoColorVars($algos);
        
        $this->assertStringContainsString('--algo-color-sha-256: #ffcc00;', $result);
        $this->assertStringContainsString('--algo-color-yespower-arwn: #00ccff;', $result);
        $this->assertStringContainsString('--algo-color-x11-gost: #ff00cc;', $result);
    }
    
    /**
     * Test generateAlgoColorVars with invalid color formats
     * 
     * @test
     */
    public function testGenerateAlgoColorVarsWithInvalidColors()
    {
        $algos = [
            ['name' => 'sha256', 'color' => 'invalid'], // Invalid format
            ['name' => 'scrypt', 'color' => 'rgb(255,0,0)'], // RGB format
            ['name' => 'x11', 'color' => '#ff00cc'], // Valid
        ];
        
        $result = DynamicStyleManager::generateAlgoColorVars($algos);
        
        // Invalid colors should be replaced with fallback
        $this->assertStringContainsString('--algo-color-sha256: #cccccc;', $result);
        $this->assertStringContainsString('--algo-color-scrypt: #cccccc;', $result);
        $this->assertStringContainsString('--algo-color-x11: #ff00cc;', $result);
    }
    
    /**
     * Test getAlgoColorClass with valid algorithm name
     * 
     * @test
     */
    public function testGetAlgoColorClassWithValidName()
    {
        $result = DynamicStyleManager::getAlgoColorClass('sha256');
        
        $this->assertEquals('algo-bg-sha256', $result);
    }
    
    /**
     * Test getAlgoColorClass with empty string
     * 
     * @test
     */
    public function testGetAlgoColorClassWithEmptyString()
    {
        $result = DynamicStyleManager::getAlgoColorClass('');
        
        $this->assertEquals('algo-bg-default', $result);
    }
    
    /**
     * Test getAlgoColorClass sanitizes algorithm names
     * 
     * @test
     */
    public function testGetAlgoColorClassSanitizesNames()
    {
        $this->assertEquals('algo-bg-sha-256', DynamicStyleManager::getAlgoColorClass('SHA-256'));
        $this->assertEquals('algo-bg-yespower-arwn', DynamicStyleManager::getAlgoColorClass('yespower_ARWN'));
        $this->assertEquals('algo-bg-x11-gost', DynamicStyleManager::getAlgoColorClass('x11 gost'));
    }
    
    /**
     * Test renderDynamicStyles with algorithm colors
     * 
     * @test
     */
    public function testRenderDynamicStylesWithAlgos()
    {
        $config = [
            'algos' => [
                ['name' => 'sha256', 'color' => '#ffcc00'],
                ['name' => 'scrypt', 'color' => '#00ccff'],
            ]
        ];
        
        $result = DynamicStyleManager::renderDynamicStyles($config);
        
        $this->assertStringContainsString('<style>', $result);
        $this->assertStringContainsString('</style>', $result);
        $this->assertStringContainsString(':root {', $result);
        $this->assertStringContainsString('--algo-color-sha256: #ffcc00;', $result);
        $this->assertStringContainsString('--algo-color-scrypt: #00ccff;', $result);
    }
    
    /**
     * Test renderDynamicStyles with chart height
     * 
     * @test
     */
    public function testRenderDynamicStylesWithChartHeight()
    {
        $config = [
            'chartHeight' => 240
        ];
        
        $result = DynamicStyleManager::renderDynamicStyles($config);
        
        $this->assertStringContainsString('<style>', $result);
        $this->assertStringContainsString('--chart-height-custom: 240px;', $result);
    }
    
    /**
     * Test renderDynamicStyles with both algos and chart height
     * 
     * @test
     */
    public function testRenderDynamicStylesWithBoth()
    {
        $config = [
            'algos' => [
                ['name' => 'sha256', 'color' => '#ffcc00'],
            ],
            'chartHeight' => 200
        ];
        
        $result = DynamicStyleManager::renderDynamicStyles($config);
        
        $this->assertStringContainsString('--algo-color-sha256: #ffcc00;', $result);
        $this->assertStringContainsString('--chart-height-custom: 200px;', $result);
    }
    
    /**
     * Test renderDynamicStyles with empty config
     * 
     * @test
     */
    public function testRenderDynamicStylesWithEmptyConfig()
    {
        $result = DynamicStyleManager::renderDynamicStyles([]);
        
        $this->assertEquals('', $result);
    }
    
    /**
     * Test renderDynamicStyles with invalid chart height
     * 
     * @test
     */
    public function testRenderDynamicStylesWithInvalidChartHeight()
    {
        $config = [
            'chartHeight' => 'invalid'
        ];
        
        $result = DynamicStyleManager::renderDynamicStyles($config);
        
        // Should return empty string since chartHeight is not numeric
        $this->assertEquals('', $result);
    }
    
    /**
     * Test renderDynamicStyles with empty algos array
     * 
     * @test
     */
    public function testRenderDynamicStylesWithEmptyAlgos()
    {
        $config = [
            'algos' => []
        ];
        
        $result = DynamicStyleManager::renderDynamicStyles($config);
        
        $this->assertEquals('', $result);
    }
    
    /**
     * Test color sanitization with 3-character hex codes
     * 
     * @test
     */
    public function testColorSanitizationWithShortHex()
    {
        $algos = [
            ['name' => 'sha256', 'color' => '#f00'], // Short hex
            ['name' => 'scrypt', 'color' => '#0F0'], // Short hex uppercase
        ];
        
        $result = DynamicStyleManager::generateAlgoColorVars($algos);
        
        $this->assertStringContainsString('--algo-color-sha256: #f00;', $result);
        $this->assertStringContainsString('--algo-color-scrypt: #0F0;', $result);
    }
    
    /**
     * Test algorithm name sanitization edge cases
     * 
     * @test
     */
    public function testAlgoNameSanitizationEdgeCases()
    {
        $this->assertEquals('algo-bg-test', DynamicStyleManager::getAlgoColorClass('test'));
        $this->assertEquals('algo-bg-test-123', DynamicStyleManager::getAlgoColorClass('test_123'));
        $this->assertEquals('algo-bg-test-algo', DynamicStyleManager::getAlgoColorClass('test-algo'));
        $this->assertEquals('algo-bg-test-algo', DynamicStyleManager::getAlgoColorClass('test--algo'));
        $this->assertEquals('algo-bg-test-algo', DynamicStyleManager::getAlgoColorClass('--test-algo--'));
    }
    
    /**
     * Test that generated CSS is valid
     * 
     * @test
     */
    public function testGeneratedCssIsValid()
    {
        $config = [
            'algos' => [
                ['name' => 'sha256', 'color' => '#ffcc00'],
                ['name' => 'scrypt', 'color' => '#00ccff'],
            ],
            'chartHeight' => 240
        ];
        
        $result = DynamicStyleManager::renderDynamicStyles($config);
        
        // Check for proper CSS structure
        $this->assertMatchesRegularExpression('/<style>\s*:root\s*\{.*\}\s*<\/style>/s', $result);
        
        // Check that all lines end with semicolons
        $lines = explode("\n", $result);
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (!empty($trimmed) && 
                $trimmed !== '<style>' && 
                $trimmed !== ':root {' && 
                $trimmed !== '}' && 
                $trimmed !== '</style>') {
                $this->assertStringEndsWith(';', $trimmed, "Line should end with semicolon: $trimmed");
            }
        }
    }
}
