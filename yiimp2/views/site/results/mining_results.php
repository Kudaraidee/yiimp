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
$scopeLabel  = ($algo === 'all') ? 'all algos' : $algo;
$rateText    = $total_rate ? $conv->Itoa2($total_rate) . 'h/s' : '—';
$coinLabel   = $count > 1 ? "{$count} coins" : '1 coin';

// ── Table open ────────────────────────────────────────────────────────────────
if ($isLegacy) {
    echo <<<'CSS'
<style>
#mining-card > .main-left-title {
    display: flex; align-items: center; justify-content: space-between; gap: 10px;
    background: #f8fafc; border-bottom: 1px solid #e8eef6; padding: 9px 12px;
}
#mining-card .mn-head-main { display: flex; align-items: baseline; gap: 9px; flex-wrap: wrap; min-width: 0; }
#mining-card .mn-title { font-size: 13px; font-weight: 800; color: #26385a; letter-spacing: .01em; }
#mining-card .mn-sub { font-size: 10px; color: #8a94a6; font-weight: 500; }
#mining-card .mn-scope {
    font-size: 9px; font-weight: 700; color: #2c5aa0; background: #eef4ff;
    border: 1px solid #d7e5ff; border-radius: 999px; padding: 2px 8px; white-space: nowrap;
}
#mining-card .main-left-inner { padding: 0; }
#mining-card .mn-summary {
    display: flex; gap: 18px; flex-wrap: wrap; padding: 8px 12px;
    background: #fcfdff; border-bottom: 1px solid #eef2f7;
    font-size: 10px; color: #8a94a6;
}
#mining-card .mn-summary b { color: #33405a; font-weight: 700; }
#mining-card .mn-summary i {
    display: inline-block; width: 6px; height: 6px; border-radius: 50%;
    background: #2fae5a; box-shadow: 0 0 0 3px rgba(47,174,90,.12); margin-right: 5px;
}
#maintable3.mn-table {
    width: 100% !important; border-collapse: collapse; table-layout: fixed;
    font-size: 11px; color: #334155;
}
#maintable3.mn-table th {
    background: #fff; padding: 7px 9px; text-align: right;
    font-size: 9px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase;
    color: #8a94a6; border-bottom: 1px solid #e8eef6; white-space: nowrap;
}
#maintable3.mn-table th:first-child, #maintable3.mn-table td:first-child { text-align: left; }
#maintable3.mn-table th.tablesorter-header { cursor: pointer; }
#maintable3.mn-table th.tablesorter-header:hover { color: #2c5aa0; }
#maintable3.mn-table td {
    padding: 8px 9px; border-bottom: 1px solid #eef2f7;
    text-align: right; vertical-align: middle;
}
#maintable3.mn-table tbody tr:last-child td { border-bottom: 0; }
#maintable3.mn-table tbody tr:hover { background: #f7faff; }
#maintable3.mn-table tr.mn-dim { opacity: .55; }
#maintable3.mn-table tr.mn-group td {
    background: #f8fafc; color: #8a94a6; font-size: 9px; font-weight: 700;
    letter-spacing: .06em; text-transform: uppercase; text-align: left; padding: 5px 9px;
}
#mining-card .mn-coin { display: flex; align-items: center; gap: 8px; min-width: 0; }
#mining-card .mn-ico {
    position: relative; flex: 0 0 26px; height: 26px; border-radius: 6px; overflow: hidden;
    display: grid; place-items: center; color: #fff; font-size: 7px; font-weight: 800;
    background: linear-gradient(135deg, #4f79c8, #2c5aa0);
}
#mining-card .mn-ico.firo { background: linear-gradient(135deg, #b06fd0, #7a3fa0); }
#mining-card .mn-ico img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: contain; background: #fff; }
#mining-card .mn-coin-main { min-width: 0; }
#mining-card .mn-link { font-size: 11px; font-weight: 700; color: #26385a; text-decoration: none; }
#mining-card .mn-link:hover { color: #2c5aa0; }
#mining-card .mn-link.warn { color: #c55; }
#mining-card .mn-meta { display: flex; gap: 5px; flex-wrap: wrap; margin-top: 2px; }
#mining-card .mn-algo, #mining-card .mn-pay {
    display: inline-block; border-radius: 3px; padding: 0 4px;
    font-size: 9px; font-weight: 700; line-height: 14px; white-space: nowrap;
}
#mining-card .mn-algo { background: #eef4ff; color: #2c5aa0; border: 1px solid #d7e5ff; }
#mining-card .mn-pay { background: #eef4ff; color: #2c5aa0; border: 1px solid #d7e5ff; }
#mining-card .mn-pay.manual { background: #fff7e8; color: #a86400; border-color: #ffe2b8; }
#mining-card .mn-val {
    display: block; font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-variant-numeric: tabular-nums; font-size: 11px; font-weight: 700; color: #2b3d5b;
    white-space: nowrap;
}
#mining-card .mn-cap { display: block; font-size: 9px; color: #98a3b5; margin-top: 1px; white-space: nowrap; }
#mining-card .mn-good .mn-val { color: #1f9d55; }
#mining-card .mn-muted .mn-val { color: #94a3b8; }
#mining-card .mn-val.mn-warn { color: #d9534f; }
#mining-card .mn-foot {
    display: flex; justify-content: space-between; gap: 10px; flex-wrap: wrap;
    padding: 7px 12px; background: #fcfdff; border-top: 1px solid #eef2f7;
    font-size: 9px; color: #98a3b5;
}
@media(max-width:720px){
    #mining-card > .main-left-title { flex-wrap: wrap; align-items: flex-start; }
    #mining-card .mn-head-main { flex: 1 1 100%; }
    #mining-card .mn-scope { flex: 0 0 auto; margin-left: auto; }
    #mining-card .main-left-inner > div { min-width: 0; }
    #maintable3.mn-table { table-layout: auto; min-width: 720px; }
    #mining-card .mn-summary { gap: 10px; }
}
</style>
CSS;
    echo "<div id='mining-card' class='main-left-box'>";
    echo "<div class='main-left-title'>";
    echo "<span class='mn-head-main'><span class='mn-title'>Mining</span>"
       . "<span class='mn-sub'>" . Html::encode($coinLabel . ' · ' . $rateText . ' · ' . $miner_count) . "</span></span>";
    echo "<span class='mn-scope'>" . Html::encode($scopeLabel) . "</span>";
    echo "</div><div class='main-left-inner'>";
    echo "<div class='mn-summary'><span><i></i><b>" . Html::encode($coinLabel) . "</b> in scope</span>"
       . "<span>Pool speed <b>" . Html::encode($rateText) . "</b></span>"
       . "<span>Active workers <b>" . (int) $worker . "</b></span></div>";
    echo "<div style='overflow-x:auto'>";
    echo "<table id='maintable3' class='mn-table'>";
    Yii::$app->ViewUtils->JavascriptReady("
        \$('#maintable3').tablesorter({
            tableClass: 'dataGrid2',
            textExtraction: {
                2: function(node){ return \$(node).attr('data') || \$(node).text(); },
                3: function(node){ return \$(node).attr('data') || \$(node).text(); },
                5: function(node){ return \$(node).attr('data') || \$(node).text(); },
                6: function(node){ return \$(node).attr('data') || \$(node).text(); }
            }
        });
        \$('.tablesorter-header').not('.sorter-false').css('cursor','pointer');
    ");
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
<th data-sorter="text" style="width:30%">Coin</th>
<th style="width:10%">Reward</th>
<th data-sorter="numeric" style="width:12%">Difficulty</th>
<th data-sorter="numeric" style="width:13%">Height</th>
<th style="width:12%">Est. block</th>
<th data-sorter="numeric" style="width:12%">Pool hash</th>
<th data-sorter="currency" style="width:11%">24h est</th>
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
            echo "<tr class='mn-group'><td colspan='7'>Merged mined coins</td></tr>";
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
    $network_display  = $util->display_network_hashrate($coin);
    $nethash_str      = $network_display ? 'network hash ' . strtoupper($conv->Itoa2($network_display)) . 'H/s' : '';
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
    $profitUnit = $util->get_algo_profit_unit($coin->algo);

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
    $blockUrl = Html::encode($coin->blockUrl);

    if ($isLegacy) {
        $paySymbol = Html::encode((string) ($coin->symbol ?: $coin->symbol_show));
        $showSymbol = Html::encode((string) $coin->symbol_show);
        $payBadge = $coin->auto_exchange
            ? "<span class='mn-pay' title='Rewards are automatically exchanged'>⇄ Auto-exchange</span>"
            : "<span class='mn-pay manual' title='Auto-exchange is off — rewards are paid in {$paySymbol}'>Paid in {$paySymbol}</span>";

        $coinHint = '';
        $linkClass = 'mn-link';
        if ($short) {
            $owed2  = $conv->bitcoinvaluetoa($owed - $coin->balance);
            $symbol = $coin->getOfficialSymbol();
            $coinHint = " title='We are short of this currency ({$owed2} {$symbol}).'";
            $linkClass .= ' warn';
        }

        $initials = strtoupper(substr((string) $coin->symbol, 0, 3));
        $icoClass = str_contains((string) $coin->algo, 'firo') ? 'mn-ico firo' : 'mn-ico';
        $img = !empty($coin->image)
            ? "<img src='" . Html::encode($coin->image) . "' alt='' onerror=\"this.remove()\">"
            : '';
        $profitClass = $btcmhd > 0 ? 'mn-good' : 'mn-muted';
        $heightClass = $coin->errors ? 'mn-warn' : '';
        $heightTitle = $coin->errors ? " title='" . Html::encode($coin->errors) . "'" : '';

        echo "<tr class='" . ($coin->auto_ready ? '' : 'mn-dim') . "'>";
        echo "<td><div class='mn-coin'><span class='{$icoClass}'>{$initials}{$img}</span><div class='mn-coin-main'>";
        echo "<a class='{$linkClass}' href='{$blockUrl}'{$coinHint}>{$name}</a>";
        echo "<div class='mn-meta'><span class='mn-algo'>" . Html::encode($coin->algo) . "</span>{$payBadge}</div>";
        echo "</div></div></td>";
        echo "<td class='mn-val'><span>{$reward} {$showSymbol}</span><span class='mn-cap'>per block</span></td>";
        echo "<td data='" . Html::encode((string) $coin->difficulty) . "' title='POW " . Html::encode((string) $coin->difficulty) . "'>"
           . "<span class='mn-val'>{$difficulty}</span><span class='mn-cap'>difficulty</span></td>";
        echo "<td data='" . Html::encode((string) $coin->block_height) . "'{$heightTitle}>"
           . "<span class='mn-val{$heightClass}'>{$height}</span><span class='mn-cap'>height</span></td>";
        echo "<td title='" . Html::encode($ttfTitle) . "'>"
           . "<span class='mn-val'>" . ($displayTtf !== '' ? Html::encode($displayTtf) : '—') . "</span>"
           . "<span class='mn-cap'>est. time</span></td>";
        echo "<td data='" . Html::encode((string) $pool_hash) . "' title='Network: " . Html::encode($nethash_str) . "'>"
           . "<span class='mn-val'>" . ($pool_hash_sfx !== '' ? Html::encode($pool_hash_sfx) : '—') . "</span>"
           . "<span class='mn-cap'>pool hash</span></td>";
        echo "<td class='{$profitClass}' data='" . Html::encode((string) $btcmhd) . "'>"
           . "<span class='mn-val'>{$btcmhd_fmt}</span><span class='mn-cap'>mBTC/{$profitUnit}/day</span></td>";
        echo "</tr>";

    } elseif (!$isTailwind) {
        $dimmed = !$coin->auto_ready ? 'opacity-50' : '';
        echo "<tr class='{$dimmed}'>";
        echo '<td>' . ($coin->image ? "<img src='" . Html::encode($coin->image) . "' width='18' style='object-fit:contain' onerror='this.style.display=\"none\"'>" : '') . '</td>';
        if ($short) {
            echo "<td class='small'><a href='{$blockUrl}' class='fw-bold text-danger' title='Short supply'>{$name}</a> <span class='text-muted'>({$coin->algo})</span></td>";
        } else {
            echo "<td class='small'><a href='{$blockUrl}' class='fw-bold'>{$name}</a> <span class='text-muted'>({$coin->algo})</span>";
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
        echo "<div class='font-medium'><a href='{$blockUrl}' class='{$shortCls}'>{$name}</a>";
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
    echo '</table></div>';
    echo "<div class='mn-foot'><span>* 24h estimate in mBTC/MH/day · per GH for sha, blake &amp; nebhash</span>"
       . "<span>** pool hash from last 5min shares<br>*** estimated time at full pool speed</span></div>";
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
