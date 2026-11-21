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
$statusBadge = function(int $status, ?Blocks $block, ?Coins $coin) use ($isTailwind, $isLegacy, $conv): string {
    if ($isLegacy) {
        $legacyMap = [
            0  => ['amber', 'Immature'],
            1  => ['green', YIIMP_ALLOW_EXCHANGE ? 'Exchange' : 'Confirmed'],
            2  => ['', 'Cleared'],
            -1 => ['red', 'Invalid'],
        ];
        [$cls, $label] = $legacyMap[$status] ?? ['', 'Unknown'];
        if ($status === 0 && $block && $coin) {
            $label = "Immature ({$block->confirmations}/{$coin->mature_blocks})";
        }
        return "<span class='wl-pill {$cls}'>" . Html::encode($label) . "</span>";
    }
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

<?php
$displayEarnings = [];
if ($isLegacy) {
    foreach ($earnings as $e) {
        $coin  = $coins[$e->coinid]    ?? null;
        $block = $blocks[$e->blockid]  ?? null;
        if (!$block) continue;
        if (!$coin && !$showrental) continue;
        $displayEarnings[] = [$e, $block, $coin];
    }
}
?>

<?php if ($isLegacy): ?>
<!-- LEGACY ───────────────────────────────────────────────────────────────── -->
<div id="wallet-earnings-card" class="main-left-box">
<div class="main-left-title">
    <span class="wl-head-main">
        <span class="wl-title">Last <?= $count ?> Earnings</span>
        <span class="wl-sub">newest first</span>
    </span>
    <span class="wl-chips">
        <span class="wl-pill blue"><?= count($displayEarnings) ?> shown</span>
        <span class="wl-pill">auto · 60s</span>
    </span>
</div>
<div class="main-left-inner">
<div class="wl-summary">
    <span><i></i><b><?= count($displayEarnings) ?> earnings</b> credited</span>
    <span>Value in <b>mBTC</b></span>
    <span>Refresh <b>60s</b></span>
</div>
<div style="overflow-x:auto">
<table class="we-table">
<thead><tr>
    <th style="width:25%">Coin</th><th style="width:13%">Height</th><th style="width:14%">Reward</th>
    <th style="width:10%">Share</th><th style="width:13%">Value</th><th style="width:13%">Found</th><th style="width:12%">Status</th>
</tr></thead>
<tbody>
<?php foreach ($displayEarnings as [$e, $block, $coin]):
    $d = $conv->datetoa2($e->create_time);
    if (!$coin):
        $reward  = $conv->bitcoinvaluetoa($e->amount);
        $value   = $conv->mbitcoinvaluetoa($e->amount * 1000);
        $percent = $block->amount ? $conv->percentvaluetoa($e->amount * 100 / $block->amount) : 0;
?>
<tr>
    <td><span class="wl-coin"><span class="wl-ico"><img src="/images/btc.png" alt=""></span>
        <span class="wl-coin-main"><span class="wl-link">Rental</span><span class="wl-cap"><?= Html::encode($block->algo) ?></span></span></span></td>
    <td><span class="wl-val">—</span></td>
    <td><span class="wl-val"><?= Html::encode($reward) ?> BTC</span></td>
    <td><span class="wl-val"><?= Html::encode($percent) ?>%</span></td>
    <td><span class="wl-val"><?= Html::encode($value) ?></span></td>
    <td><span class="wl-val"><?= Html::encode(strip_tags($d)) ?> ago</span></td>
    <td><span class="wl-pill">Cleared</span></td>
</tr>
<?php continue; endif ?>
<?php
    $height  = number_format($block->height, 0, '.', ' ');
    $reward  = $conv->altcoinvaluetoa($e->amount);
    $percent = $block->amount ? $conv->percentvaluetoa($e->amount * 100 / $block->amount) : 0;
    $value   = $conv->mbitcoinvaluetoa($e->amount * $e->price * 1000);
    $initials = strtoupper(substr((string) $coin->symbol, 0, 3));
    $img = !empty($coin->image)
        ? "<img src='" . Html::encode($coin->image) . "' alt='' onerror=\"this.remove()\">"
        : '';
    $blockUrl = $coin->createExplorerLink($coin->name, ['height' => $block->height]);
?>
<tr>
    <td><span class="wl-coin"><span class="wl-ico"><?= Html::encode($initials) ?><?= $img ?></span>
        <span class="wl-coin-main"><span class="wl-link"><?= $blockUrl ?></span><span class="wl-cap"><?= Html::encode($coin->algo) ?></span></span></span></td>
    <td><span class="wl-val"><?= Html::encode($height) ?></span><span class="wl-cap">height</span></td>
    <td><span class="wl-val"><?= Html::encode($reward) ?> <?= Html::encode($coin->symbol_show) ?></span></td>
    <td><span class="wl-val"><?= Html::encode($percent) ?>%</span></td>
    <td><span class="wl-val"><?= Html::encode($value) ?></span><span class="wl-cap">mBTC</span></td>
    <td><span class="wl-val"><?= Html::encode(strip_tags($d)) ?> ago</span></td>
    <td><?= $statusBadge($e->status, $block, $coin) ?></td>
</tr>
<?php endforeach; if (!$displayEarnings): ?>
<tr><td colspan="7" class="wl-empty">No Earnings Found</td></tr>
<?php endif ?>
</tbody>
</table>
</div>
<div class="wl-foot"><span>Share is the portion of the block reward credited to this wallet</span><span>Refresh 60s</span></div>
</div></div>


<?php elseif (!$isTailwind): ?>
<!-- ADMINLTE ─────────────────────────────────────────────────────────────── -->
<div class="card shadow-sm mb-3">
    <div class="card-header py-2 d-flex align-items-center gap-2">
        <i class="bi bi-receipt text-secondary"></i>
        <strong class="small">Last <?= $count ?> Earnings</strong>
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
