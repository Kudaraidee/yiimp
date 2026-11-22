<?php

use yii\helpers\Html;

if (!$coin) return Yii::$app->controller->goBack();

$this->title = 'Peers — ' . $coin->name;
$isTailwind  = Yii::$app->LayoutManager->isTailwind();
$lines       = $coin->getPeerAddresses();

// Split the text lines returned by Coins::getPeerAddresses() into display rows.
$peerRows     = [];
$addressCount = 0;
$reported     = 0;
$client       = '';
$source       = $addressCount ? 'admin_peers' : '—';
foreach ($lines as $line) {
    $line = trim((string) $line);
    if ($line === '') continue;
    if (preg_match('/^(addnode|addpeer)=(.+)$/', $line, $m)) {
        $addressCount++;
        $peerRows[] = ['kind' => $m[1], 'value' => trim($m[2])];
        continue;
    }
    if (preg_match('/^Peer count:\s*(\d+)/i', $line, $m)) {
        $reported = (int) $m[1];
        $source   = 'net_peerCount';
        continue;
    }
    if (preg_match('/^Client:\s*(.+)$/i', $line, $m)) {
        $client = trim($m[1]);
        $source = 'net_peerCount';
        continue;
    }
    $peerRows[] = ['kind' => 'info', 'value' => $line];
}
if (!$reported) $reported = $addressCount;
if ($addressCount) $source = 'admin_peers';

$symbol = !empty($coin->symbol) ? $coin->symbol : '—';
$algo   = !empty($coin->algo)   ? $coin->algo   : '—';
$rpc    = !empty($coin->rpcencoding) ? $coin->rpcencoding : '—';
?>

<?php if ($isTailwind): ?>
<!DOCTYPE html>
<html lang="en" class="<?= !empty($_COOKIE['yiimp_darkmode']) ? 'dark' : '' ?>">
<head>
    <meta charset="utf-8">
    <title><?= Html::encode($this->title) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100 p-4 text-sm">
<div class="max-w-4xl mx-auto space-y-4">
    <div class="flex items-center gap-3 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg px-4 py-3">
        <span class="w-2 h-2 rounded-full bg-green-500"></span>
        <div class="flex flex-col leading-tight">
            <b class="text-gray-800 dark:text-gray-100 text-sm">Node Peers</b>
            <small class="text-gray-400 text-[10px]">explorer · live from RPC</small>
        </div>
        <span class="ml-1 px-2 py-0.5 rounded-full bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 text-[10px] font-bold"><?= Html::encode($symbol) ?></span>
        <span class="ml-auto px-2 py-0.5 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-500 text-[10px] font-bold"><?= $addressCount ?> NODES</span>
    </div>
    <div class="bg-indigo-50/50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 border-l-4 border-indigo-500 rounded-lg px-4 py-2 flex flex-wrap gap-5 text-xs text-gray-600 dark:text-gray-300">
        <span>Coin <b class="text-gray-800 dark:text-gray-100"><?= Html::encode($coin->name) ?></b></span>
        <span>Algo <b class="text-gray-800 dark:text-gray-100"><?= Html::encode($algo) ?></b></span>
        <span>RPC <b class="text-gray-800 dark:text-gray-100"><?= Html::encode($rpc) ?></b></span>
        <span>Source <b class="text-gray-800 dark:text-gray-100"><?= Html::encode($source) ?></b></span>
    </div>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-3">
            <div class="text-[9px] font-bold uppercase tracking-wider text-gray-400">Nodes</div>
            <div class="font-mono text-base font-bold text-indigo-600 mt-1"><?= $addressCount ?></div>
        </div>
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-3">
            <div class="text-[9px] font-bold uppercase tracking-wider text-gray-400">Reported</div>
            <div class="font-mono text-base font-bold text-green-600 mt-1"><?= (int) $reported ?></div>
        </div>
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-3">
            <div class="text-[9px] font-bold uppercase tracking-wider text-gray-400">Encoding</div>
            <div class="font-mono text-base font-bold text-gray-700 dark:text-gray-200 mt-1"><?= Html::encode($rpc) ?></div>
        </div>
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-3">
            <div class="text-[9px] font-bold uppercase tracking-wider text-gray-400">Client</div>
            <div class="font-mono text-xs text-gray-700 dark:text-gray-200 mt-1 break-all"><?= Html::encode($client !== '' ? $client : '—') ?></div>
        </div>
    </div>
    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
        <div class="px-4 py-2 bg-gray-50 dark:bg-gray-900/40 border-b border-gray-200 dark:border-gray-700 font-bold text-gray-700 dark:text-gray-200 text-sm">Peer list</div>
        <pre class="p-4 text-xs font-mono overflow-auto whitespace-pre text-gray-700 dark:text-gray-300"><?= Html::encode(implode("\n", $lines)) ?></pre>
    </div>
</div>
</body>
</html>
<?php else: ?>
<style>
body { margin: 4px; }
#explorer_peers {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    color: #4a586c; display: flex; flex-direction: column; gap: 14px;
}
#explorer_peers .ep-toolbar {
    display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
    padding: 9px 12px; border: 1px solid #d8e2ef; border-radius: 7px;
    background: #fff; box-shadow: 0 1px 2px rgba(20,40,80,.04);
}
#explorer_peers .ep-live-dot {
    width: 8px; height: 8px; border-radius: 50%; background: #2fae5a;
    box-shadow: 0 0 0 3px rgba(47,174,90,.15);
}
#explorer_peers .ep-brand { display: flex; flex-direction: column; line-height: 1.25; }
#explorer_peers .ep-brand b { color: #26385a; font-size: 13px; }
#explorer_peers .ep-brand small { color: #8a94a6; font-size: 9px; }
#explorer_peers .mc-badge {
    display: inline-block; padding: 2px 7px; border-radius: 999px;
    background: #eef4ff; border: 1px solid #d7e5ff; color: #2c5aa0;
    font-size: 9px; font-weight: 700; line-height: 14px; white-space: nowrap;
}
#explorer_peers .ep-count { margin-left: auto; color: #8a94a6; font-size: 10px; font-weight: 700; letter-spacing: .08em; }

#explorer_peers .ep-summary {
    background: #f8fbff; border: 1px solid #d8e2ef; border-left: 3px solid #2c5aa0;
    border-radius: 6px; padding: 8px 14px; display: flex; gap: 22px; flex-wrap: wrap;
    font-size: 12px;
}
#explorer_peers .ep-summary b { color: #223a5e; }
#explorer_peers .ep-summary .sep { color: #ccd4e0; }

#explorer_peers .ep-kpis {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 12px;
}
#explorer_peers .ep-kpi {
    border: 1px solid #e8eef6; border-radius: 6px; background: #fcfdff;
    padding: 8px 10px; min-width: 0;
}
#explorer_peers .ep-kpi .k-label {
    display: block; font-size: 9px; font-weight: 800; letter-spacing: .06em;
    text-transform: uppercase; color: #98a3b5;
}
#explorer_peers .ep-kpi .k-value {
    display: block; margin-top: 3px; font-family: monospace; font-size: 15px;
    font-weight: 700; color: #26385a; overflow-wrap: anywhere;
}
#explorer_peers .ep-kpi .k-sub { display: block; margin-top: 2px; font-size: 9px; color: #8a94a6; }
#explorer_peers .ep-kpi.accent .k-value { color: #2c5aa0; }
#explorer_peers .ep-kpi.good .k-value { color: #2fae5a; }

#explorer_peers .main-left-box {
    border: 1px solid #d8e2ef; border-radius: 7px; background: #fff;
    box-shadow: 0 1px 2px rgba(20,40,80,.04); overflow: hidden;
}
#explorer_peers .main-left-title {
    display: flex; align-items: center; justify-content: space-between; gap: 10px;
    min-height: 34px; padding: 9px 12px; background: #f8fafc;
    border-bottom: 1px solid #e8eef6; color: #26385a; font-size: 13px; font-weight: 800;
}
#explorer_peers .mn-head-main { display: flex; align-items: baseline; gap: 9px; flex-wrap: wrap; min-width: 0; }
#explorer_peers .mn-sub { font-size: 10px; color: #8a94a6; font-weight: 500; }
#explorer_peers .main-left-inner { padding: 0; }

#explorer_peers .ep-filter {
    display: flex; align-items: center; gap: 10px; padding: 8px 12px;
    border-bottom: 1px solid #e8eef6; background: #fff;
}
#explorer_peers .ep-filter label {
    font-size: 10px; font-weight: 700; letter-spacing: .12em;
    color: #8a94a6; text-transform: uppercase;
}
#explorer_peers .ep-filter input {
    width: 230px; padding: 4px 9px; border: 1px solid #dfe5ec; border-radius: 7px;
    background: #fafbfc; font: 11px/1.4 inherit; color: #26385a; outline: 0;
}
#explorer_peers .ep-filter input:focus { border-color: #a9c4ea; background: #fff; }
#explorer_peers .ep-shown { font-size: 10px; color: #8a94a6; font-variant-numeric: tabular-nums; }
#explorer_peers .ep-copy-all {
    margin-left: auto; padding: 4px 10px; border-radius: 7px; cursor: pointer;
    border: 1px solid #d7e5ff; background: #eef4ff; color: #2c5aa0;
    font-size: 10px; font-weight: 700;
}
#explorer_peers .ep-copy-all:hover { background: #e3eeff; }

#explorer_peers table.dataGrid { width: 100%; border-collapse: collapse; font-size: 11px; }
#explorer_peers table.dataGrid th {
    padding: 6px 8px; background: #f8fafc; border-bottom: 1px solid #e8eef6;
    color: #8a94a6; font-size: 9px; font-weight: 700; letter-spacing: .05em;
    text-transform: uppercase; text-align: left; white-space: nowrap;
}
#explorer_peers table.dataGrid th:first-child,
#explorer_peers table.dataGrid td.num { text-align: right; width: 42px; }
#explorer_peers table.dataGrid td {
    padding: 5px 8px; border-bottom: 1px solid #eef2f7; vertical-align: middle;
}
#explorer_peers table.dataGrid tr:hover td { background: #f7faff; }
#explorer_peers td.mono { font-family: monospace; color: #33405a; overflow-wrap: anywhere; }
#explorer_peers td.num { font-family: monospace; color: #8a94a6; }
#explorer_peers .ep-type {
    display: inline-block; padding: 2px 7px; border-radius: 999px;
    background: #eef4ff; border: 1px solid #d7e5ff; color: #2c5aa0;
    font-size: 9px; font-weight: 700; text-transform: uppercase;
}
#explorer_peers .ep-type.info { background: #f8fafc; border-color: #e8eef6; color: #8a94a6; }
#explorer_peers .ep-copy {
    padding: 2px 8px; border-radius: 999px; cursor: pointer;
    border: 1px solid #dfe7f2; background: #f8fafc; color: #4a586c; font-size: 10px;
}
#explorer_peers .ep-copy:hover { background: #eef4ff; border-color: #c9d9f5; color: #2c5aa0; }
#explorer_peers .ep-empty { padding: 18px; text-align: center; color: #98a3b5; font-size: 11px; }
#explorer_peers pre.ep-config {
    margin: 0; padding: 12px; font-family: monospace; font-size: 11px; color: #33405a;
    background: #fcfdff; overflow-x: auto; white-space: pre;
}
@media (max-width: 900px) {
    #explorer_peers .ep-count { margin-left: 0; }
    #explorer_peers .ep-summary { gap: 12px; }
    #explorer_peers .ep-filter input { width: 100%; }
}
</style>

<div id="explorer_peers">

    <div class="ep-toolbar">
        <span class="ep-live-dot"></span>
        <span class="ep-brand"><b>Node Peers</b><small>explorer · live from RPC</small></span>
        <span class="mc-badge"><?= Html::encode($symbol) ?></span>
        <span class="ep-count"><?= $addressCount ?> NODES</span>
    </div>

    <div class="ep-summary">
        <span>Coin <b><?= Html::encode($coin->name) ?></b></span>
        <span class="sep">|</span>
        <span>Algo <b><?= Html::encode($algo) ?></b></span>
        <span class="sep">|</span>
        <span>RPC <b><?= Html::encode($rpc) ?></b></span>
        <span class="sep">|</span>
        <span>Source <b><?= Html::encode($source) ?></b></span>
    </div>

    <div class="ep-kpis">
        <div class="ep-kpi accent">
            <span class="k-label">Nodes</span>
            <span class="k-value"><?= $addressCount ?></span>
            <span class="k-sub">addresses from RPC</span>
        </div>
        <div class="ep-kpi good">
            <span class="k-label">Reported</span>
            <span class="k-value"><?= (int) $reported ?></span>
            <span class="k-sub">node peer count</span>
        </div>
        <div class="ep-kpi">
            <span class="k-label">Encoding</span>
            <span class="k-value"><?= Html::encode($rpc) ?></span>
            <span class="k-sub">wallet RPC</span>
        </div>
        <div class="ep-kpi">
            <span class="k-label">Client</span>
            <span class="k-value" title="<?= Html::encode($client) ?>"><?= Html::encode($client !== '' ? $client : '—') ?></span>
            <span class="k-sub">reported version</span>
        </div>
    </div>

    <div class="main-left-box">
        <div class="main-left-title">
            <span class="mn-head-main">
                <span>Peer list</span>
                <span class="mn-sub">addnode / addpeer entries</span>
            </span>
            <span class="mc-badge"><?= count($peerRows) ?> ROWS</span>
        </div>
        <div class="ep-filter">
            <label for="ep-filter-input">Filter</label>
            <input type="search" id="ep-filter-input" placeholder="address, type…" autocomplete="off">
            <span class="ep-shown" id="ep-shown">showing <?= count($peerRows) ?> / <?= count($peerRows) ?></span>
            <button type="button" class="ep-copy-all" data-copy="<?= Html::encode(implode("\n", $lines)) ?>">Copy all</button>
        </div>
        <div class="main-left-inner">
            <?php if (!empty($peerRows)): ?>
            <table class="dataGrid" id="ep-peer-table">
                <thead><tr>
                    <th>#</th>
                    <th>Type</th>
                    <th>Address / Value</th>
                    <th></th>
                </tr></thead>
                <tbody>
                <?php foreach ($peerRows as $i => $row): ?>
                <tr data-search="<?= Html::encode(strtolower($row['kind'] . ' ' . $row['value'])) ?>">
                    <td class="num"><?= $i + 1 ?></td>
                    <td><span class="ep-type <?= $row['kind'] === 'info' ? 'info' : '' ?>"><?= Html::encode($row['kind']) ?></span></td>
                    <td class="mono"><?= Html::encode($row['value']) ?></td>
                    <td class="num"><button type="button" class="ep-copy" data-copy="<?= Html::encode($row['value']) ?>">Copy</button></td>
                </tr>
                <?php endforeach ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="ep-empty">No peers available.</div>
            <?php endif ?>
        </div>
    </div>

    <?php if (!empty($lines)): ?>
    <div class="main-left-box">
        <div class="main-left-title">
            <span class="mn-head-main">
                <span>addnode config</span>
                <span class="mn-sub">copy into your node configuration</span>
            </span>
            <span class="mc-badge"><?= count($lines) ?> LINES</span>
        </div>
        <div class="main-left-inner">
            <pre class="ep-config"><?= Html::encode(implode("\n", $lines)) ?></pre>
        </div>
    </div>
    <?php endif ?>

</div>

<script>
(function () {
    var root = document.getElementById('explorer_peers');
    if (!root) return;
    var input = document.getElementById('ep-filter-input');
    var rows  = Array.prototype.slice.call(root.querySelectorAll('#ep-peer-table tbody tr'));
    var shown = document.getElementById('ep-shown');
    if (input) {
        input.addEventListener('input', function () {
            var q = input.value.toLowerCase();
            var n = 0;
            rows.forEach(function (r) {
                var hit = (r.getAttribute('data-search') || '').indexOf(q) !== -1;
                r.style.display = hit ? '' : 'none';
                if (hit) n++;
            });
            if (shown) shown.textContent = 'showing ' + n + ' / ' + rows.length;
        });
    }
    function copyText(text) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            return navigator.clipboard.writeText(text);
        }
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
        return Promise.resolve();
    }
    root.querySelectorAll('[data-copy]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var old = btn.textContent;
            copyText(btn.getAttribute('data-copy') || '').then(function () {
                btn.textContent = 'Copied';
                setTimeout(function () { btn.textContent = old; }, 1200);
            });
        });
    });
})();
</script>
<?php endif ?>
