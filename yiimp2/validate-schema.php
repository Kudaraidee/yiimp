#!/usr/bin/env php
<?php
/**
 * Database Schema Validation Script
 * 
 * Validates that all Active Record models align with the database schema.
 * Checks for missing tables, columns, and indexes.
 * 
 * Usage:
 *   php validate-schema.php
 *   php validate-schema.php --verbose
 *   php validate-schema.php --format=json
 */

// Parse command line arguments
$options = [
    'verbose' => false,
    'format' => 'text',
];

foreach ($argv as $arg) {
    if ($arg === '--verbose') {
        $options['verbose'] = true;
    } elseif (strpos($arg, '--format=') === 0) {
        $options['format'] = substr($arg, 9);
    }
}

// Load serverconfig.php from multiple possible locations
$configPaths = [
    '/etc/yiimp2/serverconfig.php',
    '/etc/yiimp/serverconfig.php',
    __DIR__ . '/../config/serverconfig.php',
];

$configLoaded = false;
foreach ($configPaths as $path) {
    if (file_exists($path)) {
        require_once($path);
        $configLoaded = true;
        break;
    }
}

if (!$configLoaded) {
    echo "Error: Could not find serverconfig.php\n";
    echo "Tried locations:\n";
    foreach ($configPaths as $path) {
        echo "  - $path\n";
    }
    exit(1);
}

// Define YIIMP constants if not already defined
if (!defined('YIIMP_DBHOST')) define('YIIMP_DBHOST', defined('YAAMP_DBHOST') ? YAAMP_DBHOST : 'localhost');
if (!defined('YIIMP_DBNAME')) define('YIIMP_DBNAME', defined('YAAMP_DBNAME') ? YAAMP_DBNAME : 'yaamp');
if (!defined('YIIMP_DBUSER')) define('YIIMP_DBUSER', defined('YAAMP_DBUSER') ? YAAMP_DBUSER : 'root');
if (!defined('YIIMP_DBPASSWORD')) define('YIIMP_DBPASSWORD', defined('YAAMP_DBPASSWORD') ? YAAMP_DBPASSWORD : '');

// Connect to database
try {
    $dsn = "mysql:host=" . YIIMP_DBHOST . ";dbname=" . YIIMP_DBNAME . ";charset=utf8";
    $pdo = new PDO($dsn, YIIMP_DBUSER, YIIMP_DBPASSWORD, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    
    if ($options['format'] === 'text') {
        echo "Database Schema Validation\n";
        echo str_repeat("=", 80) . "\n\n";
        echo "✓ Database connection successful\n";
        echo "  Database: " . YIIMP_DBNAME . " @ " . YIIMP_DBHOST . "\n\n";
    }
} catch (PDOException $e) {
    echo "✗ Database connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Model definitions with their expected attributes
$models = [
    'Accounts' => ['id', 'username', 'coinsymbol', 'balance', 'email', 'password', 'pin', 'donation', 'no_fees', 'is_locked', 'swap_time'],
    'Algos' => ['id', 'name', 'port', 'color'],
    'Balances' => ['id', 'userid', 'coinid', 'balance', 'last_payout_time'],
    'BenchChips' => ['id', 'chip', 'algo', 'hashrate', 'power'],
    'Benchmarks' => ['id', 'algo', 'type', 'chip', 'hashrate', 'power', 'client'],
    'Blocks' => ['id', 'coin_id', 'coinid', 'userid', 'workerid', 'category', 'difficulty', 'difficulty_user', 'blockhash', 'height', 'amount', 'confirmations', 'time', 'txhash', 'segwit', 'solo', 'algo', 'price', 'effort'],
    'Bookmarks' => ['id', 'userid', 'coinid', 'algo'],
    'Coins' => ['id', 'name', 'symbol', 'symbol2', 'algo', 'version', 'image', 'market', 'marketid', 'master_wallet', 'charity_address', 'charity_amount', 'charity_percent', 'deposit_address', 'deposit_minimum', 'sellonbid', 'dontsell', 'block_explorer', 'index_avg', 'connections', 'enable', 'visible', 'auto_ready', 'auxpow', 'rpcencoding', 'difficulty', 'reward', 'price', 'block_time', 'actual_ttf', 'powlimit_bits', 'decimals', 'no_explorer', 'rpcuser', 'rpcpasswd', 'rpchost', 'rpcport'],
    'Earnings' => ['id', 'userid', 'coinid', 'blockid', 'create_time', 'amount', 'price', 'status', 'mature_time'],
    'Hashrate' => ['id', 'time', 'algo', 'hashrate', 'hashrate_bad', 'hashrate_solo'],
    'Hashrenter' => ['id', 'time', 'renterid', 'hashrate'],
    'Hashuser' => ['id', 'time', 'userid', 'algo', 'hashrate'],
    'Jobs' => ['id', 'coinid', 'jobid', 'block_hash', 'block_bits', 'block_time', 'block_height', 'block_reward'],
    'Jobsubmits' => ['id', 'jobid', 'workerid', 'time', 'extranonce1', 'extranonce2', 'nonce', 'ntime', 'algo', 'difficulty', 'share_diff', 'error'],
    'MarketHistory' => ['id', 'idcoin', 'time', 'balance', 'buyonbid', 'sellonbid', 'lasttraded'],
    'Markets' => ['id', 'coinid', 'name', 'marketid', 'balance', 'balancetime', 'deposit_address', 'lastsent', 'lasttraded', 'ontrade', 'message'],
    'Mining' => ['id', 'userid', 'coinid', 'time', 'accepted', 'rejected'],
    'Nicehash' => ['id', 'algo', 'price', 'btc', 'workers', 'time'],
    'Orders' => ['id', 'renterid', 'coinid', 'algo', 'amount', 'price', 'status', 'time'],
    'Payouts' => ['id', 'account_id', 'coinid', 'time', 'amount', 'tx', 'completed'],
    'Renters' => ['id', 'name', 'address', 'balance', 'updated'],
    'Rentertxs' => ['id', 'renterid', 'time', 'amount', 'tx'],
    'Shares' => ['id', 'userid', 'workerid', 'coinid', 'time', 'valid', 'difficulty', 'algo', 'solo', 'blocknumber'],
    'Stats' => ['id', 'time', 'algo', 'hashrate', 'hashrate_bad', 'hashrate_solo', 'workers', 'workers_solo'],
    'Stratums' => ['id', 'algo', 'host', 'port', 'password', 'max_ttf'],
    'Workers' => ['id', 'userid', 'name', 'worker', 'algo', 'ip', 'dns', 'nonce1', 'version', 'difficulty', 'subscribe', 'password', 'pid', 'time'],
];

$report = [
    'timestamp' => date('Y-m-d H:i:s'),
    'database' => YIIMP_DBNAME . ' @ ' . YIIMP_DBHOST,
    'summary' => [
        'total_models' => count($models),
        'models_ok' => 0,
        'models_with_issues' => 0,
        'missing_tables' => 0,
        'missing_columns' => 0,
        'missing_indexes' => 0,
    ],
    'models' => [],
];

// Validate each model
foreach ($models as $modelName => $expectedAttributes) {
    $tableName = strtolower($modelName);
    
    $modelReport = [
        'model' => $modelName,
        'table' => $tableName,
        'has_issues' => false,
        'missing_tables' => [],
        'missing_columns' => [],
        'missing_indexes' => [],
        'extra_columns' => [],
    ];

    // Check if table exists
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE '$tableName'");
        $tableExists = $stmt->fetch();
        
        if (!$tableExists) {
            $modelReport['has_issues'] = true;
            $modelReport['missing_tables'][] = $tableName;
            $report['summary']['missing_tables']++;
            
            if ($options['format'] === 'text') {
                echo "✗ $modelName: Table '$tableName' does not exist\n";
            }
            
            $report['models'][] = $modelReport;
            $report['summary']['models_with_issues']++;
            continue;
        }

        // Get table columns
        $stmt = $pdo->query("SHOW COLUMNS FROM `$tableName`");
        $columns = $stmt->fetchAll();
        $dbColumns = array_column($columns, 'Field');

        if ($options['format'] === 'text') {
            echo "✓ $modelName ($tableName)\n";
        }

        // Check for missing columns
        foreach ($expectedAttributes as $attribute) {
            if (!in_array($attribute, $dbColumns)) {
                $modelReport['has_issues'] = true;
                $modelReport['missing_columns'][] = $attribute;
                $report['summary']['missing_columns']++;
                
                if ($options['format'] === 'text') {
                    echo "  ✗ Missing column: $attribute\n";
                }
            }
        }

        // Check for extra columns (in DB but not in model)
        if ($options['verbose']) {
            foreach ($dbColumns as $column) {
                if (!in_array($column, $expectedAttributes) && $column !== 'id') {
                    $modelReport['extra_columns'][] = $column;
                    
                    if ($options['format'] === 'text') {
                        echo "  ⚠ Extra column in DB: $column\n";
                    }
                }
            }
        }

        // Check indexes
        $indexRecommendations = validateIndexes($pdo, $tableName, $dbColumns);
        if (!empty($indexRecommendations)) {
            $modelReport['missing_indexes'] = $indexRecommendations;
            $report['summary']['missing_indexes'] += count($indexRecommendations);
            
            if ($options['verbose'] && $options['format'] === 'text') {
                foreach ($indexRecommendations as $indexInfo) {
                    echo "  ⚠ Recommended index: $indexInfo\n";
                }
            }
        }

        if (!$modelReport['has_issues']) {
            $report['summary']['models_ok']++;
            if ($options['verbose'] && $options['format'] === 'text') {
                echo "  All columns present\n";
            }
        } else {
            $report['summary']['models_with_issues']++;
        }

    } catch (PDOException $e) {
        $modelReport['has_issues'] = true;
        $modelReport['error'] = $e->getMessage();
        $report['summary']['models_with_issues']++;
        
        if ($options['format'] === 'text') {
            echo "✗ $modelName: Error - " . $e->getMessage() . "\n";
        }
    }

    $report['models'][] = $modelReport;
}

/**
 * Validate indexes for a table
 */
function validateIndexes($pdo, $tableName, $dbColumns) {
    $recommendations = [];

    // Get existing indexes
    try {
        $stmt = $pdo->query("SHOW INDEX FROM `$tableName`");
        $indexes = $stmt->fetchAll();
        $existingIndexes = array_unique(array_column($indexes, 'Column_name'));
    } catch (PDOException $e) {
        return $recommendations;
    }

    // Check for common patterns that should have indexes
    $columnsToCheck = [
        'time' => 'Time-based queries',
        'userid' => 'User lookups',
        'workerid' => 'Worker lookups',
        'coinid' => 'Coin lookups',
        'coin_id' => 'Coin lookups',
        'algo' => 'Algorithm filtering',
        'valid' => 'Valid/invalid filtering',
        'category' => 'Category filtering',
        'enable' => 'Enabled/disabled filtering',
        'account_id' => 'Account lookups',
        'blockid' => 'Block lookups',
        'renterid' => 'Renter lookups',
    ];

    foreach ($columnsToCheck as $column => $reason) {
        if (in_array($column, $dbColumns) && !in_array($column, $existingIndexes)) {
            $recommendations[] = "$column ($reason)";
        }
    }

    return $recommendations;
}

// Output report
if ($options['format'] === 'json') {
    echo json_encode($report, JSON_PRETTY_PRINT) . "\n";
} else {
    outputTextReport($report, $options);
}

/**
 * Output text format report
 */
function outputTextReport($report, $options) {
    echo "\n" . str_repeat("=", 80) . "\n";
    echo "VALIDATION SUMMARY\n";
    echo str_repeat("=", 80) . "\n\n";

    $summary = $report['summary'];
    echo "Total Models:           {$summary['total_models']}\n";
    echo "Models OK:              {$summary['models_ok']}\n";
    
    if ($summary['models_with_issues'] > 0) {
        echo "Models with Issues:     {$summary['models_with_issues']}\n";
        echo "Missing Tables:         {$summary['missing_tables']}\n";
        echo "Missing Columns:        {$summary['missing_columns']}\n";
        
        if ($options['verbose']) {
            echo "Recommended Indexes:    {$summary['missing_indexes']}\n";
        }
    } else {
        echo "\n✓ All models validated successfully!\n";
    }

    // Detailed issues
    if ($summary['models_with_issues'] > 0) {
        echo "\n" . str_repeat("=", 80) . "\n";
        echo "DETAILED ISSUES\n";
        echo str_repeat("=", 80) . "\n\n";

        foreach ($report['models'] as $modelReport) {
            if (!$modelReport['has_issues']) {
                continue;
            }

            echo "{$modelReport['model']} ({$modelReport['table']})\n";

            if (!empty($modelReport['missing_tables'])) {
                echo "  Missing Tables:\n";
                foreach ($modelReport['missing_tables'] as $table) {
                    echo "    - $table\n";
                }
            }

            if (!empty($modelReport['missing_columns'])) {
                echo "  Missing Columns:\n";
                foreach ($modelReport['missing_columns'] as $column) {
                    echo "    - $column\n";
                }
            }

            if ($options['verbose'] && !empty($modelReport['missing_indexes'])) {
                echo "  Recommended Indexes:\n";
                foreach ($modelReport['missing_indexes'] as $index) {
                    echo "    - $index\n";
                }
            }

            if (isset($modelReport['error'])) {
                echo "  Error: {$modelReport['error']}\n";
            }

            echo "\n";
        }
    }

    echo str_repeat("=", 80) . "\n";
}

// Exit with appropriate code
exit($report['summary']['models_with_issues'] > 0 ? 1 : 0);
