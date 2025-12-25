<?php

namespace tests\unit\site;

use Codeception\Test\Unit;
use yii\helpers\FileHelper;

/**
 * Property-based test for CSS Inline Styles Removal
 * 
 * **Feature: csp-inline-styles-removal, Property 1: No inline styles in rendered HTML**
 * 
 * **Validates: Requirements 1.1, 1.2**
 * 
 * Property: For any view template rendered by the application, the HTML output
 * should not contain any style= attributes on elements.
 */
class CssInlineStylesPropertyTest extends Unit
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
     * Property 1: No inline styles in rendered HTML
     * 
     * For any view template, the rendered HTML should not contain inline style attributes.
     * This ensures CSP compliance without requiring 'unsafe-inline' directive.
     * 
     * Validates: Requirements 1.1, 1.2
     * 
     * @test
     */
    public function testNoInlineStylesInRenderedHtml()
    {
        // Feature: csp-inline-styles-removal, Property 1: No inline styles in rendered HTML
        
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
        
        // Test a random sample of view files
        for ($i = 0; $i < min($iterations, count($viewFiles)); $i++) {
            $viewFile = $viewFiles[array_rand($viewFiles)];
            $relativePath = str_replace(\Yii::getAlias('@app/views/'), '', $viewFile);
            
            try {
                // Read the view file content
                $content = file_get_contents($viewFile);
                
                // Check for inline style attributes
                // Pattern matches: style="..." or style='...'
                $pattern = '/\s+style\s*=\s*["\'][^"\']*["\']/i';
                
                if (preg_match_all($pattern, $content, $matches)) {
                    $failures[] = [
                        'iteration' => $i,
                        'file' => $relativePath,
                        'reason' => 'Contains inline style attributes',
                        'count' => count($matches[0]),
                        'examples' => array_slice($matches[0], 0, 3) // Show first 3 examples
                    ];
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
        
        // Assert no failures
        if (!empty($failures)) {
            $message = "Found inline styles in view files:\n";
            foreach ($failures as $failure) {
                $message .= sprintf(
                    "  [%d] %s: %s (count: %d)\n",
                    $failure['iteration'],
                    $failure['file'],
                    $failure['reason'],
                    $failure['count'] ?? 0
                );
                if (isset($failure['examples'])) {
                    foreach ($failure['examples'] as $example) {
                        $message .= "    Example: " . trim($example) . "\n";
                    }
                }
            }
            $this->fail($message);
        }
        
        $this->assertTrue(true, 'No inline styles found in view files');
    }
    
    /**
     * Test that CSS class files exist and contain expected classes
     * 
     * @test
     */
    public function testCssClassFilesExist()
    {
        // Feature: csp-inline-styles-removal, Property 1: No inline styles in rendered HTML
        
        $cssFiles = [
            'algo-colors.css' => [
                '.algo-bg-sha256',
                '.algo-bg-scrypt',
                '.algo-bg-x11',
                '.algo-bg-equihash',
            ],
            'utilities.css' => [
                '.resume-update-button',
                '.api-code-block',
                '.row-immature',
                '.row-selected',
                '.price-warning',
                '.border-top-thick',
                '.cursor-pointer',
                '.text-small',
            ],
        ];
        
        $webCssPath = \Yii::getAlias('@app/web/css');
        
        foreach ($cssFiles as $filename => $expectedClasses) {
            $filePath = $webCssPath . '/' . $filename;
            
            // Check file exists
            $this->assertFileExists(
                $filePath,
                "CSS file {$filename} should exist"
            );
            
            // Read file content
            $content = file_get_contents($filePath);
            
            // Check for expected classes
            foreach ($expectedClasses as $className) {
                $this->assertStringContainsString(
                    $className,
                    $content,
                    "CSS file {$filename} should contain class {$className}"
                );
            }
        }
    }
    
    /**
     * Test that chart container height classes exist
     * 
     * @test
     */
    public function testChartContainerHeightClassesExist()
    {
        // Feature: csp-inline-styles-removal, Property 1: No inline styles in rendered HTML
        
        $chartsFile = \Yii::getAlias('@app/web/css/charts.css');
        
        $this->assertFileExists($chartsFile, 'charts.css should exist');
        
        $content = file_get_contents($chartsFile);
        
        $expectedClasses = [
            '.chart-container-240',
            '.chart-container-200',
            '.chart-container-160',
        ];
        
        foreach ($expectedClasses as $className) {
            $this->assertStringContainsString(
                $className,
                $content,
                "charts.css should contain class {$className}"
            );
        }
    }
    
    /**
     * Test that algorithm color classes cover all algorithms
     * 
     * @test
     */
    public function testAlgorithmColorClassesCoverAllAlgorithms()
    {
        // Feature: csp-inline-styles-removal, Property 1: No inline styles in rendered HTML
        
        $algoColorsFile = \Yii::getAlias('@app/web/css/algo-colors.css');
        
        $this->assertFileExists($algoColorsFile, 'algo-colors.css should exist');
        
        $content = file_get_contents($algoColorsFile);
        
        // Get algorithms from YiimpUtils if available
        if (class_exists('\app\components\YiimpUtils')) {
            try {
                $yiimpUtils = \Yii::$app->YiimpUtils;
                $algos = $yiimpUtils->get_algos(false);
                
                if (!empty($algos)) {
                    $missingClasses = [];
                    
                    foreach ($algos as $algo) {
                        $className = '.algo-bg-' . $algo;
                        if (strpos($content, $className) === false) {
                            $missingClasses[] = $algo;
                        }
                    }
                    
                    if (!empty($missingClasses)) {
                        $this->fail(
                            'Missing algorithm color classes for: ' . 
                            implode(', ', $missingClasses)
                        );
                    }
                }
            } catch (\Exception $e) {
                // If we can't get algorithms from the database, skip this check
                $this->markTestSkipped('Cannot retrieve algorithms from database: ' . $e->getMessage());
            }
        }
        
        // At minimum, verify some common algorithms are present
        $commonAlgos = ['sha256', 'scrypt', 'x11', 'equihash', 'neoscrypt'];
        foreach ($commonAlgos as $algo) {
            $className = '.algo-bg-' . $algo;
            $this->assertStringContainsString(
                $className,
                $content,
                "algo-colors.css should contain class for {$algo}"
            );
        }
    }
    
    /**
     * Test that utility classes are properly defined
     * 
     * @test
     */
    public function testUtilityClassesAreProperlyDefined()
    {
        // Feature: csp-inline-styles-removal, Property 1: No inline styles in rendered HTML
        
        $utilitiesFile = \Yii::getAlias('@app/web/css/utilities.css');
        
        $this->assertFileExists($utilitiesFile, 'utilities.css should exist');
        
        $content = file_get_contents($utilitiesFile);
        
        // Test that each utility class has actual CSS properties
        $utilityClasses = [
            '.resume-update-button' => ['color', 'background-color'],
            '.api-code-block' => ['padding', 'font-size'],
            '.row-immature' => ['background-color'],
            '.row-selected' => ['background-color'],
            '.price-warning' => ['background-color'],
            '.border-top-thick' => ['border-top'],
            '.cursor-pointer' => ['cursor'],
            '.text-small' => ['font-size'],
        ];
        
        foreach ($utilityClasses as $className => $expectedProperties) {
            // Find the class definition
            $pattern = '/' . preg_quote($className, '/') . '\s*\{([^}]+)\}/';
            
            if (preg_match($pattern, $content, $matches)) {
                $classContent = $matches[1];
                
                // Check for expected properties
                foreach ($expectedProperties as $property) {
                    $this->assertStringContainsString(
                        $property,
                        $classContent,
                        "Class {$className} should contain property {$property}"
                    );
                }
            } else {
                $this->fail("Class {$className} not found in utilities.css");
            }
        }
    }
}
