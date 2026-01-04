<?php
/**
 * Test script to understand Yii2 session behavior
 */

// Load Yii2
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/vendor/yiisoft/yii2/Yii.php';

// Load test config
$config = require __DIR__ . '/config/test.php';

// Create application
$app = new \yii\web\Application($config);

echo "=== Test 1: Session ID behavior after destroy ===\n";

// Open session
$app->session->open();
$id1 = $app->session->getId();
$token1 = $app->request->getCsrfToken();
echo "Session ID 1: $id1\n";
echo "CSRF Token 1: " . substr($token1, 0, 20) . "...\n";

// Destroy session
$app->session->destroy();
echo "Session destroyed\n";

// Open new session
$app->session->open();
$id2 = $app->session->getId();
$token2 = $app->request->getCsrfToken();
echo "Session ID 2: $id2\n";
echo "CSRF Token 2: " . substr($token2, 0, 20) . "...\n";

echo "Session IDs are " . ($id1 === $id2 ? "SAME" : "DIFFERENT") . "\n";
echo "CSRF Tokens are " . ($token1 === $token2 ? "SAME" : "DIFFERENT") . "\n";

echo "\n=== Test 2: Regenerate ID behavior ===\n";

$app->session->close();
$app->session->open();
$id3 = $app->session->getId();
$token3 = $app->request->getCsrfToken();
echo "Session ID 3: $id3\n";
echo "CSRF Token 3: " . substr($token3, 0, 20) . "...\n";

$app->session->regenerateID();
$id4 = $app->session->getId();
$token4 = $app->request->getCsrfToken();
echo "Session ID 4 (after regenerate): $id4\n";
echo "CSRF Token 4 (after regenerate): " . substr($token4, 0, 20) . "...\n";

echo "Session IDs are " . ($id3 === $id4 ? "SAME" : "DIFFERENT") . "\n";
echo "CSRF Tokens are " . ($token3 === $token4 ? "SAME" : "DIFFERENT") . "\n";
