<?php

/** @var yii\web\View $this */

$this->title = 'DB Connections';

$isTailwind = Yii::$app->LayoutManager->isTailwind();
$isLegacy   = Yii::$app->LayoutManager->isLegacy();

use yii\helpers\Html;
$backUrl = Yii::$app->request->referrer ?: '/admin/dashboard';

?>

<?php if ($isLegacy): ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     LEGACY
     ══════════════════════════════════════════════════════════════════════════ -->
<?= Yii::$app->ViewUtils->getAdminSideBarLinks() ?>
&nbsp;-&nbsp;<a href='<?= Html::encode($backUrl) ?>'>&larr; Back</a><br>
<style>#refresh-bar{height:3px;background:#08c;transition:width 1s linear;margin-bottom:.5rem;}</style>
<div id="refresh-bar" style="width:100%"></div>
<div id="main_results"></div>


<?php elseif (!$isTailwind): ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     ADMINLTE
     ══════════════════════════════════════════════════════════════════════════ -->
<div class="d-flex align-items-center justify-content-between mb-3">
    <h5 class="mb-0 fw-semibold">DB Connections</h5>
    <div class="d-flex align-items-center gap-3">
        <small class="text-muted">refreshing in <span id="refresh-secs">30</span>s</small>
        <a href="<?= Html::encode($backUrl) ?>" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>
</div>
<div class="mb-3" style="height:3px;background:#e9ecef;border-radius:1px;">
    <div id="refresh-bar" class="h-100" style="width:100%;background:#0d6efd;transition:width 1s linear;border-radius:1px;"></div>
</div>
<div id="main_results"></div>


<?php else: ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     TAILWIND
     ══════════════════════════════════════════════════════════════════════════ -->
<div class="flex items-center justify-between mb-4">
    <h2 class="text-base font-bold text-gray-800 dark:text-gray-100">DB Connections</h2>
    <div class="flex items-center gap-3">
        <span class="text-xs text-gray-400 dark:text-gray-500">
            refreshing in <span id="refresh-secs" class="font-mono font-medium">30</span>s
        </span>
        <a href="<?= Html::encode($backUrl) ?>"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg
                  border border-gray-300 dark:border-gray-600
                  bg-white dark:bg-gray-700 text-gray-600 dark:text-gray-300
                  hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors">
            <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>Back
        </a>
    </div>
</div>
<div class="mb-4 h-0.5 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
    <div id="refresh-bar" class="h-full bg-indigo-500 transition-all duration-1000 rounded-full" style="width:100%"></div>
</div>
<div id="main_results"></div>

<?php endif ?>

<script>
var main_delay   = 30;   // seconds
var main_elapsed = 0;
var main_timeout;
var bar  = document.getElementById('refresh-bar');
var secs = document.getElementById('refresh-secs');

function tick() {
    main_elapsed++;
    var pct = Math.max(0, (1 - main_elapsed / main_delay) * 100);
    if (bar)  bar.style.width = pct + '%';
    if (secs) secs.textContent = Math.max(0, main_delay - main_elapsed);
    if (main_elapsed >= main_delay) {
        main_elapsed = 0;
        main_refresh();
    } else {
        main_timeout = setTimeout(tick, 1000);
    }
}

function main_ready(data) {
    $('#main_results').html(data);
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
    $.get('/admin/connections_results', '', main_ready).fail(function () {
        main_elapsed = 0;
        main_timeout = setTimeout(tick, 1000);
    });
}

$(function () { main_refresh(); });
</script>
