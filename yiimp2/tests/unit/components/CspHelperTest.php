<?php

namespace tests\unit\components;

use Codeception\Test\Unit;
use app\components\CspHelper;
use Yii;

/**
 * Unit tests for CspHelper component
 * 
 * Tests the helper class for adding CSP nonces to inline scripts and styles.
 * 
 * Requirements: 2.1, 2.2, 2.3, 2.4, 2.5
 */
class CspHelperTest extends Unit
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
     * Test getNonce() retrieves correct value from CspNonceManager
     * 
     * @test
     * Requirements: 2.1, 2.5
     */
    public function testGetNonceRetrievesCorrectValue()
    {
        // Mock the CspNonceManager component
        $mockNonceManager = $this->getMockBuilder(\app\components\CspNonceManager::class)
            ->onlyMethods(['getNonce'])
            ->getMock();
        
        $mockNonceManager->expects($this->once())
            ->method('getNonce')
            ->willReturn('test-nonce-12345');
        
        // Set the mock in the application
        Yii::$app->set('cspNonce', $mockNonceManager);
        
        $result = CspHelper::getNonce();
        
        $this->assertEquals('test-nonce-12345', $result);
    }
    
    /**
     * Test getNonce() returns null when CspNonceManager is not available
     * 
     * @test
     * Requirements: 2.1, 2.5
     */
    public function testGetNonceReturnsNullWhenNotAvailable()
    {
        // Remove the cspNonce component if it exists
        if (Yii::$app->has('cspNonce')) {
            Yii::$app->clear('cspNonce');
        }
        
        // Clear view params
        if (isset(Yii::$app->view->params['cspNonce'])) {
            unset(Yii::$app->view->params['cspNonce']);
        }
        
        $result = CspHelper::getNonce();
        
        $this->assertNull($result);
    }
    
    /**
     * Test getNonce() falls back to view params
     * 
     * @test
     * Requirements: 2.1, 2.5
     */
    public function testGetNonceFallsBackToViewParams()
    {
        // Remove the cspNonce component if it exists
        if (Yii::$app->has('cspNonce')) {
            Yii::$app->clear('cspNonce');
        }
        
        // Set nonce in view params
        Yii::$app->view->params['cspNonce'] = 'view-param-nonce-67890';
        
        $result = CspHelper::getNonce();
        
        $this->assertEquals('view-param-nonce-67890', $result);
        
        // Clean up
        unset(Yii::$app->view->params['cspNonce']);
    }
    
    /**
     * Test beginScript() includes nonce attribute
     * 
     * @test
     * Requirements: 2.2
     */
    public function testBeginScriptIncludesNonceAttribute()
    {
        // Mock the CspNonceManager component
        $mockNonceManager = $this->getMockBuilder(\app\components\CspNonceManager::class)
            ->onlyMethods(['getNonce'])
            ->getMock();
        
        $mockNonceManager->method('getNonce')
            ->willReturn('test-nonce-abc123');
        
        Yii::$app->set('cspNonce', $mockNonceManager);
        
        $result = CspHelper::beginScript();
        
        $this->assertStringContainsString('nonce="test-nonce-abc123"', $result);
        $this->assertStringStartsWith('<script', $result);
        $this->assertStringEndsWith('>', $result);
    }
    
    /**
     * Test beginScript() without nonce when not available
     * 
     * @test
     * Requirements: 2.2
     */
    public function testBeginScriptWithoutNonceWhenNotAvailable()
    {
        // Remove the cspNonce component
        if (Yii::$app->has('cspNonce')) {
            Yii::$app->clear('cspNonce');
        }
        
        if (isset(Yii::$app->view->params['cspNonce'])) {
            unset(Yii::$app->view->params['cspNonce']);
        }
        
        $result = CspHelper::beginScript();
        
        $this->assertEquals('<script>', $result);
        $this->assertStringNotContainsString('nonce', $result);
    }
    
    /**
     * Test beginScript() with additional options
     * 
     * @test
     * Requirements: 2.2
     */
    public function testBeginScriptWithAdditionalOptions()
    {
        // Mock the CspNonceManager component
        $mockNonceManager = $this->getMockBuilder(\app\components\CspNonceManager::class)
            ->onlyMethods(['getNonce'])
            ->getMock();
        
        $mockNonceManager->method('getNonce')
            ->willReturn('test-nonce-xyz789');
        
        Yii::$app->set('cspNonce', $mockNonceManager);
        
        $result = CspHelper::beginScript(['type' => 'module', 'async' => true]);
        
        $this->assertStringContainsString('nonce="test-nonce-xyz789"', $result);
        $this->assertStringContainsString('type="module"', $result);
        $this->assertStringContainsString('async', $result);
    }
    
    /**
     * Test beginScript() escapes HTML in nonce value
     * 
     * @test
     * Requirements: 2.2, 2.5
     */
    public function testBeginScriptEscapesHtmlInNonce()
    {
        // Mock with a nonce containing special characters
        $mockNonceManager = $this->getMockBuilder(\app\components\CspNonceManager::class)
            ->onlyMethods(['getNonce'])
            ->getMock();
        
        $mockNonceManager->method('getNonce')
            ->willReturn('test"nonce<script>');
        
        Yii::$app->set('cspNonce', $mockNonceManager);
        
        $result = CspHelper::beginScript();
        
        $this->assertStringContainsString('nonce="test&quot;nonce&lt;script&gt;"', $result);
        $this->assertStringNotContainsString('nonce="test"nonce<script>"', $result);
    }
    
    /**
     * Test endScript() returns closing tag
     * 
     * @test
     * Requirements: 2.3
     */
    public function testEndScriptReturnsClosingTag()
    {
        $result = CspHelper::endScript();
        
        $this->assertEquals('</script>', $result);
    }
    
    /**
     * Test script() wraps JavaScript correctly
     * 
     * @test
     * Requirements: 2.4
     */
    public function testScriptWrapsJavaScriptCorrectly()
    {
        // Mock the CspNonceManager component
        $mockNonceManager = $this->getMockBuilder(\app\components\CspNonceManager::class)
            ->onlyMethods(['getNonce'])
            ->getMock();
        
        $mockNonceManager->method('getNonce')
            ->willReturn('test-nonce-wrap123');
        
        Yii::$app->set('cspNonce', $mockNonceManager);
        
        $js = "console.log('Hello World');";
        $result = CspHelper::script($js);
        
        $this->assertStringStartsWith('<script nonce="test-nonce-wrap123">', $result);
        $this->assertStringContainsString($js, $result);
        $this->assertStringEndsWith('</script>', $result);
    }
    
    /**
     * Test script() with empty JavaScript
     * 
     * @test
     * Requirements: 2.4
     */
    public function testScriptWithEmptyJavaScript()
    {
        // Mock the CspNonceManager component
        $mockNonceManager = $this->getMockBuilder(\app\components\CspNonceManager::class)
            ->onlyMethods(['getNonce'])
            ->getMock();
        
        $mockNonceManager->method('getNonce')
            ->willReturn('test-nonce-empty');
        
        Yii::$app->set('cspNonce', $mockNonceManager);
        
        $result = CspHelper::script('');
        
        $this->assertStringStartsWith('<script nonce="test-nonce-empty">', $result);
        $this->assertStringEndsWith('</script>', $result);
    }
    
    /**
     * Test script() with multiline JavaScript
     * 
     * @test
     * Requirements: 2.4
     */
    public function testScriptWithMultilineJavaScript()
    {
        // Mock the CspNonceManager component
        $mockNonceManager = $this->getMockBuilder(\app\components\CspNonceManager::class)
            ->onlyMethods(['getNonce'])
            ->getMock();
        
        $mockNonceManager->method('getNonce')
            ->willReturn('test-nonce-multiline');
        
        Yii::$app->set('cspNonce', $mockNonceManager);
        
        $js = "document.addEventListener('DOMContentLoaded', function() {\n    console.log('Ready');\n});";
        $result = CspHelper::script($js);
        
        $this->assertStringContainsString('nonce="test-nonce-multiline"', $result);
        $this->assertStringContainsString($js, $result);
        $this->assertStringContainsString("\n", $result);
    }
    
    /**
     * Test script() with additional options
     * 
     * @test
     * Requirements: 2.4
     */
    public function testScriptWithAdditionalOptions()
    {
        // Mock the CspNonceManager component
        $mockNonceManager = $this->getMockBuilder(\app\components\CspNonceManager::class)
            ->onlyMethods(['getNonce'])
            ->getMock();
        
        $mockNonceManager->method('getNonce')
            ->willReturn('test-nonce-options');
        
        Yii::$app->set('cspNonce', $mockNonceManager);
        
        $js = "console.log('test');";
        $result = CspHelper::script($js, ['type' => 'module']);
        
        $this->assertStringContainsString('nonce="test-nonce-options"', $result);
        $this->assertStringContainsString('type="module"', $result);
        $this->assertStringContainsString($js, $result);
    }
    
    /**
     * Test beginStyle() includes nonce attribute
     * 
     * @test
     * Requirements: 2.2
     */
    public function testBeginStyleIncludesNonceAttribute()
    {
        // Mock the CspNonceManager component
        $mockNonceManager = $this->getMockBuilder(\app\components\CspNonceManager::class)
            ->onlyMethods(['getNonce'])
            ->getMock();
        
        $mockNonceManager->method('getNonce')
            ->willReturn('test-nonce-style123');
        
        Yii::$app->set('cspNonce', $mockNonceManager);
        
        $result = CspHelper::beginStyle();
        
        $this->assertStringContainsString('nonce="test-nonce-style123"', $result);
        $this->assertStringStartsWith('<style', $result);
        $this->assertStringEndsWith('>', $result);
    }
    
    /**
     * Test endStyle() returns closing tag
     * 
     * @test
     * Requirements: 2.3
     */
    public function testEndStyleReturnsClosingTag()
    {
        $result = CspHelper::endStyle();
        
        $this->assertEquals('</style>', $result);
    }
    
    /**
     * Test style() wraps CSS correctly
     * 
     * @test
     * Requirements: 2.4
     */
    public function testStyleWrapsCssCorrectly()
    {
        // Mock the CspNonceManager component
        $mockNonceManager = $this->getMockBuilder(\app\components\CspNonceManager::class)
            ->onlyMethods(['getNonce'])
            ->getMock();
        
        $mockNonceManager->method('getNonce')
            ->willReturn('test-nonce-css123');
        
        Yii::$app->set('cspNonce', $mockNonceManager);
        
        $css = ".test { color: red; }";
        $result = CspHelper::style($css);
        
        $this->assertStringStartsWith('<style nonce="test-nonce-css123">', $result);
        $this->assertStringContainsString($css, $result);
        $this->assertStringEndsWith('</style>', $result);
    }
    
    /**
     * Test that nonce attribute is not duplicated when passed in options
     * 
     * @test
     * Requirements: 2.2
     */
    public function testNonceAttributeNotDuplicatedInOptions()
    {
        // Mock the CspNonceManager component
        $mockNonceManager = $this->getMockBuilder(\app\components\CspNonceManager::class)
            ->onlyMethods(['getNonce'])
            ->getMock();
        
        $mockNonceManager->method('getNonce')
            ->willReturn('test-nonce-nodup');
        
        Yii::$app->set('cspNonce', $mockNonceManager);
        
        // Try to pass nonce in options (should be ignored)
        $result = CspHelper::beginScript(['nonce' => 'should-be-ignored', 'type' => 'module']);
        
        // Should only contain the nonce from getNonce(), not the one in options
        $this->assertStringContainsString('nonce="test-nonce-nodup"', $result);
        $this->assertStringNotContainsString('should-be-ignored', $result);
        $this->assertStringContainsString('type="module"', $result);
    }
    
    /**
     * Test HTML escaping in option values
     * 
     * @test
     * Requirements: 2.2
     */
    public function testHtmlEscapingInOptionValues()
    {
        // Mock the CspNonceManager component
        $mockNonceManager = $this->getMockBuilder(\app\components\CspNonceManager::class)
            ->onlyMethods(['getNonce'])
            ->getMock();
        
        $mockNonceManager->method('getNonce')
            ->willReturn('test-nonce-escape');
        
        Yii::$app->set('cspNonce', $mockNonceManager);
        
        $result = CspHelper::beginScript(['data-value' => 'test"value<script>']);
        
        $this->assertStringContainsString('data-value="test&quot;value&lt;script&gt;"', $result);
        $this->assertStringNotContainsString('data-value="test"value<script>"', $result);
    }
    
    /**
     * Test complete script generation flow
     * 
     * @test
     * Requirements: 2.1, 2.2, 2.3, 2.4
     */
    public function testCompleteScriptGenerationFlow()
    {
        // Mock the CspNonceManager component
        $mockNonceManager = $this->getMockBuilder(\app\components\CspNonceManager::class)
            ->onlyMethods(['getNonce'])
            ->getMock();
        
        $mockNonceManager->method('getNonce')
            ->willReturn('test-nonce-complete');
        
        Yii::$app->set('cspNonce', $mockNonceManager);
        
        // Test using beginScript/endScript
        $output = CspHelper::beginScript();
        $output .= "\nconsole.log('test');\n";
        $output .= CspHelper::endScript();
        
        $this->assertStringStartsWith('<script nonce="test-nonce-complete">', $output);
        $this->assertStringContainsString("console.log('test');", $output);
        $this->assertStringEndsWith('</script>', $output);
        
        // Test using script() method
        $output2 = CspHelper::script("console.log('test');");
        
        $this->assertStringContainsString('nonce="test-nonce-complete"', $output2);
        $this->assertStringContainsString("console.log('test');", $output2);
    }
}
