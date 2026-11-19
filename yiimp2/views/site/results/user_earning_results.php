<?php

use yii\helpers\Html;
use app\models\Earnings;
use app\models\Coins;
use app\models\Blocks;

$isTailwind = Yii::$app->LayoutManager->isTailwind();
$isLegacy   = Yii::$app->LayoutManager->isLegacy();
$conv       = Yii::$app->ConversionUtils;

$user = Yii::$app->YiimpUtils->getuserbyaddress(Yii::$app->getRequest()->getQueryParam('address'));
if (!$user || $user->is_locked) return;

$count       = max(1, min(200, (int) Yii::$app->getRequest()->getQueryParam('count', 50)));
$showrental  = (bool) YIIMP_RENTAL;

// ── Fetch earnings and batch-load coins + blocks ──────────────────────────────
$earnings = Earnings::find()
    ->where(['userid' => $user->id])
    ->orderBy('create_time desc')
    ->limit($count)
    ->all();

$coinIds  = array_values(array_unique(array_filter(array_map(fn($e) => $e->coinid,  $earnings))));
$blockIds = array_values(array_unique(array_filter(array_map(fn($e) => $e->blockid, $earnings))));

$coins  = $coinIds  ? Coins::find()->where(['id' => $coinIds])->indexBy('id')->all()   : [];
$blocks = $blockIds ? Blocks::find()->where(['id' => $blockIds])->indexBy('id')->all() : [];

// ── Status badge helper ───────────────────────────────────────────────────────
$statusBadge = function(int $status, ?Blocks $block, ?Coins $coin) use ($isTailwind, $conv): string {
    if ($status === 0) {
        $eta = '';
        if ($coin && $block && $coin->block_time && $coin->mature_blocks) {
            $t   = (int) ($coin->mature_blocks - $block->confirmations) * $coin->block_time;
            $eta = 'ETA: ' . sprintf('%dh %02dmn', $t / 3600, ($t / 60) % 60);
        }
        $text = $coin ? "Immature ({$block->confirmations}/{$coin->mature_blocks})" : 'Immature';
        return $isTailwind
            ? "<span class=\"inline-flex items-center px-1.5 py-0.5 rounded-full text-xs bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300\" title=\"{$eta}\">{$text}</span>"
            : "<span class=\"badge bg-warning text-dark\" title=\"{$eta}\">{$text}</span>";
    }
    if ($status === 1) {
        $label = YIIMP_ALLOW_EXCHANGE ? 'Exchange' : 'Confirmed';
        return $isTailwind
            ? "<span class=\"inline-flex items-center px-1.5 py-0.5 rounded-full text-xs bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-300\">{$label}</span>"
            : "<span class=\"badge bg-success\">{$label}</span>";
    }
    if ($status === 2) {
        return $isTailwind
            ? '<span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400">Cleared</span>'
            : '<span class="badge bg-secondary">Cleared</span>';
    }
    if ($status === -1) {
        return $isTailwind
            ? '<span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs bg-red-50 text-red-600 dark:bg-red-900/30 dark:text-red-400">Invalid</span>'
            : '<span class="badge bg-danger">Invalid</span>';
    }
    return '';
};

?>

<?php if ($isLegacy): ?>
<!-- LEGACY ───────────────────────────────────────────────────────────────── -->
<style>
span.block { padding:2px;display:inline-block;text-align:center;min-width:75px;border-radius:3px; }
span.block.invalid  { color:white;background:#d9534f; }
span.block.immature { color:white;background:#f0ad4e; }
span.block.exchange { color:white;background:#5cb85c; }
span.block.cleared  { color:white;background:gray; }
</style>
<div class="main-left-box">
<div class="main-left-title">Last <?= $count ?> Earnings: <?= Html::encode($user->username) ?></div>
<div class="main-left-inner">
<table class="dataGrid2">
<thead><tr>
    <td></td><th>Name</th><th align=right>Block</th><th align=right>Amount</th>
    <th align=right>Percent</th><th align=right>mBTC</th><th align=right>Time</th><th align=right>Status</th>
</tr></thead>
<tbody>
<?php foreach ($earnings as $e):
    $coin  = $coins[$e->coinid]    ?? null;
    $block = $blocks[$e->blockid]  ?? null;
    if (!$block) continue;
    $d = $conv->datetoa2($e->create_time);
    if (!$coin):
        if (!$showrental) continue;
        $reward  = $conv->bitcoinvaluetoa($e->amount);
        $value   = $conv->mbitcoinvaluetoa($e->amount * 1000);
        $percent = $block->amount ? $conv->percentvaluetoa($e->amount * 100 / $block->amount) : 0;
?>
<tr class="ssrow">
    <td width="18"><img width="16" src="/images/btc.png"></td>
    <td><b>Rental</b><span style="font-size:.8em;"> (<?= Html::encode($block->algo) ?>)</span></td>
    <td align="right" style="font-size:.8em;"><b><?= $reward ?> BTC</b></td>
    <td align="right" style="font-size:.8em;"><?= $percent ?>%</td>
    <td align="right" style="font-size:.8em;"><?= $value ?></td>
    <td align="right" style="font-size:.8em;"><?= $d ?>&nbsp;ago</td>
    <td align="right" style="font-size:.8em;"><span class="block cleared">Cleared</span></td>
</tr>
<?php continue; endif ?>
<?php
    $height  = number_format($block->height, 0, '.', ' ');
    $reward  = $conv->altcoinvaluetoa($e->amount);
    $percent = $block->amount ? $conv->percentvaluetoa($e->amount * 100 / $block->amount) : 0;
    $value   = $conv->mbitcoinvaluetoa($e->amount * $e->price * 1000);
    $blockUrl = $coin->createExplorerLink($coin->name, ['height' => $block->height]);
?>
<tr class="ssrow">
    <td width="18"><img width="16" src="<?= Html::encode($coin->image) ?>"></td>
    <td><b><?= $blockUrl ?></b><span style="font-size:.8em;"> (<?= Html::encode($coin->algo) ?>)</span></td>
    <td align="right" style="font-size:.8em;"><?= $height ?></td>
    <td align="right" style="font-size:.8em;"><b><?= $reward ?> <?= Html::encode($coin->symbol_show) ?></b></td>
    <td align="right" style="font-size:.8em;"><?= $percent ?>%</td>
    <td align="right" style="font-size:.8em;"><?= $value ?></td>
    <td align="right" style="font-size:.8em;"><?= $d ?>&nbsp;ago</td>
    <td align="right" style="font-size:.8em;"><?= $statusBadge($e->status, $block, $coin) ?></td>
</tr>
<?php endforeach ?>
</tbody>
</table>
<br></div></div><br>


<?php elseif (!$isTailwind): ?>
<!-- ADMINLTE ─────────────────────────────────────────────────────────────── -->
<div class="card shadow-sm mb-3">
    <div class="card-header py-2 d-flex align-items-center gap-2">
        <i class="bi bi-receipt text-secondary"></i>
        <strong class="small">Last <?= $count ?> Earnings — <?= Html::encode($user->username) ?></strong>
        <span class="badge bg-secondary ms-1"><?= count($earnings) ?></span>
    </div>
    <div class="card-body p-0">
    <div class="overflow-auto">
    <table class="table table-sm table-bordered table-hover mb-0">
    <thead class="table-light">
    <tr>
        <th style="width:24px"></th>
        <th data-sorter="text">Coin</th>
        <th data-sorter="numeric" class="text-end">Block</th>
        <th data-sorter="currency" class="text-end">Amount</th>
        <th data-sorter="numeric" class="text-end">%</th>
        <th data-sorter="currency" class="text-end">mBTC</th>
        <th data-sorter="numeric">Time</th>
        <th data-sorter="text">Status</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($earnings as $e):
        $coin  = $coins[$e->coinid]   ?? null;
        $block = $blocks[$e->blockid] ?? null;
        if (!$block) continue;
        $d = $conv->datetoa2($e->create_time);
        if (!$coin):
            if (!$showrental) continue;
            $reward  = $conv->bitcoinvaluetoa($e->amount);
            $value   = $conv->mbitcoinvaluetoa($e->amount * 1000);
            $percent = $block->amount ? $conv->percentvaluetoa($e->amount * 100 / $block->amount) : 0;
    ?>
    <tr>
        <td><img width="16" src="/images/btc.png" alt=""></td>
        <td class="small"><strong>Rental</strong> <span class="text-muted">(<?= Html::encode($block->algo) ?>)</span></td>
        <td></td>
        <td class="text-end small font-monospace"><b><?= $reward ?> BTC</b></td>
        <td class="text-end small"><?= $percent ?>%</td>
        <td class="text-end small font-monospace"><?= $value ?></td>
        <td class="small text-muted"><?= $d ?> ago</td>
        <td><span class="badge bg-secondary">Cleared</span></td>
    </tr>
    <?php continue; endif ?>
    <?php
        $height  = number_format($block->height, 0, '.', ' ');
        $reward  = $conv->altcoinvaluetoa($e->amount);
        $percent = $block->amount ? $conv->percentvaluetoa($e->amount * 100 / $block->amount) : 0;
        $value   = $conv->mbitcoinvaluetoa($e->amount * $e->price * 1000);
        $imm     = $e->status === 0;
    ?>
    <tr class="<?= $imm ? 'table-warning' : '' ?>">
        <td><?php if (!empty($coin->image)): ?>
            <img src="<?= Html::encode($coin->image) ?>" width="18" alt="" style="object-fit:contain">
        <?php endif ?></td>
        <td class="small">
            <?= $coin->createExplorerLink('<strong>' . Html::encode($coin->name) . '</strong>', ['height' => $block->height]) ?>
            <span class="badge bg-light text-dark border font-monospace ms-1"><?= Html::encode($coin->algo) ?></span>
        </td>
        <td class="text-end small tabular-nums"><?= $height ?></td>
        <td class="text-end small font-monospace"><b><?= $reward ?> <?= Html::encode($coin->symbol_show) ?></b></td>
        <td class="text-end small tabular-nums"><?= $percent ?>%</td>
        <td class="text-end small font-monospace"><?= $value ?></td>
        <td class="small text-muted"><?= $d ?> ago</td>
        <td><?= $statusBadge($e->status, $block, $coin) ?></td>
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
        <i data-lucide="receipt" class="w-4 h-4 text-gray-400 shrink-0"></i>
        <span class="text-sm font-semibold text-gray-800 dark:text-gray-100">
            Last <?= $count ?> Earnings
        </span>
        <span class="px-2 py-0.5 text-xs rounded-full bg-gray-100 dark:bg-gray-700
                     text-gray-600 dark:text-gray-300 ml-auto">
            <?= count($earnings) ?>
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
        <th class="px-3 py-2.5 text-right">%</th>
        <th class="px-3 py-2.5 text-right">mBTC</th>
        <th class="px-3 py-2.5 text-left">Time</th>
        <th class="px-3 py-2.5 text-left">Status</th>
    </tr>
    </thead>
    <tbody class="divide-y divide-gray-100 dark:divide-gray-700/50">
    <?php foreach ($earnings as $e):
        $coin  = $coins[$e->coinid]   ?? null;
        $block = $blocks[$e->blockid] ?? null;
        if (!$block) continue;
        $d = $conv->datetoa2($e->create_time);
        if (!$coin):
            if (!$showrental) continue;
            $reward  = $conv->bitcoinvaluetoa($e->amount);
            $value   = $conv->mbitcoinvaluetoa($e->amount * 1000);
            $percent = $block->amount ? $conv->percentvaluetoa($e->amount * 100 / $block->amount) : 0;
    ?>
    <tr class="hover:bg-gray-50/70 dark:hover:bg-gray-700/20 transition-colors">
        <td class="px-3 py-2"><img width="18" src="/images/btc.png" alt="" class="rounded object-contain"></td>
        <td class="px-3 py-2 font-medium text-gray-700 dark:text-gray-300">
            Rental <span class="text-gray-400 dark:text-gray-500 font-mono">(<?= Html::encode($block->algo) ?>)</span>
        </td>
        <td></td>
        <td class="px-3 py-2 text-right font-mono tabular-nums font-semibold text-gray-800 dark:text-gray-200">
            <?= $reward ?> BTC
        </td>
        <td class="px-3 py-2 text-right tabular-nums text-gray-500 dark:text-gray-400"><?= $percent ?>%</td>
        <td class="px-3 py-2 text-right font-mono tabular-nums text-gray-500 dark:text-gray-400"><?= $value ?></td>
        <td class="px-3 py-2 text-gray-400 dark:text-gray-500"><?= $d ?> ago</td>
        <td class="px-3 py-2"><span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400">Cleared</span></td>
    </tr>
    <?php continue; endif ?>
    <?php
        $height  = number_format($block->height, 0, '.', ' ');
        $reward  = $conv->altcoinvaluetoa($e->amount);
        $percent = $block->amount ? $conv->percentvaluetoa($e->amount * 100 / $block->amount) : 0;
        $value   = $conv->mbitcoinvaluetoa($e->amount * $e->price * 1000);
        $imm     = $e->status === 0;
    ?>
    <tr class="<?= $imm ? 'bg-amber-50/40 dark:bg-amber-900/10' : 'hover:bg-gray-50/70 dark:hover:bg-gray-700/20' ?> transition-colors">
        <td class="px-3 py-2">
            <?php if (!empty($coin->image)): ?>
                <img src="<?= Html::encode($coin->image) ?>" width="18" height="18"
                     class="rounded object-contain" onerror="this.style.display='none'" alt="">
            <?php endif ?>
        </td>
        <td class="px-3 py-2">
            <div class="font-medium text-gray-900 dark:text-gray-100">
                <?= $coin->createExplorerLink(Html::encode($coin->name), ['height' => $block->height]) ?>
            </div>
            <div class="font-mono text-gray-400 dark:text-gray-500"><?= Html::encode($coin->algo) ?></div>
        </td>
        <td class="px-3 py-2 text-right tabular-nums text-gray-600 dark:text-gray-300"><?= $height ?></td>
        <td class="px-3 py-2 text-right font-mono tabular-nums font-semibold text-gray-800 dark:text-gray-200">
            <?= $reward ?> <?= Html::encode($coin->symbol_show) ?>
        </td>
        <td class="px-3 py-2 text-right tabular-nums text-gray-500 dark:text-gray-400"><?= $percent ?>%</td>
        <td class="px-3 py-2 text-right font-mono tabular-nums text-indigo-600 dark:text-indigo-400"><?= $value ?></td>
        <td class="px-3 py-2 text-gray-400 dark:text-gray-500 whitespace-nowrap"><?= $d ?> ago</td>
        <td class="px-3 py-2"><?= $statusBadge($e->status, $block, $coin) ?></td>
    </tr>
    <?php endforeach ?>
    </tbody>
    </table>
    </div>
</div>

<?php endif ?>
