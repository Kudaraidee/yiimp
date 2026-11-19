<?php

/** @var yii\web\View  $this */
/** @var string        $exch */

$this->title = 'Balances' . ($exch ? " - {$exch}" : '');
$isTailwind  = Yii::$app->LayoutManager->isTailwind();
$isLegacy    = Yii::$app->LayoutManager->isLegacy();

echo Yii::$app->ViewUtils->getAdminSideBarLinks();
?>

<div id="main_results"></div>

<?php if ($isLegacy): ?>
<style type="text/css">p.notes { opacity: 0.7; }</style>
<p class="notes">Non-zero exchange balances tracked by yiimp. Use "update ticker" to manually trigger an API price refresh for that exchange.</p>

<?php elseif (!$isTailwind): ?>
<p class="text-muted small mt-2">
    <i class="bi bi-info-circle me-1"></i>
    Non-zero exchange balances. Use <em>update ticker</em> to manually trigger an API price refresh.
</p>

<?php else: ?>
<p class="text-xs text-gray-400 dark:text-gray-500 mt-2 flex items-center gap-1">
    <i data-lucide="info" class="w-3.5 h-3.5 shrink-0"></i>
    Non-zero exchange balances. Use <em>update ticker</em> to manually trigger an API price refresh.
</p>
<?php endif ?>

<script>
var main_delay = 60000;
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
    clearTimeout(main_timeout);
    $.get('/admin/balances_results?exch=<?= urlencode($exch) ?>', '', main_ready).fail(main_error);
}
</script>

<?php Yii::$app->view->registerJs('main_refresh();'); ?>
