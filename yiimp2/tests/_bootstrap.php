<?php
// This is global bootstrap for autoloading
defined('YII_DEBUG') or define('YII_DEBUG', true);
defined('YII_ENV') or define('YII_ENV', 'test');

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../vendor/yiisoft/yii2/Yii.php';

// Load test serverconfig
require_once __DIR__ . '/_data/serverconfig.test.php';

// Initialize Yii application for tests
$config = require __DIR__ . '/../config/test.php';
new yii\web\Application($config);
