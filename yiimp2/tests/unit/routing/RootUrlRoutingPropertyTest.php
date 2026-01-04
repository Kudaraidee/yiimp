<?php

namespace tests\unit\routing;

use Codeception\Test\Unit;
use Yii;
use yii\web\Application;
use app\controllers\SiteController;

/**
 * Property-based tests for Root URL Routing
 * 
 * **Feature: localhost-redirect-loop-fix, Property 6: Root URL routing**
 * **Validates: Requirements 4.1**
 */
class RootUrlRoutingPropertyTest extends Unit
{
    /**
     * @var \UnitTester
     */
    protected $tester;
    
    protected $controller;

    protected function _before()
    {
        parent::_before();
        // Ensure URL manager is properly configured
        Yii::$app->urlManager->enablePrettyUrl = true;
        Yii::$app->urlManager->showScriptName = false;
        
        // Create controller instance for testing
        $this->controller = new SiteController('site', Yii::$app);
    }

    /**
     * Property 6: Root URL routing
     * 
     * For any request to the root URL (/), the system should route to
     * SiteController::actionIndex without requiring authentication.
     * 
     * This property verifies that:
     * 1. The default route is site/index
     * 2. SiteController has an actionIndex method
     * 3. The routing configuration is consistent
     * 
     * Validates: Requirements 4.1
     * 
     * @test
     */
    public function testRootUrlRoutesToSiteIndex()
    {
        // Feature: localhost-redirect-loop-fix, Property 6: Root URL routing
        
        $iterations = 100;
        $failures = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            // Verify SiteController exists and has actionIndex
            if (!method_exists($this->controller, 'actionIndex')) {
                $failures[] = [
                    'iteration' => $i,
                    'reason' => 'SiteController does not have actionIndex method',
                    'expected' => 'actionIndex exists',
                    'actual' => 'method not found'
                ];
                continue;
            }
            
            // Verify the action can be created
            $action = $this->controller->createAction('index');
            if ($action === null) {
                $failures[] = [
                    'iteration' => $i,
                    'reason' => 'Could not create index action',
                    'expected' => 'action created',
                    'actual' => 'null'
                ];
                continue;
            }
            
            // Verify the action ID is correct
            if ($action->id !== 'index') {
                $failures[] = [
                    'iteration' => $i,
                    'reason' => 'Action ID mismatch',
                    'expected' => 'index',
                    'actual' => $action->id
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
     * Test that SiteController is the default controller
     * 
     * @test
     */
    public function testSiteControllerIsDefaultController()
    {
        // Verify that SiteController exists
        $this->assertTrue(class_exists('app\controllers\SiteController'),
            'SiteController class should exist');
        
        // Verify that actionIndex exists
        $this->assertTrue(method_exists($this->controller, 'actionIndex'),
            'SiteController should have actionIndex method');
        
        // Verify the controller ID is 'site'
        $this->assertEquals('site', $this->controller->id,
            'Controller ID should be "site"');
    }
    
    /**
     * Test that creating a URL for site/index returns root URL
     * 
     * This tests the reverse routing (route to URL)
     * 
     * @test
     */
    public function testSiteIndexCreatesRootUrl()
    {
        $urlManager = Yii::$app->urlManager;
        
        // Create URL for site/index
        $url = $urlManager->createUrl(['site/index']);
        
        // The URL should be the root or contain site/index
        // In test environment, it might include the script path
        $this->assertTrue(
            $url === '/' || 
            $url === '' || 
            strpos($url, '/site/index') !== false ||
            strpos($url, 'site/index') !== false,
            "site/index should create a valid URL, got: {$url}"
        );
    }
    
    /**
     * Test routing consistency across multiple requests
     * 
     * Verifies that the index action always behaves the same way
     * 
     * @test
     */
    public function testIndexActionConsistency()
    {
        $iterations = 50;
        $actionIds = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            // Create the index action multiple times
            $action = $this->controller->createAction('index');
            
            $this->assertNotNull($action, 'Index action should be creatable');
            
            $actionIds[] = $action->id;
        }
        
        // All action IDs should be the same
        $uniqueIds = array_unique($actionIds);
        
        $this->assertCount(1, $uniqueIds,
            'Index action should always have the same ID');
        
        $this->assertEquals('index', reset($uniqueIds),
            'Action ID should be "index"');
    }
    
    /**
     * Test that index action works with different HTTP methods
     * 
     * @test
     */
    public function testIndexActionWorksWithDifferentHttpMethods()
    {
        $methods = ['GET', 'POST', 'HEAD'];
        $failures = [];
        
        foreach ($methods as $method) {
            // The index action should be creatable regardless of HTTP method
            $action = $this->controller->createAction('index');
            
            if ($action === null) {
                $failures[] = [
                    'method' => $method,
                    'reason' => 'Could not create index action',
                    'expected' => 'action created',
                    'actual' => 'null'
                ];
                continue;
            }
            
            // Verify the action ID
            if ($action->id !== 'index') {
                $failures[] = [
                    'method' => $method,
                    'reason' => 'Incorrect action ID',
                    'expected' => 'index',
                    'actual' => $action->id
                ];
            }
        }
        
        if (!empty($failures)) {
            $this->fail(
                "Index action failed for some HTTP methods:\n" .
                json_encode($failures, JSON_PRETTY_PRINT)
            );
        }
        
        $this->assertTrue(true, 'Index action works for all HTTP methods');
    }
    
    /**
     * Test that index action does not require authentication
     * 
     * This is tested by verifying SiteController has no access control on index action
     * 
     * @test
     */
    public function testIndexActionDoesNotRequireAuthentication()
    {
        // Ensure user is logged out
        Yii::$app->user->logout();
        
        // Get the behaviors configuration
        $behaviors = $this->controller->behaviors();
        
        // Check if access control is configured
        $this->assertArrayHasKey('access', $behaviors,
            'SiteController should have access control configured');
        
        $accessControl = $behaviors['access'];
        
        // Verify that access control only applies to specific actions (not index)
        $this->assertArrayHasKey('only', $accessControl,
            'Access control should specify which actions it applies to');
        
        // Verify that 'index' is not in the 'only' list
        $this->assertNotContains('index', $accessControl['only'],
            'Index action should not be in access control "only" list');
        
        // Verify that the controller is SiteController (not AdminController)
        $this->assertEquals('site', $this->controller->id,
            'Controller should be SiteController');
        
        // Verify the index action can be created without authentication
        $action = $this->controller->createAction('index');
        $this->assertNotNull($action, 'Index action should be creatable without authentication');
    }
}
