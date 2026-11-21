<?php

use yii\helpers\Html;
use app\models\Blocks;
use app\models\Coins;

$isTailwind = Yii::$app->LayoutManager->isTailwind();
$isLegacy   = Yii::$app->LayoutManager->isLegacy();
$conv       = Yii::$app->ConversionUtils;

$user = Yii::$app->YiimpUtils->getuserbyaddress(Yii::$app->getRequest()->getQueryParam('address'));
if (!$user || $user->is_locked) return;

$count   = max(1, min(100, (int) Yii::$app->getRequest()->getQueryParam('count', 20)));
$isAdmin = !is_null(Yii::$app->user->identity) && Yii::$app->user->identity->is_admin;

// ── Batch-load blocks + coins ─────────────────────────────────────────────────
$blocks = Blocks::find()
    ->where(['userid' => $user->id])
    ->orderBy('time desc')
    ->limit($count)
    ->all();

$coinIds  = array_values(array_unique(array_filter(array_map(fn($b) => $b->coin_id, $blocks))));
$coins    = $coinIds ? Coins::find()->where(['id' => $coinIds])->indexBy('id')->all() : [];

// ── Status badge helper ───────────────────────────────────────────────────────
$statusBadge = function(string $category, int $confirmations = 0, ?Coins $coin = null) use ($isTailwind, $isLegacy): string {
    $eta = '';
    if ($category === 'immature' && $coin && $coin->block_time && $coin->mature_blocks) {
        $t   = (int) ($coin->mature_blocks - $confirmations) * $coin->block_time;
        $eta = 'ETA: ' . sprintf('%dh %02dmn', $t / 3600, ($t / 60) % 60);
    }
    if ($isLegacy) {
        $map = [
            'orphan'    => ['red',   'Orphan'],
            'immature'  => ['amber', 'Immature ' . $confirmations . '/' . ($coin->mature_blocks ?? '?')],
            'generate'  => ['green', 'Confirmed'],
            'stake'     => ['teal',  'Stake'],
            'generated' => ['teal',  'Stake'],
            'new'       => ['blue',  'New'],
        ];
        [$cls, $text] = $map[$category] ?? ['', ucfirst($category)];
        return "<span class='wl-pill {$cls}' title=\"" . Html::encode($eta) . "\">" . Html::encode($text) . "</span>";
    }
    if ($isTailwind) {
        $map = [
            'orphan'   => 'bg-red-50 text-red-600 dark:bg-red-900/30 dark:text-red-400',
            'immature' => 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
            'generate' => 'bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-300',
            'stake'    => 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
        ];
        $cls  = $map[$category] ?? 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400';
        $text = $category === 'immature' ? "Immature ({$confirmations})" : ucfirst($category);
        return "<span class=\"inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium {$cls}\" title=\"{$eta}\">{$text}</span>";
    }
    $bsMap = ['orphan' => 'danger', 'immature' => 'warning', 'generate' => 'success', 'stake' => 'info'];
    $bs    = $bsMap[$category] ?? 'secondary';
    $text  = $category === 'immature' ? "Immature ({$confirmations})" : ucfirst($category);
    return "<span class=\"badge bg-{$bs}" . ($bs === 'warning' || $bs === 'info' ? ' text-dark' : '') . "\" title=\"{$eta}\">{$text}</span>";
};

?>

<?php
$displayBlocks = [];
$blockStats = ['confirmed' => 0, 'immature' => 0, 'orphan' => 0, 'new' => 0, 'stake' => 0];
if ($isLegacy) {
    foreach ($blocks as $b) {
        $coin = $coins[$b->coin_id] ?? null;
        if (!$coin) continue;
        if (in_array($b->category, ['stake', 'generated'], true) && !$isAdmin) continue;
        $displayBlocks[] = [$b, $coin];
        $cat = (string) $b->category;
        if ($cat === 'generate') $cat = 'confirmed';
        if ($cat === 'generated') $cat = 'stake';
        if (isset($blockStats[$cat])) $blockStats[$cat]++;
    }
}
?>

<?php if ($isLegacy): ?>
<!-- LEGACY ───────────────────────────────────────────────────────────────── -->
<div id="wallet-blocks-card" class="main-left-box">
<div class="main-left-title">
    <span class="wl-head-main">
        <span class="wl-title">Last <?= $count ?> Blocks</span>
        <span class="wl-sub">newest first</span>
    </span>
    <span class="wl-chips">
        <span class="wl-pill blue"><?= count($displayBlocks) ?> shown</span>
        <?php if ($blockStats['confirmed']): ?><span class="wl-pill green"><?= $blockStats['confirmed'] ?> confirmed</span><?php endif ?>
        <?php if ($blockStats['immature']): ?><span class="wl-pill amber"><?= $blockStats['immature'] ?> immature</span><?php endif ?>
        <?php if ($blockStats['orphan']): ?><span class="wl-pill red"><?= $blockStats['orphan'] ?> orphan</span><?php endif ?>
        <span class="wl-pill">auto · 60s</span>
    </span>
</div>
<div class="main-left-inner">
<div class="wl-summary">
    <span><i></i><b><?= count($displayBlocks) ?> blocks</b> found</span>
    <span>Refresh <b>60s</b></span>
</div>
<div style="overflow-x:auto">
<table id="wallet-blocks-table" class="wf-table">
<?php Yii::$app->ViewUtils->JavascriptReady("
    \$('#wallet-blocks-table').tablesorter({
        tableClass: 'dataGrid2',
        textExtraction: {
            1: function(node){ return \$(node).attr('data') || \$(node).text(); },
            2: function(node){ return \$(node).attr('data') || \$(node).text(); },
            3: function(node){ return \$(node).attr('data') || \$(node).text(); },
            4: function(node){ return \$(node).attr('data') || \$(node).text(); }
        }
    });
    \$('.tablesorter-header').not('.sorter-false').css('cursor','pointer');
"); ?>
<thead><tr>
    <th style="width:25%">Coin</th><th style="width:12%">Reward</th><th style="width:13%">Height</th>
    <th style="width:12%">Found</th><th style="width:16%">Effort</th><th style="width:10%">Type</th><th style="width:12%">Status</th>
</tr></thead>
<tbody>
<?php foreach ($displayBlocks as [$b, $coin]):
    $initials = strtoupper(substr((string) $coin->symbol, 0, 3));
    $img = !empty($coin->image)
        ? "<img src='" . Html::encode($coin->image) . "' alt='' onerror=\"this.remove()\">"
        : '';
    $coinName = Html::encode($coin->name);
    $coinLink = $isAdmin
        ? "<a class='wl-link' href='/site/coin?id=" . (int) $coin->id . "'>" . $coinName . "</a>"
        : "<span class='wl-link'>" . $coinName . "</span>";
    $segwit = $b->segwit ? "<span class='wl-cap' title='SegWit'>SegWit</span>" : '';

    $effortValue = (float) $b->effort;
    $effortClass = $effortValue > 150 ? 'high' : ($effortValue > 100 ? 'mid' : 'ok');
    $effortWidth = $effortValue > 0 ? min(100, max(2, $effortValue)) : 0;
    $effortBar = $effortWidth > 0
        ? "<span class='wl-eff-bar'><i style='width:" . round($effortWidth, 1) . "%'></i></span>"
        : '';
    $effortText = $b->effort ? $b->effort . '%' : 'N/A';
?>
<tr>
    <td>
        <span class="wl-coin"><span class="wl-ico"><?= Html::encode($initials) ?><?= $img ?></span>
        <span class="wl-coin-main"><?= $coinLink ?><?= $segwit ?><span class="wl-cap"><?= Html::encode($coin->algo) ?></span></span></span>
    </td>
    <td data="<?= (float) $b->amount ?>"><span class="wl-val"><?= Html::encode((string) $b->amount) ?> <?= Html::encode((string) $coin->symbol_show) ?></span><span class="wl-cap">reward</span></td>
    <td data="<?= (int) $b->height ?>"><span class="wl-val"><?= $coin->createExplorerLink($b->height, ['height' => $b->height]) ?></span><span class="wl-cap">height</span></td>
    <td data="<?= (int) $b->time ?>"><span class="wl-val"><?= Html::encode(strip_tags($conv->datetoa2($b->time))) ?> ago</span><span class="wl-cap">found</span></td>
    <td data="<?= $effortValue ?>"><span class="wl-eff <?= $effortClass ?>"><?= $effortBar ?><span class="wl-eff-num"><?= Html::encode($effortText) ?></span></span></td>
    <td><span class="wl-pill <?= $b->solo == 1 ? 'teal' : 'blue' ?>"><?= $b->solo == 1 ? 'Solo' : 'Shared' ?></span></td>
    <td><?= $statusBadge($b->category, (int) $b->confirmations, $coin) ?></td>
</tr>
<?php endforeach; if (!$displayBlocks): ?>
<tr><td colspan="7" class="wl-empty">No Blocks Found</td></tr>
<?php endif ?>
</tbody>
</table>
</div>
<div class="wl-foot"><span>Effort compares share difficulty with network difficulty</span><span>Sorted by newest · auto refresh 60s</span></div>
</div></div>


<?php elseif (!$isTailwind): ?>
<!-- ADMINLTE ─────────────────────────────────────────────────────────────── -->
<div class="card shadow-sm mb-3">
    <div class="card-header py-2 d-flex align-items-center gap-2">
        <i class="bi bi-boxes text-secondary"></i>
        <strong class="small">Last <?= $count ?> Blocks</strong>
        <span class="badge bg-secondary ms-1"><?= count($blocks) ?></span>
    </div>
    <div class="card-body p-0">
    <div class="overflow-auto">
    <table class="table table-sm table-bordered table-hover mb-0">
    <thead class="table-light">
    <tr>
        <th style="width:24px" data-sorter="false"></th>
        <th data-sorter="text">Coin</th>
        <th data-sorter="numeric" class="text-end">Block</th>
        <th data-sorter="numeric" class="text-end">Amount</th>
        <th data-sorter="numeric" class="text-end">Difficulty</th>
        <th data-sorter="numeric">Time</th>
        <th data-sorter="numeric" class="text-end">Effort</th>
        <th data-sorter="text" style="width:65px">Type</th>
        <th data-sorter="text">Status</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($blocks as $b):
        $coin = $coins[$b->coin_id] ?? null;
        if (!$coin) continue;
        if ($b->category === 'stake' && !$isAdmin) continue;
        if ($b->category === 'generated' && !$isAdmin) continue;
        $rowCls = $b->category === 'immature' ? 'table-warning' : '';
        $flags  = $b->segwit ? '&nbsp;<img src="/images/ui/segwit.png" height="8px" title="segwit">' : '';
    ?>
    <tr class="<?= $rowCls ?>">
        <td><?php if (!empty($coin->image)): ?>
            <img src="<?= Html::encode($coin->image) ?>" width="18" alt="" style="object-fit:contain">
        <?php endif ?></td>
        <td class="small">
            <?php if ($isAdmin): ?>
                <a href="/site/coin?id=<?= $coin->id ?>"><strong><?= Html::encode($coin->name) ?></strong></a>
            <?php else: ?>
                <strong><?= Html::encode($coin->name) ?></strong>
            <?php endif ?>
            <span class="badge bg-light text-dark border font-monospace ms-1"><?= Html::encode($coin->algo) ?></span>
            <?= $flags ?>
        </td>
        <td class="text-end small tabular-nums"><?= $coin->createExplorerLink($b->height, ['height' => $b->height]) ?></td>
        <td class="text-end small font-monospace"><?= Html::encode((string)$b->amount) ?></td>
        <td class="text-end small font-monospace"><?= $conv->round_difficulty($b->difficulty) ?></td>
        <td class="small text-muted" data="<?= $b->time ?>"><?= $conv->datetoa2($b->time) ?> ago</td>
        <td class="text-end small"><?= $b->effort ? $b->effort . '%' : '<span class="text-muted">N/A</span>' ?></td>
        <td>
            <?php if ($b->solo == 1): ?>
                <span class="badge bg-info text-dark">Solo</span>
            <?php elseif ($b->solo == 0): ?>
                <span class="badge bg-success">Shared</span>
            <?php endif ?>
        </td>
        <td><?= $statusBadge($b->category, (int) $b->confirmations, $coin) ?></td>
    </tr>
    <?php endforeach ?>
    </tbody>
    </table>
    </div>
    </div>
</div>


<?php else: ?>
<!-- TAILWIND ─────────────────────────────────────────────────────────────── -->
<div class="rounded-xl border border-gray-200 dark:border-gray-700
            bg-white dark:bg-gray-800 shadow-sm overflow-hidden">
    <div class="px-4 py-2.5 border-b border-gray-200 dark:border-gray-700
                flex items-center gap-2">
        <i data-lucide="blocks" class="w-4 h-4 text-gray-400 shrink-0"></i>
        <span class="text-sm font-semibold text-gray-800 dark:text-gray-100">
            Last <?= $count ?> Blocks
        </span>
        <span class="px-2 py-0.5 text-xs rounded-full bg-gray-100 dark:bg-gray-700
                     text-gray-600 dark:text-gray-300 ml-auto">
            <?= count($blocks) ?>
        </span>
    </div>
    <div class="overflow-x-auto">
    <table class="w-full text-xs">
    <thead>
    <tr class="bg-gray-50 dark:bg-gray-700/50 text-gray-500 dark:text-gray-400
               font-semibold uppercase tracking-wider
               border-b border-gray-200 dark:border-gray-700">
        <th class="px-3 py-2.5 w-8"></th>
        <th class="px-3 py-2.5 text-left">Coin</th>
        <th class="px-3 py-2.5 text-right">Block</th>
        <th class="px-3 py-2.5 text-right">Amount</th>
        <th class="px-3 py-2.5 text-right">Diff</th>
        <th class="px-3 py-2.5 text-left">Time</th>
        <th class="px-3 py-2.5 text-right">Effort</th>
        <th class="px-3 py-2.5 text-left">Type</th>
        <th class="px-3 py-2.5 text-left">Status</th>
    </tr>
    </thead>
    <tbody class="divide-y divide-gray-100 dark:divide-gray-700/50">
    <?php foreach ($blocks as $b):
        $coin = $coins[$b->coin_id] ?? null;
        if (!$coin) continue;
        if ($b->category === 'stake' && !$isAdmin) continue;
        if ($b->category === 'generated' && !$isAdmin) continue;
        $rowCls = $b->category === 'immature'
            ? 'bg-amber-50/40 dark:bg-amber-900/10'
            : 'hover:bg-gray-50/70 dark:hover:bg-gray-700/20';
        $flags = $b->segwit ? '&nbsp;<img src="/images/ui/segwit.png" height="8px" title="segwit">' : '';
    ?>
    <tr class="<?= $rowCls ?> transition-colors">
        <td class="px-3 py-2">
            <?php if (!empty($coin->image)): ?>
                <img src="<?= Html::encode($coin->image) ?>" width="18" height="18"
                     class="rounded object-contain" onerror="this.style.display='none'" alt="">
            <?php endif ?>
        </td>
        <td class="px-3 py-2">
            <div class="font-medium text-gray-900 dark:text-gray-100">
                <?php if ($isAdmin): ?>
                    <a href="/site/coin?id=<?= $coin->id ?>" class="hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">
                        <?= Html::encode($coin->name) ?>
                    </a>
                <?php else: ?>
                    <?= Html::encode($coin->name) ?>
                <?php endif ?>
                <?= $flags ?>
            </div>
            <div class="text-gray-400 dark:text-gray-500 font-mono"><?= Html::encode($coin->algo) ?></div>
        </td>
        <td class="px-3 py-2 text-right tabular-nums text-gray-600 dark:text-gray-300">
            <?= $coin->createExplorerLink($b->height, ['height' => $b->height]) ?>
        </td>
        <td class="px-3 py-2 text-right font-mono tabular-nums text-gray-700 dark:text-gray-300">
            <?= Html::encode((string)$b->amount) ?>
        </td>
        <td class="px-3 py-2 text-right font-mono tabular-nums text-gray-500 dark:text-gray-400">
            <?= $conv->round_difficulty($b->difficulty) ?>
        </td>
        <td class="px-3 py-2 text-gray-400 dark:text-gray-500 whitespace-nowrap"
            data="<?= $b->time ?>"><?= $conv->datetoa2($b->time) ?> ago</td>
        <td class="px-3 py-2 text-right tabular-nums text-gray-500 dark:text-gray-400">
            <?= $b->effort ? $b->effort . '%' : '—' ?>
        </td>
        <td class="px-3 py-2">
            <?php if ($b->solo == 1): ?>
                <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs
                             bg-cyan-50 text-cyan-700 dark:bg-cyan-900/30 dark:text-cyan-300">Solo</span>
            <?php elseif ($b->solo == 0): ?>
                <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs
                             bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-300">Shared</span>
            <?php endif ?>
        </td>
        <td class="px-3 py-2"><?= $statusBadge($b->category, (int) $b->confirmations, $coin) ?></td>
    </tr>
    <?php endforeach ?>
    </tbody>
    </table>
    </div>
</div>

<?php endif ?>
