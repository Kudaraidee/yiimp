<?php
// Unit test bootstrap file

// Load test serverconfig constants if not already loaded
if (!defined('YAAMP_ALLOW_EXCHANGE')) {
    $testServerConfig = __DIR__ . '/../_data/serverconfig.test.php';
    if (file_exists($testServerConfig)) {
        require_once $testServerConfig;
    }
}

// Ensure Yii is loaded (should be loaded by global bootstrap)
if (!class_exists('Yii')) {
    require_once __DIR__ . '/../../vendor/autoload.php';
    require_once __DIR__ . '/../../vendor/yiisoft/yii2/Yii.php';
}

// Initialize application if not already done
if (!\Yii::$app) {
    $config = require __DIR__ . '/../../config/test.php';
    new yii\web\Application($config);
}
