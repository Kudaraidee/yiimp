<?php

namespace tests\helpers;

/**
 * Detects runtime environment and available services
 * 
 * Helps tests adapt their behavior based on whether they're running
 * in a container, development environment, or CI environment.
 */
class EnvironmentDetector
{
    /**
     * Check if running in a Docker container
     * 
     * @return bool
     */
    public static function isContainerEnvironment(): bool
    {
        // Check for .dockerenv file
        if (file_exists('/.dockerenv')) {
            return true;
        }
        
        // Check for Docker in cgroup
        if (file_exists('/proc/1/cgroup')) {
            $cgroup = file_get_contents('/proc/1/cgroup');
            if (strpos($cgroup, 'docker') !== false || strpos($cgroup, 'lxc') !== false) {
                return true;
            }
        }
        
        // Check environment variable
        if (getenv('DOCKER_CONTAINER') === 'true') {
            return true;
        }
        
        return false;
    }
    
    /**
     * Check if memcached is available
     * 
     * @return bool
     */
    public static function isMemcachedAvailable(): bool
    {
        if (!class_exists('Memcached')) {
            return false;
        }
        
        try {
            $memcached = new \Memcached();
            $memcached->addServer('localhost', 11211);
            $memcached->set('test_key', 'test_value', 1);
            $result = $memcached->get('test_key');
            return $result === 'test_value';
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Get configuration file path based on environment
     * 
     * @param string $filename Configuration filename
     * @return string|null Path to config file or null if not found
     */
    public static function getConfigPath(string $filename): ?string
    {
        $paths = [
            '/etc/yiimp2/' . $filename,
            '/etc/yiimp/' . $filename,
            __DIR__ . '/../../../config/yiimp2/' . $filename,
            __DIR__ . '/../../../config/' . $filename,
        ];
        
        foreach ($paths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }
        
        return null;
    }
    
    /**
     * Check if a configuration file exists
     * 
     * @param string $filename Configuration filename
     * @return bool
     */
    public static function configFileExists(string $filename): bool
    {
        return self::getConfigPath($filename) !== null;
    }
    
    /**
     * Check if running in CI environment
     * 
     * @return bool
     */
    public static function isCIEnvironment(): bool
    {
        $ciEnvVars = ['CI', 'CONTINUOUS_INTEGRATION', 'GITHUB_ACTIONS', 'GITLAB_CI', 'TRAVIS'];
        
        foreach ($ciEnvVars as $var) {
            if (getenv($var)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if a process is running
     * 
     * @param string $processName Process name to check
     * @return bool
     */
    public static function isProcessRunning(string $processName): bool
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $output = shell_exec("tasklist /FI \"IMAGENAME eq {$processName}\" 2>NUL");
            return strpos($output, $processName) !== false;
        }
        
        $output = shell_exec("ps aux | grep -v grep | grep {$processName}");
        return !empty($output);
    }
    
    /**
     * Check if a port is open/listening
     * 
     * @param string $host Hostname or IP
     * @param int $port Port number
     * @param int $timeout Timeout in seconds
     * @return bool
     */
    public static function isPortOpen(string $host, int $port, int $timeout = 1): bool
    {
        $connection = @fsockopen($host, $port, $errno, $errstr, $timeout);
        
        if (is_resource($connection)) {
            fclose($connection);
            return true;
        }
        
        return false;
    }
    
    /**
     * Get environment type
     * 
     * @return string 'container', 'ci', or 'development'
     */
    public static function getEnvironmentType(): string
    {
        if (self::isContainerEnvironment()) {
            return 'container';
        }
        
        if (self::isCIEnvironment()) {
            return 'ci';
        }
        
        return 'development';
    }
    
    /**
     * Check if full integration tests should run
     * 
     * @return bool
     */
    public static function shouldRunIntegrationTests(): bool
    {
        // Check environment variable override
        $runIntegration = getenv('RUN_INTEGRATION_TESTS');
        if ($runIntegration !== false) {
            return filter_var($runIntegration, FILTER_VALIDATE_BOOLEAN);
        }
        
        // Default: run integration tests in container or CI
        return self::isContainerEnvironment() || self::isCIEnvironment();
    }
}
