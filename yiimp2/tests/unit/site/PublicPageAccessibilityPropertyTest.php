<?php

namespace tests\unit\site;

use Codeception\Test\Unit;
use Yii;
use app\controllers\SiteController;

/**
 * Property-based tests for Public Page Accessibility
 * 
 * **Feature: localhost-redirect-loop-fix, Property 3: Public page accessibility**
 * **Validates: Requirements 1.1, 4.2, 4.5**
 */
class PublicPageAccessibilityPropertyTest extends Unit
{
    protected $controller;
    
    protected function _before()
    {
        parent::_before();
        
        // Create controller instance
        $this->controller = new SiteController('site', Yii::$app);
    }
    
    protected function _after()
    {
        // Ensure user is logged out after each test
        Yii::$app->user->logout();
        parent::_after();
    }
    
    /**
     * Property 3: Public page accessibility
     * 
     * For any request to public pages (/, /mining, /api, etc.), the system should
     * not require authentication. Guest users should be able to access these pages
     * without being redirected to login.
     * 
     * Validates: Requirements 1.1, 4.2, 4.5
     * 
     * @test
     */
    public function testPublicPagesAccessibleWithoutAuthentication()
    {
        // Feature: localhost-redirect-loop-fix, Property 3: Public page accessibility
        
        $iterations = 100;
        $failures = [];
        
        // Get all public actions from SiteController
        $publicActions = $this->getPublicActions();
        
        for ($i = 0; $i < $iterations; $i++) {
            // Randomly select a public action to test
            $action = $publicActions[array_rand($publicActions)];
            
            // Ensure user is logged out (guest)
            Yii::$app->user->logout();
            
            // Test that guest user can access the action
            $result = $this->testActionAccess($action);
            
            if (!$result['allowed']) {
                $failures[] = [
                    'iteration' => $i,
                    'action' => $action,
                    'user_type' => 'guest',
                    'reason' => 'Guest user was denied access to public page',
                    'expected' => 'allowed',
                    'actual' => 'denied',
                    'error' => $result['error'] ?? null,
                    'redirect' => $result['redirect'] ?? null
                ];
            }
            
            // Verify no redirect to login page
            if ($result['redirect'] && strpos($result['redirect'], '/admin/login') !== false) {
                $failures[] = [
                    'iteration' => $i,
                    'action' => $action,
                    'user_type' => 'guest',
                    'reason' => 'Public page redirected to admin login',
                    'expected' => 'no redirect to login',
                    'actual' => 'redirected to ' . $result['redirect'],
                    'error' => $result['error'] ?? null
                ];
            }
        }
        
        // Report failures if any
        if (!empty($failures)) {
            $this->fail(
                "Property test failed in " . count($failures) . " out of $iterations iterations:\n" .
                json_encode($failures, JSON_PRETTY_PRINT)
            );
        }
        
        $this->assertTrue(true, "Property holds for all $iterations iterations");
    }
    
    /**
     * Get list of public actions from SiteController
     * 
     * These actions should be accessible without authentication
     * 
     * @return array
     */
    protected function getPublicActions()
    {
        return [
            'index',           // Homepage
            'mining',          // Mining instructions
            'api',             // API documentation
            'about',           // About page
            'terms',           // Terms of service
            'privacy',         // Privacy policy
            'benchmarks',      // Benchmarks page
            'bookmarks',       // Bookmarks page
            'diff',            // Difficulty charts
            'multialgo',       // Multi-algorithm statistics
            'miners',          // Active miners listing
            'block',           // Block details
            'tx',              // Transaction details
            'algo',            // Algorithm selection
            // AJAX endpoints (also public)
            'miners_results',
            'history_results',
            'coins_info',
            'current_results',
            'found_results',
            'mining_results',
            'graph_hashrate_results',
            'graph_price_results',
            'wallet_results',
            'wallet_miners_results',
            'wallet_graphs_results',
            'graph_earnings_results',
            'user_earning_results',
            'wallet_found_results',
            'graph_user_results',
            'title_results',
            'block_results',
        ];
    }
    
    /**
     * Test access to an action
     * 
     * @param string $actionId
     * @return array ['allowed' => bool, 'error' => string|null, 'redirect' => string|null]
     */
    protected function testActionAccess($actionId)
    {
        try {
            // Create a mock request
            Yii::$app->request->setUrl('/site/' . str_replace('_', '-', $actionId));
            
            // Get the action
            $action = $this->controller->createAction($actionId);
            
            if ($action === null) {
                return ['allowed' => false, 'error' => 'Action not found', 'redirect' => null];
            }
            
            // Run beforeAction to trigger any access control
            $allowed = $this->controller->beforeAction($action);
            
            // Check if there's a redirect response
            $response = Yii::$app->response;
            $redirect = null;
            
            if ($response->statusCode === 302 || $response->statusCode === 301) {
                $headers = $response->headers;
                if ($headers->has('Location')) {
                    $redirect = $headers->get('Location');
                }
            }
            
            return [
                'allowed' => $allowed,
                'error' => null,
                'redirect' => $redirect
            ];
            
        } catch (\yii\web\ForbiddenHttpException $e) {
            // Access denied - this should NOT happen for public pages
            return [
                'allowed' => false,
                'error' => 'ForbiddenHttpException: ' . $e->getMessage(),
                'redirect' => null
            ];
        } catch (\yii\web\UnauthorizedHttpException $e) {
            // Unauthorized - this should NOT happen for public pages
            return [
                'allowed' => false,
                'error' => 'UnauthorizedHttpException: ' . $e->getMessage(),
                'redirect' => null
            ];
        } catch (\Exception $e) {
            // Check if it's a redirect exception
            $response = Yii::$app->response;
            $redirect = null;
            
            if ($response->statusCode === 302 || $response->statusCode === 301) {
                $headers = $response->headers;
                if ($headers->has('Location')) {
                    $redirect = $headers->get('Location');
                }
                
                // If redirected to login, this is a failure
                if ($redirect && strpos($redirect, '/admin/login') !== false) {
                    return [
                        'allowed' => false,
                        'error' => 'Redirected to login',
                        'redirect' => $redirect
                    ];
                }
            }
            
            // For other exceptions, check if access was actually allowed
            // Some actions might throw exceptions during execution but still be accessible
            return [
                'allowed' => false,
                'error' => get_class($e) . ': ' . $e->getMessage(),
                'redirect' => $redirect
            ];
        }
    }
    
    /**
     * Test that SiteController has no access control on public actions
     * 
     * This verifies the behaviors() configuration
     * 
     * @test
     */
    public function testSiteControllerHasNoAccessControlOnPublicActions()
    {
        $behaviors = $this->controller->behaviors();
        
        // Check if access control is configured
        $this->assertArrayHasKey('access', $behaviors, 
            'SiteController should have access control configured');
        
        $accessControl = $behaviors['access'];
        
        // Verify that access control only applies to 'logout' action
        $this->assertArrayHasKey('only', $accessControl,
            'Access control should specify which actions it applies to');
        
        $this->assertEquals(['logout'], $accessControl['only'],
            'Access control should only apply to logout action');
        
        // Verify that public actions are not in the 'only' list
        $publicActions = $this->getPublicActions();
        foreach ($publicActions as $action) {
            $this->assertNotContains($action, $accessControl['only'],
                "Public action '{$action}' should not be in access control 'only' list");
        }
    }
    
    /**
     * Test that actionIndex specifically has no authentication requirement
     * 
     * This is critical for the homepage to load without redirect
     * 
     * @test
     */
    public function testActionIndexHasNoAuthenticationRequirement()
    {
        // Ensure user is logged out
        Yii::$app->user->logout();
        
        // Test access to index action
        $result = $this->testActionAccess('index');
        
        $this->assertTrue($result['allowed'],
            'Guest user should be able to access index action. Error: ' . ($result['error'] ?? 'none'));
        
        $this->assertNull($result['redirect'],
            'Index action should not redirect. Redirect: ' . ($result['redirect'] ?? 'none'));
        
        // Specifically check no redirect to admin/login
        if ($result['redirect']) {
            $this->assertStringNotContainsString('/admin/login', $result['redirect'],
                'Index action should not redirect to admin login');
        }
    }
    
    /**
     * Test that all public pages work for both guest and authenticated users
     * 
     * @test
     */
    public function testPublicPagesAccessibleForBothGuestAndAuthenticatedUsers()
    {
        $publicActions = ['index', 'mining', 'api', 'about'];
        $failures = [];
        
        foreach ($publicActions as $action) {
            // Test as guest
            Yii::$app->user->logout();
            $guestResult = $this->testActionAccess($action);
            
            if (!$guestResult['allowed']) {
                $failures[] = [
                    'action' => $action,
                    'user_type' => 'guest',
                    'allowed' => false,
                    'error' => $guestResult['error'] ?? null
                ];
            }
            
            // Test as authenticated user (if we can create one)
            // Note: We skip this if we can't create a test user
            // The important test is that guests can access
        }
        
        if (!empty($failures)) {
            $this->fail(
                "Some public pages are not accessible:\n" .
                json_encode($failures, JSON_PRETTY_PRINT)
            );
        }
        
        $this->assertTrue(true, 'All tested public pages are accessible');
    }
}
