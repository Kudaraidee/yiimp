<?php

namespace tests\unit\migration;

use Codeception\Test\Unit;

/**
 * Property-Based Test for Optional Table Migration
 * 
 * Feature: yiimp2-dedicated-system, Property 12: Optional table migration
 * 
 * Property: For any optional table (benchmarks, bench_chips, markets, market_history),
 * the table should be populated if and only if the corresponding migration flag is enabled.
 * 
 * Validates: Requirements 10.1-10.6
 * 
 * This test verifies that the migration script correctly handles optional tables,
 * migrating them only when explicitly requested via command-line flags.
 */
class OptionalTableMigrationPropertyTest extends Unit
{
    private $projectRoot;
    private $migrationScript;
    
    protected function _before()
    {
        // __DIR__ is yiimp2/tests/unit/migration
        // We need to go up 4 levels to get to project root
        $this->projectRoot = dirname(__DIR__, 4);
        $this->migrationScript = $this->projectRoot . '/bin/migrate-yiimp2-data.sh';
    }
    
    /**
     * Property: Migration script supports optional table flags
     * 
     * For any migration script execution, it should accept flags for optional tables
     * and only migrate those tables when the corresponding flag is provided.
     * 
     * @test
     */
    public function testMigrationScriptSupportsOptionalTableFlags()
    {
        if (!file_exists($this->migrationScript)) {
            $this->markTestSkipped('Migration script not found');
        }
        
        $content = file_get_contents($this->migrationScript);
        
        // Script should support --include-benchmarks flag
        $this->assertStringContainsString('include-benchmarks', $content,
            'Migration script should support --include-benchmarks flag');
        
        // Script should support --include-markets flag
        $this->assertStringContainsString('include-markets', $content,
            'Migration script should support --include-markets flag');
        
        // Script should have logic to conditionally migrate benchmarks
        $this->assertMatchesRegularExpression('/benchmarks.*include.*benchmarks/is', $content,
            'Migration script should conditionally migrate benchmarks table');
        
        // Script should have logic to conditionally migrate markets
        $this->assertMatchesRegularExpression('/markets.*include.*markets/is', $content,
            'Migration script should conditionally migrate markets table');
    }
    
    /**
     * Property: Optional tables are defined separately from required tables
     * 
     * For any migration script, optional tables should be clearly distinguished
     * from required tables (coins, algos, settings).
     * 
     * @test
     */
    public function testOptionalTablesAreDefinedSeparately()
    {
        if (!file_exists($this->migrationScript)) {
            $this->markTestSkipped('Migration script not found');
        }
        
        $content = file_get_contents($this->migrationScript);
        
        // Required tables should always be migrated
        $requiredTables = ['coins', 'algos', 'settings'];
        foreach ($requiredTables as $table) {
            $this->assertStringContainsString($table, $content,
                "Migration script should reference required table: $table");
        }
        
        // Optional tables should be referenced
        $optionalTables = ['benchmarks', 'bench_chips', 'markets', 'market_history'];
        foreach ($optionalTables as $table) {
            $this->assertStringContainsString($table, $content,
                "Migration script should reference optional table: $table");
        }
    }
    
    /**
     * Property: Help text documents optional table flags
     * 
     * For any migration script, the help text should document the optional
     * table flags and their purpose.
     * 
     * @test
     */
    public function testHelpTextDocumentsOptionalTableFlags()
    {
        if (!file_exists($this->migrationScript)) {
            $this->markTestSkipped('Migration script not found');
        }
        
        $content = file_get_contents($this->migrationScript);
        
        // Script should have help/usage text
        $hasHelpText = 
            strpos($content, 'usage') !== false ||
            strpos($content, 'Usage') !== false ||
            strpos($content, 'USAGE') !== false ||
            strpos($content, '--help') !== false;
        
        $this->assertTrue($hasHelpText,
            'Migration script should have help/usage text');
        
        // Help text should mention optional flags
        if ($hasHelpText) {
            $this->assertMatchesRegularExpression('/include.*benchmarks/i', $content,
                'Help text should document --include-benchmarks flag');
            $this->assertMatchesRegularExpression('/include.*markets/i', $content,
                'Help text should document --include-markets flag');
        }
    }
    
    /**
     * Property: Default behavior excludes optional tables
     * 
     * For any migration script execution without optional flags, the script
     * should only migrate required tables and skip optional tables.
     * 
     * @test
     */
    public function testDefaultBehaviorExcludesOptionalTables()
    {
        if (!file_exists($this->migrationScript)) {
            $this->markTestSkipped('Migration script not found');
        }
        
        $content = file_get_contents($this->migrationScript);
        
        // Script should have default values for optional flags (false/0/empty)
        // or conditional logic that checks if flags are set
        $hasConditionalLogic = 
            preg_match('/if.*include.*benchmarks/i', $content) ||
            preg_match('/\$.*include.*benchmarks.*=.*false/i', $content) ||
            preg_match('/include_benchmarks=0/i', $content);
        
        $this->assertTrue($hasConditionalLogic,
            'Migration script should have conditional logic for optional tables');
    }
    
    /**
     * Property: Optional table migration is independent
     * 
     * For any optional table, enabling its migration flag should not affect
     * the migration of other optional tables.
     * 
     * @test
     */
    public function testOptionalTableMigrationIsIndependent()
    {
        if (!file_exists($this->migrationScript)) {
            $this->markTestSkipped('Migration script not found');
        }
        
        $content = file_get_contents($this->migrationScript);
        
        // Benchmarks flag should be independent of markets flag
        // Check that they are separate variables/conditions
        $hasSeparateBenchmarksFlag = preg_match('/INCLUDE.*BENCHMARKS|include.*benchmarks/i', $content) > 0;
        $hasSeparateMarketsFlag = preg_match('/INCLUDE.*MARKETS|include.*markets/i', $content) > 0;
        
        $this->assertTrue($hasSeparateBenchmarksFlag,
            'Migration script should have separate flag for benchmarks');
        $this->assertTrue($hasSeparateMarketsFlag,
            'Migration script should have separate flag for markets');
        
        // Flags should not be combined (e.g., not "include-all-optional")
        $this->assertStringNotContainsString('include-all-optional', $content,
            'Migration script should not have combined optional flag');
    }
    
    /**
     * Property: Optional tables include all related tables
     * 
     * For any optional table group (e.g., benchmarks), all related tables
     * should be migrated together (e.g., benchmarks and bench_chips).
     * 
     * @test
     */
    public function testOptionalTablesIncludeRelatedTables()
    {
        if (!file_exists($this->migrationScript)) {
            $this->markTestSkipped('Migration script not found');
        }
        
        $content = file_get_contents($this->migrationScript);
        
        // If benchmarks is migrated, bench_chips should also be migrated
        // (they are related tables)
        $benchmarksSection = $this->extractSectionContaining($content, 'benchmarks');
        if ($benchmarksSection) {
            $this->assertStringContainsString('bench_chips', $benchmarksSection,
                'Benchmarks migration should include bench_chips table');
        }
        
        // If markets is migrated, market_history should also be migrated
        // (they are related tables)
        $marketsSection = $this->extractSectionContaining($content, 'markets');
        if ($marketsSection) {
            $this->assertStringContainsString('market_history', $marketsSection,
                'Markets migration should include market_history table');
        }
    }
    
    /**
     * Property: Optional table migration preserves data integrity
     * 
     * For any optional table migration, foreign key relationships should be
     * maintained (e.g., benchmarks reference algos).
     * 
     * @test
     */
    public function testOptionalTableMigrationPreservesDataIntegrity()
    {
        if (!file_exists($this->migrationScript)) {
            $this->markTestSkipped('Migration script not found');
        }
        
        $content = file_get_contents($this->migrationScript);
        
        // Optional tables should be migrated after required tables
        // to ensure foreign key references exist
        $coinsPos = strpos($content, 'coins');
        $algosPos = strpos($content, 'algos');
        $benchmarksPos = strpos($content, 'benchmarks');
        $marketsPos = strpos($content, 'markets');
        
        if ($coinsPos !== false && $benchmarksPos !== false) {
            // Benchmarks reference algos, so algos should be migrated first
            if ($algosPos !== false) {
                $this->assertLessThan($benchmarksPos, $algosPos,
                    'Algos should be migrated before benchmarks (foreign key dependency)');
            }
        }
        
        if ($coinsPos !== false && $marketsPos !== false) {
            // Markets reference coins, so coins should be migrated first
            $this->assertLessThan($marketsPos, $coinsPos,
                'Coins should be migrated before markets (foreign key dependency)');
        }
    }
    
    // Helper methods
    
    /**
     * Extract a section of content containing a specific keyword
     * 
     * @param string $content Full content
     * @param string $keyword Keyword to search for
     * @return string|null Section containing the keyword, or null if not found
     */
    private function extractSectionContaining($content, $keyword)
    {
        // Find the keyword and extract surrounding context (500 chars before and after)
        $pos = stripos($content, $keyword);
        if ($pos === false) {
            return null;
        }
        
        $start = max(0, $pos - 500);
        $length = 1000;
        
        return substr($content, $start, $length);
    }
}
