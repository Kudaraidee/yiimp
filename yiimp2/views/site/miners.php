<?php

/** @var yii\web\View $this */

$isTailwind = Yii::$app->LayoutManager->isTailwind();
$isLegacy   = Yii::$app->LayoutManager->isLegacy();
?>

<?php if ($isLegacy): ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     LEGACY
     ══════════════════════════════════════════════════════════════════════════ -->
<div id="resume_update_button"
     style="color:#444;background:#ffd;border:1px solid #eea;padding:10px;
            margin:15px 20px;cursor:pointer;display:none;"
     onclick="auto_page_resume()" align="center">
    <b>Auto refresh is paused — click to resume</b>
</div>

<table cellspacing="20" width="100%">
<tr>
    <td valign="top" width="50%">
        <div id="miners_results"></div>
    </td>
    <td valign="top">
        <div id="pool_current_results"></div>
    </td>
</tr>
</table>


<?php elseif (!$isTailwind): ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     ADMINLTE
     ══════════════════════════════════════════════════════════════════════════ -->
<div id="resume_update_button"
     class="alert alert-warning d-flex align-items-center gap-2 mb-3"
     style="cursor:pointer;display:none!important;"
     onclick="auto_page_resume()">
    <i class="bi bi-pause-circle-fill"></i>
    <strong>Auto refresh is paused</strong> — click to resume
</div>

<div class="row gx-3">
    <div class="col-12 col-md-6">
        <div id="miners_results" class="min-vh-25"></div>
    </div>
    <div class="col-12 col-md-6">
        <div id="pool_current_results" class="min-vh-25"></div>
    </div>
</div>


<?php else: ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     TAILWIND
     ══════════════════════════════════════════════════════════════════════════ -->
<div id="resume_update_button"
     class="flex items-center gap-3 px-4 py-3 mb-4 rounded-xl
            bg-amber-50 dark:bg-amber-900/20
            border border-amber-200 dark:border-amber-800
            text-amber-700 dark:text-amber-300 text-sm cursor-pointer"
     style="display:none;"
     onclick="auto_page_resume()">
    <i data-lucide="pause-circle" class="w-5 h-5 shrink-0"></i>
    <strong>Auto refresh is paused</strong> — click to resume
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div id="miners_results"></div>
    <div id="pool_current_results"></div>
</div>

<?php endif ?>

<script>
function page_refresh() {
    miners_refresh();
    pool_current_refresh();
}

function select_algo(algo) {
    window.location.href = '/site/algo?algo=' + encodeURIComponent(algo) + '&r=/site/miners';
}

function pool_current_refresh() {
    $.get('/site/current_results', '', function (data) {
        $('#pool_current_results').html(data);
        if (typeof lucide !== 'undefined') lucide.createIcons();
    });
}

function miners_refresh() {
    $.get('/site/miners_results', '', function (data) {
        $('#miners_results').html(data);
        if (typeof lucide !== 'undefined') lucide.createIcons();
    });
}
</script>
