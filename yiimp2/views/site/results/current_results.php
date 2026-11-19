<?php

use yii\helpers\Html;
use app\models\Coins;
use app\models\Workers;
use app\models\Stratums;

$isTailwind  = Yii::$app->LayoutManager->isTailwind();
$isLegacy    = Yii::$app->LayoutManager->isLegacy();
$conv        = Yii::$app->ConversionUtils;
$defaultalgo = Yii::$app->session->get('yaamp-algo');

// ── Build sorted algo list (business logic unchanged) ─────────────────────────
$best_algo = '';
$best_norm = 0;
$algos     = [];

foreach (Yii::$app->YiimpUtils->get_algos() as $algo) {
    $algo_norm = Yii::$app->YiimpUtils->get_algo_norm($algo);
    $price = Yii::$app->cache->get("current_price-{$algo}");
    if (!$price) {
        $price = (new \yii\db\Query())->select(['price'])->from('hashrate')
            ->where(['algo' => $algo])->orderBy(['time' => SORT_DESC])->scalar();
        Yii::$app->cache->set("current_price-{$algo}", $price);
    }
    $norm = Yii::$app->YiimpUtils->take_yiimp_fee($price * $algo_norm, $algo);
    $algos[] = [$norm, $algo];
    if ($norm > $best_norm) { $best_norm = $norm; $best_algo = $algo; }
}
usort($algos, fn($a, $b) => $a[0] < $b[0]);

$total_coins = 0; $total_workers = 0; $total_solo_workers = 0; $total_users = 0;
$showestimates = false;

// ── Table open ─────────────────────────────────────────────────────────────────
if ($isLegacy) {
    echo "<div class='main-left-box'><div class='main-left-title'>Pool Status</div><div class='main-left-inner'>";
    Yii::$app->ViewUtils->showTableSorter('maintable1', "{
        tableClass: 'dataGrid2',
        textExtraction: {
            4: function(node,table,n){ return \$(node).attr('data'); },
            8: function(node,table,n){ return \$(node).attr('data'); }
        }
    }");
} elseif (!$isTailwind) {
    echo '<div class="card shadow-sm mb-3"><div class="card-header py-2 small fw-semibold"><i class="bi bi-bar-chart me-1"></i>Pool Status</div>';
    echo '<div class="card-body p-0"><div class="overflow-auto">';
    echo '<table id="maintable1" class="table table-sm table-bordered mb-0">';
    Yii::$app->ViewUtils->JavascriptReady("
        \$('#maintable1').tablesorter({
            textExtraction:{
                4: function(node,table,n){ return \$(node).attr('data'); },
                8: function(node,table,n){ return \$(node).attr('data'); }
            }
        });
        \$('.tablesorter-header').not('.sorter-false').css('cursor','pointer');
    ");
} else {
    echo '<div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm overflow-hidden mb-4">';
    echo '<div class="px-4 py-2.5 border-b border-gray-200 dark:border-gray-700 flex items-center gap-2">';
    echo '<i data-lucide="bar-chart-2" class="w-4 h-4 text-gray-400 shrink-0"></i>';
    echo '<span class="text-sm font-semibold text-gray-800 dark:text-gray-100">Pool Status</span>';
    echo '</div><div class="overflow-x-auto">';
    echo '<table id="maintable1" class="w-full text-xs">';
}

// ── Thead ─────────────────────────────────────────────────────────────────────
if ($isLegacy) {
    echo <<<END
<thead><tr>
<th>Coins</th>
<th data-sorter="numeric" align="center">Auto Exchanged</th>
<th data-sorter="numeric" align="center">Minimum Payout</th>
<th data-sorter="numeric" align="center">Port</th>
<th data-sorter="numeric" align="center">Users (Active)</th>
<th data-sorter="numeric" align="center">Workers<br>Share/Solo</th>
<th data-sorter="numeric" align="center">Pool HashRate<br>Share/Solo/Total</th>
<th data-sorter="numeric" align="center">Network Hashrate</th>
<th data-sorter="currency" align="center">Fees<br>Share/Solo</th>
<th data-sorter="currency" align="center">24 Hours<br>Actual</th>
</tr></thead>
END;
} elseif (!$isTailwind) {
    echo '<thead class="table-light"><tr>';
    foreach (['Algo/Coin','Excl.','Min Pay','Port','Users','Workers S/S','Hashrate S/S/T','Net Hash','Fees','24h Act.'] as $h)
        echo "<th class='text-center small' style='white-space:nowrap'>" . Html::encode($h) . "</th>";
    echo '</tr></thead>';
} else {
    echo '<thead><tr class="bg-gray-50 dark:bg-gray-700/50 text-gray-500 dark:text-gray-400 font-semibold uppercase tracking-wider border-b border-gray-200 dark:border-gray-700">';
    foreach (['Algo/Coin','Excl.','Min Pay','Port','Users','Workers','Hashrate','Net Hash','Fees','24h Act.'] as $h)
        echo '<th class="px-2 py-2.5 text-center">' . Html::encode($h) . '</th>';
    echo '</tr></thead>';
}

echo '<tbody>';

foreach ($algos as $item) {
    $norm = $item[0];
    $algo = $item[1];
    $coinsym = '';
    $users_total = 0;

    $coins = Coins::find()->where(['enable' => 1, 'visible' => 1, 'auto_ready' => 1, 'algo' => $algo])
                          ->orderBy(['index_avg' => SORT_DESC]);
    if (!$coins || $coins->count() == 0) continue;

    $workers      = Workers::find()->where(['algo' => $algo])->andFilterWhere(['not like', 'password', 'm=solo'])->count();
    $solo_workers = Workers::find()->where(['algo' => $algo])->andFilterWhere(['like', 'password', 'm=solo'])->count();

    $hashrate = Yii::$app->cache->get("current_hashrate-{$algo}");
    if (!$hashrate) {
        $hashrate = (new \yii\db\Query())->select(['hashrate'])->from('hashrate')
            ->where(['algo' => $algo])->orderBy(['time' => SORT_DESC])->scalar();
        Yii::$app->cache->set("current_hashrate-{$algo}", $hashrate);
    }

    $price = Yii::$app->cache->get("current_price-{$algo}");
    if (!$price) {
        $price = (new \yii\db\Query())->select(['price'])->from('hashrate')
            ->where(['algo' => $algo])->orderBy(['time' => SORT_DESC])->scalar();
        Yii::$app->cache->set("current_price-{$algo}", $price);
    }
    $price_fmt = $price ? $conv->mbitcoinvaluetoa(Yii::$app->YiimpUtils->take_yiimp_fee($price, $algo)) : '-';
    $norm_fmt  = $conv->mbitcoinvaluetoa($norm);

    $t         = time() - 86400;
    $avgprice  = Yii::$app->cache->get("current_avgprice-{$algo}");
    if (!$avgprice) {
        $avgprice = (new \yii\db\Query())->select(['avg(price)'])->from('hashrate')
            ->where(['algo' => $algo])->andWhere(['>', 'time', $t])->scalar();
        Yii::$app->cache->set("current_avgprice-{$algo}", $avgprice);
    }

    $total1 = Yii::$app->cache->get("current_total-{$algo}");
    if (!$total1) {
        $total1 = (new \yii\db\Query())->select(['SUM(amount*price)'])->from('blocks')
            ->where(['algo' => $algo])->andWhere(['>', 'time', $t])
            ->andWhere(['not in', 'category', ['orphan','stake','generated']])->scalar();
        Yii::$app->cache->set("current_total-{$algo}", $total1);
    }
    $hashrate1 = Yii::$app->cache->get("current_hashrate1-{$algo}");
    if (!$hashrate1) {
        $hashrate1 = (new \yii\db\Query())->select(['avg(hashrate)'])->from('hashrate')
            ->where(['algo' => $algo])->andWhere(['>', 'time', $t])->scalar();
        Yii::$app->cache->set("current_hashrate1-{$algo}", $hashrate1);
    }

    $algo_unit_factor = Yii::$app->YiimpUtils->algo_mBTC_factor($algo);
    $btcmhday1 = $hashrate1 != 0 ? $conv->mbitcoinvaluetoa($total1 / $hashrate1 * 1000000 * 1000 * $algo_unit_factor) : '';
    $fees      = Yii::$app->YiimpUtils->yiimp_fee($algo);
    $fees_solo = Yii::$app->YiimpUtils->yiimp_fee_solo($algo);
    $isBest    = ($algo === $best_algo);

    // ── Algo header row ───────────────────────────────────────────────────────
    $isSelected = ($defaultalgo === $algo);
    if ($isLegacy) {
        $bg = $isSelected ? "style='cursor:pointer;background-color:#d9d9d9;'" : "style='cursor:pointer' class='ssrow'";
        echo "<tr {$bg} onclick='javascript:select_algo(\"{$algo}\")'>";
        echo "<td style='font-size:110%;background-color:#f2f2f2;'><b>{$algo}</b></td>";
        for ($i = 0; $i < 8; $i++) echo "<td align='center' style='font-size:.8em;background-color:#f2f2f2;'></td>";
        echo $isBest
            ? "<td class='estimate' align='center' style='font-size:.8em;background-color:#f2f2f2;' title='normalized {$norm_fmt}'><b>{$price_fmt}</b></td>"
            : "<td class='estimate' align='center' style='font-size:.8em;background-color:#f2f2f2;' title='normalized {$norm_fmt}'>{$price_fmt}</td>";
        echo "<td align='center' style='font-size:.8em;background-color:#f2f2f2;' data='{$btcmhday1}'>" . ($isBest ? "<b>{$btcmhday1}*</b>" : $btcmhday1) . "</td>";
        echo '</tr>';
    } elseif (!$isTailwind) {
        $rowCls = $isSelected ? 'table-secondary fw-bold' : 'table-light';
        echo "<tr class='{$rowCls}' style='cursor:pointer;' onclick='select_algo(\"{$algo}\")'>";
        echo "<td class='small fw-bold'>" . Html::encode($algo) . "</td>";
        for ($i = 0; $i < 8; $i++) echo "<td></td>";
        echo "<td class='text-center small" . ($isBest ? " fw-bold" : "") . "' title='normalized {$norm_fmt}'>{$price_fmt}</td>";
        echo "<td class='text-center small font-monospace" . ($isBest ? " fw-bold" : "") . "' data='{$btcmhday1}'>" . ($isBest ? "{$btcmhday1}*" : $btcmhday1) . "</td>";
        echo '</tr>';
    } else {
        $rowCls = $isSelected
            ? 'bg-indigo-50 dark:bg-indigo-900/20 border-l-2 border-indigo-500'
            : 'bg-gray-50 dark:bg-gray-700/30 hover:bg-indigo-50/50 dark:hover:bg-indigo-900/10';
        echo "<tr class='{$rowCls} cursor-pointer transition-colors' onclick='select_algo(\"{$algo}\")'>";
        echo "<td class='px-2 py-2 font-bold " . ($isSelected ? "text-indigo-700 dark:text-indigo-300" : "text-gray-800 dark:text-gray-100") . "'>" . Html::encode($algo) . "</td>";
        for ($i = 0; $i < 8; $i++) echo "<td></td>";
        echo "<td class='px-2 py-2 text-center font-mono" . ($isBest ? " font-bold text-green-600 dark:text-green-400" : " text-gray-500 dark:text-gray-400") . "' title='normalized {$norm_fmt}'>{$price_fmt}</td>";
        echo "<td class='px-2 py-2 text-center font-mono tabular-nums" . ($isBest ? " font-bold text-green-600 dark:text-green-400" : " text-gray-500 dark:text-gray-400") . "' data='{$btcmhday1}'>" . ($isBest ? "{$btcmhday1}*" : $btcmhday1) . "</td>";
        echo '</tr>';
    }

    // ── Per-coin rows ─────────────────────────────────────────────────────────
    foreach ($coins->all() as $coin) {
        $name   = Html::encode(substr($coin->name, 0, 20));
        $symbol = $coin->getOfficialSymbol();

        $coin_stratum = Stratums::find()->where(['algo' => $algo, 'symbol' => $symbol]);
        $port_count   = $coin_stratum->count();
        $port_db      = $port_count >= 1 ? $coin_stratum->one() : null;

        $auto_exchange = $coin->auto_exchange;
        $min_payout    = max(floatval(YIIMP_PAYMENTS_MINI), floatval($coin->payout_min));

        $subq = (new \yii\db\Query())->select(['userid'])->from('workers')->distinct();
        $users_total = (new \yii\db\Query())->select(['count(id)'])->from('accounts')
            ->where(['in', 'id', $subq])->scalar();
        $users_coins = (new \yii\db\Query())->select(['count(id)'])->from('accounts')
            ->where(['coinid' => $coin->id])->andWhere(['in', 'id', $subq])->scalar();

        $wq = (new \yii\db\Query())->select(['count(id)'])->from('workers')
            ->where(['algo' => $algo, 'pid' => ($port_db ? $port_db->pid : 0)]);
        $workers_coins      = (clone $wq)->andWhere(['not like', 'password', 'm=solo'])->scalar();
        $solo_workers_coins = (clone $wq)->andWhere(['like', 'password', 'm=solo'])->scalar();

        $pool_hash         = Yii::$app->YiimpUtils->coin_rate($coin->id);
        $pool_hash_sfx     = $pool_hash ? $conv->Itoa2($pool_hash) . 'h/s' : '0 h/s';
        $pool_shared       = Yii::$app->YiimpUtils->coin_shared_rate($coin->id);
        $pool_shared_sfx   = $pool_shared ? $conv->Itoa2($pool_shared) . 'h/s' : '0 h/s';
        $pool_solo_h       = Yii::$app->YiimpUtils->coin_solo_rate($coin->id);
        $pool_solo_sfx     = $pool_solo_h ? $conv->Itoa2($pool_solo_h) . 'h/s' : '0 h/s';
        $nethash           = Yii::$app->YiimpUtils->coin_nethash($coin);
        $nethash_sfx       = $nethash ? $conv->Itoa2($nethash) . 'h/s' : '';
        $btcmhd            = $conv->mbitcoinvaluetoa(Yii::$app->YiimpUtils->yiimp_profitability($coin));

        $display_users   = $port_count >= 1 ? $users_coins : $users_total;
        $display_workers = $port_count >= 1 ? "{$workers_coins}/{$solo_workers_coins}" : "{$workers}/{$solo_workers}";
        $display_port    = $port_db ? $port_db->port : '—';
        $display_hash    = "{$pool_shared_sfx}/{$pool_solo_sfx}/{$pool_hash_sfx}";

        if ($isLegacy) {
            echo '<tr>';
            echo "<td align='left' valign='top' style='font-size:.8em;'><img width='10' src='" . Html::encode($coin->image) . "'>&nbsp;<b>{$name} ({$coin->symbol})</b></td>";
            echo "<td align='center' valign='top' style='font-size:.8em;'><img width='13' src='" . ($auto_exchange ? '/images/ok.png' : '/images/cancel.png') . "'></td>";
            echo "<td align='center' style='font-size:.8em;'><b>{$min_payout} {$symbol}</b></td>";
            echo "<td align='center' style='font-size:.8em;'><b>{$display_port}</b></td>";
            echo "<td align='center' style='font-size:.8em;'>{$display_users}</td>";
            echo "<td align='center' style='font-size:.8em;'>{$display_workers}</td>";
            echo "<td align='center' style='font-size:.8em;'>{$display_hash}</td>";
            echo "<td align='center' style='font-size:.8em;' data='{$pool_hash}'>{$nethash_sfx}</td>";
            echo "<td align='center' style='font-size:.8em;'>{$fees}%/{$fees_solo}%</td>";
            echo "<td align='center' style='font-size:.8em;'>{$btcmhd}</td>";
            echo '</tr>';
        } elseif (!$isTailwind) {
            echo '<tr>';
            $coinImg = !empty($coin->image) ? "<img src='" . Html::encode($coin->image) . "' width='14' style='object-fit:contain' onerror='this.style.display=\"none\"'>&nbsp;" : '';
            echo "<td class='small'>{$coinImg}<b>{$name}</b> <span class='text-muted'>({$coin->symbol})</span></td>";
            $exchIco = $auto_exchange ? "<i class='bi bi-check-circle-fill text-success'></i>" : "<i class='bi bi-x-circle-fill text-danger'></i>";
            echo "<td class='text-center small'>{$exchIco}</td>";
            echo "<td class='text-center small font-monospace'>{$min_payout} {$symbol}</td>";
            echo "<td class='text-center small font-monospace fw-bold'>{$display_port}</td>";
            echo "<td class='text-center small tabular-nums'>{$display_users}</td>";
            echo "<td class='text-center small tabular-nums'>{$display_workers}</td>";
            echo "<td class='text-center small font-monospace'>{$display_hash}</td>";
            echo "<td class='text-center small font-monospace' data='{$pool_hash}'>{$nethash_sfx}</td>";
            echo "<td class='text-center small'>{$fees}%/{$fees_solo}%</td>";
            echo "<td class='text-center small font-monospace'>{$btcmhd}</td>";
            echo '</tr>';
        } else {
            echo '<tr class="hover:bg-gray-50/50 dark:hover:bg-gray-700/10 transition-colors border-b border-gray-100 dark:border-gray-700/50">';
            $coinImg = !empty($coin->image) ? "<img src='" . Html::encode($coin->image) . "' width='14' height='14' class='inline rounded object-contain mr-1' onerror='this.style.display=\"none\"'>" : '';
            echo "<td class='px-2 py-1.5 font-medium text-gray-800 dark:text-gray-200'>{$coinImg}{$name} <span class='text-gray-400 dark:text-gray-500 font-mono'>({$coin->symbol})</span></td>";
            $exchEl = $auto_exchange
                ? '<i data-lucide="check-circle" class="w-3.5 h-3.5 text-green-500 dark:text-green-400 inline"></i>'
                : '<i data-lucide="x-circle" class="w-3.5 h-3.5 text-red-500 dark:text-red-400 inline"></i>';
            echo "<td class='px-2 py-1.5 text-center'>{$exchEl}</td>";
            echo "<td class='px-2 py-1.5 text-center font-mono text-gray-600 dark:text-gray-300'>{$min_payout} {$symbol}</td>";
            echo "<td class='px-2 py-1.5 text-center font-mono font-bold text-gray-800 dark:text-gray-200'>{$display_port}</td>";
            echo "<td class='px-2 py-1.5 text-center tabular-nums text-gray-600 dark:text-gray-300'>{$display_users}</td>";
            echo "<td class='px-2 py-1.5 text-center tabular-nums font-mono text-gray-600 dark:text-gray-300'>{$display_workers}</td>";
            echo "<td class='px-2 py-1.5 text-center font-mono text-gray-600 dark:text-gray-300'>{$display_hash}</td>";
            echo "<td class='px-2 py-1.5 text-center font-mono text-gray-500 dark:text-gray-400' data='{$pool_hash}'>{$nethash_sfx}</td>";
            echo "<td class='px-2 py-1.5 text-center text-gray-500 dark:text-gray-400'>{$fees}%/{$fees_solo}%</td>";
            echo "<td class='px-2 py-1.5 text-center font-mono font-semibold text-indigo-600 dark:text-indigo-400'>{$btcmhd}</td>";
            echo '</tr>';
        }

        $total_users = $users_total;
    }

    $total_coins        += $coins->count();
    $total_workers      += $workers;
    $total_solo_workers += $solo_workers;
}

// ── "All" footer row ──────────────────────────────────────────────────────────
$isAllSelected = ($defaultalgo === 'all');
if ($isLegacy) {
    $bg = $isAllSelected ? "style='cursor:pointer;background-color:#d9d9d9;'" : "style='cursor:pointer' class='ssrow'";
    echo "<tr {$bg} onclick='javascript:select_algo(\"all\")'>";
    echo "<td><b>all</b></td><td></td>";
    echo "<td align='center' style='font-size:.8em;'>{$total_coins} Coins</td><td></td>";
    echo "<td align='center' style='font-size:.8em;'>{$total_users} Users</td>";
    echo "<td align='center' style='font-size:.8em;'>Shared: {$total_workers}<br>Solo: {$total_solo_workers}</td>";
    echo "<td></td><td class='estimate'></td><td class='estimate'></td><td></td>";
    echo '</tr>';
} elseif (!$isTailwind) {
    $rowCls = $isAllSelected ? 'table-secondary fw-bold' : 'table-light';
    echo "<tr class='{$rowCls}' style='cursor:pointer;' onclick='select_algo(\"all\")'>";
    echo "<td class='small fw-bold'>all</td><td></td>";
    echo "<td class='text-center small'>{$total_coins} coins</td><td></td>";
    echo "<td class='text-center small'>{$total_users} users</td>";
    echo "<td class='text-center small'>{$total_workers}S/{$total_solo_workers}So</td>";
    echo "<td></td><td></td><td></td><td></td>";
    echo '</tr>';
} else {
    $rowCls = $isAllSelected
        ? 'bg-indigo-50 dark:bg-indigo-900/20 border-l-2 border-indigo-500'
        : 'bg-gray-50 dark:bg-gray-700/30 hover:bg-indigo-50/50 dark:hover:bg-indigo-900/10';
    echo "<tr class='{$rowCls} cursor-pointer transition-colors' onclick='select_algo(\"all\")'>";
    echo "<td class='px-2 py-2 font-bold " . ($isAllSelected ? "text-indigo-700 dark:text-indigo-300" : "text-gray-800 dark:text-gray-100") . "'>all</td><td></td>";
    echo "<td class='px-2 py-2 text-center text-gray-500 dark:text-gray-400'>{$total_coins} coins</td><td></td>";
    echo "<td class='px-2 py-2 text-center text-gray-500 dark:text-gray-400'>{$total_users}</td>";
    echo "<td class='px-2 py-2 text-center font-mono text-gray-500 dark:text-gray-400'>{$total_workers}S/{$total_solo_workers}So</td>";
    echo "<td></td><td></td><td></td><td></td>";
    echo '</tr>';
}

echo '</tbody></table>';

if ($isLegacy) {
    echo '<p style="font-size:.8em;">&nbsp;* values in mBTC/MH/day, per GH for sha & blake</p>';
    echo '</div></div><br>';
    if (!$showestimates) echo '<style>#maintable1 .estimate{display:none;}</style>';
} elseif (!$isTailwind) {
    echo '</div></div>';
    echo '<div class="card-footer text-muted small py-1">* mBTC/MH/day, per GH for sha &amp; blake</div></div>';
} else {
    echo '<div class="px-4 py-2 border-t border-gray-200 dark:border-gray-700 text-xs text-gray-400 dark:text-gray-500">* mBTC/MH/day (GH for sha &amp; blake)</div></div>';
}
