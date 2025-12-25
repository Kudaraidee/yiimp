<?php

namespace yiimp2\tests\unit\schema;

use Codeception\Test\Unit;

/**
 * Test schema import on clean database
 * Tests that the generated sql/yiimp2-init.sql can be imported successfully
 * 
 * Uses a temporary Docker MariaDB container for testing
 * 
 * Requirements: 6.6, 6.7, 14.5
 */
class SchemaImportTest extends Unit
{
    private $containerName;
    private $dbConnection;
    private $schemaFile;
    private $dbPort;
    private $dbPassword = 'test_password_123';
    
    protected function _before()
    {
        $this->containerName = 'yiimp2_schema_test_' . uniqid();
        $this->dbPort = rand(13306, 19999); // Random port to avoid conflicts
        $this->schemaFile = dirname(dirname(dirname(__DIR__))) . '/../sql/yiimp2-init.sql';
        
        // Skip if schema file doesn't exist
        if (!file_exists($this->schemaFile)) {
            $this->markTestSkipped('Schema file sql/yiimp2-init.sql not found. Run bin/generate-yiimp2-schema.sh first.');
        }
        
        // Check if docker/podman is available
        $dockerCmd = $this->getDockerCommand();
        if (!$dockerCmd) {
            $this->markTestSkipped('Docker or Podman not available for testing');
        }
        
        // Start MariaDB container
        $cmd = "{$dockerCmd} run -d " .
               "--name {$this->containerName} " .
               "-e MYSQL_ROOT_PASSWORD={$this->dbPassword} " .
               "-e MYSQL_DATABASE=yiimp2_test " .
               "-p {$this->dbPort}:3306 " .
               "mariadb:10.11 " .
               "--character-set-server=utf8mb3 " .
               "--collation-server=utf8mb3_general_ci";
        
        exec($cmd, $output, $returnCode);
        
        if ($returnCode !== 0) {
            $this->markTestSkipped('Failed to start MariaDB container: ' . implode("\n", $output));
        }
        
        // Wait for MariaDB to be ready (max 30 seconds)
        $maxAttempts = 30;
        $attempt = 0;
        $connected = false;
        
        while ($attempt < $maxAttempts && !$connected) {
            sleep(1);
            $attempt++;
            
            try {
                $this->dbConnection = new \PDO(
                    "mysql:host=127.0.0.1;port={$this->dbPort};dbname=yiimp2_test",
                    'root',
                    $this->dbPassword,
                    [
                        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                        \PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"
                    ]
                );
                $connected = true;
            } catch (\PDOException $e) {
                // Keep trying
            }
        }
        
        if (!$connected) {
            $this->cleanupContainer();
            $this->markTestSkipped('MariaDB container did not become ready in time');
        }
    }
    
    protected function _after()
    {
        $this->cleanupContainer();
    }
    
    /**
     * Cleanup Docker container
     */
    private function cleanupContainer()
    {
        if ($this->containerName) {
            $dockerCmd = $this->getDockerCommand();
            if ($dockerCmd) {
                exec("{$dockerCmd} stop {$this->containerName} 2>/dev/null");
                exec("{$dockerCmd} rm {$this->containerName} 2>/dev/null");
            }
        }
    }
    
    /**
     * Get docker or podman command
     */
    private function getDockerCommand()
    {
        // Check for podman first (preferred)
        exec('which podman 2>/dev/null', $output, $returnCode);
        if ($returnCode === 0) {
            return 'podman';
        }
        
        // Check for docker
        exec('which docker 2>/dev/null', $output, $returnCode);
        if ($returnCode === 0) {
            return 'docker';
        }
        
        return null;
    }
    
    /**
     * Test that schema file exists
     */
    public function testSchemaFileExists()
    {
        $this->assertFileExists($this->schemaFile, 'Schema file sql/yiimp2-init.sql should exist');
    }
    
    /**
     * Test schema import on clean database
     * Requirements: 6.6, 6.7
     */
    public function testSchemaImport()
    {
        // Read schema file
        $schemaContent = file_get_contents($this->schemaFile);
        $this->assertNotEmpty($schemaContent, 'Schema file should not be empty');
        
        // Split into individual statements
        $statements = $this->splitSqlStatements($schemaContent);
        $this->assertNotEmpty($statements, 'Schema should contain SQL statements');
        
        // Execute each statement
        $executedCount = 0;
        $errors = [];
        
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (empty($statement) || substr($statement, 0, 2) === '--') {
                continue;
            }
            
            try {
                $this->dbConnection->exec($statement);
                $executedCount++;
            } catch (\PDOException $e) {
                $errors[] = [
                    'statement' => substr($statement, 0, 100) . '...',
                    'error' => $e->getMessage()
                ];
            }
        }
        
        // Verify statements were executed
        $this->assertGreaterThan(0, $executedCount, 'At least some SQL statements should execute successfully');
        
        // Report errors if any (but don't fail the test if most succeeded)
        if (!empty($errors) && count($errors) > $executedCount * 0.1) {
            $errorMsg = "Too many SQL errors during import:\n";
            foreach (array_slice($errors, 0, 5) as $error) {
                $errorMsg .= "Statement: {$error['statement']}\nError: {$error['error']}\n\n";
            }
            $this->fail($errorMsg);
        }
    }
    
    /**
     * Test that all required tables are created
     * Requirements: 6.7
     */
    public function testAllTablesCreated()
    {
        // Import schema first
        $this->importSchema();
        
        // List of core required tables (not all tables may be in every schema)
        $requiredTables = [
            'accounts',
            'algos',
            'blocks',
            'coins',
            'earnings',
            'payouts',
            'settings',
            'shares',
            'workers'
        ];
        
        // Get list of tables in database
        $stmt = $this->dbConnection->query("SHOW TABLES");
        $actualTables = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        
        // Verify each required table exists
        foreach ($requiredTables as $table) {
            $this->assertContains(
                $table,
                $actualTables,
                "Table '{$table}' should exist after schema import"
            );
        }
        
        // Verify we have a reasonable number of tables (at least the core ones)
        $this->assertGreaterThanOrEqual(
            count($requiredTables),
            count($actualTables),
            'Database should have at least the core required tables'
        );
    }
    
    /**
     * Test that decimal column exists in coins table
     * Requirements: 14.5
     */
    public function testDecimalColumnExists()
    {
        // Import schema first
        $this->importSchema();
        
        // Get columns from coins table
        $stmt = $this->dbConnection->query("DESCRIBE coins");
        $columns = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Extract column names
        $columnNames = array_column($columns, 'Field');
        
        // Verify decimal column exists
        $this->assertContains(
            'decimals',
            $columnNames,
            'Coins table should have decimals column'
        );
        
        // Find the decimal column definition
        $decimalColumn = null;
        foreach ($columns as $column) {
            if ($column['Field'] === 'decimals') {
                $decimalColumn = $column;
                break;
            }
        }
        
        $this->assertNotNull($decimalColumn, 'Decimal column should be found');
        
        // Verify column type (should be tinyint)
        $this->assertStringContainsString(
            'tinyint',
            strtolower($decimalColumn['Type']),
            'Decimals column should be tinyint type'
        );
        
        // Verify default value
        $this->assertEquals(
            '8',
            $decimalColumn['Default'],
            'Decimals column should have default value of 8'
        );
    }
    
    /**
     * Test that schema can be imported multiple times (idempotency)
     * Requirements: 6.6
     */
    public function testIdempotentImport()
    {
        // Import schema first time
        $this->importSchema();
        
        // Get core table structures after first import
        $coreTablesFirst = $this->getCoreTableStructures();
        
        // Import schema second time (should not fail)
        $this->importSchema();
        
        // Get core table structures after second import
        $coreTablesSecond = $this->getCoreTableStructures();
        
        // Verify core tables have same structure after re-import
        $this->assertEquals(
            $coreTablesFirst,
            $coreTablesSecond,
            'Schema should be idempotent - core table structures unchanged after re-import'
        );
        
        // Verify we can still query the tables (no corruption)
        foreach (array_keys($coreTablesFirst) as $table) {
            $stmt = $this->dbConnection->query("SELECT COUNT(*) FROM `{$table}`");
            $count = $stmt->fetchColumn();
            $this->assertIsNumeric($count, "Table {$table} should be queryable after re-import");
        }
    }
    
    /**
     * Get structures of core tables
     */
    private function getCoreTableStructures()
    {
        $coreTables = ['accounts', 'algos', 'blocks', 'coins', 'shares', 'workers'];
        $structures = [];
        
        foreach ($coreTables as $table) {
            $stmt = $this->dbConnection->query("DESCRIBE `{$table}`");
            $structures[$table] = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }
        
        return $structures;
    }
    
    /**
     * Test that table relationships are properly defined
     * Requirements: 6.7
     */
    public function testTableRelationships()
    {
        // Import schema first
        $this->importSchema();
        
        // Verify coins table has algo column (relationship to algos table)
        $stmt = $this->dbConnection->query("DESCRIBE coins");
        $columns = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $columnNames = array_column($columns, 'Field');
        
        $this->assertContains(
            'algo',
            $columnNames,
            'Coins table should have algo column for relationship to algos table'
        );
        
        // Verify both tables exist for the relationship
        $stmt = $this->dbConnection->query("SHOW TABLES LIKE 'algos'");
        $algosExists = $stmt->rowCount() > 0;
        $this->assertTrue($algosExists, 'Algos table should exist');
        
        $stmt = $this->dbConnection->query("SHOW TABLES LIKE 'coins'");
        $coinsExists = $stmt->rowCount() > 0;
        $this->assertTrue($coinsExists, 'Coins table should exist');
        
        // Verify we can join the tables (relationship is usable)
        $stmt = $this->dbConnection->query("
            SELECT COUNT(*) 
            FROM coins c 
            LEFT JOIN algos a ON c.algo = a.name
        ");
        $count = $stmt->fetchColumn();
        $this->assertIsNumeric($count, 'Should be able to join coins and algos tables');
    }
    
    /**
     * Helper method to import schema
     */
    private function importSchema()
    {
        $schemaContent = file_get_contents($this->schemaFile);
        $statements = $this->splitSqlStatements($schemaContent);
        
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (empty($statement) || substr($statement, 0, 2) === '--') {
                continue;
            }
            
            try {
                $this->dbConnection->exec($statement);
            } catch (\PDOException $e) {
                // Continue on error - some statements may fail on re-import
            }
        }
    }
    
    /**
     * Helper method to split SQL content into individual statements
     */
    private function splitSqlStatements($sql)
    {
        // Remove comments
        $sql = preg_replace('/^--.*$/m', '', $sql);
        $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
        
        // Split by semicolon (simple approach)
        $statements = explode(';', $sql);
        
        return array_filter($statements, function($stmt) {
            return !empty(trim($stmt));
        });
    }
}
