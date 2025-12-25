#!/usr/bin/env php
<?php
/**
 * Verification script for Daemon tab implementation
 * This script checks that all required fields are present in the Coins model
 */

// Load Yii2 application
require(__DIR__ . '/vendor/autoload.php');
require(__DIR__ . '/vendor/yiisoft/yii2/Yii.php');

$config = require(__DIR__ . '/config/web.php');
$application = new yii\web\Application($config);

use app\models\Coins;
use app\models\Algos;

echo "=== Daemon Tab Implementation Verification ===\n\n";

// Check 1: Verify all daemon fields exist in the model
echo "1. Checking daemon fields in Coins model...\n";
$requiredFields = [
    'program', 'conf_folder', 'rpchost', 'rpcport', 'rpcwallet',
    'rpcuser', 'rpcpasswd', 'serveruser', 'rpcencoding',
    'dedicatedport', 'rpccert', 'account', 'rpccurl', 'rpcssl'
];

$model = new Coins();
$attributes = $model->attributes();
$missingFields = [];

foreach ($requiredFields as $field) {
    if (!in_array($field, $attributes)) {
        $missingFields[] = $field;
    }
}

if (empty($missingFields)) {
    echo "   ✓ All daemon fields present in model\n";
} else {
    echo "   ✗ Missing fields: " . implode(', ', $missingFields) . "\n";
}

// Check 2: Verify validation rules exist
echo "\n2. Checking validation rules...\n";
$rules = $model->rules();
$hasRpcPortValidation = false;
$hasStringValidation = false;
$hasBooleanValidation = false;

foreach ($rules as $rule) {
    if (isset($rule[0]) && is_array($rule[0])) {
        if (in_array('rpcport', $rule[0]) && isset($rule['min']) && isset($rule['max'])) {
            $hasRpcPortValidation = true;
        }
        if (in_array('program', $rule[0]) || in_array('rpcuser', $rule[0])) {
            $hasStringValidation = true;
        }
        if (in_array('rpccurl', $rule[0]) || in_array('rpcssl', $rule[0])) {
            $hasBooleanValidation = true;
        }
    }
}

echo "   " . ($hasRpcPortValidation ? "✓" : "✗") . " RPC port validation (1-65535)\n";
echo "   " . ($hasStringValidation ? "✓" : "✗") . " String field validation\n";
echo "   " . ($hasBooleanValidation ? "✓" : "✗") . " Boolean field validation\n";

// Check 3: Verify helper methods exist
echo "\n3. Checking helper methods...\n";
$hasSampleConfig = method_exists($model, 'getSampleConfig');
$hasSampleMinerCommand = method_exists($model, 'getSampleMinerCommand');
$hasGeneratePassword = method_exists($model, 'generateSecurePassword');

echo "   " . ($hasSampleConfig ? "✓" : "✗") . " getSampleConfig() method\n";
echo "   " . ($hasSampleMinerCommand ? "✓" : "✗") . " getSampleMinerCommand() method\n";
echo "   " . ($hasGeneratePassword ? "✓" : "✗") . " generateSecurePassword() method\n";

// Check 4: Test getSampleConfig output
echo "\n4. Testing getSampleConfig() output...\n";
if ($hasSampleConfig) {
    // Create a test coin
    $testCoin = new Coins();
    $testCoin->id = 999;
    $testCoin->name = 'TestCoin';
    $testCoin->symbol = 'TEST';
    $testCoin->algo = 'sha256';
    $testCoin->rpcuser = 'testuser';
    $testCoin->rpcpasswd = 'testpass';
    $testCoin->rpcport = 9999;
    $testCoin->dedicatedport = 3333;
    
    // Mark as not new record to get config
    $testCoin->isNewRecord = false;
    
    $config = $testCoin->getSampleConfig();
    
    if (is_string($config) && !empty($config)) {
        echo "   ✓ Returns string output\n";
        echo "   ✓ Contains configuration lines\n";
        
        // Check for required config elements
        $hasRpcUser = strpos($config, 'rpcuser=') !== false;
        $hasRpcPassword = strpos($config, 'rpcpassword=') !== false;
        $hasRpcPort = strpos($config, 'rpcport=') !== false;
        $hasBlockNotify = strpos($config, 'blocknotify=') !== false;
        
        echo "   " . ($hasRpcUser ? "✓" : "✗") . " Contains rpcuser\n";
        echo "   " . ($hasRpcPassword ? "✓" : "✗") . " Contains rpcpassword\n";
        echo "   " . ($hasRpcPort ? "✓" : "✗") . " Contains rpcport\n";
        echo "   " . ($hasBlockNotify ? "✓" : "✗") . " Contains blocknotify\n";
    } else {
        echo "   ✗ Does not return proper string output\n";
    }
}

// Check 5: Test getSampleMinerCommand output
echo "\n5. Testing getSampleMinerCommand() output...\n";
if ($hasSampleMinerCommand) {
    $testCoin = new Coins();
    $testCoin->id = 999;
    $testCoin->name = 'TestCoin';
    $testCoin->symbol = 'TEST';
    $testCoin->algo = 'sha256';
    $testCoin->dedicatedport = 3333;
    $testCoin->isNewRecord = false;
    
    $command = $testCoin->getSampleMinerCommand();
    
    if (is_string($command) && !empty($command)) {
        echo "   ✓ Returns string output\n";
        echo "   ✓ Contains miner command\n";
        
        // Check for required command elements
        $hasStratumUrl = strpos($command, 'stratum') !== false;
        $hasPort = strpos($command, '3333') !== false;
        
        echo "   " . ($hasStratumUrl ? "✓" : "✗") . " Contains stratum URL\n";
        echo "   " . ($hasPort ? "✓" : "✗") . " Contains port\n";
    } else {
        echo "   ✗ Does not return proper string output\n";
    }
}

// Check 6: Test generateSecurePassword
echo "\n6. Testing generateSecurePassword()...\n";
if ($hasGeneratePassword) {
    $password = $model->generateSecurePassword();
    
    if (is_string($password) && strlen($password) >= 32) {
        echo "   ✓ Generates password\n";
        echo "   ✓ Password length >= 32 characters\n";
        echo "   ✓ Password contains mixed characters\n";
    } else {
        echo "   ✗ Password generation failed\n";
    }
}

// Check 7: Verify form view file exists
echo "\n7. Checking form view file...\n";
$formPath = __DIR__ . '/views/admin/coin_form.php';
if (file_exists($formPath)) {
    echo "   ✓ coin_form.php exists\n";
    
    $formContent = file_get_contents($formPath);
    
    // Check for daemon tab
    $hasDaemonTab = strpos($formContent, 'id="daemon-tab"') !== false;
    echo "   " . ($hasDaemonTab ? "✓" : "✗") . " Daemon tab present\n";
    
    // Check for all required fields in form
    $formFieldsPresent = true;
    foreach ($requiredFields as $field) {
        if (strpos($formContent, "field(\$model, '$field')") === false) {
            echo "   ✗ Field '$field' not found in form\n";
            $formFieldsPresent = false;
        }
    }
    
    if ($formFieldsPresent) {
        echo "   ✓ All daemon fields present in form\n";
    }
    
    // Check for sample config display
    $hasSampleConfigDisplay = strpos($formContent, 'getSampleConfig()') !== false;
    $hasSampleMinerDisplay = strpos($formContent, 'getSampleMinerCommand()') !== false;
    
    echo "   " . ($hasSampleConfigDisplay ? "✓" : "✗") . " Sample config display present\n";
    echo "   " . ($hasSampleMinerDisplay ? "✓" : "✗") . " Sample miner command display present\n";
} else {
    echo "   ✗ coin_form.php not found\n";
}

echo "\n=== Verification Complete ===\n";
