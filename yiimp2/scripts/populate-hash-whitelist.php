<?php
/**
 * Populate CSP Hash Whitelist Script
 * 
 * This script populates the hash whitelist with the CSP violations
 * identified during the analysis.
 * 
 * Feature: csp-library-compliance
 * Task: 2.1 Create hash whitelist documentation
 */

// Bootstrap Yii application
require_once __DIR__ . '/../vendor/autoload.php';

// Define Yii constants and aliases
defined('YII_DEBUG') or define('YII_DEBUG', true);
defined('YII_ENV') or define('YII_ENV', 'dev');

require_once __DIR__ . '/../vendor/yiisoft/yii2/Yii.php';

$config = [
    'id' => 'csp-hash-whitelist-populator',
    'basePath' => dirname(__DIR__),
    'components' => [
        'cspHashWhitelistManager' => [
            'class' => 'app\components\CspHashWhitelistManager',
        ],
    ],
];

$app = new yii\console\Application($config);

echo "=== CSP Hash Whitelist Population ===\n";
echo "Populating whitelist with identified CSP violations...\n\n";

// Get the hash whitelist manager
$manager = $app->get('cspHashWhitelistManager');

// Identified hashes from the comprehensive analysis
$identifiedHashes = [
    [
        'hash' => 'sha256-3IL6vpnscstlAHatOzMR+Hq4pUBi+owMf+qTaCsqJy0=',
        'library' => 'jquery',
        'type' => 'style',
        'description' => 'jQuery inline style generation for DOM manipulation',
        'metadata' => [
            'sourceFile' => 'jquery.min.js',
            'violationCount' => 2,
            'analysisDate' => '2025-12-14',
            'pages' => ['homepage'],
            'browserTested' => 'Chrome, Firefox'
        ]
    ],
    [
        'hash' => 'sha256-VyHeNfMcVpEmF2DHZeJfhCiBXVr6MCvSeDVLrAcpKss=',
        'library' => 'jquery',
        'type' => 'style',
        'description' => 'jQuery CSS manipulation methods creating inline styles',
        'metadata' => [
            'sourceFile' => 'jquery.min.js',
            'violationCount' => 3,
            'analysisDate' => '2025-12-14',
            'pages' => ['homepage'],
            'browserTested' => 'Chrome, Firefox'
        ]
    ],
    [
        'hash' => 'sha256-yBuPLWyWmnBuGHKi2bpAWiKD5pr23Vgv5utcfNKmSys=',
        'library' => 'jquery',
        'type' => 'script',
        'description' => 'jQuery inline script execution for event handling',
        'metadata' => [
            'sourceFile' => 'jquery.min.js',
            'violationCount' => 2,
            'analysisDate' => '2025-12-14',
            'pages' => ['homepage'],
            'browserTested' => 'Chrome, Firefox'
        ]
    ],
    [
        'hash' => 'sha256-YzwpIdlhXTB126pb2q/vihpB+Wi0ZbB0pgkXIAOO3sk=',
        'library' => 'jquery',
        'type' => 'script',
        'description' => 'jQuery dynamic script content for DOM events',
        'metadata' => [
            'sourceFile' => 'jquery.min.js',
            'violationCount' => 2,
            'analysisDate' => '2025-12-14',
            'pages' => ['homepage'],
            'browserTested' => 'Chrome, Firefox'
        ]
    ]
];

// Additional hashes from Chart.js violations (external CDN scripts)
$chartJsHashes = [
    [
        'hash' => 'sha256-ChartJsCdnScript1234567890abcdef=', // Placeholder - would be real hash
        'library' => 'chartjs',
        'type' => 'script',
        'description' => 'Chart.js CDN script loading violation',
        'metadata' => [
            'sourceFile' => 'https://cdn.jsdelivr.net/npm/chart.js@4.5.1/dist/chart.umd.min.js',
            'violationCount' => 1,
            'analysisDate' => '2025-12-14',
            'pages' => ['stats'],
            'note' => 'External CDN script - consider hosting locally'
        ]
    ]
];

$successCount = 0;
$totalHashes = count($identifiedHashes);

echo "Adding {$totalHashes} identified hashes to whitelist...\n\n";

foreach ($identifiedHashes as $index => $hashData) {
    echo "Adding hash " . ($index + 1) . "/{$totalHashes}: {$hashData['hash']}\n";
    echo "  Library: {$hashData['library']}\n";
    echo "  Type: {$hashData['type']}\n";
    echo "  Description: {$hashData['description']}\n";
    
    $success = $manager->addApprovedHash(
        $hashData['hash'],
        $hashData['library'],
        $hashData['type'],
        $hashData['description'],
        $hashData['metadata']
    );
    
    if ($success) {
        echo "  ✓ Successfully added\n";
        $successCount++;
    } else {
        echo "  ✗ Failed to add\n";
    }
    echo "\n";
}

echo "=== WHITELIST POPULATION COMPLETE ===\n";
echo "Successfully added: {$successCount}/{$totalHashes} hashes\n\n";

// Display whitelist statistics
$stats = $manager->getWhitelistStats();
echo "=== WHITELIST STATISTICS ===\n";
echo "Total active hashes: {$stats['activeHashes']}\n";
echo "Script hashes: {$stats['byType']['script']}\n";
echo "Style hashes: {$stats['byType']['style']}\n";
echo "Libraries represented: " . count($stats['byLibrary']) . "\n";
echo "Last updated: {$stats['lastUpdated']}\n\n";

echo "Library breakdown:\n";
foreach ($stats['byLibrary'] as $library => $count) {
    echo "  - {$library}: {$count} hashes\n";
}
echo "\n";

// Generate CSP directives
echo "=== GENERATED CSP DIRECTIVES ===\n";
echo "Add these to your Content Security Policy:\n\n";
echo "script-src " . $manager->generateCspDirective('script-src') . ";\n";
echo "style-src " . $manager->generateCspDirective('style-src') . ";\n\n";

// Export documentation
echo "=== DOCUMENTATION EXPORT ===\n";
$documentation = $manager->exportDocumentation();
$docPath = dirname(__DIR__) . '/docs/csp-violations/managed-hash-whitelist.md';

// Ensure directory exists
$docDir = dirname($docPath);
if (!is_dir($docDir)) {
    mkdir($docDir, 0755, true);
}

file_put_contents($docPath, $documentation);
echo "Documentation exported to: {$docPath}\n";

echo "\nHash whitelist population complete!\n";