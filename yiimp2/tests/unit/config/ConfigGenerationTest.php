<?php

namespace yiimp2\tests\unit\config;

use Codeception\Test\Unit;

/**
 * Unit tests for Yiimp2 configuration generation script
 * 
 * Tests the bin/generate-yiimp2-configs.sh script functionality:
 * - serverconfig.php generation with correct database settings
 * - Stratum config generation with port offsets
 * - Configuration file permissions
 * 
 * Requirements: 9.1, 9.2, 16.1, 16.2
 */
class ConfigGenerationTest extends Unit
{
    private $testConfigDir;
    private $testStratumDir;
    private $scriptPath;
    
    protected function _before()
    {
        // Set up test directories
        $this->testConfigDir = codecept_data_dir() . 'test_yiimp2_config';
        $this->testStratumDir = $this->testConfigDir . '/stratum';
        $this->scriptPath = codecept_root_dir() . '../bin/generate-yiimp2-configs.sh';
        
        // Clean up any previous test artifacts
        if (file_exists($this->testConfigDir)) {
            $this->recursiveRemoveDirectory($this->testConfigDir);
        }
    }
    
    protected function _after()
    {
        // Clean up test directories
        if (file_exists($this->testConfigDir)) {
            $this->recursiveRemoveDirectory($this->testConfigDir);
        }
    }
    
    /**
     * Test serverconfig.php generation with correct database settings
     * Validates Requirements 9.1, 9.2
     */
    public function testServerconfigGeneration()
    {
        // Create temporary config directory structure
        $tempDir = sys_get_temp_dir() . '/yiimp2_test_' . uniqid();
        mkdir($tempDir, 0755, true);
        mkdir($tempDir . '/config/yiimp2', 0755, true);
        mkdir($tempDir . '/config/stratum', 0755, true);
        
        // Create a minimal legacy stratum config for testing
        file_put_contents($tempDir . '/config/stratum/sha256.conf', "[TCP]\nport = 3333\n[STRATUM]\nalgo = sha256\ndifficulty = 128\n");
        
        // Run the script
        $dbPass = 'test_password_' . uniqid();
        $command = sprintf(
            'cd %s && %s --db-name test_yiimp2 --db-user test_user --db-pass %s --db-host test-db 2>&1',
            escapeshellarg($tempDir),
            escapeshellarg($this->scriptPath),
            escapeshellarg($dbPass)
        );
        
        exec($command, $output, $returnCode);
        
        // Verify script executed successfully
        $this->assertEquals(0, $returnCode, "Script should execute successfully. Output: " . implode("\n", $output));
        
        // Verify serverconfig.php was created
        $serverconfigPath = $tempDir . '/config/yiimp2/serverconfig.php';
        $this->assertFileExists($serverconfigPath, "serverconfig.php should be created");
        
        // Read and verify serverconfig.php content
        $content = file_get_contents($serverconfigPath);
        
        // Verify database settings
        $this->assertStringContainsString("define('YAAMP_DBHOST', 'test-db');", $content, "Database host should be set correctly");
        $this->assertStringContainsString("define('YAAMP_DBNAME', 'test_yiimp2');", $content, "Database name should be set correctly");
        $this->assertStringContainsString("define('YAAMP_DBUSER', 'test_user');", $content, "Database user should be set correctly");
        $this->assertStringContainsString("define('YAAMP_DBPASSWORD', '{$dbPass}');", $content, "Database password should be set correctly");
        
        // Verify it's a valid PHP file
        $this->assertStringStartsWith('<?php', $content, "File should start with PHP opening tag");
        
        // Verify essential configuration defines exist
        $this->assertStringContainsString("define('YAAMP_LOGS'", $content, "YAAMP_LOGS should be defined");
        $this->assertStringContainsString("define('YAAMP_HTDOCS'", $content, "YAAMP_HTDOCS should be defined");
        $this->assertStringContainsString("define('YAAMP_SITE_URL'", $content, "YAAMP_SITE_URL should be defined");
        
        // Clean up
        $this->recursiveRemoveDirectory($tempDir);
    }
    
    /**
     * Test stratum config generation with port offsets
     * Validates Requirements 16.1, 16.2
     */
    public function testStratumConfigGeneration()
    {
        // Create temporary config directory structure
        $tempDir = sys_get_temp_dir() . '/yiimp2_test_' . uniqid();
        mkdir($tempDir, 0755, true);
        mkdir($tempDir . '/config/yiimp2', 0755, true);
        mkdir($tempDir . '/config/stratum', 0755, true);
        
        // Create legacy stratum configs with different ports and difficulties
        file_put_contents($tempDir . '/config/stratum/sha256.conf', 
            "[TCP]\nport = 3333\n[STRATUM]\nalgo = sha256\ndifficulty = 128\nmax_ttf = 40000\n");
        file_put_contents($tempDir . '/config/stratum/sha256-high.conf', 
            "[TCP]\nport = 3334\n[STRATUM]\nalgo = sha256\ndifficulty = 1000000\nmax_ttf = 40000\n");
        file_put_contents($tempDir . '/config/stratum/scrypt.conf', 
            "[TCP]\nport = 3433\n[STRATUM]\nalgo = scrypt\ndifficulty = 256\nmax_ttf = 40000\n");
        
        // Run the script with custom port offset
        $dbPass = 'test_password_' . uniqid();
        $portOffset = 1000;
        $command = sprintf(
            'cd %s && %s --db-name yiimp2 --db-user yiimp2 --db-pass %s --port-offset %d 2>&1',
            escapeshellarg($tempDir),
            escapeshellarg($this->scriptPath),
            escapeshellarg($dbPass),
            $portOffset
        );
        
        exec($command, $output, $returnCode);
        
        // Verify script executed successfully
        $this->assertEquals(0, $returnCode, "Script should execute successfully");
        
        // Verify stratum configs were created
        $sha256Config = $tempDir . '/config/yiimp2/stratum/sha256.conf';
        $sha256HighConfig = $tempDir . '/config/yiimp2/stratum/sha256-high.conf';
        $scryptConfig = $tempDir . '/config/yiimp2/stratum/scrypt.conf';
        
        $this->assertFileExists($sha256Config, "sha256.conf should be created");
        $this->assertFileExists($sha256HighConfig, "sha256-high.conf should be created");
        $this->assertFileExists($scryptConfig, "scrypt.conf should be created");
        
        // Verify sha256.conf content
        $sha256Content = file_get_contents($sha256Config);
        $this->assertStringContainsString("host = yiimp2-db", $sha256Content, "Database host should be yiimp2-db");
        $this->assertStringContainsString("database = yiimp2", $sha256Content, "Database name should be yiimp2");
        $this->assertStringContainsString("username = yiimp2", $sha256Content, "Database user should be yiimp2");
        $this->assertStringContainsString("password = {$dbPass}", $sha256Content, "Database password should be set");
        $this->assertStringContainsString("algo = sha256", $sha256Content, "Algorithm should be sha256");
        $this->assertStringContainsString("difficulty = 128", $sha256Content, "Difficulty should be preserved");
        
        // Verify sha256-high.conf preserves high difficulty
        $sha256HighContent = file_get_contents($sha256HighConfig);
        $this->assertStringContainsString("algo = sha256", $sha256HighContent, "Algorithm should be sha256");
        $this->assertStringContainsString("difficulty = 1000000", $sha256HighContent, "High difficulty should be preserved");
        
        // Verify scrypt.conf
        $scryptContent = file_get_contents($scryptConfig);
        $this->assertStringContainsString("algo = scrypt", $scryptContent, "Algorithm should be scrypt");
        $this->assertStringContainsString("difficulty = 256", $scryptContent, "Difficulty should be preserved");
        
        // Clean up
        $this->recursiveRemoveDirectory($tempDir);
    }
    
    /**
     * Test configuration file permissions
     * Validates Requirements 9.1, 9.2
     */
    public function testConfigFilePermissions()
    {
        // Create temporary config directory structure
        $tempDir = sys_get_temp_dir() . '/yiimp2_test_' . uniqid();
        mkdir($tempDir, 0755, true);
        mkdir($tempDir . '/config/yiimp2', 0755, true);
        mkdir($tempDir . '/config/stratum', 0755, true);
        
        // Create a minimal legacy stratum config
        file_put_contents($tempDir . '/config/stratum/sha256.conf', "[TCP]\nport = 3333\n[STRATUM]\nalgo = sha256\n");
        
        // Run the script
        $dbPass = 'test_password_' . uniqid();
        $command = sprintf(
            'cd %s && %s --db-name yiimp2 --db-user yiimp2 --db-pass %s 2>&1',
            escapeshellarg($tempDir),
            escapeshellarg($this->scriptPath),
            escapeshellarg($dbPass)
        );
        
        exec($command, $output, $returnCode);
        
        // Verify script executed successfully
        $this->assertEquals(0, $returnCode, "Script should execute successfully");
        
        // Check serverconfig.php permissions
        $serverconfigPath = $tempDir . '/config/yiimp2/serverconfig.php';
        $this->assertFileExists($serverconfigPath);
        
        $perms = fileperms($serverconfigPath);
        $octalPerms = substr(sprintf('%o', $perms), -3);
        $this->assertEquals('600', $octalPerms, "serverconfig.php should have 600 permissions (read/write for owner only)");
        
        // Check stratum config permissions
        $stratumConfig = $tempDir . '/config/yiimp2/stratum/sha256.conf';
        $this->assertFileExists($stratumConfig);
        
        $perms = fileperms($stratumConfig);
        $octalPerms = substr(sprintf('%o', $perms), -3);
        $this->assertEquals('600', $octalPerms, "Stratum configs should have 600 permissions (read/write for owner only)");
        
        // Clean up
        $this->recursiveRemoveDirectory($tempDir);
    }
    
    /**
     * Test script with missing required parameters
     */
    public function testMissingRequiredParameters()
    {
        // Try to run script without --db-pass
        $command = sprintf('%s --db-name yiimp2 --db-user yiimp2 2>&1', escapeshellarg($this->scriptPath));
        exec($command, $output, $returnCode);
        
        // Should fail with non-zero exit code
        $this->assertNotEquals(0, $returnCode, "Script should fail when --db-pass is missing");
        
        // Should contain error message
        $outputStr = implode("\n", $output);
        $this->assertStringContainsString("--db-pass is required", $outputStr, "Should show error about missing --db-pass");
    }
    
    /**
     * Test script help option
     */
    public function testHelpOption()
    {
        $command = sprintf('%s --help 2>&1', escapeshellarg($this->scriptPath));
        exec($command, $output, $returnCode);
        
        // Should succeed
        $this->assertEquals(0, $returnCode, "Help option should succeed");
        
        // Should contain usage information
        $outputStr = implode("\n", $output);
        $this->assertStringContainsString("Usage:", $outputStr, "Should show usage information");
        $this->assertStringContainsString("--db-name", $outputStr, "Should document --db-name option");
        $this->assertStringContainsString("--db-pass", $outputStr, "Should document --db-pass option");
        $this->assertStringContainsString("--port-offset", $outputStr, "Should document --port-offset option");
    }
    
    /**
     * Helper function to recursively remove a directory
     */
    private function recursiveRemoveDirectory($dir)
    {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->recursiveRemoveDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
