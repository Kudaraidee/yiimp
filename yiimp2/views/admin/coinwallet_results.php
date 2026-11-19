<?php

/** @var yii\web\View                        $this        */
/** @var app\models\Coins[]                  $coins       */
/** @var app\models\Mining|null              $mining      */
/** @var array{found:array,orphan:array}     $blockCounts */

use yii\helpers\Html;

$usdBtc     = $mining->usdbtc ?? 0;
$isTailwind = Yii::$app->LayoutManager->isTailwind();
$isLegacy   = Yii::$app->LayoutManager->isLegacy();
$conv       = Yii::$app->ConversionUtils;
$utils      = Yii::$app->YiimpUtils;

// ── Shared per-coin computation ───────────────────────────────────────────────
// Runs regardless of scheme; each branch renders from these variables.

$rows = [];
foreach ($coins as $coin) {
    $algoColor  = $utils->getAlgoColors($coin->algo);
    $version    = $conv->formatWalletVersion($coin);
    if (!empty($coin->symbol2)) $version .= " ({$coin->symbol2})";

    $difficulty = $conv->Itoa2($coin->difficulty, 3);
    if ($coin->difficulty > 1e20) $difficulty = '';

    $btcmhd = $conv->mbitcoinvaluetoa($utils->yiimp_profitability($coin));
    $ss1    = $blockCounts['found'][$coin->id]  ?? 0;
    $ss2    = $blockCounts['orphan'][$coin->id] ?? 0;

    $price  = $conv->bitcoinvaluetoa($coin->price);
    $price2 = $conv->bitcoinvaluetoa($coin->price2);

    $btcBalance   = $conv->bitcoinvaluetoa($coin->balance   * $coin->price);
    $btcAvailable = $conv->bitcoinvaluetoa($coin->available * $coin->price);

    $fiatBalance   = round($coin->balance   * $coin->price * $usdBtc, 2);
    $fiatAvailable = round($coin->available * $coin->price * $usdBtc, 2);

    $hasDeficit  = $coin->balance + $coin->mint < $coin->cleared;
    $hasDontsell = $coin->dontsell && YIIMP_ALLOW_EXCHANGE;
    $syncPct     = ($coin->block_height < $coin->target_height && $coin->target_height)
                   ? round($coin->block_height * 100 / $coin->target_height, 1) : null;

    $rows[] = compact(
        'coin', 'algoColor', 'version', 'difficulty',
        'btcmhd', 'ss1', 'ss2', 'price', 'price2',
        'btcBalance', 'btcAvailable', 'fiatBalance', 'fiatAvailable',
        'hasDeficit', 'hasDontsell', 'syncPct'
    );
}

// ── Legacy ────────────────────────────────────────────────────────────────────
if ($isLegacy):
?>
<style type="text/css">
tr.ssrow.filtered { display: none; }
th.status, td.status { min-width: 28px; max-width: 48px; text-align: center; }
td.status { font-family: monospace; font-size: 9pt; letter-spacing: 3px; }
td.status span.progress { font-size: .8em; letter-spacing: 0; }
td.status span.hidden { visibility: hidden; }
span.eov { opacity: 0.5; }
</style>

<?php Yii::$app->ViewUtils->showTableSorter('maintable', '{
tableClass: "dataGrid",
widgets: ["zebra","filter","Storage","saveSort"],
widgetOptions: {
	saveSort: true,
	filter_saveFilters: true,
	filter_external: ".search",
	filter_columnFilters: false,
	filter_childRows : true,
	filter_ignoreCase: true
}}'); ?>

<thead>
<tr>
<th data-sorter="" width="30"></th>
<th data-sorter="text" width="30" class="status"></th>
<th data-sorter="text">Name</th>
<th data-sorter="text">Server</th>
<th data-sorter="currency" align="right">Difficulty<br/>Height</th>
<th data-sorter="currency" align="right" title="mBTC profit. shown in mining status">Profit<br/>Pool Net</th>
<th data-sorter="currency" align="right">Bid Price<br/>Ask Price</th>
<th data-sorter="currency" align="right">Immature<br/>Cleared</th>
<th data-sorter="currency" align="right">Balance<br/>Available</th>
<th data-sorter="currency" align="right">BTC</th>
<th data-sorter="currency" align="right">USD</th>
<th data-sorter="currency" align="right">Win<br/>Market</th>
</tr>
</thead><tbody>

<?php foreach ($rows as $r):
    $coin      = $r['coin'];
    $algoColor = $r['algoColor'];

    $cellImmature = $conv->valuetocell($coin->mint)    . '<br/>' . $conv->valuetocell($coin->cleared);
    $cellBalance  = $conv->valuetocell($coin->balance) . '<br/>' . $conv->valuetocell($coin->available);
    $deficitClass = $r['hasDeficit']  ? ' class="red"' : '';
    $priceStyle   = $r['hasDontsell'] ? 'background-color: #ffaaaa' : '';

    $statusCell  = (!$coin->enable    ? '<span class="hidden" title="Coin disabled">X</span>'
                 : ($coin->auto_ready ? '<span class="green"  title="Auto enable">A</span>'
                                      : '<span class="red"    title="Stratum disabled">D</span>'))
                 . ($coin->visible ? '<span title="Visible to public">V</span>' : '<span title="Hidden">H</span>')
                 . ($coin->auxpow  ? '<span title="AUX PoW">X</span>' : '&nbsp;')
                 . '<br/>'
                 . ($coin->rpccurl ? '<span title="RPC with Curl">C</span>' : '&nbsp;')
                 . ($coin->rpcssl  ? '<span title="RPC over SSL">S</span>'  : '&nbsp;')
                 . ($coin->watch   ? '<span title="Watched (history)">W</span>' : '&nbsp;');
    if ($r['syncPct'] !== null)
        $statusCell .= '<br/><span class="progress">' . $r['syncPct'] . '%</span>';
?>
<tr class="ssrow">
    <td><?= Html::img(Html::encode($coin->image), ['width' => 24]) ?></td>
    <td class="status" style="background-color: <?= $algoColor ?>;"><?= $statusCell ?></td>
    <td>
        <b><?= Html::a(Html::encode("{$coin->name} ({$coin->symbol})"), ['/admin/coinwallet', 'id' => $coin->id]) ?></b>
        <br><span style="font-size:.8em"><?= Html::encode($r['version']) ?></span>
    </td>
    <td>
        <?= Html::encode("{$coin->rpchost}:{$coin->rpcport}") ?>
        <?= $coin->connections ? ' (' . (int) $coin->connections . ')' : '' ?>
        <br><span style="font-size:.8em">
            <?= Html::encode($coin->rpcencoding) ?>
            <span style="background-color:<?= $algoColor ?>;">&nbsp; <?= Html::encode($coin->algo) ?> &nbsp;</span>
        </span>
    </td>
    <?php if (!empty($coin->errors)): ?>
        <td align="right" style="font-size:.9em;" class="red" title="<?= Html::encode($coin->errors) ?>">
            <b><?= $r['difficulty'] ?></b><br/><?= (int) $coin->block_height ?>
        </td>
    <?php else: ?>
        <td align="right" style="font-size:.9em;">
            <b><?= $r['difficulty'] ?></b><br><?= (int) $coin->block_height ?>
        </td>
    <?php endif ?>
    <td align="right" style="font-size:.9em;" title="Pool % of last 100 net blocks">
        <b><?= $r['btcmhd'] ?></b><br/>
        <?= $r['ss1'] > 50 ? '<span class="blue">' . $r['ss1'] . '%</span>' : ($r['ss1'] ? $r['ss1'] . '%' : '') ?>
        <span class="red" title="orphans"> <?= $r['ss2'] ? $r['ss2'] . '%' : '' ?></span>
    </td>
    <td align="right" style="font-size:.9em;<?= $priceStyle ? " $priceStyle" : '' ?>">
        <?= $r['price'] ?><br><?= $r['price2'] ?>
    </td>
    <td align="right" style="font-size:.9em;"<?= $deficitClass ?>><?= $cellImmature ?></td>
    <td align="right" style="font-size:.9em;"><?= $cellBalance ?></td>
    <td align="right" style="font-size:.9em;"><?= $r['btcBalance'] ?><br/><?= $r['btcAvailable'] ?></td>
    <td align="right" style="font-size:.9em;"><?= $r['fiatBalance'] ?> $<br/><?= $r['fiatAvailable'] ?> $</td>
    <td align="right" style="font-size:.9em;"><?= Html::encode((string) $coin->reward) ?></td>
</tr>
<?php endforeach ?>
</tbody>
<tr><th colspan="12"><?= count($coins) ?> wallets</th></tr>
</table>
<br/>

<?php
// ── AdminLTE ──────────────────────────────────────────────────────────────────
elseif (!$isTailwind):
?>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="overflow-x-auto">
        <table id="maintable" class="table table-hover table-sm table-bordered mb-0">
        <thead class="table-light">
        <tr>
            <th data-sorter="" style="width:28px"></th>
            <th data-sorter="text" style="width:54px" class="text-center">Flags</th>
            <th data-sorter="text">Name</th>
            <th data-sorter="text">Server</th>
            <th data-sorter="currency" class="text-end">Diff<br/><small>Height</small></th>
            <th data-sorter="currency" class="text-end" title="mBTC/MH/d · pool %">Profit</th>
            <th data-sorter="currency" class="text-end">Bid<br/><small>Ask</small></th>
            <th data-sorter="currency" class="text-end">Immature<br/><small>Cleared</small></th>
            <th data-sorter="currency" class="text-end">Balance<br/><small>Available</small></th>
            <th data-sorter="currency" class="text-end">BTC</th>
            <th data-sorter="currency" class="text-end">USD</th>
            <th data-sorter="currency" class="text-end">Reward</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r):
            $coin = $r['coin'];

            // Status flags as compact Bootstrap badges
            if (!$coin->enable)         $enableBadge = '<span class="badge bg-danger"     title="Coin disabled">D</span>';
            elseif ($coin->auto_ready)  $enableBadge = '<span class="badge bg-success"    title="Auto enable">A</span>';
            else                        $enableBadge = '<span class="badge bg-warning text-dark" title="Stratum disabled">D</span>';

            $flagBits = $enableBadge
                . ($coin->visible ? '<span class="badge bg-secondary ms-1" title="Visible">V</span>'
                                  : '<span class="badge bg-light text-dark border ms-1" title="Hidden">H</span>')
                . ($coin->auxpow  ? '<span class="badge bg-warning text-dark ms-1" title="AUX PoW">X</span>' : '')
                . ($coin->rpccurl ? '<span class="badge bg-secondary ms-1" title="RPC Curl">C</span>' : '')
                . ($coin->rpcssl  ? '<span class="badge bg-secondary ms-1" title="RPC SSL">S</span>' : '')
                . ($coin->watch   ? '<span class="badge bg-info text-dark ms-1" title="Watched">W</span>' : '')
                . ($r['syncPct'] !== null
                    ? '<br/><small class="text-muted">' . $r['syncPct'] . '%</small>' : '');
        ?>
        <tr class="<?= !empty($coin->errors) ? 'table-danger' : '' ?>">

            <td>
                <?php if (!empty($coin->image)): ?>
                    <img src="<?= Html::encode($coin->image) ?>" width="22" height="22"
                         style="object-fit:contain" onerror="this.style.display='none'" alt="">
                <?php endif ?>
            </td>

            <td class="text-center" style="background-color:<?= $r['algoColor'] ?>22;">
                <?= $flagBits ?>
            </td>

            <td>
                <div>
                    <?= Html::a('<strong>' . Html::encode("{$coin->name} ({$coin->symbol})") . '</strong>',
                        ['/admin/coinwallet', 'id' => $coin->id], ['encode' => false]) ?>
                </div>
                <small class="text-muted font-monospace"><?= Html::encode($r['version']) ?></small>
            </td>

            <td>
                <div class="font-monospace small"><?= Html::encode("{$coin->rpchost}:{$coin->rpcport}") ?>
                    <?= $coin->connections ? ' <span class="text-muted">(' . (int) $coin->connections . ')</span>' : '' ?>
                </div>
                <div>
                    <span class="badge text-dark"
                          style="background-color:<?= $r['algoColor'] ?>;">
                        <?= Html::encode($coin->algo) ?>
                    </span>
                    <small class="text-muted"><?= Html::encode($coin->rpcencoding) ?></small>
                </div>
            </td>

            <td class="text-end small <?= !empty($coin->errors) ? 'text-danger' : '' ?>"
                title="<?= Html::encode($coin->errors ?? '') ?>">
                <strong><?= $r['difficulty'] ?></strong><br>
                <span class="text-muted"><?= number_format((int) $coin->block_height) ?></span>
            </td>

            <td class="text-end small" title="Pool % of last 100 net blocks">
                <strong><?= $r['btcmhd'] ?></strong><br>
                <?php if ($r['ss1']): ?>
                    <span class="<?= $r['ss1'] > 50 ? 'text-primary fw-bold' : 'text-muted' ?>"><?= $r['ss1'] ?>%</span>
                <?php endif ?>
                <?php if ($r['ss2']): ?>
                    <span class="text-danger ms-1" title="orphans"><?= $r['ss2'] ?>%</span>
                <?php endif ?>
            </td>

            <td class="text-end small <?= $r['hasDontsell'] ? 'table-warning' : '' ?>">
                <?= $r['price'] ?><br><span class="text-muted"><?= $r['price2'] ?></span>
            </td>

            <td class="text-end small <?= $r['hasDeficit'] ? 'text-danger' : '' ?>">
                <?= $conv->valuetocell($coin->mint) ?><br>
                <span class="text-muted"><?= $conv->valuetocell($coin->cleared) ?></span>
            </td>

            <td class="text-end small">
                <?= $conv->valuetocell($coin->balance) ?><br>
                <span class="text-muted"><?= $conv->valuetocell($coin->available) ?></span>
            </td>

            <td class="text-end small font-monospace">
                <?= $r['btcBalance'] ?><br>
                <span class="text-muted"><?= $r['btcAvailable'] ?></span>
            </td>

            <td class="text-end small font-monospace">
                <?= $r['fiatBalance'] ?> $<br>
                <span class="text-muted"><?= $r['fiatAvailable'] ?> $</span>
            </td>

            <td class="text-end small"><?= Html::encode((string) $coin->reward) ?></td>

        </tr>
        <?php endforeach ?>
        </tbody>
        <tfoot class="table-light">
            <tr><th colspan="12" class="text-muted small"><?= count($coins) ?> wallets</th></tr>
        </tfoot>
        </table>
        </div>
    </div>
</div>

<?php
Yii::$app->ViewUtils->JavascriptReady("
    \$('#maintable').tablesorter({
        widgets: ['zebra','filter','Storage','saveSort'],
        widgetOptions: {
            saveSort: true, filter_saveFilters: true,
            filter_external: '.search', filter_columnFilters: false,
            filter_childRows: true, filter_ignoreCase: true
        }
    });
    \$('.tablesorter-header').not('.sorter-false').css('cursor','pointer');
");
?>

<?php
// ── Tailwind ──────────────────────────────────────────────────────────────────
else:
?>

<div class="rounded-xl border border-gray-200 dark:border-gray-700
            bg-white dark:bg-gray-800 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
    <table id="maintable" class="w-full text-xs">
    <thead>
    <tr class="bg-gray-50 dark:bg-gray-700/50 text-gray-500 dark:text-gray-400
               font-semibold uppercase tracking-wider border-b border-gray-200 dark:border-gray-700">
        <th class="px-2 py-2.5 w-7" data-sorter="false"></th>
        <th class="px-2 py-2.5 w-14 text-center" data-sorter="text">Flags</th>
        <th class="px-3 py-2.5 text-left" data-sorter="text">Name</th>
        <th class="px-3 py-2.5 text-left" data-sorter="text">Server</th>
        <th class="px-3 py-2.5 text-right" data-sorter="currency">Diff / Height</th>
        <th class="px-3 py-2.5 text-right" data-sorter="currency">Profit</th>
        <th class="px-3 py-2.5 text-right" data-sorter="currency">Bid / Ask</th>
        <th class="px-3 py-2.5 text-right" data-sorter="currency">Immature / Cleared</th>
        <th class="px-3 py-2.5 text-right" data-sorter="currency">Balance / Avail</th>
        <th class="px-3 py-2.5 text-right" data-sorter="currency">BTC</th>
        <th class="px-3 py-2.5 text-right" data-sorter="currency">USD</th>
        <th class="px-3 py-2.5 text-right" data-sorter="currency">Reward</th>
    </tr>
    </thead>
    <tbody class="divide-y divide-gray-100 dark:divide-gray-700/50">
    <?php foreach ($rows as $r):
        $coin = $r['coin'];

        $enableDot = !$coin->enable
            ? '<span class="inline-block w-2 h-2 rounded-full bg-red-400" title="Disabled"></span>'
            : ($coin->auto_ready
                ? '<span class="inline-block w-2 h-2 rounded-full bg-green-400" title="Auto enable"></span>'
                : '<span class="inline-block w-2 h-2 rounded-full bg-amber-400" title="Stratum disabled"></span>');

        $flags = $enableDot
            . ($coin->visible
                ? '<span class="inline-block w-2 h-2 rounded-full bg-blue-400 ml-0.5" title="Visible"></span>'
                : '<span class="inline-block w-2 h-2 rounded-full bg-gray-300 dark:bg-gray-600 ml-0.5" title="Hidden"></span>')
            . ($coin->auxpow
                ? '<span class="inline-block w-2 h-2 rounded-full bg-amber-400 ml-0.5" title="AUX PoW"></span>' : '')
            . ($coin->rpccurl
                ? '<span class="inline-block w-2 h-2 rounded-full bg-orange-400 ml-0.5" title="RPC Curl"></span>' : '')
            . ($coin->rpcssl
                ? '<span class="inline-block w-2 h-2 rounded-full bg-teal-400 ml-0.5" title="RPC SSL"></span>' : '')
            . ($coin->watch
                ? '<span class="inline-block w-2 h-2 rounded-full bg-indigo-400 ml-0.5" title="Watched"></span>' : '')
            . ($r['syncPct'] !== null
                ? '<div class="text-gray-400 dark:text-gray-500 mt-0.5">' . $r['syncPct'] . '%</div>' : '');

        $rowClass = !empty($coin->errors)
            ? 'bg-red-50/60 dark:bg-red-900/10'
            : 'hover:bg-gray-50/70 dark:hover:bg-gray-700/20';
    ?>
    <tr class="<?= $rowClass ?> transition-colors">

        <td class="px-2 py-2">
            <?php if (!empty($coin->image)): ?>
                <img src="<?= Html::encode($coin->image) ?>" width="20" height="20"
                     class="rounded object-contain" onerror="this.style.display='none'" alt="">
            <?php endif ?>
        </td>

        <td class="px-2 py-2 text-center">
            <div class="flex flex-wrap items-center justify-center gap-0.5"><?= $flags ?></div>
        </td>

        <td class="px-3 py-2">
            <div class="font-medium text-gray-900 dark:text-gray-100">
                <?= Html::a(Html::encode("{$coin->name} ({$coin->symbol})"),
                    ['/admin/coinwallet', 'id' => $coin->id],
                    ['class' => 'hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors']) ?>
            </div>
            <div class="font-mono text-gray-400 dark:text-gray-500 text-xs">
                <?= Html::encode($r['version']) ?>
            </div>
        </td>

        <td class="px-3 py-2">
            <div class="font-mono text-gray-600 dark:text-gray-300">
                <?= Html::encode("{$coin->rpchost}:{$coin->rpcport}") ?>
                <?= $coin->connections ? '<span class="text-gray-400"> (' . (int) $coin->connections . ')</span>' : '' ?>
            </div>
            <div class="flex items-center gap-1 mt-0.5">
                <span class="px-1.5 py-0 rounded text-white text-xs font-medium"
                      style="background-color:<?= $r['algoColor'] ?>;">
                    <?= Html::encode($coin->algo) ?>
                </span>
                <span class="text-gray-400 dark:text-gray-500"><?= Html::encode($coin->rpcencoding) ?></span>
            </div>
        </td>

        <td class="px-3 py-2 text-right <?= !empty($coin->errors) ? 'text-red-500 dark:text-red-400' : 'text-gray-700 dark:text-gray-300' ?>"
            title="<?= Html::encode($coin->errors ?? '') ?>">
            <div class="font-semibold"><?= $r['difficulty'] ?></div>
            <div class="text-gray-400 dark:text-gray-500 font-mono tabular-nums">
                <?= number_format((int) $coin->block_height) ?>
            </div>
        </td>

        <td class="px-3 py-2 text-right text-gray-700 dark:text-gray-300"
            title="Pool % of last 100 net blocks">
            <div class="font-semibold"><?= $r['btcmhd'] ?></div>
            <div class="flex items-center justify-end gap-1 mt-0.5">
                <?php if ($r['ss1']): ?>
                    <span class="<?= $r['ss1'] > 50 ? 'text-blue-600 dark:text-blue-400 font-bold' : 'text-gray-400' ?>">
                        <?= $r['ss1'] ?>%
                    </span>
                <?php endif ?>
                <?php if ($r['ss2']): ?>
                    <span class="text-red-400 dark:text-red-500" title="orphans"><?= $r['ss2'] ?>%</span>
                <?php endif ?>
            </div>
        </td>

        <td class="px-3 py-2 text-right <?= $r['hasDontsell'] ? 'bg-red-50 dark:bg-red-900/20' : '' ?>">
            <div class="text-gray-700 dark:text-gray-300 font-mono"><?= $r['price'] ?></div>
            <div class="text-gray-400 dark:text-gray-500 font-mono"><?= $r['price2'] ?></div>
        </td>

        <td class="px-3 py-2 text-right <?= $r['hasDeficit'] ? 'text-red-500 dark:text-red-400' : 'text-gray-700 dark:text-gray-300' ?>">
            <div class="font-mono"><?= $conv->valuetocell($coin->mint) ?></div>
            <div class="font-mono text-gray-400 dark:text-gray-500"><?= $conv->valuetocell($coin->cleared) ?></div>
        </td>

        <td class="px-3 py-2 text-right text-gray-700 dark:text-gray-300">
            <div class="font-mono"><?= $conv->valuetocell($coin->balance) ?></div>
            <div class="font-mono text-gray-400 dark:text-gray-500"><?= $conv->valuetocell($coin->available) ?></div>
        </td>

        <td class="px-3 py-2 text-right font-mono tabular-nums text-gray-700 dark:text-gray-300">
            <div><?= $r['btcBalance'] ?></div>
            <div class="text-gray-400 dark:text-gray-500"><?= $r['btcAvailable'] ?></div>
        </td>

        <td class="px-3 py-2 text-right font-mono tabular-nums text-gray-700 dark:text-gray-300">
            <div><?= $r['fiatBalance'] ?> $</div>
            <div class="text-gray-400 dark:text-gray-500"><?= $r['fiatAvailable'] ?> $</div>
        </td>

        <td class="px-3 py-2 text-right text-gray-600 dark:text-gray-400">
            <?= Html::encode((string) $coin->reward) ?>
        </td>

    </tr>
    <?php endforeach ?>
    </tbody>
    </table>
    </div>
    <div class="px-4 py-2 border-t border-gray-200 dark:border-gray-700
                text-xs text-gray-400 dark:text-gray-500">
        <?= count($coins) ?> wallets
    </div>
</div>

<?php
$this->registerJs("
document.addEventListener('DOMContentLoaded', function () {
    var table = document.getElementById('maintable');
    if (!table) return;
    var tbody = table.tBodies[0];
    var ths   = Array.from(table.tHead.rows[0].cells);
    var asc   = ths.map(function () { return true; });
    ths.forEach(function (th, col) {
        if (th.dataset.sorter === 'false') return;
        th.style.cursor = 'pointer';
        th.addEventListener('click', function () {
            var rows = Array.from(tbody.rows);
            rows.sort(function (a, b) {
                var av = (a.cells[col].getAttribute('data') || a.cells[col].textContent || '').trim();
                var bv = (b.cells[col].getAttribute('data') || b.cells[col].textContent || '').trim();
                var n  = parseFloat(av) - parseFloat(bv);
                return isNaN(n) ? (asc[col] ? av.localeCompare(bv) : bv.localeCompare(av)) : (asc[col] ? n : -n);
            });
            asc[col] = !asc[col];
            rows.forEach(function (r) { tbody.appendChild(r); });
        });
    });
});
");
?>

<?php endif ?>
