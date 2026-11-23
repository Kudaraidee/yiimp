<?php

/** @var yii\web\View  $this        */
/** @var string        $currentAlgo */
/** @var string[]      $algos       */

use yii\helpers\Html;

$this->title = 'Versions';
$isTailwind  = Yii::$app->LayoutManager->isTailwind();
$isLegacy    = Yii::$app->LayoutManager->isLegacy();

$options = '';
foreach ($algos as $a) {
    $sel      = ($a === $currentAlgo) ? ' selected' : '';
    $options .= '<option value="' . Html::encode($a) . '"' . $sel . '>'
              . Html::encode($a) . '</option>';
}
if ($currentAlgo !== '' && !in_array($currentAlgo, $algos, true)) {
    $options = '<option value="' . Html::encode($currentAlgo) . '" selected>'
             . Html::encode($currentAlgo) . '</option>' . $options;
}

$homeUrl = Yii::$app->homeUrl;
?>

<?php if ($isLegacy): ?>
<div class="admn-page">
<?= Yii::$app->ViewUtils->getAdminMiningToolbar('Versions', 'miner clients · admin console', 'BUILD', 'live · 30s') ?>
<div class="admn-selectbar">
    <label for="algo_select">Algorithm</label>
    <select id="algo_select"><?= $options ?></select>
    <span class="admn-hint">client version mix by hashrate</span>
</div>
<div id="main_results"></div>
</div>

<?php else: ?>
<?= Yii::$app->ViewUtils->getAdminSideBarLinks() ?>
<?php if (!$isTailwind): ?>
<div class="d-flex align-items-center gap-2 mb-2">
    <label class="text-muted small mb-0">Algo:</label>
    <select id="algo_select" class="form-select form-select-sm" style="width:140px;">
        <?= $options ?>
    </select>
</div>

<?php else: ?>
<div class="flex items-center gap-2 mb-3">
    <label class="text-xs text-gray-400 dark:text-gray-500">Algo:</label>
    <select id="algo_select"
            class="px-2 py-1.5 text-sm rounded-lg border border-gray-300 dark:border-gray-600
                   bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100
                   focus:outline-none focus:ring-2 focus:ring-indigo-500">
        <?= $options ?>
    </select>
</div>
<?php endif ?>

<div id="main_results"></div>
<?php endif ?>

<script>
$(function () {
    main_refresh();
    $('#algo_select').on('change', function () { main_refresh(); });
});

var main_delay   = 30000;
var main_timeout;

function main_ready(data) {
    $('#main_results').html(data);
    main_timeout = setTimeout(main_refresh, main_delay);
}
function main_error() {
    main_timeout = setTimeout(main_refresh, main_delay * 2);
}
function main_refresh() {
    clearTimeout(main_timeout);
    $.get('<?= $homeUrl ?>admin/version_results?algo=' + encodeURIComponent($('#algo_select').val()),
          '', main_ready).fail(main_error);
}
</script>
