<?php

namespace app\tests\unit\routing;

use Codeception\Test\Unit;
use yii\helpers\Url;
use yii\web\UrlManager;
use ReflectionClass;
use ReflectionMethod;
use Faker\Factory as FakerFactory;

/**
 * Property-based test for URL Generation Consistency
 * 
 * **Feature: yiimp2-routing-database-alignment, Property 1: URL Generation Consistency**
 * **Validates: Requirements 1.1, 1.2, 1.3**
 * 
 * This test verifies that:
 * 1. For any admin panel view, all generated URLs resolve to existing controller actions
 * 2. URL generation produces valid routes without 404 errors
 * 3. Random action names are properly converted to valid URLs
 */
class UrlGenerationPropertyTest extends Unit
{
    /**
     * @var \UnitTester
     */
    protected $tester;
    
    /**
     * @var UrlManager
     */
    protected $urlManager;

    /**
     * @var \Faker\Generator
     */
    protected $faker;

    /**
     * @var array List of actual admin controller actions
     */
    protected $actualActions = [];

    protected function _before()
    {
        $this->urlManager = \Yii::$app->urlManager;
        $this->faker = FakerFactory::create();
        
        // Get all actual actions from AdminController
        $this->actualActions = $this->getAdminControllerActions();
    }

    /**
     * Property 1: URL Generation Consistency
     * 
     * For any admin panel view, all generated URLs should resolve to existing 
     * controller actions without 404 errors.
     * 
     * This test generates random action names from the actual AdminController
     * and verifies that:
     * 1. URL generation produces valid routes
     * 2. URLs use kebab-case convention
     * 3. No 404 errors would occur
     * 
     * Runs 100 iterations with random action selections.
     */
    public function testUrlGenerationConsistencyForRandomActions()
    {
        $iterations = 100;
        $failures = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            // Pick a random action from actual AdminController actions
            $action = $this->faker->randomElement($this->actualActions);
            
            try {
                // Generate URL for the action
                $url = $this->urlManager->createUrl(['admin/' . $action]);
                
                // Verify URL is not empty
                if (empty($url)) {
                    $failures[] = "Iteration $i: Action '$action' generated empty URL";
                    continue;
                }
                
                // Verify URL contains the action name (in kebab-case)
                if (!str_contains($url, $action)) {
                    $failures[] = "Iteration $i: Action '$action' not found in generated URL: $url";
                    continue;
                }
                
                // Verify URL uses kebab-case (no underscores in path)
                $urlPath = parse_url($url, PHP_URL_PATH);
                if ($urlPath && str_contains($urlPath, '_')) {
                    $failures[] = "Iteration $i: URL path contains underscores: $url";
                    continue;
                }
                
                // Verify the action exists in AdminController
                $camelCaseAction = $this->kebabToCamelCase($action);
                $actionMethod = 'action' . ucfirst($camelCaseAction);
                
                if (!method_exists('app\controllers\AdminController', $actionMethod)) {
                    $failures[] = "Iteration $i: Action method '$actionMethod' does not exist for action '$action'";
                }
                
            } catch (\Exception $e) {
                $failures[] = "Iteration $i: Exception for action '$action': " . $e->getMessage();
            }
        }
        
        $this->assertEmpty(
            $failures,
            "URL generation consistency failures:\n" . implode("\n", $failures)
        );
    }

    /**
     * Test URL generation with random parameters
     * 
     * Verifies that URLs with parameters are generated correctly and consistently.
     */
    public function testUrlGenerationWithRandomParameters()
    {
        $iterations = 100;
        $failures = [];
        
        // Actions that typically require an ID parameter
        $actionsWithId = [
            'coin-update', 'coin', 'coin-console', 'coin-peers', 
            'start-coin', 'stop-coin', 'restart-coin',
            'ban-user', 'unban-user', 'cancel-payment', 'delete-earning'
        ];
        
        for ($i = 0; $i < $iterations; $i++) {
            // Pick a random action that requires ID
            $action = $this->faker->randomElement($actionsWithId);
            
            // Generate random ID
            $id = $this->faker->numberBetween(1, 1000);
            
            try {
                // Generate URL with ID parameter
                $url = $this->urlManager->createUrl(['admin/' . $action, 'id' => $id]);
                
                // Verify URL is not empty
                if (empty($url)) {
                    $failures[] = "Iteration $i: Action '$action' with ID $id generated empty URL";
                    continue;
                }
                
                // Verify URL contains the action
                if (!str_contains($url, $action)) {
                    $failures[] = "Iteration $i: Action '$action' not found in URL: $url";
                    continue;
                }
                
                // Verify URL contains the ID (either in path or query string)
                $idString = (string)$id;
                if (!str_contains($url, $idString)) {
                    $failures[] = "Iteration $i: ID $id not found in URL: $url";
                    continue;
                }
                
                // Verify no underscores in path
                $urlPath = parse_url($url, PHP_URL_PATH);
                if ($urlPath && str_contains($urlPath, '_')) {
                    $failures[] = "Iteration $i: URL path contains underscores: $url";
                }
                
            } catch (\Exception $e) {
                $failures[] = "Iteration $i: Exception for action '$action' with ID $id: " . $e->getMessage();
            }
        }
        
        $this->assertEmpty(
            $failures,
            "URL generation with parameters failures:\n" . implode("\n", $failures)
        );
    }

    /**
     * Test URL generation with random query parameters
     * 
     * Verifies that URLs with query parameters are generated correctly.
     */
    public function testUrlGenerationWithRandomQueryParameters()
    {
        $iterations = 100;
        $failures = [];
        
        // Actions that typically use query parameters
        $actionsWithQuery = [
            'user-results', 'worker-results', 'payments-results',
            'earning-results', 'exchange-results', 'balances-results'
        ];
        
        for ($i = 0; $i < $iterations; $i++) {
            // Pick a random action
            $action = $this->faker->randomElement($actionsWithQuery);
            
            // Generate random query parameters
            $params = ['admin/' . $action];
            
            // Add 1-3 random query parameters
            $paramCount = $this->faker->numberBetween(1, 3);
            for ($j = 0; $j < $paramCount; $j++) {
                $key = $this->faker->randomElement(['search', 'coinid', 'algo', 'status', 'active']);
                $value = $this->faker->randomElement([
                    $this->faker->word,
                    $this->faker->numberBetween(1, 100),
                    $this->faker->randomElement(['sha256', 'scrypt', 'x11'])
                ]);
                $params[$key] = $value;
            }
            
            try {
                // Generate URL with query parameters
                $url = $this->urlManager->createUrl($params);
                
                // Verify URL is not empty
                if (empty($url)) {
                    $failures[] = "Iteration $i: Action '$action' with params generated empty URL";
                    continue;
                }
                
                // Verify URL contains the action
                if (!str_contains($url, $action)) {
                    $failures[] = "Iteration $i: Action '$action' not found in URL: $url";
                    continue;
                }
                
                // Verify no underscores in path (query params can have underscores)
                $urlPath = parse_url($url, PHP_URL_PATH);
                if ($urlPath && str_contains($urlPath, '_')) {
                    $failures[] = "Iteration $i: URL path contains underscores: $url";
                }
                
            } catch (\Exception $e) {
                $failures[] = "Iteration $i: Exception for action '$action': " . $e->getMessage();
            }
        }
        
        $this->assertEmpty(
            $failures,
            "URL generation with query parameters failures:\n" . implode("\n", $failures)
        );
    }

    /**
     * Test that URL generation is idempotent
     * 
     * Verifies that generating the same URL multiple times produces identical results.
     */
    public function testUrlGenerationIsIdempotent()
    {
        $iterations = 50;
        $failures = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            // Pick a random action
            $action = $this->faker->randomElement($this->actualActions);
            
            // Generate random parameters
            $params = ['admin/' . $action];
            
            // Randomly add an ID parameter
            if ($this->faker->boolean(50)) {
                $params['id'] = $this->faker->numberBetween(1, 100);
            }
            
            try {
                // Generate URL three times
                $url1 = $this->urlManager->createUrl($params);
                $url2 = $this->urlManager->createUrl($params);
                $url3 = $this->urlManager->createUrl($params);
                
                // Verify all three are identical
                if ($url1 !== $url2 || $url2 !== $url3) {
                    $failures[] = "Iteration $i: URL generation not idempotent for action '$action'\n" .
                                  "  URL1: $url1\n" .
                                  "  URL2: $url2\n" .
                                  "  URL3: $url3";
                }
                
            } catch (\Exception $e) {
                $failures[] = "Iteration $i: Exception for action '$action': " . $e->getMessage();
            }
        }
        
        $this->assertEmpty(
            $failures,
            "URL generation idempotency failures:\n" . implode("\n", $failures)
        );
    }

    /**
     * Test that all generated URLs follow kebab-case convention
     * 
     * Verifies that no URLs contain underscores or camelCase in the path.
     */
    public function testAllGeneratedUrlsFollowKebabCaseConvention()
    {
        $iterations = 100;
        $failures = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            // Pick a random action
            $action = $this->faker->randomElement($this->actualActions);
            
            try {
                // Generate URL
                $url = $this->urlManager->createUrl(['admin/' . $action]);
                
                // Extract path component
                $urlPath = parse_url($url, PHP_URL_PATH);
                
                if (!$urlPath) {
                    continue;
                }
                
                // Check for underscores in path
                if (str_contains($urlPath, '_')) {
                    $failures[] = "Iteration $i: URL path contains underscores: $url";
                }
                
                // Check for camelCase in path (uppercase letters after lowercase)
                if (preg_match('/[a-z][A-Z]/', $urlPath)) {
                    $failures[] = "Iteration $i: URL path contains camelCase: $url";
                }
                
            } catch (\Exception $e) {
                $failures[] = "Iteration $i: Exception for action '$action': " . $e->getMessage();
            }
        }
        
        $this->assertEmpty(
            $failures,
            "Kebab-case convention failures:\n" . implode("\n", $failures)
        );
    }

    /**
     * Test that absolute URLs are generated correctly
     * 
     * Verifies that absolute URL generation works for random actions.
     */
    public function testAbsoluteUrlGenerationForRandomActions()
    {
        $iterations = 50;
        $failures = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            // Pick a random action
            $action = $this->faker->randomElement($this->actualActions);
            
            try {
                // Generate absolute URL
                $url = $this->urlManager->createAbsoluteUrl(['admin/' . $action]);
                
                // Verify URL is not empty
                if (empty($url)) {
                    $failures[] = "Iteration $i: Absolute URL for action '$action' is empty";
                    continue;
                }
                
                // Verify URL contains the action
                if (!str_contains($url, $action)) {
                    $failures[] = "Iteration $i: Action '$action' not found in absolute URL: $url";
                }
                
            } catch (\Exception $e) {
                $failures[] = "Iteration $i: Exception for action '$action': " . $e->getMessage();
            }
        }
        
        $this->assertEmpty(
            $failures,
            "Absolute URL generation failures:\n" . implode("\n", $failures)
        );
    }

    /**
     * Test round-trip conversion: action name -> URL -> action name
     * 
     * Verifies that we can extract the action name from a generated URL.
     */
    public function testRoundTripActionNameConversion()
    {
        $iterations = 100;
        $failures = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            // Pick a random action
            $originalAction = $this->faker->randomElement($this->actualActions);
            
            try {
                // Generate URL
                $url = $this->urlManager->createUrl(['admin/' . $originalAction]);
                
                // Extract path
                $urlPath = parse_url($url, PHP_URL_PATH);
                
                if (!$urlPath) {
                    continue;
                }
                
                // Extract action from path (should be after /admin/)
                if (preg_match('#/admin/([^/?]+)#', $urlPath, $matches)) {
                    $extractedAction = $matches[1];
                    
                    // Verify extracted action matches original
                    if ($extractedAction !== $originalAction) {
                        $failures[] = "Iteration $i: Round-trip failed\n" .
                                      "  Original: $originalAction\n" .
                                      "  URL: $url\n" .
                                      "  Extracted: $extractedAction";
                    }
                }
                
            } catch (\Exception $e) {
                $failures[] = "Iteration $i: Exception for action '$originalAction': " . $e->getMessage();
            }
        }
        
        $this->assertEmpty(
            $failures,
            "Round-trip conversion failures:\n" . implode("\n", $failures)
        );
    }

    /**
     * Get all action methods from AdminController
     * 
     * @return array List of action names in kebab-case
     */
    protected function getAdminControllerActions()
    {
        $reflection = new ReflectionClass('app\controllers\AdminController');
        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
        
        $actions = [];
        foreach ($methods as $method) {
            $name = $method->getName();
            
            // Skip non-action methods
            if (strpos($name, 'action') !== 0 || $name === 'actions') {
                continue;
            }
            
            // Convert actionCoinWallets to coin-wallets
            $actionName = $this->camelCaseToKebab(substr($name, 6));
            $actions[] = $actionName;
        }
        
        return $actions;
    }

    /**
     * Convert camelCase to kebab-case
     * 
     * @param string $string
     * @return string
     */
    protected function camelCaseToKebab($string)
    {
        return strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $string));
    }

    /**
     * Convert kebab-case to camelCase
     * 
     * @param string $string
     * @return string
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
