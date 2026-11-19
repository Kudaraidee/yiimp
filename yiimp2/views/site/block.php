<?php

/** @var yii\web\View      $this */
/** @var app\models\Coins  $coin */
/** @var int               $id   */

use yii\helpers\Html;

$this->title = $coin ? 'Blocks — ' . $coin->symbol : 'Blocks';

$isTailwind = Yii::$app->LayoutManager->isTailwind();
$isLegacy   = Yii::$app->LayoutManager->isLegacy();
$resultsUrl = Yii::$app->homeUrl . 'site/block_results?id=' . $id;

?>

<?php if ($isLegacy): ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     LEGACY
     ══════════════════════════════════════════════════════════════════════════ -->
<?php if ($coin): ?>
<h3><?= Html::encode($coin->name) ?> (<?= Html::encode($coin->symbol) ?>) &mdash; Blocks</h3>
<?php endif ?>
<div id="main_results">
    <p style="color:#aaa;margin:24px 0;">Loading&hellip;</p>
</div>


<?php elseif (!$isTailwind): ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     ADMINLTE
     ══════════════════════════════════════════════════════════════════════════ -->
<div class="d-flex align-items-center justify-content-between mb-3">
    <h5 class="mb-0 fw-semibold">
        <?php if ($coin): ?>
            <?php if ($coin->image): ?>
                <img src="<?= Html::encode($coin->image) ?>" width="20" height="20"
                     class="me-2 rounded" style="object-fit:contain" onerror="this.style.display='none'" alt="">
            <?php endif ?>
            <?= Html::encode($coin->name) ?>
            <span class="text-muted fw-normal fs-6">(<?= Html::encode($coin->symbol) ?>)</span>
        <?php else: ?>
            Blocks
        <?php endif ?>
    </h5>
    <small class="text-muted">refreshing in <span id="refresh-secs">60</span>s</small>
</div>
<div class="mb-3" style="height:3px;background:#e9ecef;border-radius:1px;">
    <div id="refresh-bar" class="h-100" style="width:100%;background:#0d6efd;transition:width 1s linear;border-radius:1px;"></div>
</div>
<div id="main_results">
    <div class="text-center text-muted py-4 small">Loading&hellip;</div>
</div>


<?php else: ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     TAILWIND
     ══════════════════════════════════════════════════════════════════════════ -->
<div class="flex items-center justify-between mb-4">
    <div class="flex items-center gap-2">
        <?php if ($coin && $coin->image): ?>
            <img src="<?= Html::encode($coin->image) ?>" width="22" height="22"
                 class="rounded object-contain" onerror="this.style.display='none'" alt="">
        <?php endif ?>
        <h2 class="text-base font-bold text-gray-800 dark:text-gray-100">
            <?= $coin ? Html::encode($coin->name) . ' <span class="text-gray-400 font-normal">(' . Html::encode($coin->symbol) . ')</span>' : 'Blocks' ?>
        </h2>
    </div>
    <span class="text-xs text-gray-400 dark:text-gray-500">
        refreshing in <span id="refresh-secs" class="font-mono font-medium">60</span>s
    </span>
</div>
<div class="mb-4 h-0.5 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
    <div id="refresh-bar" class="h-full bg-indigo-500 transition-all duration-1000 rounded-full" style="width:100%"></div>
</div>
<div id="main_results">
    <div class="text-center text-gray-400 dark:text-gray-500 py-8 text-sm">Loading&hellip;</div>
</div>

<?php endif ?>

<script>
var main_delay   = 60;
var main_elapsed = 0;
var main_timeout;
var bar  = document.getElementById('refresh-bar');
var secs = document.getElementById('refresh-secs');

function tick() {
    main_elapsed++;
    if (bar)  bar.style.width = Math.max(0, (1 - main_elapsed / main_delay) * 100) + '%';
    if (secs) secs.textContent = Math.max(0, main_delay - main_elapsed);
    if (main_elapsed >= main_delay) {
        main_elapsed = 0;
        main_refresh();
    } else {
        main_timeout = setTimeout(tick, 1000);
    }
}

function main_ready(data) {
    document.getElementById('main_results').innerHTML = data;
    <?php if ($isTailwind): ?>
    if (typeof lucide !== 'undefined') lucide.createIcons();
    <?php endif ?>
    main_elapsed = 0;
    if (bar)  bar.style.width = '100%';
    if (secs) secs.textContent = main_delay;
    clearTimeout(main_timeout);
    main_timeout = setTimeout(tick, 1000);
}

function main_refresh() {
    clearTimeout(main_timeout);
    $.get(<?= json_encode($resultsUrl) ?>, '', main_ready).fail(function () {
        main_elapsed = 0;
        main_timeout = setTimeout(tick, 1000);
    });
}

$(function () { main_refresh(); });
</script>
