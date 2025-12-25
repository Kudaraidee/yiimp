<?php
/**
 * Script to automatically add DatabaseTestHelper trait and requireDatabase() calls
 * to tests that need database access
 */

$testDirs = [
    __DIR__ . '/unit/admin',
    __DIR__ . '/unit/backend',
    __DIR__ . '/unit/bench',
    __DIR__ . '/unit/explorer',
    __DIR__ . '/unit/site',
    __DIR__ . '/unit/models',
    __DIR__ . '/unit/controllers',
];

$filesFixed = 0;
$filesSkipped = 0;

foreach ($testDirs as $dir) {
    if (!is_dir($dir)) {
        continue;
    }
    
    $files = glob($dir . '/*PropertyTest.php');
    
    foreach ($files as $file) {
        $content = file_get_contents($file);
        $originalContent = $content;
        
        // Skip if already has DatabaseTestHelper
        if (strpos($content, 'use DatabaseTestHelper') !== false) {
            $filesSkipped++;
            continue;
        }
        
        // Skip if doesn't use database models
        $usesDatabase = (
            strpos($content, '::find()') !== false ||
            strpos($content, '->save()') !== false ||
            strpos($content, '->delete()') !== false ||
            strpos($content, 'ActiveRecord') !== false ||
            strpos($content, 'Yii::$app->db') !== false
        );
        
        if (!$usesDatabase) {
            $filesSkipped++;
            continue;
        }
        
        // Add use statement for DatabaseTestHelper
        $content = preg_replace(
            '/(namespace [^;]+;)/s',
            "$1\n\nuse tests\\helpers\\DatabaseTestHelper;",
            $content,
            1
        );
        
        // Add trait usage after class declaration
        $content = preg_replace(
            '/(class\s+\w+\s+extends\s+\w+\s*\{)/s',
            "$1\n    use DatabaseTestHelper;\n",
            $content,
            1
        );
        
        // Add requireDatabase() call at the beginning of test methods
        $content = preg_replace_callback(
            '/(public\s+function\s+test\w+\([^)]*\)\s*\{[^\n]*\n)(\s*\/\/[^\n]*\n)?(\s*)/s',
            function($matches) {
                $indent = $matches[3] ?? '        ';
                return $matches[1] . ($matches[2] ?? '') . $indent . "// Skip test if database is not available\n" . $indent . "\$this->requireDatabase();\n" . $indent;
            },
            $content
        );
        
        if ($content !== $originalContent) {
            file_put_contents($file, $content);
            echo "Fixed: " . basename($file) . "\n";
            $filesFixed++;
        } else {
            $filesSkipped++;
        }
    }
}

echo "\nSummary:\n";
echo "Files fixed: $filesFixed\n";
echo "Files skipped: $filesSkipped\n";
