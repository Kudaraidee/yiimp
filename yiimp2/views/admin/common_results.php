<?php

/** @var yii\web\View $this */

use yii\helpers\Html;
use app\models\Balances;
use app\models\Blocks;
use app\models\Coins;
use app\models\Markets;
use app\models\Mining;
use app\models\Orders;
use app\models\Stats;
use app\models\Stratums;
use app\models\Workers;

$isTailwind  = Yii::$app->LayoutManager->isTailwind();
$isLegacy    = Yii::$app->LayoutManager->isLegacy();
$conv        = Yii::$app->ConversionUtils;
$util        = Yii::$app->YiimpUtils;
$showrental  = (bool) YIIMP_RENTAL;

// ── Mining USD rate ───────────────────────────────────────────────────────────
$mining = Mining::find()->one() ?? new Mining(['usdbtc' => 0]);

// ── Build sorted algo list ────────────────────────────────────────────────────
$t48 = time() - 48 * 3600;
$algosSorted = [];
foreach ($util->get_algos() as $algo) {
    $price = Yii::$app->cache->get("current_price-{$algo}") ?: (new \yii\db\Query())
        ->select(['price'])->from('hashrate')->where(['algo' => $algo])
        ->andWhere(['>', 'time', $t48])->orderBy(['time' => SORT_DESC])->scalar();
    Yii::$app->cache->set("current_price-{$algo}", $price);
    $norm = $util->take_yiimp_fee($price * $util->get_algo_norm($algo), $algo);
    $algosSorted[] = [$norm, $algo];
}
usort($algosSorted, fn($a, $b) => $a[0] < $b[0]);

// ── Markets + balances ────────────────────────────────────────────────────────
$markets    = Balances::find()->orderBy('name')->all();
$altmarkets = (new \yii\db\Query())
    ->select(['B.name', 'SUM((markets.balance+markets.ontrade)*markets.price) AS balance'])
    ->from('balances as B')
    ->leftJoin('markets', "markets.name = B.name AND IFNULL(markets.deleted,0)=0 AND IFNULL(markets.base_coin,'BTC') IN ('','BTC')")
    ->groupBy('B.name')->orderBy('B.name')->all();

// ── Stale markets (sent but not traded > 2h) ──────────────────────────────────
$minsent = time() - 2 * 3600;
$staleMarkets = Markets::find()
    ->where(['<', 'lastsent', $minsent])
    ->andWhere(['>', 'lastsent', 'lasttraded'])
    ->orderBy('lastsent')->all();
$staleCoinIds = array_values(array_unique(array_filter(array_map(fn($m) => $m->coinid, $staleMarkets))));
$staleCoins   = $staleCoinIds ? Coins::find()->where(['id' => $staleCoinIds])->indexBy('id')->all() : [];

// ── Open orders ───────────────────────────────────────────────────────────────
$orders = Orders::find()->orderBy('(amount*bid) desc')->all();
$orderCoinIds = array_values(array_unique(array_filter(array_map(fn($o) => $o->coinid, $orders))));
$orderCoins   = $orderCoinIds ? Coins::find()->where(['id' => $orderCoinIds])->indexBy('id')->all() : [];

// ── BTC wallet summary ────────────────────────────────────────────────────────
$btc = Coins::find()->where(['symbol' => 'BTC'])->one();
if (!$btc) $btc = (object) ['id' => 0, 'balance' => 0];
$btcaddr = YIIMP_BTCADDRESS;
$topayRaw  = (new \yii\db\Query())->select(['sum(balance)'])->from('accounts')->where(['coinid' => $btc->id])->scalar();
$topay2    = $conv->bitcoinvaluetoa((new \yii\db\Query())->select(['sum(balance)'])->from('accounts')
    ->where(['coinid' => $btc->id])->andWhere(['>', 'balance', '0.001'])->scalar());
$renterRaw = (new \yii\db\Query())->select(['sum(balance)'])->from('renters')->scalar();
$stats     = Stats::find()->orderBy(['time' => SORT_DESC])->one();
$margin    = $conv->bitcoinvaluetoa($btc->balance - $topayRaw - $renterRaw);
$margin2   = $conv->bitcoinvaluetoa($btc->balance - $topayRaw - $renterRaw + ($stats ? $stats->balances + $stats->onsell + $stats->wallets : 0));
$topayFmt  = $conv->bitcoinvaluetoa($topayRaw);
$renterFmt = $conv->bitcoinvaluetoa($renterRaw);
$immature  = $conv->bitcoinvaluetoa((new \yii\db\Query())->select(['sum(mint*price)'])->from('coins')->where(['enable' => 1])->scalar());
$mints     = $immature;

// ── Recent blocks (batch-loaded) ──────────────────────────────────────────────
$db_blocks   = Blocks::find()->orderBy(['time' => SORT_DESC])->limit(50)->all();
$blockCoinIds = array_values(array_unique(array_filter(array_map(fn($b) => $b->coin_id, $db_blocks))));
$blockCoins   = $blockCoinIds ? Coins::find()->where(['id' => $blockCoinIds])->indexBy('id')->all() : [];

// ── Status badge helper ───────────────────────────────────────────────────────
$statusBadge = function (string $cat, int $confs) use ($isTailwind, $isLegacy): string {
    if ($isLegacy) {
        $map = [
            'orphan'    => 'padding:2px;color:#fff;background:#d9534f;',
            'immature'  => 'padding:2px;color:#fff;background:#f0ad4e;',
            'stake'     => 'padding:2px;color:#fff;background:#a0a0a0;',
            'generated' => 'padding:2px;color:#fff;background:#a0a0a0;',
            'generate'  => 'padding:2px;color:#fff;background:#5cb85c;',
            'new'       => 'padding:2px;color:#fff;background:#ad4ef0;',
        ];
        $labels = ['orphan' => 'Orphan', 'immature' => "Immature ({$confs})", 'stake' => "Stake ({$confs})",
                   'generated' => 'Confirmed', 'generate' => 'Confirmed', 'new' => 'New'];
        $s = $map[$cat] ?? 'padding:2px;background:#eee;';
        return '<span style="' . $s . '">' . ($labels[$cat] ?? ucfirst($cat)) . '</span>';
    }
    if (!$isTailwind) {
        $map = ['orphan' => 'bg-danger', 'immature' => 'bg-warning text-dark', 'stake' => 'bg-secondary',
                'generated' => 'bg-secondary', 'generate' => 'bg-success', 'new' => 'bg-purple'];
        $labels = ['orphan' => 'Orphan', 'immature' => "Immature ({$confs})", 'stake' => "Stake ({$confs})",
                   'generated' => 'Confirmed', 'generate' => 'Confirmed', 'new' => 'New'];
        return '<span class="badge ' . ($map[$cat] ?? 'bg-secondary') . '">' . ($labels[$cat] ?? ucfirst($cat)) . '</span>';
    }
    $map = ['orphan' => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400',
            'immature' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
            'stake' => 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400',
            'generated' => 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400',
            'generate' => 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-400',
            'new' => 'bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-400'];
    $labels = ['orphan' => 'Orphan', 'immature' => "Immature ({$confs})", 'stake' => "Stake ({$confs})",
               'generated' => 'Confirmed', 'generate' => 'Confirmed', 'new' => 'New'];
    return '<span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium ' . ($map[$cat] ?? 'bg-gray-100 text-gray-500') . '">' . ($labels[$cat] ?? ucfirst($cat)) . '</span>';
};

// ── Column layout open ────────────────────────────────────────────────────────
if ($isLegacy) {
    echo '<table width="100%"><tr><td valign="top" width="68%">';
} elseif (!$isTailwind) {
    echo '<div class="row gx-3"><div class="col-12 col-xl-8 d-flex flex-column gap-3">';
} else {
    echo '<div class="flex flex-col xl:flex-row gap-4"><div class="flex-1 min-w-0 flex flex-col gap-4">';
}

// ══════════════════════════════════════════════════════════════════════════════
// SECTION 1 — Algo status table
// ══════════════════════════════════════════════════════════════════════════════
$total_coins = $total_workers = 0;
$total_hashrate = $total_hashrate_bad = 0;

if ($isLegacy) {
    Yii::$app->ViewUtils->showTableSorter('maintable', '{
        tableClass:"dataGrid",
        widgets:["Storage","saveSort"],
        textExtraction:{
            1:function(node,table,cellIndex){return $(node).attr("data");},
            5:function(node,table,cellIndex){return $(node).attr("data");}
        },
        widgetOptions:{saveSort:true}
    }');
    echo '<thead><tr>';
    echo '<th data-sorter="text" align="left">Algo</th>';
    echo '<th data-sorter="numeric" align="left">Up</th>';
    echo '<th data-sorter="numeric" align="right" title="Currencies">C</th>';
    echo '<th data-sorter="numeric" align="right" title="Miners">M</th>';
    echo '<th data-sorter="currency" align="right">Fee</th>';
    echo '<th data-sorter="numeric" align="right">Rate</th>';
    if ($showrental) echo '<th data-sorter="currency" align="right">Rent</th>';
    echo '<th data-sorter="currency" align="right">Bad</th>';
    echo '<th data-sorter="currency" align="right">Now</th>';
    if ($showrental) echo '<th data-sorter="currency" align="right">Rent$</th>';
    echo '<th data-sorter="currency" align="right">Norm</th>';
    echo '<th data-sorter="currency" align="right">24E</th>';
    echo '<th data-sorter="currency" align="right">24A</th>';
    echo '</tr></thead><tbody>';
} else {
    $thCls = $isTailwind
        ? 'px-2 py-2.5 text-right text-gray-500 dark:text-gray-400 font-semibold uppercase tracking-wider text-xs'
        : 'small text-end';
    $thLeft = $isTailwind
        ? 'px-2 py-2.5 text-left text-gray-500 dark:text-gray-400 font-semibold uppercase tracking-wider text-xs'
        : 'small';

    if (!$isTailwind) {
        echo '<div class="card shadow-sm"><div class="card-header py-2 d-flex align-items-center gap-2">';
        echo '<i class="bi bi-bar-chart-line text-secondary"></i>';
        echo '<strong class="small">Algo Status</strong></div>';
        echo '<div class="card-body p-0"><div class="overflow-auto">';
        echo '<table id="maintable" class="table table-sm table-bordered mb-0">';
        Yii::$app->ViewUtils->JavascriptReady("
            \$('#maintable').tablesorter({
                textExtraction:{
                    1:function(node,table,c){return \$(node).attr('data');},
                    5:function(node,table,c){return \$(node).attr('data');}
                }
            });
        ");
    } else {
        echo '<div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm overflow-hidden">';
        echo '<div class="px-4 py-2.5 border-b border-gray-200 dark:border-gray-700 flex items-center gap-2">';
        echo '<i data-lucide="bar-chart-2" class="w-4 h-4 text-gray-400 shrink-0"></i>';
        echo '<span class="text-sm font-semibold text-gray-800 dark:text-gray-100">Algo Status</span>';
        echo '</div><div class="overflow-x-auto">';
        echo '<table id="maintable" class="w-full text-xs">';
    }

    $hdr = $isTailwind ? '<thead><tr class="bg-gray-50 dark:bg-gray-700/50 border-b border-gray-200 dark:border-gray-700">' : '<thead class="table-light"><tr>';
    echo $hdr;
    foreach (['Algo', 'Up', 'C', 'M', 'Fee%', 'Rate'] as $i => $h)
        echo '<th class="' . ($i <= 1 ? $thLeft : $thCls) . '" title="' . Html::encode($h) . '">' . Html::encode($h) . '</th>';
    if ($showrental) echo '<th class="' . $thCls . '">Rent</th>';
    echo '<th class="' . $thCls . '" title="Bad hashrate %">Bad</th>';
    echo '<th class="' . $thCls . '">Now</th>';
    if ($showrental) echo '<th class="' . $thCls . '">Rent$</th>';
    echo '<th class="' . $thCls . '">Norm</th>';
    echo '<th class="' . $thCls . '">24E</th>';
    echo '<th class="' . $thCls . '">24A</th>';
    echo '</tr></thead>';
    echo '<tbody' . ($isTailwind ? ' class="divide-y divide-gray-100 dark:divide-gray-700/50"' : '') . '>';
}

foreach ($algosSorted as [$normVal, $algo]) {
    $algoNorm  = $util->get_algo_norm($algo);
    $algoColor = $util->getAlgoColors($algo);
    $coins     = Coins::find()->where(['enable' => 1, 'auto_ready' => 1, 'algo' => $algo])->count();
    $count     = Workers::find()->where(['algo' => $algo])->count();
    $t1        = time() - 86400;

    $total1 = (new \yii\db\Query())->select(['sum(amount*price)'])->from('blocks')
        ->where(['algo' => $algo])->andWhere(['!=', 'category', 'orphan'])->andWhere(['>', 'time', $t1])->scalar();

    if (!$coins && !$total1) continue;

    $total_coins   += $coins;
    $total_workers += $count;

    $hashrate1 = (new \yii\db\Query())->select(['avg(hashrate)'])->from('hashrate')
        ->where(['algo' => $algo])->andWhere(['>', 'time', $t1])->scalar();

    $hashrate = Yii::$app->cache->get("current_hashrate-{$algo}") ?: (new \yii\db\Query())
        ->select(['hashrate'])->from('hashrate')->where(['algo' => $algo])->orderBy(['time' => SORT_DESC])->scalar();
    Yii::$app->cache->set("current_hashrate-{$algo}", $hashrate);

    $hashrate_bad = (new \yii\db\Query())->select(['hashrate_bad'])->from('hashrate')
        ->where(['algo' => $algo])->orderBy(['time' => SORT_DESC])->scalar();

    $bad = ($hashrate + $hashrate_bad) ? round($hashrate_bad * 100 / ($hashrate + $hashrate_bad), 1) : '';
    $total_hashrate     += $hashrate;
    $total_hashrate_bad += $hashrate_bad;

    $hashrate_sfx  = $hashrate ? $conv->Itoa2($hashrate) . 'h/s' : '-';
    $hashrate_b_sfx = $hashrate_bad ? $conv->Itoa2($hashrate_bad) . 'h/s' : '-';
    $hashrate_jobs = $util->rented_rate($algo);
    $hashrate_jobs_sfx = $hashrate_jobs > 0 ? $conv->Itoa2($hashrate_jobs) . 'h/s' : '';

    $priceRaw = (new \yii\db\Query())->select(['price'])->from('hashrate')
        ->where(['algo' => $algo])->orderBy(['time' => SORT_DESC])->scalar();
    $price = $priceRaw ? $conv->mbitcoinvaluetoa($priceRaw) : '-';

    $rentRaw = (new \yii\db\Query())->select(['rent'])->from('hashrate')
        ->where(['algo' => $algo])->orderBy(['time' => SORT_DESC])->scalar();
    $rent = $rentRaw ? $conv->mbitcoinvaluetoa($rentRaw) : '-';

    $normFmt = $conv->mbitcoinvaluetoa($normVal);

    $avgpriceRaw = (new \yii\db\Query())->select(['avg(price)'])->from('hashrate')
        ->where(['algo' => $algo])->andWhere(['>', 'time', $t1])->scalar();
    $avgprice = $avgpriceRaw ? $conv->mbitcoinvaluetoa($util->take_yiimp_fee($avgpriceRaw, $algo)) : '-';

    $algoUnitFactor = $util->algo_mBTC_factor($algo);
    $btcmhday1 = $hashrate1 != 0 ? $conv->mbitcoinvaluetoa($total1 / $hashrate1 * 1000000 * 1000 * $algoUnitFactor) : '-';

    $fees = $util->yiimp_fee($algo);

    $stratum = Stratums::find()->where(['algo' => $algo])->orderBy(['started' => SORT_DESC])->one();
    $isup    = $conv->Booltoa($stratum);
    $uptime  = $stratum ? $conv->datetoa2($stratum->started) : '';
    $ts      = $stratum ? $conv->datetoa2($stratum->started) : '';

    // Bad hashrate cell style
    if ($bad > 10) {
        $badCellLegacy  = 'align="right" style="font-size:.8em;color:#fff;background:#d9534f;"';
        $badCellAdminlte = 'text-end small table-danger';
        $badCellTailwind = 'px-2 py-1.5 text-right font-mono tabular-nums bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400';
    } elseif ($bad > 5) {
        $badCellLegacy   = 'align="right" style="font-size:.8em;color:#fff;background:#f0ad4e;"';
        $badCellAdminlte = 'text-end small table-warning';
        $badCellTailwind = 'px-2 py-1.5 text-right font-mono tabular-nums bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300';
    } else {
        $badCellLegacy   = 'align="right" style="font-size:.8em;"';
        $badCellAdminlte = 'text-end small';
        $badCellTailwind = 'px-2 py-1.5 text-right font-mono tabular-nums text-gray-500 dark:text-gray-400';
    }
    $badFmt = $bad !== '' ? $bad . '%' : '-';

    // 24A cell style
    $actualStyle = '';
    if ($btcmhday1 !== '-') {
        $bv = (float) $btcmhday1;
        $av = (float) $avgprice;
        if ($bv > $av * 1.1)        { $actualStyle = 'good'; }
        elseif ($bv * 1.3 < $av)    { $actualStyle = 'red'; }
        elseif ($bv * 1.2 < $av)    { $actualStyle = 'orange'; }
        elseif ($bv * 1.1 < $av)    { $actualStyle = 'yellow'; }
    }
    $actualStyles = [
        'legacy' => [
            'good'   => 'color:#fff;background:#5cb85c;',
            'red'    => 'color:#fff;background:#d9534f;',
            'orange' => 'color:#fff;background:#e4804e;',
            'yellow' => 'color:#fff;background:#f0ad4e;',
            ''       => '',
        ],
        'adminlte' => [
            'good' => 'table-success', 'red' => 'table-danger',
            'orange' => 'table-warning', 'yellow' => 'table-warning', '' => '',
        ],
        'tailwind' => [
            'good'   => 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-400',
            'red'    => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400',
            'orange' => 'bg-orange-100 text-orange-700 dark:bg-orange-900/40 dark:text-orange-400',
            'yellow' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
            ''       => '',
        ],
    ];

    if ($isLegacy) {
        $rowStyle = "style='background-color:{$algoColor};'";
        echo "<tr class='ssrow' {$rowStyle}>";
        echo '<td><b>' . Html::a($algo, '/site/gomining?algo=' . $algo) . '</b></td>';
        echo '<td align="left" style="font-size:.8em;" data="' . $ts . '">' . $isup . '&nbsp;' . $uptime . '</td>';
        echo '<td align="right" style="font-size:.8em;">' . ($coins ?: '-') . '</td>';
        echo '<td align="right" style="font-size:.8em;">' . ($count ?: '-') . '</td>';
        echo '<td align="right" style="font-size:.8em;">' . ($fees ? "$fees%" : '-') . '</td>';
        echo '<td align="right" style="font-size:.8em;" data="' . $hashrate . '">' . $hashrate_sfx . '</td>';
        if ($showrental) echo '<td align="right" style="font-size:.8em;">' . $hashrate_jobs_sfx . '</td>';
        echo '<td ' . $badCellLegacy . '>' . $badFmt . '</td>';
        echo ($normVal > 0 ? '<td align="right" style="font-size:.8em;" title="normalized ' . $normFmt . '">' : '<td align="right" style="font-size:.8em;">') . ($price === '0.0' ? '-' : $price) . '</td>';
        if ($showrental) echo '<td align="right" style="font-size:.8em;">' . $rent . '</td>';
        echo '<td align="right" style="font-size:.8em;">' . ($normVal == 0 ? '-' : $normFmt) . '</td>';
        echo '<td align="right" style="font-size:.8em;">' . ($avgprice === '0.0' ? '-' : $avgprice) . '</td>';
        $aStyle = $actualStyles['legacy'][$actualStyle] ?? '';
        echo '<td align="right" style="font-size:.8em;' . $aStyle . '">' . $btcmhday1 . '</td>';
        echo '</tr>';
    } elseif (!$isTailwind) {
        $rowBg = "style='background-color:{$algoColor}20;border-left:3px solid {$algoColor};'";
        $aClass = $actualStyles['adminlte'][$actualStyle] ?? '';
        echo "<tr {$rowBg}>";
        echo '<td class="small fw-bold"><a href="/site/gomining?algo=' . Html::encode($algo) . '" class="text-reset">' . Html::encode($algo) . '</a></td>';
        echo '<td class="small text-muted" data="' . $ts . '">' . $isup . '&nbsp;' . $uptime . '</td>';
        echo '<td class="text-end small">' . ($coins ?: '-') . '</td>';
        echo '<td class="text-end small">' . ($count ?: '-') . '</td>';
        echo '<td class="text-end small">' . ($fees ? "$fees%" : '-') . '</td>';
        echo '<td class="text-end small font-monospace" data="' . $hashrate . '">' . $hashrate_sfx . '</td>';
        if ($showrental) echo '<td class="text-end small font-monospace">' . $hashrate_jobs_sfx . '</td>';
        echo '<td class="' . $badCellAdminlte . '">' . $badFmt . '</td>';
        echo '<td class="text-end small font-monospace"' . ($normVal > 0 ? ' title="normalized ' . $normFmt . '"' : '') . '>' . ($price === '0.0' ? '-' : $price) . '</td>';
        if ($showrental) echo '<td class="text-end small font-monospace">' . $rent . '</td>';
        echo '<td class="text-end small font-monospace">' . ($normVal == 0 ? '-' : $normFmt) . '</td>';
        echo '<td class="text-end small font-monospace">' . ($avgprice === '0.0' ? '-' : $avgprice) . '</td>';
        echo '<td class="text-end small font-monospace ' . $aClass . '">' . $btcmhday1 . '</td>';
        echo '</tr>';
    } else {
        $rowBg = "style='background-color:{$algoColor}10;border-left:3px solid {$algoColor};'";
        $aClass = 'px-2 py-1.5 text-right font-mono tabular-nums ' . ($actualStyles['tailwind'][$actualStyle] ?? 'text-gray-600 dark:text-gray-300');
        $tdR = 'px-2 py-1.5 text-right font-mono tabular-nums text-gray-600 dark:text-gray-300';
        $tdM = 'px-2 py-1.5 text-right tabular-nums text-gray-500 dark:text-gray-400';
        echo "<tr class='hover:bg-gray-50/30 dark:hover:bg-gray-700/10 transition-colors' {$rowBg}>";
        echo '<td class="px-2 py-1.5 font-semibold"><a href="/site/gomining?algo=' . Html::encode($algo) . '" class="text-gray-800 dark:text-gray-200 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">' . Html::encode($algo) . '</a></td>';
        echo '<td class="px-2 py-1.5 text-xs text-gray-400 dark:text-gray-500 whitespace-nowrap" data="' . $ts . '">' . $isup . '&nbsp;' . $uptime . '</td>';
        echo '<td class="' . $tdM . '">' . ($coins ?: '-') . '</td>';
        echo '<td class="' . $tdM . '">' . ($count ?: '-') . '</td>';
        echo '<td class="' . $tdM . '">' . ($fees ? "$fees%" : '-') . '</td>';
        echo '<td class="' . $tdR . '" data="' . $hashrate . '">' . $hashrate_sfx . '</td>';
        if ($showrental) echo '<td class="' . $tdR . '">' . $hashrate_jobs_sfx . '</td>';
        echo '<td class="' . $badCellTailwind . '">' . $badFmt . '</td>';
        echo '<td class="' . $tdR . '"' . ($normVal > 0 ? ' title="normalized ' . $normFmt . '"' : '') . '>' . ($price === '0.0' ? '-' : $price) . '</td>';
        if ($showrental) echo '<td class="' . $tdR . '">' . $rent . '</td>';
        echo '<td class="' . $tdR . '">' . ($normVal == 0 ? '-' : $normFmt) . '</td>';
        echo '<td class="' . $tdR . '">' . ($avgprice === '0.0' ? '-' : $avgprice) . '</td>';
        echo '<td class="' . $aClass . '">' . $btcmhday1 . '</td>';
        echo '</tr>';
    }
}

// Footer totals row
$totalBad     = ($total_hashrate + $total_hashrate_bad) ? round($total_hashrate_bad * 100 / ($total_hashrate + $total_hashrate_bad), 1) : '';
$totalHashFmt = $conv->Itoa2($total_hashrate) . 'h/s';
$cols         = 6 + ($showrental ? 2 : 0) + 5;

if ($isLegacy) {
    echo '<tr class="ssfooter">';
    echo '<td colspan="2"></td>';
    echo '<td align="right" style="font-size:.8em;">' . $total_coins . '</td>';
    echo '<td align="right" style="font-size:.8em;">' . $total_workers . '</td>';
    echo '<td align="right" style="font-size:.8em;"></td>';
    echo '<td align="right" style="font-size:.8em;">' . $totalHashFmt . '</td>';
    if ($showrental) echo '<td></td>';
    echo '<td align="right" style="font-size:.8em;">' . ($totalBad ? $totalBad . '%' : '') . '</td>';
    echo '<td colspan="' . (4 + ($showrental ? 1 : 0)) . '"></td></tr>';
    echo '</tbody></table><br>';
} elseif (!$isTailwind) {
    echo '<tr class="table-secondary fw-bold">';
    echo '<td class="small" colspan="2">Totals</td>';
    echo '<td class="text-end small">' . $total_coins . '</td>';
    echo '<td class="text-end small">' . $total_workers . '</td>';
    echo '<td></td>';
    echo '<td class="text-end small font-monospace">' . $totalHashFmt . '</td>';
    if ($showrental) echo '<td></td>';
    echo '<td class="text-end small">' . ($totalBad ? $totalBad . '%' : '-') . '</td>';
    echo '<td colspan="' . (4 + ($showrental ? 1 : 0)) . '"></td></tr>';
    echo '</tbody></table></div></div></div>';
} else {
    echo '<tr class="bg-gray-50 dark:bg-gray-700/40 border-t-2 border-gray-300 dark:border-gray-600 font-semibold">';
    echo '<td class="px-2 py-2 text-xs text-gray-600 dark:text-gray-300" colspan="2">Totals</td>';
    echo '<td class="px-2 py-2 text-right text-gray-600 dark:text-gray-300">' . $total_coins . '</td>';
    echo '<td class="px-2 py-2 text-right text-gray-600 dark:text-gray-300">' . $total_workers . '</td>';
    echo '<td></td>';
    echo '<td class="px-2 py-2 text-right font-mono text-gray-600 dark:text-gray-300">' . $totalHashFmt . '</td>';
    if ($showrental) echo '<td></td>';
    echo '<td class="px-2 py-2 text-right text-gray-500 dark:text-gray-400">' . ($totalBad ? $totalBad . '%' : '-') . '</td>';
    echo '<td colspan="' . (4 + ($showrental ? 1 : 0)) . '"></td></tr>';
    echo '</tbody></table></div></div>';
}

// ══════════════════════════════════════════════════════════════════════════════
// SECTION 2 — Exchange balances
// ══════════════════════════════════════════════════════════════════════════════
$alt_balances  = [];
$sale_balances = [];
$total_balance = $total_onsell = $total_altcoins = $total_total = $total_usd = 0.0;

if ($isLegacy) {
    echo '<table class="dataGrid"><thead><tr><th></th>';
    foreach ($markets as $market)
        echo '<th align="right"><a href="/admin/runExchange?id=' . $market->id . '">' . Html::encode($market->name) . '</a></th>';
    echo '<th align="right">Total</th></tr></thead>';
} elseif (!$isTailwind) {
    echo '<div class="card shadow-sm"><div class="card-header py-2 d-flex align-items-center gap-2">';
    echo '<i class="bi bi-currency-bitcoin text-secondary"></i><strong class="small">Exchange Balances</strong></div>';
    echo '<div class="card-body p-0"><div class="overflow-auto"><table class="table table-sm table-bordered mb-0">';
    echo '<thead class="table-light"><tr><th class="small">Market</th>';
    foreach ($markets as $market)
        echo '<th class="text-end small"><a href="/admin/runExchange?id=' . $market->id . '">' . Html::encode($market->name) . '</a></th>';
    echo '<th class="text-end small">Total</th></tr></thead>';
} else {
    echo '<div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm overflow-hidden">';
    echo '<div class="px-4 py-2.5 border-b border-gray-200 dark:border-gray-700 flex items-center gap-2">';
    echo '<i data-lucide="coins" class="w-4 h-4 text-gray-400 shrink-0"></i>';
    echo '<span class="text-sm font-semibold text-gray-800 dark:text-gray-100">Exchange Balances</span></div>';
    echo '<div class="overflow-x-auto"><table class="w-full text-xs">';
    echo '<thead><tr class="bg-gray-50 dark:bg-gray-700/50 border-b border-gray-200 dark:border-gray-700 text-gray-500 dark:text-gray-400 font-semibold uppercase tracking-wider">';
    echo '<th class="px-3 py-2.5 text-left">Market</th>';
    foreach ($markets as $market)
        echo '<th class="px-3 py-2.5 text-right"><a href="/admin/runExchange?id=' . $market->id . '" class="text-indigo-600 dark:text-indigo-400 hover:underline">' . Html::encode($market->name) . '</a></th>';
    echo '<th class="px-3 py-2.5 text-right">Total</th></tr></thead>';
}

// BTC row
$fn_cell_btc = function (float $val, string $label) use ($isLegacy, $isTailwind, $conv): string {
    $f = $conv->bitcoinvaluetoa($val);
    if ($val > 0.25) $c = ['color:#fff;background:#5cb85c;', 'table-success', 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-400'];
    elseif ($val > 0.20) $c = ['color:#fff;background:#f0ad4e;', 'table-warning', 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300'];
    else $c = ['', '', ''];
    if ($isLegacy) return $val == 0 ? '<td align="right">-</td>' : '<td align="right" style="' . $c[0] . '">' . $f . '</td>';
    if (!$isTailwind) return $val == 0 ? '<td class="text-end small">-</td>' : '<td class="text-end small font-monospace ' . $c[1] . '">' . $f . '</td>';
    return $val == 0 ? '<td class="px-3 py-1.5 text-right text-gray-300 dark:text-gray-600">-</td>'
        : '<td class="px-3 py-1.5 text-right font-mono tabular-nums ' . $c[2] . '">' . $f . '</td>';
};

echo $isLegacy ? '<tr class="ssrow"><td>BTC</td>' : ($isTailwind
    ? '<tbody class="divide-y divide-gray-100 dark:divide-gray-700/50"><tr class="hover:bg-gray-50/30 dark:hover:bg-gray-700/10"><td class="px-3 py-1.5 font-medium text-gray-700 dark:text-gray-300">BTC</td>'
    : '<tbody><tr><td class="small fw-bold">BTC</td>');

foreach ($markets as $market) {
    echo $fn_cell_btc((float) $market->balance, $market->name);
    $total_balance += $market->balance;
}
$tbFmt = $conv->bitcoinvaluetoa($total_balance);
if ($isLegacy) echo '<td align="right" style="color:#fff;background:#eaa228;">' . $tbFmt . '</td></tr>';
elseif (!$isTailwind) echo '<td class="text-end small font-monospace fw-bold table-warning">' . $tbFmt . '</td></tr>';
else echo '<td class="px-3 py-1.5 text-right font-mono font-bold text-amber-700 dark:text-amber-400">' . $tbFmt . '</td></tr>';

// Orders row
echo $isLegacy ? '<tr class="ssrow"><td>orders</td>' : ($isTailwind
    ? '<tr class="hover:bg-gray-50/30 dark:hover:bg-gray-700/10"><td class="px-3 py-1.5 font-medium text-gray-700 dark:text-gray-300">Orders</td>'
    : '<tr><td class="small fw-bold">Orders</td>');

if (YIIMP_ALLOW_EXCHANGE) {
    foreach ($markets as $market) {
        $onsell_db = (new \yii\db\Query())->select(['sum(amount*bid)'])->from('orders')->where(['market' => $market->name])->scalar();
        $onsell    = (float) $onsell_db;
        $sale_balances[$market->name] = $onsell;
        $f = $conv->bitcoinvaluetoa($onsell);
        if ($isLegacy) {
            if ($onsell > 0.2) echo '<td align="right" style="color:#fff;background:#d9534f;">' . $f . '</td>';
            elseif ($onsell > 0.1) echo '<td align="right" style="color:#fff;background:#f0ad4e;">' . $f . '</td>';
            elseif ($onsell == 0) echo '<td align="right">-</td>';
            else echo '<td align="right">' . $f . '</td>';
        } elseif (!$isTailwind) {
            if ($onsell > 0.2) echo '<td class="text-end small font-monospace table-danger">' . $f . '</td>';
            elseif ($onsell > 0.1) echo '<td class="text-end small font-monospace table-warning">' . $f . '</td>';
            elseif ($onsell == 0) echo '<td class="text-end small text-muted">-</td>';
            else echo '<td class="text-end small font-monospace">' . $f . '</td>';
        } else {
            if ($onsell > 0.2) echo '<td class="px-3 py-1.5 text-right font-mono tabular-nums bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400">' . $f . '</td>';
            elseif ($onsell > 0.1) echo '<td class="px-3 py-1.5 text-right font-mono tabular-nums bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">' . $f . '</td>';
            elseif ($onsell == 0) echo '<td class="px-3 py-1.5 text-right text-gray-300 dark:text-gray-600">-</td>';
            else echo '<td class="px-3 py-1.5 text-right font-mono tabular-nums text-gray-600 dark:text-gray-300">' . $f . '</td>';
        }
        $total_onsell += $onsell;
    }
} else {
    $ontrade = (new \yii\db\Query())->select(['name', 'onsell'])->from('balances')->orderBy('name')->all();
    foreach ($ontrade as $row) {
        $onsell = (float) $conv->bitcoinvaluetoa($row['onsell']);
        $sale_balances[$row['name']] = $onsell;
        $f = $onsell == 0 ? '-' : $onsell;
        if ($isLegacy) echo '<td align="right">' . $f . '</td>';
        elseif (!$isTailwind) echo '<td class="text-end small font-monospace">' . $f . '</td>';
        else echo '<td class="px-3 py-1.5 text-right font-mono tabular-nums text-gray-600 dark:text-gray-300">' . $f . '</td>';
        $total_onsell += (float) $row['onsell'];
    }
}
$toFmt = $conv->bitcoinvaluetoa($total_onsell);
if ($isLegacy) echo '<td align="right">' . $toFmt . '</td></tr>';
elseif (!$isTailwind) echo '<td class="text-end small font-monospace">' . $toFmt . '</td></tr>';
else echo '<td class="px-3 py-1.5 text-right font-mono tabular-nums text-gray-600 dark:text-gray-300">' . $toFmt . '</td></tr>';

// Alt-coins row
echo $isLegacy ? '<tr class="ssrow"><td>other</td>' : ($isTailwind
    ? '<tr class="hover:bg-gray-50/30 dark:hover:bg-gray-700/10"><td class="px-3 py-1.5 font-medium text-gray-700 dark:text-gray-300">Alt</td>'
    : '<tr><td class="small fw-bold">Alt</td>');

foreach ($altmarkets as $row) {
    $exchange = $row['name'];
    if ((float) $row['balance'] == 0) {
        if ($isLegacy) echo '<td align="right">-</td>';
        elseif (!$isTailwind) echo '<td class="text-end small">-</td>';
        else echo '<td class="px-3 py-1.5 text-right text-gray-300 dark:text-gray-600">-</td>';
    } else {
        $balance = (new \yii\db\Query())->select(['SUM((markets.balance+markets.ontrade)*markets.price)'])
            ->from('markets')->innerJoin('coins', 'coins.id = markets.coinid')
            ->where(['IFNULL(markets.deleted,0)' => 0, 'markets.name' => $exchange])
            ->andWhere(["INSTR(coins.symbol,'-')" => 0])->scalar();
        $bFmt = $conv->bitcoinvaluetoa($balance);
        if ($isLegacy) echo '<td align="right"><a href="/admin/balances?exch=' . Html::encode($exchange) . '">' . $bFmt . '</a></td>';
        elseif (!$isTailwind) echo '<td class="text-end small font-monospace"><a href="/admin/balances?exch=' . Html::encode($exchange) . '">' . $bFmt . '</a></td>';
        else echo '<td class="px-3 py-1.5 text-right font-mono tabular-nums text-gray-600 dark:text-gray-300"><a href="/admin/balances?exch=' . Html::encode($exchange) . '" class="text-indigo-600 dark:text-indigo-400 hover:underline">' . $bFmt . '</a></td>';
        $alt_balances[$exchange] = $balance;
        $total_altcoins += $balance;
    }
}
$taFmt = $conv->bitcoinvaluetoa($total_altcoins);
if ($isLegacy) echo '<td align="right">' . $taFmt . '</td></tr>';
elseif (!$isTailwind) echo '<td class="text-end small font-monospace">' . $taFmt . '</td></tr>';
else echo '<td class="px-3 py-1.5 text-right font-mono tabular-nums text-gray-600 dark:text-gray-300">' . $taFmt . '</td></tr>';

// Total + USD footer
$tdR = $isLegacy ? '' : ($isTailwind ? 'px-3 py-1.5 text-right font-mono tabular-nums font-semibold text-gray-700 dark:text-gray-200' : 'text-end small font-monospace fw-bold');

echo $isLegacy ? '<tfoot><tr class="ssrow"><td><b>Total</b></td>' : ($isTailwind
    ? '<tr class="bg-gray-50 dark:bg-gray-700/30 border-t border-gray-200 dark:border-gray-600"><td class="px-3 py-1.5 font-bold text-gray-700 dark:text-gray-300">Total</td>'
    : '<tr class="table-light fw-bold"><td class="small fw-bold">Total</td>');

foreach ($markets as $market) {
    $total = $market->balance + ($alt_balances[$market->name] ?? 0) + ($sale_balances[$market->name] ?? 0);
    $f     = $total > 0 ? $conv->bitcoinvaluetoa($total) : '-';
    if ($isLegacy) echo '<td align="right">' . $f . '</td>';
    elseif (!$isTailwind) echo '<td class="text-end small font-monospace fw-bold">' . $f . '</td>';
    else echo '<td class="' . $tdR . '">' . $f . '</td>';
    $total_total += $total;
}
$ttFmt = $conv->bitcoinvaluetoa($total_total);
if ($isLegacy) echo '<td align="right"><b>' . $ttFmt . '</b></td></tr>';
elseif (!$isTailwind) echo '<td class="text-end small font-monospace fw-bold">' . $ttFmt . '</td></tr>';
else echo '<td class="' . $tdR . '">' . $ttFmt . '</td></tr>';

// USD row
echo $isLegacy ? '<tr class="ssrow"><td>USD</td>' : ($isTailwind
    ? '<tr class="bg-gray-50/50 dark:bg-gray-700/20"><td class="px-3 py-1.5 font-medium text-gray-500 dark:text-gray-400">USD</td>'
    : '<tr class="table-secondary"><td class="small">USD</td>');

foreach ($markets as $market) {
    $total = $market->balance + ($alt_balances[$market->name] ?? 0) + ($sale_balances[$market->name] ?? 0);
    $usd   = $total * $mining->usdbtc;
    $f     = $usd > 0 ? round($usd, 2) : '-';
    if ($isLegacy) echo '<td align="right">' . $f . '</td>';
    elseif (!$isTailwind) echo '<td class="text-end small">' . $f . '</td>';
    else echo '<td class="px-3 py-1.5 text-right text-gray-500 dark:text-gray-400">' . $f . '</td>';
    $total_usd += $usd;
}
$usdFmt = round($total_usd, 2) . ' $';
if ($isLegacy) echo '<td align="right">' . $usdFmt . '</td></tr></tfoot></table><br>';
elseif (!$isTailwind) echo '<td class="text-end small">' . $usdFmt . '</td></tr></tbody></table></div></div></div>';
else echo '<td class="px-3 py-1.5 text-right text-gray-500 dark:text-gray-400">' . $usdFmt . '</td></tr></tbody></table></div></div>';

// ══════════════════════════════════════════════════════════════════════════════
// SECTION 3 — Stale markets
// ══════════════════════════════════════════════════════════════════════════════
if (!empty($staleMarkets)) {
    if ($isLegacy) {
        echo '<table class="dataGrid"><thead><tr>';
        echo '<th width="20px"></th><th>Name</th><th>Exchange</th><th>Sent</th><th>Traded</th><th></th>';
        echo '</tr></thead><tbody>';
    } elseif (!$isTailwind) {
        echo '<div class="card shadow-sm"><div class="card-header py-2 d-flex align-items-center gap-2">';
        echo '<i class="bi bi-exclamation-triangle text-warning"></i><strong class="small">Stale Markets</strong>';
        echo '<span class="badge bg-warning text-dark ms-1">' . count($staleMarkets) . '</span></div>';
        echo '<div class="card-body p-0"><div class="overflow-auto"><table class="table table-sm table-bordered mb-0">';
        echo '<thead class="table-light"><tr><th style="width:24px"></th><th class="small">Name</th>';
        echo '<th class="small">Exchange</th><th class="small">Sent</th><th class="small">Traded</th><th class="small">Action</th></tr></thead><tbody>';
    } else {
        echo '<div class="rounded-xl border border-amber-200 dark:border-amber-800/50 bg-white dark:bg-gray-800 shadow-sm overflow-hidden">';
        echo '<div class="px-4 py-2.5 border-b border-amber-200 dark:border-amber-800/50 flex items-center gap-2">';
        echo '<i data-lucide="alert-triangle" class="w-4 h-4 text-amber-500 shrink-0"></i>';
        echo '<span class="text-sm font-semibold text-gray-800 dark:text-gray-100">Stale Markets</span>';
        echo '<span class="ml-auto px-2 py-0.5 text-xs rounded-full bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">' . count($staleMarkets) . '</span></div>';
        echo '<div class="overflow-x-auto"><table class="w-full text-xs">';
        echo '<thead><tr class="bg-gray-50 dark:bg-gray-700/50 border-b border-gray-200 dark:border-gray-700 text-gray-500 dark:text-gray-400 font-semibold uppercase tracking-wider">';
        echo '<th class="px-3 py-2.5 w-8"></th><th class="px-3 py-2.5 text-left">Coin</th>';
        echo '<th class="px-3 py-2.5 text-left">Exchange</th><th class="px-3 py-2.5 text-right">Sent</th>';
        echo '<th class="px-3 py-2.5 text-right">Traded</th><th class="px-3 py-2.5 text-center">Action</th>';
        echo '</tr></thead><tbody>';
    }

    foreach ($staleMarkets as $market) {
        $coin      = $staleCoins[$market->coinid] ?? null;
        if (!$coin) continue;
        $algoColor = $util->getAlgoColors($coin->algo);
        $marketUrl = $util->getMarketUrl($coin, $market->name);
        $sent      = $conv->datetoa2($market->lastsent);
        $traded    = $conv->datetoa2($market->lasttraded);
        $coinImg   = !empty($coin->image) ? "<img width='16' src='" . Html::encode($coin->image) . "'>" : '';

        if ($isLegacy) {
            echo '<tr style="background-color:' . $algoColor . ';">';
            echo '<td>' . $coinImg . '</td>';
            echo '<td><b><a href="/admin/coinwallet?id=' . $coin->id . '">' . Html::encode($coin->name) . ' (' . Html::encode($coin->symbol) . ')</a></b></td>';
            echo '<td><b><a href="' . Html::encode($marketUrl) . '" target="_blank">' . Html::encode($market->name) . '</a></b></td>';
            echo '<td>' . $sent . ' ago</td><td>' . $traded . ' ago</td>';
            echo '<td><a href="/admin/clearmarket?id=' . $market->id . '">clear</a></td></tr>';
        } elseif (!$isTailwind) {
            echo '<tr style="background-color:' . $algoColor . '20;border-left:3px solid ' . $algoColor . ';">';
            echo '<td>' . $coinImg . '</td>';
            echo '<td class="small"><b><a href="/admin/coinwallet?id=' . $coin->id . '">' . Html::encode($coin->name) . '</a></b> <span class="text-muted">(' . Html::encode($coin->symbol) . ')</span></td>';
            echo '<td class="small"><a href="' . Html::encode($marketUrl) . '" target="_blank">' . Html::encode($market->name) . '</a></td>';
            echo '<td class="text-end small text-muted">' . $sent . ' ago</td>';
            echo '<td class="text-end small text-muted">' . $traded . ' ago</td>';
            echo '<td class="text-center small"><a href="/admin/clearmarket?id=' . $market->id . '" class="text-danger small">clear</a></td></tr>';
        } else {
            echo '<tr class="hover:bg-gray-50/50 dark:hover:bg-gray-700/10 transition-colors border-b border-gray-100 dark:border-gray-700/50" style="border-left:3px solid ' . $algoColor . ';">';
            echo '<td class="px-3 py-1.5">' . $coinImg . '</td>';
            echo '<td class="px-3 py-1.5 font-medium text-gray-800 dark:text-gray-200"><a href="/admin/coinwallet?id=' . $coin->id . '" class="hover:text-indigo-600 dark:hover:text-indigo-400">' . Html::encode($coin->name) . '</a> <span class="text-gray-400 font-mono">(' . Html::encode($coin->symbol) . ')</span></td>';
            echo '<td class="px-3 py-1.5"><a href="' . Html::encode($marketUrl) . '" target="_blank" class="text-indigo-600 dark:text-indigo-400 hover:underline">' . Html::encode($market->name) . '</a></td>';
            echo '<td class="px-3 py-1.5 text-right text-gray-400 dark:text-gray-500">' . $sent . ' ago</td>';
            echo '<td class="px-3 py-1.5 text-right text-gray-400 dark:text-gray-500">' . $traded . ' ago</td>';
            echo '<td class="px-3 py-1.5 text-center"><a href="/admin/clearmarket?id=' . $market->id . '" class="text-red-500 hover:text-red-700 dark:text-red-400">clear</a></td></tr>';
        }
    }

    if ($isLegacy) echo '</tbody></table><br>';
    elseif (!$isTailwind) echo '</tbody></table></div></div></div>';
    else echo '</tbody></table></div></div>';
}

// ══════════════════════════════════════════════════════════════════════════════
// SECTION 4 — Open orders
// ══════════════════════════════════════════════════════════════════════════════
if (!empty($orders)) {
    if ($isLegacy) {
        echo '<table class="dataGrid"><thead><tr>';
        foreach (['', 'Name', 'Exchange', 'Created', 'Qty', 'Ask', 'Bid', 'Value', ''] as $h)
            echo '<th>' . Html::encode($h) . '</th>';
        echo '</tr></thead><tbody>';
    } elseif (!$isTailwind) {
        echo '<div class="card shadow-sm"><div class="card-header py-2 d-flex align-items-center gap-2">';
        echo '<i class="bi bi-cart text-secondary"></i><strong class="small">Open Orders</strong>';
        echo '<span class="badge bg-secondary ms-1">' . count($orders) . '</span></div>';
        echo '<div class="card-body p-0"><div class="overflow-auto"><table class="table table-sm table-bordered mb-0">';
        echo '<thead class="table-light"><tr><th style="width:24px"></th>';
        foreach (['Name', 'Exchange', 'Created', 'Qty', 'Ask', 'Bid', 'Value', 'Actions'] as $h)
            echo '<th class="small">' . Html::encode($h) . '</th>';
        echo '</tr></thead><tbody>';
    } else {
        echo '<div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm overflow-hidden">';
        echo '<div class="px-4 py-2.5 border-b border-gray-200 dark:border-gray-700 flex items-center gap-2">';
        echo '<i data-lucide="shopping-cart" class="w-4 h-4 text-gray-400 shrink-0"></i>';
        echo '<span class="text-sm font-semibold text-gray-800 dark:text-gray-100">Open Orders</span>';
        echo '<span class="ml-auto px-2 py-0.5 text-xs rounded-full bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400">' . count($orders) . '</span></div>';
        echo '<div class="overflow-x-auto"><table class="w-full text-xs">';
        echo '<thead><tr class="bg-gray-50 dark:bg-gray-700/50 border-b border-gray-200 dark:border-gray-700 text-gray-500 dark:text-gray-400 font-semibold uppercase tracking-wider">';
        echo '<th class="px-3 py-2.5 w-8"></th>';
        foreach (['Name', 'Exchange', 'Created', 'Qty', 'Ask', 'Bid', 'Value', ''] as $h)
            echo '<th class="px-3 py-2.5 ' . (in_array($h, ['', 'Name', 'Exchange', 'Created']) ? 'text-left' : 'text-right') . '">' . Html::encode($h) . '</th>';
        echo '</tr></thead><tbody>';
    }

    $totalvalue = $totalbid = 0;
    foreach ($orders as $order) {
        $coin = $orderCoins[$order->coinid] ?? null;
        if (!$coin) continue;
        $algoColor = $util->getAlgoColors($coin->algo);
        $marketUrl = $util->getMarketUrl($coin, $order->market);
        $created   = $conv->datetoa2($order->created) . ' ago';
        $price     = $conv->bitcoinvaluetoa($order->price);
        $bid       = $conv->bitcoinvaluetoa($order->bid);
        $value     = $conv->bitcoinvaluetoa($order->amount * $order->price);
        $bidvalue  = $conv->bitcoinvaluetoa($order->amount * $order->bid);
        $bidpct    = ($order->amount * $order->price) > 0 ? round((($order->amount * $order->price) - ($order->amount * $order->bid)) / ($order->amount * $order->price) * 100, 1) : 0;
        $amount    = round($order->amount, 3);
        $totalvalue += $order->amount * $order->price;
        $totalbid   += $order->amount * $order->bid;
        $coinImg    = !empty($coin->image) ? "<img width='16' src='" . Html::encode($coin->image) . "'>" : '';

        if ($isLegacy) {
            echo '<tr class="ssrow" style="background-color:' . $algoColor . ';">';
            echo '<td>' . $coinImg . '</td>';
            echo '<td><b><a href="/admin/coin?id=' . $coin->id . '">' . Html::encode($coin->name) . '</a></b></td>';
            echo '<td><b><a href="' . Html::encode($marketUrl) . '" target="_blank">' . Html::encode($order->market) . '</a></b></td>';
            echo '<td style="font-size:.8em">' . $created . '</td>';
            echo '<td style="font-size:.8em">' . $amount . '</td>';
            echo '<td style="font-size:.8em">' . $price . '</td>';
            echo '<td style="font-size:.8em">' . $bid . ' (' . $bidpct . '%)</td>';
            echo ($order->amount * $order->bid > 0.01 ? '<td style="font-size:.8em"><b>' . $bidvalue . '</b></td>' : '<td style="font-size:.8em">' . $bidvalue . '</td>');
            echo '<td><a href="/admin/cancelorder?id=' . $order->id . '" title="Cancel on exchange">cancel</a> ';
            echo '<a href="/admin/clearorder?id=' . $order->id . '" title="Clear from DB only">clear</a></td></tr>';
        } elseif (!$isTailwind) {
            echo '<tr style="background-color:' . $algoColor . '20;border-left:3px solid ' . $algoColor . ';">';
            echo '<td>' . $coinImg . '</td>';
            echo '<td class="small"><b><a href="/admin/coin?id=' . $coin->id . '">' . Html::encode($coin->name) . '</a></b></td>';
            echo '<td class="small"><a href="' . Html::encode($marketUrl) . '" target="_blank">' . Html::encode($order->market) . '</a></td>';
            echo '<td class="small text-muted">' . $created . '</td>';
            echo '<td class="text-end small font-monospace">' . $amount . '</td>';
            echo '<td class="text-end small font-monospace">' . $price . '</td>';
            echo '<td class="text-end small font-monospace">' . $bid . ' <span class="text-muted">(' . $bidpct . '%)</span></td>';
            echo '<td class="text-end small font-monospace' . ($order->amount * $order->bid > 0.01 ? ' fw-bold' : '') . '">' . $bidvalue . '</td>';
            echo '<td class="small"><a href="/admin/cancelorder?id=' . $order->id . '" class="text-danger me-1" title="Cancel on exchange">cancel</a>';
            echo '<a href="/admin/clearorder?id=' . $order->id . '" class="text-secondary" title="Clear from DB only">clear</a></td></tr>';
        } else {
            echo '<tr class="hover:bg-gray-50/50 dark:hover:bg-gray-700/10 transition-colors border-b border-gray-100 dark:border-gray-700/50" style="border-left:3px solid ' . $algoColor . ';">';
            echo '<td class="px-3 py-1.5">' . $coinImg . '</td>';
            echo '<td class="px-3 py-1.5 font-medium text-gray-800 dark:text-gray-200"><a href="/admin/coin?id=' . $coin->id . '" class="hover:text-indigo-600 dark:hover:text-indigo-400">' . Html::encode($coin->name) . '</a></td>';
            echo '<td class="px-3 py-1.5 text-gray-600 dark:text-gray-300"><a href="' . Html::encode($marketUrl) . '" target="_blank" class="text-indigo-600 dark:text-indigo-400 hover:underline">' . Html::encode($order->market) . '</a></td>';
            echo '<td class="px-3 py-1.5 text-gray-400 dark:text-gray-500">' . $created . '</td>';
            echo '<td class="px-3 py-1.5 text-right font-mono tabular-nums text-gray-600 dark:text-gray-300">' . $amount . '</td>';
            echo '<td class="px-3 py-1.5 text-right font-mono tabular-nums text-gray-500 dark:text-gray-400">' . $price . '</td>';
            echo '<td class="px-3 py-1.5 text-right font-mono tabular-nums text-gray-600 dark:text-gray-300">' . $bid . ' <span class="text-gray-400">(' . $bidpct . '%)</span></td>';
            echo '<td class="px-3 py-1.5 text-right font-mono tabular-nums' . ($order->amount * $order->bid > 0.01 ? ' font-bold text-gray-800 dark:text-gray-200' : ' text-gray-500 dark:text-gray-400') . '">' . $bidvalue . '</td>';
            echo '<td class="px-3 py-1.5 whitespace-nowrap"><a href="/admin/cancelorder?id=' . $order->id . '" class="text-red-500 hover:text-red-700 dark:text-red-400 mr-2" title="Cancel on exchange">cancel</a>';
            echo '<a href="/admin/clearorder?id=' . $order->id . '" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300" title="Clear from DB only">clear</a></td></tr>';
        }
    }

    if ($totalvalue) {
        $totalBidPct = $totalvalue > 0 ? round(($totalvalue - $totalbid) / $totalvalue * 100, 1) : '';
        $tvFmt = $conv->bitcoinvaluetoa($totalvalue);
        $tbdFmt = $conv->bitcoinvaluetoa($totalbid);
        if ($isLegacy) {
            echo '<tr><td></td><td>Total</td><td colspan="3"></td>';
            echo '<td style="font-size:.8em"><b>' . $tvFmt . '</b></td>';
            echo '<td style="font-size:.8em"><b>' . $tbdFmt . ' (' . $totalBidPct . '%)</b></td><td></td></tr>';
        } elseif (!$isTailwind) {
            echo '<tr class="table-secondary fw-bold"><td></td><td class="small">Total</td><td colspan="3"></td>';
            echo '<td class="text-end small font-monospace">' . $tvFmt . '</td>';
            echo '<td class="text-end small font-monospace">' . $tbdFmt . ' (' . $totalBidPct . '%)</td><td colspan="2"></td></tr>';
        } else {
            echo '<tr class="bg-gray-50 dark:bg-gray-700/30 border-t-2 border-gray-300 dark:border-gray-600 font-semibold"><td class="px-3 py-1.5"></td>';
            echo '<td class="px-3 py-1.5 text-gray-700 dark:text-gray-300">Total</td><td colspan="3"></td>';
            echo '<td class="px-3 py-1.5 text-right font-mono tabular-nums text-gray-700 dark:text-gray-200">' . $tvFmt . '</td>';
            echo '<td class="px-3 py-1.5 text-right font-mono tabular-nums text-gray-700 dark:text-gray-200">' . $tbdFmt . ' (' . $totalBidPct . '%)</td><td colspan="2"></td></tr>';
        }
    }

    if ($isLegacy) echo '</tbody></table><br>';
    elseif (!$isTailwind) echo '</tbody></table></div></div></div>';
    else echo '</tbody></table></div></div>';
}

// ── Column split ──────────────────────────────────────────────────────────────
if ($isLegacy) {
    echo '</td><td>&nbsp;&nbsp;</td><td valign="top">';
} elseif (!$isTailwind) {
    echo '</div><div class="col-12 col-xl-4 d-flex flex-column gap-3">';
} else {
    echo '</div><div class="xl:w-80 2xl:w-96 flex flex-col gap-4 shrink-0">';
}

// ══════════════════════════════════════════════════════════════════════════════
// SECTION 5 — BTC wallet summary
// ══════════════════════════════════════════════════════════════════════════════
if ($isLegacy) {
    echo '<a href="https://www.bitstamp.net/markets/btc/usd/" target="_blank">Bitstamp ' . $mining->usdbtc . '</a>, ';
    echo '<a href="https://blockchain.info/address/' . Html::encode($btcaddr) . '" target="_blank">wallet ' . Html::encode((string) $btc->balance) . '</a>, next payout ' . $topay2 . '<br>';
    echo "pay {$topayFmt}, renter {$renterFmt}, marg {$margin}, {$margin2}<br>";
    echo "mint {$mints} immature {$immature}<br><br>";
} elseif (!$isTailwind) {
    echo '<div class="card shadow-sm"><div class="card-header py-2 d-flex align-items-center gap-2">';
    echo '<i class="bi bi-wallet2 text-secondary"></i><strong class="small">BTC Summary</strong></div>';
    echo '<div class="card-body py-2 small">';
    echo '<div class="mb-1"><a href="https://www.bitstamp.net/markets/btc/usd/" target="_blank" class="text-muted">Bitstamp</a> $' . Html::encode((string) $mining->usdbtc) . '</div>';
    echo '<div class="mb-1"><a href="https://blockchain.info/address/' . Html::encode($btcaddr) . '" target="_blank">Wallet</a>: <b>' . Html::encode((string) $btc->balance) . '</b> BTC &mdash; Next payout &ge; ' . $topay2 . '</div>';
    echo '<table class="table table-sm table-borderless mb-0"><tbody>';
    foreach ([['Pay', $topayFmt], ['Renter', $renterFmt], ['Margin', $margin], ['Margin+', $margin2], ['Mint', $mints], ['Immature', $immature]] as [$l, $v]) {
        echo '<tr><td class="text-muted py-0 ps-0">' . $l . '</td><td class="font-monospace py-0">' . $v . '</td></tr>';
    }
    echo '</tbody></table></div></div>';
} else {
    echo '<div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm overflow-hidden">';
    echo '<div class="px-4 py-2.5 border-b border-gray-200 dark:border-gray-700 flex items-center gap-2">';
    echo '<i data-lucide="wallet" class="w-4 h-4 text-gray-400 shrink-0"></i>';
    echo '<span class="text-sm font-semibold text-gray-800 dark:text-gray-100">BTC Summary</span>';
    echo '<a href="https://www.bitstamp.net/markets/btc/usd/" target="_blank" class="ml-auto text-xs text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">$' . Html::encode((string) $mining->usdbtc) . '</a></div>';
    echo '<div class="px-4 py-3">';
    echo '<div class="text-xs text-gray-500 dark:text-gray-400 mb-2">';
    echo '<a href="https://blockchain.info/address/' . Html::encode($btcaddr) . '" target="_blank" class="text-indigo-600 dark:text-indigo-400 hover:underline">Wallet</a>: ';
    echo '<span class="font-mono font-semibold text-gray-800 dark:text-gray-200">' . Html::encode((string) $btc->balance) . ' BTC</span>';
    echo ' &mdash; next payout &ge; <span class="font-mono">' . $topay2 . '</span></div>';
    echo '<div class="grid grid-cols-2 gap-x-4 gap-y-0.5 text-xs">';
    foreach ([['Pay', $topayFmt, ''], ['Renter', $renterFmt, ''], ['Margin', $margin, ''], ['Margin+', $margin2, 'text-indigo-600 dark:text-indigo-400'], ['Mint', $mints, ''], ['Immature', $immature, '']] as [$l, $v, $cls]) {
        echo '<span class="text-gray-400 dark:text-gray-500">' . $l . '</span>';
        echo '<span class="font-mono tabular-nums ' . ($cls ?: 'text-gray-700 dark:text-gray-200') . '">' . $v . '</span>';
    }
    echo '</div></div></div>';
}

// ══════════════════════════════════════════════════════════════════════════════
// SECTION 6 — Graphs (containers; filled by dashboard.php JS)
// ══════════════════════════════════════════════════════════════════════════════
if ($isLegacy) {
    echo '<div style="height:160px;overflow:hidden;" id="graph_results_negative"></div>';
    echo '<div style="height:200px;overflow:hidden;" id="graph_results_assets"></div><br>';
} elseif (!$isTailwind) {
    echo '<div class="card shadow-sm"><div class="card-header py-2 d-flex align-items-center gap-2">';
    echo '<i class="bi bi-graph-up text-secondary"></i><strong class="small">Liabilities</strong></div>';
    echo '<div class="card-body"><div id="graph_results_negative" style="height:140px;"></div></div></div>';
    echo '<div class="card shadow-sm"><div class="card-header py-2 d-flex align-items-center gap-2">';
    echo '<i class="bi bi-bar-chart text-secondary"></i><strong class="small">Assets</strong></div>';
    echo '<div class="card-body"><div id="graph_results_assets" style="height:180px;"></div></div></div>';
} else {
    echo '<div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm overflow-hidden">';
    echo '<div class="px-4 py-2.5 border-b border-gray-200 dark:border-gray-700 flex items-center gap-2">';
    echo '<i data-lucide="trending-down" class="w-4 h-4 text-gray-400 shrink-0"></i>';
    echo '<span class="text-sm font-semibold text-gray-800 dark:text-gray-100">Liabilities</span></div>';
    echo '<div class="p-4"><div id="graph_results_negative" style="height:130px;"></div></div></div>';
    echo '<div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm overflow-hidden">';
    echo '<div class="px-4 py-2.5 border-b border-gray-200 dark:border-gray-700 flex items-center gap-2">';
    echo '<i data-lucide="bar-chart-2" class="w-4 h-4 text-gray-400 shrink-0"></i>';
    echo '<span class="text-sm font-semibold text-gray-800 dark:text-gray-100">Assets</span></div>';
    echo '<div class="p-4"><div id="graph_results_assets" style="height:170px;"></div></div></div>';
}

// ══════════════════════════════════════════════════════════════════════════════
// SECTION 7 — Recent blocks
// ══════════════════════════════════════════════════════════════════════════════
if ($isLegacy) {
    echo '<br><table class="dataGrid"><thead><tr>';
    foreach (['', 'Name', 'Amount', 'Diff', 'Block', 'Time', 'Status'] as $h)
        echo '<th' . ($h !== '' && $h !== 'Name' ? ' align="right"' : '') . '>' . Html::encode($h) . '</th>';
    echo '</tr></thead><tbody>';
} elseif (!$isTailwind) {
    echo '<div class="card shadow-sm"><div class="card-header py-2 d-flex align-items-center gap-2">';
    echo '<i class="bi bi-boxes text-secondary"></i><strong class="small">Recent Blocks</strong>';
    echo '<span class="badge bg-secondary ms-1">' . count($db_blocks) . '</span></div>';
    echo '<div class="card-body p-0"><div class="overflow-auto"><table class="table table-sm table-bordered mb-0">';
    echo '<thead class="table-light"><tr><th style="width:24px"></th>';
    foreach (['Name', 'Amount', 'Diff', 'Block', 'Time', 'Status'] as $h)
        echo '<th class="small' . (in_array($h, ['Amount', 'Diff', 'Block', 'Time']) ? ' text-end' : '') . '">' . Html::encode($h) . '</th>';
    echo '</tr></thead><tbody>';
} else {
    echo '<div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm overflow-hidden">';
    echo '<div class="px-4 py-2.5 border-b border-gray-200 dark:border-gray-700 flex items-center gap-2">';
    echo '<i data-lucide="layers" class="w-4 h-4 text-gray-400 shrink-0"></i>';
    echo '<span class="text-sm font-semibold text-gray-800 dark:text-gray-100">Recent Blocks</span>';
    echo '<span class="ml-auto px-2 py-0.5 text-xs rounded-full bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400">' . count($db_blocks) . '</span></div>';
    echo '<div class="overflow-x-auto"><table class="w-full text-xs">';
    echo '<thead><tr class="bg-gray-50 dark:bg-gray-700/50 border-b border-gray-200 dark:border-gray-700 text-gray-500 dark:text-gray-400 font-semibold uppercase tracking-wider">';
    echo '<th class="px-3 py-2.5 w-8"></th><th class="px-3 py-2.5 text-left">Name</th>';
    echo '<th class="px-3 py-2.5 text-right">Amount</th><th class="px-3 py-2.5 text-right">Diff</th>';
    echo '<th class="px-3 py-2.5 text-right">Block</th><th class="px-3 py-2.5 text-right">Time</th>';
    echo '<th class="px-3 py-2.5 text-center">Status</th></tr></thead>';
    echo '<tbody class="divide-y divide-gray-100 dark:divide-gray-700/50">';
}

foreach ($db_blocks as $db_block) {
    $d = $conv->datetoa2($db_block->time);
    if (!$db_block->coin_id) {
        if (!$showrental) continue;
        $reward = $conv->bitcoinvaluetoa($db_block->amount);
        if ($isLegacy) {
            $ac = $util->getAlgoColors($db_block->algo);
            echo '<tr style="background-color:' . $ac . ';"><td><img width="16" src="/images/btc.png"></td>';
            echo '<td><b>Rental</b> (' . Html::encode($db_block->algo) . ')</td>';
            echo '<td align="right" style="font-size:.8em"><b>' . $reward . ' BTC</b></td>';
            echo '<td></td><td></td><td align="right" style="font-size:.8em">' . $d . ' ago</td>';
            echo '<td align="right" style="font-size:.8em"><span style="padding:2px;color:#fff;background:#5cb85c;">Confirmed</span></td></tr>';
        } elseif (!$isTailwind) {
            echo '<tr><td><img src="/images/btc.png" width="16"></td>';
            echo '<td class="small"><b>Rental</b> <span class="text-muted font-monospace">(' . Html::encode($db_block->algo) . ')</span></td>';
            echo '<td class="text-end small font-monospace fw-bold">' . $reward . ' BTC</td>';
            echo '<td></td><td></td><td class="text-end small text-muted">' . $d . ' ago</td>';
            echo '<td class="text-center"><span class="badge bg-success">Confirmed</span></td></tr>';
        } else {
            echo '<tr class="hover:bg-gray-50/50 dark:hover:bg-gray-700/10 transition-colors">';
            echo '<td class="px-3 py-1.5"><img src="/images/btc.png" width="14" height="14" class="rounded object-contain"></td>';
            echo '<td class="px-3 py-1.5 font-medium text-gray-800 dark:text-gray-200"><b>Rental</b> <span class="font-mono text-gray-400">(' . Html::encode($db_block->algo) . ')</span></td>';
            echo '<td class="px-3 py-1.5 text-right font-mono font-semibold text-gray-800 dark:text-gray-200">' . $reward . ' BTC</td>';
            echo '<td></td><td></td><td class="px-3 py-1.5 text-right text-gray-400 dark:text-gray-500">' . $d . ' ago</td>';
            echo '<td class="px-3 py-1.5 text-center"><span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-400">Confirmed</span></td></tr>';
        }
        continue;
    }

    $coin = $blockCoins[$db_block->coin_id] ?? null;
    if (!$coin) continue;

    $algoColor = $util->getAlgoColors($coin->algo);
    $height    = number_format($db_block->height, 0, '.', ' ');
    $diff      = $conv->Itoa2($db_block->difficulty, 3);
    $flags     = $db_block->segwit ? '&nbsp;<img src="/images/ui/segwit.png" height="8px" title="segwit">' : '';
    $badge     = $statusBadge($db_block->category, (int) $db_block->confirmations);
    $coinImg   = !empty($coin->image) ? "<img width='16' src='" . Html::encode($coin->image) . "'>" : '';

    if ($isLegacy) {
        echo '<tr style="background-color:' . $algoColor . ';">';
        echo '<td>' . $coinImg . '</td>';
        echo '<td><b><a href="/admin/coinwallet?id=' . $coin->id . '">' . Html::encode($coin->name) . '</a></b>' . $flags . '</td>';
        echo '<td align="right" style="font-size:.8em">' . Html::encode((string) $db_block->amount) . ' ' . Html::encode($coin->symbol) . '</td>';
        echo '<td align="right" style="font-size:.8em" title="' . Html::encode($db_block->difficulty_user) . '">' . $diff . '</td>';
        echo '<td align="right" style="font-size:.8em">' . $height . '</td>';
        echo '<td align="right" style="font-size:.8em">' . $d . ' ago</td>';
        echo '<td align="right" style="font-size:.8em">' . $badge . '</td></tr>';
    } elseif (!$isTailwind) {
        echo '<tr style="background-color:' . $algoColor . '20;border-left:3px solid ' . $algoColor . ';">';
        echo '<td>' . $coinImg . '</td>';
        echo '<td class="small"><b><a href="/admin/coinwallet?id=' . $coin->id . '">' . Html::encode($coin->name) . '</a></b>' . $flags . '</td>';
        echo '<td class="text-end small font-monospace">' . Html::encode((string) $db_block->amount) . ' ' . Html::encode($coin->symbol) . '</td>';
        echo '<td class="text-end small font-monospace" title="' . Html::encode($db_block->difficulty_user) . '">' . $diff . '</td>';
        echo '<td class="text-end small tabular-nums">' . $height . '</td>';
        echo '<td class="text-end small text-muted">' . $d . ' ago</td>';
        echo '<td class="text-center">' . $badge . '</td></tr>';
    } else {
        echo '<tr class="hover:bg-gray-50/50 dark:hover:bg-gray-700/10 transition-colors" style="border-left:3px solid ' . $algoColor . ';">';
        echo '<td class="px-3 py-1.5">' . $coinImg . '</td>';
        echo '<td class="px-3 py-1.5 font-medium text-gray-800 dark:text-gray-200"><a href="/admin/coinwallet?id=' . $coin->id . '" class="hover:text-indigo-600 dark:hover:text-indigo-400">' . Html::encode($coin->name) . '</a>' . $flags . '</td>';
        echo '<td class="px-3 py-1.5 text-right font-mono tabular-nums text-gray-600 dark:text-gray-300">' . Html::encode((string) $db_block->amount) . ' ' . Html::encode($coin->symbol) . '</td>';
        echo '<td class="px-3 py-1.5 text-right font-mono tabular-nums text-gray-500 dark:text-gray-400" title="' . Html::encode($db_block->difficulty_user) . '">' . $diff . '</td>';
        echo '<td class="px-3 py-1.5 text-right tabular-nums text-gray-600 dark:text-gray-300">' . $height . '</td>';
        echo '<td class="px-3 py-1.5 text-right text-gray-400 dark:text-gray-500 whitespace-nowrap">' . $d . ' ago</td>';
        echo '<td class="px-3 py-1.5 text-center">' . $badge . '</td></tr>';
    }
}

if ($isLegacy) echo '</tbody></table><br>';
elseif (!$isTailwind) echo '</tbody></table></div></div></div>';
else echo '</tbody></table></div></div>';

// ── Column layout close ───────────────────────────────────────────────────────
if ($isLegacy) {
    echo '</td></tr></table>';
} elseif (!$isTailwind) {
    echo '</div></div>';
} else {
    echo '</div></div>';
}
