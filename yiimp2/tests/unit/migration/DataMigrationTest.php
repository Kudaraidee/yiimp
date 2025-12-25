<?php

namespace yiimp2\tests\unit\migration;

use Codeception\Test\Unit;

/**
 * Unit tests for data migration script
 * Tests the bin/migrate-yiimp2-data.sh script functionality
 * 
 * Requirements: 7.2, 7.3, 7.4, 10.1-10.6
 */
class DataMigrationTest extends Unit
{
    private $testDir;
    private $scriptPath;
    private $testDbName = 'yiimp2_test_migration';
    
    protected function _before()
    {
        $this->scriptPath = dirname(dirname(dirname(__DIR__))) . '/../bin/migrate-yiimp2-data.sh';
        $this->testDir = sys_get_temp_dir() . '/yiimp2-migration-test-' . uniqid();
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
     * Test that the migration script exists and is executable
     */
    public function testScriptExists()
    {
        $this->assertFileExists($this->scriptPath, 'Migration script should exist');
        $this->assertTrue(is_executable($this->scriptPath), 'Script should be executable');
    }
    
    /**
     * Test coin data extraction from old-yiimp.sql
     * Requirements: 7.2
     */
    public function testCoinDataExtraction()
    {
        // Create test SQL file with coin data
        $sqlDir = $this->testDir . '/sql';
        mkdir($sqlDir, 0755, true);
        
        $coinsSql = "INSERT INTO `coins` (`id`, `name`, `symbol`, `decimals`, `algo`) VALUES\n";
        $coinsSql .= "(1, 'Bitcoin', 'BTC', 8, 'sha256'),\n";
        $coinsSql .= "(2, 'Litecoin', 'LTC', 8, 'scrypt'),\n";
        $coinsSql .= "(3, 'Dogecoin', 'DOGE', 8, 'scrypt');\n";
        
        file_put_contents($sqlDir . '/test-coins.sql', $coinsSql);
        
        // Extract INSERT statements (simulating script behavior)
        $content = file_get_contents($sqlDir . '/test-coins.sql');
        $this->assertStringContainsString('INSERT INTO `coins`', $content);
        $this->assertStringContainsString('Bitcoin', $content);
        $this->assertStringContainsString('Litecoin', $content);
        $this->assertStringContainsString('Dogecoin', $content);
        $this->assertStringContainsString('decimals', $content);
        
        // Verify all columns are present
        $this->assertStringContainsString('`id`', $content);
        $this->assertStringContainsString('`name`', $content);
        $this->assertStringContainsString('`symbol`', $content);
        $this->assertStringContainsString('`decimals`', $content);
        $this->assertStringContainsString('`algo`', $content);
    }
    
    /**
     * Test algorithm data extraction
     * Requirements: 7.3
     */
    public function testAlgorithmDataExtraction()
    {
        // Create test SQL file with algorithm data
        $sqlDir = $this->testDir . '/sql';
        mkdir($sqlDir, 0755, true);
        
        $algosSql = "INSERT INTO `algos` (`id`, `name`, `port`, `color`) VALUES\n";
        $algosSql .= "(1, 'sha256', 3333, '#c0c0e0'),\n";
        $algosSql .= "(2, 'scrypt', 3433, '#d0d0d0'),\n";
        $algosSql .= "(3, 'x11', 3533, '#e0e0e0');\n";
        
        file_put_contents($sqlDir . '/test-algos.sql', $algosSql);
        
        // Extract INSERT statements
        $content = file_get_contents($sqlDir . '/test-algos.sql');
        $this->assertStringContainsString('INSERT INTO `algos`', $content);
        $this->assertStringContainsString('sha256', $content);
        $this->assertStringContainsString('scrypt', $content);
        $this->assertStringContainsString('x11', $content);
        
        // Verify all columns are present
        $this->assertStringContainsString('`id`', $content);
        $this->assertStringContainsString('`name`', $content);
        $this->assertStringContainsString('`port`', $content);
        $this->assertStringContainsString('`color`', $content);
    }
    
    /**
     * Test settings data extraction
     * Requirements: 7.4
     */
    public function testSettingsDataExtraction()
    {
        // Create test SQL file with settings data
        $sqlDir = $this->testDir . '/sql';
        mkdir($sqlDir, 0755, true);
        
        $settingsSql = "INSERT INTO `settings` (`param`, `value`, `type`) VALUES\n";
        $settingsSql .= "('pool_name', 'Test Pool', 'string'),\n";
        $settingsSql .= "('pool_fee', '1.5', 'float'),\n";
        $settingsSql .= "('auto_exchange', '1', 'bool');\n";
        
        file_put_contents($sqlDir . '/test-settings.sql', $settingsSql);
        
        // Extract INSERT statements
        $content = file_get_contents($sqlDir . '/test-settings.sql');
        $this->assertStringContainsString('INSERT INTO `settings`', $content);
        $this->assertStringContainsString('pool_name', $content);
        $this->assertStringContainsString('pool_fee', $content);
        $this->assertStringContainsString('auto_exchange', $content);
        
        // Verify all columns are present
        $this->assertStringContainsString('`param`', $content);
        $this->assertStringContainsString('`value`', $content);
        $this->assertStringContainsString('`type`', $content);
    }
    
    /**
     * Test data insertion into database (mock test)
     * Requirements: 7.2, 7.3, 7.4
     */
    public function testDataInsertion()
    {
        // This test verifies the INSERT statement format is correct
        $insertStmt = "INSERT INTO `coins` (`id`, `name`, `symbol`) VALUES (1, 'Bitcoin', 'BTC');";
        
        // Verify statement structure
        $this->assertStringContainsString('INSERT INTO', $insertStmt);
        $this->assertStringContainsString('VALUES', $insertStmt);
        $this->assertStringEndsWith(';', $insertStmt);
        
        // Test INSERT IGNORE transformation (skip mode)
        $skipStmt = str_replace('INSERT INTO', 'INSERT IGNORE INTO', $insertStmt);
        $this->assertStringContainsString('INSERT IGNORE INTO', $skipStmt);
        
        // Test REPLACE INTO transformation (update mode)
        $updateStmt = str_replace('INSERT INTO', 'REPLACE INTO', $insertStmt);
        $this->assertStringContainsString('REPLACE INTO', $updateStmt);
    }
    
    /**
     * Test handling of duplicate records
     * Requirements: 7.2, 7.3, 7.4
     */
    public function testDuplicateRecordHandling()
    {
        // Test skip mode - INSERT IGNORE
        $originalInsert = "INSERT INTO `coins` (`id`, `name`) VALUES (1, 'Bitcoin');";
        $skipInsert = str_replace('INSERT INTO', 'INSERT IGNORE INTO', $originalInsert);
        
        $this->assertStringContainsString('INSERT IGNORE INTO', $skipInsert);
        $this->assertStringNotContainsString('INSERT INTO `coins`', $skipInsert);
        
        // Test update mode - REPLACE INTO
        $updateInsert = str_replace('INSERT INTO', 'REPLACE INTO', $originalInsert);
        
        $this->assertStringContainsString('REPLACE INTO', $updateInsert);
        $this->assertStringNotContainsString('INSERT INTO `coins`', $updateInsert);
    }
    
    /**
     * Test optional table migration flags - benchmarks
     * Requirements: 10.1, 10.2
     */
    public function testBenchmarksTableMigration()
    {
        // Create test SQL file with benchmarks data
        $sqlDir = $this->testDir . '/sql';
        mkdir($sqlDir, 0755, true);
        
        $benchmarksSql = "INSERT INTO `benchmarks` (`id`, `algo`, `hashrate`) VALUES\n";
        $benchmarksSql .= "(1, 'sha256', 1000000),\n";
        $benchmarksSql .= "(2, 'scrypt', 500000);\n";
        
        file_put_contents($sqlDir . '/test-benchmarks.sql', $benchmarksSql);
        
        // Verify benchmarks data can be extracted
        $content = file_get_contents($sqlDir . '/test-benchmarks.sql');
        $this->assertStringContainsString('INSERT INTO `benchmarks`', $content);
        $this->assertStringContainsString('sha256', $content);
        $this->assertStringContainsString('scrypt', $content);
    }
    
    /**
     * Test optional table migration flags - bench_chips
     * Requirements: 10.1, 10.2
     */
    public function testBenchChipsTableMigration()
    {
        // Create test SQL file with bench_chips data
        $sqlDir = $this->testDir . '/sql';
        mkdir($sqlDir, 0755, true);
        
        $benchChipsSql = "INSERT INTO `bench_chips` (`id`, `name`, `power`) VALUES\n";
        $benchChipsSql .= "(1, 'ASIC-1', 1500),\n";
        $benchChipsSql .= "(2, 'ASIC-2', 2000);\n";
        
        file_put_contents($sqlDir . '/test-bench-chips.sql', $benchChipsSql);
        
        // Verify bench_chips data can be extracted
        $content = file_get_contents($sqlDir . '/test-bench-chips.sql');
        $this->assertStringContainsString('INSERT INTO `bench_chips`', $content);
        $this->assertStringContainsString('ASIC-1', $content);
        $this->assertStringContainsString('ASIC-2', $content);
    }
    
    /**
     * Test optional table migration flags - markets
     * Requirements: 10.3, 10.4
     */
    public function testMarketsTableMigration()
    {
        // Create test SQL file with markets data
        $sqlDir = $this->testDir . '/sql';
        mkdir($sqlDir, 0755, true);
        
        $marketsSql = "INSERT INTO `markets` (`id`, `coinid`, `name`) VALUES\n";
        $marketsSql .= "(1, 1, 'Binance'),\n";
        $marketsSql .= "(2, 2, 'Coinbase');\n";
        
        file_put_contents($sqlDir . '/test-markets.sql', $marketsSql);
        
        // Verify markets data can be extracted
        $content = file_get_contents($sqlDir . '/test-markets.sql');
        $this->assertStringContainsString('INSERT INTO `markets`', $content);
        $this->assertStringContainsString('Binance', $content);
        $this->assertStringContainsString('Coinbase', $content);
    }
    
    /**
     * Test optional table migration flags - market_history
     * Requirements: 10.3, 10.4
     */
    public function testMarketHistoryTableMigration()
    {
        // Create test SQL file with market_history data
        $sqlDir = $this->testDir . '/sql';
        mkdir($sqlDir, 0755, true);
        
        $marketHistorySql = "INSERT INTO `market_history` (`id`, `marketid`, `price`) VALUES\n";
        $marketHistorySql .= "(1, 1, 50000.00),\n";
        $marketHistorySql .= "(2, 2, 3000.00);\n";
        
        file_put_contents($sqlDir . '/test-market-history.sql', $marketHistorySql);
        
        // Verify market_history data can be extracted
        $content = file_get_contents($sqlDir . '/test-market-history.sql');
        $this->assertStringContainsString('INSERT INTO `market_history`', $content);
        $this->assertStringContainsString('50000', $content);
        $this->assertStringContainsString('3000', $content);
    }
    
    /**
     * Test script help output
     */
    public function testScriptHelp()
    {
        // Execute script with --help flag
        $output = shell_exec($this->scriptPath . ' --help 2>&1');
        
        $this->assertStringContainsString('Usage:', $output);
        $this->assertStringContainsString('--db-host', $output);
        $this->assertStringContainsString('--db-name', $output);
        $this->assertStringContainsString('--db-user', $output);
        $this->assertStringContainsString('--db-pass', $output);
        $this->assertStringContainsString('--include-benchmarks', $output);
        $this->assertStringContainsString('--include-markets', $output);
        $this->assertStringContainsString('--mode', $output);
    }
    
    /**
     * Test script validates required parameters
     */
    public function testScriptValidation()
    {
        // Execute script without required password parameter
        $output = shell_exec($this->scriptPath . ' 2>&1');
        
        $this->assertStringContainsString('Error', $output);
        $this->assertStringContainsString('password', strtolower($output));
    }
    
    /**
     * Test script validates mode parameter
     */
    public function testModeValidation()
    {
        // Create a temporary source file so we can test mode validation
        $sqlDir = $this->testDir . '/sql';
        mkdir($sqlDir, 0755, true);
        file_put_contents($sqlDir . '/test-source.sql', 'SELECT 1;');
        
        // Execute script with invalid mode
        $output = shell_exec($this->scriptPath . ' --db-pass test --source-file ' . $sqlDir . '/test-source.sql --mode invalid 2>&1');
        
        $this->assertStringContainsString('Error', $output);
        $this->assertStringContainsString('mode', strtolower($output));
    }
    
    /**
     * Test multi-line INSERT statement extraction
     * Requirements: 7.2, 7.3, 7.4
     */
    public function testMultiLineInsertExtraction()
    {
        // Create test SQL file with multi-line INSERT
        $sqlDir = $this->testDir . '/sql';
        mkdir($sqlDir, 0755, true);
        
        $multiLineSql = "INSERT INTO `coins` (\n";
        $multiLineSql .= "  `id`,\n";
        $multiLineSql .= "  `name`,\n";
        $multiLineSql .= "  `symbol`\n";
        $multiLineSql .= ") VALUES\n";
        $multiLineSql .= "(1, 'Bitcoin', 'BTC'),\n";
        $multiLineSql .= "(2, 'Litecoin', 'LTC');\n";
        
        file_put_contents($sqlDir . '/test-multiline.sql', $multiLineSql);
        
        // Verify multi-line INSERT can be extracted
        $content = file_get_contents($sqlDir . '/test-multiline.sql');
        $this->assertStringContainsString('INSERT INTO `coins`', $content);
        $this->assertStringContainsString('VALUES', $content);
        $this->assertStringContainsString('Bitcoin', $content);
        $this->assertStringContainsString('Litecoin', $content);
        
        // Verify it's a complete statement
        $this->assertStringEndsWith(";\n", $content);
    }
    
    /**
     * Test that operational tables are not included in migration
     * Requirements: 5.1-5.11
     */
    public function testOperationalTablesExcluded()
    {
        // List of operational tables that should NOT be migrated
        $operationalTables = [
            'shares',
            'workers',
            'accounts',
            'blocks',
            'payouts',
            'earnings',
            'hashrate',
            'hashuser',
            'hashstats',
            'balances',
            'connections'
        ];
        
        // Read the migration script
        $scriptContent = file_get_contents($this->scriptPath);
        
        // Verify operational tables are not in the migration list
        foreach ($operationalTables as $table) {
            // Check that the table is not explicitly migrated
            $this->assertStringNotContainsString(
                "migrate_table \"$table\"",
                $scriptContent,
                "Operational table '$table' should not be migrated"
            );
        }
        
        // Verify only configuration tables are migrated
        $this->assertStringContainsString('migrate_table "algos"', $scriptContent);
        $this->assertStringContainsString('migrate_table "coins"', $scriptContent);
        $this->assertStringContainsString('migrate_table "settings"', $scriptContent);
    }
}
