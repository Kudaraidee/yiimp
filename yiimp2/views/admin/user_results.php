<?php

/** @var yii\web\View           $this          */
/** @var string                 $symbol        */
/** @var app\models\Accounts[]  $users         */
/** @var app\models\Coins|null  $coin          */
/** @var app\models\Coins[]     $coins         */
/** @var array<int,float>       $rateMap       */
/** @var array<int,float>       $badRateMap    */
/** @var array<int,int>         $minerCountMap */
/** @var array<int,array>       $blockDataMap  */
/** @var array<int,float>       $paidMap       */

use yii\helpers\Html;

$conv       = Yii::$app->ConversionUtils;
$isTailwind = Yii::$app->LayoutManager->isTailwind();
$isLegacy   = Yii::$app->LayoutManager->isLegacy();

// ── Shared row computation ────────────────────────────────────────────────────
$totalBalance = 0.0;
$totalPaid    = 0.0;
$rows = [];

foreach ($users as $user) {
    $uid        = $user->id;
    $userRate   = $rateMap[$uid]       ?? 0.0;
    $userBad    = $badRateMap[$uid]    ?? 0.0;
    $minerCount = $minerCountMap[$uid] ?? 0;
    $blockData  = $blockDataMap[$uid]  ?? ['cnt' => 0, 'diff_sum' => 0.0];
    $paid       = $paidMap[$uid]       ?? 0.0;
    $blockCount = $blockData['cnt'];
    $blockDiff  = ($paid > 0 && $blockCount > 0)
                  ? round($blockData['diff_sum'] / $paid, 3) : '?';
    $pctBad     = $userRate ? round($userBad * 100 / $userRate, 3) : 0;

    $totalBalance += (float) $user->balance;
    $totalPaid    += $paid;
    $rows[] = compact('user', 'uid', 'userRate', 'pctBad', 'minerCount',
                      'blockCount', 'blockDiff', 'paid');
}

$balanceFmt = $conv->bitcoinvaluetoa($totalBalance);
$paidFmt    = $conv->bitcoinvaluetoa($totalPaid);

// ── Action link builders ──────────────────────────────────────────────────────
if ($isTailwind) {
    $actionLink = fn(string $label, array $url, string $color = 'gray', array $extra = []) =>
        Html::a($label, $url, array_merge([
            'class' => "text-xs text-{$color}-500 dark:text-{$color}-400 hover:underline transition-colors",
        ], $extra));
} else {
    $actionLink = fn(string $label, array $url, string $color = 'secondary', array $extra = []) =>
        Html::a($label, $url, array_merge([
            'class' => "btn btn-sm btn-outline-{$color} py-0",
        ], $extra));
}

?>

<?php if ($isLegacy): ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     LEGACY
     ══════════════════════════════════════════════════════════════════════════ -->
<div align="right" style="margin-top:-20px; margin-bottom:6px;">
    <input class="search" type="search" data-column="all" style="width:140px;" placeholder="Search…">
</div>
<style>
.red { color: darkred; }
tr.ssrow.filtered { display: none; }
.actions a { margin-right: 4px; }
</style>

<?php Yii::$app->ViewUtils->showTableSorter('maintable', "{
    tableClass: 'dataGrid',
    textExtraction: {
        4: function(node, table, cellIndex) { return \$(node).attr('data'); },
        6: function(node, table, cellIndex) { return \$(node).attr('data'); }
    },
    widgets: ['zebra','filter','Storage','saveSort'],
    widgetOptions: {
        saveSort: true,
        filter_saveFilters: false,
        filter_external: '.search',
        filter_columnFilters: false,
        filter_childRows: true,
        filter_ignoreCase: true
    }
}"); ?>
<thead>
<tr>
    <th data-sorter="numeric">UID</th>
    <th data-sorter="false">&nbsp;</th>
    <th data-sorter="text">Coin</th>
    <th data-sorter="text">Address</th>
    <th data-sorter="numeric">Last</th>
    <th data-sorter="numeric" align="right">Workers</th>
    <th data-sorter="numeric" align="right">Hashrate</th>
    <th data-sorter="numeric" align="right">Bad&nbsp;%</th>
    <th data-sorter="numeric" align="right">Blocks</th>
    <th data-sorter="numeric" align="right">Diff/Paid</th>
    <th data-sorter="currency" align="right">Balance</th>
    <th data-sorter="currency" align="right">Total Paid</th>
    <th data-sorter="false" class="actions" align="right" width="150">Actions</th>
</tr>
</thead>
<tbody>
<?php foreach ($rows as $r):
    $user     = $r['user'];
    $uid      = $r['uid'];
    $userCoin = $coins[$user->coinid] ?? null;
    $coinImg  = $userCoin ? Html::img(Html::encode($userCoin->image), ['width' => 16, 'alt' => Html::encode($userCoin->symbol)]) : '';
    $coinLink = $userCoin ? Html::a(Html::encode($userCoin->symbol), ['/admin/coinwallet', 'id' => $userCoin->id]) : '';
?>
<tr class="ssrow">
    <td width="24"><?= (int) $uid ?></td>
    <td width="16"><?= $coinImg ?></td>
    <td width="48"><b><?= $coinLink ?></b></td>
    <td><?= Html::a('<b>' . Html::encode($user->username) . '</b>', ['/?address=' . urlencode($user->username)], ['encode' => false]) ?></td>
    <td data="<?= (int) $user->last_earning ?>"><?= $conv->datetoa2($user->last_earning) ?></td>
    <td align="right"><?= $r['minerCount'] ?></td>
    <td width="32" data="<?= (int) $r['userRate'] ?>" align="right">
        <?= $r['userRate'] ? Html::encode($conv->Itoa2($r['userRate'])) : '' ?>
    </td>
    <td width="32" align="right">
        <?= $r['pctBad'] ? round($r['pctBad'], 1) . '&nbsp;%' : '' ?>
    </td>
    <td align="right"><?= $r['blockCount'] ?></td>
    <td align="right"><?= $r['userRate'] ? Html::encode((string) $r['blockDiff']) : '' ?></td>
    <td align="right"><?= Html::encode($conv->bitcoinvaluetoa($user->balance)) ?></td>
    <td align="right"><?= Html::encode($conv->bitcoinvaluetoa($r['paid'])) ?></td>
    <td class="actions" align="right">
        <?php if ($user->logtraffic): ?>
            <?= Html::a('unwatch', ['/admin/loguser', 'id' => $uid, 'en' => 0]) ?>
        <?php else: ?>
            <?= Html::a('watch',   ['/admin/loguser', 'id' => $uid, 'en' => 1]) ?>
        <?php endif ?>
        <?php if ($user->is_locked): ?>
            <?= Html::a('unblock', ['/admin/unblockuser', 'wallet' => $user->username]) ?>
        <?php else: ?>
            <?= Html::a('block',   ['/admin/blockuser',   'wallet' => $user->username]) ?>
        <?php endif ?>
        <?= Html::a('<span class="red">BAN</span>', ['/admin/banuser', 'id' => $uid], [
            'encode'  => false,
            'onclick' => 'return confirm(' . json_encode('Ban ' . $user->username . '?') . ')',
        ]) ?>
    </td>
</tr>
<?php endforeach ?>
</tbody>
<tfoot>
<tr class="ssfoot" style="border-top:2px solid #eee;">
    <th colspan="3"><b>Users Total (<?= count($users) ?>)</b></th>
    <?php for ($c = 0; $c < 7; $c++): ?><th></th><?php endfor ?>
    <th align="right"><b><?= Html::encode($balanceFmt) ?></b></th>
    <th align="right"><b><?= Html::encode($paidFmt) ?></b></th>
    <th></th>
</tr>
<?php if ($coin): ?>
<tr class="ssfoot" style="border-top:2px solid #eee;">
    <th colspan="3"><b>Wallet Balance</b></th>
    <?php for ($c = 0; $c < 7; $c++): ?><th></th><?php endfor ?>
    <th align="right"><b><?= Html::encode($conv->bitcoinvaluetoa($coin->balance)) ?></b></th>
    <th colspan="2"></th>
</tr>
<tr class="ssfoot" style="border-top:2px solid #eee;">
    <th colspan="3"><b>Wallet Profit</b></th>
    <?php for ($c = 0; $c < 7; $c++): ?><th></th><?php endfor ?>
    <th align="right"><b><?= Html::encode($conv->bitcoinvaluetoa($coin->balance - $totalBalance)) ?></b></th>
    <th colspan="2"></th>
</tr>
<?php endif ?>
</tfoot>
</table>


<?php elseif (!$isTailwind): ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     ADMINLTE
     ══════════════════════════════════════════════════════════════════════════ -->

<div class="card shadow-sm">
    <div class="card-header d-flex align-items-center gap-2 py-2 flex-wrap">
        <span class="badge bg-secondary"><?= count($users) ?> users</span>
        <?php if ($coin): ?>
            <span class="text-muted small ms-1">
                <i class="bi bi-wallet2 me-1"></i>Wallet:
                <strong><?= Html::encode($conv->bitcoinvaluetoa($coin->balance)) ?></strong>
                <?= Html::encode($coin->symbol) ?>
            </span>
        <?php endif ?>
        <input class="search form-control form-control-sm ms-auto" type="search"
               style="width:160px;" placeholder="Search…">
    </div>
    <div class="card-body p-0">
    <div class="overflow-auto">
    <table id="maintable" class="table table-hover table-sm table-bordered mb-0">
    <thead class="table-light">
    <tr>
        <th data-sorter="numeric" style="width:50px">UID</th>
        <th data-sorter="false"  style="width:24px"></th>
        <th data-sorter="text"   style="width:60px">Coin</th>
        <th data-sorter="text">Address</th>
        <th data-sorter="numeric">Last active</th>
        <th data-sorter="numeric" class="text-end">Workers</th>
        <th data-sorter="numeric" class="text-end">Hashrate</th>
        <th data-sorter="numeric" class="text-end">Bad %</th>
        <th data-sorter="numeric" class="text-end">Blocks</th>
        <th data-sorter="numeric" class="text-end">Diff/Paid</th>
        <th data-sorter="currency" class="text-end">Balance</th>
        <th data-sorter="currency" class="text-end">Total Paid</th>
        <th data-sorter="false"  class="text-end" style="width:160px">Actions</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $r):
        $user     = $r['user'];
        $uid      = $r['uid'];
        $userCoin = $coins[$user->coinid] ?? null;
    ?>
    <tr class="<?= $user->is_locked ? 'table-secondary' : '' ?>">
        <td class="text-muted small"><?= (int) $uid ?></td>
        <td>
            <?php if ($userCoin && !empty($userCoin->image)): ?>
                <img src="<?= Html::encode($userCoin->image) ?>" width="18" alt=""
                     style="object-fit:contain" onerror="this.style.display='none'">
            <?php endif ?>
        </td>
        <td>
            <?php if ($userCoin): ?>
                <?= Html::a('<strong>' . Html::encode($userCoin->symbol) . '</strong>',
                    ['/admin/coinwallet', 'id' => $userCoin->id], ['encode' => false]) ?>
            <?php endif ?>
        </td>
        <td>
            <?= Html::a('<strong>' . Html::encode($user->username) . '</strong>',
                '/?address=' . urlencode($user->username),
                ['encode' => false]) ?>
            <?php if ($user->is_locked): ?>
                <span class="badge bg-danger ms-1">locked</span>
            <?php endif ?>
        </td>
        <td class="small text-muted" data="<?= (int) $user->last_earning ?>">
            <?= $conv->datetoa2($user->last_earning) ?>
        </td>
        <td class="text-end small"><?= $r['minerCount'] ?: '' ?></td>
        <td class="text-end small font-monospace" data="<?= (int) $r['userRate'] ?>">
            <?= $r['userRate'] ? Html::encode($conv->Itoa2($r['userRate'])) : '' ?>
        </td>
        <td class="text-end small <?= $r['pctBad'] > 20 ? 'text-danger' : '' ?>">
            <?= $r['pctBad'] ? round($r['pctBad'], 1) . '%' : '' ?>
        </td>
        <td class="text-end small"><?= $r['blockCount'] ?: '' ?></td>
        <td class="text-end small font-monospace">
            <?= $r['userRate'] ? Html::encode((string) $r['blockDiff']) : '' ?>
        </td>
        <td class="text-end small font-monospace">
            <?= Html::encode($conv->bitcoinvaluetoa($user->balance)) ?>
        </td>
        <td class="text-end small font-monospace">
            <?= Html::encode($conv->bitcoinvaluetoa($r['paid'])) ?>
        </td>
        <td class="text-end">
            <div class="d-flex justify-content-end gap-1 flex-wrap">
                <?= $user->logtraffic
                    ? $actionLink('unwatch', ['/admin/loguser', 'id' => $uid, 'en' => 0])
                    : $actionLink('watch',   ['/admin/loguser', 'id' => $uid, 'en' => 1]) ?>
                <?= $user->is_locked
                    ? $actionLink('unblock', ['/admin/unblockuser', 'wallet' => $user->username])
                    : $actionLink('block',   ['/admin/blockuser',   'wallet' => $user->username], 'warning') ?>
                <?= Html::a('BAN', ['/admin/banuser', 'id' => $uid], [
                    'class'   => 'btn btn-sm btn-outline-danger py-0',
                    'onclick' => 'return confirm(' . json_encode('Ban ' . $user->username . '?') . ')',
                ]) ?>
            </div>
        </td>
    </tr>
    <?php endforeach ?>
    </tbody>
    <tfoot class="table-light">
    <tr>
        <th colspan="10" class="text-muted small">Users Total (<?= count($users) ?>)</th>
        <th class="text-end small"><strong><?= Html::encode($balanceFmt) ?></strong></th>
        <th class="text-end small"><strong><?= Html::encode($paidFmt) ?></strong></th>
        <th></th>
    </tr>
    <?php if ($coin): ?>
    <tr>
        <th colspan="10" class="text-muted small">Wallet Balance</th>
        <th class="text-end small"><strong><?= Html::encode($conv->bitcoinvaluetoa($coin->balance)) ?></strong></th>
        <th colspan="2"></th>
    </tr>
    <tr>
        <th colspan="10" class="text-muted small">Wallet Profit</th>
        <th class="text-end small"><strong><?= Html::encode($conv->bitcoinvaluetoa($coin->balance - $totalBalance)) ?></strong></th>
        <th colspan="2"></th>
    </tr>
    <?php endif ?>
    </tfoot>
    </table>
    </div>
    </div>
</div>

<?php
Yii::$app->ViewUtils->JavascriptReady("
    \$('#maintable').tablesorter({
        textExtraction: {
            4: function(node, table, n) { return \$(node).attr('data'); },
            6: function(node, table, n) { return \$(node).attr('data'); }
        },
        widgets: ['zebra','filter','Storage','saveSort'],
        widgetOptions: {
            saveSort: true, filter_saveFilters: false,
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

    <!-- header -->
    <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700
                flex items-center gap-3 flex-wrap">
        <span class="px-2 py-0.5 text-xs rounded-full bg-gray-100 dark:bg-gray-700
                     text-gray-600 dark:text-gray-300 font-medium">
            <?= count($users) ?> users
        </span>
        <?php if ($coin): ?>
            <span class="text-xs text-gray-400 dark:text-gray-500">
                Wallet:
                <strong class="text-gray-700 dark:text-gray-200 font-mono ml-1">
                    <?= Html::encode($conv->bitcoinvaluetoa($coin->balance)) ?>
                    <?= Html::encode($coin->symbol) ?>
                </strong>
            </span>
        <?php endif ?>
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
        <th class="px-3 py-2.5 text-left"  data-sorter="numeric">UID</th>
        <th class="px-2 py-2.5 w-8"        data-sorter="false"></th>
        <th class="px-2 py-2.5 w-16"       data-sorter="text">Coin</th>
        <th class="px-3 py-2.5 text-left"  data-sorter="text">Address</th>
        <th class="px-3 py-2.5 text-left"  data-sorter="numeric">Last active</th>
        <th class="px-3 py-2.5 text-right" data-sorter="numeric">Workers</th>
        <th class="px-3 py-2.5 text-right" data-sorter="numeric">Hashrate</th>
        <th class="px-3 py-2.5 text-right" data-sorter="numeric">Bad %</th>
        <th class="px-3 py-2.5 text-right" data-sorter="numeric">Blocks</th>
        <th class="px-3 py-2.5 text-right" data-sorter="numeric">Diff/Paid</th>
        <th class="px-3 py-2.5 text-right" data-sorter="currency">Balance</th>
        <th class="px-3 py-2.5 text-right" data-sorter="currency">Total Paid</th>
        <th class="px-3 py-2.5 text-right" data-sorter="false">Actions</th>
    </tr>
    </thead>
    <tbody class="divide-y divide-gray-100 dark:divide-gray-700/50">
    <?php foreach ($rows as $r):
        $user     = $r['user'];
        $uid      = $r['uid'];
        $userCoin = $coins[$user->coinid] ?? null;
        $locked   = (bool) $user->is_locked;
    ?>
    <tr class="<?= $locked
            ? 'opacity-60 bg-gray-50/50 dark:bg-gray-700/20'
            : 'hover:bg-gray-50/70 dark:hover:bg-gray-700/20' ?> transition-colors">

        <td class="px-3 py-2 text-gray-400 dark:text-gray-500 tabular-nums">
            <?= (int) $uid ?>
        </td>

        <td class="px-2 py-2">
            <?php if ($userCoin && !empty($userCoin->image)): ?>
                <img src="<?= Html::encode($userCoin->image) ?>" width="18" height="18"
                     class="rounded object-contain" onerror="this.style.display='none'" alt="">
            <?php endif ?>
        </td>

        <td class="px-2 py-2">
            <?php if ($userCoin): ?>
                <?= Html::a('<span class="font-medium text-indigo-600 dark:text-indigo-400">'
                    . Html::encode($userCoin->symbol) . '</span>',
                    ['/admin/coinwallet', 'id' => $userCoin->id],
                    ['encode' => false]) ?>
            <?php endif ?>
        </td>

        <td class="px-3 py-2">
            <div class="flex items-center gap-1.5 flex-wrap">
                <?= Html::a('<span class="font-medium text-gray-900 dark:text-gray-100">'
                    . Html::encode($user->username) . '</span>',
                    '/?address=' . urlencode($user->username),
                    ['encode' => false,
                     'class'  => 'hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors']) ?>
                <?php if ($locked): ?>
                    <span class="inline-flex items-center px-1.5 py-0.5 rounded-full
                                 bg-red-50 text-red-600 dark:bg-red-900/30 dark:text-red-400">
                        locked
                    </span>
                <?php endif ?>
            </div>
        </td>

        <td class="px-3 py-2 text-gray-400 dark:text-gray-500 whitespace-nowrap"
            data="<?= (int) $user->last_earning ?>">
            <?= $conv->datetoa2($user->last_earning) ?>
        </td>

        <td class="px-3 py-2 text-right text-gray-600 dark:text-gray-300 tabular-nums">
            <?= $r['minerCount'] ?: '' ?>
        </td>

        <td class="px-3 py-2 text-right font-mono tabular-nums text-gray-700 dark:text-gray-300"
            data="<?= (int) $r['userRate'] ?>">
            <?= $r['userRate'] ? Html::encode($conv->Itoa2($r['userRate'])) : '' ?>
        </td>

        <td class="px-3 py-2 text-right tabular-nums
                   <?= $r['pctBad'] > 20 ? 'text-red-500 dark:text-red-400 font-medium' : 'text-gray-500 dark:text-gray-400' ?>">
            <?= $r['pctBad'] ? round($r['pctBad'], 1) . '%' : '' ?>
        </td>

        <td class="px-3 py-2 text-right text-gray-600 dark:text-gray-300 tabular-nums">
            <?= $r['blockCount'] ?: '' ?>
        </td>

        <td class="px-3 py-2 text-right font-mono tabular-nums text-gray-500 dark:text-gray-400">
            <?= $r['userRate'] ? Html::encode((string) $r['blockDiff']) : '' ?>
        </td>

        <td class="px-3 py-2 text-right font-mono tabular-nums text-gray-700 dark:text-gray-300">
            <?= Html::encode($conv->bitcoinvaluetoa($user->balance)) ?>
        </td>

        <td class="px-3 py-2 text-right font-mono tabular-nums text-gray-700 dark:text-gray-300">
            <?= Html::encode($conv->bitcoinvaluetoa($r['paid'])) ?>
        </td>

        <td class="px-3 py-2 text-right">
            <div class="flex items-center justify-end gap-2">
                <?= $user->logtraffic
                    ? $actionLink('unwatch', ['/admin/loguser', 'id' => $uid, 'en' => 0])
                    : $actionLink('watch',   ['/admin/loguser', 'id' => $uid, 'en' => 1]) ?>
                <?= $user->is_locked
                    ? $actionLink('unblock', ['/admin/unblockuser', 'wallet' => $user->username], 'green')
                    : $actionLink('block',   ['/admin/blockuser',   'wallet' => $user->username], 'amber') ?>
                <?= Html::a('BAN', ['/admin/banuser', 'id' => $uid], [
                    'class'   => 'text-xs text-red-500 dark:text-red-400 hover:underline font-medium transition-colors',
                    'onclick' => 'return confirm(' . json_encode('Ban ' . $user->username . '?') . ')',
                ]) ?>
            </div>
        </td>

    </tr>
    <?php endforeach ?>
    </tbody>
    </table>
    </div>

    <!-- totals footer -->
    <div class="border-t border-gray-200 dark:border-gray-700
                divide-y divide-gray-100 dark:divide-gray-700/50">
        <div class="px-4 py-2 flex items-center justify-between text-xs">
            <span class="text-gray-500 dark:text-gray-400">
                Users total (<?= count($users) ?>)
            </span>
            <div class="flex items-center gap-6 font-mono tabular-nums">
                <span class="text-gray-500 dark:text-gray-400">Balance
                    <strong class="text-gray-800 dark:text-gray-100 ml-1"><?= Html::encode($balanceFmt) ?></strong>
                </span>
                <span class="text-gray-500 dark:text-gray-400">Paid
                    <strong class="text-gray-800 dark:text-gray-100 ml-1"><?= Html::encode($paidFmt) ?></strong>
                </span>
            </div>
        </div>
        <?php if ($coin): ?>
        <div class="px-4 py-2 flex items-center justify-between text-xs">
            <span class="text-gray-500 dark:text-gray-400">Wallet balance</span>
            <span class="font-mono tabular-nums text-gray-700 dark:text-gray-300">
                <?= Html::encode($conv->bitcoinvaluetoa($coin->balance)) ?>
            </span>
        </div>
        <div class="px-4 py-2 flex items-center justify-between text-xs">
            <span class="text-gray-500 dark:text-gray-400">Wallet profit</span>
            <span class="font-mono tabular-nums
                         <?= ($coin->balance - $totalBalance) >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-500 dark:text-red-400' ?>">
                <?= Html::encode($conv->bitcoinvaluetoa($coin->balance - $totalBalance)) ?>
            </span>
        </div>
        <?php endif ?>
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
            var rows = Array.from(tbody.rows);
            rows.sort(function (a, b) {
                var av = (a.cells[col].getAttribute('data') || a.cells[col].textContent || '').trim();
                var bv = (b.cells[col].getAttribute('data') || b.cells[col].textContent || '').trim();
                var n = parseFloat(av) - parseFloat(bv);
                return isNaN(n) ? (asc[col] ? av.localeCompare(bv) : bv.localeCompare(av)) : (asc[col] ? n : -n);
            });
            asc[col] = !asc[col];
            rows.forEach(function (r) { tbody.appendChild(r); });
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
