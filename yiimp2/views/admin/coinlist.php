<?php

/** @var yii\web\View                $this           */
/** @var yii\data\ActiveDataProvider $provider       */
/** @var int                         $totalInstalled */
/** @var int                         $totalActive    */
/** @var string                      $searchQuery    */
/** @var int                         $pageSize       */
/** @var array<int,string[]>         $marketsMap     */

use yii\helpers\Html;
use yii\widgets\LinkPager;

$this->title = 'Coins';

$pagination      = $provider->pagination;
$totalCoins      = $provider->totalCount;
$coins           = $provider->models;
$pageSizeOptions = [25, 50, 100, 250];
$isTailwind      = Yii::$app->LayoutManager->isTailwind();
$isLegacy        = Yii::$app->LayoutManager->isLegacy();

// Keep long coin names, versions and error messages inside their cells.
// The wrapper below scrolls horizontally on narrow screens.
$this->registerCss(<<<'CSS'
#maintable {
    width: 100%;
    min-width: 820px;
    table-layout: fixed;
}
#maintable th,
#maintable td {
    overflow-wrap: anywhere;
    word-break: break-word;
    vertical-align: top;
}
#maintable .coinlist-truncate {
    display: block;
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

/* ── Legacy: same card language as /site/mining ───────────────────────────── */
#cl-admin-bar {
    background: #fff; border: 1px solid #d8e2ef; border-radius: 8px;
    padding: 11px 14px 12px; margin: 12px 0 10px;
    box-shadow: 0 1px 2px rgba(20,40,80,.04);
    color: #334155; font-size: 11px;
}
#cl-bar-head { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
#cl-bar-label {
    font-size: 10px; font-weight: 700; letter-spacing: .12em;
    color: #8a94a6; text-transform: uppercase; white-space: nowrap;
}
.cl-search {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 4px 9px; border: 1px solid #dfe5ec; border-radius: 7px;
    background: #fafbfc; color: #8a94a6;
}
.cl-search:focus-within { border-color: #a9c4ea; background: #fff; }
.cl-search input {
    width: 190px; border: 0; outline: 0; background: transparent;
    font: 11px/1.4 inherit; color: #26385a;
}
.cl-search input::placeholder { color: #aab4c4; }
.cl-btn {
    border: 1px solid #dfe5ec; background: #fafbfc; color: #64748b;
    border-radius: 999px; padding: 3px 10px; font-size: 10px;
    font-weight: 700; line-height: 1.6; cursor: pointer; text-decoration: none;
}
.cl-btn:hover { border-color: #b9d4ff; color: #2c5aa0; background: #f7faff; }
.cl-counts { margin-left: auto; display: flex; gap: 6px; flex-wrap: wrap; }
.cl-pill {
    display: inline-block; border-radius: 999px; padding: 2px 9px;
    font-size: 9px; font-weight: 800; line-height: 15px; white-space: nowrap;
    border: 1px solid transparent;
}
.cl-pill.all { background: #eef4ff; color: #2c5aa0; border-color: #d7e5ff; }
.cl-pill.run { background: #e8f7ee; color: #187d46; border-color: #ccebd8; }
.cl-pill.installed { background: #fff5e6; color: #b45309; border-color: #fde3b8; }
.cl-live {
    display: flex; align-items: center; gap: 6px; white-space: nowrap;
    font-size: 11px; color: #5b6675;
}
.cl-live i {
    width: 7px; height: 7px; border-radius: 50%; background: #2fae5a;
    box-shadow: 0 0 0 3px rgba(47,174,90,.15);
}
#cl-bar-links {
    display: flex; align-items: center; gap: 6px; flex-wrap: wrap;
    border-top: 1px solid #eef2f7; margin-top: 10px; padding-top: 9px;
}
#cl-bar-links .cl-bar-label { margin-right: 2px; }
.cl-page {
    display: inline-block; border: 1px solid #dfe5ec; background: #fafbfc;
    color: #64748b; border-radius: 999px; padding: 3px 10px;
    font-size: 10px; font-weight: 700; line-height: 1.6; text-decoration: none;
}
.cl-page:hover { border-color: #b9d4ff; color: #2c5aa0; }
.cl-page.active { background: #2c5aa0; border-color: #2c5aa0; color: #fff; }
.cl-add {
    margin-left: auto; display: inline-block; background: #2c5aa0;
    border: 1px solid #2c5aa0; color: #fff; border-radius: 999px;
    padding: 3px 12px; font-size: 10px; font-weight: 800;
    line-height: 1.6; text-decoration: none;
}
.cl-add:hover { background: #1f467f; border-color: #1f467f; color: #fff; }

.cl-legacy.card {
    border: 1px solid #d8e2ef !important; border-radius: 8px !important;
    box-shadow: 0 1px 2px rgba(20,40,80,.04) !important;
    overflow: hidden; background: #fff; color: #334155; font-size: 11px;
}
.cl-legacy .cl-card-head {
    display: flex; align-items: center; justify-content: space-between; gap: 10px;
    min-height: 40px; padding: 9px 13px;
    background: #f8fafc; border-bottom: 1px solid #e8eef6;
}
.cl-legacy .cl-title { font-size: 13px; font-weight: 800; color: #26385a; }
.cl-legacy .cl-sub { font-size: 10px; color: #8a94a6; font-weight: 500; }
.cl-legacy .cl-scope {
    font-size: 9px; font-weight: 800; color: #2c5aa0; background: #eef4ff;
    border: 1px solid #d7e5ff; border-radius: 999px; padding: 2px 9px;
    line-height: 15px; white-space: nowrap;
}
.cl-legacy .cl-summary {
    display: flex; gap: 18px; flex-wrap: wrap; padding: 8px 13px;
    background: #fcfdff; border-bottom: 1px solid #eef2f7;
    font-size: 10px; color: #8a94a6;
}
.cl-legacy .cl-summary b { color: #33405a; font-weight: 700; }
.cl-legacy .cl-summary i {
    display: inline-block; width: 6px; height: 6px; border-radius: 50%;
    background: #2fae5a; box-shadow: 0 0 0 3px rgba(47,174,90,.12); margin-right: 5px;
}
.cl-legacy table#maintable {
    width: 100% !important; min-width: 820px; margin: 0 !important;
    border-collapse: collapse; table-layout: fixed;
    font-size: 11px; color: #334155;
}
.cl-legacy table#maintable th {
    background: #fff !important; padding: 8px 9px !important;
    font-size: 9px !important; font-weight: 800 !important;
    letter-spacing: .05em; text-transform: uppercase; color: #8a94a6;
    border-bottom: 1px solid #e2e8f0 !important; white-space: nowrap;
}
.cl-legacy table#maintable td {
    padding: 8px 9px !important; border-bottom: 1px solid #eef2f7 !important;
    vertical-align: middle; font-size: 11px;
}
.cl-legacy table#maintable tbody tr:last-child td { border-bottom: 0; }
.cl-legacy table#maintable tbody tr:hover td { background: #f7faff !important; }
.cl-legacy table#maintable tbody tr.table-danger td { background: #fff8f8 !important; }
.cl-legacy table#maintable tbody tr.table-danger td:first-child { box-shadow: inset 3px 0 0 #dc2626; }
.cl-legacy table#maintable td:first-child > div { display: flex; align-items: center; gap: 8px; min-width: 0; }
.cl-legacy table#maintable td:first-child img {
    width: 26px !important; height: 26px !important; object-fit: contain;
    border: 1px solid #e8eef6; border-radius: 6px; background: #fff; padding: 2px;
}
.cl-legacy table#maintable td:first-child a:first-child {
    color: #26385a; font-weight: 800; font-size: 11px; text-decoration: none;
}
.cl-legacy table#maintable td:first-child a:first-child:hover { color: #2c5aa0; }
.cl-legacy table#maintable td:first-child a:last-child {
    color: #94a3b8; font-size: 9px; font-family: ui-monospace, monospace; text-decoration: none;
}
.cl-legacy .badge {
    border-radius: 999px !important; font-size: 9px !important;
    font-weight: 800 !important; line-height: 15px; padding: 2px 8px !important;
}
.cl-legacy .badge.bg-success { background: #e8f7ee !important; color: #187d46 !important; border: 1px solid #ccebd8; }
.cl-legacy .badge.bg-warning { background: #fff5e6 !important; color: #b45309 !important; border: 1px solid #fde3b8; }
.cl-legacy .badge.bg-secondary { background: #f1f5f9 !important; color: #94a3b8 !important; border: 1px solid #e2e8f0; }
.cl-legacy .badge.bg-light { background: #eef4ff !important; color: #2c5aa0 !important; border: 1px solid #d7e5ff; }
.cl-legacy .card-footer {
    background: #fcfdff !important; border-top: 1px solid #eef2f7 !important;
    color: #98a3b5; font-size: 10px;
}
.cl-legacy .pagination { gap: 4px; margin: 0; }
.cl-legacy .page-link {
    border: 1px solid #dfe5ec !important; border-radius: 6px !important;
    color: #64748b; font-size: 10px; padding: 3px 8px;
}
.cl-legacy .page-item.active .page-link { background: #2c5aa0; border-color: #2c5aa0; color: #fff; }
@media (max-width: 720px) {
    #cl-bar-head { align-items: flex-start; }
    .cl-counts, .cl-live { margin-left: 0; }
    .cl-search input { width: 140px; }
}
CSS);

$pagerWidget = fn(string $extraClass = '') => LinkPager::widget([
    'pagination'            => $pagination,
    'options'               => ['class' => "pagination pagination-sm mb-0 $extraClass"],
    'linkOptions'           => ['class' => 'page-link'],
    'linkContainerOptions'  => ['class' => 'page-item'],
    'disabledListItemSubTagOptions' => ['class' => 'page-link'],
    'maxButtonCount'        => 7,
    'firstPageLabel'        => '«',
    'lastPageLabel'         => '»',
]);

// ── Per-scheme helpers ────────────────────────────────────────────────────────

if ($isTailwind) {
    $pill = fn(string $label, string $color) =>
        "<span class=\"inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full
            bg-{$color}-50 text-{$color}-700 dark:bg-{$color}-900/30 dark:text-{$color}-300\">
            {$label}</span>";

    $statusBadgeFn = fn($coin) => $coin->enable
        ? $pill('running',   'green')
        : ($coin->installed ? $pill('installed', 'amber') : $pill('inactive',  'gray'));

    $algoBadgeFn = fn(string $algo) =>
        "<span class=\"inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full
            bg-indigo-50 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300 font-mono\">
            " . Html::encode($algo) . "</span>";

    $marketBadgeFn = fn(string $name) =>
        "<span class=\"inline-flex items-center px-1.5 py-0.5 text-xs rounded
            bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400 mr-1 mb-0.5\">
            " . Html::encode($name) . "</span>";

} else {
    $statusBadgeFn = fn($coin) => $coin->enable
        ? '<span class="badge bg-success">running</span>'
        : ($coin->installed
            ? '<span class="badge bg-warning text-dark">installed</span>'
            : '<span class="badge bg-secondary">inactive</span>');

    $algoBadgeFn = fn(string $algo) =>
        '<span class="badge bg-light text-dark border font-monospace">' . Html::encode($algo) . '</span>';

    $marketBadgeFn = fn(string $name) =>
        '<span class="badge bg-light text-dark border me-1">' . Html::encode($name) . '</span>';
}

?>
<?php if ($isTailwind): ?>

<!-- ══════════════════════════════════════════════════════════════════════════
     TAILWIND version
     ══════════════════════════════════════════════════════════════════════════ -->

<div class="rounded-xl border border-gray-200 dark:border-gray-700
            bg-white dark:bg-gray-800 shadow-sm overflow-hidden">

    <!-- ── Header ──────────────────────────────────────────────────────────── -->
    <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700
                flex items-center gap-3 flex-wrap">

        <div class="flex items-center gap-1.5 text-xs">
            <span class="px-2 py-0.5 rounded-full bg-gray-100 dark:bg-gray-700
                         text-gray-600 dark:text-gray-300 font-medium">
                <?= $totalCoins ?> coins
            </span>
            <span class="px-2 py-0.5 rounded-full bg-green-50 dark:bg-green-900/30
                         text-green-700 dark:text-green-300 font-medium">
                <?= $totalActive ?> running
            </span>
            <span class="px-2 py-0.5 rounded-full bg-blue-50 dark:bg-blue-900/30
                         text-blue-700 dark:text-blue-300 font-medium">
                <?= $totalInstalled ?> installed
            </span>
        </div>

        <?= Html::beginForm(['admin/coinlist'], 'get',
            ['class' => 'flex items-center gap-1 ml-auto']) ?>
        <?= Html::hiddenInput('pageSize', $pageSize) ?>
        <?= Html::textInput('q', $searchQuery, [
            'class'        => 'px-3 py-1.5 text-sm rounded-lg border border-gray-300
                               dark:border-gray-600 bg-white dark:bg-gray-700
                               text-gray-900 dark:text-gray-100
                               focus:outline-none focus:ring-2 focus:ring-indigo-500
                               placeholder-gray-400 dark:placeholder-gray-500',
            'style'        => 'width:170px',
            'placeholder'  => 'Search…',
            'autocomplete' => 'off',
        ]) ?>
        <?= Html::submitButton('Search', [
            'class' => 'px-3 py-1.5 text-sm rounded-lg border border-gray-300
                        dark:border-gray-600 bg-white dark:bg-gray-700
                        text-gray-700 dark:text-gray-300
                        hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors',
        ]) ?>
        <?php if ($searchQuery !== ''): ?>
            <?= Html::a('✕', ['admin/coinlist', 'pageSize' => $pageSize], [
                'class' => 'px-2 py-1.5 text-sm rounded-lg text-red-500
                            hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors',
                'title' => 'Clear',
            ]) ?>
        <?php endif ?>
        <?= Html::endForm() ?>

        <div class="flex items-center gap-1 text-xs">
            <span class="text-gray-400 dark:text-gray-500">Show</span>
            <?php foreach ($pageSizeOptions as $n):
                $a = ($n === $pageSize); ?>
            <?= Html::a($n, ['admin/coinlist', 'q' => $searchQuery, 'pageSize' => $n, 'page' => 1], [
                'class' => 'px-2 py-1 rounded font-medium transition-colors ' . ($a
                    ? 'bg-indigo-600 text-white'
                    : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700'),
            ]) ?>
            <?php endforeach ?>
        </div>

        <?= Html::a('+ Add coin', ['/admin/coin_create'], [
            'class' => 'px-3 py-1.5 text-sm font-medium rounded-lg
                        bg-indigo-600 hover:bg-indigo-700 text-white transition-colors',
        ]) ?>
    </div>

    <?php if ($totalCoins === 0): ?>
    <div class="px-4 py-8 text-center text-sm text-gray-400 dark:text-gray-500">
        No coins found<?= $searchQuery !== '' ? ' for <strong class="text-gray-600 dark:text-gray-300">' . Html::encode($searchQuery) . '</strong>' : '' ?>.
    </div>
    <?php else: ?>

    <!-- ── Table ────────────────────────────────────────────────────────────── -->
    <div class="overflow-x-auto">
        <table id="maintable" class="w-full table-fixed text-sm">
        <colgroup>
            <col style="width:20%">
            <col style="width:8%">
            <col style="width:8%">
            <col style="width:11%">
            <col style="width:7%">
            <col style="width:10%">
            <col style="width:18%">
            <col style="width:18%">
        </colgroup>
        <thead>
        <tr class="bg-gray-50 dark:bg-gray-700/50 text-xs font-semibold
                   text-gray-500 dark:text-gray-400 uppercase tracking-wider">
            <th class="px-4 py-2.5 text-left" data-sorter="text">Coin</th>
            <th class="px-3 py-2.5 text-left" data-sorter="text">Algo</th>
            <th class="px-3 py-2.5 text-left" data-sorter="text">Status</th>
            <th class="px-3 py-2.5 text-left" data-sorter="text">Version</th>
            <th class="px-3 py-2.5 text-right" data-sorter="numeric">Height</th>
            <th class="px-3 py-2.5 text-left" data-sorter="numeric">Added</th>
            <th class="px-3 py-2.5 text-left" data-sorter="text">Message</th>
            <th class="px-3 py-2.5 text-left" data-sorter="false">Links</th>
        </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-700/50">
        <?php foreach ($coins as $coin):
            $markets    = $marketsMap[$coin->id] ?? [];
            $hasError   = !empty($coin->errors);
            $created    = Yii::$app->ConversionUtils->datetoa2($coin->created);
            $version    = (string) $coin->version;
            $errors     = (string) $coin->errors;
        ?>
        <tr class="<?= $hasError ? 'bg-red-50/50 dark:bg-red-900/10' : 'hover:bg-gray-50/70 dark:hover:bg-gray-700/20' ?>
                   transition-colors">

            <td class="px-4 py-2.5">
                <div class="flex items-center gap-2.5">
                    <?php if (!empty($coin->image)): ?>
                    <img src="<?= Html::encode($coin->image) ?>" width="22" height="22"
                         alt="" class="rounded object-contain flex-shrink-0"
                         onerror="this.style.display='none'">
                    <?php else: ?>
                    <span class="w-5 flex-shrink-0"></span>
                    <?php endif ?>
                    <div class="min-w-0">
                        <div class="font-medium text-gray-900 dark:text-gray-100 leading-tight">
                            <?= Html::a(Html::encode($coin->name),
                                ['/admin/coinwallet', 'id' => $coin->id],
                                ['class' => 'hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors']) ?>
                        </div>
                        <div class="text-xs text-gray-400 dark:text-gray-500 font-mono coinlist-truncate">
                            <?= Html::a(Html::encode($coin->symbol),
                                ['/admin/coinwallet_update', 'id' => $coin->id],
                                ['class' => 'hover:text-indigo-500 transition-colors']) ?>
                        </div>
                    </div>
                </div>
            </td>

            <td class="px-3 py-2.5"><?= $algoBadgeFn($coin->algo) ?></td>
            <td class="px-3 py-2.5"><?= $statusBadgeFn($coin) ?></td>

            <td class="px-3 py-2.5 text-xs font-mono text-gray-500 dark:text-gray-400">
                <span class="coinlist-truncate" title="<?= Html::encode($version) ?>"><?= Html::encode(substr($version, 0, 22)) ?></span>
            </td>

            <td class="px-3 py-2.5 text-right text-xs text-gray-500 dark:text-gray-400 tabular-nums">
                <?= number_format((int) $coin->block_height) ?>
            </td>

            <td class="px-3 py-2.5 text-xs text-gray-400 dark:text-gray-500 whitespace-nowrap"
                data="<?= (int) $coin->created ?>">
                <?= $created ?>
            </td>

            <td class="px-3 py-2.5 text-xs <?= $hasError ? 'text-red-600 dark:text-red-400 font-medium' : 'text-gray-400 dark:text-gray-500' ?>">
                <span class="coinlist-truncate" title="<?= Html::encode($errors) ?>"><?= Html::encode(substr($errors, 0, 40)) ?></span>
            </td>

            <td class="px-3 py-2.5">
                <div class="flex flex-wrap items-center gap-1">
                    <?php if (!empty($coin->link_bitcointalk)): ?>
                        <?= Html::a('forum', $coin->link_bitcointalk,
                            ['target' => '_blank', 'class' => 'text-xs text-indigo-500 hover:underline']) ?>
                    <?php endif ?>
                    <?php if (!empty($coin->link_github)): ?>
                        <?= Html::a('git', $coin->link_github,
                            ['target' => '_blank', 'class' => 'text-xs text-indigo-500 hover:underline']) ?>
                    <?php endif ?>
                    <?= Html::a('g',
                        'https://google.com/search?q=' . urlencode($coin->name . ' ' . $coin->symbol . ' bitcointalk'),
                        ['target' => '_blank', 'class' => 'text-xs text-gray-400 hover:text-indigo-500 hover:underline', 'title' => 'Google']) ?>
                    <?php foreach ($markets as $m): ?>
                        <?= $marketBadgeFn($m) ?>
                    <?php endforeach ?>
                </div>
            </td>
        </tr>
        <?php endforeach ?>
        </tbody>
        </table>
    </div>

    <!-- ── Footer ────────────────────────────────────────────────────────── -->
    <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700
                flex items-center justify-between gap-4 flex-wrap">
        <span class="text-xs text-gray-400 dark:text-gray-500">
            Page <?= $pagination->page + 1 ?> of <?= $pagination->pageCount ?>
            &nbsp;·&nbsp; <?= $totalCoins ?> coins
            <?php if ($searchQuery !== ''): ?>
                matching &ldquo;<?= Html::encode($searchQuery) ?>&rdquo;
            <?php endif ?>
        </span>
        <nav><?= $pagerWidget() ?></nav>
    </div>

    <?php endif ?>
</div>

<?php
// Vanilla sort for Tailwind (no jQuery available)
$this->registerJs("
document.addEventListener('DOMContentLoaded', function () {
    var table  = document.getElementById('maintable');
    if (!table) return;
    var tbody  = table.tBodies[0];
    var ths    = Array.from(table.tHead.rows[0].cells);
    var asc    = ths.map(function () { return true; });
    ths.forEach(function (th, col) {
        if (th.dataset.sorter === 'false') return;
        th.style.cursor = 'pointer';
        th.addEventListener('click', function () {
            var rows = Array.from(tbody.rows);
            rows.sort(function (a, b) {
                var av = (a.cells[col].getAttribute('data') || a.cells[col].textContent || '').trim();
                var bv = (b.cells[col].getAttribute('data') || b.cells[col].textContent || '').trim();
                var n  = parseFloat(av) - parseFloat(bv);
                return isNaN(n) ? (asc[col] ? av.localeCompare(bv) : bv.localeCompare(av))
                                : (asc[col] ? n : -n);
            });
            asc[col] = !asc[col];
            rows.forEach(function (r) { tbody.appendChild(r); });
        });
    });
});
");
?>

<?php else: ?>

<!-- ══════════════════════════════════════════════════════════════════════════
     BOOTSTRAP 5 version  (legacy + AdminLTE)
     ══════════════════════════════════════════════════════════════════════════ -->

<?php if ($isLegacy): ?>
<div id="cl-admin-bar">
    <div id="cl-bar-head">
        <span id="cl-bar-label">Coins</span>

        <?= Html::beginForm(['admin/coinlist'], 'get', ['class' => 'd-flex align-items-center gap-1']) ?>
        <?= Html::hiddenInput('pageSize', $pageSize) ?>
        <label class="cl-search">
            <span aria-hidden="true">⌕</span>
            <?= Html::textInput('q', $searchQuery, [
                'placeholder'  => 'Search coins…',
                'autocomplete' => 'off',
                'aria-label'   => 'Search coins',
            ]) ?>
        </label>
        <?= Html::submitButton('Search', ['class' => 'cl-btn']) ?>
        <?php if ($searchQuery !== ''): ?>
            <?= Html::a('✕', ['admin/coinlist', 'pageSize' => $pageSize],
                ['class' => 'cl-btn', 'title' => 'Clear']) ?>
        <?php endif ?>
        <?= Html::endForm() ?>

        <div class="cl-counts">
            <span class="cl-pill all"><?= $totalCoins ?> coins</span>
            <span class="cl-pill run"><i style="display:inline-block;width:6px;height:6px;border-radius:50%;background:#187d46;margin-right:4px;"></i><?= $totalActive ?> running</span>
            <span class="cl-pill installed"><?= $totalInstalled ?> installed</span>
        </div>
        <span class="cl-live"><i></i> pool db</span>
    </div>
    <div id="cl-bar-links">
        <span class="cl-bar-label">Rows</span>
        <?php foreach ($pageSizeOptions as $n): ?>
            <?= Html::a($n, ['admin/coinlist', 'q' => $searchQuery, 'pageSize' => $n, 'page' => 1], [
                'class' => 'cl-page' . ($n === $pageSize ? ' active' : ''),
            ]) ?>
        <?php endforeach ?>
        <?= Html::a('+ Add coin', ['/admin/coin_create'], ['class' => 'cl-add']) ?>
    </div>
</div>
<?php endif ?>

<div class="card shadow-sm cl-card<?= $isLegacy ? ' cl-legacy' : '' ?>">

<?php if ($isLegacy): ?>
    <div class="cl-card-head">
        <span><span class="cl-title">Coin list</span><span class="cl-sub"> · catalog · node status · markets</span></span>
        <span class="cl-scope"><?= $totalCoins ?> coins</span>
    </div>
    <div class="cl-summary">
        <span><i></i><b><?= $totalCoins ?> coins</b> in catalog</span>
        <span>Running <b><?= $totalActive ?></b></span>
        <span>Installed <b><?= $totalInstalled ?></b></span>
        <?php if ($searchQuery !== ''): ?>
            <span>Filter <b><?= Html::encode($searchQuery) ?></b></span>
        <?php endif ?>
    </div>
<?php else: ?>
    <div class="card-header d-flex align-items-center gap-2 flex-wrap py-2">
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-secondary"><?= $totalCoins ?> coins</span>
            <span class="badge bg-success"><?= $totalActive ?> running</span>
            <span class="badge bg-info text-dark"><?= $totalInstalled ?> installed</span>
        </div>

        <?= Html::beginForm(['admin/coinlist'], 'get', ['class' => 'd-flex align-items-center gap-1 ms-auto']) ?>
        <?= Html::hiddenInput('pageSize', $pageSize) ?>
        <?= Html::textInput('q', $searchQuery, [
            'class'        => 'form-control form-control-sm',
            'placeholder'  => 'Search…',
            'style'        => 'width:170px',
            'autocomplete' => 'off',
        ]) ?>
        <?= Html::submitButton('Search', ['class' => 'btn btn-sm btn-outline-secondary']) ?>
        <?php if ($searchQuery !== ''): ?>
            <?= Html::a('✕', ['admin/coinlist', 'pageSize' => $pageSize],
                ['class' => 'btn btn-sm btn-outline-danger', 'title' => 'Clear']) ?>
        <?php endif ?>
        <?= Html::endForm() ?>

        <div class="btn-group btn-group-sm">
            <?php foreach ($pageSizeOptions as $n):
                $active = ($n === $pageSize); ?>
            <?= Html::a($n, ['admin/coinlist', 'q' => $searchQuery, 'pageSize' => $n, 'page' => 1], [
                'class' => 'btn ' . ($active ? 'btn-secondary' : 'btn-outline-secondary'),
            ]) ?>
            <?php endforeach ?>
        </div>

        <?= Html::a('+ Add coin', ['/admin/coin_create'], ['class' => 'btn btn-sm btn-success']) ?>
    </div>
<?php endif ?>

    <?php if ($totalCoins === 0): ?>
    <div class="card-body">
        <div class="alert alert-info mb-0">
            No coins found<?= $searchQuery !== '' ? ' for <strong>' . Html::encode($searchQuery) . '</strong>' : '' ?>.
        </div>
    </div>
    <?php else: ?>

    <div class="card-body p-0">
      <div class="table-responsive">
        <table id="maintable" class="table table-hover table-sm table-bordered mb-0">
        <colgroup>
            <col style="width:20%">
            <col style="width:8%">
            <col style="width:8%">
            <col style="width:11%">
            <col style="width:7%">
            <col style="width:10%">
            <col style="width:18%">
            <col style="width:18%">
        </colgroup>
        <thead class="table-light">
        <tr>
            <th data-sorter="text">Coin</th>
            <th data-sorter="text">Algo</th>
            <th data-sorter="text">Status</th>
            <th data-sorter="text">Version</th>
            <th data-sorter="numeric" class="text-end">Height</th>
            <th data-sorter="numeric">Added</th>
            <th data-sorter="text">Message</th>
            <th data-sorter="false">Links</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($coins as $coin):
            $markets  = $marketsMap[$coin->id] ?? [];
            $hasError = !empty($coin->errors);
            $created  = Yii::$app->ConversionUtils->datetoa2($coin->created);
            $version  = (string) $coin->version;
            $errors   = (string) $coin->errors;
        ?>
        <tr class="<?= $hasError ? 'table-danger' : '' ?>">
            <td>
                <div class="d-flex align-items-center gap-2">
                    <?php if (!empty($coin->image)): ?>
                    <img src="<?= Html::encode($coin->image) ?>" width="20" height="20"
                         alt="" style="object-fit:contain;flex-shrink:0;"
                         onerror="this.style.display='none'">
                    <?php else: ?>
                    <span style="width:20px;flex-shrink:0;"></span>
                    <?php endif ?>
                    <div class="lh-sm" style="min-width:0;">
                        <div class="text-break"><?= Html::a('<strong>' . Html::encode($coin->name) . '</strong>',
                            ['/admin/coinwallet', 'id' => $coin->id],
                            ['encode' => false, 'class' => 'text-decoration-none']) ?></div>
                        <div class="text-muted text-truncate" style="font-size:.75rem;">
                            <?= Html::a(Html::encode($coin->symbol),
                                ['/admin/coinwallet_update', 'id' => $coin->id],
                                ['class' => 'text-muted']) ?>
                        </div>
                    </div>
                </div>
            </td>
            <td><?= $algoBadgeFn($coin->algo) ?></td>
            <td><?= $statusBadgeFn($coin) ?></td>
            <td class="text-muted small font-monospace">
                <span class="coinlist-truncate" title="<?= Html::encode($version) ?>"><?= Html::encode(substr($version, 0, 22)) ?></span>
            </td>
            <td class="text-end text-muted small">
                <?= number_format((int) $coin->block_height) ?>
            </td>
            <td class="text-muted small" data="<?= (int) $coin->created ?>"><?= $created ?></td>
            <td class="small <?= $hasError ? 'text-danger fw-semibold' : 'text-muted' ?>">
                <span class="coinlist-truncate" title="<?= Html::encode($errors) ?>"><?= Html::encode(substr($errors, 0, 40)) ?></span>
            </td>
            <td class="small">
                <div class="d-flex flex-wrap align-items-center gap-1">
                    <?php if (!empty($coin->link_bitcointalk)): ?>
                        <?= Html::a('forum', $coin->link_bitcointalk, ['target' => '_blank']) ?>
                    <?php endif ?>
                    <?php if (!empty($coin->link_github)): ?>
                        <?= Html::a('git', $coin->link_github, ['target' => '_blank']) ?>
                    <?php endif ?>
                    <?= Html::a('google',
                        'https://google.com/search?q=' . urlencode($coin->name . ' ' . $coin->symbol . ' bitcointalk'),
                        ['target' => '_blank', 'class' => 'text-muted']) ?>
                    <?php foreach ($markets as $m): ?>
                        <?= $marketBadgeFn($m) ?>
                    <?php endforeach ?>
                </div>
            </td>
        </tr>
        <?php endforeach ?>
        </tbody>
        </table>
      </div>
    </div>

    <div class="card-footer d-flex align-items-center justify-content-between py-2">
        <small class="text-muted">
            Page <?= $pagination->page + 1 ?> of <?= $pagination->pageCount ?>
            &nbsp;·&nbsp; <?= $totalCoins ?> coins
            <?php if ($searchQuery !== ''): ?>
                matching &ldquo;<?= Html::encode($searchQuery) ?>&rdquo;
            <?php endif ?>
        </small>
        <nav><?= $pagerWidget() ?></nav>
    </div>

    <?php endif ?>
</div>

<?php
Yii::$app->ViewUtils->JavascriptReady("
    \$('#maintable').tablesorter({
        textExtraction: { 5: function(node,table,n){ return \$(node).attr('data'); } },
        widgets: ['zebra'],
        widgetOptions: {}
    });
    \$('.tablesorter-header').not('.sorter-false').css('cursor','pointer');
");
?>

<?php endif ?>
