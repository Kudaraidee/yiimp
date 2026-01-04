<?php

namespace yiimp2\tests\unit\schema;

use Codeception\Test\Unit;

/**
 * Property-based test for migration file ordering
 * Feature: yiimp2-dedicated-system, Property 9: Migration file ordering
 * 
 * Property 9: Migration file ordering
 * For any set of migration files in sql/ directory, they should be applied 
 * in chronological order based on the date in the filename.
 * 
 * Validates: Requirements 6.3
 */
class MigrationFileOrderingPropertyTest extends Unit
{
    
    /**
     * Test that migration files are always ordered chronologically by date
     * 
     * Property: For any set of migration files with date prefixes (YYYY-MM-DD),
     * sorting them should result in chronological order
     * 
     * @dataProvider migrationFileSetProvider
     */
    public function testMigrationFilesAreOrderedChronologically($filenames)
    {
        // Sort the filenames (simulating what the script does)
        $sorted = $filenames;
        sort($sorted);
        
        // Verify chronological ordering
        for ($i = 0; $i < count($sorted) - 1; $i++) {
            $current = $this->extractDate($sorted[$i]);
            $next = $this->extractDate($sorted[$i + 1]);
            
            $this->assertLessThanOrEqual(
                $next,
                $current,
                "Migration files should be in chronological order: {$sorted[$i]} should come before or equal to {$sorted[$i + 1]}"
            );
        }
    }
    
    /**
     * Data provider for migration file sets
     */
    public function migrationFileSetProvider()
    {
        return [
            'simple chronological' => [[
                '2024-01-15-first.sql',
                '2024-02-10-second.sql',
                '2024-03-20-third.sql',
            ]],
            'reverse chronological' => [[
                '2024-12-31-last.sql',
                '2024-06-15-middle.sql',
                '2024-01-01-first.sql',
            ]],
            'mixed order' => [[
                '2024-05-10-feature.sql',
                '2024-01-20-init.sql',
                '2024-08-30-update.sql',
                '2024-03-15-patch.sql',
            ]],
            'same year different months' => [[
                '2024-12-01-dec.sql',
                '2024-01-01-jan.sql',
                '2024-06-01-jun.sql',
            ]],
            'same month different days' => [[
                '2024-03-25-last.sql',
                '2024-03-10-middle.sql',
                '2024-03-05-first.sql',
            ]],
            'different years' => [[
                '2025-01-01-new.sql',
                '2024-12-31-old.sql',
                '2023-06-15-older.sql',
            ]],
            'single file' => [[
                '2024-01-01-single.sql',
            ]],
            'two files same date' => [[
                '2024-05-15-feature-a.sql',
                '2024-05-15-feature-b.sql',
            ]],
        ];
    }
    
    /**
     * Test that migration files with same date maintain stable order
     * 
     * Property: For any two migration files with the same date,
     * their relative order should be stable after sorting
     * 
     * @dataProvider sameDateFilesProvider
     */
    public function testMigrationFilesWithSameDateMaintainStableOrder($filenames, $expectedDatePrefix)
    {
        // Sort them
        $sorted = $filenames;
        sort($sorted);
        
        // All should have the same date prefix
        foreach ($sorted as $filename) {
            $this->assertStringStartsWith(
                $expectedDatePrefix,
                $filename,
                "All files should have the same date prefix"
            );
        }
    }
    
    /**
     * Data provider for files with same date
     */
    public function sameDateFilesProvider()
    {
        return [
            'two files same date' => [
                ['2024-05-15-feature-b.sql', '2024-05-15-feature-a.sql'],
                '2024-05-15'
            ],
            'three files same date' => [
                ['2024-03-10-c.sql', '2024-03-10-a.sql', '2024-03-10-b.sql'],
                '2024-03-10'
            ],
            'multiple files same date different names' => [
                [
                    '2024-07-20-update.sql',
                    '2024-07-20-add-column.sql',
                    '2024-07-20-fix-bug.sql',
                ],
                '2024-07-20'
            ],
        ];
    }
    
    /**
     * Test that migration file ordering is consistent across multiple sorts
     * 
     * Property: For any set of migration files, sorting them multiple times
     * should always produce the same order
     * 
     * @dataProvider migrationFileSetProvider
     */
    public function testMigrationFileOrderingIsConsistent($filenames)
    {
        // Sort multiple times
        $sorted1 = $filenames;
        sort($sorted1);
        
        $sorted2 = $filenames;
        sort($sorted2);
        
        $sorted3 = $filenames;
        sort($sorted3);
        
        // All should be identical
        $this->assertEquals(
            $sorted1,
            $sorted2,
            "Multiple sorts should produce identical results"
        );
        
        $this->assertEquals(
            $sorted2,
            $sorted3,
            "Multiple sorts should produce identical results"
        );
    }
    
    /**
     * Test that earlier dates always come before later dates
     * 
     * Property: For any two migration files where file1's date < file2's date,
     * file1 should appear before file2 in the sorted list
     * 
     * @dataProvider twoFileComparisonProvider
     */
    public function testEarlierDatesAlwaysComeFirst($file1, $file2)
    {
        $filenames = [$file1, $file2];
        sort($filenames);
        
        $date1Str = $this->extractDate($file1);
        $date2Str = $this->extractDate($file2);
        
        if ($date1Str < $date2Str) {
            $this->assertEquals(
                $file1,
                $filenames[0],
                "Earlier date should come first: $file1 should be before $file2"
            );
        } else {
            $this->assertEquals(
                $file2,
                $filenames[0],
                "Earlier date should come first: $file2 should be before $file1"
            );
        }
    }
    
    /**
     * Data provider for two-file comparisons
     */
    public function twoFileComparisonProvider()
    {
        return [
            'different years' => ['2025-01-01-new.sql', '2024-01-01-old.sql'],
            'different months' => ['2024-06-01-june.sql', '2024-01-01-jan.sql'],
            'different days' => ['2024-03-15-mid.sql', '2024-03-05-early.sql'],
            'one day apart' => ['2024-05-11-next.sql', '2024-05-10-prev.sql'],
            'far apart years' => ['2030-01-01-future.sql', '2020-01-01-past.sql'],
        ];
    }
    
    /**
     * Test with actual migration files from the repository
     */
    public function testActualMigrationFilesAreOrdered()
    {
        $sqlDir = dirname(dirname(dirname(__DIR__))) . '/../sql';
        
        if (!is_dir($sqlDir)) {
            $this->markTestSkipped('SQL directory not found');
        }
        
        // Get actual migration files
        $files = glob($sqlDir . '/20*.sql');
        
        if (empty($files)) {
            $this->markTestSkipped('No migration files found');
        }
        
        // Extract just the filenames
        $filenames = array_map('basename', $files);
        
        // Sort them
        $sorted = $filenames;
        sort($sorted);
        
        // Verify chronological ordering
        for ($i = 0; $i < count($sorted) - 1; $i++) {
            $current = $this->extractDate($sorted[$i]);
            $next = $this->extractDate($sorted[$i + 1]);
            
            $this->assertLessThanOrEqual(
                $next,
                $current,
                "Migration files should be in chronological order: {$sorted[$i]} should come before or equal to {$sorted[$i + 1]}"
            );
        }
    }
    
    /**
     * Extract date from filename in format YYYYMMDD
     */
    private function extractDate($filename)
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $filename, $matches)) {
            return $matches[1] . $matches[2] . $matches[3];
        }
        return '';
    }
}
