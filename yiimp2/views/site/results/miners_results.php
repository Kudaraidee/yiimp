<?php

/** @var yii\web\View  $this             */
/** @var string        $algo             */
/** @var string        $algoUnit         */
/** @var int           $algoCount        */
/** @var bool          $isAdmin          */
/** @var int           $totalWorkers     */
/** @var int           $totalExtranonce  */
/** @var string        $totalHashrateFmt */
/** @var int           $totalDonators    */
/** @var string        $totalBad         */
/** @var string        $totalAvg         */
/** @var string        $totalErrorTitle  */
/** @var array[]       $versionData      */

use yii\helpers\Html;

$isTailwind = Yii::$app->LayoutManager->isTailwind();
$isLegacy   = Yii::$app->LayoutManager->isLegacy();

if (!$algo) {
    echo $isTailwind
        ? '<p class="text-sm text-gray-400 dark:text-gray-500">No algo selected.</p>'
        : '<p class="text-muted">No algo selected.</p>';
    return;
}

$showAlgo  = ($algo === 'all');
$algoLabel = $showAlgo ? 'all algos' : $algo;

// Numeric key for tablesorter from values like "2.2 MH/s" or "62.4%".
$sortVal = function ($value): float {
    $value = trim((string) $value);
    if ($value === '' || $value === '-') return 0.0;
    if (!preg_match('/([0-9]+(?:\.[0-9]+)?)\s*([KMGTP]?)/i', $value, $m)) return 0.0;
    $mult = ['K' => 1e3, 'M' => 1e6, 'G' => 1e9, 'T' => 1e12, 'P' => 1e15];
    return (float) $m[1] * ($mult[strtoupper($m[2] ?? '')] ?? 1);
};

$versionsCount = count($versionData);
$cols          = ($isAdmin ? 6 : 5) + ($showAlgo ? 1 : 0);

$algoSet = [];
foreach ($versionData as $row) {
    if (!empty($row['algo'])) $algoSet[(string) $row['algo']] = true;
}

// ── Wrapper ───────────────────────────────────────────────────────────────────
if ($isLegacy) {
    echo "<div class='main-left-box'><div class='main-left-title'>Miners Version</div><div class='main-left-inner'>";
} elseif (!$isTailwind) {
    echo '<div class="card shadow-sm"><div class="card-header py-2"><strong class="small">Miners Version</strong></div>'
       . '<div class="card-body p-0">';
} else {
    echo '<div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm overflow-hidden">';
}

// ── Styles ────────────────────────────────────────────────────────────────────
echo <<<'CSS'
<style>
#maintable2.mv-table {
    width: 100% !important;
    border-collapse: collapse;
    table-layout: fixed;
    font-size: 11px;
}
#maintable2.mv-table th {
    font-size: 9px;
    font-weight: 700;
    letter-spacing: .04em;
    text-transform: uppercase;
    color: #8a94a6;
    background: #f8fafc;
    padding: 6px 8px;
    text-align: left;
    border-bottom: 1px solid #e8eef6;
    white-space: nowrap;
}
#maintable2.mv-table th.num { text-align: right; }
#maintable2.mv-table th .sort { opacity: .35; font-size: 8px; margin-left: 2px; }
#maintable2.mv-table td {
    padding: 7px 8px;
    border-bottom: 1px solid #eef2f7;
    vertical-align: middle;
}
#maintable2.mv-table tr:last-child td { border-bottom: 0; }
#maintable2.mv-table tbody tr:hover { background: #f7faff; }
#maintable2 .num { text-align: right; }
#maintable2 .mono {
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-variant-numeric: tabular-nums;
}
#maintable2 .big {
    display: block;
    font-size: 11.5px;
    font-weight: 700;
    color: #2b3d5b;
    line-height: 1.25;
}
#maintable2 .sub {
    display: block;
    font-size: 9px;
    color: #98a3b5;
    line-height: 1.35;
    margin-top: 2px;
}
#maintable2 .ver { display: flex; align-items: center; gap: 7px; min-width: 0; }
#maintable2 .vico {
    flex: 0 0 24px;
    height: 24px;
    border-radius: 6px;
    display: grid;
    place-items: center;
    font-size: 10px;
    font-weight: 800;
    color: #fff;
    background: linear-gradient(135deg, #4f79c8, #2c5aa0);
}
#maintable2 .vico.nb  { background: linear-gradient(135deg, #3fae7a, #1f8a58); }
#maintable2 .vico.lol { background: linear-gradient(135deg, #a874d8, #7a3fa0); }
#maintable2 .vico.tr  { background: linear-gradient(135deg, #e0a13a, #c47a16); }
#maintable2 .vname {
    font-size: 10.5px;
    font-weight: 700;
    color: #26385a;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
#maintable2 .vsub { font-size: 9px; color: #98a3b5; margin-top: 1px; white-space: nowrap; }
#maintable2 .chip {
    display: inline-block;
    padding: 0 4px;
    border-radius: 3px;
    font-size: 8px;
    font-weight: 700;
    line-height: 13px;
    vertical-align: 1px;
    margin-right: 4px;
    background: #e6f6ed;
    color: #187d46;
}
#maintable2 .chip.es { background: #eef4ff; color: #2c5aa0; }
#maintable2 .algo-badge {
    display: inline-block;
    padding: 2px 6px;
    border-radius: 5px;
    font-size: 9px;
    font-weight: 700;
    border: 1px solid var(--c, #d8e2ef);
    background: #f8fafc;
    color: #33405a;
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
#maintable2 .pct { color: #1f467f; }
#maintable2 .bar {
    display: block;
    height: 4px;
    border-radius: 3px;
    background: #eef2f7;
    margin-top: 4px;
    overflow: hidden;
}
#maintable2 .bar i {
    display: block;
    height: 100%;
    border-radius: 3px;
    background: linear-gradient(90deg, #5b8fd6, #2c5aa0);
}
#maintable2 .bar.alt i { background: linear-gradient(90deg, #6fc79a, #2fae5a); }
#maintable2 .reject.bad { color: #d64545; }
#maintable2 tr.total td { background: #f8fafc; font-weight: 700; color: #5b6675; }
#maintable2 .mv-head {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    padding: 8px 12px;
    border-bottom: 1px solid #e8eef6;
    background: #fbfdff;
}
.mv-head .pill {
    display: inline-block;
    padding: 2px 7px;
    border-radius: 10px;
    font-size: 9px;
    font-weight: 700;
    background: #eef4ff;
    color: #2c5aa0;
    font-family: ui-monospace, Menlo, monospace;
}
.mv-head .pill.gray { background: #f1f3f6; color: #7d8aa0; }
.mv-head .hash-total {
    margin-left: auto;
    font-family: ui-monospace, Menlo, monospace;
    font-size: 11px;
    font-weight: 700;
    color: #2b3d5b;
}
.mv-head .live { font-size: 9px; color: #5b6675; display: flex; align-items: center; gap: 6px; }
.mv-head .live i {
    width: 6px; height: 6px; border-radius: 50%; background: #2fae5a;
    box-shadow: 0 0 0 3px rgba(47,174,90,.15);
}
.mv-foot {
    padding: 6px 12px;
    border-top: 1px solid #eef2f7;
    background: #fbfdff;
    font-size: 9px;
    color: #98a3b5;
    display: flex;
    justify-content: space-between;
    gap: 8px;
}
.mv-foot b { color: #7d8aa0; }
</style>
CSS;

// ── Sorting ───────────────────────────────────────────────────────────────────
// Column 0 is Algo only in "All" scope; the version name stays text-sortable.
$verIdx   = $showAlgo ? 1 : 0;
$shareIdx = $verIdx + 1;
$workIdx  = $verIdx + 2;
$hashIdx  = $verIdx + 3;
$avgIdx   = $verIdx + 4;
$rejectIdx = $verIdx + 5;

$extract = "{$shareIdx}:function(n){return \$(n).attr('data')||\$(n).text()},"
         . "{$workIdx}:function(n){return \$(n).attr('data')||\$(n).text()},"
         . "{$hashIdx}:function(n){return \$(n).attr('data')||\$(n).text()},"
         . "{$avgIdx}:function(n){return \$(n).attr('data')||\$(n).text()}";
if ($isAdmin) {
    $extract .= ",{$rejectIdx}:function(n){return \$(n).attr('data')||\$(n).text()}";
}
$sortCfg = "{tableClass:'dataGrid2',textExtraction:{{$extract}}}";
if ($isLegacy) {
    Yii::$app->ViewUtils->showTableSorter('maintable2', $sortCfg);
} else {
    Yii::$app->ViewUtils->JavascriptReady("\$('#maintable2').tablesorter(" . $sortCfg . ");"
        . "\$('.tablesorter-header').not('.sorter-false').css('cursor','pointer');");
}

// ── Header strip ──────────────────────────────────────────────────────────────
echo '<div class="mv-head">';
echo '<span style="font-size:12px;font-weight:700;color:#1f467f">Miners Version</span>';
echo '<span class="pill">' . Html::encode($algoLabel) . '</span>';
if ($showAlgo) {
    $displayAlgoCount = isset($algoCount) ? (int) $algoCount : count($algoSet);
    echo '<span class="pill gray">' . $displayAlgoCount . ' algos</span>';
}
echo '<span class="pill gray">' . $versionsCount . ' versions</span>';
echo '<span class="pill gray">' . $totalWorkers . ' workers</span>';
echo '<span class="live"><i></i>live · 60s</span>';
echo '<span class="hash-total">' . Html::encode($totalHashrateFmt) . '</span>';
echo '</div>';

// ── Table open + thead ────────────────────────────────────────────────────────
echo '<div style="overflow-x:auto">';
echo '<table id="maintable2" class="mv-table">';
echo '<thead><tr>';
if ($showAlgo) {
    $w = $isAdmin
        ? ['algo' => 9, 'ver' => 21, 'share' => 14, 'work' => 11, 'hash' => 18, 'avg' => 11, 'reject' => 16]
        : ['algo' => 10, 'ver' => 26, 'share' => 17, 'work' => 13, 'hash' => 22, 'avg' => 12];
    echo '<th style="width:' . $w['algo'] . '%">Algo<span class="sort">↕</span></th>';
} else {
    $w = $isAdmin
        ? ['ver' => 26, 'share' => 15, 'work' => 12, 'hash' => 19, 'avg' => 13, 'reject' => 15]
        : ['ver' => 30, 'share' => 18, 'work' => 14, 'hash' => 22, 'avg' => 16];
}
echo '<th style="width:' . $w['ver'] . '%">Miner / Version</th>';
echo '<th style="width:' . $w['share'] . '%">Share<span class="sort">↕</span></th>';
echo '<th class="num" style="width:' . $w['work'] . '%">Workers<span class="sort">↕</span></th>';
echo '<th class="num" style="width:' . $w['hash'] . '%">Hashrate<span class="sort">↕</span></th>';
echo '<th class="num" style="width:' . $w['avg'] . '%">Avg / miner<span class="sort">↕</span></th>';
if ($isAdmin) echo '<th class="num" style="width:' . $w['reject'] . '%">Reject<span class="sort">↕</span></th>';
echo '</tr></thead><tbody>';

if (!$versionData) {
    echo '<tr><td colspan="' . $cols . '" style="text-align:center;color:#98a3b5;padding:14px">'
       . 'No miner versions reported for ' . Html::encode($algoLabel) . '.</td></tr>';
}

// ── Rows ──────────────────────────────────────────────────────────────────────
foreach ($versionData as $r) {
    $version    = (string) $r['version'];
    $count      = (int) $r['count'];
    $extranonce = (int) $r['extranonce'];
    $donators   = (int) $r['donators'];

    $pctNum  = max(0.0, min(100.0, $sortVal($r['percent'])));
    $pctTxt  = $r['percent'] !== '' && $r['percent'] !== '-' ? $r['percent'] : '—';
    $hashTxt = $r['hashrate'] !== '' ? $r['hashrate'] : '0 H/s';
    $avgTxt  = $r['avg'] !== '' ? $r['avg'] : '—';

    $lower = strtolower($version);
    $icoCls = 'vico';
    if (str_contains($lower, 'nbminer')) {
        $icoCls .= ' nb';
    } elseif (str_contains($lower, 'lolminer')) {
        $icoCls .= ' lol';
    } elseif (str_contains($lower, 't-rex') || str_contains($lower, 'trex')) {
        $icoCls .= ' tr';
    }
    $initial = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $version) ?: '?', 0, 1));
    $barCls  = $pctNum > 40 ? 'bar' : 'bar alt';

    echo '<tr>';

    // Algo (only in All scope)
    if ($showAlgo) {
        $rowAlgo   = (string) ($r['algo'] ?? '');
        $algoColor = Html::encode(Yii::$app->YiimpUtils->getAlgoColors($rowAlgo));
        echo "<td data='" . Html::encode($rowAlgo) . "'>";
        echo "<span class='algo-badge' style='--c:{$algoColor}' title='" . Html::encode($rowAlgo) . "'>"
           . Html::encode($rowAlgo !== '' ? $rowAlgo : '—') . '</span>';
        echo '</td>';
    }

    // Miner / Version
    echo '<td><div class="ver">';
    echo "<span class='{$icoCls}'>{$initial}</span>";
    echo '<div style="min-width:0">';
    echo '<div class="vname" title="' . Html::encode($version) . '">' . Html::encode($version) . '</div>';
    echo '<div class="vsub">';
    echo "<span class='chip es'>ES {$extranonce}</span>";
    echo "<span class='chip'>Donors {$donators}</span>";
    echo '</div></div></div></td>';

    // Share + bar
    echo "<td data='{$pctNum}'>";
    echo "<span class='big mono pct'>{$pctTxt}</span>";
    echo "<span class='{$barCls}'><i style='width:{$pctNum}%'></i></span>";
    echo '</td>';

    // Workers
    echo "<td class='num' data='{$count}'>";
    echo "<span class='big mono'>{$count}</span>";
    echo "<span class='sub mono'>{$extranonce} subscribed</span>";
    echo '</td>';

    // Hashrate
    echo "<td class='num' data='" . $sortVal($hashTxt) . "'>";
    echo "<span class='big mono'>" . Html::encode($hashTxt) . '</span>';
    echo '<span class="sub">of pool hashrate</span>';
    echo '</td>';

    // Avg
    echo "<td class='num' data='" . $sortVal($avgTxt) . "'>";
    echo "<span class='big mono'>" . Html::encode($avgTxt) . '</span>';
    echo '<span class="sub">per worker</span>';
    echo '</td>';

    // Reject (admin)
    if ($isAdmin) {
        $badTxt = $r['bad'] !== '' ? $r['bad'] : '-';
        $badNum = $sortVal($badTxt);
        echo "<td class='num" . ($badNum > 0 ? ' reject bad' : ' reject') . "' data='{$badNum}'"
           . " title='" . Html::encode($r['errorTitle']) . "'>";
        echo '<span class="big mono">' . Html::encode($badTxt) . '</span>';
        echo '<span class="sub">invalid</span>';
        echo '</td>';
    }

    echo '</tr>';
}

echo '</tbody>';

// ── Total row ─────────────────────────────────────────────────────────────────
$totalBar = $sortVal($totalHashrateFmt) > 0 ? 100 : 0;
echo '<tr class="total">';
if ($showAlgo) echo '<td>All algos</td>';
echo "<td>Total · {$versionsCount} versions</td>";
echo "<td data='{$totalBar}'><span class='big mono pct'>" . ($totalBar ? '100%' : '—') . '</span>'
   . "<span class='bar'><i style='width:{$totalBar}%'></i></span></td>";
echo "<td class='num mono' data='{$totalWorkers}'>{$totalWorkers}</td>";
echo "<td class='num mono' data='" . $sortVal($totalHashrateFmt) . "'>"
   . Html::encode($totalHashrateFmt) . '</td>';
echo "<td class='num mono' data='" . $sortVal($totalAvg) . "'>"
   . Html::encode($totalAvg !== '' ? $totalAvg : '—') . '</td>';
if ($isAdmin) {
    $totalBadNum = $sortVal($totalBad);
    echo "<td class='num mono reject" . ($totalBadNum > 0 ? ' bad' : '') . "' data='{$totalBadNum}'"
       . " title='" . Html::encode($totalErrorTitle) . "'>"
       . Html::encode($totalBad !== '' ? $totalBad : '—') . '</td>';
}
echo '</tr>';

echo '</table></div>';

// ── Footer + wrapper close ────────────────────────────────────────────────────
$foot = '<div class="mv-foot"><span>* hashrate approximate from last 5 min shares</span>'
      . '<span><b>ES</b> extranonce subscribe</span></div>';

if ($isLegacy) {
    echo $foot;
    echo '</div></div><br>';
} elseif (!$isTailwind) {
    echo $foot;
    echo '</div></div>';
} else {
    echo $foot;
    echo '</div>';
}
