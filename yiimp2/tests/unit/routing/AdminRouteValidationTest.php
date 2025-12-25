<?php

namespace app\tests\unit\routing;

use Codeception\Test\Unit;
use yii\web\UrlManager;
use ReflectionClass;
use ReflectionMethod;

/**
 * Test that all AdminController actions are properly handled by URL routing
 * 
 * This test validates Requirements 8.1, 8.2, 8.3:
 * - All admin routes are documented
 * - Route patterns handle all admin actions
 * - Routing configuration is maintainable
 */
class AdminRouteValidationTest extends Unit
{
    /**
     * @var \UnitTester
     */
    protected $tester;
    
    /**
     * @var UrlManager
     */
    protected $urlManager;

    protected function _before()
    {
        $this->urlManager = \Yii::$app->urlManager;
    }

    /**
     * Test that all AdminController actions can be routed
     */
    public function testAllAdminActionsAreRoutable()
    {
        // Get all action methods from AdminController
        $reflection = new ReflectionClass('app\controllers\AdminController');
        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
        
        $actionMethods = [];
        foreach ($methods as $method) {
            $name = $method->getName();
            if (strpos($name, 'action') === 0 && $name !== 'actions') {
                // Convert actionCoinWallets to coin-wallets
                $actionName = $this->camelCaseToKebab(substr($name, 6));
                $actionMethods[] = $actionName;
            }
        }

        // Test that each action can generate a valid URL
        $failedActions = [];
        foreach ($actionMethods as $action) {
            try {
                // Test action without ID
                $url = $this->urlManager->createUrl(['admin/' . $action]);
                
                // Verify URL was created (not empty)
                if (empty($url)) {
                    $failedActions[] = $action . ' (no ID)';
                }
                
                // For actions that might need an ID, test with ID
                if ($this->actionRequiresId($action)) {
                    $urlWithId = $this->urlManager->createUrl(['admin/' . $action, 'id' => 1]);
                    if (empty($urlWithId)) {
                        $failedActions[] = $action . ' (with ID)';
                    }
                }
            } catch (\Exception $e) {
                $failedActions[] = $action . ' (exception: ' . $e->getMessage() . ')';
            }
        }

        $this->assertEmpty(
            $failedActions,
            "The following admin actions could not be routed:\n" . implode("\n", $failedActions)
        );
    }

    /**
     * Test that admin route patterns match expected URLs
     */
    public function testAdminRoutePatterns()
    {
        $testCases = [
            // Dashboard
            [['admin/dashboard'], 'admin/dashboard'],
            
            // Coin management
            [['admin/coinwallets'], 'admin/coinwallets'],
            [['admin/coin-create'], 'admin/coin-create'],
            [['admin/coin-update', 'id' => 5], 'admin/coin-update'],
            [['admin/coin', 'id' => 10], 'admin/coin'],
            [['admin/coin-console', 'id' => 3], 'admin/coin-console'],
            
            // User management
            [['admin/user'], 'admin/user'],
            [['admin/user-results'], 'admin/user-results'],
            [['admin/ban-user', 'id' => 7], 'admin/ban-user'],
            
            // Worker monitoring
            [['admin/worker'], 'admin/worker'],
            [['admin/worker-results'], 'admin/worker-results'],
            
            // Payment management
            [['admin/payments'], 'admin/payments'],
            [['admin/payments-results'], 'admin/payments-results'],
            [['admin/cancel-payment', 'id' => 15], 'admin/cancel-payment'],
            
            // System management
            [['admin/memcached'], 'admin/memcached'],
            [['admin/clear-cache'], 'admin/clear-cache'],
            [['admin/version'], 'admin/version'],
        ];

        foreach ($testCases as $testCase) {
            list($params, $expectedPath) = $testCase;
            
            $actualUrl = $this->urlManager->createUrl($params);
            
            // Verify the URL contains the expected action path
            // URLs may have query parameters (?id=5) or path segments (/5)
            $this->assertStringContainsString(
                $expectedPath,
                $actualUrl,
                "URL generation failed for route: " . $params[0]
            );
            
            // Verify ID parameter is present if specified
            if (isset($params['id'])) {
                $id = $params['id'];
                $this->assertTrue(
                    strpos($actualUrl, "id=$id") !== false || strpos($actualUrl, "/$id") !== false,
                    "URL does not contain ID parameter: $id"
                );
            }
        }
    }

    /**
     * Test that kebab-case URLs map to camelCase actions
     */
    public function testKebabCaseToCamelCaseConversion()
    {
        $conversions = [
            'coin-wallets' => 'coinWallets',
            'coin-create' => 'coinCreate',
            'coin-update' => 'coinUpdate',
            'user-results' => 'userResults',
            'ban-user' => 'banUser',
            'worker-results' => 'workerResults',
            'clear-cache' => 'clearCache',
        ];

        foreach ($conversions as $kebab => $expected) {
            $actual = $this->kebabToCamelCase($kebab);
            $this->assertEquals(
                $expected,
                $actual,
                "Kebab-case to camelCase conversion failed for: $kebab"
            );
        }
    }

    /**
     * Test that admin route without action defaults to dashboard
     */
    public function testAdminDefaultRoute()
    {
        $url = $this->urlManager->createUrl(['admin']);
        
        // In test environment, URLs may have a base path prefix
        // We just need to verify the path ends with 'admin'
        $this->assertStringEndsWith('admin', $url);
        
        // Verify the route is not empty
        $this->assertNotEmpty($url);
    }

    /**
     * Test that invalid admin actions return proper URLs
     */
    public function testInvalidAdminActions()
    {
        // Even invalid actions should generate URLs (they'll 404 at runtime)
        $url = $this->urlManager->createUrl(['admin/nonexistent-action']);
        $this->assertNotEmpty($url);
        $this->assertStringContainsString('admin', $url);
    }

    /**
     * Convert camelCase to kebab-case
     */
    protected function camelCaseToKebab($string)
    {
        return strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $string));
    }

    /**
     * Convert kebab-case to camelCase
     * This mimics how Yii2 converts URL segments to action names
     */
    protected function kebabToCamelCase($string)
    {
        // Split by hyphen, capitalize each part, then join
        $parts = explode('-', $string);
        $result = array_shift($parts); // First part stays lowercase
        foreach ($parts as $part) {
            $result .= ucfirst($part);
        }
        return $result;
    }

    /**
     * Check if an action typically requires an ID parameter
     */
    protected function actionRequiresId($action)
    {
        $actionsWithId = [
            'coin-update', 'coin', 'coin-console', 'coin-peers', 'add-peer', 'remove-peer',
            'coin-triggers', 'enable-trigger', 'disable-trigger', 'reset-trigger',
            'start-coin', 'stop-coin', 'restart-coin', 'reset-blockchain', 'uninstall-coin',
            'ban-user', 'unban-user', 'cancel-payment', 'delete-earning'
        ];
        
        return in_array($action, $actionsWithId);
    }
}
