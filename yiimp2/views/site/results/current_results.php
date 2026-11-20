<?php

use yii\helpers\Html;
use app\models\Coins;
use app\models\Workers;
use app\models\Stratums;

$isTailwind  = Yii::$app->LayoutManager->isTailwind();
$isLegacy    = Yii::$app->LayoutManager->isLegacy();
$conv        = Yii::$app->ConversionUtils;
$defaultalgo = Yii::$app->session->get('yaamp-algo');
$showestimates = true;

// ── Determine the most profitable algo (kept from the old table) ──────────────
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

// The Algorithm bar on /site/mining drives this table.
$selectedAlgo = ($defaultalgo && $defaultalgo !== 'all') ? $defaultalgo : null;

// ── Wrapper ───────────────────────────────────────────────────────────────────
if ($isLegacy) {
    echo "<div class='main-left-box'><div class='main-left-title'>"
       . "<span class='mn-head-main'><span>Pool Status</span>"
       . "<span class='mn-sub'>coin list</span></span>"
       . "<span class='mn-scope'>follows Algorithm bar</span></div><div class='main-left-inner'>";
} elseif (!$isTailwind) {
    echo '<div class="card shadow-sm mb-3"><div class="card-header py-2 small fw-semibold">'
       . '<i class="bi bi-bar-chart me-1"></i>Pool Status</div><div class="card-body p-0">';
} else {
    echo '<div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm overflow-hidden mb-4">';
    echo '<div class="px-4 py-2.5 border-b border-gray-200 dark:border-gray-700 flex items-center gap-2">';
    echo '<i data-lucide="bar-chart-2" class="w-4 h-4 text-gray-400 shrink-0"></i>';
    echo '<span class="text-sm font-semibold text-gray-800 dark:text-gray-100">Pool Status</span>';
    echo '</div>';
}

// ── Coin-list table styles ────────────────────────────────────────────────────
echo <<<'CSS'
<style>
#maintable1.ps-table {
    table-layout: fixed !important;
    width: 100% !important;
    border-collapse: collapse;
    font-size: 11px;
}
#maintable1.ps-table th {
    font-size: 9px;
    font-weight: 700;
    letter-spacing: .04em;
    text-transform: uppercase;
    color: #8a94a6;
    background: #f8fafc;
    padding: 6px 7px;
    text-align: left;
    border-bottom: 1px solid #e8eef6;
    white-space: nowrap;
}
#maintable1.ps-table th.num { text-align: right; }
#maintable1.ps-table th .sort { opacity: .35; font-size: 8px; margin-left: 2px; }
#maintable1.ps-table td {
    padding: 7px;
    border-bottom: 1px solid #eef2f7;
    vertical-align: middle;
}
#maintable1.ps-table tr:last-child td { border-bottom: 0; }
#maintable1.ps-table tbody tr:hover { background: #f7faff; }
#maintable1.ps-table tr.is-active {
    background: #eef4ff;
    box-shadow: inset 3px 0 0 #2c5aa0;
}
#maintable1 .num { text-align: right; }
#maintable1 .big {
    display: block;
    font-size: 11px;
    font-weight: 700;
    color: #2b3d5b;
    line-height: 1.25;
}
#maintable1 .sub {
    display: block;
    font-size: 9px;
    color: #98a3b5;
    line-height: 1.35;
    margin-top: 1px;
}
#maintable1 .mono {
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-variant-numeric: tabular-nums;
}
#maintable1 .good { color: #1f9d55; }
#maintable1 .coin { display: flex; align-items: center; gap: 7px; min-width: 0; }
#maintable1 .coin-ico {
    position: relative;
    flex: 0 0 24px;
    height: 24px;
    border-radius: 6px;
    display: grid;
    place-items: center;
    font-size: 7px;
    font-weight: 800;
    color: #fff;
    background: linear-gradient(135deg, #4f79c8, #2c5aa0);
    overflow: hidden;
}
#maintable1 .coin-ico.firo { background: linear-gradient(135deg, #b06fd0, #7a3fa0); }
#maintable1 .coin-ico img {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: contain;
    background: #fff;
    border-radius: 6px;
}
#maintable1 .coin-name {
    font-size: 10.5px;
    font-weight: 700;
    color: #26385a;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
#maintable1 .coin-sym { font-size: 9px; color: #8a94a6; margin-left: 3px; }
#maintable1 .coin-sub { font-size: 9px; color: #98a3b5; white-space: nowrap; margin-top: 1px; }
#maintable1 .tag {
    display: inline-block;
    padding: 0 5px;
    border-radius: 3px;
    font-size: 9px;
    font-weight: 700;
    line-height: 14px;
    vertical-align: 1px;
    margin-right: 4px;
    background: #eef4ff;
    color: #2c5aa0;
    border: 1px solid #d7e5ff;
}
#maintable1 .tag.manual { background: #fff7e8; color: #a86400; border-color: #ffe2b8; }
#maintable1 .algo {
    display: inline-block;
    padding: 2px 6px;
    border-radius: 5px;
    font-size: 9px;
    font-weight: 700;
    border: 1px solid var(--c, #d8e2ef);
    background: #f8fafc;
    color: #33405a;
    cursor: pointer;
}
#maintable1 .port { display: block; font-size: 9px; color: #8a94a6; margin-top: 3px; }
#maintable1 .rpc { color: #5b8fd6; font-weight: 600; }
#maintable1 .all-row td { background: #f8fafc; font-weight: 700; color: #5b6675; }
#maintable1 .ps-foot {
    padding: 6px 12px;
    border-top: 1px solid #eef2f7;
    background: #fbfdff;
    font-size: 9px;
    color: #98a3b5;
    display: flex;
    justify-content: space-between;
    gap: 8px;
}
#maintable1 .ps-foot b { color: #7d8aa0; }
</style>
CSS;

// ── Sorting ───────────────────────────────────────────────────────────────────
$sortConfig = "{tableClass:'dataGrid2',textExtraction:{"
    . "2:function(node){return \$(node).attr('data')||\$(node).text()},"
    . "3:function(node){return \$(node).attr('data')||\$(node).text()},"
    . "4:function(node){return \$(node).attr('data')||\$(node).text()},"
    . "5:function(node){return \$(node).attr('data')||\$(node).text()}"
    . "}}";

if ($isLegacy) {
    Yii::$app->ViewUtils->showTableSorter('maintable1', $sortConfig);
} else {
    Yii::$app->ViewUtils->JavascriptReady("\$('#maintable1').tablesorter(" . $sortConfig . ");"
        . "\$('.tablesorter-header').not('.sorter-false').css('cursor','pointer');");
}

// ── Table open + header ───────────────────────────────────────────────────────
echo '<div class="" style="overflow-x:auto;">';
echo '<table id="maintable1" class="ps-table">';
echo '<thead><tr>';
echo '<th style="width:34%">Coin</th>';
echo '<th style="width:15%">Algo</th>';
echo '<th class="num" style="width:12%">Workers<span class="sort">↕</span></th>';
echo '<th class="num" style="width:15%">Pool hash<span class="sort">↕</span></th>';
echo '<th class="num" style="width:13%">Network<span class="sort">↕</span></th>';
if ($showestimates) echo '<th class="num" style="width:11%">24h est<span class="sort">↕</span></th>';
echo '</tr></thead><tbody>';

$total_coins   = 0;
$total_workers = 0;
$total_solo    = 0;
$total_pool    = 0.0;

// ── One row per coin ──────────────────────────────────────────────────────────
foreach ($algos as $item) {
    $algo = $item[1];
    if ($selectedAlgo && $algo !== $selectedAlgo) continue;

    $coins = Coins::find()
        ->where(['enable' => 1, 'visible' => 1, 'algo' => $algo])
        ->orderBy(['index_avg' => SORT_DESC]);
    if (!$coins->count()) continue;

    $workers      = Workers::find()->where(['algo' => $algo])->andFilterWhere(['not like', 'password', 'm=solo'])->count();
    $solo_workers = Workers::find()->where(['algo' => $algo])->andFilterWhere(['like', 'password', 'm=solo'])->count();

    $fees      = Yii::$app->YiimpUtils->yiimp_fee($algo);
    $fees_solo = Yii::$app->YiimpUtils->yiimp_fee_solo($algo);
    $algoColor = Html::encode(Yii::$app->YiimpUtils->getAlgoColors($algo));
    $isSelected = ($defaultalgo === $algo);

    foreach ($coins->all() as $coin) {
        $name   = Html::encode(substr($coin->name, 0, 20));
        $symbol = $coin->getOfficialSymbol();
        $symEnc = Html::encode((string) $symbol);

        $coin_stratum = Stratums::find()->where(['algo' => $algo, 'symbol' => $symbol]);
        $port_db      = $coin_stratum->count() >= 1 ? $coin_stratum->one() : null;
        $display_port = $port_db ? (int) $port_db->port : '—';

        $min_payout    = max(floatval(YIIMP_PAYMENTS_MINI), floatval($coin->payout_min));

        $wq = (new \yii\db\Query())->select(['count(id)'])->from('workers')
            ->where(['algo' => $algo, 'pid' => ($port_db ? $port_db->pid : 0)]);
        $workers_coins      = (int) (clone $wq)->andWhere(['not like', 'password', 'm=solo'])->scalar();
        $solo_workers_coins = (int) (clone $wq)->andWhere(['like', 'password', 'm=solo'])->scalar();
        $workers_display    = $workers_coins + $solo_workers_coins;

        $pool_hash       = (float) Yii::$app->YiimpUtils->coin_rate($coin->id);
        $pool_shared     = (float) Yii::$app->YiimpUtils->coin_shared_rate($coin->id);
        $pool_solo       = (float) Yii::$app->YiimpUtils->coin_solo_rate($coin->id);
        $pool_hash_sfx   = $pool_hash   ? $conv->Itoa2($pool_hash)   . 'h/s' : '0 h/s';
        $pool_shared_sfx = $pool_shared ? $conv->Itoa2($pool_shared) . 'h/s' : '0 h/s';
        $pool_solo_sfx   = $pool_solo   ? $conv->Itoa2($pool_solo)   . 'h/s' : '0 h/s';

        $nethash     = Yii::$app->YiimpUtils->display_network_hashrate($coin);
        $nethash_sfx = $nethash ? strtoupper($conv->Itoa2($nethash)) . 'H/s' : '—';

        $btcmhd = $conv->mbitcoinvaluetoa(Yii::$app->YiimpUtils->yiimp_profitability($coin));
        $isBest = ($algo === $best_algo);

        $initials = strtoupper(substr($coin->symbol, 0, 3));
        $img = !empty($coin->image)
            ? "<img src='" . Html::encode($coin->image) . "' alt='' onerror=\"this.remove()\">"
            : '';
        $icoClass = str_contains($algo, 'firo') ? 'coin-ico firo' : 'coin-ico';

        echo '<tr class="' . ($isSelected ? 'is-active' : '') . '">';

        // Coin
        echo '<td>';
        echo '<div class="coin">';
        echo "<span class='{$icoClass}'>{$initials}{$img}</span>";
        echo '<div style="min-width:0">';
        echo "<div class='coin-name'>{$name}<span class='coin-sym'>{$symEnc}</span></div>";
        echo '<div class="coin-sub">';
        echo $coin->auto_exchange
            ? '<span class="tag" title="Rewards are automatically exchanged">⇄ Auto-exchange</span>'
            : '<span class="tag manual" title="Auto-exchange is off — rewards are paid in ' . $symEnc . '">Paid in ' . $symEnc . '</span>';
        echo 'Min ' . Html::encode((string) $min_payout) . ' ' . $symEnc;
        echo ' · Fee ' . Html::encode((string) $fees) . '%/' . Html::encode((string) $fees_solo) . '%';
        echo '</div></div></div></td>';

        // Algo + port
        echo '<td>';
        echo "<span class='algo' style='--c:{$algoColor}' title='Select " . Html::encode($algo) . "'"
           . " onclick='if(typeof select_algo===\"function\")select_algo(" . json_encode($algo) . ")'>"
           . Html::encode($algo) . '</span>';
        echo "<span class='port mono'>:{$display_port}</span>";
        echo '</td>';

        // Workers
        echo "<td class='num' data='{$workers_display}'>";
        echo "<span class='big mono'>{$workers_display}</span>";
        echo "<span class='sub mono'>{$workers_coins}/{$solo_workers_coins} S/So</span>";
        echo '</td>';

        // Pool hash
        echo "<td class='num' data='{$pool_hash}'>";
        echo "<span class='big mono'>{$pool_hash_sfx}</span>";
        echo "<span class='sub mono'>{$pool_shared_sfx}/{$pool_solo_sfx} shared/solo</span>";
        echo '</td>';

        // Network
        echo "<td class='num' data='" . (float) $nethash . "'>";
        echo "<span class='big mono rpc'>{$nethash_sfx}</span>";
        echo '<span class="sub">RPC</span>';
        echo '</td>';

        // Estimate
        if ($showestimates) {
            echo "<td class='num" . ($isBest ? ' good' : '') . "' data='{$btcmhd}'"
               . " title='best algo estimate'>";
            echo "<span class='big mono'>" . ($isBest ? $btcmhd . '*' : $btcmhd) . '</span>';
            echo '<span class="sub">mBTC/MH</span>';
            echo '</td>';
        }

        echo '</tr>';

        $total_pool += $pool_hash;
    }

    $total_coins   += $coins->count();
    $total_workers += $workers;
    $total_solo    += $solo_workers;
}

// ── Summary footer row ────────────────────────────────────────────────────────
$total_pool_fmt = $total_pool > 0 ? $conv->Itoa2($total_pool) . 'h/s' : '0 h/s';
echo '<tr class="all-row">';
echo "<td>All · {$total_coins} coins</td>";
echo '<td>—</td>';
echo "<td class='num mono' data='{$total_workers}'>{$total_workers}</td>";
echo "<td class='num mono' data='{$total_pool}'>{$total_pool_fmt}</td>";
echo '<td class="num">—</td>';
if ($showestimates) echo '<td class="num">—</td>';
echo '</tr>';

echo '</tbody></table></div>';

// ── Footer note + wrapper close ───────────────────────────────────────────────
$note = '* mBTC/MH/day, per GH for sha &amp; blake';
if ($isLegacy) {
    echo "<div class='ps-foot'><span>" . $note . "</span><span>live · 60s</span></div>";
    echo '</div></div><br>';
} elseif (!$isTailwind) {
    echo '<div class="card-footer text-muted small py-1">' . $note . '</div>';
    echo '</div></div>';
} else {
    echo '<div class="px-4 py-2 border-t border-gray-200 dark:border-gray-700 text-xs text-gray-400 dark:text-gray-500">'
       . $note . '</div></div>';
}
