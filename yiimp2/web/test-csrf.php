<?php
// Simple CSRF test page
// Access at: http://localhost:8090/test-csrf.php

// Load Yii2
require(__DIR__ . '/../vendor/autoload.php');
require(__DIR__ . '/../vendor/yiisoft/yii2/Yii.php');

$config = require(__DIR__ . '/../config/web.php');
$application = new yii\web\Application($config);

?>
<!DOCTYPE html>
<html>
<head>
    <title>CSRF Test</title>
</head>
<body>
    <h1>CSRF Configuration Test</h1>
    
    <h2>Configuration:</h2>
    <pre><?php
    echo "Cookie Validation Key: " . substr(Yii::$app->request->cookieValidationKey, 0, 20) . "...\n";
    echo "CSRF Param: " . Yii::$app->request->csrfParam . "\n";
    echo "CSRF Token: " . Yii::$app->request->getCsrfToken() . "\n";
    echo "CSRF Validation Enabled: " . (Yii::$app->request->enableCsrfValidation ? 'Yes' : 'No') . "\n";
    echo "Session ID: " . Yii::$app->session->id . "\n";
    echo "Session Name: " . Yii::$app->session->name . "\n";
    ?></pre>
    
    <h2>Test Form:</h2>
    <form method="POST" action="">
        <?= yii\helpers\Html::csrfMetaTags() ?>
        <input type="hidden" name="<?= Yii::$app->request->csrfParam ?>" value="<?= Yii::$app->request->getCsrfToken() ?>">
        <input type="text" name="test_field" value="test value" placeholder="Test field">
        <button type="submit">Submit Test</button>
    </form>
    
    <?php if (Yii::$app->request->isPost): ?>
        <h2>POST Result:</h2>
        <pre><?php
        echo "POST data received:\n";
        print_r(Yii::$app->request->post());
        echo "\nCSRF Token in POST: " . (Yii::$app->request->post(Yii::$app->request->csrfParam) ? 'Present' : 'Missing') . "\n";
        echo "CSRF Validation: " . (Yii::$app->request->validateCsrfToken() ? 'PASS' : 'FAIL') . "\n";
        ?></pre>
    <?php endif; ?>
    
    <h2>Cookies:</h2>
    <pre><?php
    print_r($_COOKIE);
    ?></pre>
</body>
</html>
<?php
$application->end();
?>
