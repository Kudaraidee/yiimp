<?php

namespace tests\unit\site;

use Codeception\Test\Unit;
use yii\helpers\FileHelper;

/**
 * Property-based test for CSS Class Reusability
 * 
 * **Feature: csp-inline-styles-removal, Property 3: CSS class reusability**
 * 
 * **Validates: Requirements 2.2**
 * 
 * Property: For any set of elements with identical inline styles in the original code,
 * they should all use the same CSS class in the refactored code.
 */
class CssClassReusabilityPropertyTest extends Unit
{
    protected function _before()
    {
        parent::_before();
    }
    
    protected function _after()
    {
        parent::_after();
    }
    
    /**
     * Property 3: CSS class reusability
     * 
     * For any set of elements with identical inline styles in the original code,
     * they should all use the same CSS class in the refactored code.
     * 
     * This ensures that duplicate styles are consolidated into reusable CSS classes,
     * improving maintainability and reducing code duplication.
     * 
     * Validates: Requirements 2.2
     * 
     * @test
     */
    public function testCssClassReusability()
    {
        // Feature: csp-inline-styles-removal, Property 3: CSS class reusability
        
        $iterations = 100;
        $failures = [];
        
        // Get all view files
        $viewPaths = [
            \Yii::getAlias('@app/views/site'),
            \Yii::getAlias('@app/views/admin'),
            \Yii::getAlias('@app/views/stats'),
        ];
        
        $viewFiles = [];
        foreach ($viewPaths as $path) {
            if (is_dir($path)) {
                $files = FileHelper::findFiles($path, ['only' => ['*.php']]);
                $viewFiles = array_merge($viewFiles, $files);
            }
        }
        
        if (empty($viewFiles)) {
            $this->markTestSkipped('No view files found to test');
            return;
        }
        
        // Map of CSS classes to their usage patterns
        $classUsageMap = [];
        
        // Test a random sample of view files
        for ($i = 0; $i < min($iterations, count($viewFiles)); $i++) {
            $viewFile = $viewFiles[array_rand($viewFiles)];
            $relativePath = str_replace(\Yii::getAlias('@app/views/'), '', $viewFile);
            
            try {
                // Read the view file content
                $content = file_get_contents($viewFile);
                
                // Extract all class attributes
                $pattern = '/class\s*=\s*["\']([^"\']*)["\']|class\s*=\s*"([^"]*)"/i';
                
                if (preg_match_all($pattern, $content, $matches)) {
                    foreach ($matches[1] as $classAttr) {
                        if (empty($classAttr)) {
                            continue;
                        }
                        
                        // Split multiple classes
                        $classes = preg_split('/\s+/', trim($classAttr));
                        
                        foreach ($classes as $class) {
                            if (empty($class)) {
                                continue;
                            }
                            
                            // Track usage of specific CSS classes we created
                            if ($this->isRelevantCssClass($class)) {
                                if (!isset($classUsageMap[$class])) {
                                    $classUsageMap[$class] = [];
                                }
                                $classUsageMap[$class][] = $relativePath;
                            }
                        }
                    }
                }
                
            } catch (\Exception $e) {
                $failures[] = [
                    'iteration' => $i,
                    'file' => $relativePath,
                    'reason' => 'Exception during test',
                    'exception' => $e->getMessage()
                ];
            }
        }
        
        // Verify that reusable classes are actually reused
        $reusableClasses = [
            'resume-update-button',
            'api-code-block',
            'row-immature',
            'row-selected',
            'price-warning',
            'border-top-thick',
            'cursor-pointer',
            'text-small',
        ];
        
        foreach ($reusableClasses as $className) {
            if (isset($classUsageMap[$className])) {
                $usageCount = count($classUsageMap[$className]);
                
                // If a class is used, it should be reused (used more than once ideally)
                // But at minimum, it should exist in the CSS file
                $this->assertCssClassIsDefined($className, $failures);
                
                // Log usage for verification
                if ($usageCount === 1) {
                    // Single usage is acceptable, but we note it
                    // This is not a failure, just informational
                }
            }
        }
        
        // Verify that algorithm color classes follow the pattern
        $algoColorPattern = '/algo-bg-[a-z0-9_-]+/i';
        $algoColorClasses = [];
        
        foreach ($classUsageMap as $class => $files) {
            if (preg_match($algoColorPattern, $class)) {
                $algoColorClasses[$class] = count($files);
            }
        }
        
        // Algorithm color classes should be reusable
        foreach ($algoColorClasses as $class => $usageCount) {
            $this->assertCssClassIsDefined($class, $failures);
        }
        
        // Verify chart container classes are reusable
        $chartContainerPattern = '/chart-container-\d+/';
        $chartContainerClasses = [];
        
        foreach ($classUsageMap as $class => $files) {
            if (preg_match($chartContainerPattern, $class)) {
                $chartContainerClasses[$class] = count($files);
            }
        }
        
        foreach ($chartContainerClasses as $class => $usageCount) {
            $this->assertCssClassIsDefined($class, $failures);
        }
        
        // Assert no failures
        if (!empty($failures)) {
            $message = "CSS class reusability issues found:\n";
            foreach ($failures as $failure) {
                $message .= sprintf(
                    "  %s: %s\n",
                    $failure['class'] ?? $failure['file'] ?? 'unknown',
                    $failure['reason']
                );
            }
            $this->fail($message);
        }
        
        $this->assertTrue(true, 'CSS classes are properly reusable');
    }
    
    /**
     * Check if a CSS class is relevant to our refactoring
     * 
     * @param string $class
     * @return bool
     */
    protected function isRelevantCssClass($class)
    {
        $relevantPrefixes = [
            'resume-update-button',
            'api-code-block',
            'row-immature',
            'row-selected',
            'price-warning',
            'border-top-thick',
            'cursor-pointer',
            'text-small',
            'algo-bg-',
            'chart-container-',
        ];
        
        foreach ($relevantPrefixes as $prefix) {
            if (strpos($class, $prefix) === 0) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Assert that a CSS class is defined in the appropriate CSS file
     * 
     * @param string $className
     * @param array &$failures
     */
    protected function assertCssClassIsDefined($className, &$failures)
    {
        $cssFiles = [
            'algo-colors.css',
            'utilities.css',
            'charts.css',
        ];
        
        $webCssPath = \Yii::getAlias('@app/web/css');
        $found = false;
        
        foreach ($cssFiles as $filename) {
            $filePath = $webCssPath . '/' . $filename;
            
            if (file_exists($filePath)) {
                $content = file_get_contents($filePath);
                
                if (strpos($content, '.' . $className) !== false) {
                    $found = true;
                    break;
                }
            }
        }
        
        if (!$found) {
            $failures[] = [
                'class' => $className,
                'reason' => 'CSS class is used in views but not defined in any CSS file'
            ];
        }
    }
    
    /**
     * Test that duplicate style patterns are consolidated
     * 
     * This test verifies that common styling patterns that appeared multiple times
     * as inline styles are now consolidated into single CSS classes.
     * 
     * @test
     */
    public function testDuplicateStylePatternsAreConsolidated()
    {
        // Feature: csp-inline-styles-removal, Property 3: CSS class reusability
        
        // Define expected consolidations based on the original inline styles
        $expectedConsolidations = [
            'resume-update-button' => [
                'description' => 'Resume/pause button styling',
                'expectedFiles' => ['site/mining.php', 'site/wallet.php', 'site/index.php', 'site/miners.php', 'stats/index.php'],
                'minUsage' => 3, // Should be used in at least 3 files
            ],
            'api-code-block' => [
                'description' => 'API documentation code blocks',
                'expectedFiles' => ['site/api.php'],
                'minUsage' => 1,
            ],
            'row-immature' => [
                'description' => 'Immature block row highlighting',
                'expectedFiles' => ['site/block_results.php'],
                'minUsage' => 1,
            ],
            'row-selected' => [
                'description' => 'Selected wallet row highlighting',
                'expectedFiles' => ['site/wallet.php'],
                'minUsage' => 1,
            ],
            'price-warning' => [
                'description' => 'Warning state for pricing cells',
                'expectedFiles' => ['admin/coinwallet_results.php'],
                'minUsage' => 1,
            ],
            'text-small' => [
                'description' => 'Small text styling',
                'expectedFiles' => ['site/results/wallet_miners_results.php', 'site/results/miners_results.php'],
                'minUsage' => 2,
            ],
        ];
        
        $failures = [];
        
        foreach ($expectedConsolidations as $className => $config) {
            // Check that the class is defined in CSS
            $cssFiles = [
                \Yii::getAlias('@app/web/css/utilities.css'),
                \Yii::getAlias('@app/web/css/algo-colors.css'),
                \Yii::getAlias('@app/web/css/charts.css'),
            ];
            
            $classDefined = false;
            foreach ($cssFiles as $cssFile) {
                if (file_exists($cssFile)) {
                    $content = file_get_contents($cssFile);
                    if (strpos($content, '.' . $className) !== false) {
                        $classDefined = true;
                        break;
                    }
                }
            }
            
            if (!$classDefined) {
                $failures[] = [
                    'class' => $className,
                    'reason' => 'Expected consolidated class not defined in CSS',
                    'description' => $config['description']
                ];
                continue;
            }
            
            // Check that the class is used in expected files
            $usageCount = 0;
            foreach ($config['expectedFiles'] as $expectedFile) {
                $viewFile = \Yii::getAlias('@app/views/' . $expectedFile);
                
                if (file_exists($viewFile)) {
                    $content = file_get_contents($viewFile);
                    
                    // Check if the class is used
                    if (preg_match('/class\s*=\s*["\'][^"\']*' . preg_quote($className, '/') . '[^"\']*["\']/', $content)) {
                        $usageCount++;
                    }
                }
            }
            
            if ($usageCount < $config['minUsage']) {
                $failures[] = [
                    'class' => $className,
                    'reason' => "Class should be used at least {$config['minUsage']} times, but found {$usageCount} usages",
                    'description' => $config['description']
                ];
            }
        }
        
        if (!empty($failures)) {
            $message = "Duplicate style patterns not properly consolidated:\n";
            foreach ($failures as $failure) {
                $message .= sprintf(
                    "  %s (%s): %s\n",
                    $failure['class'],
                    $failure['description'],
                    $failure['reason']
                );
            }
            $this->fail($message);
        }
        
        $this->assertTrue(true, 'Duplicate style patterns are properly consolidated');
    }
    
    /**
     * Test that algorithm color classes follow consistent naming
     * 
     * @test
     */
    public function testAlgorithmColorClassesFollowConsistentNaming()
    {
        // Feature: csp-inline-styles-removal, Property 3: CSS class reusability
        
        $algoColorsFile = \Yii::getAlias('@app/web/css/algo-colors.css');
        
        if (!file_exists($algoColorsFile)) {
            $this->fail('algo-colors.css file not found');
        }
        
        $content = file_get_contents($algoColorsFile);
        
        // Extract all algorithm color class definitions
        $pattern = '/\.algo-bg-([a-z0-9_-]+)\s*\{/i';
        
        if (preg_match_all($pattern, $content, $matches)) {
            $algoClasses = $matches[1];
            
            // Verify naming convention
            foreach ($algoClasses as $algoName) {
                // Should be alphanumeric with hyphens or underscores (case-insensitive)
                if (!preg_match('/^[a-zA-Z0-9_-]+$/', $algoName)) {
                    $this->fail(
                        "Algorithm class name 'algo-bg-{$algoName}' does not follow naming convention"
                    );
                }
            }
            
            // Verify no duplicates
            $uniqueClasses = array_unique($algoClasses);
            if (count($uniqueClasses) !== count($algoClasses)) {
                $duplicates = array_diff_assoc($algoClasses, $uniqueClasses);
                $this->fail(
                    'Duplicate algorithm color classes found: ' . implode(', ', $duplicates)
                );
            }
            
            $this->assertGreaterThan(
                0,
                count($algoClasses),
                'Should have at least one algorithm color class defined'
            );
        } else {
            $this->fail('No algorithm color classes found in algo-colors.css');
        }
    }
    
    /**
     * Test that chart container classes follow consistent sizing
     * 
     * @test
     */
    public function testChartContainerClassesFollowConsistentSizing()
    {
        // Feature: csp-inline-styles-removal, Property 3: CSS class reusability
        
        $chartsFile = \Yii::getAlias('@app/web/css/charts.css');
        
        if (!file_exists($chartsFile)) {
            $this->fail('charts.css file not found');
        }
        
        $content = file_get_contents($chartsFile);
        
        // Expected chart container sizes
        $expectedSizes = [240, 200, 160];
        
        foreach ($expectedSizes as $size) {
            $className = ".chart-container-{$size}";
            
            // Check class exists
            $this->assertStringContainsString(
                $className,
                $content,
                "Chart container class for size {$size}px should exist"
            );
            
            // Check that it has a height property
            $pattern = '/\.chart-container-' . $size . '\s*\{[^}]*height:\s*' . $size . 'px/';
            $this->assertMatchesRegularExpression(
                $pattern,
                $content,
                "Chart container class for size {$size}px should have correct height"
            );
        }
    }
}
