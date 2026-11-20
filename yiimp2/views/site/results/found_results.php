<?php

use yii\helpers\Html;
use app\models\Blocks;
use app\models\Coins;

$isTailwind = Yii::$app->LayoutManager->isTailwind();
$isLegacy   = Yii::$app->LayoutManager->isLegacy();

$showrental = (bool) YIIMP_RENTAL;

// ── Algo filter ───────────────────────────────────────────────────────────────
$algo_from_query_param = Yii::$app->YiimpUtils->get_algo_param();
if ($algo_from_query_param !== '') {
    if ($algo_from_query_param !== 'all') {
        $r_algo = array_map('trim', explode(',', $algo_from_query_param));
        $r_algo = preg_replace('/[^A-Za-z0-9\-]/', '', $r_algo);
    }
} else {
    $algo_from_user_pref = Yii::$app->session->get('yaamp-algo');
    if ($algo_from_user_pref !== 'all') {
        $r_algo = [$algo_from_user_pref];
    }
}

$count = (int) (Yii::$app->request->get('count') ?: 50);
$algo_header = isset($r_algo) ? implode(',', $r_algo) : 'any algo';
$title = "Last {$count} Blocks ({$algo_header})";

// ── Query ─────────────────────────────────────────────────────────────────────
$coins_subquery = (new \yii\db\Query())->select(['id'])->from('coins')->where(['visible' => 1]);
if (!empty($r_algo)) {
    $coins_subquery->andWhere(['in', 'algo', $r_algo]);
}
$block_query = Blocks::find()
    ->where(['not in', 'category', ['stake', 'generated']])
    ->andWhere(['in', 'coin_id', $coins_subquery])
    ->with('coin')
    ->orderBy(['time' => SORT_DESC])
    ->limit($count);

$db_blocks = $block_query->all();

// The pool can mine several algos at once. If the currently selected algo has
// no blocks yet, still show the most recent blocks from the other algos so the
// "found blocks" panel is not misleadingly empty.
if (empty($db_blocks) && $algo_header !== 'all') {
    $all_coins = (new \yii\db\Query())->select(['id'])->from('coins')->where(['visible' => 1]);
    $db_blocks = Blocks::find()
        ->where(['not in', 'category', ['stake', 'generated']])
        ->andWhere(['in', 'coin_id', $all_coins])
        ->with('coin')
        ->orderBy(['time' => SORT_DESC])
        ->limit($count)
        ->all();
    $algo_header = 'all algos';
    $title = "Last {$count} Blocks ({$algo_header})";
}

// ── Header summary ────────────────────────────────────────────────────────────
$blockStats = ['confirmed' => 0, 'immature' => 0, 'new' => 0, 'orphan' => 0, 'shared' => 0, 'solo' => 0];
foreach ($db_blocks as $statBlock) {
    if ($statBlock->solo === '1') $blockStats['solo']++;
    elseif ($statBlock->solo === '0') $blockStats['shared']++;
    $cat = $statBlock->category;
    if ($cat === 'generate') $cat = 'confirmed';
    if (isset($blockStats[$cat])) $blockStats[$cat]++;
}
$latestAgo = !empty($db_blocks)
    ? strip_tags(Yii::$app->ConversionUtils->datetoa2($db_blocks[0]->time)) . ' ago'
    : '—';
$scopeText = ($algo_header === 'any algo') ? 'all algos' : $algo_header;
$renderedRows = 0;

// ── Badge helpers ─────────────────────────────────────────────────────────────
function statusBadge(string $category, int $confirmations, ?int $matureBlocks, ?int $blockTime, bool $isLegacy, bool $isTailwind): string
{
    if ($isLegacy) {
        switch ($category) {
            case 'orphan':   return '<span class="bf-status orphan" title="This block was orphaned">Orphan</span>';
            case 'immature':
                $eta = '';
                if ($blockTime && $matureBlocks) {
                    $t   = (int) ($matureBlocks - $confirmations) * $blockTime;
                    $eta = 'ETA: ' . sprintf('%dh %02dmn', $t / 3600, ($t / 60) % 60);
                }
                $count = $matureBlocks ? "{$confirmations}/{$matureBlocks}" : (string) $confirmations;
                $title = $eta !== '' ? $eta : "{$confirmations} confirmations";
                return "<span class='bf-status immature' title='" . Html::encode($title) . "'>Immature <em>{$count}</em></span>";
            case 'generate': return '<span class="bf-status confirmed" title="Block is confirmed">Confirmed</span>';
            case 'new':      return '<span class="bf-status new" title="Newly found block">New</span>';
        }
        return '';
    }
    if (!$isTailwind) {
        switch ($category) {
            case 'orphan':   return '<span class="badge bg-danger">Orphan</span>';
            case 'immature':
                $eta = '';
                if ($blockTime && $matureBlocks) {
                    $t   = (int) ($matureBlocks - $confirmations) * $blockTime;
                    $eta = 'ETA: ' . sprintf('%dh %02dmn', $t / 3600, ($t / 60) % 60);
                }
                $label = $matureBlocks ? "Immature ({$confirmations}/{$matureBlocks})" : "Immature ({$confirmations})";
                return "<span class='badge bg-warning text-dark' title='{$eta}'>{$label}</span>";
            case 'generate': return '<span class="badge bg-success">Confirmed</span>';
            case 'new':      return '<span class="badge" style="background:#ad4ef0">New</span>';
        }
        return '';
    }
    // Tailwind
    switch ($category) {
        case 'orphan':   return '<span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400">Orphan</span>';
        case 'immature':
            $eta = '';
            if ($blockTime && $matureBlocks) {
                $t   = (int) ($matureBlocks - $confirmations) * $blockTime;
                $eta = 'ETA: ' . sprintf('%dh %02dmn', $t / 3600, ($t / 60) % 60);
            }
            $label = $matureBlocks ? "Immature ({$confirmations}/{$matureBlocks})" : "Immature ({$confirmations})";
            return "<span class='inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400' title='{$eta}'>{$label}</span>";
        case 'generate': return '<span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-400">Confirmed</span>';
        case 'new':      return '<span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-400">New</span>';
    }
    return '';
}

function typeBadge(string $solo, bool $isLegacy, bool $isTailwind): string
{
    if ($isLegacy) {
        if ($solo === '1') return '<span class="bf-type solo" title="Found by a solo miner">Solo</span>';
        if ($solo === '0') return '<span class="bf-type shared" title="Found by shared mining">Shared</span>';
        return '';
    }
    if (!$isTailwind) {
        if ($solo === '1') return '<span class="badge bg-info text-dark">Solo</span>';
        if ($solo === '0') return '<span class="badge" style="background:#87d547">Shared</span>';
        return '';
    }
    if ($solo === '1') return '<span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-cyan-100 text-cyan-700 dark:bg-cyan-900/40 dark:text-cyan-400">Solo</span>';
    if ($solo === '0') return '<span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-lime-100 text-lime-700 dark:bg-lime-900/40 dark:text-lime-400">Shared</span>';
    return '';
}

// ── Card open ─────────────────────────────────────────────────────────────────
if ($isLegacy) {
    echo <<<CSS
<style>
#blocks-card > .main-left-title {
    display: flex; align-items: center; justify-content: space-between; gap: 10px;
    background: #f8fafc; border-bottom: 1px solid #e8eef6; padding: 9px 12px;
}
#blocks-card .bf-head-main { display: flex; align-items: baseline; gap: 9px; flex-wrap: wrap; min-width: 0; }
#blocks-card .bf-title { font-size: 13px; font-weight: 800; color: #26385a; }
#blocks-card .bf-sub { font-size: 10px; color: #8a94a6; font-weight: 500; }
#blocks-card .bf-chips { display: flex; gap: 5px; flex-wrap: wrap; }
#blocks-card .bf-chip {
    border-radius: 999px; padding: 2px 7px; font-size: 9px; font-weight: 700; white-space: nowrap;
    background: #f1f5f9; color: #64748b; border: 1px solid #e2e8f0;
}
#blocks-card .bf-chip.confirmed { background: #e8f7ee; color: #187d46; border-color: #ccebd8; }
#blocks-card .bf-chip.immature { background: #fff7e8; color: #a86400; border-color: #ffe2b8; }
#blocks-card .bf-chip.orphan { background: #fdecec; color: #c0392b; border-color: #f6d2d2; }
#blocks-card .main-left-inner { padding: 0; }
#found-table.bf-table {
    width: 100% !important; border-collapse: collapse; table-layout: fixed;
    font-size: 11px; color: #334155;
}
#found-table.bf-table th {
    background: #fff; padding: 7px 9px; text-align: right;
    font-size: 9px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase;
    color: #8a94a6; border-bottom: 1px solid #e8eef6; white-space: nowrap;
}
#found-table.bf-table th:first-child, #found-table.bf-table td:first-child { text-align: left; }
#found-table.bf-table td {
    padding: 8px 9px; border-bottom: 1px solid #eef2f7;
    text-align: right; vertical-align: middle;
}
#found-table.bf-table tbody tr:last-child td { border-bottom: 0; }
#found-table.bf-table tbody tr:hover { background: #f7faff; }
#found-table .bf-coin { display: flex; align-items: center; gap: 8px; min-width: 0; }
#found-table .bf-ico {
    position: relative; flex: 0 0 26px; height: 26px; border-radius: 6px; overflow: hidden;
    display: grid; place-items: center; color: #fff; font-size: 7px; font-weight: 800;
    background: linear-gradient(135deg, #4f79c8, #2c5aa0);
}
#found-table .bf-ico.firo { background: linear-gradient(135deg, #b06fd0, #7a3fa0); }
#found-table .bf-ico img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: contain; background: #fff; }
#found-table .bf-coin-main { min-width: 0; }
#found-table .bf-link { font-size: 11px; font-weight: 700; color: #26385a; text-decoration: none; }
#found-table .bf-link:hover { color: #2c5aa0; }
#found-table .bf-meta { display: block; font-size: 9px; color: #98a3b5; margin-top: 2px; white-space: nowrap; }
#found-table .bf-mono {
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-variant-numeric: tabular-nums;
}
#found-table .bf-val {
    display: block; font-size: 11px; font-weight: 700; color: #2b3d5b; white-space: nowrap;
}
#found-table .bf-cap { display: block; font-size: 9px; color: #98a3b5; margin-top: 1px; white-space: nowrap; }
#found-table .bf-segwit {
    display: inline-block; margin-left: 4px; border-radius: 3px; padding: 0 3px;
    font-size: 8px; font-weight: 700; line-height: 13px;
    color: #64748b; background: #f1f5f9; border: 1px solid #e2e8f0;
}
#found-table .bf-eff { display: flex; align-items: center; justify-content: center; gap: 6px; }
#found-table .bf-eff-bar { width: 46px; height: 5px; border-radius: 999px; background: #edf1f6; overflow: hidden; }
#found-table .bf-eff-bar i { display: block; height: 100%; border-radius: 999px; background: #2fae5a; }
#found-table .bf-eff.mid .bf-eff-bar i { background: #e0a020; }
#found-table .bf-eff.high .bf-eff-bar i { background: #d9534f; }
#found-table .bf-eff-num { font-size: 10px; font-weight: 700; color: #475569; min-width: 42px; text-align: right; }
#found-table .bf-eff.mid .bf-eff-num { color: #a86400; }
#found-table .bf-eff.high .bf-eff-num { color: #c0392b; }
#found-table .bf-status, #found-table .bf-type {
    display: inline-block; border-radius: 999px; padding: 2px 7px;
    font-size: 9px; font-weight: 700; line-height: 14px; white-space: nowrap;
}
#found-table .bf-status em { font-style: normal; opacity: .75; margin-left: 3px; }
#found-table .bf-status.confirmed { background: #e8f7ee; color: #187d46; border: 1px solid #ccebd8; }
#found-table .bf-status.immature { background: #fff7e8; color: #a86400; border: 1px solid #ffe2b8; }
#found-table .bf-status.new { background: #f3e8ff; color: #7e22ce; border: 1px solid #e9d5ff; }
#found-table .bf-status.orphan { background: #fdecec; color: #c0392b; border: 1px solid #f6d2d2; }
#found-table .bf-type.shared { background: #eef4ff; color: #2c5aa0; border: 1px solid #d7e5ff; }
#found-table .bf-type.solo { background: #e6fbfb; color: #0f766e; border: 1px solid #c7f0ee; }
#found-table .bf-type.rental { background: #f1f5f9; color: #64748b; border: 1px solid #e2e8f0; }
#found-table .bf-empty { text-align: center !important; padding: 22px 10px !important; color: #98a3b5; font-size: 11px; }
#blocks-card .bf-foot {
    display: flex; justify-content: space-between; gap: 10px; flex-wrap: wrap;
    padding: 7px 12px; background: #fcfdff; border-top: 1px solid #eef2f7;
    font-size: 9px; color: #98a3b5;
}
@media(max-width:820px){
    #found-table.bf-table { table-layout: auto; }
    #blocks-card .bf-chips { display: none; }
}
</style>
CSS;
    echo "<div id='blocks-card' class='main-left-box'>";
    echo "<div class='main-left-title'><span class='bf-head-main'><span class='bf-title'>Last {$count} Blocks</span>"
       . "<span class='bf-sub'>" . Html::encode($scopeText . ' · ' . count($db_blocks) . ' shown · newest ' . $latestAgo) . "</span></span>";
    echo "<span class='bf-chips'>";
    if ($blockStats['confirmed']) echo "<span class='bf-chip confirmed'>{$blockStats['confirmed']} confirmed</span>";
    if ($blockStats['immature']) echo "<span class='bf-chip immature'>{$blockStats['immature']} immature</span>";
    if ($blockStats['orphan']) echo "<span class='bf-chip orphan'>{$blockStats['orphan']} orphan</span>";
    echo "<span class='bf-chip'>auto · 60s</span></span></div>";
    echo "<div class='main-left-inner'><div style='overflow-x:auto'>";
    echo "<table id='found-table' class='bf-table'>";
    echo "<thead><tr><th style=\"width:30%\">Coin</th><th style=\"width:12%\">Reward</th><th style=\"width:13%\">Height</th>"
       . "<th style=\"width:13%\">Found</th><th style=\"width:14%\">Effort</th><th style=\"width:9%\">Type</th><th style=\"width:9%\">Status</th></tr></thead>";
    echo "<tbody>";
} elseif (!$isTailwind) {
    echo '<div class="card shadow-sm mb-3">';
    echo '<div class="card-header py-2 d-flex align-items-center gap-2">';
    echo '<i class="bi bi-boxes text-secondary"></i>';
    echo '<strong class="small">' . Html::encode($title) . '</strong>';
    echo '</div><div class="card-body p-0"><div class="overflow-auto">';
    echo '<table class="table table-sm table-bordered table-hover mb-0">';
    echo '<thead class="table-light"><tr>';
    foreach (['', 'Name', 'Amount', 'Difficulty', 'Block', 'Time', 'Effort', 'Type', 'Status'] as $h)
        echo '<th class="small' . ($h !== '' && $h !== 'Name' && $h !== 'Effort' && $h !== 'Type' && $h !== 'Status' ? ' text-end' : '') . '">' . Html::encode($h) . '</th>';
    echo '</tr></thead><tbody>';
} else {
    echo '<div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm overflow-hidden">';
    echo '<div class="px-4 py-2.5 border-b border-gray-200 dark:border-gray-700 flex items-center gap-2">';
    echo '<i data-lucide="layers" class="w-4 h-4 text-gray-400 shrink-0"></i>';
    echo '<span class="text-sm font-semibold text-gray-800 dark:text-gray-100">' . Html::encode($title) . '</span>';
    echo '</div><div class="overflow-x-auto">';
    echo '<table class="w-full text-xs">';
    echo '<thead><tr class="bg-gray-50 dark:bg-gray-700/50 text-gray-500 dark:text-gray-400 font-semibold uppercase tracking-wider border-b border-gray-200 dark:border-gray-700">';
    echo '<th class="px-3 py-2.5 w-8"></th>';
    echo '<th class="px-3 py-2.5 text-left">Name</th>';
    echo '<th class="px-3 py-2.5 text-right">Amount</th>';
    echo '<th class="px-3 py-2.5 text-right">Difficulty</th>';
    echo '<th class="px-3 py-2.5 text-right">Block</th>';
    echo '<th class="px-3 py-2.5 text-right">Time</th>';
    echo '<th class="px-3 py-2.5 text-center">Effort</th>';
    echo '<th class="px-3 py-2.5 text-center">Type</th>';
    echo '<th class="px-3 py-2.5 text-center">Status</th>';
    echo '</tr></thead><tbody>';
}

// ── Rows ──────────────────────────────────────────────────────────────────────
foreach ($db_blocks as $db_block) {
    // Rental rows can be hidden by settings; do not count them as rendered.
    if (!$db_block->coin_id && !$showrental) continue;
    $renderedRows++;

    $d = Yii::$app->ConversionUtils->datetoa2($db_block->time);

    // Rental row (no coin)
    if (!$db_block->coin_id) {
        $reward = Yii::$app->ConversionUtils->bitcoinvaluetoa($db_block->amount);

        if ($isLegacy) {
            echo '<tr>';
            echo "<td><div class='bf-coin'><span class='bf-ico'><img src='/images/btc.png' alt=''></span><span class='bf-coin-main'>";
            echo "<span class='bf-link'>Rental</span><span class='bf-meta'>" . Html::encode($db_block->algo) . "</span>";
            echo "</span></div></td>";
            echo "<td class='bf-mono'><span class='bf-val'>{$reward} BTC</span><span class='bf-cap'>payout</span></td>";
            echo "<td><span class='bf-val'>—</span><span class='bf-cap'>height</span></td>";
            echo "<td><span class='bf-val'>" . Html::encode(strip_tags($d)) . " ago</span><span class='bf-cap'>found</span></td>";
            echo "<td><span class='bf-eff'><span class='bf-eff-num'>—</span></span></td>";
            echo "<td><span class='bf-type rental'>Rental</span></td>";
            echo "<td><span class='bf-status confirmed'>Confirmed</span></td>";
            echo '</tr>';
        } elseif (!$isTailwind) {
            echo '<tr>';
            echo '<td><img src="/images/btc.png" width="16" style="object-fit:contain"></td>';
            echo '<td class="small"><b>Rental</b> <span class="text-muted font-monospace">(' . Html::encode($db_block->algo) . ')</span></td>';
            echo '<td class="text-end small font-monospace"><b>' . $reward . ' BTC</b></td>';
            echo '<td></td><td></td>';
            echo '<td class="text-end small text-muted">' . $d . ' ago</td>';
            echo '<td></td><td></td>';
            echo '<td class="text-center"><span class="badge bg-success">Confirmed</span></td></tr>';
        } else {
            echo '<tr class="hover:bg-gray-50/50 dark:hover:bg-gray-700/10 transition-colors border-b border-gray-100 dark:border-gray-700/50">';
            echo '<td class="px-3 py-2"><img src="/images/btc.png" width="16" height="16" class="rounded object-contain"></td>';
            echo '<td class="px-3 py-2 font-medium text-gray-800 dark:text-gray-200"><b>Rental</b> <span class="font-mono text-gray-400">(' . Html::encode($db_block->algo) . ')</span></td>';
            echo '<td class="px-3 py-2 text-right font-mono font-semibold text-gray-800 dark:text-gray-200">' . $reward . ' BTC</td>';
            echo '<td></td><td></td>';
            echo '<td class="px-3 py-2 text-right text-gray-400 dark:text-gray-500">' . $d . ' ago</td>';
            echo '<td></td><td></td>';
            echo '<td class="px-3 py-2 text-center"><span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-400">Confirmed</span></td></tr>';
        }
        continue;
    }

    $reward     = round($db_block->amount, 3);
    $coin       = $db_block->coin ?? Coins::findOne((int) $db_block->coin_id);
    $difficulty = Yii::$app->ConversionUtils->Itoa2($db_block->difficulty, 3);
    $height     = number_format($db_block->height, 0, '.', ' ');
    $link       = $coin->createExplorerLink($coin->name, ['hash' => $db_block->blockhash]);
    $effort     = $db_block->effort ? $db_block->effort . '%' : 'N/A';

    $statusBadge = statusBadge(
        $db_block->category,
        (int) $db_block->confirmations,
        $coin->mature_blocks,
        $coin->block_time,
        $isLegacy,
        $isTailwind
    );
    $typeBadge = typeBadge($db_block->solo, $isLegacy, $isTailwind);

    if ($isLegacy) {
        $initials = strtoupper(substr((string) $coin->symbol, 0, 3));
        $icoClass = str_contains((string) $db_block->algo, 'firo') ? 'bf-ico firo' : 'bf-ico';
        $img = !empty($coin->image)
            ? "<img src='" . Html::encode($coin->image) . "' alt='' onerror=\"this.remove()\">"
            : '';
        $segwit = $db_block->segwit
            ? "<span class='bf-segwit' title='Block signalled SegWit'>SegWit</span>"
            : '';

        $effortValue = (float) $db_block->effort;
        $effortClass = $effortValue > 150 ? 'high' : ($effortValue > 100 ? 'mid' : 'ok');
        $effortWidth = $effortValue > 0 ? min(100, max(2, $effortValue)) : 0;
        $effortBar = $effortWidth > 0
            ? "<span class='bf-eff-bar'><i style='width:" . round($effortWidth, 1) . "%'></i></span>"
            : '';

        echo '<tr>';
        echo "<td><div class='bf-coin'><span class='{$icoClass}'>{$initials}{$img}</span><span class='bf-coin-main'>";
        echo $link . $segwit;
        echo "<span class='bf-meta' title='Found difficulty " . Html::encode((string) $db_block->difficulty_user) . "'>"
           . Html::encode((string) $db_block->algo) . ' · diff ' . Html::encode($difficulty) . "</span>";
        echo "</span></div></td>";
        echo "<td class='bf-mono'><span class='bf-val'>" . $reward . ' ' . Html::encode((string) $coin->symbol_show) . "</span><span class='bf-cap'>reward</span></td>";
        echo "<td><span class='bf-val mono'>" . Html::encode($height) . "</span><span class='bf-cap'>height</span></td>";
        echo "<td><span class='bf-val'>" . Html::encode(strip_tags($d)) . " ago</span><span class='bf-cap'>found</span></td>";
        echo "<td><span class='bf-eff {$effortClass}' title='Effort vs expected difficulty'>{$effortBar}"
           . "<span class='bf-eff-num'>" . Html::encode($effort) . "</span></span></td>";
        echo "<td>{$typeBadge}</td>";
        echo "<td>{$statusBadge}</td>";
        echo '</tr>';
    } elseif (!$isTailwind) {
        $coinImg = !empty($coin->image) ? "<img src='" . Html::encode($coin->image) . "' width='16' style='object-fit:contain' onerror='this.style.display=\"none\"'>" : '';
        $segwit  = $db_block->segwit ? ' <span class="badge bg-secondary" style="font-size:.65em;">SegWit</span>' : '';
        echo '<tr>';
        echo '<td>' . $coinImg . '</td>';
        echo '<td class="small"><b>' . $link . '</b> <span class="text-muted font-monospace">(' . Html::encode($db_block->algo) . ')</span>' . $segwit . '</td>';
        echo '<td class="text-end small font-monospace fw-bold">' . $reward . ' ' . Html::encode($coin->symbol_show) . '</td>';
        echo '<td class="text-end small font-monospace" title="found ' . Html::encode($db_block->difficulty_user) . '">' . $difficulty . '</td>';
        echo '<td class="text-end small tabular-nums">' . $height . '</td>';
        echo '<td class="text-end small text-muted text-nowrap">' . $d . ' ago</td>';
        echo '<td class="text-center small">' . $effort . '</td>';
        echo '<td class="text-center">' . $typeBadge . '</td>';
        echo '<td class="text-center">' . $statusBadge . '</td>';
        echo '</tr>';
    } else {
        $coinImg = !empty($coin->image)
            ? "<img src='" . Html::encode($coin->image) . "' width='16' height='16' class='rounded object-contain' onerror='this.style.display=\"none\"'>"
            : '';
        $segwit = $db_block->segwit
            ? ' <span class="inline-flex items-center px-1 py-0.5 rounded text-xs bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400">SegWit</span>'
            : '';
        echo '<tr class="hover:bg-gray-50/50 dark:hover:bg-gray-700/10 transition-colors border-b border-gray-100 dark:border-gray-700/50">';
        echo '<td class="px-3 py-2">' . $coinImg . '</td>';
        echo '<td class="px-3 py-2 font-medium text-gray-800 dark:text-gray-200">' . $link . ' <span class="font-mono text-gray-400 dark:text-gray-500">(' . Html::encode($db_block->algo) . ')</span>' . $segwit . '</td>';
        echo '<td class="px-3 py-2 text-right font-mono tabular-nums font-semibold text-gray-800 dark:text-gray-200">' . $reward . ' ' . Html::encode($coin->symbol_show) . '</td>';
        echo '<td class="px-3 py-2 text-right font-mono tabular-nums text-gray-500 dark:text-gray-400" title="found ' . Html::encode($db_block->difficulty_user) . '">' . $difficulty . '</td>';
        echo '<td class="px-3 py-2 text-right tabular-nums text-gray-600 dark:text-gray-300">' . $height . '</td>';
        echo '<td class="px-3 py-2 text-right text-gray-400 dark:text-gray-500 whitespace-nowrap">' . $d . ' ago</td>';
        echo '<td class="px-3 py-2 text-center text-gray-500 dark:text-gray-400">' . $effort . '</td>';
        echo '<td class="px-3 py-2 text-center">' . $typeBadge . '</td>';
        echo '<td class="px-3 py-2 text-center">' . $statusBadge . '</td>';
        echo '</tr>';
    }
}

// ── Card close ────────────────────────────────────────────────────────────────
if ($isLegacy) {
    if (!$renderedRows) {
        echo "<tr><td colspan='7' class='bf-empty'>No Blocks Found</td></tr>";
    }
    echo '</tbody></table></div>';
    echo "<div class='bf-foot'><span>Effort compares share difficulty with network difficulty</span>"
       . "<span>Sorted by discovery time · auto refresh 60s</span></div>";
    echo '</div></div><br>';
} elseif (!$isTailwind) {
    if (!$renderedRows) {
        echo '<tr><td colspan="9" class="text-center py-4 text-muted">No Blocks Found</td></tr>';
    }
    echo '</tbody></table></div></div></div>';
} else {
    if (!$renderedRows) {
        echo '<tr><td colspan="9" class="px-3 py-4 text-center text-gray-400 dark:text-gray-500">No Blocks Found</td></tr>';
    }
    echo '</tbody></table></div></div>';
    Yii::$app->ViewUtils->JavascriptReady("
        if (typeof lucide !== 'undefined') lucide.createIcons();
    ");
}
