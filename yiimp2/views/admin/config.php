<?php
/**
 * Hidden admin page — /admin/config
 * Dumps all YIIMP_* and NICEHASH_* constants loaded from serverconfig.php.
 * Sensitive values (passwords, secrets, keys, tokens) are masked.
 * Not linked from the navbar — access directly by URL.
 */

use yii\helpers\Html;

$this->title = 'Server Config';

// Patterns that indicate a sensitive value — mask these
$sensitivePatterns = [
    'PASSWORD', 'PASS', 'SECRET', 'KEY', 'TOKEN', 'ACCESSTOKEN',
    'MYSQLDUMP_USER', 'MYSQLDUMP_PASS', 'SMTP_USERNAME', 'SMTP_PASSWORD',
];

$isSensitive = function (string $name) use ($sensitivePatterns): bool {
    foreach ($sensitivePatterns as $p) {
        if (str_contains(strtoupper($name), $p)) return true;
    }
    return false;
};

$mask = function (mixed $value): string {
    $s = (string) $value;
    return empty($s) ? '(empty)' : '••••••••';
};

$display = function (mixed $value) use ($isSensitive, $mask): string {
    if (is_bool($value)) return $value ? 'true' : 'false';
    if (is_null($value)) return 'null';
    return htmlspecialchars((string) $value, ENT_QUOTES);
};

// Collect all relevant constants
$prefixes  = ['YIIMP_', 'YAAMP_', 'NICEHASH_', 'EXCH_', 'SMTP_', 'GITHUB_'];
$constants = get_defined_constants(true)['user'] ?? [];

$groups = [];
foreach ($constants as $name => $value) {
    foreach ($prefixes as $prefix) {
        if (str_starts_with($name, $prefix)) {
            $groups[$prefix][$name] = $value;
            break;
        }
    }
}
ksort($groups);

// PHP / environment info
$phpVersion  = PHP_VERSION;
$serverConfig = file_exists('/etc/yiimp/serverconfig.php') ? '/etc/yiimp/serverconfig.php' : 'serverconfig.php (local)';
$yiiVersion  = Yii::getVersion();
?>

<style>
.config-table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
.config-table th { background: #444; color: #fff; padding: 6px 10px; text-align: left; }
.config-table td { padding: 4px 10px; border-bottom: 1px solid #eee; font-family: monospace; font-size: .9em; }
.config-table tr:nth-child(even) td { background: #f8f8f8; }
.val-true  { color: #2a2; font-weight: bold; }
.val-false { color: #a22; }
.val-empty { color: #aaa; font-style: italic; }
.val-masked { color: #888; letter-spacing: 2px; }
.section-header { margin: 20px 0 4px; font-size: 1.1em; font-weight: bold; color: #555; border-bottom: 2px solid #ddd; padding-bottom: 4px; }
</style>

<p style="margin-bottom:16px;">
  Loaded from: <code><?= Html::encode($serverConfig) ?></code> &nbsp;|&nbsp;
  PHP <?= Html::encode($phpVersion) ?> &nbsp;|&nbsp;
  Yii2 <?= Html::encode($yiiVersion) ?>
</p>

<?php foreach ($groups as $prefix => $consts):
    ksort($consts);
    $label = rtrim($prefix, '_');
?>
<div class="section-header"><?= Html::encode($label) ?></div>
<table class="config-table">
<thead><tr><th width="350">Constant</th><th>Value</th></tr></thead>
<tbody>
<?php foreach ($consts as $name => $value):
    $sensitive = $isSensitive($name);
    if ($sensitive) {
        $rendered = '<span class="val-masked">' . $mask($value) . '</span>';
    } elseif ($value === true) {
        $rendered = '<span class="val-true">true</span>';
    } elseif ($value === false) {
        $rendered = '<span class="val-false">false</span>';
    } elseif ($value === '' || $value === null) {
        $rendered = '<span class="val-empty">(empty)</span>';
    } else {
        $rendered = Html::encode((string) $value);
    }
?>
<tr>
  <td><?= Html::encode($name) ?></td>
  <td><?= $rendered ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php endforeach; ?>

<?php if (empty($groups)): ?>
<p style="color:red;">No YIIMP_* / NICEHASH_* / EXCH_* constants found — serverconfig.php may not have loaded.</p>
<?php endif; ?>
