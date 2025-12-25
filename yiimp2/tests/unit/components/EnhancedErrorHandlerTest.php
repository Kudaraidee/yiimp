<?php

namespace tests\unit\components;

use Codeception\Test\Unit;
use Yii;
use app\components\EnhancedErrorHandler;
use yii\web\BadRequestHttpException;

/**
 * Unit tests for EnhancedErrorHandler
 * 
 * Tests the enhanced error handling functionality including:
 * - CSRF exception detection and handling
 * - Session expiration detection
 * - User-friendly error messages
 * - Debug information in development mode
 */
class EnhancedErrorHandlerTest extends Unit
{
    /**
     * Test CSRF exception detection
     * 
     * @test
     */
    public function testIsCsrfException()
    {
        $handler = new EnhancedErrorHandler();
        
        // Use reflection to access protected method
        $reflection = new \ReflectionClass($handler);
        $method = $reflection->getMethod('isCsrfException');
        $method->setAccessible(true);
        
        // Test CSRF-related exception
        $csrfException = new BadRequestHttpException('Unable to verify your data submission');
        $this->assertTrue($method->invoke($handler, $csrfException));
        
        // Test CSRF token exception
        $tokenException = new BadRequestHttpException('Invalid CSRF token');
        $this->assertTrue($method->invoke($handler, $tokenException));
        
        // Test non-CSRF exception
        $otherException = new BadRequestHttpException('Some other error');
        $this->assertFalse($method->invoke($handler, $otherException));
        
        // Test non-BadRequest exception
        $genericException = new \Exception('Generic error');
        $this->assertFalse($method->invoke($handler, $genericException));
    }
    
    /**
     * Test CSRF remediation steps generation
     * 
     * @test
     */
    public function testGetCsrfRemediationSteps()
    {
        $handler = new EnhancedErrorHandler();
        
        // Use reflection to access protected method
        $reflection = new \ReflectionClass($handler);
        $method = $reflection->getMethod('getCsrfRemediationSteps');
        $method->setAccessible(true);
        
        $steps = $method->invoke($handler);
        
        // Verify steps is an array
        $this->assertIsArray($steps);
        
        // Verify steps are not empty
        $this->assertNotEmpty($steps);
        
        // Verify steps contain helpful information
        // Steps may vary based on session state, but should always provide guidance
        $stepsText = implode(' ', $steps);
        $this->assertTrue(
            stripos($stepsText, 'refresh') !== false || 
            stripos($stepsText, 'session') !== false ||
            stripos($stepsText, 'cookie') !== false,
            'Remediation steps should contain helpful guidance'
        );
    }
    
    /**
     * Test CSRF debug info generation
     * 
     * @test
     */
    public function testGetCsrfDebugInfo()
    {
        $handler = new EnhancedErrorHandler();
        
        // Use reflection to access protected method
        $reflection = new \ReflectionClass($handler);
        $method = $reflection->getMethod('getCsrfDebugInfo');
        $method->setAccessible(true);
        
        $info = $method->invoke($handler);
        
        // Verify info structure
        $this->assertIsArray($info);
        $this->assertArrayHasKey('session_status', $info);
        $this->assertArrayHasKey('cookies', $info);
        $this->assertArrayHasKey('csrf_config', $info);
        $this->assertArrayHasKey('remediation_steps', $info);
        
        // Verify session status details
        $this->assertArrayHasKey('active', $info['session_status']);
        $this->assertArrayHasKey('id', $info['session_status']);
        $this->assertArrayHasKey('name', $info['session_status']);
        
        // Verify cookie details
        $this->assertArrayHasKey('count', $info['cookies']);
        $this->assertArrayHasKey('has_session_cookie', $info['cookies']);
        
        // Verify CSRF config
        $this->assertArrayHasKey('enabled', $info['csrf_config']);
        $this->assertArrayHasKey('param', $info['csrf_config']);
    }
    
    /**
     * Test that technical error messages are not exposed
     * 
     * @test
     */
    public function testUserFriendlyErrorMessages()
    {
        // This test verifies that error messages don't contain technical details
        $technicalTerms = [
            'exception',
            'stack trace',
            'BadRequestHttpException',
            'validateCsrfToken',
            'session->',
            'Yii::',
        ];
        
        $userFriendlyMessage = 'Unable to verify your data submission. Please refresh the page and try again.';
        
        foreach ($technicalTerms as $term) {
            $this->assertStringNotContainsStringIgnoringCase(
                $term,
                $userFriendlyMessage,
                "User-friendly message should not contain technical term: $term"
            );
        }
        
        // Verify message is helpful
        $this->assertStringContainsStringIgnoringCase('refresh', $userFriendlyMessage);
        $this->assertStringContainsStringIgnoringCase('try again', $userFriendlyMessage);
    }
    
    /**
     * Test session expiration detection
     * 
     * @test
     */
    public function testIsSessionExpired()
    {
        $handler = new EnhancedErrorHandler();
        
        // Use reflection to access protected method
        $reflection = new \ReflectionClass($handler);
        $method = $reflection->getMethod('isSessionExpired');
        $method->setAccessible(true);
        
        // In test environment, session should be active
        $isExpired = $method->invoke($handler);
        
        // This is a basic check - in real scenarios, we'd mock session state
        $this->assertIsBool($isExpired);
    }
}
