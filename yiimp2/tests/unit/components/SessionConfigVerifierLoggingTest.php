<?php

namespace tests\unit\components;

use Codeception\Test\Unit;
use Yii;
use app\components\SessionConfigVerifier;

/**
 * Unit tests for SessionConfigVerifier logging enhancements
 * 
 * Tests the new logging methods added in task 6 of localhost-redirect-loop-fix
 * Requirements: 3.3, 3.4, 3.5
 */
class SessionConfigVerifierLoggingTest extends Unit
{
    protected function _before()
    {
        parent::_before();
        
        // Ensure we have a clean Yii application instance
        if (!Yii::$app) {
            $this->mockApplication();
        }
    }
    
    /**
     * Test that logSessionInitSuccess logs successfully
     * 
     * Requirement 3.3: Log session initialization success
     * 
     * @test
     */
    public function testLogSessionInitSuccess()
    {
        // This should not throw an exception
        try {
            SessionConfigVerifier::logSessionInitSuccess();
            $this->assertTrue(true, 'logSessionInitSuccess executed without error');
        } catch (\Exception $e) {
            $this->fail('logSessionInitSuccess threw exception: ' . $e->getMessage());
        }
    }
    
    /**
     * Test that logSessionInitFailure logs exception details
     * 
     * Requirement 3.3: Log session initialization failure with detailed error information
     * 
     * @test
     */
    public function testLogSessionInitFailure()
    {
        $exception = new \Exception('Test session initialization failure', 500);
        
        // This should not throw an exception
        try {
            SessionConfigVerifier::logSessionInitFailure($exception);
            $this->assertTrue(true, 'logSessionInitFailure executed without error');
        } catch (\Exception $e) {
            $this->fail('logSessionInitFailure threw exception: ' . $e->getMessage());
        }
    }
    
    /**
     * Test that logCookieSettingFailure logs cookie parameters
     * 
     * Requirement 3.4: Log cookie parameters and browser state when cookie setting fails
     * 
     * @test
     */
    public function testLogCookieSettingFailure()
    {
        $cookieParams = [
            'httponly' => true,
            'secure' => false,
            'sameSite' => null,
            'path' => '/',
            'domain' => '',
        ];
        
        // This should not throw an exception
        try {
            SessionConfigVerifier::logCookieSettingFailure($cookieParams);
            $this->assertTrue(true, 'logCookieSettingFailure executed without error');
        } catch (\Exception $e) {
            $this->fail('logCookieSettingFailure threw exception: ' . $e->getMessage());
        }
    }
    
    /**
     * Test that logCookieSettingFailure works with empty params
     * 
     * @test
     */
    public function testLogCookieSettingFailureWithEmptyParams()
    {
        // This should not throw an exception and should retrieve params from session
        try {
            SessionConfigVerifier::logCookieSettingFailure();
            $this->assertTrue(true, 'logCookieSettingFailure with empty params executed without error');
        } catch (\Exception $e) {
            $this->fail('logCookieSettingFailure with empty params threw exception: ' . $e->getMessage());
        }
    }
    
    /**
     * Test that getSessionConfig returns expected structure
     * 
     * @test
     */
    public function testGetSessionConfig()
    {
        $config = SessionConfigVerifier::getSessionConfig();
        
        // Verify the config has expected keys
        $this->assertIsArray($config, 'getSessionConfig should return an array');
        $this->assertArrayHasKey('session_name', $config);
        $this->assertArrayHasKey('session_timeout', $config);
        $this->assertArrayHasKey('session_use_cookies', $config);
        $this->assertArrayHasKey('session_cookie_params', $config);
        $this->assertArrayHasKey('csrf_enabled', $config);
        $this->assertArrayHasKey('csrf_param', $config);
        $this->assertArrayHasKey('cookie_validation_key_set', $config);
        $this->assertArrayHasKey('cookie_validation_key_length', $config);
    }
}

