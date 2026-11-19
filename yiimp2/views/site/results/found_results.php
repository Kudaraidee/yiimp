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
$db_blocks = Blocks::find()
    ->where(['in', 'category', ['stake', 'generated']])
    ->andWhere(['in', 'coin_id', $coins_subquery])
    ->with('coin')
    ->orderBy(['time' => SORT_DESC])
    ->limit($count)
    ->all();

// ── Badge helpers ─────────────────────────────────────────────────────────────
function statusBadge(string $category, int $confirmations, ?int $matureBlocks, ?int $blockTime, bool $isLegacy, bool $isTailwind): string
{
    if ($isLegacy) {
        switch ($category) {
            case 'orphan':   return '<span class="block orphan">Orphan</span>';
            case 'immature':
                $eta = '';
                if ($blockTime && $matureBlocks) {
                    $t   = (int) ($matureBlocks - $confirmations) * $blockTime;
                    $eta = 'ETA: ' . sprintf('%dh %02dmn', $t / 3600, ($t / 60) % 60);
                }
                $label = $matureBlocks ? "Immature ({$confirmations}/{$matureBlocks})" : "Immature ({$confirmations})";
                return "<span class='block immature' title='{$eta}'>{$label}</span>";
            case 'generate': return '<span class="block confirmed">Confirmed</span>';
            case 'new':      return '<span class="block new">New</span>';
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
        if ($solo === '1') return '<span class="solo" title="Block was found by solo miner">Solo</span>';
        if ($solo === '0') return '<span class="shared" title="Block was found shared">Shared</span>';
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
span.block{padding:2px;display:inline-block;text-align:center;min-width:75px;border-radius:3px;}
span.block.new{color:#fff;background:#ad4ef0;}
span.block.orphan{color:#fff;background:#d9534f;}
span.block.immature{color:#fff;background:#f0ad4e;}
span.block.confirmed{color:#fff;background:#5cb85c;}
span.shared{padding:2px;display:inline-block;text-align:center;min-width:15px;border-radius:3px;color:#fff;background:#87d547;}
span.solo{padding:2px;display:inline-block;text-align:center;min-width:15px;border-radius:3px;color:#fff;background:#48D8D8;}
.ssrow td.row{font-size:.8em;}td.right{text-align:right;}
</style>
CSS;
    echo "<div class='main-left-box'>";
    echo "<div class='main-left-title'>" . Html::encode($title) . "</div>";
    echo "<div class='main-left-inner'>";
    echo "<table class='dataGrid2'>";
    echo "<thead><tr><td></td><th>Name</th><th align='right'>Amount</th><th align='right'>Difficulty</th><th align='right'>Block</th><th align='right'>Time</th><th>Effort</th><th align='right'>Type</th><th align='right'>Status</th></tr></thead>";
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
    $d = Yii::$app->ConversionUtils->datetoa2($db_block->time);

    // Rental row (no coin)
    if (!$db_block->coin_id) {
        if (!$showrental) continue;
        $reward = Yii::$app->ConversionUtils->bitcoinvaluetoa($db_block->amount);

        if ($isLegacy) {
            echo '<tr class="ssrow">';
            echo '<td width="18px"><img width="16px" src="/images/btc.png"/></td>';
            echo '<td class="row"><b>Rental</b> (' . Html::encode($db_block->algo) . ')</td>';
            echo '<td class="row right"><b>' . $reward . ' BTC</b></td>';
            echo '<td class="row right"></td><td class="row right"></td>';
            echo '<td class="row right">' . $d . ' ago</td><td></td><td></td>';
            echo '<td class="row right"><span class="block confirmed">Confirmed</span></td></tr>';
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
        $flags = $db_block->segwit ? '&nbsp;<img src="/images/ui/segwit.png" height="8px" valign="center" title="segwit"/>' : '';
        echo '<tr class="ssrow">';
        echo '<td width="18px"><img width="16px" src="' . Html::encode($coin->image) . '"></td>';
        echo '<td class="row"><b class="row">' . $link . '</b> (' . Html::encode($db_block->algo) . ')' . $flags . '</td>';
        echo '<td class="row right"><b>' . $reward . ' ' . Html::encode($coin->symbol_show) . '</b></td>';
        echo '<td class="row right" title="found ' . Html::encode($db_block->difficulty_user) . '">' . $difficulty . '</td>';
        echo '<td class="row right">' . $height . '</td>';
        echo '<td class="row right">' . $d . ' ago</td>';
        echo '<td>' . $effort . '</td>';
        echo '<td class="row right">' . $typeBadge . '</td>';
        echo '<td class="row right">' . $statusBadge . '</td>';
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
    echo '</tbody></table></div></div><br>';
} elseif (!$isTailwind) {
    echo '</tbody></table></div></div></div>';
} else {
    echo '</tbody></table></div></div>';
    Yii::$app->ViewUtils->JavascriptReady("
        if (typeof lucide !== 'undefined') lucide.createIcons();
    ");
}
