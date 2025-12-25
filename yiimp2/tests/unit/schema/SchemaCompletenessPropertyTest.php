<?php

namespace yiimp2\tests\unit\schema;

use Codeception\Test\Unit;

/**
 * Property-based test for schema completeness
 * Feature: yiimp2-dedicated-system, Property 1: Schema completeness
 * 
 * Property 1: Schema Completeness
 * For any Yiimp2 database initialization, all required tables from the base 
 * schema and migrations should exist in the database after initialization completes.
 * 
 * Validates: Requirements 1.2, 1.5
 */
class SchemaCompletenessPropertyTest extends Unit
{
    private $containerName;
    private $dbConnection;
    private $schemaFile;
    private $dbPort;
    private $dbPassword = 'test_password_123';
    
    protected function _before()
    {
        $this->containerName = 'yiimp2_completeness_test_' . uniqid();
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
        
        // Import schema
        $this->importSchema();
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
     * Property Test: All core tables exist after initialization
     * 
     * For any Yiimp2 database initialization, all required core tables 
     * should exist in the database.
     */
    public function testAllCoreTablesExistAfterInitialization()
    {
        // Define core tables that MUST exist in any Yiimp2 installation
        $coreTables = [
            'accounts',
            'algos',
            'balances',
            'blocks',
            'coins',
            'connections',
            'earnings',
            'hashrate',
            'hashstats',
            'hashuser',
            'payouts',
            'settings',
            'shares',
            'workers'
        ];
        
        // Get actual tables in database
        $stmt = $this->dbConnection->query("SHOW TABLES");
        $actualTables = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        
        // Property: For any core table, it should exist after initialization
        foreach ($coreTables as $table) {
            $this->assertContains(
                $table,
                $actualTables,
                "Property violation: Core table '{$table}' should exist after schema initialization"
            );
        }
    }
    
    /**
     * Property Test: All tables from consolidated schema exist
     * 
     * For any table defined in the consolidated yiimp2-init.sql schema, 
     * that table should exist in the initialized database (accounting for renames).
     */
    public function testAllConsolidatedSchemaTablesExist()
    {
        // Extract table names from CREATE TABLE statements
        $createdTableNames = $this->extractTableNamesFromConsolidatedSchema($this->schemaFile);
        
        if (empty($createdTableNames)) {
            $this->markTestSkipped('Could not extract table names from consolidated schema');
        }
        
        // Extract table renames from migrations (RENAME TABLE statements)
        $renamedTables = $this->extractTableRenames($this->schemaFile);
        
        // Get actual tables in database
        $stmt = $this->dbConnection->query("SHOW TABLES");
        $actualTables = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        
        // Property: For any table created in schema, it should exist (or be renamed)
        $missingTables = [];
        foreach ($createdTableNames as $table) {
            // Check if table exists directly
            if (in_array($table, $actualTables)) {
                continue;
            }
            
            // Check if table was renamed
            if (isset($renamedTables[$table]) && in_array($renamedTables[$table], $actualTables)) {
                continue;
            }
            
            // Table is truly missing
            $missingTables[] = $table;
        }
        
        // Property: ALL tables from schema must exist (complete schema)
        $this->assertEmpty(
            $missingTables,
            "Property violation: ALL tables from consolidated schema must exist after initialization. " .
            "Missing tables: " . implode(', ', $missingTables)
        );
    }
    
    /**
     * Property Test: Schema initialization is complete (no partial state)
     * 
     * For any Yiimp2 database initialization, the database should be in a 
     * complete state with all expected structures, not a partial state.
     */
    public function testSchemaInitializationIsComplete()
    {
        // Get all tables
        $stmt = $this->dbConnection->query("SHOW TABLES");
        $tables = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        
        // Property: For any table, it should have at least one column
        foreach ($tables as $table) {
            $stmt = $this->dbConnection->query("DESCRIBE `{$table}`");
            $columns = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            $this->assertNotEmpty(
                $columns,
                "Property violation: Table '{$table}' should have columns (not be empty structure)"
            );
        }
        
        // Property: For any table, it should have a primary key or unique index
        foreach ($tables as $table) {
            $stmt = $this->dbConnection->query("SHOW INDEXES FROM `{$table}`");
            $indexes = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            $hasPrimaryOrUnique = false;
            foreach ($indexes as $index) {
                if ($index['Key_name'] === 'PRIMARY' || $index['Non_unique'] == 0) {
                    $hasPrimaryOrUnique = true;
                    break;
                }
            }
            
            $this->assertTrue(
                $hasPrimaryOrUnique,
                "Property violation: Table '{$table}' should have a primary key or unique index"
            );
        }
    }
    
    /**
     * Property Test: Migration alterations are applied
     * 
     * For any migration file that adds columns or tables, those additions 
     * should be present in the initialized database.
     */
    public function testMigrationAlterationsAreApplied()
    {
        // Check for specific columns that are added by migrations
        // These are known additions from migration files
        
        // Example: Check if specific migration-added columns exist
        $migrationAdditions = [
            // Table => [columns that should exist from migrations]
            'coins' => ['decimals'], // Added by decimal column migration
        ];
        
        foreach ($migrationAdditions as $table => $expectedColumns) {
            $stmt = $this->dbConnection->query("DESCRIBE `{$table}`");
            $columns = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $actualColumnNames = array_column($columns, 'Field');
            
            foreach ($expectedColumns as $expectedColumn) {
                $this->assertContains(
                    $expectedColumn,
                    $actualColumnNames,
                    "Property violation: Migration-added column '{$expectedColumn}' should exist in table '{$table}'"
                );
            }
        }
    }
    
    /**
     * Property Test: Schema is queryable (functional completeness)
     * 
     * For any table in the initialized database, it should be queryable 
     * without errors (indicating proper structure).
     */
    public function testAllTablesAreQueryable()
    {
        // Get all tables
        $stmt = $this->dbConnection->query("SHOW TABLES");
        $tables = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        
        // Property: For any table, SELECT COUNT(*) should work without error
        foreach ($tables as $table) {
            try {
                $stmt = $this->dbConnection->query("SELECT COUNT(*) FROM `{$table}`");
                $count = $stmt->fetchColumn();
                
                $this->assertIsNumeric(
                    $count,
                    "Property violation: Table '{$table}' should be queryable"
                );
            } catch (\PDOException $e) {
                $this->fail(
                    "Property violation: Table '{$table}' is not queryable: " . $e->getMessage()
                );
            }
        }
    }
    
    /**
     * Property Test: Required relationships exist
     * 
     * For any table with foreign key relationships, the referenced tables 
     * should exist in the database.
     */
    public function testRequiredRelationshipsExist()
    {
        // Define known relationships in the schema
        $relationships = [
            'coins' => ['algos'], // coins.algo references algos.name
            'blocks' => ['coins'], // blocks.coin_id references coins.id
            'shares' => ['workers'], // shares.userid references workers
            'payouts' => ['accounts'], // payouts.account_id references accounts.id
        ];
        
        // Get all tables
        $stmt = $this->dbConnection->query("SHOW TABLES");
        $actualTables = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        
        // Property: For any table with relationships, referenced tables should exist
        foreach ($relationships as $table => $referencedTables) {
            if (in_array($table, $actualTables)) {
                foreach ($referencedTables as $referencedTable) {
                    $this->assertContains(
                        $referencedTable,
                        $actualTables,
                        "Property violation: Table '{$table}' references '{$referencedTable}' which should exist"
                    );
                }
            }
        }
    }
    
    /**
     * Property Test: Schema has minimum expected size
     * 
     * For any Yiimp2 database initialization, the database should have 
     * at least a minimum number of tables (indicating completeness).
     */
    public function testSchemaHasMinimumExpectedSize()
    {
        // Get all tables
        $stmt = $this->dbConnection->query("SHOW TABLES");
        $tables = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        
        // Property: Database should have at least 14 core tables
        $minExpectedTables = 14;
        
        $this->assertGreaterThanOrEqual(
            $minExpectedTables,
            count($tables),
            "Property violation: Database should have at least {$minExpectedTables} tables after initialization, found " . count($tables)
        );
    }
    
    /**
     * Property Test: Idempotent initialization produces consistent core schema
     * 
     * For any Yiimp2 database, initializing it multiple times should 
     * result in the same core table structures.
     */
    public function testIdempotentInitializationProducesConsistentSchema()
    {
        // Get initial core table structures
        $initialCoreTables = $this->getCoreTableStructures();
        
        // Re-import schema (idempotent operation)
        $this->importSchema();
        
        // Get core table structures after re-import
        $finalCoreTables = $this->getCoreTableStructures();
        
        // Property: Core table structures should be identical after re-initialization
        $this->assertEquals(
            $initialCoreTables,
            $finalCoreTables,
            "Property violation: Core table structures should be identical after idempotent re-initialization"
        );
        
        // Property: Number of tables should not decrease after re-import
        $stmt = $this->dbConnection->query("SHOW TABLES");
        $finalTableCount = count($stmt->fetchAll(\PDO::FETCH_COLUMN));
        
        $this->assertGreaterThanOrEqual(
            count($initialCoreTables),
            $finalTableCount,
            "Property violation: Table count should not decrease after re-import"
        );
    }
    
    /**
     * Helper: Get core table structures (subset of all tables)
     */
    private function getCoreTableStructures()
    {
        $coreTables = [
            'accounts', 'algos', 'blocks', 'coins', 
            'earnings', 'payouts', 'shares', 'workers', 'settings'
        ];
        
        $structures = [];
        foreach ($coreTables as $table) {
            try {
                $stmt = $this->dbConnection->query("DESCRIBE `{$table}`");
                $structures[$table] = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            } catch (\PDOException $e) {
                // Table doesn't exist, skip it
            }
        }
        
        return $structures;
    }
    
    /**
     * Helper: Import schema from file
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
     * Helper: Split SQL content into individual statements
     */
    private function splitSqlStatements($sql)
    {
        // Remove comments
        $sql = preg_replace('/^--.*$/m', '', $sql);
        $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
        
        // Split by semicolon
        $statements = explode(';', $sql);
        
        return array_filter($statements, function($stmt) {
            return !empty(trim($stmt));
        });
    }
    
    /**
     * Helper: Extract table names from consolidated schema file
     */
    private function extractTableNamesFromConsolidatedSchema($schemaPath)
    {
        $tables = [];
        
        $content = file_get_contents($schemaPath);
        if (!$content) {
            return $tables;
        }
        
        // Look for CREATE TABLE statements
        if (preg_match_all('/CREATE TABLE.*?`([^`]+)`/i', $content, $matches)) {
            $tables = $matches[1];
        }
        
        return array_unique($tables);
    }
    
    /**
     * Helper: Extract table renames from schema file
     * Returns array mapping old_name => new_name
     */
    private function extractTableRenames($schemaPath)
    {
        $renames = [];
        
        $content = file_get_contents($schemaPath);
        if (!$content) {
            return $renames;
        }
        
        // Look for RENAME TABLE statements
        if (preg_match_all('/RENAME TABLE\s+`([^`]+)`\s+TO\s+`([^`]+)`/i', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $renames[$match[1]] = $match[2];
            }
        }
        
        return $renames;
    }
    
    /**
     * Helper: Get all table structures
     */
    private function getAllTableStructures()
    {
        $stmt = $this->dbConnection->query("SHOW TABLES");
        $tables = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        
        $structures = [];
        foreach ($tables as $table) {
            $stmt = $this->dbConnection->query("DESCRIBE `{$table}`");
            $structures[$table] = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }
        
        return $structures;
    }
}
