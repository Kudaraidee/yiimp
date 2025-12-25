<?php
/**
 * CSRF Diagnostic Page
 * Access this page to check CSRF configuration and token generation
 * 
 * SECURITY: This page is only accessible in debug mode.
 * In production mode, it returns a 404 error.
 */

// Set up Yii2 environment
require(__DIR__ . '/../vendor/autoload.php');
require(__DIR__ . '/../vendor/yiisoft/yii2/Yii.php');

$config = require(__DIR__ . '/../config/web.php');
$application = new yii\web\Application($config);

// SECURITY CHECK: Only allow access in debug mode
if (!YII_DEBUG) {
    header('HTTP/1.0 404 Not Found');
    echo '<!DOCTYPE html><html><head><title>404 Not Found</title></head><body><h1>404 Not Found</h1><p>The requested page was not found.</p></body></html>';
    exit;
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>CSRF Diagnostic</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #f5f5f5; }
        .section { background: white; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
        h2 { border-bottom: 2px solid #333; padding-bottom: 5px; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 5px; border-bottom: 1px solid #ddd; }
        td:first-child { font-weight: bold; width: 250px; }
    </style>
</head>
<body>
    <h1>CSRF Diagnostic Page</h1>
    
    <div class="section">
        <h2>Session Information</h2>
        <table>
            <tr>
                <td>Session Status:</td>
                <td>
                    <?php
                    $status = session_status();
                    $statusText = [
                        PHP_SESSION_DISABLED => 'Disabled',
                        PHP_SESSION_NONE => 'Not Started',
                        PHP_SESSION_ACTIVE => 'Active'
                    ];
                    echo htmlspecialchars($statusText[$status] ?? 'Unknown');
                    ?>
                </td>
            </tr>
            <tr>
                <td>Session Active:</td>
                <td class="<?= Yii::$app->session->getIsActive() ? 'success' : 'error' ?>">
                    <?= Yii::$app->session->getIsActive() ? '✓ Yes' : '✗ No' ?>
                </td>
            </tr>
            <tr>
                <td>Session ID:</td>
                <td><?= htmlspecialchars(Yii::$app->session->id) ?></td>
            </tr>
            <tr>
                <td>Session Name:</td>
                <td><?= htmlspecialchars(Yii::$app->session->name) ?></td>
            </tr>
            <tr>
                <td>Session Timeout:</td>
                <td><?= Yii::$app->session->timeout ?> seconds</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <h2>CSRF Configuration</h2>
        <table>
            <tr>
                <td>CSRF Validation Enabled:</td>
                <td class="<?= Yii::$app->request->enableCsrfValidation ? 'success' : 'warning' ?>">
                    <?= Yii::$app->request->enableCsrfValidation ? '✓ Yes' : '⚠ No' ?>
                </td>
            </tr>
            <tr>
                <td>CSRF Parameter Name:</td>
                <td><?= htmlspecialchars(Yii::$app->request->csrfParam) ?></td>
            </tr>
            <tr>
                <td>CSRF Token:</td>
                <td style="word-break: break-all;"><?= htmlspecialchars(Yii::$app->request->getCsrfToken()) ?></td>
            </tr>
        </table>
    </div>

    <div class="section">
        <h2>Cookie Configuration</h2>
        <table>
            <tr>
                <td>Cookie Validation Key Set:</td>
                <td class="<?= !empty(Yii::$app->request->cookieValidationKey) ? 'success' : 'error' ?>">
                    <?= !empty(Yii::$app->request->cookieValidationKey) ? '✓ Yes' : '✗ No' ?>
                </td>
            </tr>
            <tr>
                <td>Cookie Validation Key Length:</td>
                <td><?= strlen(Yii::$app->request->cookieValidationKey) ?> characters</td>
            </tr>
            <tr>
                <td>Cookies Received:</td>
                <td><?= count($_COOKIE) ?> cookies</td>
            </tr>
            <tr>
                <td>Session Cookie Present:</td>
                <td class="<?= isset($_COOKIE[Yii::$app->session->name]) ? 'success' : 'error' ?>">
                    <?= isset($_COOKIE[Yii::$app->session->name]) ? '✓ Yes' : '✗ No' ?>
                </td>
            </tr>
        </table>
    </div>

    <div class="section">
        <h2>Test Form</h2>
        <p>Submit this form to test CSRF validation:</p>
        <form method="post" action="">
            <input type="hidden" name="<?= Yii::$app->request->csrfParam ?>" value="<?= Yii::$app->request->getCsrfToken() ?>">
            <input type="text" name="test_field" value="test value" style="padding: 5px; margin: 5px;">
            <button type="submit" style="padding: 5px 15px; margin: 5px;">Submit Test</button>
        </form>
        
        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
            <div style="margin-top: 10px; padding: 10px; background: #e8f5e9; border-radius: 3px;">
                <strong class="success">✓ Form submitted successfully!</strong><br>
                CSRF validation passed. The token was valid.
            </div>
        <?php endif; ?>
    </div>

    <div class="section">
        <h2>Environment Variables</h2>
        <table>
            <tr>
                <td>YII_DEBUG:</td>
                <td class="<?= YII_DEBUG ? 'success' : 'warning' ?>"><?= YII_DEBUG ? 'true' : 'false' ?></td>
            </tr>
            <tr>
                <td>YII_ENV:</td>
                <td><?= YII_ENV ?></td>
            </tr>
            <tr>
                <td>YIIMP_DEBUG:</td>
                <td><?= htmlspecialchars(getenv('YIIMP_DEBUG') ?: 'not set') ?></td>
            </tr>
            <tr>
                <td>YIIMP_COOKIE_VALIDATION_KEY:</td>
                <td class="<?= getenv('YIIMP_COOKIE_VALIDATION_KEY') ? 'success' : 'error' ?>">
                    <?= getenv('YIIMP_COOKIE_VALIDATION_KEY') ? '✓ Set (' . strlen(getenv('YIIMP_COOKIE_VALIDATION_KEY')) . ' chars)' : '✗ Not set' ?>
                </td>
            </tr>
            <tr>
                <td>YIIMP_SESSION_NAME:</td>
                <td><?= htmlspecialchars(getenv('YIIMP_SESSION_NAME') ?: 'not set (using default)') ?></td>
            </tr>
            <tr>
                <td>YIIMP_SESSION_TIMEOUT:</td>
                <td><?= htmlspecialchars(getenv('YIIMP_SESSION_TIMEOUT') ?: 'not set (using default)') ?></td>
            </tr>
            <tr>
                <td>YIIMP_FORCE_HTTPS:</td>
                <td><?= htmlspecialchars(getenv('YIIMP_FORCE_HTTPS') ?: 'not set') ?></td>
            </tr>
            <tr>
                <td>PHP Version:</td>
                <td><?= PHP_VERSION ?></td>
            </tr>
            <tr>
                <td>Server Software:</td>
                <td><?= htmlspecialchars($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') ?></td>
            </tr>
            <tr>
                <td>HTTPS Detected:</td>
                <td class="<?= (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
                              (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
                              (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on') ||
                              (getenv('YIIMP_FORCE_HTTPS') === 'true' || getenv('YIIMP_FORCE_HTTPS') === '1') ? 'success' : 'warning' ?>">
                    <?php 
                    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
                        echo '✓ Yes (via $_SERVER[\'HTTPS\'])';
                    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
                        echo '✓ Yes (via X-Forwarded-Proto header)';
                    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on') {
                        echo '✓ Yes (via X-Forwarded-SSL header)';
                    } elseif (getenv('YIIMP_FORCE_HTTPS') === 'true' || getenv('YIIMP_FORCE_HTTPS') === '1') {
                        echo '✓ Yes (via YIIMP_FORCE_HTTPS)';
                    } else {
                        echo '⚠ No (HTTP mode)';
                    }
                    ?>
                </td>
            </tr>
        </table>
    </div>
    
    <div class="section">
        <h2>Cookie Details</h2>
        <table>
            <tr>
                <td>Session Cookie Secure Flag:</td>
                <td class="<?= Yii::$app->session->cookieParams['secure'] ? 'success' : 'warning' ?>">
                    <?= Yii::$app->session->cookieParams['secure'] ? '✓ Enabled' : '⚠ Disabled' ?>
                </td>
            </tr>
            <tr>
                <td>Session Cookie HttpOnly Flag:</td>
                <td class="<?= Yii::$app->session->cookieParams['httponly'] ? 'success' : 'error' ?>">
                    <?= Yii::$app->session->cookieParams['httponly'] ? '✓ Enabled' : '✗ Disabled' ?>
                </td>
            </tr>
            <tr>
                <td>Session Cookie SameSite:</td>
                <td><?= htmlspecialchars(Yii::$app->session->cookieParams['sameSite'] ?? 'not set') ?></td>
            </tr>
            <tr>
                <td>All Cookies Received:</td>
                <td>
                    <?php if (count($_COOKIE) > 0): ?>
                        <?= count($_COOKIE) ?> cookies:
                        <ul style="margin: 5px 0; padding-left: 20px;">
                            <?php foreach (array_keys($_COOKIE) as $cookieName): ?>
                                <li><?= htmlspecialchars($cookieName) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <span class="error">No cookies received</span>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
    </div>

    <div class="section">
        <h2>Recommendations</h2>
        <?php
        $errors = [];
        $warnings = [];
        $success = [];
        
        // Critical errors
        if (!Yii::$app->session->getIsActive()) {
            $errors[] = 'Session is not active. Check session configuration and ensure session storage is writable.';
        }
        
        if (!isset($_COOKIE[Yii::$app->session->name])) {
            $errors[] = 'Session cookie not found in browser. Check browser cookie settings and ensure cookies are enabled.';
        }
        
        if (empty(Yii::$app->request->cookieValidationKey)) {
            $errors[] = 'Cookie validation key is not set. Set YIIMP_COOKIE_VALIDATION_KEY environment variable. Generate with: openssl rand -hex 32';
        }
        
        if (!Yii::$app->request->enableCsrfValidation) {
            $errors[] = 'CSRF validation is disabled. Enable it in config/web.php for security.';
        }
        
        // Warnings
        if (strlen(Yii::$app->request->cookieValidationKey) < 32) {
            $warnings[] = 'Cookie validation key is too short (' . strlen(Yii::$app->request->cookieValidationKey) . ' chars). Use at least 32 characters for security.';
        }
        
        if (!Yii::$app->session->cookieParams['httponly']) {
            $warnings[] = 'Session cookie HttpOnly flag is disabled. Enable it to prevent XSS attacks.';
        }
        
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
                   (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
                   (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on') ||
                   (getenv('YIIMP_FORCE_HTTPS') === 'true' || getenv('YIIMP_FORCE_HTTPS') === '1');
        
        if ($isHttps && !Yii::$app->session->cookieParams['secure']) {
            $warnings[] = 'Running in HTTPS mode but session cookie secure flag is not set. This may cause cookie issues.';
        }
        
        if (!$isHttps && Yii::$app->session->cookieParams['secure']) {
            $warnings[] = 'Session cookie secure flag is set but not running in HTTPS mode. Cookies will not be sent by browser.';
        }
        
        if (count($_COOKIE) === 0) {
            $warnings[] = 'No cookies received from browser. Check browser settings and ensure cookies are enabled.';
        }
        
        if (!getenv('YIIMP_COOKIE_VALIDATION_KEY')) {
            $warnings[] = 'YIIMP_COOKIE_VALIDATION_KEY environment variable is not set. Using generated key which will change on restart.';
        }
        
        // Success checks
        if (Yii::$app->session->getIsActive()) {
            $success[] = 'Session is active and working correctly.';
        }
        
        if (isset($_COOKIE[Yii::$app->session->name])) {
            $success[] = 'Session cookie is present in browser.';
        }
        
        if (Yii::$app->request->enableCsrfValidation) {
            $success[] = 'CSRF validation is enabled.';
        }
        
        if (strlen(Yii::$app->request->cookieValidationKey) >= 32) {
            $success[] = 'Cookie validation key has sufficient length.';
        }
        
        if (Yii::$app->session->cookieParams['httponly']) {
            $success[] = 'Session cookie HttpOnly flag is enabled.';
        }
        
        if (($isHttps && Yii::$app->session->cookieParams['secure']) || (!$isHttps && !Yii::$app->session->cookieParams['secure'])) {
            $success[] = 'Cookie security settings match the connection protocol.';
        }
        ?>
        
        <?php if (count($errors) > 0): ?>
            <h3 style="color: red;">Critical Issues:</h3>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li class="error">✗ <?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        
        <?php if (count($warnings) > 0): ?>
            <h3 style="color: orange;">Warnings:</h3>
            <ul>
                <?php foreach ($warnings as $warning): ?>
                    <li class="warning">⚠ <?= htmlspecialchars($warning) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        
        <?php if (count($success) > 0): ?>
            <h3 style="color: green;">Passed Checks:</h3>
            <ul>
                <?php foreach ($success as $item): ?>
                    <li class="success">✓ <?= htmlspecialchars($item) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        
        <?php if (count($errors) === 0 && count($warnings) === 0): ?>
            <div style="padding: 15px; background: #e8f5e9; border-radius: 5px; margin-top: 10px;">
                <strong class="success" style="font-size: 1.2em;">✓ All checks passed!</strong><br>
                <p style="margin: 10px 0 0 0;">CSRF protection should be working correctly. If you still experience issues, check the application logs.</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
