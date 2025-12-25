<?php

namespace tests\unit\security;

use Codeception\Test\Unit;
use app\components\CspNonceManager;

/**
 * Property Test: CSP header presence and nonce consistency
 * 
 * Feature: yiimp2-nginx-migration, Property 5: CSP header presence and nonce consistency
 * Validates: Requirements 2.3, 3.3, 3.5
 * 
 * This test verifies that:
 * 1. CSP headers are present in all responses
 * 2. The nonce in the CSP header matches the nonce used in page content
 * 3. The CSP header format is correct and includes all required directives
 */
class CspHeaderConsistencyPropertyTest extends Unit
{
    protected $tester;
    
    /**
     * Property: For any HTTP response, the Content-Security-Policy header should
     * be present and contain a nonce value
     */
    public function testCspHeaderPresence()
    {
        $iterations = 100;
        $failures = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            $manager = new CspNonceManager();
            
            // Clear HTTP_X_CSP_NONCE to force generation
            if (isset($_SERVER['HTTP_X_CSP_NONCE'])) {
                unset($_SERVER['HTTP_X_CSP_NONCE']);
            }
            
            // Get CSP header
            $isDev = ($i % 2 === 0); // Alternate between dev and prod
            $cspHeader = $manager->getCspHeader($isDev);
            
            // Verify header is not empty
            if (empty($cspHeader)) {
                $failures[] = [
                    'iteration' => $i,
                    'reason' => 'CSP header is empty',
                    'isDev' => $isDev
                ];
                continue;
            }
            
            // Verify header contains nonce directive
            if (!preg_match("/'nonce-[A-Za-z0-9+\/=]+'/", $cspHeader)) {
                $failures[] = [
                    'iteration' => $i,
                    'header' => substr($cspHeader, 0, 100),
                    'reason' => 'CSP header missing nonce directive',
                    'isDev' => $isDev
                ];
            }
        }
        
        if (!empty($failures)) {
            $failureMessage = "Found CSP header issues:\n";
            foreach (array_slice($failures, 0, 5) as $failure) {
                $failureMessage .= sprintf(
                    "Iteration %d (%s): %s\n",
                    $failure['iteration'],
                    $failure['isDev'] ? 'dev' : 'prod',
                    $failure['reason']
                );
            }
            $failureMessage .= sprintf("\nTotal failures: %d out of %d iterations", count($failures), $iterations);
            $this->fail($failureMessage);
        }
        
        $this->assertTrue(true, "CSP header present with nonce across $iterations iterations");
    }
    
    /**
     * Property: For any CSP header, the nonce value in the header should match
     * the nonce value returned by getNonce()
     */
    public function testCspHeaderNonceConsistency()
    {
        $iterations = 100;
        $failures = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            $manager = new CspNonceManager();
            
            // Clear HTTP_X_CSP_NONCE to force generation
            if (isset($_SERVER['HTTP_X_CSP_NONCE'])) {
                unset($_SERVER['HTTP_X_CSP_NONCE']);
            }
            
            // Get nonce and CSP header
            $nonce = $manager->getNonce();
            $cspHeader = $manager->getCspHeader(false);
            
            // Extract nonce from CSP header
            if (preg_match("/'nonce-([A-Za-z0-9+\/=]+)'/", $cspHeader, $matches)) {
                $headerNonce = $matches[1];
                
                // Verify nonce matches
                if ($headerNonce !== $nonce) {
                    $failures[] = [
                        'iteration' => $i,
                        'expected_nonce' => $nonce,
                        'header_nonce' => $headerNonce,
                        'reason' => 'Nonce mismatch between getNonce() and CSP header'
                    ];
                }
            } else {
                $failures[] = [
                    'iteration' => $i,
                    'nonce' => $nonce,
                    'header' => substr($cspHeader, 0, 100),
                    'reason' => 'Could not extract nonce from CSP header'
                ];
            }
        }
        
        if (!empty($failures)) {
            $failureMessage = "Found nonce consistency issues:\n";
            foreach (array_slice($failures, 0, 5) as $failure) {
                $failureMessage .= sprintf(
                    "Iteration %d: %s\n",
                    $failure['iteration'],
                    $failure['reason']
                );
                if (isset($failure['expected_nonce']) && isset($failure['header_nonce'])) {
                    $failureMessage .= sprintf(
                        "  Expected: %s, Got: %s\n",
                        $failure['expected_nonce'],
                        $failure['header_nonce']
                    );
                }
            }
            $failureMessage .= sprintf("\nTotal failures: %d out of %d iterations", count($failures), $iterations);
            $this->fail($failureMessage);
        }
        
        $this->assertTrue(true, "Nonce consistent between getNonce() and CSP header across $iterations iterations");
    }
    
    /**
     * Property: For any CSP header, it should contain all required security directives
     */
    public function testCspHeaderRequiredDirectives()
    {
        $iterations = 100;
        $failures = [];
        
        $requiredDirectives = [
            'default-src',
            'script-src',
            'style-src',
            'img-src',
            'font-src',
            'connect-src',
            'frame-ancestors',
            'base-uri',
            'form-action'
        ];
        
        for ($i = 0; $i < $iterations; $i++) {
            $manager = new CspNonceManager();
            
            // Clear HTTP_X_CSP_NONCE to force generation
            if (isset($_SERVER['HTTP_X_CSP_NONCE'])) {
                unset($_SERVER['HTTP_X_CSP_NONCE']);
            }
            
            $isDev = ($i % 2 === 0);
            $cspHeader = $manager->getCspHeader($isDev);
            
            $missingDirectives = [];
            foreach ($requiredDirectives as $directive) {
                if (strpos($cspHeader, $directive) === false) {
                    $missingDirectives[] = $directive;
                }
            }
            
            if (!empty($missingDirectives)) {
                $failures[] = [
                    'iteration' => $i,
                    'isDev' => $isDev,
                    'missing_directives' => $missingDirectives,
                    'header' => substr($cspHeader, 0, 150)
                ];
            }
        }
        
        if (!empty($failures)) {
            $failureMessage = "Found CSP headers with missing directives:\n";
            foreach (array_slice($failures, 0, 5) as $failure) {
                $failureMessage .= sprintf(
                    "Iteration %d (%s): Missing %s\n",
                    $failure['iteration'],
                    $failure['isDev'] ? 'dev' : 'prod',
                    implode(', ', $failure['missing_directives'])
                );
            }
            $failureMessage .= sprintf("\nTotal failures: %d out of %d iterations", count($failures), $iterations);
            $this->fail($failureMessage);
        }
        
        $this->assertTrue(true, "All required CSP directives present across $iterations iterations");
    }
    
    /**
     * Property: For any CSP header in development mode, it should allow unsafe-eval
     * For production mode, it should NOT allow unsafe-eval
     */
    public function testCspHeaderDevVsProdDifferences()
    {
        $iterations = 100;
        $failures = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            $manager = new CspNonceManager();
            
            // Clear HTTP_X_CSP_NONCE to force generation
            if (isset($_SERVER['HTTP_X_CSP_NONCE'])) {
                unset($_SERVER['HTTP_X_CSP_NONCE']);
            }
            
            // Test development mode
            $devHeader = $manager->getCspHeader(true);
            if (strpos($devHeader, "'unsafe-eval'") === false) {
                $failures[] = [
                    'iteration' => $i,
                    'mode' => 'development',
                    'reason' => 'Development CSP should allow unsafe-eval',
                    'header' => substr($devHeader, 0, 150)
                ];
            }
            
            // Test production mode
            $prodHeader = $manager->getCspHeader(false);
            if (strpos($prodHeader, "'unsafe-eval'") !== false) {
                $failures[] = [
                    'iteration' => $i,
                    'mode' => 'production',
                    'reason' => 'Production CSP should NOT allow unsafe-eval',
                    'header' => substr($prodHeader, 0, 150)
                ];
            }
        }
        
        if (!empty($failures)) {
            $failureMessage = "Found CSP header dev/prod configuration issues:\n";
            foreach (array_slice($failures, 0, 5) as $failure) {
                $failureMessage .= sprintf(
                    "Iteration %d (%s): %s\n",
                    $failure['iteration'],
                    $failure['mode'],
                    $failure['reason']
                );
            }
            $failureMessage .= sprintf("\nTotal failures: %d out of %d iterations", count($failures), $iterations);
            $this->fail($failureMessage);
        }
        
        $this->assertTrue(true, "CSP header correctly configured for dev/prod across $iterations iterations");
    }
    
    /**
     * Property: For any nonce attribute generated, it should match the format
     * expected in HTML (nonce="value")
     */
    public function testNonceAttributeFormat()
    {
        $iterations = 100;
        $failures = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            $manager = new CspNonceManager();
            
            // Clear HTTP_X_CSP_NONCE to force generation
            if (isset($_SERVER['HTTP_X_CSP_NONCE'])) {
                unset($_SERVER['HTTP_X_CSP_NONCE']);
            }
            
            $nonce = $manager->getNonce();
            $nonceAttr = $manager->getNonceAttribute();
            
            // Verify format is nonce="value"
            $expectedAttr = 'nonce="' . $nonce . '"';
            if ($nonceAttr !== $expectedAttr) {
                $failures[] = [
                    'iteration' => $i,
                    'expected' => $expectedAttr,
                    'actual' => $nonceAttr,
                    'reason' => 'Nonce attribute format incorrect'
                ];
            }
            
            // Verify it's valid HTML attribute format
            if (!preg_match('/^nonce="[A-Za-z0-9+\/=]+"$/', $nonceAttr)) {
                $failures[] = [
                    'iteration' => $i,
                    'attribute' => $nonceAttr,
                    'reason' => 'Nonce attribute not valid HTML format'
                ];
            }
        }
        
        if (!empty($failures)) {
            $failureMessage = "Found nonce attribute format issues:\n";
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
        
        $this->assertTrue(true, "Nonce attribute format correct across $iterations iterations");
    }
}
