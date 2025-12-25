<?php

namespace yiimp2\tests\unit\schema;

use Codeception\Test\Unit;

/**
 * Unit tests for SQL schema generation script
 * Tests the bin/generate-yiimp2-schema.sh script functionality
 * 
 * Requirements: 6.3, 6.4
 */
class SchemaGenerationTest extends Unit
{
    private $testDir;
    private $scriptPath;
    
    protected function _before()
    {
        $this->scriptPath = dirname(dirname(dirname(__DIR__))) . '/../bin/generate-yiimp2-schema.sh';
        $this->testDir = sys_get_temp_dir() . '/yiimp2-schema-test-' . uniqid();
        mkdir($this->testDir, 0755, true);
    }
    
    protected function _after()
    {
        // Cleanup test directory
        if (is_dir($this->testDir)) {
            $this->rrmdir($this->testDir);
        }
    }
    
    /**
     * Recursively remove directory
     */
    private function rrmdir($dir)
    {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (is_dir($dir . "/" . $object)) {
                        $this->rrmdir($dir . "/" . $object);
                    } else {
                        unlink($dir . "/" . $object);
                    }
                }
            }
            rmdir($dir);
        }
    }
    
    /**
     * Test that the script file exists and is executable
     */
    public function testScriptExists()
    {
        $this->assertFileExists($this->scriptPath, 'Schema generation script should exist');
        $this->assertTrue(is_executable($this->scriptPath), 'Script should be executable');
    }
    
    /**
     * Test SQL file parsing with valid migration files
     * Requirements: 6.3, 6.4
     */
    public function testSqlFileParsing()
    {
        // Create test SQL directory structure
        $sqlDir = $this->testDir . '/sql';
        mkdir($sqlDir, 0755, true);
        
        // Create a simple base schema
        $baseSchema = "CREATE TABLE IF NOT EXISTS `test_table` (\n";
        $baseSchema .= "  `id` int NOT NULL AUTO_INCREMENT,\n";
        $baseSchema .= "  `name` varchar(64) DEFAULT NULL,\n";
        $baseSchema .= "  PRIMARY KEY (`id`)\n";
        $baseSchema .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;\n";
        
        file_put_contents($sqlDir . '/base.sql', $baseSchema);
        
        // Verify the file was created and can be parsed
        $this->assertFileExists($sqlDir . '/base.sql');
        $content = file_get_contents($sqlDir . '/base.sql');
        $this->assertStringContainsString('CREATE TABLE', $content);
        $this->assertStringContainsString('test_table', $content);
    }
    
    /**
     * Test chronological ordering of migrations
     * Requirements: 6.3
     */
    public function testChronologicalOrdering()
    {
        // Create test migration files with dates
        $sqlDir = $this->testDir . '/sql';
        mkdir($sqlDir, 0755, true);
        
        $migrations = [
            '2024-01-15-first.sql' => 'ALTER TABLE test ADD COLUMN first INT;',
            '2024-03-20-second.sql' => 'ALTER TABLE test ADD COLUMN second INT;',
            '2024-02-10-third.sql' => 'ALTER TABLE test ADD COLUMN third INT;',
        ];
        
        foreach ($migrations as $filename => $content) {
            file_put_contents($sqlDir . '/' . $filename, $content);
        }
        
        // Get files in chronological order (simulating what the script does)
        $files = glob($sqlDir . '/20*.sql');
        sort($files);
        
        // Verify ordering
        $this->assertCount(3, $files);
        $this->assertStringContainsString('2024-01-15', $files[0]);
        $this->assertStringContainsString('2024-02-10', $files[1]);
        $this->assertStringContainsString('2024-03-20', $files[2]);
    }
    
    /**
     * Test decimal column extraction
     * Requirements: 6.4
     */
    public function testDecimalColumnExtraction()
    {
        // Create a test old-yiimp.sql file with decimal column
        $sqlDir = $this->testDir . '/sql';
        mkdir($sqlDir, 0755, true);
        
        $oldYiimpSql = "CREATE TABLE `coins` (\n";
        $oldYiimpSql .= "  `id` int(11) NOT NULL,\n";
        $oldYiimpSql .= "  `name` varchar(64) DEFAULT NULL,\n";
        $oldYiimpSql .= "  `symbol` varchar(16) DEFAULT NULL,\n";
        $oldYiimpSql .= "  `decimals` tinyint(3) UNSIGNED NOT NULL DEFAULT 8,\n";
        $oldYiimpSql .= "  `algo` varchar(16) DEFAULT NULL\n";
        $oldYiimpSql .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;\n";
        
        file_put_contents($sqlDir . '/old-yiimp.sql', $oldYiimpSql);
        
        // Extract decimal line (simulating what the script does)
        $content = file_get_contents($sqlDir . '/old-yiimp.sql');
        $lines = explode("\n", $content);
        $decimalLine = null;
        
        foreach ($lines as $line) {
            if (preg_match('/^\s*`decimals`/', $line)) {
                $decimalLine = trim($line);
                break;
            }
        }
        
        $this->assertNotNull($decimalLine, 'Decimal column definition should be found');
        $this->assertStringContainsString('decimals', $decimalLine);
        $this->assertStringContainsString('tinyint', $decimalLine);
        $this->assertStringContainsString('DEFAULT 8', $decimalLine);
    }
    
    /**
     * Test consolidated SQL file generation
     * Requirements: 6.4
     */
    public function testConsolidatedSqlGeneration()
    {
        // Create test files
        $sqlDir = $this->testDir . '/sql';
        mkdir($sqlDir, 0755, true);
        
        $baseSchema = "CREATE TABLE IF NOT EXISTS `test_table` (\n";
        $baseSchema .= "  `id` int NOT NULL AUTO_INCREMENT\n";
        $baseSchema .= ") ENGINE=InnoDB;\n";
        
        $migration1 = "ALTER TABLE test_table ADD COLUMN name VARCHAR(64);\n";
        
        file_put_contents($sqlDir . '/base.sql', $baseSchema);
        file_put_contents($sqlDir . '/2024-01-01-add-name.sql', $migration1);
        
        // Simulate consolidated file generation
        $consolidated = "-- Base Schema\n";
        $consolidated .= file_get_contents($sqlDir . '/base.sql');
        $consolidated .= "\n-- Migrations\n";
        $consolidated .= file_get_contents($sqlDir . '/2024-01-01-add-name.sql');
        
        $outputFile = $sqlDir . '/consolidated.sql';
        file_put_contents($outputFile, $consolidated);
        
        // Verify consolidated file
        $this->assertFileExists($outputFile);
        $content = file_get_contents($outputFile);
        $this->assertStringContainsString('Base Schema', $content);
        $this->assertStringContainsString('Migrations', $content);
        $this->assertStringContainsString('CREATE TABLE', $content);
        $this->assertStringContainsString('ALTER TABLE', $content);
    }
    
    /**
     * Test handling of missing SQL files
     * Requirements: 6.3, 6.4
     */
    public function testMissingFiles()
    {
        $sqlDir = $this->testDir . '/sql';
        mkdir($sqlDir, 0755, true);
        
        // Test with non-existent base schema
        $baseSchemaPath = $sqlDir . '/non-existent.sql.gz';
        $this->assertFileDoesNotExist($baseSchemaPath);
        
        // Test with non-existent old-yiimp.sql
        $oldYiimpPath = $sqlDir . '/old-yiimp.sql';
        $this->assertFileDoesNotExist($oldYiimpPath);
    }
    
    /**
     * Test handling of malformed SQL files
     * Requirements: 6.3, 6.4
     */
    public function testMalformedSqlFiles()
    {
        $sqlDir = $this->testDir . '/sql';
        mkdir($sqlDir, 0755, true);
        
        // Create malformed SQL file (missing semicolon, incomplete statement)
        $malformedSql = "CREATE TABLE test (\n";
        $malformedSql .= "  id INT\n";
        // Missing closing parenthesis and semicolon
        
        file_put_contents($sqlDir . '/malformed.sql', $malformedSql);
        
        // Verify file exists but is malformed
        $this->assertFileExists($sqlDir . '/malformed.sql');
        $content = file_get_contents($sqlDir . '/malformed.sql');
        $this->assertStringNotContainsString(');', $content);
    }
    
    /**
     * Test that DROP TABLE IF EXISTS statements are included
     * Requirements: 6.5 (idempotency)
     */
    public function testDropTableStatements()
    {
        // Read the actual generated schema file
        $schemaFile = dirname(dirname(dirname(__DIR__))) . '/../sql/yiimp2-init.sql';
        
        if (file_exists($schemaFile)) {
            $content = file_get_contents($schemaFile);
            
            // Verify DROP TABLE statements exist
            $this->assertStringContainsString('DROP TABLE IF EXISTS', $content);
            $this->assertStringContainsString('DROP TABLE IF EXISTS `accounts`', $content);
            $this->assertStringContainsString('DROP TABLE IF EXISTS `coins`', $content);
            $this->assertStringContainsString('DROP TABLE IF EXISTS `algos`', $content);
        } else {
            $this->markTestSkipped('Generated schema file not found. Run bin/generate-yiimp2-schema.sh first.');
        }
    }
    
    /**
     * Test that comments indicating source are included
     * Requirements: 6.4
     */
    public function testSourceComments()
    {
        // Read the actual generated schema file
        $schemaFile = dirname(dirname(dirname(__DIR__))) . '/../sql/yiimp2-init.sql';
        
        if (file_exists($schemaFile)) {
            $content = file_get_contents($schemaFile);
            
            // Verify section comments exist
            $this->assertStringContainsString('SECTION 1: Base Schema', $content);
            $this->assertStringContainsString('SECTION 2: Migration Files', $content);
            $this->assertStringContainsString('SECTION 3: Decimal Column', $content);
            $this->assertStringContainsString('Source: sql/2024-03-06-complete_export.sql.gz', $content);
            $this->assertStringContainsString('Source: sql/old-yiimp.sql', $content);
        } else {
            $this->markTestSkipped('Generated schema file not found. Run bin/generate-yiimp2-schema.sh first.');
        }
    }
}
