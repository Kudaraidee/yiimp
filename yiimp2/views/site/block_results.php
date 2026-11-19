<?php

/** @var yii\web\View        $this     */
/** @var app\models\Coins    $coin     */
/** @var app\models\Blocks[] $blocks   */
/** @var app\models\Accounts[] $accounts keyed by id */

use yii\helpers\Html;

if (!$coin) return;

$conv       = Yii::$app->ConversionUtils;
$isAdmin    = !Yii::$app->user->isGuest && (Yii::$app->user->identity->is_admin ?? false);
$isTailwind = Yii::$app->LayoutManager->isTailwind();
$isLegacy   = Yii::$app->LayoutManager->isLegacy();

// ── Shared per-row computation ────────────────────────────────────────────────
$rows = [];
foreach ($blocks as $block) {
    $diffUser = (float) $block->difficulty_user;
    if (!$diffUser && str_starts_with((string) $block->blockhash, '0000')) {
        $diffUser = (float) $conv->hash_to_difficulty($coin, $block->blockhash);
    }

    $finder = '';
    if (!empty($block->userid) && isset($accounts[$block->userid])) {
        $finder = substr($accounts[$block->userid]->username, 0, 7) . '…';
    }

    $maturityEta = '';
    if ($block->category === 'immature' && $coin->block_time && $coin->mature_blocks) {
        $t = (int) ($coin->mature_blocks - $block->confirmations) * $coin->block_time;
        $maturityEta = sprintf('%dh %02dm', (int) ($t / 3600), (int) ($t / 60) % 60);
    }

    $rows[] = [
        'block'       => $block,
        'diffBlock'   => $conv->round_difficulty($block->difficulty),
        'diffUser'    => $conv->round_difficulty($diffUser),
        'finder'      => $finder,
        'maturityEta' => $maturityEta,
        'segwit'      => (bool) $block->segwit,
        'solo'        => $block->solo == 1,
    ];
}

?>

<?php if ($isLegacy): ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     LEGACY
     ══════════════════════════════════════════════════════════════════════════ -->
<style>
span.block { padding:2px 5px; display:inline-block; text-align:center; min-width:15px; border-radius:3px; color:#fff; }
span.block.orphan   { background:#d9534f; }
span.block.immature { background:#f0ad4e; }
span.block.generate { background:#5cb85c; }
span.block.new      { background:#ad4ef0; }
span.block.stake    { background:#5bc0de; }
span.solo-badge     { padding:1px 5px; border-radius:3px; background:#4BB2C5; color:#fff; font-size:.8em; margin-right:4px; }
</style>

<?php Yii::$app->ViewUtils->showTableSorter('maintable', "{
    tableClass: 'dataGrid',
    widgets: ['zebra','filter'],
    widgetOptions: { filter_columnFilters:false, filter_ignoreCase:true }
}") ?>
<thead><tr>
    <th width="20"></th>
    <th>Name</th>
    <th data-sorter="numeric">Time</th>
    <th data-sorter="numeric">Height</th>
    <th data-sorter="currency">Amount</th>
    <th>Type</th>
    <th data-sorter="numeric">Effort</th>
    <th>Status</th>
    <th data-sorter="numeric">Difficulty</th>
    <th data-sorter="numeric">Share Diff</th>
    <th>Finder</th>
    <th data-sorter="false">Blockhash</th>
</tr></thead>
<tbody>
<?php foreach ($rows as $r):
    $block = $r['block'];
    $rowStyle = $block->category === 'immature' ? " style='background:#e0d3e8;'" : '';
?>
<tr class="ssrow"<?= $rowStyle ?>>
    <td><?php if ($coin->image): ?><img width="16" src="<?= Html::encode($coin->image) ?>"><?php endif ?></td>
    <td>
        <?php if ($isAdmin): ?>
            <?= Html::a('<b>' . Html::encode($coin->name) . '</b>', ['/admin/coinwallet', 'id' => $coin->id]) ?>
        <?php else: ?>
            <b><?= Html::encode($coin->name) ?></b>
        <?php endif ?>
        &nbsp;(<?= Html::encode($coin->symbol) ?>)
        <?php if ($r['segwit']): ?>&nbsp;<img src="/images/ui/segwit.png" height="8" title="SegWit"><?php endif ?>
    </td>
    <td data="<?= $block->time ?>"><b><?= $conv->datetoa2($block->time) ?> ago</b></td>
    <td><?= $coin->createExplorerLink($block->height, ['height' => $block->height]) ?></td>
    <td><?= Html::encode($block->amount) ?></td>
    <td><?= $r['solo'] ? '<span class="solo-badge" title="Solo miner">solo</span>' : '' ?></td>
    <td><?= $block->effort ? Html::encode($block->effort) . '%' : 'N/A' ?></td>
    <td>
        <?php if ($block->category === 'orphan'): ?>
            <span class="block orphan">Orphan</span>
        <?php elseif ($block->category === 'immature'): ?>
            <span class="block immature"<?= $r['maturityEta'] ? ' title="ETA: ' . Html::encode($r['maturityEta']) . '"' : '' ?>>
                Immature (<?= $block->confirmations ?>/<?= $coin->mature_blocks ?>)
            </span>
        <?php elseif ($block->category === 'generate'): ?>
            <span class="block generate">Confirmed</span>
        <?php elseif ($block->category === 'stake'): ?>
            <span class="block stake">Stake (<?= $block->confirmations ?>)</span>
        <?php elseif ($block->category === 'generated'): ?>
            <span class="block stake">Stake</span>
        <?php elseif ($block->category === 'new'): ?>
            <span class="block new">New</span>
        <?php endif ?>
    </td>
    <td><?= Html::encode($r['diffBlock']) ?></td>
    <td><?= Html::encode($r['diffUser']) ?></td>
    <td><?= Html::encode($r['finder']) ?></td>
    <td style="font-size:.8em;font-family:monospace;">
        <?= $coin->createExplorerLink($block->blockhash, ['hash' => $block->blockhash]) ?>
    </td>
</tr>
<?php endforeach ?>
</tbody></table>


<?php elseif (!$isTailwind): ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     ADMINLTE
     ══════════════════════════════════════════════════════════════════════════ -->
<div class="card shadow-sm">
<div class="card-body p-0">
<div class="table-responsive">
<table class="table table-sm table-bordered table-hover mb-0">
<thead class="table-light">
<tr>
    <th style="width:24px"></th>
    <th>Name</th>
    <th>Time</th>
    <th>Height</th>
    <th class="text-end">Amount</th>
    <th>Type</th>
    <th class="text-end">Effort</th>
    <th>Status</th>
    <th class="text-end">Difficulty</th>
    <th class="text-end">Share Diff</th>
    <th>Finder</th>
    <th>Blockhash</th>
</tr>
</thead>
<tbody>
<?php foreach ($rows as $r):
    $block  = $r['block'];
    $rowCls = match ($block->category) {
        'orphan'   => 'table-danger',
        'immature' => 'table-warning',
        default    => '',
    };
?>
<tr class="<?= $rowCls ?>">
    <td><?php if ($coin->image): ?><img width="18" src="<?= Html::encode($coin->image) ?>" style="object-fit:contain"><?php endif ?></td>
    <td class="small">
        <?php if ($isAdmin): ?>
            <?= Html::a('<strong>' . Html::encode($coin->name) . '</strong>', ['/admin/coinwallet', 'id' => $coin->id], ['encode' => false]) ?>
        <?php else: ?>
            <strong><?= Html::encode($coin->name) ?></strong>
        <?php endif ?>
        <span class="text-muted">(<?= Html::encode($coin->symbol) ?>)</span>
        <?php if ($r['segwit']): ?><span class="badge bg-info text-dark ms-1" style="font-size:.65em;">SW</span><?php endif ?>
    </td>
    <td class="small text-nowrap"><?= Html::encode($conv->datetoa2($block->time)) ?> ago</td>
    <td class="small"><?= $coin->createExplorerLink($block->height, ['height' => $block->height]) ?></td>
    <td class="small text-end font-monospace"><?= Html::encode($block->amount) ?></td>
    <td class="small"><?= $r['solo'] ? '<span class="badge bg-info text-dark">solo</span>' : '' ?></td>
    <td class="small text-end"><?= $block->effort ? Html::encode($block->effort) . '%' : '<span class="text-muted">N/A</span>' ?></td>
    <td class="small">
        <?php if ($block->category === 'orphan'): ?>
            <span class="badge bg-danger">Orphan</span>
        <?php elseif ($block->category === 'immature'): ?>
            <span class="badge bg-warning text-dark"
                  <?= $r['maturityEta'] ? 'title="ETA: ' . Html::encode($r['maturityEta']) . '"' : '' ?>>
                Immature <?= $block->confirmations ?>/<?= $coin->mature_blocks ?>
            </span>
        <?php elseif ($block->category === 'generate'): ?>
            <span class="badge bg-success">Confirmed</span>
        <?php elseif ($block->category === 'stake' || $block->category === 'generated'): ?>
            <span class="badge bg-secondary">Stake</span>
        <?php elseif ($block->category === 'new'): ?>
            <span class="badge bg-primary">New</span>
        <?php endif ?>
    </td>
    <td class="small text-end font-monospace"><?= Html::encode($r['diffBlock']) ?></td>
    <td class="small text-end font-monospace"><?= Html::encode($r['diffUser']) ?></td>
    <td class="small"><?= Html::encode($r['finder']) ?></td>
    <td class="small font-monospace" style="font-size:.78em;max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
        <?= $coin->createExplorerLink($block->blockhash, ['hash' => $block->blockhash]) ?>
    </td>
</tr>
<?php endforeach ?>
</tbody>
<tfoot class="table-light">
    <tr><th colspan="12" class="small text-muted"><?= count($rows) ?> blocks</th></tr>
</tfoot>
</table>
</div>
</div>
</div>


<?php else: ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     TAILWIND
     ══════════════════════════════════════════════════════════════════════════ -->
<div class="rounded-xl border border-gray-200 dark:border-gray-700
            bg-white dark:bg-gray-800 shadow-sm overflow-hidden">
<div class="overflow-x-auto">
<table class="w-full text-xs">
<thead>
<tr class="bg-gray-50 dark:bg-gray-700/50 text-gray-500 dark:text-gray-400
           font-semibold uppercase tracking-wider border-b border-gray-200 dark:border-gray-700">
    <th class="px-2 py-2.5 w-7"></th>
    <th class="px-3 py-2.5 text-left">Name</th>
    <th class="px-3 py-2.5 text-left">Time</th>
    <th class="px-3 py-2.5 text-right">Height</th>
    <th class="px-3 py-2.5 text-right">Amount</th>
    <th class="px-3 py-2.5 text-center">Type</th>
    <th class="px-3 py-2.5 text-right">Effort</th>
    <th class="px-3 py-2.5 text-left">Status</th>
    <th class="px-3 py-2.5 text-right">Difficulty</th>
    <th class="px-3 py-2.5 text-right">Share Diff</th>
    <th class="px-3 py-2.5 text-left">Finder</th>
    <th class="px-3 py-2.5 text-left">Blockhash</th>
</tr>
</thead>
<tbody class="divide-y divide-gray-100 dark:divide-gray-700/50">
<?php foreach ($rows as $r):
    $block  = $r['block'];
    $rowCls = match ($block->category) {
        'orphan'   => 'bg-red-50/50 dark:bg-red-900/10',
        'immature' => 'bg-amber-50/50 dark:bg-amber-900/10',
        default    => 'hover:bg-gray-50/50 dark:hover:bg-gray-700/20',
    };
    $statusBadge = match ($block->category) {
        'orphan'    => '<span class="px-1.5 py-0.5 rounded text-xs bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300">Orphan</span>',
        'immature'  => '<span class="px-1.5 py-0.5 rounded text-xs bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300"'
                       . ($r['maturityEta'] ? ' title="ETA: ' . Html::encode($r['maturityEta']) . '"' : '') . '>'
                       . 'Immature ' . $block->confirmations . '/' . $coin->mature_blocks . '</span>',
        'generate'  => '<span class="px-1.5 py-0.5 rounded text-xs bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300">Confirmed</span>',
        'stake','generated' => '<span class="px-1.5 py-0.5 rounded text-xs bg-teal-100 text-teal-700 dark:bg-teal-900/40 dark:text-teal-300">Stake</span>',
        'new'       => '<span class="px-1.5 py-0.5 rounded text-xs bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300">New</span>',
        default     => Html::encode($block->category),
    };
?>
<tr class="<?= $rowCls ?> transition-colors">
    <td class="px-2 py-1.5">
        <?php if ($coin->image): ?>
            <img width="18" src="<?= Html::encode($coin->image) ?>" class="rounded object-contain" onerror="this.style.display='none'" alt="">
        <?php endif ?>
    </td>
    <td class="px-3 py-1.5 font-medium text-gray-800 dark:text-gray-200">
        <?php if ($isAdmin): ?>
            <?= Html::a(Html::encode($coin->name), ['/admin/coinwallet', 'id' => $coin->id],
                ['class' => 'hover:text-indigo-600 dark:hover:text-indigo-400']) ?>
        <?php else: ?>
            <?= Html::encode($coin->name) ?>
        <?php endif ?>
        <span class="text-gray-400 ml-0.5">(<?= Html::encode($coin->symbol) ?>)</span>
        <?php if ($r['segwit']): ?>
            <span class="ml-0.5 px-1 py-0 rounded text-xs bg-cyan-100 text-cyan-700 dark:bg-cyan-900/30 dark:text-cyan-300">SW</span>
        <?php endif ?>
    </td>
    <td class="px-3 py-1.5 text-gray-500 dark:text-gray-400 whitespace-nowrap">
        <?= Html::encode($conv->datetoa2($block->time)) ?> ago
    </td>
    <td class="px-3 py-1.5 text-right tabular-nums text-gray-700 dark:text-gray-300">
        <?= $coin->createExplorerLink($block->height, ['height' => $block->height]) ?>
    </td>
    <td class="px-3 py-1.5 text-right font-mono tabular-nums text-gray-700 dark:text-gray-300">
        <?= Html::encode($block->amount) ?>
    </td>
    <td class="px-3 py-1.5 text-center">
        <?= $r['solo']
            ? '<span class="px-1.5 py-0.5 rounded text-xs bg-cyan-100 text-cyan-700 dark:bg-cyan-900/40 dark:text-cyan-300">solo</span>'
            : '' ?>
    </td>
    <td class="px-3 py-1.5 text-right tabular-nums text-gray-600 dark:text-gray-400">
        <?= $block->effort ? Html::encode($block->effort) . '%' : '<span class="text-gray-300 dark:text-gray-600">—</span>' ?>
    </td>
    <td class="px-3 py-1.5"><?= $statusBadge ?></td>
    <td class="px-3 py-1.5 text-right font-mono tabular-nums text-gray-500 dark:text-gray-400">
        <?= Html::encode($r['diffBlock']) ?>
    </td>
    <td class="px-3 py-1.5 text-right font-mono tabular-nums text-gray-500 dark:text-gray-400">
        <?= Html::encode($r['diffUser']) ?>
    </td>
    <td class="px-3 py-1.5 text-gray-500 dark:text-gray-400"><?= Html::encode($r['finder']) ?></td>
    <td class="px-3 py-1.5 font-mono text-gray-400 dark:text-gray-500"
        style="max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
        <?= $coin->createExplorerLink($block->blockhash, ['hash' => $block->blockhash]) ?>
    </td>
</tr>
<?php endforeach ?>
</tbody>
</table>
</div>
<div class="px-4 py-2 border-t border-gray-200 dark:border-gray-700 text-xs text-gray-400 dark:text-gray-500">
    <?= count($rows) ?> blocks
</div>
</div>

<?php endif ?>
