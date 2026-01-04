<?php

namespace tests\unit\nginx;

use Codeception\Test\Unit;

/**
 * Property Test: Non-static requests routed to PHP-FPM
 * 
 * Feature: yiimp2-nginx-migration, Property 7: Non-static requests routed to PHP-FPM
 * Validates: Requirements 4.2
 * 
 * This test verifies that non-static requests (PHP endpoints, non-existent paths)
 * are correctly routed to PHP-FPM for processing by Nginx.
 */
class PhpRoutingPropertyTest extends Unit
{
    protected $tester;
    
    /**
     * Property: For any request to a PHP endpoint or non-existent path, Nginx should
     * route the request to PHP-FPM for processing
     */
    public function testNonStaticRequestsRoutedToPhpFpm()
    {
        // Check if we're running in a container environment
        if (!$this->isContainerEnvironment()) {
            $this->markTestSkipped('This test requires a container environment');
        }
        
        $iterations = 100;
        $failures = [];
        
        // Define test scenarios for non-static requests
        $testScenarios = [
            // PHP files that should be processed
            'php_file' => [
                'type' => 'php',
                'createFile' => true,
                'content' => '<?php echo "PHP_PROCESSED"; ?>',
                'expectedResponse' => 'PHP_PROCESSED',
                'expectedStatus' => 200
            ],
            // Non-existent paths that should be routed to index.php (Yii2 routing)
            'non_existent_path' => [
                'type' => 'route',
                'createFile' => false,
                'path' => '/site/index',
                'expectedStatus' => [200, 302, 404], // Could be valid route, redirect, or 404
                'checkPhpProcessing' => true
            ],
            // API endpoints
            'api_endpoint' => [
                'type' => 'route',
                'createFile' => false,
                'path' => '/api/status',
                'expectedStatus' => [200, 404, 500], // 500 indicates PHP processed but had error
                'checkPhpProcessing' => true
            ],
            // Admin routes
            'admin_route' => [
                'type' => 'route',
                'createFile' => false,
                'path' => '/admin/coins',
                'expectedStatus' => [200, 302, 403, 404], // Could redirect to login or be forbidden
                'checkPhpProcessing' => true
            ]
        ];
        
        for ($i = 0; $i < $iterations; $i++) {
            // Randomly select a test scenario
            $scenarioKey = array_rand($testScenarios);
            $scenario = $testScenarios[$scenarioKey];
            
            $url = '';
            $filepath = '';
            
            if ($scenario['createFile']) {
                // Create a test PHP file
                $filename = 'test-php-routing-' . uniqid() . '-' . $i . '.php';
                $filepath = '/var/yiimp2/web/' . $filename;
                file_put_contents($filepath, $scenario['content']);
                $url = 'http://localhost/' . $filename;
            } else {
                // Use a predefined path
                $url = 'http://localhost' . $scenario['path'];
            }
            
            // Make request through Nginx
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false); // Don't follow redirects
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            // Parse response
            list($headers, $body) = $this->parseHttpResponse($response);
            
            // Verify HTTP status code is acceptable
            $expectedStatuses = is_array($scenario['expectedStatus']) ? 
                $scenario['expectedStatus'] : [$scenario['expectedStatus']];
            
            if (!in_array($httpCode, $expectedStatuses, true)) {
                $failures[] = [
                    'iteration' => $i,
                    'scenario' => $scenarioKey,
                    'url' => $url,
                    'reason' => sprintf(
                        'Expected HTTP status %s, got %d',
                        implode(' or ', $expectedStatuses),
                        $httpCode
                    )
                ];
            }
            
            // For PHP files, verify content was processed
            if ($scenario['createFile'] && isset($scenario['expectedResponse'])) {
                if (trim($body) !== $scenario['expectedResponse']) {
                    $failures[] = [
                        'iteration' => $i,
                        'scenario' => $scenarioKey,
                        'url' => $url,
                        'reason' => sprintf(
                            'Expected response "%s", got "%s" - PHP may not have processed the file',
                            $scenario['expectedResponse'],
                            substr(trim($body), 0, 50)
                        )
                    ];
                }
            }
            
            // Verify PHP processing occurred by checking for PHP-related indicators
            if (isset($scenario['checkPhpProcessing']) && $scenario['checkPhpProcessing']) {
                $phpProcessed = false;
                
                // Check for PHP-specific headers or content
                foreach ($headers as $header) {
                    // X-Powered-By header indicates PHP processing
                    if (stripos($header, 'X-Powered-By: PHP') === 0) {
                        $phpProcessed = true;
                        break;
                    }
                    // Yii2 debug toolbar or other Yii-specific headers
                    if (stripos($header, 'X-Debug-') === 0) {
                        $phpProcessed = true;
                        break;
                    }
                }
                
                // Check for Yii2-specific content in response
                if (!$phpProcessed && $httpCode === 200) {
                    // Look for Yii2 indicators in the response body
                    if (stripos($body, 'yii\\') !== false || 
                        stripos($body, 'csrf') !== false ||
                        stripos($body, 'data-method') !== false) {
                        $phpProcessed = true;
                    }
                }
                
                // For 404 responses, check if it's a Yii2 404 (processed by PHP)
                // vs Nginx 404 (not processed by PHP)
                if ($httpCode === 404) {
                    // Yii2 404 pages typically have HTML content
                    if (strlen($body) > 100 && stripos($body, '<html') !== false) {
                        $phpProcessed = true;
                    }
                }
                
                // For redirects, PHP processing is assumed if we got a redirect
                if ($httpCode >= 300 && $httpCode < 400) {
                    $phpProcessed = true;
                }
                
                if (!$phpProcessed && $httpCode === 200) {
                    $failures[] = [
                        'iteration' => $i,
                        'scenario' => $scenarioKey,
                        'url' => $url,
                        'reason' => 'No evidence of PHP processing - request may have been served statically'
                    ];
                }
            }
            
            // Verify request was NOT served as a static file
            // Static files should have specific caching headers
            $servedAsStatic = false;
            foreach ($headers as $header) {
                // Check for static file caching headers
                if (stripos($header, 'Cache-Control:') === 0) {
                    // Production static files have "public, immutable" or similar
                    if (stripos($header, 'immutable') !== false) {
                        $servedAsStatic = true;
                        break;
                    }
                }
            }
            
            if ($servedAsStatic) {
                $failures[] = [
                    'iteration' => $i,
                    'scenario' => $scenarioKey,
                    'url' => $url,
                    'reason' => 'Request was served as static file instead of being routed to PHP-FPM'
                ];
            }
            
            // Clean up test file if created
            if ($filepath && file_exists($filepath)) {
                @unlink($filepath);
            }
        }
        
        if (!empty($failures)) {
            $failureMessage = "PHP routing failures:\n";
            foreach (array_slice($failures, 0, 10) as $failure) {
                $failureMessage .= sprintf(
                    "Iteration %d (%s): %s - %s\n",
                    $failure['iteration'],
                    $failure['scenario'],
                    $failure['url'],
                    $failure['reason']
                );
            }
            $failureMessage .= sprintf("\nTotal failures: %d out of %d iterations", count($failures), $iterations);
            $this->fail($failureMessage);
        }
        
        $this->assertTrue(true, "All non-static requests correctly routed to PHP-FPM across $iterations iterations");
    }
    
    /**
     * Property: For any PHP file request, the response should show evidence of
     * PHP processing (not served as static content)
     */
    public function testPhpFilesProcessedByPhpFpm()
    {
        // Check if we're running in a container environment
        if (!$this->isContainerEnvironment()) {
            $this->markTestSkipped('This test requires a container environment');
        }
        
        $iterations = 50;
        $failures = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            // Create a PHP file with dynamic content
            $randomValue = uniqid('test-', true);
            $filename = 'test-php-processing-' . $i . '.php';
            $filepath = '/var/yiimp2/web/' . $filename;
            $content = sprintf('<?php echo "%s"; ?>', $randomValue);
            file_put_contents($filepath, $content);
            
            // Request the file
            $url = 'http://localhost/' . $filename;
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            list($headers, $body) = $this->parseHttpResponse($response);
            
            // Verify HTTP 200 OK
            if ($httpCode !== 200) {
                $failures[] = [
                    'iteration' => $i,
                    'filename' => $filename,
                    'reason' => "Expected HTTP 200, got $httpCode"
                ];
            }
            
            // Verify PHP processed the file (output matches expected)
            if (trim($body) !== $randomValue) {
                $failures[] = [
                    'iteration' => $i,
                    'filename' => $filename,
                    'reason' => sprintf(
                        'PHP did not process file correctly. Expected "%s", got "%s"',
                        $randomValue,
                        substr(trim($body), 0, 50)
                    )
                ];
            }
            
            // Verify PHP code is not visible in response (would indicate static serving)
            if (strpos($body, '<?php') !== false) {
                $failures[] = [
                    'iteration' => $i,
                    'filename' => $filename,
                    'reason' => 'PHP code visible in response - file was served statically instead of processed'
                ];
            }
            
            // Clean up
            @unlink($filepath);
        }
        
        if (!empty($failures)) {
            $failureMessage = "PHP processing failures:\n";
            foreach (array_slice($failures, 0, 5) as $failure) {
                $failureMessage .= sprintf(
                    "Iteration %d: %s - %s\n",
                    $failure['iteration'],
                    $failure['filename'],
                    $failure['reason']
                );
            }
            $failureMessage .= sprintf("\nTotal failures: %d out of %d iterations", count($failures), $iterations);
            $this->fail($failureMessage);
        }
        
        $this->assertTrue(true, "All PHP files correctly processed by PHP-FPM across $iterations iterations");
    }
    
    /**
     * Property: For any non-existent path, Nginx should route to index.php
     * (Yii2 front controller) for application routing
     */
    public function testNonExistentPathsRoutedToFrontController()
    {
        // Check if we're running in a container environment
        if (!$this->isContainerEnvironment()) {
            $this->markTestSkipped('This test requires a container environment');
        }
        
        $iterations = 50;
        $failures = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            // Generate a random non-existent path
            $randomPath = '/test-route-' . uniqid() . '-' . $i;
            $url = 'http://localhost' . $randomPath;
            
            // Request the non-existent path
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            list($headers, $body) = $this->parseHttpResponse($response);
            
            // Non-existent paths should be handled by Yii2 (typically 404 or redirect)
            // They should NOT be Nginx 404s
            $isYii2Response = false;
            
            // Check for Yii2 indicators
            if ($httpCode === 404) {
                // Yii2 404 pages have HTML content
                if (strlen($body) > 100 && stripos($body, '<html') !== false) {
                    $isYii2Response = true;
                }
                // Check for Yii2-specific content
                if (stripos($body, 'yii\\') !== false || stripos($body, 'Not Found') !== false) {
                    $isYii2Response = true;
                }
            } elseif ($httpCode >= 200 && $httpCode < 400) {
                // Any 2xx or 3xx response indicates PHP processing
                $isYii2Response = true;
            }
            
            // Check for PHP processing headers
            foreach ($headers as $header) {
                if (stripos($header, 'X-Powered-By: PHP') === 0) {
                    $isYii2Response = true;
                    break;
                }
            }
            
            if (!$isYii2Response) {
                $failures[] = [
                    'iteration' => $i,
                    'path' => $randomPath,
                    'httpCode' => $httpCode,
                    'reason' => 'Non-existent path not routed to Yii2 front controller (index.php)'
                ];
            }
            
            // Verify it's not a static Nginx 404
            $isNginx404 = false;
            if ($httpCode === 404 && strlen($body) < 200) {
                // Short 404 responses are typically Nginx defaults
                if (stripos($body, 'nginx') !== false || stripos($body, '404 Not Found') !== false) {
                    $isNginx404 = true;
                }
            }
            
            if ($isNginx404) {
                $failures[] = [
                    'iteration' => $i,
                    'path' => $randomPath,
                    'reason' => 'Received Nginx 404 instead of Yii2 404 - routing not working'
                ];
            }
        }
        
        if (!empty($failures)) {
            $failureMessage = "Front controller routing failures:\n";
            foreach (array_slice($failures, 0, 5) as $failure) {
                $failureMessage .= sprintf(
                    "Iteration %d: %s (HTTP %d) - %s\n",
                    $failure['iteration'],
                    $failure['path'],
                    $failure['httpCode'] ?? 0,
                    $failure['reason']
                );
            }
            $failureMessage .= sprintf("\nTotal failures: %d out of %d iterations", count($failures), $iterations);
            $this->fail($failureMessage);
        }
        
        $this->assertTrue(true, "All non-existent paths correctly routed to front controller across $iterations iterations");
    }
    
    /**
     * Helper: Parse HTTP response into headers and body
     */
    private function parseHttpResponse(string $response): array
    {
        $parts = explode("\r\n\r\n", $response, 2);
        $headerLines = explode("\r\n", $parts[0]);
        $body = $parts[1] ?? '';
        
        return [$headerLines, $body];
    }
    
    /**
     * Check if we're running in a container environment
     */
    private function isContainerEnvironment(): bool
    {
        return file_exists('/.dockerenv') || 
               (file_exists('/proc/1/cgroup') && 
                strpos(file_get_contents('/proc/1/cgroup'), 'docker') !== false);
    }
}
