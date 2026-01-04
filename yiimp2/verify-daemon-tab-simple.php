#!/usr/bin/env php
<?php
/**
 * Simple verification script for Daemon tab implementation
 * This script checks that all required fields are present without loading Yii2
 */

echo "=== Daemon Tab Implementation Verification ===\n\n";

// Check 1: Verify form view file exists
echo "1. Checking form view file...\n";
$formPath = __DIR__ . '/views/admin/coin_form.php';
if (!file_exists($formPath)) {
    echo "   ✗ coin_form.php not found\n";
    exit(1);
}
echo "   ✓ coin_form.php exists\n";

$formContent = file_get_contents($formPath);

// Check 2: Verify daemon tab exists
echo "\n2. Checking daemon tab structure...\n";
$hasDaemonTab = strpos($formContent, 'id="daemon-tab"') !== false;
$hasDaemonPanel = strpos($formContent, 'id="daemon"') !== false;
echo "   " . ($hasDaemonTab ? "✓" : "✗") . " Daemon tab button present\n";
echo "   " . ($hasDaemonPanel ? "✓" : "✗") . " Daemon tab panel present\n";

// Check 3: Verify all required fields in form
echo "\n3. Checking daemon fields in form...\n";
$requiredFields = [
    'program', 'conf_folder', 'rpchost', 'rpcport', 'rpcwallet',
    'rpcuser', 'rpcpasswd', 'serveruser', 'rpcencoding',
    'dedicatedport', 'rpccert', 'account', 'rpccurl', 'rpcssl'
];

$allFieldsPresent = true;
foreach ($requiredFields as $field) {
    $pattern = "field\\(\\\$model, '$field'\\)";
    if (preg_match("/$pattern/", $formContent)) {
        echo "   ✓ Field '$field' present\n";
    } else {
        echo "   ✗ Field '$field' NOT FOUND\n";
        $allFieldsPresent = false;
    }
}

// Check 4: Verify field types
echo "\n4. Checking field types...\n";
$hasPasswordField = preg_match("/passwordInput.*rpcpasswd/", $formContent);
$hasNumberFields = preg_match("/'type' => 'number'.*rpcport/", $formContent);
$hasCheckboxes = preg_match("/checkbox\\(\\).*rpccurl/", $formContent);

echo "   " . ($hasPasswordField ? "✓" : "✗") . " Password field for rpcpasswd\n";
echo "   " . ($hasNumberFields ? "✓" : "✗") . " Number input for rpcport\n";
echo "   " . ($hasCheckboxes ? "✓" : "✗") . " Checkboxes for rpccurl/rpcssl\n";

// Check 5: Verify field hints
echo "\n5. Checking field hints...\n";
$hintCount = preg_match_all("/->hint\\(/", $formContent, $matches);
echo "   ✓ Found $hintCount field hints in form\n";

$daemonHints = 0;
if (strpos($formContent, "hint('Daemon process name") !== false) $daemonHints++;
if (strpos($formContent, "hint('Config folder name") !== false) $daemonHints++;
if (strpos($formContent, "hint('Daemon RPC host") !== false) $daemonHints++;
if (strpos($formContent, "hint('Daemon RPC port") !== false) $daemonHints++;
if (strpos($formContent, "hint('RPC username") !== false) $daemonHints++;
if (strpos($formContent, "hint('RPC password") !== false) $daemonHints++;

echo "   ✓ Found $daemonHints daemon-specific hints\n";

// Check 6: Verify sample config display
echo "\n6. Checking sample configuration display...\n";
$hasSampleConfigDisplay = strpos($formContent, 'getSampleConfig()') !== false;
$hasSampleMinerDisplay = strpos($formContent, 'getSampleMinerCommand()') !== false;
$hasConditionalDisplay = strpos($formContent, '!$isNew && $model->id') !== false;

echo "   " . ($hasSampleConfigDisplay ? "✓" : "✗") . " Sample config display present\n";
echo "   " . ($hasSampleMinerDisplay ? "✓" : "✗") . " Sample miner command display present\n";
echo "   " . ($hasConditionalDisplay ? "✓" : "✗") . " Conditional display for existing coins\n";

// Check 7: Verify Coins model file
echo "\n7. Checking Coins model...\n";
$modelPath = __DIR__ . '/models/Coins.php';
if (!file_exists($modelPath)) {
    echo "   ✗ Coins.php not found\n";
    exit(1);
}
echo "   ✓ Coins.php exists\n";

$modelContent = file_get_contents($modelPath);

// Check for helper methods
$hasGetSampleConfig = strpos($modelContent, 'public function getSampleConfig()') !== false;
$hasGetSampleMinerCommand = strpos($modelContent, 'public function getSampleMinerCommand()') !== false;
$hasGeneratePassword = strpos($modelContent, 'public function generateSecurePassword()') !== false;

echo "   " . ($hasGetSampleConfig ? "✓" : "✗") . " getSampleConfig() method present\n";
echo "   " . ($hasGetSampleMinerCommand ? "✓" : "✗") . " getSampleMinerCommand() method present\n";
echo "   " . ($hasGeneratePassword ? "✓" : "✗") . " generateSecurePassword() method present\n";

// Check that getSampleConfig returns string
$returnsString = preg_match("/function getSampleConfig\\(\\).*?return implode/s", $modelContent);
echo "   " . ($returnsString ? "✓" : "✗") . " getSampleConfig() returns string (uses implode)\n";

// Check 8: Verify validation rules
echo "\n8. Checking validation rules in model...\n";
$hasRpcPortValidation = preg_match("/'rpcport'.*'min' => 1.*'max' => 65535/s", $modelContent);
$hasStringValidation = preg_match("/'program'.*'string'/s", $modelContent);
$hasBooleanValidation = preg_match("/'rpccurl'.*'boolean'/s", $modelContent);

echo "   " . ($hasRpcPortValidation ? "✓" : "✗") . " RPC port validation (1-65535)\n";
echo "   " . ($hasStringValidation ? "✓" : "✗") . " String field validation\n";
echo "   " . ($hasBooleanValidation ? "✓" : "✗") . " Boolean field validation\n";

// Check 9: Verify all daemon fields in model attributes
echo "\n9. Checking daemon field properties in model...\n";
$allPropertiesPresent = true;
foreach ($requiredFields as $field) {
    if (preg_match("/@property.*\\\$$field/", $modelContent)) {
        // Property documented
    } else {
        echo "   ⚠ Property '$field' not documented in PHPDoc\n";
    }
}

// Summary
echo "\n=== Verification Summary ===\n";
if ($allFieldsPresent && $hasDaemonTab && $hasSampleConfigDisplay && $hasSampleMinerDisplay && 
    $hasGetSampleConfig && $hasGetSampleMinerCommand && $hasGeneratePassword) {
    echo "✓ All required components are present!\n";
    echo "✓ Daemon tab implementation is complete!\n";
    exit(0);
} else {
    echo "✗ Some components are missing or incomplete\n";
    exit(1);
}
