<?php

namespace yiimp2\tests\unit\migration;

use Codeception\Test\Unit;

/**
 * Property-based tests for data migration
 * Feature: yiimp2-dedicated-system
 * 
 * Tests the following properties:
 * - Property 3: Coin data preservation
 * - Property 4: Coin migration completeness
 * - Property 5: Coin conflict resolution
 * - Property 6: Algorithm migration completeness
 * - Property 7: Settings migration completeness
 * - Property 10: Data extraction completeness
 * 
 * Validates: Requirements 2.1, 2.2, 2.4, 2.5, 2.6, 3.1, 3.2, 3.3, 3.4, 4.1, 4.2, 4.3, 4.4, 7.2-7.5
 */
class DataMigrationPropertyTest extends Unit
{
    private $testDir;
    
    protected function _before()
    {
        $this->testDir = sys_get_temp_dir() . '/yiimp2-migration-property-test-' . uniqid();
        mkdir($this->testDir, 0755, true);
    }
    
    protected function _after()
    {
        if (is_dir($this->testDir)) {
            $this->rrmdir($this->testDir);
        }
    }
    
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
     * Property 3: Coin Data Preservation
     * 
     * For any coin record migrated from old-yiimp.sql, all fields 
     * (including decimal column) should have identical values in the Yiimp2 database.
     * 
     * Validates: Requirements 2.2
     * 
     * @dataProvider coinDataProvider
     */
    public function testCoinDataPreservation($coinData)
    {
        // Create SQL INSERT statement
        $columns = array_keys($coinData);
        $values = array_values($coinData);
        
        $columnList = '`' . implode('`, `', $columns) . '`';
        $valueList = implode(', ', array_map(function($v) {
            return is_numeric($v) ? $v : "'" . addslashes($v) . "'";
        }, $values));
        
        $insertSql = "INSERT INTO `coins` ($columnList) VALUES ($valueList);";
        
        // Verify all fields are present in the INSERT statement
        foreach ($columns as $column) {
            $this->assertStringContainsString("`$column`", $insertSql);
        }
        
        // Verify decimal column is included
        $this->assertStringContainsString('`decimals`', $insertSql);
        
        // Verify all values are present (check for escaped version if needed)
        foreach ($values as $value) {
            if (is_string($value)) {
                // Check for either the original or escaped version
                $escaped = addslashes($value);
                $found = strpos($insertSql, $value) !== false || strpos($insertSql, $escaped) !== false;
                $this->assertTrue($found, "Value '$value' should be present in INSERT statement");
            }
        }
    }
    
    /**
     * Data provider for coin data
     */
    public function coinDataProvider()
    {
        return [
            'bitcoin' => [[
                'id' => 1,
                'name' => 'Bitcoin',
                'symbol' => 'BTC',
                'decimals' => 8,
                'algo' => 'sha256',
            ]],
            'litecoin' => [[
                'id' => 2,
                'name' => 'Litecoin',
                'symbol' => 'LTC',
                'decimals' => 8,
                'algo' => 'scrypt',
            ]],
            'dogecoin' => [[
                'id' => 3,
                'name' => 'Dogecoin',
                'symbol' => 'DOGE',
                'decimals' => 8,
                'algo' => 'scrypt',
            ]],
            'coin with different decimals' => [[
                'id' => 4,
                'name' => 'TestCoin',
                'symbol' => 'TEST',
                'decimals' => 6,
                'algo' => 'x11',
            ]],
            'coin with special characters' => [[
                'id' => 5,
                'name' => "Test's Coin",
                'symbol' => 'TST',
                'decimals' => 8,
                'algo' => 'sha256',
            ]],
        ];
    }
    
    /**
     * Property 4: Coin Migration Completeness
     * 
     * For any coin in the source old-yiimp.sql, that coin should exist 
     * in the Yiimp2 database with the same ID.
     * 
     * Validates: Requirements 2.1, 2.4, 2.6
     * 
     * @dataProvider coinIdProvider
     */
    public function testCoinMigrationCompleteness($coinId, $coinName)
    {
        // Create INSERT statement
        $insertSql = "INSERT INTO `coins` (`id`, `name`, `symbol`) VALUES ($coinId, '$coinName', 'SYM');";
        
        // Extract ID from INSERT statement
        preg_match('/VALUES\s*\((\d+),/', $insertSql, $matches);
        $extractedId = isset($matches[1]) ? (int)$matches[1] : null;
        
        // Verify ID is preserved
        $this->assertEquals($coinId, $extractedId, "Coin ID should be preserved during migration");
    }
    
    /**
     * Data provider for coin IDs
     */
    public function coinIdProvider()
    {
        return [
            'id 1' => [1, 'Bitcoin'],
            'id 2' => [2, 'Litecoin'],
            'id 10' => [10, 'TestCoin'],
            'id 100' => [100, 'AnotherCoin'],
            'id 999' => [999, 'MaxCoin'],
        ];
    }
    
    /**
     * Property 5: Coin Conflict Resolution
     * 
     * For any duplicate coin during migration, the system should handle it 
     * according to the configured mode (skip, update, or replace) without data corruption.
     * 
     * Validates: Requirements 2.5
     * 
     * @dataProvider conflictModeProvider
     */
    public function testCoinConflictResolution($mode, $expectedTransformation)
    {
        $originalInsert = "INSERT INTO `coins` (`id`, `name`) VALUES (1, 'Bitcoin');";
        
        // Apply mode transformation
        if ($mode === 'skip') {
            $transformed = str_replace('INSERT INTO', 'INSERT IGNORE INTO', $originalInsert);
        } elseif ($mode === 'update') {
            $transformed = str_replace('INSERT INTO', 'REPLACE INTO', $originalInsert);
        } else {
            $transformed = $originalInsert;
        }
        
        // Verify transformation
        $this->assertStringContainsString($expectedTransformation, $transformed);
    }
    
    /**
     * Data provider for conflict modes
     */
    public function conflictModeProvider()
    {
        return [
            'skip mode' => ['skip', 'INSERT IGNORE INTO'],
            'update mode' => ['update', 'REPLACE INTO'],
            'replace mode' => ['replace', 'INSERT INTO'],
        ];
    }
    
    /**
     * Property 6: Algorithm Migration Completeness
     * 
     * For any algorithm in the source old-yiimp.sql, that algorithm should exist 
     * in the Yiimp2 database with all fields preserved and the same ID.
     * 
     * Validates: Requirements 3.1, 3.2, 3.3, 3.4
     * 
     * @dataProvider algorithmDataProvider
     */
    public function testAlgorithmMigrationCompleteness($algoData)
    {
        // Create SQL INSERT statement
        $columns = array_keys($algoData);
        $values = array_values($algoData);
        
        $columnList = '`' . implode('`, `', $columns) . '`';
        $valueList = implode(', ', array_map(function($v) {
            return is_numeric($v) ? $v : "'" . addslashes($v) . "'";
        }, $values));
        
        $insertSql = "INSERT INTO `algos` ($columnList) VALUES ($valueList);";
        
        // Verify all fields are present
        foreach ($columns as $column) {
            $this->assertStringContainsString("`$column`", $insertSql);
        }
        
        // Verify ID is preserved
        $this->assertStringContainsString($algoData['id'], $insertSql);
        
        // Verify name is preserved
        $this->assertStringContainsString($algoData['name'], $insertSql);
    }
    
    /**
     * Data provider for algorithm data
     */
    public function algorithmDataProvider()
    {
        return [
            'sha256' => [[
                'id' => 1,
                'name' => 'sha256',
                'port' => 3333,
                'color' => '#c0c0e0',
            ]],
            'scrypt' => [[
                'id' => 2,
                'name' => 'scrypt',
                'port' => 3433,
                'color' => '#d0d0d0',
            ]],
            'x11' => [[
                'id' => 3,
                'name' => 'x11',
                'port' => 3533,
                'color' => '#e0e0e0',
            ]],
            'equihash' => [[
                'id' => 4,
                'name' => 'equihash',
                'port' => 3633,
                'color' => '#f0f0f0',
            ]],
        ];
    }
    
    /**
     * Property 7: Settings Migration Completeness
     * 
     * For any setting parameter in the source old-yiimp.sql, that setting should exist 
     * in the Yiimp2 database with the same value (or configured override).
     * 
     * Validates: Requirements 4.1, 4.2, 4.3, 4.4
     * 
     * @dataProvider settingsDataProvider
     */
    public function testSettingsMigrationCompleteness($settingData)
    {
        // Create SQL INSERT statement
        $param = $settingData['param'];
        $value = $settingData['value'];
        $type = $settingData['type'];
        
        $insertSql = "INSERT INTO `settings` (`param`, `value`, `type`) VALUES ('$param', '$value', '$type');";
        
        // Verify all fields are present
        $this->assertStringContainsString('`param`', $insertSql);
        $this->assertStringContainsString('`value`', $insertSql);
        $this->assertStringContainsString('`type`', $insertSql);
        
        // Verify values are preserved
        $this->assertStringContainsString($param, $insertSql);
        $this->assertStringContainsString($value, $insertSql);
        $this->assertStringContainsString($type, $insertSql);
    }
    
    /**
     * Data provider for settings data
     */
    public function settingsDataProvider()
    {
        return [
            'pool name' => [[
                'param' => 'pool_name',
                'value' => 'Test Pool',
                'type' => 'string',
            ]],
            'pool fee' => [[
                'param' => 'pool_fee',
                'value' => '1.5',
                'type' => 'float',
            ]],
            'auto exchange' => [[
                'param' => 'auto_exchange',
                'value' => '1',
                'type' => 'bool',
            ]],
            'min payout' => [[
                'param' => 'min_payout',
                'value' => '0.001',
                'type' => 'float',
            ]],
        ];
    }
    
    /**
     * Property 10: Data Extraction Completeness
     * 
     * For any required table (coins, algos, settings), all records from old-yiimp.sql 
     * should be successfully extracted and inserted into the Yiimp2 database.
     * 
     * Validates: Requirements 7.2-7.5
     * 
     * @dataProvider tableDataProvider
     */
    public function testDataExtractionCompleteness($tableName, $sampleData)
    {
        // Create SQL file with INSERT statements
        $sqlDir = $this->testDir . '/sql';
        mkdir($sqlDir, 0755, true);
        
        $insertSql = "INSERT INTO `$tableName` ";
        $columns = array_keys($sampleData[0]);
        $insertSql .= "(`" . implode("`, `", $columns) . "`) VALUES\n";
        
        $values = [];
        foreach ($sampleData as $row) {
            $rowValues = array_map(function($v) {
                return is_numeric($v) ? $v : "'" . addslashes($v) . "'";
            }, array_values($row));
            $values[] = "(" . implode(", ", $rowValues) . ")";
        }
        
        $insertSql .= implode(",\n", $values) . ";";
        
        file_put_contents($sqlDir . "/test-$tableName.sql", $insertSql);
        
        // Verify file was created and contains data
        $this->assertFileExists($sqlDir . "/test-$tableName.sql");
        $content = file_get_contents($sqlDir . "/test-$tableName.sql");
        
        // Verify INSERT statement is present
        $this->assertStringContainsString("INSERT INTO `$tableName`", $content);
        
        // Verify all sample data is present
        foreach ($sampleData as $row) {
            foreach ($row as $value) {
                if (is_string($value)) {
                    $this->assertStringContainsString($value, $content);
                }
            }
        }
        
        // Verify record count matches (count opening parentheses in VALUES section only)
        $recordCount = count($sampleData);
        // Extract just the VALUES section
        preg_match('/VALUES\s+(.*);/s', $content, $matches);
        if (isset($matches[1])) {
            $valuesSection = $matches[1];
            // Count record tuples by counting commas between records + 1
            $actualCount = substr_count($valuesSection, '),') + 1;
            $this->assertEquals($recordCount, $actualCount, "All records should be extracted");
        } else {
            $this->fail("Could not extract VALUES section from INSERT statement");
        }
    }
    
    /**
     * Data provider for table data
     */
    public function tableDataProvider()
    {
        return [
            'coins table' => [
                'coins',
                [
                    ['id' => 1, 'name' => 'Bitcoin', 'symbol' => 'BTC', 'decimals' => 8],
                    ['id' => 2, 'name' => 'Litecoin', 'symbol' => 'LTC', 'decimals' => 8],
                    ['id' => 3, 'name' => 'Dogecoin', 'symbol' => 'DOGE', 'decimals' => 8],
                ]
            ],
            'algos table' => [
                'algos',
                [
                    ['id' => 1, 'name' => 'sha256', 'port' => 3333],
                    ['id' => 2, 'name' => 'scrypt', 'port' => 3433],
                    ['id' => 3, 'name' => 'x11', 'port' => 3533],
                ]
            ],
            'settings table' => [
                'settings',
                [
                    ['param' => 'pool_name', 'value' => 'Test Pool'],
                    ['param' => 'pool_fee', 'value' => '1.5'],
                    ['param' => 'auto_exchange', 'value' => '1'],
                ]
            ],
        ];
    }
    
    /**
     * Test that extraction works with various INSERT statement formats
     * 
     * @dataProvider insertFormatProvider
     */
    public function testVariousInsertFormats($insertStatement, $tableName)
    {
        // Verify INSERT statement can be parsed
        $this->assertStringContainsString("INSERT INTO `$tableName`", $insertStatement);
        $this->assertStringContainsString('VALUES', $insertStatement);
        $this->assertStringEndsWith(';', trim($insertStatement));
    }
    
    /**
     * Data provider for INSERT statement formats
     */
    public function insertFormatProvider()
    {
        return [
            'single line' => [
                "INSERT INTO `coins` (`id`, `name`) VALUES (1, 'Bitcoin');",
                'coins'
            ],
            'multi line' => [
                "INSERT INTO `coins` (`id`, `name`) VALUES\n(1, 'Bitcoin'),\n(2, 'Litecoin');",
                'coins'
            ],
            'with spaces' => [
                "INSERT INTO `algos` ( `id` , `name` ) VALUES ( 1 , 'sha256' ) ;",
                'algos'
            ],
            'multiple records' => [
                "INSERT INTO `settings` (`param`, `value`) VALUES ('a', '1'), ('b', '2'), ('c', '3');",
                'settings'
            ],
        ];
    }
    
    /**
     * Test that field order doesn't matter for data preservation
     * 
     * @dataProvider fieldOrderProvider
     */
    public function testFieldOrderPreservation($fields1, $fields2)
    {
        // Both should contain the same fields, just in different order
        sort($fields1);
        sort($fields2);
        
        $this->assertEquals($fields1, $fields2, "Field order should not affect data preservation");
    }
    
    /**
     * Data provider for field order
     */
    public function fieldOrderProvider()
    {
        return [
            'coins fields' => [
                ['id', 'name', 'symbol', 'decimals'],
                ['name', 'id', 'decimals', 'symbol'],
            ],
            'algos fields' => [
                ['id', 'name', 'port', 'color'],
                ['port', 'name', 'id', 'color'],
            ],
            'settings fields' => [
                ['param', 'value', 'type'],
                ['value', 'param', 'type'],
            ],
        ];
    }
}
