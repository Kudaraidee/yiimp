<?php

namespace app\tests\integration;

use Yii;
use app\components\RedirectDiagnostic;

/**
 * Integration test for redirect loop detection
 * 
 * Tests the redirect loop detection in a realistic scenario
 * Requirements: 3.1
 */
class RedirectLoopDetectionTest extends \Codeception\Test\Unit
{
    /**
     * @var \UnitTester
     */
    protected $tester;

    protected function _before()
    {
        // Clear any existing redirect chain
        RedirectDiagnostic::clearChain();
        
        // Ensure user is logged out
        if (!Yii::$app->user->isGuest) {
            Yii::$app->user->logout();
        }
    }

    protected function _after()
    {
        // Clean up
        RedirectDiagnostic::clearChain();
        
        if (!Yii::$app->user->isGuest) {
            Yii::$app->user->logout();
        }
    }

    /**
     * Test that accessing admin pages as guest triggers redirect but not loop
     * 
     * Requirement 3.1: Prevent redirect loops
     */
    public function testGuestAccessDoesNotCreateLoop()
    {
        // Simulate first access to admin dashboard
        RedirectDiagnostic::logRedirect('/admin/dashboard', '/admin/login', 'guest_user');
        
        // Should not throw exception on first redirect
        RedirectDiagnostic::detectLoop('/admin/login');
        
        $this->assertTrue(true, 'First redirect should not trigger loop detection');
    }

    /**
     * Test that repeated redirects to same URL are detected
     * 
     * Requirement 3.1: Detect redirect loops
     */
    public function testRepeatedRedirectsDetected()
    {
        // Simulate multiple redirects to same URL
        RedirectDiagnostic::logRedirect('/admin/dashboard', '/admin/login', 'guest_user');
        RedirectDiagnostic::logRedirect('/admin/login', '/admin/login', 'session_issue');
        RedirectDiagnostic::logRedirect('/admin/login', '/admin/login', 'cookie_issue');
        
        // This should throw exception
        $this->expectException(\yii\web\HttpException::class);
        $this->expectExceptionMessage('Redirect loop detected');
        
        RedirectDiagnostic::detectLoop('/admin/login');
    }

    /**
     * Test that chain is cleared after successful login
     */
    public function testChainClearedAfterLogin()
    {
        // Simulate redirect chain before login
        RedirectDiagnostic::logRedirect('/admin/dashboard', '/admin/login', 'guest_user');
        
        $chain = RedirectDiagnostic::getChain();
        $this->assertNotEmpty($chain, 'Chain should have entries before login');
        
        // Simulate successful login by clearing chain
        RedirectDiagnostic::clearChain();
        
        $chain = RedirectDiagnostic::getChain();
        $this->assertEmpty($chain, 'Chain should be empty after login');
    }

    /**
     * Test that normal navigation doesn't trigger loop detection
     */
    public function testNormalNavigationAllowed()
    {
        // Simulate normal navigation through different pages
        RedirectDiagnostic::logRedirect('/admin/dashboard', '/admin/coinwallets', 'navigation');
        RedirectDiagnostic::logRedirect('/admin/coinwallets', '/admin/user', 'navigation');
        RedirectDiagnostic::logRedirect('/admin/user', '/admin/worker', 'navigation');
        
        // None of these should trigger loop detection
        RedirectDiagnostic::detectLoop('/admin/payments');
        
        $this->assertTrue(true, 'Normal navigation should not trigger loop detection');
    }

    /**
     * Test that access denial is logged properly
     */
    public function testAccessDenialLogging()
    {
        // This should not throw exception, just log
        RedirectDiagnostic::logAccessDenial('admin', 'dashboard', 'guest_user');
        
        $this->assertTrue(true, 'Access denial logging should complete without error');
    }

    /**
     * Test redirect chain information is preserved
     */
    public function testRedirectChainPreservesInformation()
    {
        RedirectDiagnostic::logRedirect('/admin/dashboard', '/admin/login', 'guest_user');
        
        $chain = RedirectDiagnostic::getChain();
        
        $this->assertCount(1, $chain);
        $this->assertEquals('/admin/login', $chain[0]['url']);
        $this->assertEquals('guest_user', $chain[0]['reason']);
        $this->assertArrayHasKey('timestamp', $chain[0]);
        $this->assertArrayHasKey('user_agent', $chain[0]);
    }
}
