<?php

use yii\helpers\Html;
use app\models\Coins;
use app\models\Workers;

$isTailwind = Yii::$app->LayoutManager->isTailwind();
$isLegacy   = Yii::$app->LayoutManager->isLegacy();
$conv       = Yii::$app->ConversionUtils;
$util       = Yii::$app->YiimpUtils;

$algo = Yii::$app->session->get('yaamp-algo');

$total_rate   = $util->pool_rate();
$total_rate_d = $total_rate ? 'at ' . $conv->Itoa2($total_rate) . 'h/s' : '';

$list = Coins::find()
    ->where(['enable' => 1, 'visible' => 1])
    ->andWhere($algo !== 'all' ? ['algo' => $algo] : [])
    ->orderBy(['auxpow' => SORT_ASC, 'index_avg' => SORT_DESC])
    ->all();

$count  = count($list);
$worker = $algo === 'all'
    ? Workers::find()->count()
    : Workers::find()->where(['algo' => $algo])->count();

$coin_count  = $count > 1 ? "on {$count} wallets" : 'on a single wallet';
$miner_count = $worker > 1 ? "{$worker} miners" : "{$worker} miner";
$title       = "Mining {$coin_count} {$total_rate_d}, {$miner_count}";

// ── Table open ────────────────────────────────────────────────────────────────
if ($isLegacy) {
    echo "<div class='main-left-box'>";
    echo "<div class='main-left-title'>" . Html::encode($title) . "</div>";
    echo "<div class='main-left-inner'>";
    Yii::$app->ViewUtils->showTableSorter('maintable3', "{
        tableClass: 'dataGrid2',
        textExtraction: {
            3: function(node,table,n){ return \$(node).attr('data'); },
            6: function(node,table,n){ return \$(node).attr('data'); },
            7: function(node,table,n){ return \$(node).attr('data'); }
        }
    }");
} elseif (!$isTailwind) {
    echo '<div class="card shadow-sm mb-3">';
    echo '<div class="card-header d-flex align-items-center gap-2 py-2">';
    echo '<i class="bi bi-cpu text-secondary"></i>';
    echo '<strong class="small">' . Html::encode($title) . '</strong>';
    echo '</div><div class="card-body p-0"><div class="overflow-auto">';
    echo '<table id="maintable3" class="table table-sm table-bordered table-hover mb-0">';
    Yii::$app->ViewUtils->JavascriptReady("
        \$('#maintable3').tablesorter({
            textExtraction: {
                3: function(node,table,n){ return \$(node).attr('data'); },
                6: function(node,table,n){ return \$(node).attr('data'); },
                7: function(node,table,n){ return \$(node).attr('data'); }
            }
        });
        \$('.tablesorter-header').not('.sorter-false').css('cursor','pointer');
    ");
} else {
    echo '<div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm overflow-hidden mb-4">';
    echo '<div class="px-4 py-2.5 border-b border-gray-200 dark:border-gray-700 flex items-center gap-2">';
    echo '<i data-lucide="cpu" class="w-4 h-4 text-gray-400 shrink-0"></i>';
    echo '<span class="text-sm font-semibold text-gray-800 dark:text-gray-100">' . Html::encode($title) . '</span>';
    echo '</div><div class="overflow-x-auto">';
    echo '<table id="maintable3" class="w-full text-xs">';
    Yii::$app->ViewUtils->JavascriptReady("
        (function(){
            var t=document.getElementById('maintable3'); if(!t)return;
            var tb=t.tBodies[0], ths=Array.from(t.tHead.rows[0].cells), asc=ths.map(function(){return true;});
            ths.forEach(function(th,col){
                if(th.dataset.sorter==='false')return;
                th.style.cursor='pointer';
                th.addEventListener('click',function(){
                    var rs=Array.from(tb.rows);
                    rs.sort(function(a,b){
                        var av=(a.cells[col].getAttribute('data')||a.cells[col].textContent||'').trim();
                        var bv=(b.cells[col].getAttribute('data')||b.cells[col].textContent||'').trim();
                        var n=parseFloat(av)-parseFloat(bv);
                        return isNaN(n)?(asc[col]?av.localeCompare(bv):bv.localeCompare(av)):(asc[col]?n:-n);
                    });
                    asc[col]=!asc[col];
                    rs.forEach(function(r){tb.appendChild(r);});
                });
            });
        })();
    ");
}

// ── Thead ─────────────────────────────────────────────────────────────────────
if ($isLegacy) {
    echo <<<END
<thead><tr>
<th data-sorter=""></th>
<th data-sorter="text">Name</th>
<th align="right">Amount</th>
<th data-sorter="numeric" align="right">Diff</th>
<th align="right">Block</th>
<th align="right">TTF***</th>
<th data-sorter="numeric" align="right">Hash**</th>
<th data-sorter="currency" align="right">Profit*</th>
</tr></thead>
END;
} elseif (!$isTailwind) {
    echo '<thead class="table-light"><tr>';
    echo '<th data-sorter="false" style="width:26px"></th>';
    echo '<th data-sorter="text">Name</th>';
    echo '<th data-sorter="text" class="text-end">Amount</th>';
    echo '<th data-sorter="numeric" class="text-end">Diff</th>';
    echo '<th data-sorter="numeric" class="text-end">Block</th>';
    echo '<th data-sorter="text" class="text-end" title="Estimated time to find a block">TTF***</th>';
    echo '<th data-sorter="numeric" class="text-end">Hash**</th>';
    echo '<th data-sorter="currency" class="text-end">Profit*</th>';
    echo '</tr></thead>';
} else {
    echo '<thead><tr class="bg-gray-50 dark:bg-gray-700/50 text-gray-500 dark:text-gray-400 font-semibold uppercase tracking-wider border-b border-gray-200 dark:border-gray-700">';
    echo '<th class="px-3 py-2.5 w-8" data-sorter="false"></th>';
    echo '<th class="px-3 py-2.5 text-left" data-sorter="text">Name</th>';
    echo '<th class="px-3 py-2.5 text-right" data-sorter="text">Amount</th>';
    echo '<th class="px-3 py-2.5 text-right" data-sorter="numeric">Diff</th>';
    echo '<th class="px-3 py-2.5 text-right" data-sorter="numeric">Block</th>';
    echo '<th class="px-3 py-2.5 text-right" data-sorter="text" title="Estimated time to find a block">TTF***</th>';
    echo '<th class="px-3 py-2.5 text-right" data-sorter="numeric">Hash**</th>';
    echo '<th class="px-3 py-2.5 text-right" data-sorter="currency">Profit*</th>';
    echo '</tr></thead>';
}

echo '<tbody>';
$separate_aux = 0;

foreach ($list as $coin) {
    if ($coin->auxpow && !$separate_aux) {
        $separate_aux = 1;
        if ($isLegacy) {
            echo "<tr class='ssrow'><td></td><td>merged mined coins</td></tr>";
        } elseif (!$isTailwind) {
            echo "<tr><td></td><td colspan='7' class='small text-muted'>Merged mined coins</td></tr>";
        } else {
            echo "<tr><td></td><td colspan='7' class='px-3 py-1.5 text-xs text-gray-400 dark:text-gray-500 italic'>Merged mined coins</td></tr>";
        }
    }

    $name       = Html::encode(substr($coin->name, 0, 20));
    $difficulty = $conv->Itoa2($coin->difficulty, 3);
    $height     = number_format($coin->block_height, 0, '.', ' ');
    $blocktime  = $coin->block_time ?: max(min($coin->actual_ttf, 60), 30);

    $network_hash     = $util->coin_nethash($coin);
    $nethash_str      = $network_hash ? 'network hash ' . $conv->Itoa2($network_hash) . 'h/s' : '';
    $total_pool_rate  = $util->pool_rate($coin->algo);
    $pool_total_rate  = $total_pool_rate ? $conv->Itoa2($total_pool_rate) . 'h/s' : '';

    $pool_ttf        = $total_rate ? $conv->sectoa2($network_hash / $total_rate * $blocktime) : '';
    $reward          = round($coin->reward, 3);
    $btcmhd          = $util->yiimp_profitability($coin);
    $pool_hash       = $util->coin_rate($coin->id);
    $real_ttf        = $pool_hash ? $conv->sectoa2($network_hash / $pool_hash * $blocktime) : '';
    $pool_shared     = $util->coin_shared_rate($coin->id);
    $shared_ttf      = $pool_shared ? $conv->sectoa2($network_hash / $pool_shared * $blocktime) : '';
    $pool_solo       = $util->coin_solo_rate($coin->id);
    $solo_ttf        = $pool_solo ? $conv->sectoa2($network_hash / $pool_solo * $blocktime) : '';

    $pool_hash_sfx   = $pool_hash   ? $conv->Itoa2($pool_hash)   . 'h/s' : '';
    $shared_hash_sfx = $pool_shared ? $conv->Itoa2($pool_shared) . 'h/s' : '';
    $solo_hash_sfx   = $pool_solo   ? $conv->Itoa2($pool_solo)   . 'h/s' : '';

    $btcmhd_fmt = $conv->mbitcoinvaluetoa($btcmhd);

    // TTF tooltip
    $ttfTitle = '';
    if (!empty($shared_ttf) && !empty($solo_ttf))
        $ttfTitle = "Shared: {$shared_ttf} at {$shared_hash_sfx}\nSolo: {$solo_ttf} at {$solo_hash_sfx}\nFull pool: {$pool_ttf} at {$pool_total_rate}";
    elseif (!empty($shared_ttf))
        $ttfTitle = "Shared: {$shared_ttf} at {$shared_hash_sfx}\nFull pool: {$pool_ttf} at {$pool_total_rate}";
    elseif (!empty($solo_ttf))
        $ttfTitle = "Solo: {$solo_ttf} at {$solo_hash_sfx}\nFull pool: {$pool_ttf} at {$pool_total_rate}";
    else
        $ttfTitle = "Full pool: {$pool_ttf} at {$pool_total_rate}";
    $displayTtf = !empty($real_ttf) ? $real_ttf : $pool_ttf;

    // Owed check
    $owed = (new \yii\db\Query())->select(['SUM(balance)'])->from('accounts')->where(['coinid' => $coin->id])->scalar();
    $short = YIIMP_ALLOW_EXCHANGE && $coin->balance + $coin->mint < $owed * 0.9;

    if ($isLegacy) {
        $rowStyle = $coin->auto_ready ? "class='ssrow'" : "style='opacity:0.4;'";
        echo "<tr {$rowStyle}>";
        echo '<td width="18">' . $coin->createExplorerLink('<img width="16" src="' . $coin->image . '">') . '</td>';
        if ($short) {
            $owed2  = $conv->bitcoinvaluetoa($owed - $coin->balance);
            $symbol = $coin->getOfficialSymbol();
            $hint   = "We are short of this currency ({$owed2} {$symbol}).";
            echo "<td><b><a href='/site/block?id={$coin->id}' title='{$hint}' style='color:#c55;'>{$name}</a></b><span style='font-size:.8em;'> ({$coin->algo})</span></td>";
        } else {
            echo "<td><b><a href='/site/block?id={$coin->id}'>{$name}</a></b><span style='font-size:.8em;'> ({$coin->algo})</span>";
            if (!$coin->auto_exchange) echo "<span style='font-size:.8em;color:red;font-weight:bold;'>(no autotrade)</span>";
            echo "</td>";
        }
        echo "<td align=right style='font-size:.8em;'><b>{$reward} {$coin->symbol_show}</b></td>";
        echo "<td align=right style='font-size:.8em;' data='{$coin->difficulty}' title='POW {$coin->difficulty}'>{$difficulty}</td>";
        echo $coin->errors
            ? "<td align=right style='font-size:.8em;color:red;' title='{$coin->errors}'>{$height}</td>"
            : "<td align=right style='font-size:.8em;'>{$height}</td>";
        echo "<td align=right style='font-size:.8em;' title='{$ttfTitle}'>{$displayTtf}</td>";
        $auxNote = $coin->auxpow && $coin->auto_ready ? "style='font-size:.8em;opacity:0.6;'" : "style='font-size:.8em;'";
        echo "<td align=right {$auxNote} title='Network: {$nethash_str}' data='{$pool_hash}'>{$pool_hash_sfx}</td>";
        echo "<td align=right style='font-size:.8em;' data='{$btcmhd}'><b>{$btcmhd_fmt}</b></td>";
        echo "</tr>";

    } elseif (!$isTailwind) {
        $dimmed = !$coin->auto_ready ? 'opacity-50' : '';
        echo "<tr class='{$dimmed}'>";
        echo '<td>' . ($coin->image ? "<img src='" . Html::encode($coin->image) . "' width='18' style='object-fit:contain' onerror='this.style.display=\"none\"'>" : '') . '</td>';
        if ($short) {
            echo "<td class='small'><a href='/site/block?id={$coin->id}' class='fw-bold text-danger' title='Short supply'>{$name}</a> <span class='text-muted'>({$coin->algo})</span></td>";
        } else {
            echo "<td class='small'><a href='/site/block?id={$coin->id}' class='fw-bold'>{$name}</a> <span class='text-muted'>({$coin->algo})</span>";
            if (!$coin->auto_exchange) echo " <span class='badge bg-danger'>no autotrade</span>";
            echo "</td>";
        }
        echo "<td class='text-end small font-monospace'><b>{$reward} " . Html::encode($coin->symbol_show) . "</b></td>";
        echo "<td class='text-end small font-monospace' data='{$coin->difficulty}' title='POW {$coin->difficulty}'>{$difficulty}</td>";
        echo $coin->errors
            ? "<td class='text-end small text-danger' title='" . Html::encode($coin->errors) . "'>{$height}</td>"
            : "<td class='text-end small tabular-nums'>{$height}</td>";
        echo "<td class='text-end small' title='" . Html::encode($ttfTitle) . "'>{$displayTtf}</td>";
        $auxCls = $coin->auxpow && $coin->auto_ready ? 'text-muted' : '';
        echo "<td class='text-end small font-monospace {$auxCls}' title='Network: {$nethash_str}' data='{$pool_hash}'>{$pool_hash_sfx}</td>";
        echo "<td class='text-end small font-monospace fw-bold' data='{$btcmhd}'>{$btcmhd_fmt}</td>";
        echo "</tr>";

    } else {
        $rowCls  = $coin->auto_ready ? 'hover:bg-gray-50/70 dark:hover:bg-gray-700/20 transition-colors' : 'opacity-40';
        $shortCls = $short ? 'text-red-500 dark:text-red-400' : 'text-gray-900 dark:text-gray-100 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors';
        echo "<tr class='{$rowCls}'>";
        echo '<td class="px-3 py-2">' . ($coin->image ? "<img src='" . Html::encode($coin->image) . "' width='18' height='18' class='rounded object-contain' onerror='this.style.display=\"none\"'>" : '') . '</td>';
        echo "<td class='px-3 py-2'>";
        echo "<div class='font-medium'><a href='/site/block?id={$coin->id}' class='{$shortCls}'>{$name}</a>";
        if (!$coin->auto_exchange) echo " <span class='inline-flex items-center px-1 py-0.5 rounded text-xs bg-red-50 text-red-600 dark:bg-red-900/30 dark:text-red-400'>no autotrade</span>";
        echo "</div>";
        echo "<div class='font-mono text-gray-400 dark:text-gray-500'>" . Html::encode($coin->algo) . "</div></td>";
        echo "<td class='px-3 py-2 text-right font-mono tabular-nums font-semibold text-gray-800 dark:text-gray-200'>{$reward} " . Html::encode($coin->symbol_show) . "</td>";
        echo "<td class='px-3 py-2 text-right font-mono tabular-nums text-gray-500 dark:text-gray-400' data='{$coin->difficulty}' title='POW {$coin->difficulty}'>{$difficulty}</td>";
        echo $coin->errors
            ? "<td class='px-3 py-2 text-right tabular-nums text-red-500 dark:text-red-400' title='" . Html::encode($coin->errors) . "'>{$height}</td>"
            : "<td class='px-3 py-2 text-right tabular-nums text-gray-600 dark:text-gray-300'>{$height}</td>";
        echo "<td class='px-3 py-2 text-right text-gray-500 dark:text-gray-400' title='" . Html::encode($ttfTitle) . "'>{$displayTtf}</td>";
        $auxOpacity = $coin->auxpow && $coin->auto_ready ? 'opacity-60' : '';
        echo "<td class='px-3 py-2 text-right font-mono tabular-nums text-gray-600 dark:text-gray-300 {$auxOpacity}' title='Network: {$nethash_str}' data='{$pool_hash}'>{$pool_hash_sfx}</td>";
        echo "<td class='px-3 py-2 text-right font-mono tabular-nums font-bold text-indigo-600 dark:text-indigo-400' data='{$btcmhd}'>{$btcmhd_fmt}</td>";
        echo "</tr>";
    }
}

echo '</tbody>';

// ── Table close ───────────────────────────────────────────────────────────────
if ($isLegacy) {
    echo '</table>';
    echo '<p style="font-size:.8em;">&nbsp;*** estimated average time to find a block at full pool speed<br>'
       . '&nbsp;** approximate from the last 5 minutes submitted shares<br>'
       . '&nbsp;* 24h estimation in mBTC/MH/day (GH/day for sha & blake)</p>';
    echo '</div></div><br>';
} elseif (!$isTailwind) {
    echo '</table></div></div>';
    echo '<div class="card-footer text-muted small py-1">'
       . '* mBTC/MH/day &nbsp;** last 5min shares &nbsp;*** at full pool speed</div></div>';
} else {
    echo '</table></div>';
    echo '<div class="px-4 py-2 border-t border-gray-200 dark:border-gray-700 text-xs text-gray-400 dark:text-gray-500">'
       . '* mBTC/MH/day &nbsp;&nbsp;** last 5min shares &nbsp;&nbsp;*** at full pool speed</div></div>';
}
