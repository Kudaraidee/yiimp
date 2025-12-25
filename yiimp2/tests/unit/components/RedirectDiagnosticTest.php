<?php

namespace app\tests\unit\components;

use Yii;
use app\components\RedirectDiagnostic;
use yii\web\HttpException;

/**
 * Test RedirectDiagnostic component
 * 
 * Validates redirect loop detection and logging functionality
 * Requirements: 3.1
 */
class RedirectDiagnosticTest extends \Codeception\Test\Unit
{
    /**
     * @var \UnitTester
     */
    protected $tester;

    protected function _before()
    {
        // Clear any existing redirect chain
        RedirectDiagnostic::clearChain();
    }

    protected function _after()
    {
        // Clean up after test
        RedirectDiagnostic::clearChain();
    }

    /**
     * Test that redirect chain is initially empty
     */
    public function testInitialChainIsEmpty()
    {
        $chain = RedirectDiagnostic::getChain();
        $this->assertEmpty($chain, 'Initial redirect chain should be empty');
    }

    /**
     * Test logging a redirect
     */
    public function testLogRedirect()
    {
        RedirectDiagnostic::logRedirect('/admin/dashboard', '/admin/login', 'guest_user');
        
        $chain = RedirectDiagnostic::getChain();
        $this->assertCount(1, $chain, 'Chain should have 1 entry after logging redirect');
        $this->assertEquals('/admin/login', $chain[0]['url']);
        $this->assertEquals('guest_user', $chain[0]['reason']);
    }

    /**
     * Test that multiple redirects are tracked
     */
    public function testMultipleRedirects()
    {
        RedirectDiagnostic::logRedirect('/admin/dashboard', '/admin/login', 'guest_user');
        RedirectDiagnostic::logRedirect('/admin/login', '/admin/dashboard', 'after_login');
        RedirectDiagnostic::logRedirect('/admin/dashboard', '/admin/coinwallets', 'navigation');
        
        $chain = RedirectDiagnostic::getChain();
        $this->assertCount(3, $chain, 'Chain should have 3 entries');
    }

    /**
     * Test that redirect loop is detected
     * 
     * Requirement 3.1: Detect redirect loops
     */
    public function testDetectLoop()
    {
        // Add same URL 3 times to trigger loop detection
        RedirectDiagnostic::logRedirect('/admin/dashboard', '/admin/login', 'guest_user');
        RedirectDiagnostic::logRedirect('/admin/login', '/admin/login', 'still_guest');
        RedirectDiagnostic::logRedirect('/admin/login', '/admin/login', 'still_guest_again');
        
        // This should throw an exception
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Redirect loop detected');
        
        RedirectDiagnostic::detectLoop('/admin/login');
    }

    /**
     * Test that no loop is detected for different URLs
     */
    public function testNoLoopForDifferentUrls()
    {
        RedirectDiagnostic::logRedirect('/admin/dashboard', '/admin/login', 'guest_user');
        RedirectDiagnostic::logRedirect('/admin/login', '/admin/dashboard', 'after_login');
        RedirectDiagnostic::logRedirect('/admin/dashboard', '/admin/coinwallets', 'navigation');
        
        // Should not throw exception
        RedirectDiagnostic::detectLoop('/admin/user');
        
        $this->assertTrue(true, 'No exception should be thrown for different URLs');
    }

    /**
     * Test clearing the redirect chain
     */
    public function testClearChain()
    {
        RedirectDiagnostic::logRedirect('/admin/dashboard', '/admin/login', 'guest_user');
        RedirectDiagnostic::logRedirect('/admin/login', '/admin/dashboard', 'after_login');
        
        $chain = RedirectDiagnostic::getChain();
        $this->assertCount(2, $chain, 'Chain should have 2 entries before clear');
        
        RedirectDiagnostic::clearChain();
        
        $chain = RedirectDiagnostic::getChain();
        $this->assertEmpty($chain, 'Chain should be empty after clear');
    }

    /**
     * Test that chain is limited to max length
     */
    public function testChainMaxLength()
    {
        // Add more than MAX_CHAIN_LENGTH redirects
        for ($i = 0; $i < 15; $i++) {
            RedirectDiagnostic::logRedirect("/url/$i", "/url/" . ($i + 1), "redirect_$i");
        }
        
        $chain = RedirectDiagnostic::getChain();
        $this->assertLessThanOrEqual(10, count($chain), 'Chain should not exceed MAX_CHAIN_LENGTH');
    }

    /**
     * Test getting chain as string
     */
    public function testGetChainAsString()
    {
        RedirectDiagnostic::logRedirect('/admin/dashboard', '/admin/login', 'guest_user');
        
        $chainString = RedirectDiagnostic::getChainAsString();
        $this->assertStringContainsString('/admin/login', $chainString);
        $this->assertStringContainsString('guest_user', $chainString);
    }

    /**
     * Test logging access denial
     */
    public function testLogAccessDenial()
    {
        // This should not throw an exception, just log
        RedirectDiagnostic::logAccessDenial('admin', 'dashboard', 'guest_user');
        
        $this->assertTrue(true, 'Access denial logging should not throw exception');
    }
}
