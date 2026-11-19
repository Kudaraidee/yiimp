<?php

use yii\helpers\Html;
use app\models\Coins;
use app\models\Blocks;

$isTailwind = Yii::$app->LayoutManager->isTailwind();
$isLegacy   = Yii::$app->LayoutManager->isLegacy();
$cache      = Yii::$app->cache;
$cachetime  = 30;

$algo = Yii::$app->session->get('yaamp-algo');
if ($algo === 'all') return;

$algoFactor = Yii::$app->YiimpUtils->algo_mBTC_factor($algo);
$algoUnit   = match (true) {
    $algoFactor == 0.001       => 'Kh',
    $algoFactor == 1000        => 'Gh',
    $algoFactor == 1000000     => 'Th',
    $algoFactor == 1000000000  => 'Ph',
    default                    => 'Mh',
};

$t1 = time() - 3600;
$t2 = time() - 86400;
$t3 = time() - 7 * 86400;
$t4 = time() - 30 * 86400;

// ── Blocks grouped by coin (last 30 days) ─────────────────────────────────────
$coins_subquery = (new \yii\db\Query())->select(['id'])->from('coins')
    ->where(['visible' => 1, 'enable' => 1, 'algo' => $algo]);

$list = Blocks::find()
    ->where(['not in', 'category', ['orphan', 'stake', 'generated']])
    ->andWhere(['>', 'time', $t4])
    ->andWhere(['in', 'coin_id', $coins_subquery])
    ->groupBy('coin_id')
    ->orderBy(['coin_id' => SORT_DESC])
    ->all();

// Batch-load coins
$listCoinIds = array_values(array_filter(array_map(fn($b) => $b->coin_id, $list)));
$coinMap     = $listCoinIds ? Coins::find()->where(['id' => $listCoinIds])->indexBy('id')->all() : [];

// ── Card open ─────────────────────────────────────────────────────────────────
$title = "Block Stats ({$algo})";
if ($isLegacy) {
    echo "<div class='main-left-box'>";
    echo "<div class='main-left-title'>" . Html::encode($title) . "</div>";
    echo "<div class='main-left-inner'>";
    echo '<style>td.symb,th.symb{width:50px;max-width:50px;text-align:right;}td.symb{font-size:.8em;}</style>';
    echo "<table class='dataGrid2'><thead><tr>";
    echo "<th></th><th>Name</th><th class='symb'>Symbol</th>";
    echo "<th align='right'>Last Hour</th><th align='right'>Last 24 Hours</th>";
    echo "<th align='right'>Last 7 Days</th><th align='right'>Last 30 Days</th>";
    echo "</tr></thead><tbody>";
} elseif (!$isTailwind) {
    echo '<div class="card shadow-sm mb-3">';
    echo '<div class="card-header py-2 d-flex align-items-center gap-2">';
    echo '<i class="bi bi-bar-chart-steps text-secondary"></i>';
    echo '<strong class="small">' . Html::encode($title) . '</strong>';
    echo '</div><div class="card-body p-0"><div class="overflow-auto">';
    echo '<table class="table table-sm table-bordered mb-0">';
    echo '<thead class="table-light"><tr>';
    echo '<th style="width:24px"></th><th class="small">Name</th><th class="small text-center">Sym</th>';
    echo '<th class="small text-end">Last Hour</th><th class="small text-end">Last 24h</th>';
    echo '<th class="small text-end">Last 7d</th><th class="small text-end">Last 30d</th>';
    echo '</tr></thead><tbody>';
} else {
    echo '<div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm overflow-hidden mb-4">';
    echo '<div class="px-4 py-2.5 border-b border-gray-200 dark:border-gray-700 flex items-center gap-2">';
    echo '<i data-lucide="bar-chart-2" class="w-4 h-4 text-gray-400 shrink-0"></i>';
    echo '<span class="text-sm font-semibold text-gray-800 dark:text-gray-100">' . Html::encode($title) . '</span>';
    echo '</div><div class="overflow-x-auto">';
    echo '<table class="w-full text-xs">';
    echo '<thead><tr class="bg-gray-50 dark:bg-gray-700/50 text-gray-500 dark:text-gray-400 font-semibold uppercase tracking-wider border-b border-gray-200 dark:border-gray-700">';
    echo '<th class="px-3 py-2.5 w-8"></th>';
    echo '<th class="px-3 py-2.5 text-left">Name</th>';
    echo '<th class="px-3 py-2.5 text-center">Sym</th>';
    echo '<th class="px-3 py-2.5 text-right">1h</th>';
    echo '<th class="px-3 py-2.5 text-right">24h</th>';
    echo '<th class="px-3 py-2.5 text-right">7d</th>';
    echo '<th class="px-3 py-2.5 text-right">30d</th>';
    echo '</tr></thead><tbody>';
}

// ── Per-coin rows ─────────────────────────────────────────────────────────────
$total1 = $total2 = $total3 = $total4 = 0;
$main_ids = [];

foreach ($list as $item) {
    $coin = $coinMap[$item->coin_id] ?? null;
    if (!$coin) continue;

    $id = $coin->id;
    $main_ids[$id] = $coin->symbol;
    if ($coin->symbol === 'BTC') continue;

    // Cached per-period block counts + BTC sums
    $periods = [
        1 => ['key' => "history_item1-{$id}-{$algo}", 'after' => $t1, 'select' => ['COUNT(id) as a', 'SUM(amount*price) as b']],
        2 => ['key' => "history_item2-{$id}-{$algo}", 'after' => $t2, 'select' => ['COUNT(id) as a', 'SUM(amount*price) as b']],
        3 => ['key' => "history_item3-{$id}-{$algo}", 'after' => $t3, 'select' => ['COUNT(id) as a', 'SUM(amount*price) as b', 'MIN(time) as t']],
        4 => ['key' => "history_item4-{$id}-{$algo}", 'after' => $t4, 'select' => ['COUNT(id) as a', 'SUM(amount*price) as b', 'MIN(time) as t']],
    ];

    $res = [];
    foreach ($periods as $p => $cfg) {
        $res[$p] = $cache->get($cfg['key']);
        if ($res[$p] === false) {
            $res[$p] = (new \yii\db\Query())
                ->select($cfg['select'])->from('blocks')
                ->where(['coin_id' => $id, 'algo' => $algo])
                ->andWhere(['not in', 'category', ['orphan', 'stake', 'generated']])
                ->andWhere(['>', 'time', $cfg['after']])
                ->one();
            $cache->set($cfg['key'], $res[$p], $cachetime);
        }
    }

    $total1 += (float) $res[1]['b'];
    $total2 += (float) $res[2]['b'];
    $total3 += (float) $res[3]['b'];
    $total4 += (float) $res[4]['b'];

    // Fallback to hashstats when blocks table is purged
    if ($res[3]['a'] == $res[2]['a'] || count($list) == 1) {
        if ($res[3]['t'] > ($t3 + 86400)) $res[3]['a'] = '-';
        $ckey3 = "history_item3-{$id}-{$algo}-btc";
        $val3  = $cache->get($ckey3);
        if ($val3 === false) {
            $val3 = (new \yii\db\Query())->select(['SUM(earnings) as b'])->from('hashstats')
                ->where(['algo' => $algo])->andWhere(['>', 'time', $t3])->scalar();
            $cache->set($ckey3, $val3, $cachetime);
        }
        $total3 = (float) $val3;
    }
    if ($res[4]['a'] == $res[3]['a'] || count($list) == 1) {
        $res[4]['a'] = '-';
        $ckey4 = "history_item4-{$id}-{$algo}-btc";
        $val4  = $cache->get($ckey4);
        if ($val4 === false) {
            $val4 = (new \yii\db\Query())->select(['SUM(earnings) as b'])->from('hashstats')
                ->where(['algo' => $algo])->andWhere(['>', 'time', $t4])->scalar();
            $cache->set($ckey4, $val4, $cachetime);
        }
        $total4 = (float) $val4;
    }

    $name = substr($coin->name, 0, 12);

    if ($isLegacy) {
        echo '<tr class="ssrow">';
        echo '<td width="18"><img width="16" src="' . Html::encode($coin->image) . '"></td>';
        echo '<td><b><a href="/site/block?id=' . $id . '">' . Html::encode($name) . '</a></b></td>';
        echo '<td class="symb">' . Html::encode($coin->symbol) . '</td>';
        echo '<td align="right" style="font-size:.9em;">' . $res[1]['a'] . '</td>';
        echo '<td align="right" style="font-size:.9em;">' . $res[2]['a'] . '</td>';
        echo '<td align="right" style="font-size:.9em;">' . $res[3]['a'] . '</td>';
        echo '<td align="right" style="font-size:.9em;">' . $res[4]['a'] . '</td>';
        echo '</tr>';
    } elseif (!$isTailwind) {
        $coinImg = !empty($coin->image) ? "<img src='" . Html::encode($coin->image) . "' width='16' style='object-fit:contain' onerror='this.style.display=\"none\"'>" : '';
        echo '<tr>';
        echo '<td>' . $coinImg . '</td>';
        echo '<td class="small"><a href="/site/block?id=' . $id . '" class="fw-bold">' . Html::encode($name) . '</a></td>';
        echo '<td class="text-center small font-monospace text-muted">' . Html::encode($coin->symbol) . '</td>';
        echo '<td class="text-end small tabular-nums">' . $res[1]['a'] . '</td>';
        echo '<td class="text-end small tabular-nums">' . $res[2]['a'] . '</td>';
        echo '<td class="text-end small tabular-nums">' . $res[3]['a'] . '</td>';
        echo '<td class="text-end small tabular-nums">' . $res[4]['a'] . '</td>';
        echo '</tr>';
    } else {
        $coinImg = !empty($coin->image)
            ? "<img src='" . Html::encode($coin->image) . "' width='16' height='16' class='rounded object-contain' onerror='this.style.display=\"none\"'>"
            : '';
        echo '<tr class="hover:bg-gray-50/50 dark:hover:bg-gray-700/10 transition-colors border-b border-gray-100 dark:border-gray-700/50">';
        echo '<td class="px-3 py-2">' . $coinImg . '</td>';
        echo '<td class="px-3 py-2 font-medium"><a href="/site/block?id=' . $id . '" class="text-gray-800 dark:text-gray-200 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">' . Html::encode($name) . '</a></td>';
        echo '<td class="px-3 py-2 text-center font-mono text-gray-400 dark:text-gray-500">' . Html::encode($coin->symbol) . '</td>';
        echo '<td class="px-3 py-2 text-right tabular-nums font-mono text-gray-600 dark:text-gray-300">' . $res[1]['a'] . '</td>';
        echo '<td class="px-3 py-2 text-right tabular-nums font-mono text-gray-600 dark:text-gray-300">' . $res[2]['a'] . '</td>';
        echo '<td class="px-3 py-2 text-right tabular-nums font-mono text-gray-600 dark:text-gray-300">' . $res[3]['a'] . '</td>';
        echo '<td class="px-3 py-2 text-right tabular-nums font-mono text-gray-600 dark:text-gray-300">' . $res[4]['a'] . '</td>';
        echo '</tr>';
    }
}

// ── "Others" — coins with no blocks but installed ─────────────────────────────
$others = (new \yii\db\Query())
    ->select(['id', 'image', 'symbol', 'name'])
    ->from('coins')
    ->where(['installed' => 1, 'enable' => 1, 'auto_ready' => 1, 'algo' => $algo])
    ->orderBy(['symbol' => SORT_ASC])
    ->all();

foreach ($others as $item) {
    if (array_key_exists($item['id'], $main_ids)) continue;
    if ($isLegacy) {
        echo '<tr class="ssrow">';
        echo '<td width="18px"><img width="16px" src="' . Html::encode($item['image']) . '"></td>';
        echo '<td><b><a href="/site/block?id=' . $item['id'] . '">' . Html::encode($item['name']) . '</a></b></td>';
        echo '<td class="symb">' . Html::encode($item['symbol']) . '</td>';
        echo '<td colspan="4"></td></tr>';
    } elseif (!$isTailwind) {
        $img = !empty($item['image']) ? "<img src='" . Html::encode($item['image']) . "' width='16' style='object-fit:contain' onerror='this.style.display=\"none\"'>" : '';
        echo '<tr><td>' . $img . '</td>';
        echo '<td class="small"><a href="/site/block?id=' . $item['id'] . '" class="fw-bold">' . Html::encode($item['name']) . '</a></td>';
        echo '<td class="text-center small font-monospace text-muted">' . Html::encode($item['symbol']) . '</td>';
        echo '<td colspan="4" class="text-muted small text-center">—</td></tr>';
    } else {
        $img = !empty($item['image']) ? "<img src='" . Html::encode($item['image']) . "' width='16' height='16' class='rounded object-contain' onerror='this.style.display=\"none\"'>" : '';
        echo '<tr class="opacity-50 border-b border-gray-100 dark:border-gray-700/50">';
        echo '<td class="px-3 py-1.5">' . $img . '</td>';
        echo '<td class="px-3 py-1.5 font-medium"><a href="/site/block?id=' . $item['id'] . '" class="text-gray-800 dark:text-gray-200 hover:text-indigo-600 dark:hover:text-indigo-400">' . Html::encode($item['name']) . '</a></td>';
        echo '<td class="px-3 py-1.5 text-center font-mono text-gray-400 dark:text-gray-500">' . Html::encode($item['symbol']) . '</td>';
        echo '<td colspan="4" class="px-3 py-1.5 text-center text-gray-300 dark:text-gray-600">—</td></tr>';
    }
}

// ── Hashrate & profitability calculations ─────────────────────────────────────
$hrPeriods = [
    1 => ['key' => "history_hashrate1-{$algo}", 'after' => $t1],
    2 => ['key' => "history_hashrate2-{$algo}", 'after' => $t2],
    3 => ['key' => "history_hashrate3-{$algo}", 'after' => $t3],
    4 => ['key' => "history_hashrate4-{$algo}", 'after' => $t4],
];
$hr = [];
foreach ($hrPeriods as $p => $cfg) {
    $hr[$p] = $cache->get($cfg['key']);
    if ($hr[$p] === false) {
        $hr[$p] = (new \yii\db\Query())->select(['AVG(hashrate)'])->from('hashrate')
            ->where(['algo' => $algo])->andWhere(['>', 'time', $cfg['after']])->scalar();
        $cache->set($cfg['key'], $hr[$p], $cachetime);
    }
    $hr[$p] = max((float) $hr[$p], 1);
}

$conv = Yii::$app->ConversionUtils;
$btcday = [
    1 => $conv->mbitcoinvaluetoa($total1 / $hr[1] * 1000000 * 24 * 1000),
    2 => $conv->mbitcoinvaluetoa($total2 / $hr[2] * 1000000 * 1 * 1000),
    3 => $conv->mbitcoinvaluetoa($total3 / $hr[3] * 1000000 / 7 * 1000),
    4 => $conv->mbitcoinvaluetoa($total4 / $hr[4] * 1000000 / 30 * 1000),
];
$hrFmt = [
    1 => $conv->Itoa2($hr[1]) . 'h/s',
    2 => $conv->Itoa2($hr[2]) . 'h/s',
    3 => $conv->Itoa2($hr[3]) . 'h/s',
    4 => $conv->Itoa2($hr[4]) . 'h/s',
];
$totFmt = [
    1 => $conv->bitcoinvaluetoa($total1),
    2 => $conv->bitcoinvaluetoa($total2),
    3 => $conv->bitcoinvaluetoa($total3),
    4 => $conv->bitcoinvaluetoa($total4),
];

// ── Summary rows ──────────────────────────────────────────────────────────────
if ($isLegacy) {
    echo '<tr class="ssrow" style="border-top:2px solid #eee;">';
    echo '<td width="18px"><img width="16px" src="/images/btc.png"></td>';
    echo '<td colspan="2"><b>BTC Value</b></td>';
    foreach ([1, 2, 3, 4] as $p)
        echo '<td align="right" style="font-size:.9em;">' . $totFmt[$p] . '</td>';
    echo '</tr>';
    echo '<tr class="ssrow" style="border-top:2px solid #eee;">';
    echo '<td width="18px"></td><td colspan="2"><b>Avg Hashrate</b></td>';
    foreach ([1, 2, 3, 4] as $p)
        echo '<td align="right" style="font-size:.9em;">' . $hrFmt[$p] . '</td>';
    echo '</tr>';
    echo '<tr class="ssrow" style="border-top:2px solid #eee;">';
    echo '<td width="18px"></td><td colspan="2"><b>mBTC/' . $algoUnit . '/d</b></td>';
    foreach ([1, 2, 3, 4] as $p)
        echo '<td align="right" style="font-size:.9em;">' . $btcday[$p] . '</td>';
    echo '</tr>';
    echo '</tbody></table></div></div><br>';
} elseif (!$isTailwind) {
    foreach ([['BTC Value', $totFmt, 'text-warning'], ['Avg Hashrate', $hrFmt, ''], ['mBTC/' . $algoUnit . '/d', $btcday, 'text-success fw-bold']] as [$label, $values, $cls]) {
        echo '<tr class="table-secondary">';
        echo '<td></td><td class="small fw-bold" colspan="2">' . Html::encode($label) . '</td>';
        foreach ([1, 2, 3, 4] as $p)
            echo '<td class="text-end small font-monospace ' . $cls . '">' . $values[$p] . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table></div></div></div>';
} else {
    $summaryRows = [
        ['BTC Value',             $totFmt,  'text-amber-600 dark:text-amber-400'],
        ['Avg Hashrate',          $hrFmt,   'text-gray-500 dark:text-gray-400'],
        ['mBTC/' . $algoUnit . '/d', $btcday, 'text-indigo-600 dark:text-indigo-400 font-bold'],
    ];
    foreach ($summaryRows as [$label, $values, $cls]) {
        echo '<tr class="bg-gray-50/80 dark:bg-gray-700/30 border-t-2 border-gray-200 dark:border-gray-600">';
        echo '<td class="px-3 py-2"></td>';
        echo '<td class="px-3 py-2 font-semibold text-gray-700 dark:text-gray-300 text-xs" colspan="2">' . Html::encode($label) . '</td>';
        foreach ([1, 2, 3, 4] as $p)
            echo '<td class="px-3 py-2 text-right font-mono tabular-nums ' . $cls . '">' . $values[$p] . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table></div></div>';
}
