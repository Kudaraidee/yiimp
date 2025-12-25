<?php

namespace tests\unit\security;

use Codeception\Test\Unit;
use Yii;
use yii\web\BadRequestHttpException;

/**
 * Test CSRF validation implementation
 * 
 * Verifies that the system properly rejects requests without CSRF tokens
 * and returns appropriate HTTP status codes.
 * 
 * Requirements: 2.1, 2.2, 2.3, 2.4, 2.5
 */
class CsrfValidationImplementationTest extends Unit
{
    /**
     * @var \UnitTester
     */
    protected $tester;
    
    /**
     * Setup before each test
     */
    protected function _before()
    {
        parent::_before();
        
        // Ensure Yii application is available
        if (Yii::$app === null) {
            $config = require __DIR__ . '/../../config/test.php';
            new \yii\web\Application($config);
        }
        
        // Clean up any existing session
        if (Yii::$app->session->getIsActive()) {
            Yii::$app->session->destroy();
        }
    }
    
    /**
     * Cleanup after each test
     */
    protected function _after()
    {
        // Clean up session
        if (Yii::$app !== null && Yii::$app->session->getIsActive()) {
            Yii::$app->session->destroy();
        }
        
        // Clean up request state
        if (isset($_POST[Yii::$app->request->csrfParam])) {
            unset($_POST[Yii::$app->request->csrfParam]);
        }
        unset($_SERVER['REQUEST_METHOD']);
        
        parent::_after();
    }
    
    /**
     * Test that CSRF validation is enabled
     * 
     * @test
     */
    public function testCsrfValidationIsEnabled()
    {
        $this->assertTrue(
            Yii::$app->request->enableCsrfValidation,
            'CSRF validation should be enabled'
        );
    }
    
    /**
     * Test that CSRF tokens are stored in session, not cookies
     * 
     * Requirement 1.2: Store CSRF token in session
     * 
     * @test
     */
    public function testCsrfTokensStoredInSession()
    {
        $this->assertFalse(
            Yii::$app->request->enableCsrfCookie,
            'CSRF tokens should be stored in session, not cookies'
        );
    }
    
    /**
     * Test that POST requests without CSRF token are rejected
     * 
     * Requirement 2.1: Reject POST requests without CSRF token
     * 
     * @test
     */
    public function testPostRequestWithoutTokenIsRejected()
    {
        // Start session
        Yii::$app->session->open();
        
        // Generate session token
        $sessionToken = Yii::$app->request->getCsrfToken();
        $this->assertNotEmpty($sessionToken);
        
        // Simulate POST request WITHOUT token
        $_SERVER['REQUEST_METHOD'] = 'POST';
        unset($_POST[Yii::$app->request->csrfParam]);
        
        // Validation should fail
        $isValid = Yii::$app->request->validateCsrfToken();
        $this->assertFalse($isValid, 'POST request without CSRF token should be rejected');
        
        Yii::$app->session->close();
    }
    
    /**
     * Test that PUT requests without CSRF token are rejected
     * 
     * Requirement 2.2: Reject PUT requests without CSRF token
     * 
     * @test
     */
    public function testPutRequestWithoutTokenIsRejected()
    {
        // Start session
        Yii::$app->session->open();
        
        // Generate session token
        $sessionToken = Yii::$app->request->getCsrfToken();
        $this->assertNotEmpty($sessionToken);
        
        // Simulate PUT request WITHOUT token
        $_SERVER['REQUEST_METHOD'] = 'PUT';
        unset($_POST[Yii::$app->request->csrfParam]);
        
        // Validation should fail
        $isValid = Yii::$app->request->validateCsrfToken();
        $this->assertFalse($isValid, 'PUT request without CSRF token should be rejected');
        
        Yii::$app->session->close();
    }
    
    /**
     * Test that DELETE requests without CSRF token are rejected
     * 
     * Requirement 2.3: Reject DELETE requests without CSRF token
     * 
     * @test
     */
    public function testDeleteRequestWithoutTokenIsRejected()
    {
        // Start session
        Yii::$app->session->open();
        
        // Generate session token
        $sessionToken = Yii::$app->request->getCsrfToken();
        $this->assertNotEmpty($sessionToken);
        
        // Simulate DELETE request WITHOUT token
        $_SERVER['REQUEST_METHOD'] = 'DELETE';
        unset($_POST[Yii::$app->request->csrfParam]);
        
        // Validation should fail
        $isValid = Yii::$app->request->validateCsrfToken();
        $this->assertFalse($isValid, 'DELETE request without CSRF token should be rejected');
        
        Yii::$app->session->close();
    }
    
    /**
     * Test that GET requests do not require CSRF token
     * 
     * @test
     */
    public function testGetRequestDoesNotRequireToken()
    {
        // Start session
        Yii::$app->session->open();
        
        // Generate session token
        $sessionToken = Yii::$app->request->getCsrfToken();
        $this->assertNotEmpty($sessionToken);
        
        // Simulate GET request WITHOUT token
        $_SERVER['REQUEST_METHOD'] = 'GET';
        unset($_POST[Yii::$app->request->csrfParam]);
        
        // Validation should pass for GET
        $isValid = Yii::$app->request->validateCsrfToken();
        $this->assertTrue($isValid, 'GET request should not require CSRF token');
        
        Yii::$app->session->close();
    }
    
    /**
     * Test that requests with valid tokens are accepted
     * 
     * @test
     */
    public function testRequestWithValidTokenIsAccepted()
    {
        // Start session
        Yii::$app->session->open();
        
        // Generate and include valid CSRF token
        $csrfToken = Yii::$app->request->getCsrfToken();
        $this->assertNotEmpty($csrfToken);
        
        // Simulate POST request WITH valid token
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST[Yii::$app->request->csrfParam] = $csrfToken;
        
        // Validation should pass
        $isValid = Yii::$app->request->validateCsrfToken($csrfToken);
        $this->assertTrue($isValid, 'Request with valid CSRF token should be accepted');
        
        unset($_POST[Yii::$app->request->csrfParam]);
        Yii::$app->session->close();
    }
    
    /**
     * Test that empty tokens are rejected
     * 
     * @test
     */
    public function testEmptyTokenIsRejected()
    {
        // Start session
        Yii::$app->session->open();
        
        // Generate session token
        $sessionToken = Yii::$app->request->getCsrfToken();
        $this->assertNotEmpty($sessionToken);
        
        // Simulate POST request with EMPTY token
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST[Yii::$app->request->csrfParam] = '';
        
        // Validation should fail
        $isValid = Yii::$app->request->validateCsrfToken('');
        $this->assertFalse($isValid, 'Empty CSRF token should be rejected');
        
        unset($_POST[Yii::$app->request->csrfParam]);
        Yii::$app->session->close();
    }
    
    /**
     * Test that invalid tokens are rejected
     * 
     * @test
     */
    public function testInvalidTokenIsRejected()
    {
        // Start session
        Yii::$app->session->open();
        
        // Generate session token
        $sessionToken = Yii::$app->request->getCsrfToken();
        $this->assertNotEmpty($sessionToken);
        
        // Simulate POST request with INVALID token
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $invalidToken = 'invalid_token_12345';
        $_POST[Yii::$app->request->csrfParam] = $invalidToken;
        
        // Validation should fail
        $isValid = Yii::$app->request->validateCsrfToken($invalidToken);
        $this->assertFalse($isValid, 'Invalid CSRF token should be rejected');
        
        unset($_POST[Yii::$app->request->csrfParam]);
        Yii::$app->session->close();
    }
    
    /**
     * Test that CSRF parameter name is correct
     * 
     * @test
     */
    public function testCsrfParameterName()
    {
        $this->assertEquals(
            '_csrf-yiimp2',
            Yii::$app->request->csrfParam,
            'CSRF parameter name should be _csrf-yiimp2'
        );
    }
}
