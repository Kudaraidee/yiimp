<?php
/** @var yii\web\View         $this */
/** @var app\models\Coins     $coin */
/** @var array|false|null     $info */
/** @var string               $src  */

use yii\helpers\Html;

$btn = fn(string $label, string $url, string $variant = 'secondary') =>
    Html::a($label, $url, ['class' => "btn btn-sm btn-outline-{$variant} me-1"]);
?>
<div class="d-flex flex-wrap gap-1 mb-2">
    <?= $btn('Properties', '/admin/coinwallet_update?id=' . $coin->id, 'primary') ?>
    <?php if ($info): ?>
        <?= $btn('Explorer', '/explorer/' . $coin->getOfficialSymbol() . '?id=' . $coin->id) ?>
        <?= $btn('Peers',    '/admin/coinpeers?id='   . $coin->id) ?>
        <?php if (YIIMP_ADMIN_WEBCONSOLE): ?>
            <?= $btn('Console', '/admin/coinwallet-console?id=' . $coin->id) ?>
        <?php endif ?>
        <?= $btn('Triggers', '/admin/cointriggers?id=' . $coin->id) ?>
        <?php if ($src !== 'wallet'): ?>
            <?= $btn(Html::encode($coin->symbol), '/admin/coinwallet?id=' . $coin->id) ?>
        <?php endif ?>
    <?php endif ?>
    <?php if (!$info && $coin->enable): ?>
        <?= $btn('Stop coind', '/admin/stopcoin?id=' . $coin->id, 'danger') ?>
    <?php endif ?>
    <?= $coin->auto_ready
        ? $btn('Unset auto', '/admin/coinwallet-unsetauto?id=' . $coin->id, 'warning')
        : $btn('Set auto',   '/admin/coinwallet-setauto?id='   . $coin->id, 'success') ?>
</div>
<?php
$extLinks = [];
if (!empty($coin->link_bitcointalk)) $extLinks[] = ['Forum',   $coin->link_bitcointalk, 'bi-chat-dots',   'BitcoinTalk Forum'];
if (!empty($coin->link_github))      $extLinks[] = ['GitHub',  $coin->link_github,      'bi-github',      'GitHub Repository'];
if (!empty($coin->link_site))        $extLinks[] = ['Site',    $coin->link_site,        'bi-globe',       'Official Website'];
if (!empty($coin->link_explorer))    $extLinks[] = ['Chain',   $coin->link_explorer,    'bi-link-45deg',  'External Explorer'];
$extLinks[] = ['Search', 'http://google.com/search?q=' . urlencode($coin->name . ' ' . $coin->symbol . ' bitcointalk'), 'bi-google', 'Google Search'];
?>
<div class="d-flex flex-wrap gap-1 mb-2">
<?php foreach ($extLinks as [$label, $url, $icon, $title]): ?>
    <?= Html::a(
        '<i class="bi ' . $icon . ' me-1"></i>' . Html::encode($label),
        $url,
        ['class' => 'btn btn-sm btn-outline-secondary', 'target' => '_blank', 'title' => $title]
    ) ?>
<?php endforeach ?>
</div>
