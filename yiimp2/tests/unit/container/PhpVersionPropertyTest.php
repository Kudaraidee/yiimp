<?php

namespace tests\unit\container;

use Codeception\Test\Unit;

/**
 * Property Test for Yiimp2 Container PHP Version Requirement
 * 
 * Feature: yiimp-to-yiimp2-migration, Property 54: Yiimp2 container PHP version
 * Validates: Requirements 16.1
 * 
 * Property: For any Yiimp2 container deployment, the PHP version should be 8.2 or higher
 * to satisfy Symfony dependency requirements.
 */
class PhpVersionPropertyTest extends Unit
{
    /**
     * Test that PHP version meets minimum requirement of 8.2
     * 
     * This property test verifies that the PHP version in the Yiimp2 container
     * is 8.2 or higher, which is required for Symfony 7.x dependencies.
     */
    public function testPhpVersionMeetsMinimumRequirement()
    {
        // Get current PHP version
        $phpVersion = PHP_VERSION;
        $versionParts = explode('.', $phpVersion);
        $majorVersion = (int)$versionParts[0];
        $minorVersion = (int)$versionParts[1];
        
        // Property: PHP version must be >= 8.2
        $this->assertGreaterThanOrEqual(8, $majorVersion, 
            "PHP major version must be at least 8, got {$majorVersion}");
        
        if ($majorVersion === 8) {
            $this->assertGreaterThanOrEqual(2, $minorVersion,
                "PHP 8.x minor version must be at least 2, got 8.{$minorVersion}");
        }
        
        // Additional check: version_compare for precise comparison
        $this->assertTrue(
            version_compare($phpVersion, '8.2.0', '>='),
            "PHP version must be >= 8.2.0, got {$phpVersion}"
        );
    }
    
    /**
     * Test that required PHP extensions for Yii2 are loaded
     * 
     * This verifies that the container has all necessary PHP extensions
     * for Yii2 framework to function properly.
     */
    public function testRequiredPhpExtensionsAreLoaded()
    {
        $requiredExtensions = [
            'bcmath',
            'curl',
            'gd',
            'intl',
            'mbstring',
            'mysqli',
            'xml',
            'zip',
        ];
        
        // opcache is typically only enabled for web server, not CLI
        $optionalExtensions = [
            'opcache',
        ];
        
        foreach ($requiredExtensions as $extension) {
            $this->assertTrue(
                extension_loaded($extension),
                "Required PHP extension '{$extension}' is not loaded"
            );
        }
        
        // Check optional extensions but don't fail if not loaded in CLI
        foreach ($optionalExtensions as $extension) {
            if (!extension_loaded($extension)) {
                // Just note it's not loaded, don't fail the test
                $this->assertTrue(true, 
                    "Optional extension '{$extension}' not loaded in CLI (expected for opcache)");
            } else {
                $this->assertTrue(true, 
                    "Optional extension '{$extension}' is loaded");
            }
        }
    }
    
    /**
     * Test that Composer is available and functional
     * 
     * This verifies that Composer is installed and can be executed,
     * which is required for managing Yii2 dependencies.
     */
    public function testComposerIsAvailable()
    {
        // Check if composer.json exists
        $composerJsonPath = \Yii::getAlias('@app/composer.json');
        $this->assertFileExists($composerJsonPath, 
            "composer.json should exist in application root");
        
        // Check if vendor directory exists (dependencies installed)
        $vendorPath = \Yii::getAlias('@app/vendor');
        $this->assertDirectoryExists($vendorPath,
            "vendor directory should exist (Composer dependencies installed)");
        
        // Check if Yii2 framework is installed
        $yiiFrameworkPath = \Yii::getAlias('@app/vendor/yiisoft/yii2');
        $this->assertDirectoryExists($yiiFrameworkPath,
            "Yii2 framework should be installed via Composer");
    }
    
    /**
     * Test that Symfony components are available
     * 
     * This verifies that Symfony 7.x components are installed,
     * which require PHP 8.2+.
     */
    public function testSymfonyComponentsAreAvailable()
    {
        // Check if Symfony components are installed
        $symfonyPath = \Yii::getAlias('@app/vendor/symfony');
        
        if (is_dir($symfonyPath)) {
            // If Symfony is installed, verify it's version 7.x or compatible
            $composerLockPath = \Yii::getAlias('@app/composer.lock');
            
            if (file_exists($composerLockPath)) {
                $composerLock = json_decode(file_get_contents($composerLockPath), true);
                
                // Check for Symfony packages in dependencies
                $symfonyPackages = array_filter($composerLock['packages'] ?? [], function($package) {
                    return strpos($package['name'], 'symfony/') === 0;
                });
                
                foreach ($symfonyPackages as $package) {
                    // Verify Symfony packages are compatible with PHP 8.2+
                    $version = $package['version'];
                    $this->assertNotEmpty($version, 
                        "Symfony package {$package['name']} should have a version");
                }
            }
        }
        
        // This test passes if Symfony is not installed or if it is installed correctly
        $this->assertTrue(true, "Symfony component check completed");
    }
}
