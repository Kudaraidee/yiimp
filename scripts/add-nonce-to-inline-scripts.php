#!/usr/bin/env php
<?php
/**
 * Add CSP nonce to inline scripts in view files
 */

$viewsDir = __DIR__ . '/../yiimp2/views';

$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($viewsDir),
    RecursiveIteratorIterator::SELF_FIRST
);

$updatedFiles = [];
$errors = [];

foreach ($files as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }
    
    $filePath = $file->getPathname();
    $content = file_get_contents($filePath);
    $originalContent = $content;
    
    // Check if file has inline scripts
    if (!preg_match('/<script[^>]*>/i', $content)) {
        continue;
    }
    
    // Check if CspHelper is already imported
    $hasCspHelper = preg_match('/use\s+app\\\\components\\\\CspHelper;/', $content);
    
    // Add CspHelper import if not present
    if (!$hasCspHelper && preg_match('/^<\?php\s*\n/m', $content)) {
        // Find the last use statement
        if (preg_match('/^use\s+[^;]+;$/m', $content, $matches, PREG_OFFSET_CAPTURE)) {
            $lastUsePos = $matches[0][1] + strlen($matches[0][0]);
            $content = substr_replace($content, "\nuse app\\components\\CspHelper;", $lastUsePos, 0);
        } else {
            // No use statements, add after <?php
            $content = preg_replace('/^(<\?php\s*\n)/m', "$1\nuse app\\components\\CspHelper;\n", $content);
        }
    }
    
    // Replace <script> with <?= CspHelper::beginScript() ?>
    $content = preg_replace(
        '/<script(\s+type=["\']text\/javascript["\'])?>/i',
        '<?= CspHelper::beginScript() ?>',
        $content
    );
    
    // Replace </script> with <?= CspHelper::endScript() ?>
    $content = preg_replace(
        '/<\/script>/i',
        '<?= CspHelper::endScript() ?>',
        $content
    );
    
    // Only update if content changed
    if ($content !== $originalContent) {
        if (file_put_contents($filePath, $content) !== false) {
            $updatedFiles[] = str_replace($viewsDir . '/', '', $filePath);
            echo "✓ Updated: " . str_replace($viewsDir . '/', '', $filePath) . "\n";
        } else {
            $errors[] = "Failed to write: " . str_replace($viewsDir . '/', '', $filePath);
            echo "✗ Failed: " . str_replace($viewsDir . '/', '', $filePath) . "\n";
        }
    }
}

echo "\n";
echo "Summary:\n";
echo "--------\n";
echo "Updated files: " . count($updatedFiles) . "\n";
echo "Errors: " . count($errors) . "\n";

if (!empty($errors)) {
    echo "\nErrors:\n";
    foreach ($errors as $error) {
        echo "  - $error\n";
    }
    exit(1);
}

exit(0);
