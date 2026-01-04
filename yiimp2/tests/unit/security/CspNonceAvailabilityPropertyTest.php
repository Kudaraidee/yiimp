<?php

namespace tests\unit\security;

use Codeception\Test\Unit;
use Yii;
use yii\web\View;

/**
 * Property Test: Nonce availability in views
 * 
 * Feature: yiimp2-nginx-migration, Property 6: Nonce availability in views
 * Validates: Requirements 3.2
 * 
 * This test verifies that the nonce value is available in view params
 * as both 'cspNonce' and 'cspNonceAttr' for use in templates.
 */
class CspNonceAvailabilityPropertyTest extends Unit
{
    protected $tester;
    
    /**
     * Property: For any view render, the nonce value should be available
     * in the view params as both 'cspNonce' and 'cspNonceAttr'
     */
    public function testNonceAvailableInViewParams()
    {
        $iterations = 100;
        $failures = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            // Create a mock application with CspNonceManager
            $this->mockApplication();
            
            // Simulate the beforeRender event that sets nonce in view params
            $view = Yii::$app->view;
            
            // Trigger the beforeRender event manually
            $view->trigger(View::EVENT_BEFORE_RENDER);
            
            // Check if cspNonce is available
            if (!isset($view->params['cspNonce'])) {
                $failures[] = [
                    'iteration' => $i,
                    'reason' => 'cspNonce not available in view params',
                    'available_params' => array_keys($view->params)
                ];
                continue;
            }
            
            // Check if cspNonceAttr is available
            if (!isset($view->params['cspNonceAttr'])) {
                $failures[] = [
                    'iteration' => $i,
                    'reason' => 'cspNonceAttr not available in view params',
                    'available_params' => array_keys($view->params)
                ];
                continue;
            }
            
            // Verify cspNonce is a non-empty string
            if (!is_string($view->params['cspNonce']) || empty($view->params['cspNonce'])) {
                $failures[] = [
                    'iteration' => $i,
                    'reason' => 'cspNonce is not a valid non-empty string',
                    'value' => $view->params['cspNonce']
                ];
            }
            
            // Verify cspNonceAttr is properly formatted
            $expectedFormat = 'nonce="' . $view->params['cspNonce'] . '"';
            if ($view->params['cspNonceAttr'] !== $expectedFormat) {
                $failures[] = [
                    'iteration' => $i,
                    'reason' => 'cspNonceAttr format incorrect',
                    'expected' => $expectedFormat,
                    'actual' => $view->params['cspNonceAttr']
                ];
            }
            
            $this->destroyApplication();
        }
        
        if (!empty($failures)) {
            $failureMessage = "Found nonce availability issues in views:\n";
            foreach (array_slice($failures, 0, 5) as $failure) {
                $failureMessage .= sprintf(
                    "Iteration %d: %s\n",
                    $failure['iteration'],
                    $failure['reason']
                );
            }
            $failureMessage .= sprintf("\nTotal failures: %d out of %d iterations", count($failures), $iterations);
            $this->fail($failureMessage);
        }
        
        $this->assertTrue(true, "Nonce available in view params across $iterations iterations");
    }
    
    /**
     * Property: For any view that accesses the nonce params, the values
     * should remain consistent throughout the view rendering
     */
    public function testNonceConsistencyDuringViewRender()
    {
        $iterations = 100;
        $failures = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            $this->mockApplication();
            
            $view = Yii::$app->view;
            $view->trigger(View::EVENT_BEFORE_RENDER);
            
            // Get nonce values multiple times during "render"
            $nonce1 = $view->params['cspNonce'] ?? null;
            $nonceAttr1 = $view->params['cspNonceAttr'] ?? null;
            
            // Simulate some rendering work
            usleep(10);
            
            $nonce2 = $view->params['cspNonce'] ?? null;
            $nonceAttr2 = $view->params['cspNonceAttr'] ?? null;
            
            // Verify consistency
            if ($nonce1 !== $nonce2) {
                $failures[] = [
                    'iteration' => $i,
                    'reason' => 'cspNonce changed during view render',
                    'first' => $nonce1,
                    'second' => $nonce2
                ];
            }
            
            if ($nonceAttr1 !== $nonceAttr2) {
                $failures[] = [
                    'iteration' => $i,
                    'reason' => 'cspNonceAttr changed during view render',
                    'first' => $nonceAttr1,
                    'second' => $nonceAttr2
                ];
            }
            
            $this->destroyApplication();
        }
        
        if (!empty($failures)) {
            $failureMessage = "Found nonce consistency issues during view render:\n";
            foreach (array_slice($failures, 0, 5) as $failure) {
                $failureMessage .= sprintf(
                    "Iteration %d: %s\n",
                    $failure['iteration'],
                    $failure['reason']
                );
            }
            $failureMessage .= sprintf("\nTotal failures: %d out of %d iterations", count($failures), $iterations);
            $this->fail($failureMessage);
        }
        
        $this->assertTrue(true, "Nonce remains consistent during view render across $iterations iterations");
    }
    
    /**
     * Property: For any view, the nonce should be accessible via both
     * $this->params['cspNonce'] and Yii::$app->view->params['cspNonce']
     */
    public function testNonceAccessibleViaDifferentPaths()
    {
        $iterations = 100;
        $failures = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            $this->mockApplication();
            
            $view = Yii::$app->view;
            $view->trigger(View::EVENT_BEFORE_RENDER);
            
            // Access via Yii::$app->view->params
            $nonceViaApp = Yii::$app->view->params['cspNonce'] ?? null;
            
            // Access via view instance
            $nonceViaView = $view->params['cspNonce'] ?? null;
            
            // Both should be the same
            if ($nonceViaApp !== $nonceViaView) {
                $failures[] = [
                    'iteration' => $i,
                    'reason' => 'Nonce differs when accessed via different paths',
                    'via_app' => $nonceViaApp,
                    'via_view' => $nonceViaView
                ];
            }
            
            // Both should be non-null
            if ($nonceViaApp === null || $nonceViaView === null) {
                $failures[] = [
                    'iteration' => $i,
                    'reason' => 'Nonce is null when accessed',
                    'via_app' => $nonceViaApp,
                    'via_view' => $nonceViaView
                ];
            }
            
            $this->destroyApplication();
        }
        
        if (!empty($failures)) {
            $failureMessage = "Found nonce accessibility issues:\n";
            foreach (array_slice($failures, 0, 5) as $failure) {
                $failureMessage .= sprintf(
                    "Iteration %d: %s\n",
                    $failure['iteration'],
                    $failure['reason']
                );
            }
            $failureMessage .= sprintf("\nTotal failures: %d out of %d iterations", count($failures), $iterations);
            $this->fail($failureMessage);
        }
        
        $this->assertTrue(true, "Nonce accessible via different paths across $iterations iterations");
    }
    
    /**
     * Property: For any view with the nonce params set, the nonce should
     * be usable in inline script and style tags
     */
    public function testNonceUsableInInlineTags()
    {
        $iterations = 100;
        $failures = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            $this->mockApplication();
            
            $view = Yii::$app->view;
            $view->trigger(View::EVENT_BEFORE_RENDER);
            
            $nonce = $view->params['cspNonce'] ?? null;
            $nonceAttr = $view->params['cspNonceAttr'] ?? null;
            
            if ($nonce === null || $nonceAttr === null) {
                $failures[] = [
                    'iteration' => $i,
                    'reason' => 'Nonce params not set'
                ];
                $this->destroyApplication();
                continue;
            }
            
            // Simulate using nonce in inline script tag
            $scriptTag = "<script {$nonceAttr}>console.log('test');</script>";
            
            // Verify the tag contains the nonce
            if (!preg_match('/nonce="[A-Za-z0-9+\/=]+"/', $scriptTag)) {
                $failures[] = [
                    'iteration' => $i,
                    'reason' => 'Generated script tag missing valid nonce attribute',
                    'tag' => $scriptTag
                ];
            }
            
            // Simulate using nonce in inline style tag
            $styleTag = "<style {$nonceAttr}>.test { color: red; }</style>";
            
            // Verify the tag contains the nonce
            if (!preg_match('/nonce="[A-Za-z0-9+\/=]+"/', $styleTag)) {
                $failures[] = [
                    'iteration' => $i,
                    'reason' => 'Generated style tag missing valid nonce attribute',
                    'tag' => $styleTag
                ];
            }
            
            $this->destroyApplication();
        }
        
        if (!empty($failures)) {
            $failureMessage = "Found issues using nonce in inline tags:\n";
            foreach (array_slice($failures, 0, 5) as $failure) {
                $failureMessage .= sprintf(
                    "Iteration %d: %s\n",
                    $failure['iteration'],
                    $failure['reason']
                );
            }
            $failureMessage .= sprintf("\nTotal failures: %d out of %d iterations", count($failures), $iterations);
            $this->fail($failureMessage);
        }
        
        $this->assertTrue(true, "Nonce usable in inline tags across $iterations iterations");
    }
    
    /**
     * Helper method to create a mock Yii application with CspNonceManager
     */
    protected function mockApplication()
    {
        new \yii\web\Application([
            'id' => 'test-app',
            'basePath' => dirname(dirname(dirname(__DIR__))),
            'components' => [
                'request' => [
                    'cookieValidationKey' => 'test-key',
                    'scriptFile' => __DIR__ . '/index.php',
                    'scriptUrl' => '/index.php',
                ],
                'cspNonce' => [
                    'class' => 'app\components\CspNonceManager',
                ],
                'view' => [
                    'class' => 'yii\web\View',
                    'on beforeRender' => function ($event) {
                        if (Yii::$app->has('cspNonce')) {
                            $cspNonce = Yii::$app->get('cspNonce');
                            $event->sender->params['cspNonce'] = $cspNonce->getNonce();
                            $event->sender->params['cspNonceAttr'] = $cspNonce->getNonceAttribute();
                        }
                    },
                ],
            ],
        ]);
    }
    
    /**
     * Helper method to destroy the mock application
     */
    protected function destroyApplication()
    {
        Yii::$app = null;
    }
}
