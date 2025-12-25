<?php

namespace tests\unit\nginx;

use Codeception\Test\Unit;

/**
 * Property Test: Security-sensitive paths blocked
 * 
 * Feature: yiimp2-nginx-migration, Property 8: Security-sensitive paths blocked
 * Validates: Requirements 4.4
 * 
 * This test verifies that security-sensitive paths (dotfiles, config directories,
 * runtime directories) are blocked by Nginx with appropriate HTTP status codes.
 */
class SecurityPathBlockingPropertyTest extends Unit
{
    protected $tester;
    
    /**
     * Property: For any request to security-sensitive paths (dotfiles, config directories),
     * Nginx should deny access with 403/404 status
     */
    public function testSecuritySensitivePathsBlocked()
    {
        // Check if we're running in a container environment
        if (!$this->isContainerEnvironment()) {
            $this->markTestSkipped('This test requires a container environment');
        }
        
        $iterations = 100;
        $failures = [];
        
        // Detect environment by checking Nginx configuration
        $nginxConfig = file_exists('/etc/nginx/sites-enabled/default') ? 
            file_get_contents('/etc/nginx/sites-enabled/default') : '';
        $isDevMode = (strpos($nginxConfig, 'Development') !== false ||
                     strpos($nginxConfig, 'YIIMP_DEBUG') !== false);
        
        // Define security-sensitive paths to test
        $securityPaths = [
            // Dotfiles
            'dotfile_env' => [
                'path' => '/.env',
                'shouldBlock' => true,
                'description' => 'Environment file'
            ],
            'dotfile_git' => [
                'path' => '/.git/config',
                'shouldBlock' => true,
                'description' => 'Git configuration'
            ],
            'dotfile_gitignore' => [
                'path' => '/.gitignore',
                'shouldBlock' => true,
                'description' => 'Git ignore file'
            ],
            'dotfile_htaccess' => [
                'path' => '/.htaccess',
                'shouldBlock' => true,
                'description' => 'Apache config file'
            ],
            
            // Sensitive directories (production only)
            'dir_config' => [
                'path' => '/config/web.php',
                'shouldBlock' => !$isDevMode,
                'description' => 'Config directory'
            ],
            'dir_runtime' => [
                'path' => '/runtime/logs/app.log',
                'shouldBlock' => true,
                'description' => 'Runtime directory'
            ],
            'dir_vendor' => [
                'path' => '/vendor/autoload.php',
                'shouldBlock' => true,
                'description' => 'Vendor directory'
            ],
            'dir_commands' => [
                'path' => '/commands/HelloController.php',
                'shouldBlock' => !$isDevMode,
                'description' => 'Commands directory'
            ],
            'dir_components' => [
                'path' => '/components/CspNonceManager.php',
                'shouldBlock' => !$isDevMode,
                'description' => 'Components directory'
            ],
            'dir_models' => [
                'path' => '/models/Coins.php',
                'shouldBlock' => !$isDevMode,
                'description' => 'Models directory'
            ],
            'dir_controllers' => [
                'path' => '/controllers/SiteController.php',
                'shouldBlock' => !$isDevMode,
                'description' => 'Controllers directory'
            ],
            
            // Sensitive file extensions (production only)
            'file_env_extension' => [
                'path' => '/test.env',
                'shouldBlock' => !$isDevMode,
                'description' => '.env file extension'
            ],
            'file_lock' => [
                'path' => '/composer.lock',
                'shouldBlock' => !$isDevMode,
                'description' => '.lock file'
            ],
            'file_json' => [
                'path' => '/composer.json',
                'shouldBlock' => !$isDevMode,
                'description' => '.json file'
            ],
            'file_yml' => [
                'path' => '/config.yml',
                'shouldBlock' => !$isDevMode,
                'description' => '.yml file'
            ],
            'file_yaml' => [
                'path' => '/config.yaml',
                'shouldBlock' => !$isDevMode,
                'description' => '.yaml file'
            ],
            'file_md' => [
                'path' => '/README.md',
                'shouldBlock' => !$isDevMode,
                'description' => '.md file'
            ],
        ];
        
        for ($i = 0; $i < $iterations; $i++) {
            // Randomly select a security path
            $pathKey = array_rand($securityPaths);
            $pathInfo = $securityPaths[$pathKey];
            
            $url = 'http://localhost' . $pathInfo['path'];
            
            // Make request through Nginx
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            // Parse response
            list($headers, $body) = $this->parseHttpResponse($response);
            
            if ($pathInfo['shouldBlock']) {
                // Verify path is blocked with 403 or 404
                if ($httpCode !== 403 && $httpCode !== 404) {
                    $failures[] = [
                        'iteration' => $i,
                        'pathKey' => $pathKey,
                        'path' => $pathInfo['path'],
                        'description' => $pathInfo['description'],
                        'httpCode' => $httpCode,
                        'reason' => sprintf(
                            'Expected HTTP 403 or 404 for blocked path, got %d',
                            $httpCode
                        )
                    ];
                }
                
                // Verify no sensitive content is leaked
                if ($httpCode === 200) {
                    $failures[] = [
                        'iteration' => $i,
                        'pathKey' => $pathKey,
                        'path' => $pathInfo['path'],
                        'description' => $pathInfo['description'],
                        'httpCode' => $httpCode,
                        'reason' => 'Sensitive path returned HTTP 200 - security breach!'
                    ];
                }
                
                // Verify response doesn't contain sensitive information
                $sensitivePatterns = [
                    'password',
                    'secret',
                    'api_key',
                    'private_key',
                    'DB_PASSWORD',
                    'COOKIE_VALIDATION_KEY',
                ];
                
                foreach ($sensitivePatterns as $pattern) {
                    if (stripos($body, $pattern) !== false) {
                        $failures[] = [
                            'iteration' => $i,
                            'pathKey' => $pathKey,
                            'path' => $pathInfo['path'],
                            'description' => $pathInfo['description'],
                            'httpCode' => $httpCode,
                            'reason' => sprintf(
                                'Response contains sensitive pattern "%s" - information leak!',
                                $pattern
                            )
                        ];
                        break;
                    }
                }
            } else {
                // In dev mode, some paths may be accessible
                // Just verify we don't get a server error
                if ($httpCode >= 500) {
                    $failures[] = [
                        'iteration' => $i,
                        'pathKey' => $pathKey,
                        'path' => $pathInfo['path'],
                        'description' => $pathInfo['description'],
                        'httpCode' => $httpCode,
                        'reason' => sprintf(
                            'Dev mode path returned server error %d',
                            $httpCode
                        )
                    ];
                }
            }
        }
        
        if (!empty($failures)) {
            $failureMessage = "Security path blocking failures:\n";
            foreach (array_slice($failures, 0, 10) as $failure) {
                $failureMessage .= sprintf(
                    "Iteration %d (%s): %s [%s] - HTTP %d - %s\n",
                    $failure['iteration'],
                    $failure['pathKey'],
                    $failure['path'],
                    $failure['description'],
                    $failure['httpCode'],
                    $failure['reason']
                );
            }
            $failureMessage .= sprintf("\nTotal failures: %d out of %d iterations", count($failures), $iterations);
            $this->fail($failureMessage);
        }
        
        $envMode = $isDevMode ? 'development' : 'production';
        $this->assertTrue(true, "All security-sensitive paths properly blocked in $envMode mode across $iterations iterations");
    }
    
    /**
     * Property: For any dotfile request, Nginx should deny access with 403/404
     */
    public function testDotfilesBlocked()
    {
        // Check if we're running in a container environment
        if (!$this->isContainerEnvironment()) {
            $this->markTestSkipped('This test requires a container environment');
        }
        
        $iterations = 50;
        $failures = [];
        
        // Common dotfiles that should always be blocked
        $dotfiles = [
            '.env',
            '.env.local',
            '.env.production',
            '.git/config',
            '.git/HEAD',
            '.gitignore',
            '.gitmodules',
            '.htaccess',
            '.htpasswd',
            '.dockerignore',
            '.editorconfig',
            '.travis.yml',
        ];
        
        for ($i = 0; $i < $iterations; $i++) {
            // Randomly select a dotfile
            $dotfile = $dotfiles[array_rand($dotfiles)];
            $url = 'http://localhost/' . $dotfile;
            
            // Make request
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            // Verify blocked with 403 or 404
            if ($httpCode !== 403 && $httpCode !== 404) {
                $failures[] = [
                    'iteration' => $i,
                    'dotfile' => $dotfile,
                    'httpCode' => $httpCode,
                    'reason' => sprintf('Expected HTTP 403 or 404, got %d', $httpCode)
                ];
            }
            
            // Verify no 200 OK
            if ($httpCode === 200) {
                $failures[] = [
                    'iteration' => $i,
                    'dotfile' => $dotfile,
                    'httpCode' => $httpCode,
                    'reason' => 'Dotfile accessible with HTTP 200 - security breach!'
                ];
            }
        }
        
        if (!empty($failures)) {
            $failureMessage = "Dotfile blocking failures:\n";
            foreach (array_slice($failures, 0, 5) as $failure) {
                $failureMessage .= sprintf(
                    "Iteration %d: %s - HTTP %d - %s\n",
                    $failure['iteration'],
                    $failure['dotfile'],
                    $failure['httpCode'],
                    $failure['reason']
                );
            }
            $failureMessage .= sprintf("\nTotal failures: %d out of %d iterations", count($failures), $iterations);
            $this->fail($failureMessage);
        }
        
        $this->assertTrue(true, "All dotfiles properly blocked across $iterations iterations");
    }
    
    /**
     * Property: For any request to config/runtime/vendor directories, Nginx should
     * deny access with 403/404
     */
    public function testSensitiveDirectoriesBlocked()
    {
        // Check if we're running in a container environment
        if (!$this->isContainerEnvironment()) {
            $this->markTestSkipped('This test requires a container environment');
        }
        
        $iterations = 50;
        $failures = [];
        
        // Detect environment
        $nginxConfig = file_exists('/etc/nginx/sites-enabled/default') ? 
            file_get_contents('/etc/nginx/sites-enabled/default') : '';
        $isDevMode = (strpos($nginxConfig, 'Development') !== false ||
                     strpos($nginxConfig, 'YIIMP_DEBUG') !== false);
        
        // Directories that should be blocked
        $sensitiveDirectories = [
            '/runtime/logs/app.log',
            '/runtime/cache/data.bin',
            '/vendor/autoload.php',
            '/vendor/composer/autoload_real.php',
        ];
        
        // In production, also block these
        if (!$isDevMode) {
            $sensitiveDirectories = array_merge($sensitiveDirectories, [
                '/config/web.php',
                '/config/db.php',
                '/commands/HelloController.php',
                '/components/CspNonceManager.php',
                '/models/Coins.php',
                '/controllers/SiteController.php',
                '/migrations/m000000_000000_init.php',
            ]);
        }
        
        for ($i = 0; $i < $iterations; $i++) {
            // Randomly select a directory path
            $path = $sensitiveDirectories[array_rand($sensitiveDirectories)];
            $url = 'http://localhost' . $path;
            
            // Make request
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            list($headers, $body) = $this->parseHttpResponse($response);
            
            // Verify blocked with 403 or 404
            if ($httpCode !== 403 && $httpCode !== 404) {
                $failures[] = [
                    'iteration' => $i,
                    'path' => $path,
                    'httpCode' => $httpCode,
                    'reason' => sprintf('Expected HTTP 403 or 404, got %d', $httpCode)
                ];
            }
            
            // Verify no 200 OK
            if ($httpCode === 200) {
                $failures[] = [
                    'iteration' => $i,
                    'path' => $path,
                    'httpCode' => $httpCode,
                    'reason' => 'Sensitive directory accessible with HTTP 200 - security breach!'
                ];
            }
            
            // Verify no source code is leaked
            if (stripos($body, '<?php') !== false || stripos($body, 'namespace') !== false) {
                $failures[] = [
                    'iteration' => $i,
                    'path' => $path,
                    'httpCode' => $httpCode,
                    'reason' => 'Response contains PHP source code - information leak!'
                ];
            }
        }
        
        if (!empty($failures)) {
            $failureMessage = "Sensitive directory blocking failures:\n";
            foreach (array_slice($failures, 0, 5) as $failure) {
                $failureMessage .= sprintf(
                    "Iteration %d: %s - HTTP %d - %s\n",
                    $failure['iteration'],
                    $failure['path'],
                    $failure['httpCode'],
                    $failure['reason']
                );
            }
            $failureMessage .= sprintf("\nTotal failures: %d out of %d iterations", count($failures), $iterations);
            $this->fail($failureMessage);
        }
        
        $envMode = $isDevMode ? 'development' : 'production';
        $this->assertTrue(true, "All sensitive directories properly blocked in $envMode mode across $iterations iterations");
    }
    
    /**
     * Property: For any request to PHP files in uploads/assets directories,
     * Nginx should deny execution
     */
    public function testPhpExecutionBlockedInUploads()
    {
        // Check if we're running in a container environment
        if (!$this->isContainerEnvironment()) {
            $this->markTestSkipped('This test requires a container environment');
        }
        
        $iterations = 30;
        $failures = [];
        
        // Test PHP execution in uploads and assets directories
        $uploadPaths = [
            '/assets/test.php',
            '/uploads/test.php',
            '/assets/images/test.php',
            '/uploads/files/test.php',
        ];
        
        for ($i = 0; $i < $iterations; $i++) {
            // Randomly select an upload path
            $path = $uploadPaths[array_rand($uploadPaths)];
            
            // Create the directory if it doesn't exist
            $fullPath = '/var/yiimp2/web' . $path;
            $dir = dirname($fullPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            
            // Create a test PHP file
            $phpContent = '<?php echo "EXECUTED"; ?>';
            file_put_contents($fullPath, $phpContent);
            
            // Make request
            $url = 'http://localhost' . $path;
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            list($headers, $body) = $this->parseHttpResponse($response);
            
            // Verify PHP was NOT executed
            if (trim($body) === 'EXECUTED') {
                $failures[] = [
                    'iteration' => $i,
                    'path' => $path,
                    'httpCode' => $httpCode,
                    'reason' => 'PHP file in uploads/assets was executed - security breach!'
                ];
            }
            
            // Verify access is denied (403) or file is served as text (200 with PHP code visible)
            if ($httpCode === 200 && strpos($body, '<?php') === false) {
                $failures[] = [
                    'iteration' => $i,
                    'path' => $path,
                    'httpCode' => $httpCode,
                    'reason' => 'PHP file executed successfully - should be blocked'
                ];
            }
            
            // Clean up
            @unlink($fullPath);
        }
        
        if (!empty($failures)) {
            $failureMessage = "PHP execution blocking failures:\n";
            foreach (array_slice($failures, 0, 5) as $failure) {
                $failureMessage .= sprintf(
                    "Iteration %d: %s - HTTP %d - %s\n",
                    $failure['iteration'],
                    $failure['path'],
                    $failure['httpCode'],
                    $failure['reason']
                );
            }
            $failureMessage .= sprintf("\nTotal failures: %d out of %d iterations", count($failures), $iterations);
            $this->fail($failureMessage);
        }
        
        $this->assertTrue(true, "PHP execution properly blocked in uploads/assets across $iterations iterations");
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
