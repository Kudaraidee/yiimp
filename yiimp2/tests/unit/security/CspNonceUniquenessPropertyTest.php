<?php

namespace tests\unit\security;

use Codeception\Test\Unit;
use app\components\CspNonceManager;

/**
 * Property Test: Nonce uniqueness across requests
 * 
 * Feature: yiimp2-nginx-migration, Property 2: Nonce uniqueness across requests
 * Validates: Requirements 2.1, 3.4
 * 
 * This test verifies that each request receives a unique cryptographic nonce value.
 * Nonces must be unique to prevent replay attacks and ensure CSP effectiveness.
 */
class CspNonceUniquenessPropertyTest extends Unit
{
    protected $tester;
    
    /**
     * Property: For any sequence of HTTP requests, each request should receive
     * a different cryptographic nonce value
     */
    public function testNonceUniquenessAcrossRequests()
    {
        $iterations = 100;
        $nonces = [];
        $duplicates = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            // Simulate a new request by creating a new CspNonceManager instance
            $manager = new CspNonceManager();
            
            // Clear the HTTP_X_CSP_NONCE to force generation of new nonce
            if (isset($_SERVER['HTTP_X_CSP_NONCE'])) {
                unset($_SERVER['HTTP_X_CSP_NONCE']);
            }
            
            // Get nonce for this "request"
            $nonce = $manager->getNonce();
            
            // Check if we've seen this nonce before
            if (isset($nonces[$nonce])) {
                $duplicates[] = [
                    'nonce' => $nonce,
                    'first_iteration' => $nonces[$nonce],
                    'duplicate_iteration' => $i,
                ];
            } else {
                $nonces[$nonce] = $i;
            }
        }
        
        if (!empty($duplicates)) {
            $failureMessage = "Found duplicate nonces across requests:\n";
            foreach (array_slice($duplicates, 0, 5) as $dup) {
                $failureMessage .= sprintf(
                    "Nonce '%s' appeared in iteration %d and %d\n",
                    $dup['nonce'],
                    $dup['first_iteration'],
                    $dup['duplicate_iteration']
                );
            }
            $failureMessage .= sprintf("\nTotal duplicates: %d out of %d iterations", count($duplicates), $iterations);
            $this->fail($failureMessage);
        }
        
        $this->assertCount($iterations, $nonces, 
            "All $iterations nonces should be unique");
    }
    
    /**
     * Property: For any generated nonce, it should be cryptographically random
     * and have sufficient entropy (at least 128 bits)
     */
    public function testNonceCryptographicStrength()
    {
        $iterations = 100;
        $failures = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            $manager = new CspNonceManager();
            
            // Clear the HTTP_X_CSP_NONCE to force generation
            if (isset($_SERVER['HTTP_X_CSP_NONCE'])) {
                unset($_SERVER['HTTP_X_CSP_NONCE']);
            }
            
            $nonce = $manager->getNonce();
            
            // Nonce should be base64 encoded (from random_bytes)
            // Decode to check entropy
            $decoded = base64_decode($nonce, true);
            
            if ($decoded === false) {
                $failures[] = [
                    'iteration' => $i,
                    'nonce' => $nonce,
                    'reason' => 'Not valid base64'
                ];
                continue;
            }
            
            // Should be at least 16 bytes (128 bits) of entropy
            if (strlen($decoded) < 16) {
                $failures[] = [
                    'iteration' => $i,
                    'nonce' => $nonce,
                    'decoded_length' => strlen($decoded),
                    'reason' => 'Insufficient entropy (< 128 bits)'
                ];
            }
        }
        
        if (!empty($failures)) {
            $failureMessage = "Found nonces with insufficient cryptographic strength:\n";
            foreach (array_slice($failures, 0, 5) as $failure) {
                $failureMessage .= sprintf(
                    "Iteration %d: %s (nonce: %s)\n",
                    $failure['iteration'],
                    $failure['reason'],
                    substr($failure['nonce'], 0, 20) . '...'
                );
            }
            $failureMessage .= sprintf("\nTotal failures: %d out of %d iterations", count($failures), $iterations);
            $this->fail($failureMessage);
        }
        
        $this->assertTrue(true, "All nonces have sufficient cryptographic strength across $iterations iterations");
    }
    
    /**
     * Property: For any nonce generated from Nginx header, it should be used
     * consistently throughout the request
     */
    public function testNonceConsistencyWithinRequest()
    {
        $iterations = 100;
        $failures = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            // Simulate Nginx providing a nonce
            $nginxNonce = base64_encode(random_bytes(16));
            $_SERVER['HTTP_X_CSP_NONCE'] = $nginxNonce;
            
            $manager = new CspNonceManager();
            
            // Get nonce multiple times within same "request"
            $nonce1 = $manager->getNonce();
            $nonce2 = $manager->getNonce();
            $nonce3 = $manager->getNonce();
            
            // All should return the same nonce
            if ($nonce1 !== $nonce2 || $nonce1 !== $nonce3) {
                $failures[] = [
                    'iteration' => $i,
                    'nonce1' => $nonce1,
                    'nonce2' => $nonce2,
                    'nonce3' => $nonce3,
                    'reason' => 'Nonce changed within same request'
                ];
            }
            
            // Should match the Nginx-provided nonce
            if ($nonce1 !== $nginxNonce) {
                $failures[] = [
                    'iteration' => $i,
                    'nginx_nonce' => $nginxNonce,
                    'returned_nonce' => $nonce1,
                    'reason' => 'Nonce does not match Nginx-provided value'
                ];
            }
            
            // Clean up for next iteration
            unset($_SERVER['HTTP_X_CSP_NONCE']);
        }
        
        if (!empty($failures)) {
            $failureMessage = "Found nonce consistency issues:\n";
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
        
        $this->assertTrue(true, "Nonce remains consistent within request across $iterations iterations");
    }
}
