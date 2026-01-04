<?php

namespace tests\unit\site;

use Codeception\Test\Unit;
use yii\web\Response;
use app\controllers\SiteController;
use app\models\Accounts;
use app\models\Workers;
use app\models\Blocks;
use app\models\Coins;

/**
 * Property-based tests for AJAX Dynamic Updates
 * 
 * Feature: yiimp-to-yiimp2-migration, Property 48: AJAX dynamic updates
 */
class AjaxDynamicUpdatesPropertyTest extends Unit
{
    protected $controller;
    
    protected function _before()
    {
        parent::_before();
        $this->controller = new SiteController('site', \Yii::$app);
    }
    
    /**
     * Check if database is available
     * 
     * @return bool
     */
    protected function isDatabaseAvailable()
    {
        try {
            \Yii::$app->db->open();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Property 48: AJAX Dynamic Updates
     * 
     * For any dynamic content area (wallet statistics, mining results, pool status),
     * updates should use AJAX requests without full page reloads.
     * 
     * This property verifies that:
     * 1. AJAX endpoints return partial content (not full HTML pages)
     * 2. AJAX endpoints return valid data structures
     * 3. AJAX endpoints do not include layout/navigation elements
     * 4. AJAX endpoints can be called repeatedly without side effects
     * 
     * Validates: Requirements 14.5
     * 
     * @test
     */
    public function testAjaxDynamicUpdates()
    {
        // Feature: yiimp-to-yiimp2-migration, Property 48: AJAX dynamic updates
        
        // Skip if database not available
        if (!$this->isDatabaseAvailable()) {
            $this->markTestSkipped('Database connection not available');
        }
        
        $iterations = 100;
        $failures = [];
        
        // Define AJAX endpoints to test
        $ajaxEndpoints = [
            'actionWallet_results',
            'actionWallet_miners_results',
            'actionWallet_found_results',
            'actionMining_results',
            'actionCurrent_results',
            'actionFound_results',
            'actionMiners_results',
            'actionHistory_results',
            'actionCoins_info',
            'actionBlock_results',
        ];
        
        for ($i = 0; $i < $iterations; $i++) {
            // Randomly select an endpoint to test
            $endpoint = $ajaxEndpoints[array_rand($ajaxEndpoints)];
            
            try {
                // Set up test data if needed
                $this->setupTestDataForEndpoint($endpoint);
                
                // Call the AJAX endpoint
                $response = $this->callAjaxEndpoint($endpoint);
                
                // Verify response is partial content (not full page)
                if (!$this->isPartialContent($response)) {
                    $failures[] = [
                        'iteration' => $i,
                        'endpoint' => $endpoint,
                        'reason' => 'Response appears to be full page HTML instead of partial content',
                        'response_length' => strlen($response),
                        'has_html_tag' => (stripos($response, '<html') !== false),
                        'has_body_tag' => (stripos($response, '<body') !== false),
                    ];
                }
                
                // Verify response does not include layout elements
                if ($this->hasLayoutElements($response)) {
                    $failures[] = [
                        'iteration' => $i,
                        'endpoint' => $endpoint,
                        'reason' => 'Response includes layout/navigation elements',
                        'has_nav' => (stripos($response, '<nav') !== false),
                        'has_header' => (stripos($response, '<header') !== false),
                        'has_footer' => (stripos($response, '<footer') !== false),
                    ];
                }
                
                // Verify response is not empty (unless legitimately no data)
                if (empty($response) && $this->shouldHaveContent($endpoint)) {
                    $failures[] = [
                        'iteration' => $i,
                        'endpoint' => $endpoint,
                        'reason' => 'Response is empty when content expected',
                    ];
                }
                
                // Verify idempotency - calling twice should return same structure
                $response2 = $this->callAjaxEndpoint($endpoint);
                if (!$this->responsesHaveSameStructure($response, $response2)) {
                    $failures[] = [
                        'iteration' => $i,
                        'endpoint' => $endpoint,
                        'reason' => 'Multiple calls return different structures (not idempotent)',
                    ];
                }
                
            } catch (\Exception $e) {
                $failures[] = [
                    'iteration' => $i,
                    'endpoint' => $endpoint,
                    'reason' => 'Exception thrown',
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ];
            }
            
            // Clean up test data
            $this->cleanupTestData();
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
     * Test that AJAX endpoints return data without full page reload
     * 
     * @test
     */
    public function testAjaxEndpointsReturnPartialContent()
    {
        // Feature: yiimp-to-yiimp2-migration, Property 48: AJAX dynamic updates (partial content)
        
        // Skip if database not available
        if (!$this->isDatabaseAvailable()) {
            $this->markTestSkipped('Database connection not available');
        }
        
        $ajaxEndpoints = [
            'actionWallet_results',
            'actionMining_results',
            'actionCurrent_results',
            'actionFound_results',
        ];
        
        foreach ($ajaxEndpoints as $endpoint) {
            $response = $this->callAjaxEndpoint($endpoint);
            
            // Should not contain full HTML document structure
            $this->assertStringNotContainsString(
                '<!DOCTYPE',
                $response,
                "AJAX endpoint $endpoint should not return full HTML document"
            );
            
            $this->assertStringNotContainsString(
                '<html',
                strtolower($response),
                "AJAX endpoint $endpoint should not contain <html> tag"
            );
            
            // Should not contain layout navigation
            $this->assertStringNotContainsString(
                '<nav',
                strtolower($response),
                "AJAX endpoint $endpoint should not contain navigation elements"
            );
        }
    }
    
    /**
     * Test that AJAX endpoints can be called multiple times
     * 
     * @test
     */
    public function testAjaxEndpointsAreIdempotent()
    {
        // Feature: yiimp-to-yiimp2-migration, Property 48: AJAX dynamic updates (idempotency)
        
        // Skip if database not available
        if (!$this->isDatabaseAvailable()) {
            $this->markTestSkipped('Database connection not available');
        }
        
        $endpoint = 'actionCurrent_results';
        
        // Call endpoint multiple times
        $response1 = $this->callAjaxEndpoint($endpoint);
        $response2 = $this->callAjaxEndpoint($endpoint);
        $response3 = $this->callAjaxEndpoint($endpoint);
        
        // Responses should have same structure (content may vary slightly due to time)
        $this->assertTrue(
            $this->responsesHaveSameStructure($response1, $response2),
            "Multiple AJAX calls should return same structure"
        );
        
        $this->assertTrue(
            $this->responsesHaveSameStructure($response2, $response3),
            "Multiple AJAX calls should return same structure"
        );
    }
    
    /**
     * Call an AJAX endpoint
     * 
     * @param string $endpoint
     * @return string
     */
    protected function callAjaxEndpoint($endpoint)
    {
        // Set up request parameters if needed
        if (strpos($endpoint, 'wallet') !== false || strpos($endpoint, 'Wallet') !== false) {
            \Yii::$app->request->setQueryParams(['address' => $this->getTestAddress()]);
        }
        
        // Call the endpoint
        ob_start();
        $result = $this->controller->$endpoint();
        $output = ob_get_clean();
        
        // Return the result (either direct return or captured output)
        return $result ?: $output;
    }
    
    /**
     * Check if response is partial content (not full page)
     * 
     * @param string $response
     * @return bool
     */
    protected function isPartialContent($response)
    {
        // Partial content should not have these full-page elements
        $fullPageIndicators = [
            '<!DOCTYPE',
            '<html',
            '<head>',
            '</html>',
        ];
        
        foreach ($fullPageIndicators as $indicator) {
            if (stripos($response, $indicator) !== false) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Check if response has layout elements
     * 
     * @param string $response
     * @return bool
     */
    protected function hasLayoutElements($response)
    {
        $layoutIndicators = [
            '<nav',
            '<header',
            '<footer',
            'class="navbar"',
            'class="header"',
            'class="footer"',
        ];
        
        foreach ($layoutIndicators as $indicator) {
            if (stripos($response, $indicator) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if endpoint should have content
     * 
     * @param string $endpoint
     * @return bool
     */
    protected function shouldHaveContent($endpoint)
    {
        // Some endpoints may legitimately return empty if no data
        $canBeEmpty = [
            'actionWallet_found_results',
            'actionBlock_results',
        ];
        
        return !in_array($endpoint, $canBeEmpty);
    }
    
    /**
     * Check if two responses have the same structure
     * 
     * @param string $response1
     * @param string $response2
     * @return bool
     */
    protected function responsesHaveSameStructure($response1, $response2)
    {
        // Extract HTML structure (tags) ignoring content
        $structure1 = $this->extractHtmlStructure($response1);
        $structure2 = $this->extractHtmlStructure($response2);
        
        // Allow for minor differences (timestamps, etc.)
        $similarity = similar_text($structure1, $structure2, $percent);
        
        // Consider same structure if 90% similar
        return $percent >= 90;
    }
    
    /**
     * Extract HTML structure from response
     * 
     * @param string $html
     * @return string
     */
    protected function extractHtmlStructure($html)
    {
        // Remove content between tags, keep only structure
        $structure = preg_replace('/>([^<]+)</s', '><', $html);
        
        // Remove attributes that may vary (ids, timestamps, etc.)
        $structure = preg_replace('/\s+id="[^"]*"/', '', $structure);
        $structure = preg_replace('/\s+data-[^=]*="[^"]*"/', '', $structure);
        
        return $structure;
    }
    
    /**
     * Set up test data for specific endpoint
     * 
     * @param string $endpoint
     */
    protected function setupTestDataForEndpoint($endpoint)
    {
        // Create minimal test data if needed
        if (strpos($endpoint, 'wallet') !== false || strpos($endpoint, 'Wallet') !== false) {
            $this->ensureTestAccount();
        }
        
        if (strpos($endpoint, 'block') !== false || strpos($endpoint, 'Block') !== false) {
            $this->ensureTestBlock();
        }
    }
    
    /**
     * Ensure test account exists
     */
    protected function ensureTestAccount()
    {
        $address = $this->getTestAddress();
        $account = Accounts::findOne(['username' => $address]);
        
        if (!$account) {
            $account = new Accounts();
            $account->username = $address;
            $account->balance = 0.001;
            $account->coinid = 1;
            $account->save();
        }
    }
    
    /**
     * Ensure test block exists
     */
    protected function ensureTestBlock()
    {
        $block = Blocks::find()->limit(1)->one();
        
        if (!$block) {
            // Create a test coin first
            $coin = Coins::find()->limit(1)->one();
            if (!$coin) {
                $coin = new Coins();
                $coin->name = 'TestCoin';
                $coin->symbol = 'TEST';
                $coin->algo = 'sha256';
                $coin->enable = 1;
                $coin->save();
            }
            
            // Create a test block
            $block = new Blocks();
            $block->coin_id = $coin->id;
            $block->height = 1;
            $block->confirmations = 100;
            $block->time = time();
            $block->userid = 1;
            $block->algo = 'sha256';
            $block->difficulty = 1000;
            $block->amount = 50;
            $block->hash = bin2hex(random_bytes(32));
            $block->category = 'generate';
            $block->save();
        }
    }
    
    /**
     * Get test wallet address
     * 
     * @return string
     */
    protected function getTestAddress()
    {
        return 'TestAddress123456789ABCDEFGH';
    }
    
    /**
     * Clean up test data
     */
    protected function cleanupTestData()
    {
        // Clean up is handled by test teardown
        // Individual tests should not leave persistent data
    }
}
