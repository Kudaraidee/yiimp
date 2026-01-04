<?php

$config = require __DIR__ . '/../config/web.php';

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../vendor/yiisoft/yii2/Yii.php';

$app = new yii\web\Application($config);

// Verify session configuration on application start (Requirement 6.1, 7.1, 7.2, 7.3, 7.5)
try {
    \app\components\SessionConfigVerifier::verify();
} catch (\Exception $e) {
    // Log error but don't prevent application from starting
    Yii::error('Session configuration verification failed: ' . $e->getMessage(), 'app\components\SessionConfigVerifier');
}

$app->run();
