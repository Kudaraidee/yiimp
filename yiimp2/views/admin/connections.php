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
     LEGACY — mining-style database console (same chrome as /site/mining)
     ══════════════════════════════════════════════════════════════════════════ -->
<style>
#dbconn_results { display:flex; flex-direction:column; gap:16px; }
#dbconn_results .ad-toolbar {
    display:flex; align-items:center; gap:12px; flex-wrap:wrap;
    padding:9px 12px; margin:0; border:1px solid #d8e2ef; border-radius:7px;
    background:#fff; box-shadow:0 1px 2px rgba(20,40,80,.04);
}
#dbconn_results .ad-toolbar-brand {
    display:flex; align-items:center; gap:9px; padding-right:12px;
    border-right:1px solid #e8eef6;
}
#dbconn_results .ad-live-dot {
    width:8px; height:8px; border-radius:50%; background:#2fae5a;
    box-shadow:0 0 0 3px rgba(47,174,90,.15);
}
#dbconn_results .ad-brand-text { display:flex; flex-direction:column; line-height:1.25; }
#dbconn_results .ad-brand-text b { color:#26385a; font-size:13px; }
#dbconn_results .ad-brand-text small { color:#8a94a6; font-size:9px; }
#dbconn_results .mc-badge {
    display:inline-block; padding:2px 7px; border-radius:999px; background:#eef4ff;
    border:1px solid #d7e5ff; color:#2c5aa0; font-size:9px; font-weight:700;
    line-height:14px; white-space:nowrap; text-decoration:none;
}
#dbconn_results .ad-toolbar-links { display:flex; flex-wrap:wrap; gap:6px; flex:1 1 auto; }
#dbconn_results .ad-toolbar-links a {
    display:inline-block; padding:3px 8px; border-radius:999px; border:1px solid #dfe7f2;
    background:#f8fafc; color:#4a586c; font-size:10px; line-height:15px;
    text-decoration:none; white-space:nowrap;
}
#dbconn_results .ad-toolbar-links a:hover {
    background:#eef4ff; border-color:#c9d9f5; color:#2c5aa0; text-decoration:none;
}
#dbconn_results .dbc-live {
    margin-left:auto; white-space:nowrap; font-size:11px; color:#5b6675;
    display:flex; align-items:center; gap:6px;
}
#dbconn_results .dbc-live i {
    width:7px; height:7px; border-radius:50%; background:#2fae5a;
    box-shadow:0 0 0 3px rgba(47,174,90,.15);
}
#dbconn_results .dbc-live b { font-family:monospace; color:#26385a; }
#dbconn_results .ad-admin-nav {
    display:flex; align-items:center; gap:10px; flex-wrap:wrap;
    padding:5px 12px; margin-top:-8px; font-size:10px; color:#8a94a6;
}
#dbconn_results .ad-admin-nav > span {
    font-weight:800; letter-spacing:.1em; text-transform:uppercase; color:#a7b0c0;
}
#dbconn_results .ad-admin-nav a { color:#5b6675; text-decoration:none; }
#dbconn_results .ad-admin-nav a:hover { color:#2c5aa0; text-decoration:underline; }
#dbconn_results .dbc-progress {
    height:3px; margin-top:-8px; border-radius:2px; overflow:hidden;
    background:#e8eef6;
}
#dbconn_results #refresh-bar {
    height:100%; width:100%; background:#2c5aa0;
    transition:width 1s linear;
}
@media (max-width:900px) {
    #dbconn_results .dbc-live { margin-left:0; }
    #dbconn_results .ad-toolbar-brand { border-right:0; }
}
</style>

<div id="dbconn_results">

    <div class="ad-toolbar">
        <div class="ad-toolbar-brand">
            <span class="ad-live-dot"></span>
            <span class="ad-brand-text"><b>DB Connections</b><small>database diagnostics · live</small></span>
            <span class="mc-badge">AUTO · 30s</span>
        </div>
        <div class="ad-toolbar-links">
            <a href="<?= Html::encode($backUrl) ?>">&larr; Back</a>
        </div>
        <span class="dbc-live"><i></i> next refresh in <b id="refresh-secs">30</b>s</span>
    </div>

    <div class="ad-admin-nav">
        <span>Admin</span>
        <?= str_replace('&nbsp;', '', Yii::$app->ViewUtils->getAdminSideBarLinks()) ?>
    </div>

    <div class="dbc-progress"><div id="refresh-bar"></div></div>
    <div id="main_results"></div>

</div>


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
