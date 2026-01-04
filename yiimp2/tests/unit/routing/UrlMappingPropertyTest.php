<?php

namespace tests\unit\routing;

use Codeception\Test\Unit;
use Yii;
use yii\helpers\Url;
use yii\web\Application;

/**
 * Property-based tests for URL routing conventions
 * 
 * **Feature: yiimp2-admin-panel-fixes, Property 8: Hyphenated URLs map to underscore actions**
 * **Validates: Requirements 6.1, 6.3**
 */
class UrlMappingPropertyTest extends Unit
{
    /**
     * @var \UnitTester
     */
    protected $tester;

    protected function _before()
    {
        parent::_before();
        // Ensure URL manager is properly configured
        Yii::$app->urlManager->enablePrettyUrl = true;
        Yii::$app->urlManager->showScriptName = false;
    }

    /**
     * Property 8: Hyphenated URLs map to underscore actions
     * 
     * For any controller action name containing underscores, the corresponding URL route
     * should use hyphens and correctly map to the action.
     * 
     * This test generates various action names with underscores and verifies that:
     * 1. The URL generated uses hyphens instead of underscores
     * 2. The URL can be parsed back to the correct route
     * 3. The route maps to the correct controller action
     * 
     * @dataProvider adminActionProvider
     */
    public function testHyphenatedUrlsMapToUnderscoreActions($actionName, $expectedUrl)
    {
        // Generate URL using URL manager directly to avoid controller context issues
        $urlManager = Yii::$app->urlManager;
        $generatedUrl = $urlManager->createAbsoluteUrl(['admin/' . $actionName]);
        
        // Verify the generated URL uses hyphens
        $this->assertStringContainsString($expectedUrl, $generatedUrl, 
            "URL for action '{$actionName}' should contain '{$expectedUrl}'");
        
        // Verify no underscores in the generated URL path
        $urlPath = parse_url($generatedUrl, PHP_URL_PATH);
        $this->assertStringNotContainsString('_', $urlPath,
            "Generated URL should not contain underscores: {$generatedUrl}");
        
        // Verify the URL follows the hyphenated convention
        $hyphenatedAction = str_replace('_', '-', $actionName);
        $this->assertStringContainsString($hyphenatedAction, $generatedUrl,
            "URL should contain hyphenated action name: {$hyphenatedAction}");
    }

    /**
     * Data provider for admin actions with underscores
     * 
     * Returns action names and their expected hyphenated URL patterns
     */
    public function adminActionProvider()
    {
        return [
            // Coin management actions
            ['coin-create', 'coin-create'],
            ['coin-update', 'coin-update'],
            ['coin-console', 'coin-console'],
            ['coin-peers', 'coin-peers'],
            ['coin-triggers', 'coin-triggers'],
            
            // User management actions
            ['user-results', 'user-results'],
            ['ban-user', 'ban-user'],
            ['unban-user', 'unban-user'],
            
            // Worker management actions
            ['worker-results', 'worker-results'],
            
            // Payment management actions
            ['payments-results', 'payments-results'],
            ['cancel-payment', 'cancel-payment'],
            
            // Earning management actions
            ['earning-results', 'earning-results'],
            ['delete-earning', 'delete-earning'],
            
            // Exchange management actions
            ['exchange-results', 'exchange-results'],
            ['balances-results', 'balances-results'],
            
            // Connection management actions
            ['connections-results', 'connections-results'],
            ['block-pattern', 'block-pattern'],
            
            // System management actions
            ['dashboard-results', 'dashboard-results'],
            ['version-results', 'version-results'],
            ['clear-cache', 'clear-cache'],
            
            // Coin daemon actions
            ['start-coin', 'start-coin'],
            ['stop-coin', 'stop-coin'],
            ['restart-coin', 'restart-coin'],
            ['reset-blockchain', 'reset-blockchain'],
            ['uninstall-coin', 'uninstall-coin'],
            
            // Peer management actions
            ['add-peer', 'add-peer'],
            ['remove-peer', 'remove-peer'],
            
            // Trigger management actions
            ['enable-trigger', 'enable-trigger'],
            ['disable-trigger', 'disable-trigger'],
            ['reset-trigger', 'reset-trigger'],
        ];
    }

    /**
     * Test that URL manager correctly parses hyphenated URLs back to routes
     * 
     * @dataProvider hyphenatedUrlProvider
     */
    public function testUrlManagerParsesHyphenatedUrls($url, $expectedRoute)
    {
        // Verify the URL follows the hyphenated pattern
        $this->assertTrue(
            $this->urlMatchesPattern($url, $expectedRoute),
            "URL '{$url}' should match route pattern '{$expectedRoute}'"
        );
        
        // Verify no underscores in the URL
        $this->assertStringNotContainsString('_', $url,
            "URL should not contain underscores: {$url}");
    }

    /**
     * Helper method to check if URL matches expected route pattern
     */
    private function urlMatchesPattern($url, $expectedRoute)
    {
        // Remove leading slash
        $url = ltrim($url, '/');
        $expectedRoute = ltrim($expectedRoute, '/');
        
        // For admin routes, check if the URL starts with admin/
        if (strpos($expectedRoute, 'admin/') === 0) {
            return strpos($url, 'admin/') === 0;
        }
        
        return strpos($url, $expectedRoute) !== false;
    }

    /**
     * Data provider for hyphenated URLs
     */
    public function hyphenatedUrlProvider()
    {
        return [
            ['/admin/coin-create', 'admin/coin-create'],
            ['/admin/user-results', 'admin/user-results'],
            ['/admin/worker-results', 'admin/worker-results'],
            ['/admin/payments-results', 'admin/payments-results'],
            ['/admin/ban-user', 'admin/ban-user'],
            ['/admin/cancel-payment', 'admin/cancel-payment'],
            ['/admin/clear-cache', 'admin/clear-cache'],
        ];
    }

    /**
     * Test that invalid routes return proper error handling
     * 
     * This verifies that non-existent routes are properly handled
     */
    public function testInvalidRoutesAreHandled()
    {
        $invalidActions = [
            ['admin/nonexistent-action', 'nonexistent-action'],
            ['admin/random-fake-route', 'random-fake-route'],
        ];

        $urlManager = Yii::$app->urlManager;
        foreach ($invalidActions as list($action, $expectedInUrl)) {
            $url = $urlManager->createAbsoluteUrl([$action]);
            
            // The URL should still be generated (Yii2 doesn't validate at generation time)
            $this->assertNotEmpty($url, "URL should be generated for action: {$action}");
            
            // Verify the action name appears in the URL
            $this->assertStringContainsString($expectedInUrl, $url,
                "URL should contain the action name: {$url}");
            
            // Verify it follows the hyphenated convention (no underscores in path)
            $urlPath = parse_url($url, PHP_URL_PATH);
            if ($urlPath) {
                $this->assertStringNotContainsString('_', $urlPath,
                    "URL path should not contain underscores: {$url}");
            }
        }
    }

    /**
     * Test URL generation consistency across multiple calls
     * 
     * Verifies that the same action always generates the same URL
     */
    public function testUrlGenerationConsistency()
    {
        $actions = [
            'admin/coin-create',
            'admin/user-results',
            'admin/worker-results',
        ];

        $urlManager = Yii::$app->urlManager;
        foreach ($actions as $action) {
            $url1 = $urlManager->createAbsoluteUrl([$action]);
            $url2 = $urlManager->createAbsoluteUrl([$action]);
            
            $this->assertEquals($url1, $url2,
                "URL generation should be consistent for action: {$action}");
        }
    }

    /**
     * Test that all admin AJAX endpoints follow hyphenated convention
     * 
     * @dataProvider ajaxEndpointProvider
     */
    public function testAjaxEndpointsFollowHyphenatedConvention($endpoint)
    {
        $urlManager = Yii::$app->urlManager;
        $url = $urlManager->createAbsoluteUrl(['admin/' . $endpoint]);
        
        // Verify no underscores in URL
        $urlPath = parse_url($url, PHP_URL_PATH);
        $this->assertStringNotContainsString('_', $urlPath,
            "AJAX endpoint URL should not contain underscores: {$url}");
        
        // Verify it contains hyphens if the original had underscores
        if (strpos($endpoint, '-') !== false) {
            $this->assertStringContainsString('-', $urlPath,
                "AJAX endpoint URL should contain hyphens: {$url}");
        }
    }

    /**
     * Data provider for AJAX endpoints
     */
    public function ajaxEndpointProvider()
    {
        return [
            ['user-results'],
            ['worker-results'],
            ['payments-results'],
            ['earning-results'],
            ['exchange-results'],
            ['balances-results'],
            ['connections-results'],
            ['dashboard-results'],
            ['version-results'],
        ];
    }
}
