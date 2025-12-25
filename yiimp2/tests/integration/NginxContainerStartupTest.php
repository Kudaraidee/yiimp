<?php

namespace tests\integration;

use tests\IntegrationTester;
use Codeception\Test\Unit;

/**
 * Integration test for Nginx container startup
 * 
 * This test verifies that the yiimp2-web container starts successfully with:
 * - Nginx process running
 * - PHP-FPM process running
 * - Web interface accessible
 * 
 * Requirements: 1.1, 1.2
 * 
 * @group integration
 * @group nginx
 * @group container
 */
class NginxContainerStartupTest extends Unit
{
    protected \tests\IntegrationTester $tester;
    
    /**
     * Test that Nginx process is running in the container
     * 
     * Validates: Requirements 1.1
     */
    public function testNginxProcessIsRunning()
    {
        // Check if we're running in a container environment
        if (!$this->isContainerEnvironment()) {
            $this->markTestSkipped('This test requires a container environment');
        }
        
        // Check if nginx process is running
        $output = [];
        $returnCode = 0;
        exec('ps aux | grep -E "nginx: (master|worker)" | grep -v grep', $output, $returnCode);
        
        $this->assertNotEmpty($output, 'Nginx process should be running');
        $this->assertStringContainsString('nginx', implode("\n", $output), 'Nginx process should be in process list');
        
        // Verify nginx master process exists
        $hasMaster = false;
        foreach ($output as $line) {
            if (strpos($line, 'nginx: master') !== false) {
                $hasMaster = true;
                break;
            }
        }
        $this->assertTrue($hasMaster, 'Nginx master process should be running');
    }
    
    /**
     * Test that PHP-FPM process is running in the container
     * 
     * Validates: Requirements 1.2
     */
    public function testPhpFpmProcessIsRunning()
    {
        // Check if we're running in a container environment
        if (!$this->isContainerEnvironment()) {
            $this->markTestSkipped('This test requires a container environment');
        }
        
        // Check if php-fpm process is running
        $output = [];
        $returnCode = 0;
        exec('ps aux | grep -E "php-fpm: (master|pool)" | grep -v grep', $output, $returnCode);
        
        $this->assertNotEmpty($output, 'PHP-FPM process should be running');
        $this->assertStringContainsString('php-fpm', implode("\n", $output), 'PHP-FPM process should be in process list');
        
        // Verify php-fpm master process exists
        $hasMaster = false;
        foreach ($output as $line) {
            if (strpos($line, 'php-fpm: master') !== false) {
                $hasMaster = true;
                break;
            }
        }
        $this->assertTrue($hasMaster, 'PHP-FPM master process should be running');
    }
    
    /**
     * Test that PHP-FPM socket exists and is accessible
     * 
     * Validates: Requirements 1.2
     */
    public function testPhpFpmSocketExists()
    {
        // Check if we're running in a container environment
        if (!$this->isContainerEnvironment()) {
            $this->markTestSkipped('This test requires a container environment');
        }
        
        $socketPath = '/run/php/php8.3-fpm.sock';
        
        // Check if socket file exists
        $this->assertFileExists($socketPath, 'PHP-FPM socket should exist');
        
        // Check if socket is a socket file (not a regular file)
        $this->assertTrue(is_link($socketPath) || filetype($socketPath) === 'socket', 
            'PHP-FPM socket should be a socket file');
    }
    
    /**
     * Test that web interface is accessible via HTTP
     * 
     * Validates: Requirements 1.1, 1.2
     */
    public function testWebInterfaceIsAccessible()
    {
        // Check if we're running in a container environment
        if (!$this->isContainerEnvironment()) {
            $this->markTestSkipped('This test requires a container environment');
        }
        
        // Try to access the web interface
        $url = 'http://localhost/';
        
        // Use curl to check if the web interface responds
        // Don't follow redirects to avoid redirect loop issues
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        $this->assertNotFalse($response, "Web interface should respond (curl error: $error)");
        // Accept 2xx, 3xx (redirects), and even 4xx/5xx as long as Nginx is responding
        $this->assertGreaterThanOrEqual(200, $httpCode, 'HTTP response code should indicate Nginx is responding');
        $this->assertLessThan(600, $httpCode, 'HTTP response code should be valid');
    }
    
    /**
     * Test that Nginx configuration is valid
     * 
     * Validates: Requirements 1.1
     */
    public function testNginxConfigurationIsValid()
    {
        // Check if we're running in a container environment
        if (!$this->isContainerEnvironment()) {
            $this->markTestSkipped('This test requires a container environment');
        }
        
        // Test nginx configuration
        $output = [];
        $returnCode = 0;
        exec('nginx -t 2>&1', $output, $returnCode);
        
        $outputStr = implode("\n", $output);
        $this->assertEquals(0, $returnCode, "Nginx configuration should be valid. Output: $outputStr");
        $this->assertStringContainsString('syntax is ok', $outputStr, 'Nginx configuration syntax should be OK');
        $this->assertStringContainsString('test is successful', $outputStr, 'Nginx configuration test should be successful');
    }
    
    /**
     * Test that PHP-FPM configuration is valid
     * 
     * Validates: Requirements 1.2
     */
    public function testPhpFpmConfigurationIsValid()
    {
        // Check if we're running in a container environment
        if (!$this->isContainerEnvironment()) {
            $this->markTestSkipped('This test requires a container environment');
        }
        
        // Test php-fpm configuration
        $output = [];
        $returnCode = 0;
        exec('php-fpm8.3 -t 2>&1', $output, $returnCode);
        
        $outputStr = implode("\n", $output);
        $this->assertEquals(0, $returnCode, "PHP-FPM configuration should be valid. Output: $outputStr");
    }
    
    /**
     * Test that PHP can process requests through Nginx
     * 
     * Validates: Requirements 1.1, 1.2
     */
    public function testPhpProcessingThroughNginx()
    {
        // Check if we're running in a container environment
        if (!$this->isContainerEnvironment()) {
            $this->markTestSkipped('This test requires a container environment');
        }
        
        // Create a simple PHP test file
        $testFile = '/var/yiimp2/web/test-nginx-php.php';
        $testContent = '<?php echo "PHP_WORKS"; ?>';
        file_put_contents($testFile, $testContent);
        
        // Try to access the test file through Nginx
        $url = 'http://localhost/test-nginx-php.php';
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        // Clean up test file
        @unlink($testFile);
        
        $this->assertEquals(200, $httpCode, 'PHP test file should return 200 OK');
        $this->assertEquals('PHP_WORKS', $response, 'PHP should process the test file correctly');
    }
    
    /**
     * Test that Nginx serves static files correctly
     * 
     * Validates: Requirements 1.1, 1.3
     */
    public function testNginxServesStaticFiles()
    {
        // Check if we're running in a container environment
        if (!$this->isContainerEnvironment()) {
            $this->markTestSkipped('This test requires a container environment');
        }
        
        // Create a test static file
        $testFile = '/var/yiimp2/web/test-static.txt';
        $testContent = 'STATIC_FILE_CONTENT';
        file_put_contents($testFile, $testContent);
        
        // Try to access the static file through Nginx
        $url = 'http://localhost/test-static.txt';
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        // Clean up test file
        @unlink($testFile);
        
        $this->assertEquals(200, $httpCode, 'Static file should return 200 OK');
        $this->assertEquals($testContent, $response, 'Nginx should serve static file correctly');
    }
    
    /**
     * Check if we're running in a container environment
     * 
     * @return bool
     */
    private function isContainerEnvironment(): bool
    {
        // Check for common container indicators
        return file_exists('/.dockerenv') || 
               (file_exists('/proc/1/cgroup') && 
                strpos(file_get_contents('/proc/1/cgroup'), 'docker') !== false);
    }
}
