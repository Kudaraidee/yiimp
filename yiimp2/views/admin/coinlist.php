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
        <table id="maintable" class="w-full text-sm">
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
                    <div>
                        <div class="font-medium text-gray-900 dark:text-gray-100 leading-tight">
                            <?= Html::a(Html::encode($coin->name),
                                ['/admin/coinwallet', 'id' => $coin->id],
                                ['class' => 'hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors']) ?>
                        </div>
                        <div class="text-xs text-gray-400 dark:text-gray-500 font-mono">
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
                <?= Html::encode(substr((string) $coin->version, 0, 22)) ?>
            </td>

            <td class="px-3 py-2.5 text-right text-xs text-gray-500 dark:text-gray-400 tabular-nums">
                <?= number_format((int) $coin->block_height) ?>
            </td>

            <td class="px-3 py-2.5 text-xs text-gray-400 dark:text-gray-500 whitespace-nowrap"
                data="<?= (int) $coin->created ?>">
                <?= $created ?>
            </td>

            <td class="px-3 py-2.5 text-xs <?= $hasError ? 'text-red-600 dark:text-red-400 font-medium' : 'text-gray-400 dark:text-gray-500' ?>">
                <?= Html::encode(substr((string) $coin->errors, 0, 40)) ?>
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

<div class="card shadow-sm">

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

    <?php if ($totalCoins === 0): ?>
    <div class="card-body">
        <div class="alert alert-info mb-0">
            No coins found<?= $searchQuery !== '' ? ' for <strong>' . Html::encode($searchQuery) . '</strong>' : '' ?>.
        </div>
    </div>
    <?php else: ?>

    <div class="card-body p-0">
        <table id="maintable" class="table table-hover table-sm table-bordered mb-0">
        <thead class="table-light">
        <tr>
            <th data-sorter="text" style="width:260px">Coin</th>
            <th data-sorter="text" style="width:90px">Algo</th>
            <th data-sorter="text" style="width:90px">Status</th>
            <th data-sorter="text" style="width:115px">Version</th>
            <th data-sorter="numeric" style="width:80px" class="text-end">Height</th>
            <th data-sorter="numeric" style="width:100px">Added</th>
            <th data-sorter="text">Message</th>
            <th data-sorter="false">Links</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($coins as $coin):
            $markets  = $marketsMap[$coin->id] ?? [];
            $hasError = !empty($coin->errors);
            $created  = Yii::$app->ConversionUtils->datetoa2($coin->created);
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
                    <div class="lh-sm">
                        <div><?= Html::a('<strong>' . Html::encode($coin->name) . '</strong>',
                            ['/admin/coinwallet', 'id' => $coin->id],
                            ['encode' => false, 'class' => 'text-decoration-none']) ?></div>
                        <div class="text-muted" style="font-size:.75rem;">
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
                <?= Html::encode(substr((string) $coin->version, 0, 22)) ?>
            </td>
            <td class="text-end text-muted small">
                <?= number_format((int) $coin->block_height) ?>
            </td>
            <td class="text-muted small" data="<?= (int) $coin->created ?>"><?= $created ?></td>
            <td class="small <?= $hasError ? 'text-danger fw-semibold' : 'text-muted' ?>">
                <?= Html::encode(substr((string) $coin->errors, 0, 40)) ?>
            </td>
            <td class="small">
                <?php if (!empty($coin->link_bitcointalk)): ?>
                    <?= Html::a('forum', $coin->link_bitcointalk, ['target' => '_blank', 'class' => 'me-1']) ?>
                <?php endif ?>
                <?php if (!empty($coin->link_github)): ?>
                    <?= Html::a('git', $coin->link_github, ['target' => '_blank', 'class' => 'me-1']) ?>
                <?php endif ?>
                <?= Html::a('google',
                    'https://google.com/search?q=' . urlencode($coin->name . ' ' . $coin->symbol . ' bitcointalk'),
                    ['target' => '_blank', 'class' => 'me-1 text-muted']) ?>
                <?php foreach ($markets as $m): ?>
                    <?= $marketBadgeFn($m) ?>
                <?php endforeach ?>
            </td>
        </tr>
        <?php endforeach ?>
        </tbody>
        </table>
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
