<?php

namespace tests\unit\nginx;

use Codeception\Test\Unit;

/**
 * Property Test: Static files served without PHP processing
 * 
 * Feature: yiimp2-nginx-migration, Property 1: Static files served without PHP processing
 * Validates: Requirements 1.3
 * 
 * This test verifies that static files (CSS, JS, images) are served directly by Nginx
 * with appropriate caching headers and MIME types, without invoking PHP-FPM.
 */
class StaticFileServingPropertyTest extends Unit
{
    protected $tester;
    
    /**
     * Property: For any static file request (CSS, JS, images), Nginx should serve
     * the file directly with appropriate caching headers without invoking PHP-FPM
     */
    public function testStaticFilesServedWithoutPhpProcessing()
    {
        // Check if we're running in a container environment
        if (!$this->isContainerEnvironment()) {
            $this->markTestSkipped('This test requires a container environment');
        }
        
        $iterations = 100;
        $failures = [];
        
        // Detect environment by checking actual Nginx configuration
        // In development/test, the config file will have "no-cache" at server level
        $nginxConfig = file_exists('/etc/nginx/sites-enabled/default') ? 
            file_get_contents('/etc/nginx/sites-enabled/default') : '';
        $isNonProduction = (strpos($nginxConfig, 'Disable caching in development') !== false ||
                           strpos($nginxConfig, 'no-cache, must-revalidate, proxy-revalidate') !== false);
        
        // Define static file types to test
        $staticFileTypes = [
            'css' => ['extension' => 'css', 'mimeType' => 'text/css', 'content' => 'body { color: red; }'],
            'js' => ['extension' => 'js', 'mimeType' => 'application/javascript', 'content' => 'console.log("test");'],
            'png' => ['extension' => 'png', 'mimeType' => 'image/png', 'content' => base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==')],
            'jpg' => ['extension' => 'jpg', 'mimeType' => 'image/jpeg', 'content' => base64_decode('/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/2wBDAQkJCQwLDBgNDRgyIRwhMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjL/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCwAA8A/9k=')],
            'gif' => ['extension' => 'gif', 'mimeType' => 'image/gif', 'content' => base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7')],
            'svg' => ['extension' => 'svg', 'mimeType' => 'image/svg+xml', 'content' => '<svg xmlns="http://www.w3.org/2000/svg"><rect width="1" height="1"/></svg>'],
            'woff' => ['extension' => 'woff', 'mimeType' => 'font/woff', 'content' => 'WOFF_FONT_DATA'],
            'woff2' => ['extension' => 'woff2', 'mimeType' => 'font/woff2', 'content' => 'WOFF2_FONT_DATA'],
            'ttf' => ['extension' => 'ttf', 'mimeType' => 'font/ttf', 'content' => 'TTF_FONT_DATA'],
            'ico' => ['extension' => 'ico', 'mimeType' => 'image/x-icon', 'content' => 'ICO_DATA'],
        ];
        
        for ($i = 0; $i < $iterations; $i++) {
            // Randomly select a file type
            $fileTypeKey = array_rand($staticFileTypes);
            $fileType = $staticFileTypes[$fileTypeKey];
            
            // Generate random filename
            $filename = 'test-static-' . uniqid() . '-' . $i . '.' . $fileType['extension'];
            $filepath = '/var/yiimp2/web/' . $filename;
            
            // Create the test file
            file_put_contents($filepath, $fileType['content']);
            
            // Request the file through Nginx
            $url = 'http://localhost/' . $filename;
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            curl_close($ch);
            
            // Parse headers
            list($headers, $body) = $this->parseHttpResponse($response);
            
            // Verify HTTP 200 OK
            if ($httpCode !== 200) {
                $failures[] = [
                    'iteration' => $i,
                    'filename' => $filename,
                    'fileType' => $fileTypeKey,
                    'reason' => "Expected HTTP 200, got $httpCode"
                ];
            }
            
            // Verify MIME type (allow variations like text/css; charset=utf-8)
            // Note: Font files may be served as application/octet-stream, which is acceptable
            $isFontFile = in_array($fileTypeKey, ['woff', 'woff2', 'ttf', 'eot', 'otf'], true);
            $isAcceptableMimeType = $this->mimeTypeMatches($contentType, $fileType['mimeType']) ||
                                   ($isFontFile && strpos($contentType, 'application/octet-stream') !== false);
            
            if (!$isAcceptableMimeType) {
                $failures[] = [
                    'iteration' => $i,
                    'filename' => $filename,
                    'fileType' => $fileTypeKey,
                    'reason' => "Expected MIME type '{$fileType['mimeType']}', got '$contentType'"
                ];
            }
            
            // Verify caching headers are present and appropriate for environment
            $hasCacheControl = false;
            
            foreach ($headers as $header) {
                if (stripos($header, 'Cache-Control:') === 0) {
                    $hasCacheControl = true;
                    
                    // In development/test, expect no-cache directives
                    // In production, expect public/max-age directives
                    if ($isNonProduction) {
                        // Development/test should have no-cache
                        if (stripos($header, 'no-cache') === false && stripos($header, 'no-store') === false) {
                            $failures[] = [
                                'iteration' => $i,
                                'filename' => $filename,
                                'fileType' => $fileTypeKey,
                                'reason' => "Non-production mode: Expected no-cache directives, got: $header"
                            ];
                        }
                    } else {
                        // Production should have caching enabled
                        if (stripos($header, 'public') === false && stripos($header, 'max-age') === false) {
                            $failures[] = [
                                'iteration' => $i,
                                'filename' => $filename,
                                'fileType' => $fileTypeKey,
                                'reason' => "Production mode: Expected caching directives (public/max-age), got: $header"
                            ];
                        }
                    }
                }
            }
            
            if (!$hasCacheControl) {
                $failures[] = [
                    'iteration' => $i,
                    'filename' => $filename,
                    'fileType' => $fileTypeKey,
                    'reason' => 'Missing Cache-Control header'
                ];
            }
            
            // Verify content matches (for text-based files)
            if (in_array($fileTypeKey, ['css', 'js', 'svg'])) {
                if (trim($body) !== trim($fileType['content'])) {
                    $failures[] = [
                        'iteration' => $i,
                        'filename' => $filename,
                        'fileType' => $fileTypeKey,
                        'reason' => 'Content mismatch - file may have been processed by PHP'
                    ];
                }
            }
            
            // Verify no PHP processing occurred by checking for PHP-specific headers
            foreach ($headers as $header) {
                if (stripos($header, 'X-Powered-By: PHP') === 0) {
                    $failures[] = [
                        'iteration' => $i,
                        'filename' => $filename,
                        'fileType' => $fileTypeKey,
                        'reason' => 'X-Powered-By: PHP header present - file was processed by PHP-FPM'
                    ];
                }
            }
            
            // Clean up test file
            @unlink($filepath);
        }
        
        if (!empty($failures)) {
            $failureMessage = "Static file serving failures:\n";
            foreach (array_slice($failures, 0, 10) as $failure) {
                $failureMessage .= sprintf(
                    "Iteration %d (%s): %s - %s\n",
                    $failure['iteration'],
                    $failure['fileType'],
                    $failure['filename'],
                    $failure['reason']
                );
            }
            $failureMessage .= sprintf("\nTotal failures: %d out of %d iterations", count($failures), $iterations);
            $this->fail($failureMessage);
        }
        
        $this->assertTrue(true, "All static files served correctly across $iterations iterations");
    }
    
    /**
     * Property: For any static file, the Cache-Control header should include
     * appropriate caching directives based on the environment
     */
    public function testStaticFilesHaveCachingHeaders()
    {
        // Check if we're running in a container environment
        if (!$this->isContainerEnvironment()) {
            $this->markTestSkipped('This test requires a container environment');
        }
        
        $iterations = 50;
        $failures = [];
        
        // Detect environment by checking actual Nginx configuration
        // In development/test, the config file will have "no-cache" at server level
        $nginxConfig = file_exists('/etc/nginx/sites-enabled/default') ? 
            file_get_contents('/etc/nginx/sites-enabled/default') : '';
        $isNonProduction = (strpos($nginxConfig, 'Disable caching in development') !== false ||
                           strpos($nginxConfig, 'no-cache, must-revalidate, proxy-revalidate') !== false);
        
        for ($i = 0; $i < $iterations; $i++) {
            // Create a random CSS file
            $filename = 'test-cache-' . uniqid() . '.css';
            $filepath = '/var/yiimp2/web/' . $filename;
            file_put_contents($filepath, 'body { margin: 0; }');
            
            // Request the file
            $url = 'http://localhost/' . $filename;
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            
            $response = curl_exec($ch);
            curl_close($ch);
            
            list($headers, $body) = $this->parseHttpResponse($response);
            
            // Check for Cache-Control header with environment-appropriate values
            $cacheControlFound = false;
            $hasAppropriateDirective = false;
            
            foreach ($headers as $header) {
                if (stripos($header, 'Cache-Control:') === 0) {
                    $cacheControlFound = true;
                    
                    if ($isNonProduction) {
                        // Development/test should have no-cache directives
                        if (stripos($header, 'no-cache') !== false || stripos($header, 'no-store') !== false) {
                            $hasAppropriateDirective = true;
                        }
                    } else {
                        // Production should have public directive
                        if (stripos($header, 'public') !== false) {
                            $hasAppropriateDirective = true;
                        }
                    }
                }
            }
            
            if (!$cacheControlFound) {
                $failures[] = [
                    'iteration' => $i,
                    'filename' => $filename,
                    'reason' => 'Cache-Control header not found'
                ];
            } elseif (!$hasAppropriateDirective) {
                $expectedDirective = $isNonProduction ? 'no-cache/no-store' : 'public';
                $failures[] = [
                    'iteration' => $i,
                    'filename' => $filename,
                    'reason' => "Cache-Control header missing expected '$expectedDirective' directive for " . 
                               ($isNonProduction ? 'non-production' : 'production') . ' environment'
                ];
            }
            
            // Clean up
            @unlink($filepath);
        }
        
        if (!empty($failures)) {
            $failureMessage = "Caching header failures:\n";
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
        
        $envMode = $isNonProduction ? 'non-production (dev/test)' : 'production';
        $this->assertTrue(true, "All static files have appropriate caching headers for $envMode mode across $iterations iterations");
    }
    
    /**
     * Property: For any static file type configured in Nginx, the correct MIME type
     * should be returned
     */
    public function testStaticFilesHaveCorrectMimeTypes()
    {
        // Check if we're running in a container environment
        if (!$this->isContainerEnvironment()) {
            $this->markTestSkipped('This test requires a container environment');
        }
        
        $iterations = 50;
        $failures = [];
        
        // Test various file types
        $fileTypes = [
            'css' => ['content' => '.test { color: blue; }', 'expectedMime' => 'text/css'],
            'js' => ['content' => 'var x = 1;', 'expectedMime' => 'application/javascript'],
            'png' => ['content' => base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='), 'expectedMime' => 'image/png'],
            'svg' => ['content' => '<svg></svg>', 'expectedMime' => 'image/svg+xml'],
        ];
        
        for ($i = 0; $i < $iterations; $i++) {
            // Randomly select a file type
            $extension = array_rand($fileTypes);
            $fileType = $fileTypes[$extension];
            
            $filename = 'test-mime-' . uniqid() . '.' . $extension;
            $filepath = '/var/yiimp2/web/' . $filename;
            file_put_contents($filepath, $fileType['content']);
            
            // Request the file
            $url = 'http://localhost/' . $filename;
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            
            curl_exec($ch);
            $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            curl_close($ch);
            
            // Verify MIME type
            if (!$this->mimeTypeMatches($contentType, $fileType['expectedMime'])) {
                $failures[] = [
                    'iteration' => $i,
                    'filename' => $filename,
                    'extension' => $extension,
                    'expected' => $fileType['expectedMime'],
                    'actual' => $contentType
                ];
            }
            
            // Clean up
            @unlink($filepath);
        }
        
        if (!empty($failures)) {
            $failureMessage = "MIME type failures:\n";
            foreach (array_slice($failures, 0, 5) as $failure) {
                $failureMessage .= sprintf(
                    "Iteration %d (%s): Expected '%s', got '%s'\n",
                    $failure['iteration'],
                    $failure['extension'],
                    $failure['expected'],
                    $failure['actual']
                );
            }
            $failureMessage .= sprintf("\nTotal failures: %d out of %d iterations", count($failures), $iterations);
            $this->fail($failureMessage);
        }
        
        $this->assertTrue(true, "All static files have correct MIME types across $iterations iterations");
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
     * Helper: Check if MIME type matches (allowing for charset and other parameters)
     */
    private function mimeTypeMatches(string $actual, string $expected): bool
    {
        // Remove charset and other parameters
        $actual = trim(explode(';', $actual)[0]);
        $expected = trim(explode(';', $expected)[0]);
        
        return strcasecmp($actual, $expected) === 0;
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
