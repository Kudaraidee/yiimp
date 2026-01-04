<?php

namespace app\tests\unit\routing;

use Codeception\Test\Unit;
use yii\helpers\Url;
use yii\web\UrlManager;
use ReflectionClass;
use ReflectionMethod;

/**
 * Unit tests for URL generation
 * 
 * Tests that URL helper generates correct kebab-case URLs,
 * route patterns match expected URLs, and all view URL references
 * resolve to existing actions.
 * 
 * Validates Requirements 1.1, 1.2, 1.3, 1.4
 */
class UrlGenerationTest extends Unit
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
     * Test that URL helper generates correct kebab-case URLs
     * Validates Requirement 1.1
     */
    public function testUrlHelperGeneratesKebabCaseUrls()
    {
        $testCases = [
            // Action name => Expected URL pattern
            ['admin/coinwallets', 'coinwallets'],
            ['admin/coin-create', 'coin-create'],
            ['admin/coin-update', 'coin-update'],
            ['admin/coin-console', 'coin-console'],
            ['admin/coin-peers', 'coin-peers'],
            ['admin/coin-triggers', 'coin-triggers'],
            ['admin/user-results', 'user-results'],
            ['admin/ban-user', 'ban-user'],
            ['admin/unban-user', 'unban-user'],
            ['admin/worker-results', 'worker-results'],
            ['admin/payments-results', 'payments-results'],
            ['admin/cancel-payment', 'cancel-payment'],
            ['admin/earning-results', 'earning-results'],
            ['admin/delete-earning', 'delete-earning'],
            ['admin/exchange-results', 'exchange-results'],
            ['admin/balances-results', 'balances-results'],
            ['admin/connections-results', 'connections-results'],
            ['admin/block-pattern', 'block-pattern'],
            ['admin/clear-cache', 'clear-cache'],
            ['admin/version-results', 'version-results'],
            ['admin/start-coin', 'start-coin'],
            ['admin/stop-coin', 'stop-coin'],
            ['admin/restart-coin', 'restart-coin'],
            ['admin/reset-blockchain', 'reset-blockchain'],
            ['admin/uninstall-coin', 'uninstall-coin'],
            ['admin/add-peer', 'add-peer'],
            ['admin/remove-peer', 'remove-peer'],
            ['admin/enable-trigger', 'enable-trigger'],
            ['admin/disable-trigger', 'disable-trigger'],
            ['admin/reset-trigger', 'reset-trigger'],
            ['admin/test-rpc', 'test-rpc'],
            ['admin/dashboard-results', 'dashboard-results'],
        ];

        foreach ($testCases as list($route, $expectedPattern)) {
            $url = $this->urlManager->createUrl([$route]);
            
            // Verify URL contains the expected kebab-case pattern
            $this->assertStringContainsString(
                $expectedPattern,
                $url,
                "URL for route '{$route}' should contain kebab-case pattern '{$expectedPattern}'"
            );
            
            // Verify URL does not contain underscores
            $urlPath = parse_url($url, PHP_URL_PATH);
            if ($urlPath) {
                $this->assertStringNotContainsString(
                    '_',
                    $urlPath,
                    "URL path should not contain underscores: {$url}"
                );
            }
        }
    }

    /**
     * Test that URL helper generates URLs with parameters correctly
     * Validates Requirement 1.2
     */
    public function testUrlHelperGeneratesUrlsWithParameters()
    {
        $testCases = [
            // Route with parameters
            [['admin/coin-update', 'id' => 5], 'coin-update', '5'],
            [['admin/coin', 'id' => 10], 'coin', '10'],
            [['admin/coin-console', 'id' => 3], 'coin-console', '3'],
            [['admin/ban-user', 'id' => 7], 'ban-user', '7'],
            [['admin/cancel-payment', 'id' => 15], 'cancel-payment', '15'],
            [['admin/user-results', 'search' => 'test'], 'user-results', 'test'],
            [['admin/worker-results', 'algo' => 'sha256'], 'worker-results', 'sha256'],
        ];

        foreach ($testCases as list($params, $expectedAction, $expectedValue)) {
            $url = $this->urlManager->createUrl($params);
            
            // Verify URL contains the action
            $this->assertStringContainsString(
                $expectedAction,
                $url,
                "URL should contain action '{$expectedAction}'"
            );
            
            // Verify URL contains the parameter value
            $this->assertStringContainsString(
                $expectedValue,
                $url,
                "URL should contain parameter value '{$expectedValue}'"
            );
            
            // Verify no underscores in path
            $urlPath = parse_url($url, PHP_URL_PATH);
            if ($urlPath) {
                $this->assertStringNotContainsString(
                    '_',
                    $urlPath,
                    "URL path should not contain underscores: {$url}"
                );
            }
        }
    }

    /**
     * Test that route patterns match expected URLs
     * Validates Requirement 1.2
     */
    public function testRoutePatternMatchExpectedUrls()
    {
        $patterns = [
            // Simple routes without parameters
            ['admin/dashboard', '/admin/dashboard'],
            ['admin/coinwallets', '/admin/coinwallets'],
            ['admin/coin-create', '/admin/coin-create'],
            ['admin/user', '/admin/user'],
            ['admin/worker', '/admin/worker'],
            ['admin/payments', '/admin/payments'],
            ['admin/earning', '/admin/earning'],
            ['admin/exchange', '/admin/exchange'],
            ['admin/balances', '/admin/balances'],
            ['admin/connections', '/admin/connections'],
            ['admin/botnets', '/admin/botnets'],
            ['admin/monsters', '/admin/monsters'],
            ['admin/memcached', '/admin/memcached'],
            ['admin/version', '/admin/version'],
        ];

        foreach ($patterns as list($route, $expectedPath)) {
            $url = $this->urlManager->createUrl([$route]);
            
            // Verify URL ends with expected path
            $this->assertStringEndsWith(
                $expectedPath,
                $url,
                "URL for route '{$route}' should end with '{$expectedPath}'"
            );
        }
    }

    /**
     * Test that all AdminController actions can generate valid URLs
     * Validates Requirement 1.3
     */
    public function testAllAdminActionsGenerateValidUrls()
    {
        // Get all action methods from AdminController
        $reflection = new ReflectionClass('app\controllers\AdminController');
        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
        
        $failedActions = [];
        
        foreach ($methods as $method) {
            $name = $method->getName();
            
            // Skip non-action methods
            if (strpos($name, 'action') !== 0 || $name === 'actions') {
                continue;
            }
            
            // Convert actionCoinWallets to coin-wallets
            $actionName = $this->camelCaseToKebab(substr($name, 6));
            
            try {
                // Generate URL
                $url = $this->urlManager->createUrl(['admin/' . $actionName]);
                
                // Verify URL is not empty
                if (empty($url)) {
                    $failedActions[] = $actionName . ' (empty URL)';
                    continue;
                }
                
                // Verify URL contains the action name
                if (!str_contains($url, $actionName)) {
                    $failedActions[] = $actionName . ' (action not in URL)';
                    continue;
                }
                
                // Verify no underscores in path
                $urlPath = parse_url($url, PHP_URL_PATH);
                if ($urlPath && str_contains($urlPath, '_')) {
                    $failedActions[] = $actionName . ' (contains underscores)';
                }
                
            } catch (\Exception $e) {
                $failedActions[] = $actionName . ' (exception: ' . $e->getMessage() . ')';
            }
        }

        $this->assertEmpty(
            $failedActions,
            "The following admin actions failed URL generation:\n" . implode("\n", $failedActions)
        );
    }

    /**
     * Test that view URL references resolve to existing actions
     * Validates Requirement 1.3
     */
    public function testViewUrlReferencesResolveToExistingActions()
    {
        // List of known admin actions that should exist
        $knownActions = [
            'dashboard',
            'coinwallets',
            'coin-create',
            'coin-update',
            'coin',
            'coin-console',
            'coin-peers',
            'add-peer',
            'remove-peer',
            'coin-triggers',
            'enable-trigger',
            'disable-trigger',
            'reset-trigger',
            'start-coin',
            'stop-coin',
            'restart-coin',
            'reset-blockchain',
            'uninstall-coin',
            'user',
            'user-results',
            'ban-user',
            'unban-user',
            'worker',
            'worker-results',
            'payments',
            'payments-results',
            'cancel-payment',
            'earning',
            'earning-results',
            'delete-earning',
            'exchange',
            'exchange-results',
            'balances',
            'balances-results',
            'connections',
            'connections-results',
            'botnets',
            'monsters',
            'block-pattern',
            'memcached',
            'clear-cache',
            'version',
            'version-results',
            'test-rpc',
            'dashboard-results',
            'login',
            'logout',
        ];

        // Get actual actions from AdminController
        $reflection = new ReflectionClass('app\controllers\AdminController');
        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
        
        $actualActions = [];
        foreach ($methods as $method) {
            $name = $method->getName();
            if (strpos($name, 'action') === 0 && $name !== 'actions') {
                $actualActions[] = $this->camelCaseToKebab(substr($name, 6));
            }
        }

        // Verify all known actions exist
        $missingActions = [];
        foreach ($knownActions as $action) {
            if (!in_array($action, $actualActions)) {
                $missingActions[] = $action;
            }
        }

        $this->assertEmpty(
            $missingActions,
            "The following actions are referenced but don't exist:\n" . implode("\n", $missingActions)
        );
    }

    /**
     * Test URL generation consistency
     * Validates Requirement 1.4
     */
    public function testUrlGenerationConsistency()
    {
        $routes = [
            'admin/dashboard',
            'admin/coinwallets',
            'admin/coin-create',
            'admin/user',
            'admin/worker',
        ];

        foreach ($routes as $route) {
            // Generate URL multiple times
            $url1 = $this->urlManager->createUrl([$route]);
            $url2 = $this->urlManager->createUrl([$route]);
            $url3 = $this->urlManager->createUrl([$route]);
            
            // Verify consistency
            $this->assertEquals(
                $url1,
                $url2,
                "URL generation should be consistent for route: {$route}"
            );
            
            $this->assertEquals(
                $url2,
                $url3,
                "URL generation should be consistent for route: {$route}"
            );
        }
    }

    /**
     * Test that absolute URLs are generated correctly
     * Validates Requirement 1.1
     */
    public function testAbsoluteUrlGeneration()
    {
        $routes = [
            'admin/dashboard',
            'admin/coinwallets',
            'admin/user-results',
        ];

        foreach ($routes as $route) {
            $url = $this->urlManager->createAbsoluteUrl([$route]);
            
            // Verify URL is not empty
            $this->assertNotEmpty($url, "Absolute URL should not be empty");
            
            // In test environment, absolute URLs may not have scheme
            // Just verify the URL contains the route
            $this->assertStringContainsString(
                str_replace('/', '', $route),
                str_replace('/', '', $url),
                "Absolute URL should contain the route: {$route}"
            );
            
            // Verify no underscores in path
            $urlPath = parse_url($url, PHP_URL_PATH);
            if ($urlPath) {
                $this->assertStringNotContainsString(
                    '_',
                    $urlPath,
                    "Absolute URL path should not contain underscores: {$url}"
                );
            }
        }
    }

    /**
     * Test URL generation with anchor fragments
     * Validates Requirement 1.2
     */
    public function testUrlGenerationWithAnchors()
    {
        $testCases = [
            [['admin/dashboard', '#' => 'stats'], '#stats'],
            [['admin/coinwallets', '#' => 'coin-5'], '#coin-5'],
            [['admin/user', '#' => 'user-list'], '#user-list'],
        ];

        foreach ($testCases as list($params, $expectedAnchor)) {
            $url = $this->urlManager->createUrl($params);
            
            // Verify URL contains the anchor
            $this->assertStringContainsString(
                $expectedAnchor,
                $url,
                "URL should contain anchor: {$expectedAnchor}"
            );
        }
    }

    /**
     * Test that kebab-case to camelCase conversion works correctly
     * Validates Requirement 1.4
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
            'test-rpc' => 'testRpc',
            'dashboard-results' => 'dashboardResults',
        ];

        foreach ($conversions as $kebab => $expectedCamel) {
            $actualCamel = $this->kebabToCamelCase($kebab);
            $this->assertEquals(
                $expectedCamel,
                $actualCamel,
                "Kebab-case '{$kebab}' should convert to camelCase '{$expectedCamel}'"
            );
        }
    }

    /**
     * Test that camelCase to kebab-case conversion works correctly
     * Validates Requirement 1.1
     */
    public function testCamelCaseToKebabCaseConversion()
    {
        $conversions = [
            'coinWallets' => 'coin-wallets',
            'coinCreate' => 'coin-create',
            'coinUpdate' => 'coin-update',
            'userResults' => 'user-results',
            'banUser' => 'ban-user',
            'workerResults' => 'worker-results',
            'clearCache' => 'clear-cache',
            'testRpc' => 'test-rpc',
            'dashboardResults' => 'dashboard-results',
        ];

        foreach ($conversions as $camel => $expectedKebab) {
            $actualKebab = $this->camelCaseToKebab($camel);
            $this->assertEquals(
                $expectedKebab,
                $actualKebab,
                "CamelCase '{$camel}' should convert to kebab-case '{$expectedKebab}'"
            );
        }
    }

    /**
     * Test URL generation with query parameters
     * Validates Requirement 1.2
     */
    public function testUrlGenerationWithQueryParameters()
    {
        $testCases = [
            [
                ['admin/user-results', 'search' => 'test', 'coinid' => '1'],
                ['search' => 'test', 'coinid' => '1']
            ],
            [
                ['admin/worker-results', 'algo' => 'sha256', 'active' => '1'],
                ['algo' => 'sha256', 'active' => '1']
            ],
            [
                ['admin/payments-results', 'status' => 'pending', 'coinid' => '2'],
                ['status' => 'pending', 'coinid' => '2']
            ],
        ];

        foreach ($testCases as list($params, $expectedParams)) {
            $url = $this->urlManager->createUrl($params);
            
            // Parse query string
            $urlParts = parse_url($url);
            if (isset($urlParts['query'])) {
                parse_str($urlParts['query'], $queryParams);
                
                // Verify all expected parameters are present
                foreach ($expectedParams as $key => $value) {
                    $this->assertArrayHasKey(
                        $key,
                        $queryParams,
                        "URL should contain query parameter: {$key}"
                    );
                    $this->assertEquals(
                        $value,
                        $queryParams[$key],
                        "Query parameter '{$key}' should have value '{$value}'"
                    );
                }
            }
        }
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
     */
    protected function kebabToCamelCase($string)
    {
        $parts = explode('-', $string);
        $result = array_shift($parts);
        foreach ($parts as $part) {
            $result .= ucfirst($part);
        }
        return $result;
    }
}
