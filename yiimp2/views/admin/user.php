<?php

/** @var yii\web\View        $this        */
/** @var string              $symbol      */
/** @var app\models\Coins[]  $activeCoins */

use yii\helpers\Html;

$this->title = 'Users';
$isTailwind  = Yii::$app->LayoutManager->isTailwind();
$isLegacy    = Yii::$app->LayoutManager->isLegacy();

echo Yii::$app->ViewUtils->getAdminSideBarLinks();

$options = '<option value="all"' . ($symbol === 'all' ? ' selected' : '') . '>— all —</option>';
foreach ($activeCoins as $coin) {
    $sel      = $coin->symbol === $symbol ? ' selected' : '';
    $options .= '<option value="' . Html::encode($coin->symbol) . '"' . $sel . '>'
              . Html::encode($coin->symbol) . '</option>';
}

$homeUrl = Yii::$app->homeUrl;
?>

<?php if ($isLegacy): ?>
<div align="right" style="margin-top:-14px; margin-bottom:-6px; margin-right:140px;">
    Select coin: <select id="coin_select"><?= $options ?></select>&nbsp;
</div>

<?php elseif (!$isTailwind): ?>
<div class="d-flex align-items-center gap-2 mb-2">
    <label class="text-muted small mb-0">Coin:</label>
    <select id="coin_select" class="form-select form-select-sm" style="width:140px;">
        <?= $options ?>
    </select>
</div>

<?php else: ?>
<div class="flex items-center gap-2 mb-3">
    <label class="text-xs text-gray-400 dark:text-gray-500">Coin:</label>
    <select id="coin_select"
            class="px-2 py-1.5 text-sm rounded-lg border border-gray-300 dark:border-gray-600
                   bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100
                   focus:outline-none focus:ring-2 focus:ring-indigo-500">
        <?= $options ?>
    </select>
</div>
<?php endif ?>

<div id="main_results"></div>

<script>
$(function () {
    $('#coin_select').on('change', function () {
        window.location.href = '<?= $homeUrl ?>admin/user?symbol=' + encodeURIComponent($(this).val());
    });
    main_refresh();
});

var main_delay   = 30000;
var main_timeout;

function main_ready(data) {
    $('#main_results').html(data);
    if (typeof lucide !== 'undefined') lucide.createIcons();
    main_timeout = setTimeout(main_refresh, main_delay);
}
function main_error() {
    main_timeout = setTimeout(main_refresh, main_delay * 2);
}
function main_refresh() {
    var symbol = $('#coin_select').val();
    clearTimeout(main_timeout);
    $.get('<?= $homeUrl ?>admin/user_results?symbol=' + encodeURIComponent(symbol), '', main_ready)
     .fail(main_error);
}
</script>
