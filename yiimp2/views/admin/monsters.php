<?php

/** @var yii\web\View              $this          */
/** @var array{0:int,1:string}[]   $rows          */
/** @var app\models\Accounts[]     $accounts      */
/** @var app\models\Coins[]        $coins         */
/** @var array<int,float>          $paidMap       */
/** @var array<int,int>            $workerCountMap */
/** @var array<int,int>            $shareCountMap */
/** @var array<int,int>            $blockCountMap */

use yii\helpers\Html;

$this->title = 'Big Miners';
$isTailwind  = Yii::$app->LayoutManager->isTailwind();
$isLegacy    = Yii::$app->LayoutManager->isLegacy();
$conv        = Yii::$app->ConversionUtils;

echo Yii::$app->ViewUtils->getAdminSideBarLinks();

// ── Shared row resolution ─────────────────────────────────────────────────────
$resolved = [];
foreach ($rows as [$userId, $what]) {
    $user = $accounts[$userId] ?? null;
    if (!$user) continue;
    $coin       = $coins[$user->coinid]      ?? null;
    $paidRaw    = $paidMap[$userId]          ?? 0.0;
    $blockCount = $blockCountMap[$userId]    ?? 0;
    $minerCount = $workerCountMap[$userId]   ?? 0;
    $shareCount = $shareCountMap[$userId]    ?? 0;
    $resolved[] = compact('user', 'coin', 'what', 'paidRaw', 'blockCount', 'minerCount', 'shareCount');
}

// ── Reason badge helper ───────────────────────────────────────────────────────
$reasonBadge = function(string $what) use ($isTailwind): string {
    if ($isTailwind) {
        $map = [
            'pid'     => 'bg-purple-50 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300',
            'blocks'  => 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
            'miners'  => 'bg-indigo-50 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300',
            'shares'  => 'bg-teal-50 text-teal-700 dark:bg-teal-900/30 dark:text-teal-300',
            'locked'  => 'bg-red-50 text-red-600 dark:bg-red-900/30 dark:text-red-400',
        ];
        $cls = $map[$what] ?? 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400';
        return '<span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium ' . $cls . '">'
             . Html::encode($what) . '</span>';
    }
    $map = [
        'pid'    => 'secondary',
        'blocks' => 'info',
        'miners' => 'primary',
        'shares' => 'success',
        'locked' => 'danger',
    ];
    $bs = $map[$what] ?? 'secondary';
    return '<span class="badge bg-' . $bs . ($bs === 'info' ? ' text-dark' : '') . '">'
         . Html::encode($what) . '</span>';
};

// ── Action link builder ───────────────────────────────────────────────────────
if ($isTailwind) {
    $actionLink = fn(string $label, array $url, string $color = 'gray') =>
        Html::a($label, $url, [
            'class' => "text-xs text-{$color}-500 dark:text-{$color}-400 hover:underline transition-colors",
        ]);
} else {
    $actionLink = fn(string $label, array $url, string $color = 'secondary') =>
        Html::a($label, $url, ['class' => "btn btn-sm btn-outline-{$color} py-0"]);
}

?>

<?php if ($isLegacy): ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     LEGACY
     ══════════════════════════════════════════════════════════════════════════ -->
<div align="right" style="margin-top:-14px; margin-bottom:6px;">
    <input class="search" type="search" data-column="all" style="width:140px;" placeholder="Search…">
</div>
<style>tr.ssrow.filtered { display: none; }</style>

<?php Yii::$app->ViewUtils->showTableSorter('maintable', "{
    tableClass: 'dataGrid',
    headers: { 1: { sorter: false } },
    widgets: ['zebra','filter'],
    widgetOptions: {
        filter_external: '.search', filter_columnFilters: false,
        filter_childRows: true, filter_ignoreCase: true
    }
}"); ?>
<thead>
<tr>
    <th>UID</th><th></th><th>Coin</th><th>Address</th><th></th>
    <th>Last</th><th>Blocks</th><th>Balance</th><th>Total Paid</th>
    <th>Miners</th><th>Shares</th><th></th><th></th>
</tr>
</thead>
<tbody>
<?php foreach ($resolved as $r):
    $user = $r['user']; $coin = $r['coin'];
    $balance = $conv->bitcoinvaluetoa($user->balance);
    $paid    = $conv->bitcoinvaluetoa($r['paidRaw']);
?>
<tr class="ssrow">
    <td width="24"><?= (int) $user->id ?></td>
    <?php if ($coin): ?>
        <td width="16"><img src="<?= Html::encode($coin->image) ?>" width="16" alt=""></td>
        <td width="48"><b><?= Html::a(Html::encode($coin->symbol), ['/admin/coinwallet', 'id' => $coin->id]) ?></b></td>
    <?php else: ?>
        <td width="60" colspan="2"></td>
    <?php endif ?>
    <td><?= Html::a('<b>' . Html::encode($user->username) . '</b>',
            '/?address=' . urlencode($user->username), ['encode' => false]) ?></td>
    <td><?= Html::encode($r['what']) ?></td>
    <td><?= $conv->datetoa2($user->last_earning) ?></td>
    <td><?= $r['blockCount'] ?></td>
    <td><?= Html::encode($balance) ?></td>
    <td><?= $r['paidRaw'] > 0.01 ? '<b>' . Html::encode($paid) . '</b>' : Html::encode($paid) ?></td>
    <td><?= $r['minerCount'] ?></td>
    <td><?= $r['shareCount'] ?></td>
    <?php if ($user->is_locked): ?>
        <td>locked</td>
        <td><?= Html::a('unblock', ['/admin/unblockuser', 'wallet' => $user->username]) ?></td>
    <?php else: ?>
        <td></td>
        <td><?= Html::a('block', ['/admin/blockuser', 'wallet' => $user->username]) ?></td>
    <?php endif ?>
</tr>
<?php endforeach ?>
</tbody>
</table>


<?php elseif (!$isTailwind): ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     ADMINLTE
     ══════════════════════════════════════════════════════════════════════════ -->

<div class="card shadow-sm">
    <div class="card-header d-flex align-items-center gap-2 py-2">
        <strong class="small">Big Miners</strong>
        <span class="badge bg-secondary ms-1"><?= count($resolved) ?></span>
        <small class="text-muted ms-2">Anomaly / high-activity users</small>
        <input class="search form-control form-control-sm ms-auto"
               type="search" style="width:160px;" placeholder="Search…">
    </div>
    <div class="card-body p-0">
    <table id="maintable" class="table table-hover table-sm table-bordered mb-0">
    <thead class="table-light">
    <tr>
        <th data-sorter="numeric"  style="width:50px">UID</th>
        <th data-sorter="false"    style="width:24px"></th>
        <th data-sorter="text"     style="width:60px">Coin</th>
        <th data-sorter="text">Address</th>
        <th data-sorter="text"     style="width:80px">Reason</th>
        <th data-sorter="numeric">Last active</th>
        <th data-sorter="numeric"  class="text-end">Blocks</th>
        <th data-sorter="currency" class="text-end">Balance</th>
        <th data-sorter="currency" class="text-end">Total Paid</th>
        <th data-sorter="numeric"  class="text-end">Miners</th>
        <th data-sorter="numeric"  class="text-end">Shares</th>
        <th data-sorter="false"    class="text-end" style="width:160px">Actions</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($resolved as $r):
        $user = $r['user']; $coin = $r['coin'];
        $balance = $conv->bitcoinvaluetoa($user->balance);
        $paid    = $conv->bitcoinvaluetoa($r['paidRaw']);
    ?>
    <tr class="<?= $user->is_locked ? 'table-secondary' : '' ?>">
        <td class="text-muted small tabular-nums"><?= (int) $user->id ?></td>
        <td><?php if ($coin && !empty($coin->image)): ?>
            <img src="<?= Html::encode($coin->image) ?>" width="18" alt=""
                 style="object-fit:contain" onerror="this.style.display='none'">
        <?php endif ?></td>
        <td><?php if ($coin): ?>
            <?= Html::a('<strong>' . Html::encode($coin->symbol) . '</strong>',
                ['/admin/coinwallet', 'id' => $coin->id], ['encode' => false]) ?>
        <?php endif ?></td>
        <td>
            <?= Html::a('<strong>' . Html::encode($user->username) . '</strong>',
                '/?address=' . urlencode($user->username), ['encode' => false]) ?>
            <?php if ($user->is_locked): ?>
                <span class="badge bg-danger ms-1">locked</span>
            <?php endif ?>
        </td>
        <td><?= $reasonBadge($r['what']) ?></td>
        <td class="small text-muted"><?= $conv->datetoa2($user->last_earning) ?></td>
        <td class="text-end small tabular-nums"><?= $r['blockCount'] ?: '' ?></td>
        <td class="text-end small font-monospace"><?= Html::encode($balance) ?></td>
        <td class="text-end small font-monospace <?= $r['paidRaw'] > 0.01 ? 'fw-bold' : '' ?>">
            <?= Html::encode($paid) ?>
        </td>
        <td class="text-end small tabular-nums"><?= $r['minerCount'] ?: '' ?></td>
        <td class="text-end small tabular-nums"><?= $r['shareCount'] ?: '' ?></td>
        <td class="text-end">
            <div class="d-flex justify-content-end gap-1">
                <?= $user->is_locked
                    ? $actionLink('unblock', ['/admin/unblockuser', 'wallet' => $user->username])
                    : $actionLink('block',   ['/admin/blockuser',   'wallet' => $user->username], 'warning') ?>
            </div>
        </td>
    </tr>
    <?php endforeach ?>
    </tbody>
    <tfoot class="table-light">
        <tr><th colspan="12" class="small text-muted">
            <?= count($resolved) ?> user<?= count($resolved) !== 1 ? 's' : '' ?>
        </th></tr>
    </tfoot>
    </table>
    </div>
</div>

<?php
Yii::$app->ViewUtils->JavascriptReady("
    \$('#maintable').tablesorter({
        headers: { 1: { sorter: false } },
        widgets: ['zebra','filter'],
        widgetOptions: {
            filter_external: '.search', filter_columnFilters: false,
            filter_childRows: true, filter_ignoreCase: true
        }
    });
    \$('.tablesorter-header').not('.sorter-false').css('cursor','pointer');
");
?>


<?php else: ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     TAILWIND
     ══════════════════════════════════════════════════════════════════════════ -->

<div class="rounded-xl border border-gray-200 dark:border-gray-700
            bg-white dark:bg-gray-800 shadow-sm overflow-hidden">

    <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700
                flex items-center gap-3 flex-wrap">
        <span class="text-sm font-semibold text-gray-800 dark:text-gray-100">
            Big Miners
        </span>
        <span class="px-2 py-0.5 text-xs rounded-full bg-gray-100 dark:bg-gray-700
                     text-gray-600 dark:text-gray-300 font-medium">
            <?= count($resolved) ?>
        </span>
        <span class="text-xs text-gray-400 dark:text-gray-500">anomaly / high-activity users</span>
        <input class="search ml-auto px-3 py-1.5 text-sm rounded-lg border
                      border-gray-300 dark:border-gray-600
                      bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100
                      focus:outline-none focus:ring-2 focus:ring-indigo-500
                      placeholder-gray-400 dark:placeholder-gray-500"
               type="search" style="width:160px;" placeholder="Search…">
    </div>

    <div class="overflow-x-auto">
    <table id="maintable" class="w-full text-xs">
    <thead>
    <tr class="bg-gray-50 dark:bg-gray-700/50 text-gray-500 dark:text-gray-400
               font-semibold uppercase tracking-wider
               border-b border-gray-200 dark:border-gray-700">
        <th class="px-3 py-2.5 text-left"  data-sorter="numeric"  style="width:50px">UID</th>
        <th class="px-3 py-2.5 w-8"        data-sorter="false"></th>
        <th class="px-3 py-2.5 text-left"  data-sorter="text"     style="width:60px">Coin</th>
        <th class="px-3 py-2.5 text-left"  data-sorter="text">Address</th>
        <th class="px-3 py-2.5 text-left"  data-sorter="text"     style="width:80px">Reason</th>
        <th class="px-3 py-2.5 text-left"  data-sorter="numeric">Last active</th>
        <th class="px-3 py-2.5 text-right" data-sorter="numeric">Blocks</th>
        <th class="px-3 py-2.5 text-right" data-sorter="currency">Balance</th>
        <th class="px-3 py-2.5 text-right" data-sorter="currency">Total Paid</th>
        <th class="px-3 py-2.5 text-right" data-sorter="numeric">Miners</th>
        <th class="px-3 py-2.5 text-right" data-sorter="numeric">Shares</th>
        <th class="px-3 py-2.5 text-right" data-sorter="false">Actions</th>
    </tr>
    </thead>
    <tbody class="divide-y divide-gray-100 dark:divide-gray-700/50">
    <?php foreach ($resolved as $r):
        $user   = $r['user'];
        $coin   = $r['coin'];
        $locked = (bool) $user->is_locked;
        $balance = $conv->bitcoinvaluetoa($user->balance);
        $paid    = $conv->bitcoinvaluetoa($r['paidRaw']);
    ?>
    <tr class="<?= $locked
            ? 'opacity-60 bg-gray-50/50 dark:bg-gray-700/20'
            : 'hover:bg-gray-50/70 dark:hover:bg-gray-700/20' ?> transition-colors">

        <td class="px-3 py-2.5 text-gray-400 dark:text-gray-500 tabular-nums">
            <?= (int) $user->id ?>
        </td>

        <td class="px-3 py-2.5">
            <?php if ($coin && !empty($coin->image)): ?>
                <img src="<?= Html::encode($coin->image) ?>" width="18" height="18"
                     class="rounded object-contain" onerror="this.style.display='none'" alt="">
            <?php endif ?>
        </td>

        <td class="px-3 py-2.5">
            <?php if ($coin): ?>
                <?= Html::a('<span class="font-medium text-indigo-600 dark:text-indigo-400">'
                    . Html::encode($coin->symbol) . '</span>',
                    ['/admin/coinwallet', 'id' => $coin->id], ['encode' => false]) ?>
            <?php endif ?>
        </td>

        <td class="px-3 py-2.5">
            <div class="flex items-center gap-1.5 flex-wrap">
                <?= Html::a('<span class="font-medium text-gray-900 dark:text-gray-100">'
                    . Html::encode($user->username) . '</span>',
                    '/?address=' . urlencode($user->username),
                    ['encode' => false,
                     'class'  => 'hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors']) ?>
                <?php if ($locked): ?>
                    <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs
                                 bg-red-50 text-red-600 dark:bg-red-900/30 dark:text-red-400">
                        locked
                    </span>
                <?php endif ?>
            </div>
        </td>

        <td class="px-3 py-2.5"><?= $reasonBadge($r['what']) ?></td>

        <td class="px-3 py-2.5 text-gray-400 dark:text-gray-500 whitespace-nowrap">
            <?= $conv->datetoa2($user->last_earning) ?>
        </td>

        <td class="px-3 py-2.5 text-right tabular-nums text-gray-600 dark:text-gray-300">
            <?= $r['blockCount'] ?: '' ?>
        </td>

        <td class="px-3 py-2.5 text-right font-mono tabular-nums text-gray-700 dark:text-gray-300">
            <?= Html::encode($balance) ?>
        </td>

        <td class="px-3 py-2.5 text-right font-mono tabular-nums
                   <?= $r['paidRaw'] > 0.01
                       ? 'font-semibold text-gray-900 dark:text-gray-100'
                       : 'text-gray-500 dark:text-gray-400' ?>">
            <?= Html::encode($paid) ?>
        </td>

        <td class="px-3 py-2.5 text-right tabular-nums text-gray-600 dark:text-gray-300">
            <?= $r['minerCount'] ?: '' ?>
        </td>

        <td class="px-3 py-2.5 text-right tabular-nums text-gray-600 dark:text-gray-300">
            <?= $r['shareCount'] ?: '' ?>
        </td>

        <td class="px-3 py-2.5 text-right">
            <?= $locked
                ? Html::a('unblock', ['/admin/unblockuser', 'wallet' => $user->username],
                    ['class' => 'text-xs text-green-500 dark:text-green-400 hover:underline transition-colors'])
                : Html::a('block',   ['/admin/blockuser',   'wallet' => $user->username],
                    ['class' => 'text-xs text-amber-500 dark:text-amber-400 hover:underline transition-colors']) ?>
        </td>

    </tr>
    <?php endforeach ?>
    </tbody>
    </table>
    </div>

    <div class="px-4 py-2 border-t border-gray-200 dark:border-gray-700
                text-xs text-gray-400 dark:text-gray-500">
        <?= count($resolved) ?> user<?= count($resolved) !== 1 ? 's' : '' ?>
    </div>
</div>

<?php
$this->registerJs("
document.addEventListener('DOMContentLoaded', function () {
    var table = document.getElementById('maintable');
    if (!table) return;
    var tbody = table.tBodies[0], ths = Array.from(table.tHead.rows[0].cells);
    var asc = ths.map(function () { return true; });
    ths.forEach(function (th, col) {
        if (th.dataset.sorter === 'false') return;
        th.style.cursor = 'pointer';
        th.addEventListener('click', function () {
            var rs = Array.from(tbody.rows);
            rs.sort(function (a, b) {
                var av = (a.cells[col].getAttribute('data') || a.cells[col].textContent || '').trim();
                var bv = (b.cells[col].getAttribute('data') || b.cells[col].textContent || '').trim();
                var n  = parseFloat(av) - parseFloat(bv);
                return isNaN(n) ? (asc[col] ? av.localeCompare(bv) : bv.localeCompare(av)) : (asc[col] ? n : -n);
            });
            asc[col] = !asc[col];
            rs.forEach(function (r) { tbody.appendChild(r); });
        });
    });
    var search = document.querySelector('input.search');
    if (search) {
        search.addEventListener('input', function () {
            var q = this.value.toLowerCase();
            Array.from(tbody.rows).forEach(function (r) {
                r.classList.toggle('hidden', q !== '' && r.textContent.toLowerCase().indexOf(q) === -1);
            });
        });
    }
});
");
?>

<?php endif ?>
