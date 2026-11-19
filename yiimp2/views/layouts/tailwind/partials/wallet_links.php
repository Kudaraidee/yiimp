<?php
/** @var yii\web\View         $this */
/** @var app\models\Coins     $coin */
/** @var array|false|null     $info */
/** @var string               $src  */

use yii\helpers\Html;

$btn = fn(string $label, string $url, string $color = 'indigo') =>
    Html::a($label, $url, [
        'class' => "inline-flex items-center px-3 py-1 rounded-md text-xs font-medium
                    bg-{$color}-100 dark:bg-{$color}-900/40
                    text-{$color}-700 dark:text-{$color}-300
                    hover:bg-{$color}-200 dark:hover:bg-{$color}-800
                    border border-{$color}-200 dark:border-{$color}-700
                    transition-colors",
    ]);
?>
<div class="flex flex-wrap gap-1.5 mb-3">
    <?= $btn('Properties', '/admin/coinwallet_update?id=' . $coin->id) ?>
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
        <?= $btn('Stop coind', '/admin/stopcoin?id=' . $coin->id, 'red') ?>
    <?php endif ?>
    <?= $coin->auto_ready
        ? $btn('Unset auto', '/admin/coinwallet-unsetauto?id=' . $coin->id, 'yellow')
        : $btn('Set auto',   '/admin/coinwallet-setauto?id='   . $coin->id, 'green') ?>
</div>
<?php
$extLinks = [];
if (!empty($coin->link_bitcointalk)) $extLinks[] = ['Forum',  $coin->link_bitcointalk, 'message-circle',  'BitcoinTalk Forum'];
if (!empty($coin->link_github))      $extLinks[] = ['GitHub', $coin->link_github,      'github',          'GitHub Repository'];
if (!empty($coin->link_site))        $extLinks[] = ['Site',   $coin->link_site,        'globe',           'Official Website'];
if (!empty($coin->link_explorer))    $extLinks[] = ['Chain',  $coin->link_explorer,    'link',            'External Explorer'];
$extLinks[] = ['Search', 'http://google.com/search?q=' . urlencode($coin->name . ' ' . $coin->symbol . ' bitcointalk'), 'search', 'Google Search'];
?>
<div class="flex flex-wrap gap-1.5 mb-3">
<?php foreach ($extLinks as [$label, $url, $icon, $title]): ?>
    <?= Html::a(
        '<i data-lucide="' . $icon . '" class="w-3 h-3 me-1 inline-block align-[-1px]"></i>' . Html::encode($label),
        $url,
        ['class' => 'inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium
                     bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300
                     hover:bg-gray-200 dark:hover:bg-gray-600
                     border border-gray-200 dark:border-gray-600 transition-colors',
         'target' => '_blank', 'title' => $title]
    ) ?>
<?php endforeach ?>
</div>
