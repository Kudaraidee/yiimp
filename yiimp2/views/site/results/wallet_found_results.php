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
$statusBadge = function(string $category, int $confirmations = 0, ?Coins $coin = null) use ($isTailwind): string {
    $eta = '';
    if ($category === 'immature' && $coin && $coin->block_time && $coin->mature_blocks) {
        $t   = (int) ($coin->mature_blocks - $confirmations) * $coin->block_time;
        $eta = 'ETA: ' . sprintf('%dh %02dmn', $t / 3600, ($t / 60) % 60);
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

<?php if ($isLegacy): ?>
<!-- LEGACY ───────────────────────────────────────────────────────────────── -->
<style>
span.solo   { padding:2px;display:inline-block;text-align:center;min-width:15px;border-radius:3px;color:white;background:#48D8D8; }
span.shared { padding:2px;display:inline-block;text-align:center;min-width:15px;border-radius:3px;color:white;background:#87d547; }
span.block  { padding:2px;display:inline-block;text-align:center;min-width:75px;border-radius:3px; }
span.block.orphan   { color:white;background:#d9534f; }
span.block.immature { color:white;background:#f0ad4e; }
span.block.generate,span.block.confirmed { color:white;background:#5cb85c; }
</style>
<div class="main-left-box">
<div class="main-left-title">Last <?= $count ?> Blocks found by <?= Html::encode($user->username) ?></div>
<div class="main-left-inner">
<table class="dataGrid2">
<thead><tr>
    <th style="max-width:18px"></th>
    <th>Name</th><th>Block</th><th>Amount</th><th>Difficulty</th>
    <th>Time</th><th>Effort</th><th>Type</th><th>Status</th>
</tr></thead>
<tbody>
<?php foreach ($blocks as $b):
    $coin = $coins[$b->coin_id] ?? null;
    if (!$coin) continue;
    if ($b->category === 'stake' && !$isAdmin) continue;
    if ($b->category === 'generated' && !$isAdmin) continue;
    $rowStyle = $b->category === 'immature' ? "style='background-color:#e0d3e8;'" : "class='ssrow'";
    $flags = $b->segwit ? '&nbsp;<img src="/images/ui/segwit.png" height="8px" title="segwit">' : '';
?>
<tr <?= $rowStyle ?>>
    <td><img width="16" src="<?= Html::encode($coin->image) ?>"></td>
    <td>
        <?php if ($isAdmin): ?>
            <a href="/site/coin?id=<?= $coin->id ?>"><b><?= Html::encode($coin->name) ?></b></a>
        <?php else: ?>
            <b><?= Html::encode($coin->name) ?></b>
        <?php endif ?>
        &nbsp;(<?= Html::encode($coin->algo) ?>)<?= $flags ?>
    </td>
    <td><?= $coin->createExplorerLink($b->height, ['height' => $b->height]) ?></td>
    <td><?= $b->amount ?></td>
    <td><?= $conv->round_difficulty($b->difficulty) ?></td>
    <td data="<?= $b->time ?>"><b><?= $conv->datetoa2($b->time) ?> ago</b></td>
    <td><?= $b->effort ? $b->effort . '%' : 'N/A' ?></td>
    <td><?php if ($b->solo == 1): ?><span class="solo" title="Solo">Solo</span>
        <?php elseif ($b->solo == 0): ?><span class="shared" title="Shared">Shared</span>
        <?php endif ?></td>
    <td><?= $statusBadge($b->category, (int) $b->confirmations, $coin) ?></td>
</tr>
<?php endforeach ?>
</tbody>
</table>
<br></div></div><br>


<?php elseif (!$isTailwind): ?>
<!-- ADMINLTE ─────────────────────────────────────────────────────────────── -->
<div class="card shadow-sm mb-3">
    <div class="card-header py-2 d-flex align-items-center gap-2">
        <i class="bi bi-boxes text-secondary"></i>
        <strong class="small">Last <?= $count ?> Blocks — <?= Html::encode($user->username) ?></strong>
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
