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

$blockStats = ['confirmed' => 0, 'immature' => 0, 'orphan' => 0, 'new' => 0, 'stake' => 0];
foreach ($rows as $statRow) {
    $cat = (string) $statRow['block']->category;
    if ($cat === 'generate') $cat = 'confirmed';
    if ($cat === 'generated') $cat = 'stake';
    if (isset($blockStats[$cat])) $blockStats[$cat]++;
}

$coinInitials = strtoupper(substr((string) $coin->symbol, 0, 3));

?>

<?php if ($isLegacy): ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     LEGACY
     ══════════════════════════════════════════════════════════════════════════ -->
<?php
echo <<<'CSS'
<style>
#block-card {
    border: 1px solid #d8e2ef !important;
    border-radius: 7px !important;
    box-shadow: 0 1px 2px rgba(20,40,80,.04);
    overflow: hidden;
}
#block-card > .main-left-title {
    display: flex !important; align-items: center; justify-content: space-between; gap: 10px;
    min-height: 34px; padding: 9px 12px !important;
    background: #f8fafc !important; border-bottom: 1px solid #e8eef6;
    color: #26385a; font-size: 13px !important; font-weight: 800 !important; line-height: 1.3;
}
#block-card .bk-head-main { display: flex; align-items: center; gap: 9px; flex-wrap: wrap; min-width: 0; }
#block-card .bk-title { font-size: 13px; font-weight: 800; color: #26385a; letter-spacing: .01em; }
#block-card .bk-sub { font-size: 10px; color: #8a94a6; font-weight: 500; }
#block-card .bk-ico {
    position: relative; flex: 0 0 26px; height: 26px; border-radius: 6px; overflow: hidden;
    display: grid; place-items: center; color: #fff; font-size: 7px; font-weight: 800;
    background: linear-gradient(135deg, #4f79c8, #2c5aa0);
}
#block-card .bk-ico img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: contain; background: #fff; }
#block-card .bk-chips { display: flex; gap: 5px; flex-wrap: wrap; }
#block-card .bk-chip {
    border-radius: 999px; padding: 2px 7px; font-size: 9px; font-weight: 700; white-space: nowrap;
    background: #f1f5f9; color: #64748b; border: 1px solid #e2e8f0;
}
#block-card .bk-chip.confirmed { background: #e8f7ee; color: #187d46; border-color: #ccebd8; }
#block-card .bk-chip.immature { background: #fff7e8; color: #a86400; border-color: #ffe2b8; }
#block-card .bk-chip.orphan { background: #fdecec; color: #c0392b; border-color: #f6d2d2; }
#block-card .main-left-inner { padding: 0; }
#block-card .bk-summary {
    display: flex; gap: 18px; flex-wrap: wrap; padding: 8px 12px;
    background: #fcfdff; border-bottom: 1px solid #eef2f7;
    font-size: 10px; color: #8a94a6;
}
#block-card .bk-summary b { color: #33405a; font-weight: 700; }
#block-card .bk-summary i {
    display: inline-block; width: 6px; height: 6px; border-radius: 50%;
    background: #2fae5a; box-shadow: 0 0 0 3px rgba(47,174,90,.12); margin-right: 5px;
}
#maintable.bk-table {
    width: 100% !important; border-collapse: collapse; table-layout: fixed;
    font-size: 11px; color: #334155;
}
#maintable.bk-table th {
    background: #fff; padding: 7px 9px; text-align: right;
    font-size: 9px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase;
    color: #8a94a6; border-bottom: 1px solid #e8eef6; white-space: nowrap;
}
#maintable.bk-table th:first-child, #maintable.bk-table td:first-child { text-align: left; }
#maintable.bk-table th.tablesorter-header { cursor: pointer; }
#maintable.bk-table th.tablesorter-header:hover { color: #2c5aa0; }
#maintable.bk-table td {
    padding: 8px 9px; border-bottom: 1px solid #eef2f7;
    text-align: right; vertical-align: middle;
}
#maintable.bk-table tbody tr:last-child td { border-bottom: 0; }
#maintable.bk-table tbody tr:hover { background: #f7faff; }
#block-card .bk-val {
    display: block; font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    font-variant-numeric: tabular-nums; font-size: 11px; font-weight: 700;
    color: #2b3d5b; white-space: nowrap;
}
#block-card .bk-cap { display: block; font-size: 9px; color: #98a3b5; margin-top: 1px; white-space: nowrap; }
#block-card .bk-link { font-size: 11px; font-weight: 700; color: #26385a; text-decoration: none; }
#block-card .bk-link:hover { color: #2c5aa0; }
#block-card .bk-eff { display: flex; align-items: center; justify-content: flex-end; gap: 6px; }
#block-card .bk-eff-bar { width: 46px; height: 5px; border-radius: 999px; background: #edf1f6; overflow: hidden; }
#block-card .bk-eff-bar i { display: block; height: 100%; border-radius: 999px; background: #2fae5a; }
#block-card .bk-eff.mid .bk-eff-bar i { background: #e0a020; }
#block-card .bk-eff.high .bk-eff-bar i { background: #d9534f; }
#block-card .bk-eff-num { font-size: 10px; font-weight: 700; color: #475569; min-width: 42px; text-align: right; }
#block-card .bk-eff.mid .bk-eff-num { color: #a86400; }
#block-card .bk-eff.high .bk-eff-num { color: #c0392b; }
#block-card .bk-type, #block-card .bk-status, #block-card .bk-segwit {
    display: inline-block; border-radius: 999px; padding: 2px 7px;
    font-size: 9px; font-weight: 700; line-height: 14px; white-space: nowrap;
}
#block-card .bk-type.shared { background: #eef4ff; color: #2c5aa0; border: 1px solid #d7e5ff; }
#block-card .bk-type.solo { background: #e6fbfb; color: #0f766e; border: 1px solid #c7f0ee; }
#block-card .bk-status.confirmed { background: #e8f7ee; color: #187d46; border: 1px solid #ccebd8; }
#block-card .bk-status.immature { background: #fff7e8; color: #a86400; border: 1px solid #ffe2b8; }
#block-card .bk-status.orphan { background: #fdecec; color: #c0392b; border: 1px solid #f6d2d2; }
#block-card .bk-status.new { background: #f3e8ff; color: #7e22ce; border: 1px solid #e9d5ff; }
#block-card .bk-status.stake { background: #e6fbfb; color: #0f766e; border: 1px solid #c7f0ee; }
#block-card .bk-status.unknown { background: #f1f5f9; color: #64748b; border: 1px solid #e2e8f0; }
#block-card .bk-segwit { background: #f1f5f9; color: #64748b; border: 1px solid #e2e8f0; margin-left: 4px; }
#block-card .bk-finder { display: block; font-size: 9px; color: #8a94a6; margin-top: 2px; white-space: nowrap; }
#block-card .bk-foot {
    display: flex; justify-content: space-between; gap: 10px; flex-wrap: wrap;
    padding: 7px 12px; background: #fcfdff; border-top: 1px solid #eef2f7;
    font-size: 9px; color: #98a3b5;
}
#block-card .bk-empty { text-align: center !important; padding: 22px 10px !important; color: #98a3b5; font-size: 11px; }
@media(max-width:820px){
    #maintable.bk-table { table-layout: auto; }
    #block-card .bk-chips { display: none; }
}
</style>
CSS;
?>
<div id='block-card' class='main-left-box'>
    <div class='main-left-title'>
        <span class='bk-head-main'>
            <span class='bk-ico'><?= Html::encode($coinInitials) ?><?php if ($coin->image): ?><img src="<?= Html::encode($coin->image) ?>" alt="" onerror="this.remove()"><?php endif ?></span>
            <span class='bk-title'>Blocks</span>
            <span class='bk-sub'><?= Html::encode($coin->name . ' (' . $coin->symbol . ') · ' . $coin->algo) ?></span>
        </span>
        <span class='bk-chips'>
            <span class='bk-chip'><?= count($rows) ?> blocks</span>
            <?php if ($blockStats['confirmed']): ?><span class='bk-chip confirmed'><?= $blockStats['confirmed'] ?> confirmed</span><?php endif ?>
            <?php if ($blockStats['immature']): ?><span class='bk-chip immature'><?= $blockStats['immature'] ?> immature</span><?php endif ?>
            <?php if ($blockStats['orphan']): ?><span class='bk-chip orphan'><?= $blockStats['orphan'] ?> orphan</span><?php endif ?>
            <span class='bk-chip'>auto · 60s</span>
        </span>
    </div>
    <div class='main-left-inner'>
        <div class='bk-summary'>
            <span><i></i><b><?= count($rows) ?> blocks</b> shown</span>
            <span>Algo <b><?= Html::encode($coin->algo) ?></b></span>
            <span>Reward unit <b><?= Html::encode($coin->symbol) ?></b></span>
            <span>Refresh <b>60s</b></span>
        </div>
        <div style='overflow-x:auto'>
        <table id='maintable' class='bk-table'>
        <?php Yii::$app->ViewUtils->JavascriptReady("
            \$('#maintable').tablesorter({
                tableClass: 'dataGrid2',
                textExtraction: {
                    0: function(node){ return \$(node).attr('data') || \$(node).text(); },
                    1: function(node){ return \$(node).attr('data') || \$(node).text(); },
                    2: function(node){ return \$(node).attr('data') || \$(node).text(); },
                    3: function(node){ return \$(node).attr('data') || \$(node).text(); },
                    4: function(node){ return \$(node).attr('data') || \$(node).text(); }
                }
            });
            \$('.tablesorter-header').not('.sorter-false').css('cursor','pointer');
        "); ?>
        <thead><tr>
            <th style="width:12%">Found</th>
            <th style="width:22%">Height</th>
            <th style="width:11%">Reward</th>
            <th style="width:14%">Effort</th>
            <th style="width:16%">Difficulty</th>
            <th data-sorter="false" style="width:15%">Finder / Type</th>
            <th data-sorter="false" style="width:10%">Status</th>
        </tr></thead>
        <tbody>
        <?php if (empty($rows)): ?>
            <tr><td colspan="7" class="bk-empty">No Blocks Found</td></tr>
        <?php else: ?>
            <?php foreach ($rows as $r):
                $block  = $r['block'];
                $hash   = (string) $block->blockhash;
                $hashShort = strlen($hash) > 18 ? substr($hash, 0, 10) . '…' . substr($hash, -6) : $hash;
                $heightLink = $coin->createExplorerLink($block->height, ['height' => $block->height]);
                $hashLink   = $coin->createExplorerLink($hashShort, ['hash' => $block->blockhash]);

                $effortValue = (float) $block->effort;
                $effortClass = $effortValue > 150 ? 'high' : ($effortValue > 100 ? 'mid' : 'ok');
                $effortWidth = $effortValue > 0 ? min(100, max(2, $effortValue)) : 0;
                $effortBar = $effortWidth > 0
                    ? "<span class='bk-eff-bar'><i style='width:" . round($effortWidth, 1) . "%'></i></span>"
                    : '';
                $effortText = $block->effort ? $block->effort . '%' : 'N/A';

                $statusMap = [
                    'orphan'    => ['Orphan', 'orphan'],
                    'immature'  => ['Immature ' . $block->confirmations . '/' . $coin->mature_blocks, 'immature'],
                    'generate'  => ['Confirmed', 'confirmed'],
                    'stake'     => ['Stake', 'stake'],
                    'generated' => ['Stake', 'stake'],
                    'new'       => ['New', 'new'],
                ];
                $statusInfo = $statusMap[$block->category] ?? [Html::encode((string) $block->category), 'unknown'];
                $statusTitle = ($block->category === 'immature' && $r['maturityEta'])
                    ? ' title="ETA: ' . Html::encode($r['maturityEta']) . '"'
                    : '';

                $typeLabel = $r['solo'] ? 'Solo' : 'Shared';
                $typeClass = $r['solo'] ? 'solo' : 'shared';
                $finder    = $r['finder'] !== '' ? $r['finder'] : '—';
                $segwit    = $r['segwit'] ? "<span class='bk-segwit' title='Block signalled SegWit'>SegWit</span>" : '';
            ?>
            <tr>
                <td data="<?= (int) $block->time ?>">
                    <span class='bk-val'><?= Html::encode(strip_tags($conv->datetoa2($block->time))) ?> ago</span>
                    <span class='bk-cap'>found</span>
                </td>
                <td data="<?= (int) $block->height ?>">
                    <span class='bk-val'><?= $heightLink ?></span>
                    <span class='bk-cap'><?= $hashLink ?></span>
                </td>
                <td data="<?= (float) $block->amount ?>">
                    <span class='bk-val'><?= Html::encode($block->amount) ?> <?= Html::encode($coin->symbol) ?></span>
                    <span class='bk-cap'>reward</span>
                </td>
                <td data="<?= $effortValue ?>">
                    <span class='bk-eff <?= $effortClass ?>' title="Effort vs expected difficulty"><?= $effortBar ?>
                        <span class='bk-eff-num'><?= Html::encode($effortText) ?></span></span>
                </td>
                <td data="<?= (float) $block->difficulty ?>">
                    <span class='bk-val'><?= Html::encode($r['diffBlock']) ?></span>
                    <span class='bk-cap'>share diff <?= Html::encode($r['diffUser']) ?></span>
                </td>
                <td>
                    <span class='bk-finder'><?= Html::encode($finder) ?></span>
                    <span class='bk-type <?= $typeClass ?>'><?= $typeLabel ?></span><?= $segwit ?>
                </td>
                <td>
                    <span class='bk-status <?= $statusInfo[1] ?>'<?= $statusTitle ?>><?= Html::encode($statusInfo[0]) ?></span>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
        </table>
        </div>
        <div class='bk-foot'>
            <span>Effort compares share difficulty with network difficulty</span>
            <span>Sorted by newest · auto refresh 60s</span>
        </div>
    </div>
</div>

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
