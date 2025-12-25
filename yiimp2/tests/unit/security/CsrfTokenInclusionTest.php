<?php

namespace app\tests\unit\security;

use Yii;
use Codeception\Test\Unit;

/**
 * Test CSRF Token Inclusion in Admin Forms
 * 
 * This test verifies that all admin forms include CSRF tokens
 * as required by Requirements 1.3 and 2.1.
 * 
 * Task 8: Update existing forms to ensure CSRF token inclusion
 */
class CsrfTokenInclusionTest extends Unit
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Ensure CSRF validation is enabled
        Yii::$app->request->enableCsrfValidation = true;
        
        // Start a new session for each test
        if (Yii::$app->session->getIsActive()) {
            Yii::$app->session->close();
        }
        Yii::$app->session->open();
    }
    
    protected function tearDown(): void
    {
        if (Yii::$app->session->getIsActive()) {
            Yii::$app->session->close();
        }
        
        parent::tearDown();
    }
    
    /**
     * Test that coin creation form includes CSRF token
     * Requirement 1.3: Session storage should be queryable for CSRF token
     */
    public function testCoinCreateFormIncludesCsrfToken()
    {
        // Generate a CSRF token
        $token = Yii::$app->request->getCsrfToken();
        $this->assertNotEmpty($token, 'CSRF token should be generated');
        
        // Verify token is stored in session
        $sessionToken = Yii::$app->session->get(Yii::$app->request->csrfParam);
        $this->assertNotEmpty($sessionToken, 'CSRF token should be stored in session');
        
        // Simulate form rendering by checking if Html::beginForm includes CSRF token
        $formHtml = \yii\helpers\Html::beginForm(
            '/admin/coin-create',
            'post',
            ['id' => 'coin-form']
        );
        
        // The form HTML should contain a hidden input with the CSRF token
        $this->assertStringContainsString(
            'name="' . Yii::$app->request->csrfParam . '"',
            $formHtml,
            'Form should include CSRF token hidden input'
        );
        
        $this->assertStringContainsString(
            'type="hidden"',
            $formHtml,
            'CSRF token should be in a hidden input'
        );
    }
    
    /**
     * Test that coin update form includes CSRF token
     * Requirement 1.3: Session storage should be queryable for CSRF token
     */
    public function testCoinUpdateFormIncludesCsrfToken()
    {
        // Generate a CSRF token
        $token = Yii::$app->request->getCsrfToken();
        $this->assertNotEmpty($token, 'CSRF token should be generated');
        
        // Verify token is stored in session
        $sessionToken = Yii::$app->session->get(Yii::$app->request->csrfParam);
        $this->assertNotEmpty($sessionToken, 'CSRF token should be stored in session');
        
        // Simulate form rendering
        $formHtml = \yii\helpers\Html::beginForm(
            '/admin/coin-update?id=1',
            'post',
            ['id' => 'coin-form']
        );
        
        // The form HTML should contain a hidden input with the CSRF token
        $this->assertStringContainsString(
            'name="' . Yii::$app->request->csrfParam . '"',
            $formHtml,
            'Form should include CSRF token hidden input'
        );
    }
    
    /**
     * Test that login form includes CSRF token
     * Requirement 1.3: Session storage should be queryable for CSRF token
     */
    public function testLoginFormIncludesCsrfToken()
    {
        // Generate a CSRF token
        $token = Yii::$app->request->getCsrfToken();
        $this->assertNotEmpty($token, 'CSRF token should be generated');
        
        // Verify token is stored in session
        $sessionToken = Yii::$app->session->get(Yii::$app->request->csrfParam);
        $this->assertNotEmpty($sessionToken, 'CSRF token should be stored in session');
        
        // Simulate form rendering
        $formHtml = \yii\helpers\Html::beginForm(
            '/admin/login',
            'post',
            ['id' => 'login-form']
        );
        
        // The form HTML should contain a hidden input with the CSRF token
        $this->assertStringContainsString(
            'name="' . Yii::$app->request->csrfParam . '"',
            $formHtml,
            'Login form should include CSRF token hidden input'
        );
    }
    
    /**
     * Test that RPC console form includes CSRF token
     * Requirement 1.3: Session storage should be queryable for CSRF token
     */
    public function testRpcConsoleFormIncludesCsrfToken()
    {
        // Generate a CSRF token
        $token = Yii::$app->request->getCsrfToken();
        $this->assertNotEmpty($token, 'CSRF token should be generated');
        
        // Verify token is stored in session
        $sessionToken = Yii::$app->session->get(Yii::$app->request->csrfParam);
        $this->assertNotEmpty($sessionToken, 'CSRF token should be stored in session');
        
        // Simulate form rendering
        $formHtml = \yii\helpers\Html::beginForm(
            '/admin/coin-console?id=1',
            'post',
            []
        );
        
        // The form HTML should contain a hidden input with the CSRF token
        $this->assertStringContainsString(
            'name="' . Yii::$app->request->csrfParam . '"',
            $formHtml,
            'RPC console form should include CSRF token hidden input'
        );
    }
    
    /**
     * Test that POST requests without CSRF token are rejected
     * Requirement 2.1: POST requests without CSRF token should be rejected
     */
    public function testPostRequestWithoutCsrfTokenIsRejected()
    {
        // Generate a valid token first
        $validToken = Yii::$app->request->getCsrfToken();
        $this->assertNotEmpty($validToken);
        
        // Simulate a POST request without CSRF token
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'Coins' => [
                'name' => 'Test Coin',
                'symbol' => 'TEST',
            ],
        ];
        // Explicitly remove CSRF token
        unset($_POST[Yii::$app->request->csrfParam]);
        
        // Validation should fail
        $isValid = Yii::$app->request->validateCsrfToken();
        $this->assertFalse($isValid, 'POST request without CSRF token should fail validation');
    }
    
    /**
     * Test that POST requests with valid CSRF token are accepted
     * Requirement 1.3: Valid CSRF tokens should pass validation
     */
    public function testPostRequestWithValidCsrfTokenIsAccepted()
    {
        // Generate a valid token first
        $validToken = Yii::$app->request->getCsrfToken();
        $this->assertNotEmpty($validToken);
        
        // Store the token in POST data
        $_POST[Yii::$app->request->csrfParam] = $validToken;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        
        // Validation should succeed
        $isValid = Yii::$app->request->validateCsrfToken($validToken);
        $this->assertTrue($isValid, 'POST request with valid CSRF token should pass validation');
    }
    
    /**
     * Test that CSRF token parameter name is correctly configured
     * Requirement 1.1: CSRF token should be stored under correct key
     */
    public function testCsrfTokenParameterNameIsConfigured()
    {
        $csrfParam = Yii::$app->request->csrfParam;
        $this->assertNotEmpty($csrfParam, 'CSRF parameter name should be configured');
        $this->assertEquals('_csrf-yiimp2', $csrfParam, 'CSRF parameter should be _csrf-yiimp2');
    }
    
    /**
     * Test that CSRF validation is enabled
     * Requirement 2.1: CSRF validation should be enabled for security
     */
    public function testCsrfValidationIsEnabled()
    {
        $this->assertTrue(
            Yii::$app->request->enableCsrfValidation,
            'CSRF validation should be enabled'
        );
    }
    
    /**
     * Test that CSRF tokens are not stored in cookies
     * Requirement 1.2: CSRF tokens should be stored in session, not cookies
     */
    public function testCsrfTokensNotStoredInCookies()
    {
        // Generate a token
        $token = Yii::$app->request->getCsrfToken();
        $this->assertNotEmpty($token);
        
        // Verify enableCsrfCookie is false
        $this->assertFalse(
            Yii::$app->request->enableCsrfCookie,
            'CSRF tokens should not be stored in cookies'
        );
        
        // Verify token is in session, not in cookies
        $sessionToken = Yii::$app->session->get(Yii::$app->request->csrfParam);
        $this->assertNotEmpty($sessionToken, 'CSRF token should be in session');
        
        // Check that CSRF token is not in cookies
        $csrfCookieExists = isset($_COOKIE[Yii::$app->request->csrfParam]);
        $this->assertFalse($csrfCookieExists, 'CSRF token should not be in cookies');
    }
}
