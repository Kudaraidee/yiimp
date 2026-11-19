<?php

/** @var yii\web\View              $this     */
/** @var array[]                   $rows     */
/** @var app\models\Accounts[]     $accounts */
/** @var app\models\Coins[]        $coins    */

use yii\helpers\Html;

$this->title = 'Botnets';
$isTailwind  = Yii::$app->LayoutManager->isTailwind();
$isLegacy    = Yii::$app->LayoutManager->isLegacy();
$conv        = Yii::$app->ConversionUtils;

echo Yii::$app->ViewUtils->getAdminSideBarLinks();
if ($isLegacy) echo '<br/><br/>';

// ── Shared row resolution ─────────────────────────────────────────────────────
$resolved = [];
foreach ($rows as $botnet) {
    if (!$botnet['userid']) continue;
    $user = $accounts[$botnet['userid']] ?? null; if (!$user) continue;
    $coin = $coins[$user->coinid]        ?? null; if (!$coin) continue;
    $resolved[] = compact('botnet', 'user', 'coin');
}

// ── Action link builder ───────────────────────────────────────────────────────
if ($isTailwind) {
    $actionLink = fn(string $label, array $url, string $color = 'gray') =>
        Html::a($label, $url, [
            'class' => "text-xs text-{$color}-500 dark:text-{$color}-400 hover:underline transition-colors",
        ]);
} else {
    $actionLink = fn(string $label, array $url, string $color = 'secondary') =>
        Html::a($label, $url, [
            'class' => "btn btn-sm btn-outline-{$color} py-0",
        ]);
}

?>

<?php if ($isLegacy): ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     LEGACY
     ══════════════════════════════════════════════════════════════════════════ -->
<style>
.red { color: darkred; }
table.dataGrid { max-width: 99.5%; }
table.dataGrid a.red { color: darkred; }
.actions form { display: inline; margin-right: 3px; }
</style>

<?php Yii::$app->ViewUtils->showTableSorter('maintable', "{
    tableClass: 'dataGrid',
    textExtraction: { 4: function(node, table, n) { return \$(node).attr('data'); } },
    widgets: ['zebra','Storage','saveSort'],
    widgetOptions: { saveSort: true }
}"); ?>
<thead>
<tr>
    <th data-sorter="" width="20"></th>
    <th data-sorter="text">Coin</th>
    <th data-sorter="text">Algo</th>
    <th data-sorter="text">Address</th>
    <th data-sorter="numeric">Last seen</th>
    <th data-sorter="numeric">PID</th>
    <th data-sorter="numeric">IPs</th>
    <th data-sorter="numeric">Workers</th>
    <th data-sorter="text">Version</th>
    <th data-sorter="false" class="actions" align="right" width="180">Actions</th>
</tr>
</thead>
<tbody>
<?php foreach ($resolved as $r):
    $botnet = $r['botnet']; $user = $r['user']; $coin = $r['coin'];
    $d = $conv->datetoa2($botnet['time']);
?>
<tr class="ssrow">
    <td><?= Html::img(Html::encode($coin->image), ['width' => 16, 'alt' => Html::encode($coin->symbol)]) ?></td>
    <td><?= Html::a(Html::encode($coin->name), ['/admin/coinwallet', 'id' => $coin->id]) ?></td>
    <td><?= Html::encode($botnet['algo']) ?></td>
    <td><?= Html::a(Html::encode($user->username), ['/?address=' . urlencode($user->username)]) ?></td>
    <td data="<?= (int) $botnet['time'] ?>"><?= $d ?></td>
    <td><?= (int) $botnet['pid'] ?></td>
    <td><b><?= (int) $botnet['ips'] ?></b></td>
    <td><?= (int) $botnet['workers'] ?></td>
    <td><?= Html::encode(substr((string) $botnet['version'], 0, 30)) ?></td>
    <td class="actions" align="right">
        <?php if ($user->logtraffic): ?>
            <?= Html::a('unwatch', ['/admin/loguser', 'id' => $user->id, 'en' => 0]) ?>
        <?php else: ?>
            <?= Html::a('watch',   ['/admin/loguser', 'id' => $user->id, 'en' => 1]) ?>
        <?php endif ?>
        <?php if ($user->is_locked): ?>
            <?= Html::a('unblock', ['/admin/unblockuser', 'wallet' => $user->username]) ?>
        <?php else: ?>
            <?= Html::a('block',   ['/admin/blockuser',   'wallet' => $user->username]) ?>
        <?php endif ?>
        <?= Html::a('<span class="red">BAN</span>', ['/admin/banuser', 'id' => $user->id], [
            'onclick' => "return confirm('Ban ' + " . json_encode($user->username) . " + '?')",
        ]) ?>
    </td>
</tr>
<?php endforeach ?>
</tbody>
<tfoot>
<?php if (empty($resolved)): ?>
<tr><th colspan="10">No botnets detected (threshold: &gt; 10 distinct IPs per worker group)</th></tr>
<?php endif ?>
</tfoot>
</table><br>


<?php elseif (!$isTailwind): ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     ADMINLTE
     ══════════════════════════════════════════════════════════════════════════ -->

<?php if (empty($resolved)): ?>
<div class="alert alert-success d-flex align-items-center gap-2">
    <i class="bi bi-check-circle-fill"></i>
    No botnets detected — threshold: &gt; 10 distinct IPs per worker group.
</div>
<?php else: ?>

<div class="card shadow-sm">
    <div class="card-header d-flex align-items-center gap-2 py-2">
        <i class="bi bi-exclamation-triangle-fill text-warning"></i>
        <strong class="small">Suspected Botnets</strong>
        <span class="badge bg-warning text-dark ms-1"><?= count($resolved) ?></span>
        <small class="text-muted ms-2">&gt; 10 distinct IPs per worker group</small>
    </div>
    <div class="card-body p-0">
    <table id="maintable" class="table table-hover table-sm table-bordered mb-0">
    <thead class="table-light">
    <tr>
        <th data-sorter="false" style="width:24px"></th>
        <th data-sorter="text">Coin</th>
        <th data-sorter="text">Algo</th>
        <th data-sorter="text">Address</th>
        <th data-sorter="numeric">Last seen</th>
        <th data-sorter="numeric" class="text-end">PID</th>
        <th data-sorter="numeric" class="text-end">IPs</th>
        <th data-sorter="numeric" class="text-end">Workers</th>
        <th data-sorter="text">Version</th>
        <th data-sorter="false" class="text-end" style="width:180px">Actions</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($resolved as $r):
        $botnet = $r['botnet']; $user = $r['user']; $coin = $r['coin'];
        $d      = $conv->datetoa2($botnet['time']);
        $highIp = (int) $botnet['ips'] >= 50;
    ?>
    <tr class="<?= $highIp ? 'table-danger' : 'table-warning' ?>">
        <td><?php if (!empty($coin->image)): ?>
            <img src="<?= Html::encode($coin->image) ?>" width="18" alt=""
                 style="object-fit:contain" onerror="this.style.display='none'">
        <?php endif ?></td>
        <td><?= Html::a('<strong>' . Html::encode($coin->name) . '</strong>',
            ['/admin/coinwallet', 'id' => $coin->id], ['encode' => false]) ?></td>
        <td><span class="badge bg-light text-dark border font-monospace"><?= Html::encode($botnet['algo']) ?></span></td>
        <td><?= Html::a(Html::encode($user->username), '/?address=' . urlencode($user->username)) ?></td>
        <td class="small text-muted" data="<?= (int) $botnet['time'] ?>"><?= $d ?></td>
        <td class="text-end small tabular-nums"><?= (int) $botnet['pid'] ?></td>
        <td class="text-end <?= $highIp ? 'text-danger fw-bold' : 'text-warning fw-semibold' ?>">
            <?= (int) $botnet['ips'] ?>
        </td>
        <td class="text-end small tabular-nums"><?= (int) $botnet['workers'] ?></td>
        <td class="small font-monospace"><?= Html::encode(substr((string) $botnet['version'], 0, 30)) ?></td>
        <td class="text-end">
            <div class="d-flex justify-content-end gap-1">
                <?= $user->logtraffic
                    ? $actionLink('unwatch', ['/admin/loguser', 'id' => $user->id, 'en' => 0])
                    : $actionLink('watch',   ['/admin/loguser', 'id' => $user->id, 'en' => 1]) ?>
                <?= $user->is_locked
                    ? $actionLink('unblock', ['/admin/unblockuser', 'wallet' => $user->username])
                    : $actionLink('block',   ['/admin/blockuser',   'wallet' => $user->username], 'warning') ?>
                <?= Html::a('BAN', ['/admin/banuser', 'id' => $user->id], [
                    'class'   => 'btn btn-sm btn-outline-danger py-0',
                    'onclick' => "return confirm('Ban ' + " . json_encode($user->username) . " + '?')",
                ]) ?>
            </div>
        </td>
    </tr>
    <?php endforeach ?>
    </tbody>
    <tfoot class="table-light">
        <tr><th colspan="10" class="small text-muted">
            <?= count($resolved) ?> suspected botnet<?= count($resolved) !== 1 ? 's' : '' ?>
        </th></tr>
    </tfoot>
    </table>
    </div>
</div>

<?php endif ?>

<?php
Yii::$app->ViewUtils->JavascriptReady("
    \$('#maintable').tablesorter({
        textExtraction: { 4: function(node,table,n){ return \$(node).attr('data'); } },
        widgets: ['zebra','Storage','saveSort'],
        widgetOptions: { saveSort: true }
    });
    \$('.tablesorter-header').not('.sorter-false').css('cursor','pointer');
");
?>


<?php else: ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     TAILWIND
     ══════════════════════════════════════════════════════════════════════════ -->

<?php if (empty($resolved)): ?>
<div class="flex items-center gap-3 px-4 py-3 rounded-xl
            bg-green-50 dark:bg-green-900/20
            border border-green-200 dark:border-green-800
            text-green-700 dark:text-green-300 text-sm">
    <i data-lucide="check-circle" class="w-5 h-5 shrink-0"></i>
    No botnets detected — threshold: &gt; 10 distinct IPs per worker group.
</div>
<?php else: ?>

<div class="rounded-xl border border-amber-200 dark:border-amber-800
            bg-white dark:bg-gray-800 shadow-sm overflow-hidden">

    <div class="px-4 py-3 border-b border-amber-200 dark:border-amber-800
                flex items-center gap-2 bg-amber-50/50 dark:bg-amber-900/10">
        <i data-lucide="alert-triangle" class="w-4 h-4 text-amber-500 shrink-0"></i>
        <span class="text-sm font-semibold text-amber-800 dark:text-amber-300">
            Suspected Botnets
        </span>
        <span class="px-2 py-0.5 text-xs rounded-full bg-amber-100 dark:bg-amber-900/40
                     text-amber-700 dark:text-amber-300 font-medium">
            <?= count($resolved) ?>
        </span>
        <span class="text-xs text-amber-600 dark:text-amber-500 ml-1">
            &gt; 10 distinct IPs per worker group
        </span>
    </div>

    <div class="overflow-x-auto">
    <table id="maintable" class="w-full text-xs">
    <thead>
    <tr class="bg-gray-50 dark:bg-gray-700/50 text-gray-500 dark:text-gray-400
               font-semibold uppercase tracking-wider
               border-b border-gray-200 dark:border-gray-700">
        <th class="px-3 py-2.5 w-8"        data-sorter="false"></th>
        <th class="px-3 py-2.5 text-left"  data-sorter="text">Coin</th>
        <th class="px-3 py-2.5 text-left"  data-sorter="text">Algo</th>
        <th class="px-3 py-2.5 text-left"  data-sorter="text">Address</th>
        <th class="px-3 py-2.5 text-left"  data-sorter="numeric">Last seen</th>
        <th class="px-3 py-2.5 text-right" data-sorter="numeric">PID</th>
        <th class="px-3 py-2.5 text-right" data-sorter="numeric">IPs</th>
        <th class="px-3 py-2.5 text-right" data-sorter="numeric">Workers</th>
        <th class="px-3 py-2.5 text-left"  data-sorter="text">Version</th>
        <th class="px-3 py-2.5 text-right" data-sorter="false">Actions</th>
    </tr>
    </thead>
    <tbody class="divide-y divide-gray-100 dark:divide-gray-700/50">
    <?php foreach ($resolved as $r):
        $botnet = $r['botnet']; $user = $r['user']; $coin = $r['coin'];
        $d      = $conv->datetoa2($botnet['time']);
        $ips    = (int) $botnet['ips'];
        $highIp = $ips >= 50;
    ?>
    <tr class="<?= $highIp
            ? 'bg-red-50/40 dark:bg-red-900/10 hover:bg-red-50/60 dark:hover:bg-red-900/20'
            : 'bg-amber-50/20 dark:bg-amber-900/5 hover:bg-amber-50/40 dark:hover:bg-amber-900/10'
        ?> transition-colors">

        <td class="px-3 py-2.5">
            <?php if (!empty($coin->image)): ?>
                <img src="<?= Html::encode($coin->image) ?>" width="18" height="18"
                     class="rounded object-contain" onerror="this.style.display='none'" alt="">
            <?php endif ?>
        </td>

        <td class="px-3 py-2.5">
            <div class="font-medium text-gray-900 dark:text-gray-100">
                <?= Html::a(Html::encode($coin->name),
                    ['/admin/coinwallet', 'id' => $coin->id],
                    ['class' => 'hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors']) ?>
            </div>
        </td>

        <td class="px-3 py-2.5">
            <span class="inline-flex items-center px-1.5 py-0.5 rounded-full
                         bg-indigo-50 dark:bg-indigo-900/30
                         text-indigo-700 dark:text-indigo-300 font-mono">
                <?= Html::encode($botnet['algo']) ?>
            </span>
        </td>

        <td class="px-3 py-2.5">
            <?= Html::a(Html::encode($user->username),
                '/?address=' . urlencode($user->username),
                ['class' => 'hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors']) ?>
        </td>

        <td class="px-3 py-2.5 text-gray-400 dark:text-gray-500 whitespace-nowrap"
            data="<?= (int) $botnet['time'] ?>">
            <?= $d ?>
        </td>

        <td class="px-3 py-2.5 text-right tabular-nums text-gray-500 dark:text-gray-400">
            <?= (int) $botnet['pid'] ?>
        </td>

        <td class="px-3 py-2.5 text-right tabular-nums font-bold
                   <?= $highIp
                       ? 'text-red-600 dark:text-red-400'
                       : 'text-amber-600 dark:text-amber-400' ?>">
            <?= $ips ?>
        </td>

        <td class="px-3 py-2.5 text-right tabular-nums text-gray-600 dark:text-gray-300">
            <?= (int) $botnet['workers'] ?>
        </td>

        <td class="px-3 py-2.5 font-mono text-gray-500 dark:text-gray-400">
            <?= Html::encode(substr((string) $botnet['version'], 0, 30)) ?>
        </td>

        <td class="px-3 py-2.5 text-right">
            <div class="flex items-center justify-end gap-2">
                <?= $user->logtraffic
                    ? $actionLink('unwatch', ['/admin/loguser', 'id' => $user->id, 'en' => 0])
                    : $actionLink('watch',   ['/admin/loguser', 'id' => $user->id, 'en' => 1]) ?>
                <?= $user->is_locked
                    ? $actionLink('unblock', ['/admin/unblockuser', 'wallet' => $user->username], 'green')
                    : $actionLink('block',   ['/admin/blockuser',   'wallet' => $user->username], 'amber') ?>
                <?= Html::a('BAN', ['/admin/banuser', 'id' => $user->id], [
                    'class'   => 'text-xs text-red-500 dark:text-red-400 hover:underline font-medium transition-colors',
                    'onclick' => "return confirm('Ban ' + " . json_encode($user->username) . " + '?')",
                ]) ?>
            </div>
        </td>

    </tr>
    <?php endforeach ?>
    </tbody>
    </table>
    </div>

    <div class="px-4 py-2 border-t border-gray-200 dark:border-gray-700
                text-xs text-gray-400 dark:text-gray-500">
        <?= count($resolved) ?> suspected botnet<?= count($resolved) !== 1 ? 's' : '' ?>
    </div>
</div>

<?php endif ?>

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
});
");
?>

<?php endif ?>
