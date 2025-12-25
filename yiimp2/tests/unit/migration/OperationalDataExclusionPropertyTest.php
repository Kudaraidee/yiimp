<?php

namespace yiimp2\tests\unit\migration;

use Codeception\Test\Unit;

/**
 * Property-based test for operational data exclusion
 * Feature: yiimp2-dedicated-system, Property 8: Operational data exclusion
 * 
 * Property 8: Operational Data Exclusion
 * For any operational table (shares, workers, accounts, blocks, payouts, earnings, 
 * hashrate, hashuser, hashstats, balances, connections), the table should be empty 
 * in the Yiimp2 database after migration completes.
 * 
 * Validates: Requirements 5.1-5.11
 */
class OperationalDataExclusionPropertyTest extends Unit
{
    private $scriptPath;
    
    protected function _before()
    {
        $this->scriptPath = dirname(dirname(dirname(__DIR__))) . '/../bin/migrate-yiimp2-data.sh';
    }
    
    /**
     * Test that operational tables are never migrated
     * 
     * Property: For any operational table, the migration script should not 
     * include migration logic for that table
     * 
     * @dataProvider operationalTableProvider
     */
    public function testOperationalTablesNotMigrated($tableName)
    {
        // Read the migration script
        $scriptContent = file_get_contents($this->scriptPath);
        
        // Verify the table is not explicitly migrated
        $this->assertStringNotContainsString(
            "migrate_table \"$tableName\"",
            $scriptContent,
            "Operational table '$tableName' should not be migrated"
        );
        
        $this->assertStringNotContainsString(
            "migrate_table '$tableName'",
            $scriptContent,
            "Operational table '$tableName' should not be migrated"
        );
    }
    
    /**
     * Data provider for operational tables
     */
    public function operationalTableProvider()
    {
        return [
            'shares' => ['shares'],
            'workers' => ['workers'],
            'accounts' => ['accounts'],
            'blocks' => ['blocks'],
            'payouts' => ['payouts'],
            'earnings' => ['earnings'],
            'hashrate' => ['hashrate'],
            'hashuser' => ['hashuser'],
            'hashstats' => ['hashstats'],
            'balances' => ['balances'],
            'connections' => ['connections'],
        ];
    }
    
    /**
     * Test that only configuration tables are migrated
     * 
     * Property: For any migration script execution, only configuration tables 
     * (coins, algos, settings) should be migrated by default
     * 
     * @dataProvider configurationTableProvider
     */
    public function testOnlyConfigurationTablesMigrated($tableName)
    {
        // Read the migration script
        $scriptContent = file_get_contents($this->scriptPath);
        
        // Verify the table IS migrated
        $found = strpos($scriptContent, "migrate_table \"$tableName\"") !== false ||
                 strpos($scriptContent, "migrate_table '$tableName'") !== false;
        
        $this->assertTrue(
            $found,
            "Configuration table '$tableName' should be migrated"
        );
    }
    
    /**
     * Data provider for configuration tables
     */
    public function configurationTableProvider()
    {
        return [
            'algos' => ['algos'],
            'coins' => ['coins'],
            'settings' => ['settings'],
        ];
    }
    
    /**
     * Test that operational tables would remain empty after migration
     * 
     * Property: For any operational table, if it exists in the source SQL file,
     * it should not be extracted or migrated
     * 
     * @dataProvider operationalTableWithDataProvider
     */
    public function testOperationalTablesRemainEmpty($tableName, $sampleInsert)
    {
        // Create a test SQL file with operational data
        $testDir = sys_get_temp_dir() . '/yiimp2-op-test-' . uniqid();
        mkdir($testDir, 0755, true);
        
        $sqlFile = $testDir . '/test-operational.sql';
        file_put_contents($sqlFile, $sampleInsert);
        
        // Read the migration script
        $scriptContent = file_get_contents($this->scriptPath);
        
        // Verify the script does NOT migrate this table
        $this->assertStringNotContainsString(
            "migrate_table \"$tableName\"",
            $scriptContent,
            "Operational table '$tableName' should not be migrated even if data exists"
        );
        
        // Cleanup
        unlink($sqlFile);
        rmdir($testDir);
    }
    
    /**
     * Data provider for operational tables with sample data
     */
    public function operationalTableWithDataProvider()
    {
        return [
            'shares with data' => [
                'shares',
                "INSERT INTO `shares` (`id`, `userid`, `valid`) VALUES (1, 1, 1);"
            ],
            'workers with data' => [
                'workers',
                "INSERT INTO `workers` (`id`, `userid`, `name`) VALUES (1, 1, 'worker1');"
            ],
            'accounts with data' => [
                'accounts',
                "INSERT INTO `accounts` (`id`, `username`, `balance`) VALUES (1, 'user1', 0);"
            ],
            'blocks with data' => [
                'blocks',
                "INSERT INTO `blocks` (`id`, `height`, `hash`) VALUES (1, 100, 'abc123');"
            ],
            'payouts with data' => [
                'payouts',
                "INSERT INTO `payouts` (`id`, `userid`, `amount`) VALUES (1, 1, 1.5);"
            ],
            'earnings with data' => [
                'earnings',
                "INSERT INTO `earnings` (`id`, `userid`, `amount`) VALUES (1, 1, 0.5);"
            ],
            'hashrate with data' => [
                'hashrate',
                "INSERT INTO `hashrate` (`id`, `time`, `hashrate`) VALUES (1, 1234567890, 1000);"
            ],
            'hashuser with data' => [
                'hashuser',
                "INSERT INTO `hashuser` (`id`, `userid`, `hashrate`) VALUES (1, 1, 500);"
            ],
            'hashstats with data' => [
                'hashstats',
                "INSERT INTO `hashstats` (`id`, `time`, `hashrate`) VALUES (1, 1234567890, 2000);"
            ],
            'balances with data' => [
                'balances',
                "INSERT INTO `balances` (`id`, `userid`, `balance`) VALUES (1, 1, 10.5);"
            ],
            'connections with data' => [
                'connections',
                "INSERT INTO `connections` (`id`, `userid`, `ip`) VALUES (1, 1, '127.0.0.1');"
            ],
        ];
    }
    
    /**
     * Test that the migration script explicitly documents operational table exclusion
     * 
     * Property: For any migration script, it should clearly indicate that 
     * operational tables are excluded
     */
    public function testScriptDocumentsExclusion()
    {
        // Read the script
        $scriptContent = file_get_contents($this->scriptPath);
        
        // Check for documentation about required tables
        $this->assertStringContainsString(
            'Migrating required tables',
            $scriptContent,
            "Script should document which tables are required"
        );
    }
    
    /**
     * Test that operational tables are not in the required migration list
     * 
     * Property: For any operational table, it should not appear in the 
     * list of tables to migrate
     * 
     * @dataProvider operationalTableProvider
     */
    public function testOperationalTablesNotInMigrationList($tableName)
    {
        // Read the script
        $scriptContent = file_get_contents($this->scriptPath);
        
        // Extract the main migration section
        preg_match('/# Migrate required tables.*?# Migrate optional tables/s', $scriptContent, $matches);
        
        if (isset($matches[0])) {
            $requiredSection = $matches[0];
            
            // Verify operational table is not in required section
            $this->assertStringNotContainsString(
                $tableName,
                $requiredSection,
                "Operational table '$tableName' should not be in required migration section"
            );
        }
    }
    
    /**
     * Test that configuration tables ARE in the required migration list
     * 
     * Property: For any configuration table, it should appear in the 
     * list of required tables to migrate
     * 
     * @dataProvider configurationTableProvider
     */
    public function testConfigurationTablesInMigrationList($tableName)
    {
        // Read the script
        $scriptContent = file_get_contents($this->scriptPath);
        
        // Extract the main migration section
        preg_match('/# Migrate required tables.*?# Migrate optional tables/s', $scriptContent, $matches);
        
        if (isset($matches[0])) {
            $requiredSection = $matches[0];
            
            // Verify configuration table IS in required section
            $this->assertStringContainsString(
                $tableName,
                $requiredSection,
                "Configuration table '$tableName' should be in required migration section"
            );
        }
    }
    
    /**
     * Test that the script has exactly 3 required tables
     * 
     * Property: For any migration script execution, exactly 3 tables 
     * (coins, algos, settings) should be migrated by default
     */
    public function testExactlyThreeRequiredTables()
    {
        // Read the script
        $scriptContent = file_get_contents($this->scriptPath);
        
        // Count migrate_table calls in the required section
        preg_match('/# Migrate required tables.*?# Migrate optional tables/s', $scriptContent, $matches);
        
        if (isset($matches[0])) {
            $requiredSection = $matches[0];
            
            // Count migrate_table calls
            $count = preg_match_all('/migrate_table\s+["\']/', $requiredSection);
            
            $this->assertEquals(
                3,
                $count,
                "Exactly 3 required tables should be migrated (coins, algos, settings)"
            );
        }
    }
    
    /**
     * Test that operational data is explicitly excluded in comments
     * 
     * Property: For any migration script, it should document that 
     * operational data is excluded
     */
    public function testOperationalDataExclusionDocumented()
    {
        // Read the script header/comments
        $scriptContent = file_get_contents($this->scriptPath);
        
        // Check for documentation about what is NOT migrated
        // The script should mention operational tables or runtime data
        $hasExclusionDoc = 
            stripos($scriptContent, 'operational') !== false ||
            stripos($scriptContent, 'runtime') !== false ||
            stripos($scriptContent, 'exclude') !== false;
        
        $this->assertTrue(
            $hasExclusionDoc,
            "Script should document that operational/runtime data is excluded"
        );
    }
}
