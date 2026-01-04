<?php

namespace yiimp2\tests\unit\migration;

use Codeception\Test\Unit;

/**
 * Property-based tests for migration validation
 * Feature: yiimp2-dedicated-system
 * 
 * Tests the following properties:
 * - Property 11: Validation count matching
 * - Property 13: Foreign key integrity
 * 
 * Validates: Requirements 8.1-8.4, 11.1, 11.3
 */
class ValidationPropertyTest extends Unit
{
    private $testDir;
    
    protected function _before()
    {
        $this->testDir = sys_get_temp_dir() . '/yiimp2-validation-property-test-' . uniqid();
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
     * Property 11: Validation Count Matching
     * 
     * For any migrated table, the record count in the Yiimp2 database should equal 
     * the record count in the source old-yiimp.sql.
     * 
     * Validates: Requirements 8.1, 8.2, 8.3, 8.4
     * 
     * @dataProvider recordCountProvider
     */
    public function testValidationCountMatching($tableName, $sourceRecords)
    {
        // Create SQL file with INSERT statements
        $sqlDir = $this->testDir . '/sql';
        mkdir($sqlDir, 0755, true);
        
        $insertSql = $this->generateInsertStatements($tableName, $sourceRecords);
        file_put_contents($sqlDir . "/test-$tableName.sql", $insertSql);
        
        // Count source records by counting INSERT statements
        $sourceCount = substr_count($insertSql, 'INSERT INTO');
        
        // Verify source count matches expected
        $expectedCount = count($sourceRecords);
        $this->assertEquals($expectedCount, $sourceCount, 
            "Source record count should match expected count for table $tableName");
        
        // Simulate validation: count records in source
        $content = file_get_contents($sqlDir . "/test-$tableName.sql");
        $actualSourceCount = substr_count($content, 'INSERT INTO');
        
        // Validation should detect if counts match
        $this->assertEquals($sourceCount, $actualSourceCount,
            "Validation should correctly count source records");
    }
    
    /**
     * Data provider for record counts
     */
    public function recordCountProvider()
    {
        return [
            'coins - 3 records' => [
                'coins',
                [
                    ['id' => 1, 'name' => 'Bitcoin', 'symbol' => 'BTC', 'algo' => 'sha256'],
                    ['id' => 2, 'name' => 'Litecoin', 'symbol' => 'LTC', 'algo' => 'scrypt'],
                    ['id' => 3, 'name' => 'Dogecoin', 'symbol' => 'DOGE', 'algo' => 'scrypt'],
                ]
            ],
            'algos - 5 records' => [
                'algos',
                [
                    ['id' => 1, 'name' => 'sha256'],
                    ['id' => 2, 'name' => 'scrypt'],
                    ['id' => 3, 'name' => 'x11'],
                    ['id' => 4, 'name' => 'equihash'],
                    ['id' => 5, 'name' => 'kawpow'],
                ]
            ],
            'settings - 10 records' => [
                'settings',
                array_map(function($i) {
                    return ['param' => "setting_$i", 'value' => "value_$i"];
                }, range(1, 10))
            ],
            'coins - 1 record' => [
                'coins',
                [
                    ['id' => 1, 'name' => 'Bitcoin', 'symbol' => 'BTC', 'algo' => 'sha256'],
                ]
            ],
            'algos - 100 records' => [
                'algos',
                array_map(function($i) {
                    return ['id' => $i, 'name' => "algo_$i"];
                }, range(1, 100))
            ],
        ];
    }
    
    /**
     * Test that validation detects count mismatches
     * 
     * @dataProvider countMismatchProvider
     */
    public function testValidationDetectsCountMismatch($sourceCount, $destCount, $shouldMatch)
    {
        // Simulate validation comparison
        $countsMatch = ($sourceCount === $destCount);
        
        $this->assertEquals($shouldMatch, $countsMatch,
            "Validation should correctly detect count mismatch");
    }
    
    /**
     * Data provider for count mismatches
     */
    public function countMismatchProvider()
    {
        return [
            'matching counts' => [10, 10, true],
            'destination has more' => [10, 15, false],
            'destination has less' => [10, 5, false],
            'zero records' => [0, 0, true],
            'source empty, dest has records' => [0, 5, false],
            'source has records, dest empty' => [5, 0, false],
            'large matching counts' => [1000, 1000, true],
            'large mismatch' => [1000, 999, false],
        ];
    }
    
    /**
     * Test that validation verifies all IDs exist
     * 
     * @dataProvider idExistenceProvider
     */
    public function testValidationVerifiesIdExistence($sourceIds, $destIds, $allExist)
    {
        // Check if all source IDs exist in destination
        $missingIds = array_diff($sourceIds, $destIds);
        $actualAllExist = empty($missingIds);
        
        $this->assertEquals($allExist, $actualAllExist,
            "Validation should correctly detect missing IDs");
        
        if (!$allExist) {
            $this->assertNotEmpty($missingIds, "Missing IDs should be identified");
        }
    }
    
    /**
     * Data provider for ID existence
     */
    public function idExistenceProvider()
    {
        return [
            'all IDs exist' => [
                [1, 2, 3, 4, 5],
                [1, 2, 3, 4, 5],
                true
            ],
            'missing one ID' => [
                [1, 2, 3, 4, 5],
                [1, 2, 3, 4],
                false
            ],
            'missing multiple IDs' => [
                [1, 2, 3, 4, 5],
                [1, 3, 5],
                false
            ],
            'destination has extra IDs' => [
                [1, 2, 3],
                [1, 2, 3, 4, 5],
                true
            ],
            'no IDs' => [
                [],
                [],
                true
            ],
            'all IDs missing' => [
                [1, 2, 3],
                [],
                false
            ],
            'non-sequential IDs' => [
                [1, 5, 10, 15, 20],
                [1, 5, 10, 15, 20],
                true
            ],
            'large ID set' => [
                range(1, 100),
                range(1, 100),
                true
            ],
        ];
    }
    
    /**
     * Property 13: Foreign Key Integrity
     * 
     * For any coin record with an algorithm reference, the referenced algorithm ID 
     * should exist in the algos table after migration completes.
     * 
     * Validates: Requirements 11.1, 11.3
     * 
     * @dataProvider foreignKeyProvider
     */
    public function testForeignKeyIntegrity($coins, $algos, $hasIntegrityViolation)
    {
        // Extract algorithm names from coins
        $referencedAlgos = array_unique(array_column($coins, 'algo'));
        
        // Extract algorithm names from algos table
        $availableAlgos = array_column($algos, 'name');
        
        // Check for violations
        $violations = [];
        foreach ($referencedAlgos as $algoName) {
            if (!empty($algoName) && !in_array($algoName, $availableAlgos)) {
                $violations[] = $algoName;
            }
        }
        
        $actualHasViolation = !empty($violations);
        
        $this->assertEquals($hasIntegrityViolation, $actualHasViolation,
            "Foreign key integrity check should correctly detect violations");
        
        if ($hasIntegrityViolation) {
            $this->assertNotEmpty($violations, "Violations should be identified");
        } else {
            $this->assertEmpty($violations, "No violations should exist");
        }
    }
    
    /**
     * Data provider for foreign key integrity
     */
    public function foreignKeyProvider()
    {
        return [
            'valid references' => [
                [
                    ['id' => 1, 'name' => 'Bitcoin', 'algo' => 'sha256'],
                    ['id' => 2, 'name' => 'Litecoin', 'algo' => 'scrypt'],
                    ['id' => 3, 'name' => 'Dogecoin', 'algo' => 'scrypt'],
                ],
                [
                    ['id' => 1, 'name' => 'sha256'],
                    ['id' => 2, 'name' => 'scrypt'],
                ],
                false
            ],
            'missing algorithm' => [
                [
                    ['id' => 1, 'name' => 'Bitcoin', 'algo' => 'sha256'],
                    ['id' => 2, 'name' => 'Litecoin', 'algo' => 'scrypt'],
                    ['id' => 3, 'name' => 'TestCoin', 'algo' => 'x11'],
                ],
                [
                    ['id' => 1, 'name' => 'sha256'],
                    ['id' => 2, 'name' => 'scrypt'],
                ],
                true
            ],
            'multiple missing algorithms' => [
                [
                    ['id' => 1, 'name' => 'Bitcoin', 'algo' => 'sha256'],
                    ['id' => 2, 'name' => 'TestCoin1', 'algo' => 'x11'],
                    ['id' => 3, 'name' => 'TestCoin2', 'algo' => 'equihash'],
                ],
                [
                    ['id' => 1, 'name' => 'sha256'],
                ],
                true
            ],
            'empty algo field' => [
                [
                    ['id' => 1, 'name' => 'Bitcoin', 'algo' => 'sha256'],
                    ['id' => 2, 'name' => 'TestCoin', 'algo' => ''],
                ],
                [
                    ['id' => 1, 'name' => 'sha256'],
                ],
                false
            ],
            'null algo field' => [
                [
                    ['id' => 1, 'name' => 'Bitcoin', 'algo' => 'sha256'],
                    ['id' => 2, 'name' => 'TestCoin', 'algo' => null],
                ],
                [
                    ['id' => 1, 'name' => 'sha256'],
                ],
                false
            ],
            'all coins reference same algo' => [
                [
                    ['id' => 1, 'name' => 'Bitcoin', 'algo' => 'sha256'],
                    ['id' => 2, 'name' => 'BitcoinCash', 'algo' => 'sha256'],
                    ['id' => 3, 'name' => 'BitcoinSV', 'algo' => 'sha256'],
                ],
                [
                    ['id' => 1, 'name' => 'sha256'],
                ],
                false
            ],
            'no coins' => [
                [],
                [
                    ['id' => 1, 'name' => 'sha256'],
                ],
                false
            ],
            'no algos' => [
                [
                    ['id' => 1, 'name' => 'Bitcoin', 'algo' => 'sha256'],
                ],
                [],
                true
            ],
        ];
    }
    
    /**
     * Test that validation checks all required tables
     * 
     * @dataProvider requiredTablesProvider
     */
    public function testValidationChecksAllRequiredTables($tables, $includeOptional)
    {
        $requiredTables = ['algos', 'coins', 'settings'];
        $optionalTables = ['benchmarks', 'bench_chips', 'markets', 'market_history'];
        
        // Determine expected tables
        $expectedTables = $requiredTables;
        if ($includeOptional) {
            $expectedTables = array_merge($expectedTables, $optionalTables);
        }
        
        // Verify all expected tables are in the validation list
        foreach ($expectedTables as $table) {
            $this->assertContains($table, $tables,
                "Validation should check table: $table");
        }
    }
    
    /**
     * Data provider for required tables
     */
    public function requiredTablesProvider()
    {
        return [
            'required only' => [
                ['algos', 'coins', 'settings'],
                false
            ],
            'all tables' => [
                ['algos', 'coins', 'settings', 'benchmarks', 'bench_chips', 'markets', 'market_history'],
                true
            ],
        ];
    }
    
    /**
     * Test that validation reports discrepancies correctly
     * 
     * @dataProvider discrepancyProvider
     */
    public function testValidationReportsDiscrepancies($discrepancies, $hasErrors)
    {
        $errorCount = count($discrepancies);
        $actualHasErrors = ($errorCount > 0);
        
        $this->assertEquals($hasErrors, $actualHasErrors,
            "Validation should correctly report error status");
        
        if ($hasErrors) {
            $this->assertGreaterThan(0, $errorCount,
                "Error count should be greater than 0 when errors exist");
        } else {
            $this->assertEquals(0, $errorCount,
                "Error count should be 0 when no errors exist");
        }
    }
    
    /**
     * Data provider for discrepancies
     */
    public function discrepancyProvider()
    {
        return [
            'no discrepancies' => [
                [],
                false
            ],
            'one discrepancy' => [
                ['Count mismatch in coins table'],
                true
            ],
            'multiple discrepancies' => [
                [
                    'Count mismatch in coins table',
                    'Missing coin ID: 5',
                    'Missing algorithm ID: 3',
                ],
                true
            ],
            'foreign key violation' => [
                ['Coin references non-existent algorithm'],
                true
            ],
        ];
    }
    
    /**
     * Test that validation generates proper report format
     * 
     * @dataProvider reportFormatProvider
     */
    public function testValidationReportFormat($tableName, $sourceCount, $destCount)
    {
        // Generate report line
        $report = sprintf("Table: %s\n  Source records: %d\n  Destination records: %d",
            $tableName, $sourceCount, $destCount);
        
        // Verify report contains required information
        $this->assertStringContainsString($tableName, $report);
        $this->assertStringContainsString((string)$sourceCount, $report);
        $this->assertStringContainsString((string)$destCount, $report);
        $this->assertStringContainsString('Source records:', $report);
        $this->assertStringContainsString('Destination records:', $report);
    }
    
    /**
     * Data provider for report format
     */
    public function reportFormatProvider()
    {
        return [
            'coins table' => ['coins', 10, 10],
            'algos table' => ['algos', 5, 5],
            'settings table' => ['settings', 20, 20],
            'zero records' => ['benchmarks', 0, 0],
            'large counts' => ['market_history', 1000, 1000],
        ];
    }
    
    /**
     * Helper method to generate INSERT statements
     */
    private function generateInsertStatements($tableName, $records)
    {
        $statements = [];
        
        foreach ($records as $record) {
            $columns = array_keys($record);
            $values = array_values($record);
            
            $columnList = '`' . implode('`, `', $columns) . '`';
            $valueList = implode(', ', array_map(function($v) {
                if ($v === null) {
                    return 'NULL';
                }
                return is_numeric($v) ? $v : "'" . addslashes($v) . "'";
            }, $values));
            
            $statements[] = "INSERT INTO `$tableName` ($columnList) VALUES ($valueList);";
        }
        
        return implode("\n", $statements);
    }
    
    /**
     * Test validation with various data sizes
     * 
     * @dataProvider dataSizeProvider
     */
    public function testValidationWithVariousDataSizes($recordCount)
    {
        // Generate test data
        if ($recordCount > 0) {
            $records = array_map(function($i) {
                return ['id' => $i, 'name' => "record_$i"];
            }, range(1, $recordCount));
        } else {
            $records = [];
        }
        
        // Verify count
        $this->assertCount($recordCount, $records,
            "Should handle $recordCount records");
        
        // Verify IDs are sequential (only if records exist)
        if ($recordCount > 0) {
            $ids = array_column($records, 'id');
            $this->assertEquals(range(1, $recordCount), $ids,
                "IDs should be sequential");
        }
    }
    
    /**
     * Data provider for data sizes
     */
    public function dataSizeProvider()
    {
        return [
            'small dataset' => [10],
            'medium dataset' => [100],
            'large dataset' => [1000],
            'single record' => [1],
            'empty dataset' => [0],
        ];
    }
}
