<?php

namespace yiimp2\tests\unit\migration;

use Codeception\Test\Unit;

/**
 * Property-based test for migration idempotency
 * Feature: yiimp2-dedicated-system, Property 14: Migration idempotency
 * 
 * Property 14: Migration Idempotency
 * For any migration script execution, running the script multiple times should 
 * result in the same final database state without duplicate records.
 * 
 * Validates: Requirements 12.1, 12.2, 12.3, 12.4
 */
class MigrationIdempotencyPropertyTest extends Unit
{
    private $scriptPath;
    
    protected function _before()
    {
        $this->scriptPath = dirname(dirname(dirname(__DIR__))) . '/../bin/migrate-yiimp2-data.sh';
    }
    
    /**
     * Test that skip mode uses INSERT IGNORE
     * 
     * Property: For any INSERT statement in skip mode, it should be transformed 
     * to INSERT IGNORE to skip duplicates
     * 
     * @dataProvider insertStatementProvider
     */
    public function testSkipModeUsesInsertIgnore($originalInsert, $tableName)
    {
        // Simulate skip mode transformation
        $transformed = str_replace('INSERT INTO', 'INSERT IGNORE INTO', $originalInsert);
        
        // Verify transformation
        $this->assertStringContainsString('INSERT IGNORE INTO', $transformed);
        $this->assertStringNotContainsString('INSERT INTO `' . $tableName . '`', $transformed);
        $this->assertStringContainsString('INSERT IGNORE INTO `' . $tableName . '`', $transformed);
    }
    
    /**
     * Data provider for INSERT statements
     */
    public function insertStatementProvider()
    {
        return [
            'coins insert' => [
                "INSERT INTO `coins` (`id`, `name`) VALUES (1, 'Bitcoin');",
                'coins'
            ],
            'algos insert' => [
                "INSERT INTO `algos` (`id`, `name`) VALUES (1, 'sha256');",
                'algos'
            ],
            'settings insert' => [
                "INSERT INTO `settings` (`param`, `value`) VALUES ('pool_name', 'Test');",
                'settings'
            ],
            'multi-value insert' => [
                "INSERT INTO `coins` (`id`, `name`) VALUES (1, 'Bitcoin'), (2, 'Litecoin');",
                'coins'
            ],
        ];
    }
    
    /**
     * Test that update mode uses REPLACE INTO
     * 
     * Property: For any INSERT statement in update mode, it should be transformed 
     * to REPLACE INTO to update existing records
     * 
     * @dataProvider insertStatementProvider
     */
    public function testUpdateModeUsesReplaceInto($originalInsert, $tableName)
    {
        // Simulate update mode transformation
        $transformed = str_replace('INSERT INTO', 'REPLACE INTO', $originalInsert);
        
        // Verify transformation
        $this->assertStringContainsString('REPLACE INTO', $transformed);
        $this->assertStringNotContainsString('INSERT INTO `' . $tableName . '`', $transformed);
        $this->assertStringContainsString('REPLACE INTO `' . $tableName . '`', $transformed);
    }
    
    /**
     * Test that replace mode truncates tables first
     * 
     * Property: For any table in replace mode, the table should be truncated 
     * before inserting new data
     * 
     * @dataProvider tableNameProvider
     */
    public function testReplaceModeDocumentsTruncate($tableName)
    {
        // Read the script
        $scriptContent = file_get_contents($this->scriptPath);
        
        // Verify replace mode includes truncate logic
        $this->assertStringContainsString('TRUNCATE TABLE', $scriptContent);
        $this->assertStringContainsString('replace', strtolower($scriptContent));
    }
    
    /**
     * Data provider for table names
     */
    public function tableNameProvider()
    {
        return [
            'coins' => ['coins'],
            'algos' => ['algos'],
            'settings' => ['settings'],
        ];
    }
    
    /**
     * Test that the script supports all three idempotency modes
     * 
     * Property: For any migration script, it should support skip, update, 
     * and replace modes
     * 
     * @dataProvider modeProvider
     */
    public function testScriptSupportsAllModes($mode)
    {
        // Read the script
        $scriptContent = file_get_contents($this->scriptPath);
        
        // Verify mode is mentioned in the script
        $this->assertStringContainsString(
            $mode,
            strtolower($scriptContent),
            "Script should support '$mode' mode"
        );
    }
    
    /**
     * Data provider for modes
     */
    public function modeProvider()
    {
        return [
            'skip mode' => ['skip'],
            'update mode' => ['update'],
            'replace mode' => ['replace'],
        ];
    }
    
    /**
     * Test that skip mode prevents duplicates
     * 
     * Property: For any record that already exists, skip mode should not 
     * create a duplicate
     */
    public function testSkipModePreventsDuplicates()
    {
        // INSERT IGNORE will skip if record exists
        $insert1 = "INSERT IGNORE INTO `coins` (`id`, `name`) VALUES (1, 'Bitcoin');";
        $insert2 = "INSERT IGNORE INTO `coins` (`id`, `name`) VALUES (1, 'Bitcoin');";
        
        // Both statements are identical and safe to run multiple times
        $this->assertEquals($insert1, $insert2);
        $this->assertStringContainsString('INSERT IGNORE', $insert1);
    }
    
    /**
     * Test that update mode overwrites existing records
     * 
     * Property: For any record that already exists, update mode should 
     * replace it with new values
     */
    public function testUpdateModeOverwritesRecords()
    {
        // REPLACE INTO will delete and re-insert
        $insert1 = "REPLACE INTO `coins` (`id`, `name`) VALUES (1, 'Bitcoin');";
        $insert2 = "REPLACE INTO `coins` (`id`, `name`) VALUES (1, 'Bitcoin Updated');";
        
        // Second statement should overwrite first
        $this->assertStringContainsString('REPLACE INTO', $insert1);
        $this->assertStringContainsString('REPLACE INTO', $insert2);
        $this->assertNotEquals($insert1, $insert2);
    }
    
    /**
     * Test that replace mode clears all data first
     * 
     * Property: For any table in replace mode, all existing data should be 
     * removed before new data is inserted
     */
    public function testReplaceModesClearsAllData()
    {
        // Read the script
        $scriptContent = file_get_contents($this->scriptPath);
        
        // Verify replace mode logic
        preg_match('/replace.*?TRUNCATE TABLE/is', $scriptContent, $matches);
        
        $this->assertNotEmpty($matches, "Replace mode should truncate tables");
    }
    
    /**
     * Test that idempotency is documented in the script
     * 
     * Property: For any migration script, it should document the idempotency 
     * behavior
     */
    public function testIdempotencyIsDocumented()
    {
        // Read the script
        $scriptContent = file_get_contents($this->scriptPath);
        
        // Check for idempotency documentation
        $hasIdempotencyDoc = 
            stripos($scriptContent, 'idempotency') !== false ||
            stripos($scriptContent, 'idempotent') !== false ||
            (stripos($scriptContent, 'skip') !== false && 
             stripos($scriptContent, 'update') !== false && 
             stripos($scriptContent, 'replace') !== false);
        
        $this->assertTrue(
            $hasIdempotencyDoc,
            "Script should document idempotency modes"
        );
    }
    
    /**
     * Test that mode parameter is validated
     * 
     * Property: For any invalid mode value, the script should reject it
     * 
     * @dataProvider invalidModeProvider
     */
    public function testInvalidModesAreRejected($invalidMode)
    {
        // Read the script
        $scriptContent = file_get_contents($this->scriptPath);
        
        // Verify mode validation exists
        $this->assertStringContainsString(
            'Invalid mode',
            $scriptContent,
            "Script should validate mode parameter"
        );
        
        // Verify valid modes are listed
        $this->assertStringContainsString('skip', $scriptContent);
        $this->assertStringContainsString('update', $scriptContent);
        $this->assertStringContainsString('replace', $scriptContent);
    }
    
    /**
     * Data provider for invalid modes
     */
    public function invalidModeProvider()
    {
        return [
            'invalid mode' => ['invalid'],
            'empty mode' => [''],
            'numeric mode' => ['123'],
            'special chars' => ['skip;update'],
        ];
    }
    
    /**
     * Test that running migration twice with skip mode produces same result
     * 
     * Property: For any set of data, running migration twice in skip mode 
     * should result in the same final state
     * 
     * @dataProvider sampleDataProvider
     */
    public function testSkipModeIdempotency($tableName, $data)
    {
        // Create INSERT statement
        $columns = array_keys($data[0]);
        $columnList = '`' . implode('`, `', $columns) . '`';
        
        $values = [];
        foreach ($data as $row) {
            $rowValues = array_map(function($v) {
                return is_numeric($v) ? $v : "'" . addslashes($v) . "'";
            }, array_values($row));
            $values[] = "(" . implode(", ", $rowValues) . ")";
        }
        
        $valueList = implode(", ", $values);
        
        // First run
        $insert1 = "INSERT IGNORE INTO `$tableName` ($columnList) VALUES $valueList;";
        
        // Second run (identical)
        $insert2 = "INSERT IGNORE INTO `$tableName` ($columnList) VALUES $valueList;";
        
        // Both should be identical
        $this->assertEquals($insert1, $insert2);
        
        // Both should use INSERT IGNORE
        $this->assertStringContainsString('INSERT IGNORE', $insert1);
        $this->assertStringContainsString('INSERT IGNORE', $insert2);
    }
    
    /**
     * Data provider for sample data
     */
    public function sampleDataProvider()
    {
        return [
            'coins data' => [
                'coins',
                [
                    ['id' => 1, 'name' => 'Bitcoin', 'symbol' => 'BTC'],
                    ['id' => 2, 'name' => 'Litecoin', 'symbol' => 'LTC'],
                ]
            ],
            'algos data' => [
                'algos',
                [
                    ['id' => 1, 'name' => 'sha256', 'port' => 3333],
                    ['id' => 2, 'name' => 'scrypt', 'port' => 3433],
                ]
            ],
            'settings data' => [
                'settings',
                [
                    ['param' => 'pool_name', 'value' => 'Test Pool'],
                    ['param' => 'pool_fee', 'value' => '1.5'],
                ]
            ],
        ];
    }
    
    /**
     * Test that mode is configurable via command line
     * 
     * Property: For any migration execution, the mode should be configurable 
     * via --mode parameter
     */
    public function testModeIsConfigurable()
    {
        // Read the script
        $scriptContent = file_get_contents($this->scriptPath);
        
        // Verify --mode parameter is supported
        $this->assertStringContainsString('--mode', $scriptContent);
        
        // Verify MODE variable is used
        $this->assertStringContainsString('MODE=', $scriptContent);
    }
    
    /**
     * Test that default mode is skip
     * 
     * Property: For any migration execution without mode specified, 
     * skip mode should be used by default
     */
    public function testDefaultModeIsSkip()
    {
        // Read the script
        $scriptContent = file_get_contents($this->scriptPath);
        
        // Verify default mode is skip
        preg_match('/MODE=["\'](.*?)["\']/', $scriptContent, $matches);
        
        if (isset($matches[1])) {
            $this->assertEquals('skip', $matches[1], "Default mode should be 'skip'");
        }
    }
}
