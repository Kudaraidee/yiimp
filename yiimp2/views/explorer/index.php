<?php

use yii\helpers\Html;
use app\models\Coins;

$this->title = 'Block Explorer';
$isTailwind  = Yii::$app->LayoutManager->isTailwind();
$isLegacy    = Yii::$app->LayoutManager->isLegacy();
$conv        = Yii::$app->ConversionUtils;

// ── Shared coin list + RPC data ───────────────────────────────────────────────
$list = Coins::find()->where(['enable' => 1, 'visible' => 1])->orderBy('name')->all();

$coinRows  = [];
$peersData = [];
foreach ($list as $coin) {
    if ($coin->symbol === 'BTC') continue;
    if (!empty($coin->symbol2)) continue;

    $coin->version = $conv->formatWalletVersion($coin);

    $walletInfo         = $coin->getWalletInfo();
    $coin->network_hash = $walletInfo['networkhashps'] ?? null;

    $difficulty  = $conv->Itoa2($coin->difficulty, 3);
    $diffnote    = in_array($coin->algo, ['equihash', 'quark']) ? '*' : '';
    $nethash     = $coin->network_hash
                   ? strtoupper($conv->Itoa2($coin->network_hash)) . 'H/s' : '';
    $cnxClass    = (intval($coin->connections) > 3) ? '' : 'low';
    $peersUrl    = "javascript:showPeers({$coin->id});";
    $link        = !empty($coin->link_bitcointalk) ? $coin->link_bitcointalk
                 : (!empty($coin->link_site) ? $coin->link_site : null);
    $linkLabel   = !empty($coin->link_bitcointalk) ? 'forum' : 'site';

    $peersData[$coin->id] = [
        'name'   => $coin->name,
        'prefix' => $coin->rpcencoding === 'DCR' ? 'addpeer=' : 'addnode=',
        'peers'  => $walletInfo['peers'] ?? [],
    ];

    $coinRows[] = compact('coin', 'difficulty', 'diffnote', 'nethash',
                          'cnxClass', 'peersUrl', 'link', 'linkLabel');
}
?>

<?php if ($isLegacy): ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     LEGACY
     ══════════════════════════════════════════════════════════════════════════ -->
<style>a.low { color:red; font-weight:bold; }</style>
<br/>
<div class="main-left-box">
<div class="main-left-title">Block Explorer</div>
<div class="main-left-inner">
<?php Yii::$app->ViewUtils->showTableSorter('maintable', "{
    tableClass: 'dataGrid2',
    textExtraction: {
        6: function(node,table,n){ return \$(node).attr('data'); },
        8: function(node,table,n){ return \$(node).attr('data'); }
    }
}"); ?>
<thead><tr>
    <th width="30" data-sorter=""></th>
    <th>Name</th><th>Symbol</th><th>Algo</th><th>Version</th><th>Height</th>
    <th>Difficulty</th><th>Connections</th><th>Network Hash</th><th data-sorter=""></th>
</tr></thead><tbody>
<?php foreach ($coinRows as $r):
    $coin = $r['coin']; ?>
<tr class="ssrow">
    <td><img src="<?= $coin->image ?>" width="18"></td>
    <td><b><?= $coin->createExplorerLink($coin->name) ?></b></td>
    <td><b><?= Html::encode($coin->symbol) ?></b></td>
    <td><?= Html::encode($coin->algo) ?></td>
    <td><?= Html::encode($coin->version) ?></td>
    <td><?= Html::encode($coin->block_height) ?></td>
    <td data="<?= $coin->difficulty ?>"><?= $r['difficulty'] ?><?= $r['diffnote'] ?></td>
    <td><?= Html::a($coin->connections, $r['peersUrl'], ['class' => $r['cnxClass']]) ?></td>
    <td data="<?= $coin->network_hash ?>"><?= $r['nethash'] ?></td>
    <td><?php if ($r['link']): ?><?= Html::a($r['linkLabel'], $r['link'], ['target' => '_blank']) ?><?php endif ?></td>
</tr>
<?php endforeach ?>
</tbody></table>
<p style="font-size:.8em;">&nbsp;* Unified difficulty (may differ from wallet)<br/></p>
</div></div>


<?php elseif (!$isTailwind): ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     ADMINLTE
     ══════════════════════════════════════════════════════════════════════════ -->
<div class="card shadow-sm">
    <div class="card-header py-2">
        <strong class="small">Block Explorer</strong>
        <span class="badge bg-secondary ms-2"><?= count($coinRows) ?> coins</span>
    </div>
    <div class="card-body p-0">
    <table id="maintable" class="table table-hover table-sm table-bordered mb-0">
    <thead class="table-light">
    <tr>
        <th data-sorter="false" style="width:28px"></th>
        <th data-sorter="text">Name</th>
        <th data-sorter="text" style="width:70px">Symbol</th>
        <th data-sorter="text" style="width:80px">Algo</th>
        <th data-sorter="text">Version</th>
        <th data-sorter="numeric" class="text-end">Height</th>
        <th data-sorter="numeric" class="text-end">Difficulty</th>
        <th data-sorter="numeric" class="text-end">Conns</th>
        <th data-sorter="numeric" class="text-end">Net Hash</th>
        <th data-sorter="false" style="width:60px"></th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($coinRows as $r):
        $coin = $r['coin']; ?>
    <tr>
        <td><?php if (!empty($coin->image)): ?>
            <img src="<?= Html::encode($coin->image) ?>" width="18" alt=""
                 style="object-fit:contain" onerror="this.style.display='none'">
        <?php endif ?></td>
        <td><strong><?= $coin->createExplorerLink(Html::encode($coin->name)) ?></strong></td>
        <td class="font-monospace small"><?= Html::encode($coin->symbol) ?></td>
        <td><span class="badge bg-light text-dark border font-monospace"><?= Html::encode($coin->algo) ?></span></td>
        <td class="small font-monospace"><?= Html::encode($coin->version) ?></td>
        <td class="text-end small tabular-nums"><?= Html::encode($coin->block_height) ?></td>
        <td class="text-end small font-monospace" data="<?= $coin->difficulty ?>">
            <?= $r['difficulty'] ?><?= $r['diffnote'] ?>
        </td>
        <td class="text-end small <?= $r['cnxClass'] === 'low' ? 'text-danger fw-bold' : '' ?>">
            <?= Html::a(Html::encode((string)$coin->connections), $r['peersUrl']) ?>
        </td>
        <td class="text-end small font-monospace" data="<?= $coin->network_hash ?>">
            <?= Html::encode($r['nethash']) ?>
        </td>
        <td class="small"><?php if ($r['link']): ?>
            <?= Html::a($r['linkLabel'], $r['link'], ['target' => '_blank', 'class' => 'text-muted']) ?>
        <?php endif ?></td>
    </tr>
    <?php endforeach ?>
    </tbody>
    </table>
    </div>
    <div class="card-footer text-muted small py-1">
        * Unified difficulty (may differ from wallet value)
    </div>
</div>
<?php
Yii::$app->ViewUtils->JavascriptReady("
    \$('#maintable').tablesorter({
        textExtraction: {
            6: function(node,table,n){ return \$(node).attr('data'); },
            8: function(node,table,n){ return \$(node).attr('data'); }
        }
    });
    \$('.tablesorter-header').not('.sorter-false').css('cursor','pointer');
");
?>


<?php else: ?>
<!-- ══════════════════════════════════════════════════════════════════════════
     TAILWIND
     ══════════════════════════════════════════════════════════════════════════ -->
<div class="rounded-xl border border-gray-200 dark:border-gray-700
            bg-white dark:bg-gray-800 shadow-sm overflow-hidden">

    <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex items-center gap-3">
        <span class="text-sm font-semibold text-gray-800 dark:text-gray-100">Block Explorer</span>
        <span class="px-2 py-0.5 text-xs rounded-full bg-gray-100 dark:bg-gray-700
                     text-gray-600 dark:text-gray-300"><?= count($coinRows) ?> coins</span>
    </div>

    <div class="overflow-x-auto">
    <table id="maintable" class="w-full text-xs">
    <thead>
    <tr class="bg-gray-50 dark:bg-gray-700/50 text-gray-500 dark:text-gray-400
               font-semibold uppercase tracking-wider
               border-b border-gray-200 dark:border-gray-700">
        <th class="px-3 py-2.5 w-8"        data-sorter="false"></th>
        <th class="px-3 py-2.5 text-left"  data-sorter="text">Name</th>
        <th class="px-3 py-2.5 text-left"  data-sorter="text">Symbol</th>
        <th class="px-3 py-2.5 text-left"  data-sorter="text">Algo</th>
        <th class="px-3 py-2.5 text-left"  data-sorter="text">Version</th>
        <th class="px-3 py-2.5 text-right" data-sorter="numeric">Height</th>
        <th class="px-3 py-2.5 text-right" data-sorter="numeric">Difficulty</th>
        <th class="px-3 py-2.5 text-right" data-sorter="numeric">Conns</th>
        <th class="px-3 py-2.5 text-right" data-sorter="numeric">Net Hash</th>
        <th class="px-3 py-2.5"            data-sorter="false"></th>
    </tr>
    </thead>
    <tbody class="divide-y divide-gray-100 dark:divide-gray-700/50">
    <?php foreach ($coinRows as $r):
        $coin = $r['coin']; ?>
    <tr class="hover:bg-gray-50/70 dark:hover:bg-gray-700/20 transition-colors">
        <td class="px-3 py-2">
            <?php if (!empty($coin->image)): ?>
                <img src="<?= Html::encode($coin->image) ?>" width="20" height="20"
                     class="rounded object-contain" onerror="this.style.display='none'" alt="">
            <?php endif ?>
        </td>
        <td class="px-3 py-2 font-medium text-gray-900 dark:text-gray-100">
            <?= $coin->createExplorerLink(Html::encode($coin->name)) ?>
        </td>
        <td class="px-3 py-2 font-mono text-indigo-600 dark:text-indigo-400">
            <?= Html::encode($coin->symbol) ?>
        </td>
        <td class="px-3 py-2">
            <span class="inline-flex items-center px-1.5 py-0.5 rounded-full
                         bg-indigo-50 dark:bg-indigo-900/30
                         text-indigo-700 dark:text-indigo-300 font-mono">
                <?= Html::encode($coin->algo) ?>
            </span>
        </td>
        <td class="px-3 py-2 font-mono text-gray-400 dark:text-gray-500">
            <?= Html::encode($coin->version) ?>
        </td>
        <td class="px-3 py-2 text-right tabular-nums text-gray-600 dark:text-gray-300">
            <?= Html::encode($coin->block_height) ?>
        </td>
        <td class="px-3 py-2 text-right font-mono tabular-nums text-gray-600 dark:text-gray-300"
            data="<?= $coin->difficulty ?>">
            <?= $r['difficulty'] ?><?= $r['diffnote'] ?>
        </td>
        <td class="px-3 py-2 text-right tabular-nums
                   <?= $r['cnxClass'] === 'low' ? 'text-red-500 dark:text-red-400 font-bold' : 'text-gray-600 dark:text-gray-300' ?>">
            <?= Html::a(Html::encode((string)$coin->connections), $r['peersUrl'],
                ['class' => 'hover:underline']) ?>
        </td>
        <td class="px-3 py-2 text-right font-mono tabular-nums text-gray-500 dark:text-gray-400"
            data="<?= $coin->network_hash ?>">
            <?= Html::encode($r['nethash']) ?>
        </td>
        <td class="px-3 py-2"><?php if ($r['link']): ?>
            <?= Html::a($r['linkLabel'], $r['link'],
                ['target' => '_blank',
                 'class'  => 'text-xs text-gray-400 dark:text-gray-500 hover:text-indigo-500 transition-colors']) ?>
        <?php endif ?></td>
    </tr>
    <?php endforeach ?>
    </tbody>
    </table>
    </div>

    <div class="px-4 py-2 border-t border-gray-200 dark:border-gray-700
                text-xs text-gray-400 dark:text-gray-500">
        * Unified difficulty (may differ from wallet value)
    </div>
</div>

<?php
$this->registerJs("
document.addEventListener('DOMContentLoaded', function () {
    var table = document.getElementById('maintable');
    if (!table) return;
    var tbody = table.tBodies[0], ths = Array.from(table.tHead.rows[0].cells);
    var asc = ths.map(function () { return true; });
    ths.forEach(function (th, col) {
        if (th.dataset.sorter === 'false') return;
        th.style.cursor = 'pointer';
        th.addEventListener('click', function () {
            var rs = Array.from(tbody.rows);
            rs.sort(function (a, b) {
                var av = (a.cells[col].getAttribute('data') || a.cells[col].textContent || '').trim();
                var bv = (b.cells[col].getAttribute('data') || b.cells[col].textContent || '').trim();
                var n  = parseFloat(av) - parseFloat(bv);
                return isNaN(n) ? (asc[col] ? av.localeCompare(bv) : bv.localeCompare(av)) : (asc[col] ? n : -n);
            });
            asc[col] = !asc[col];
            rs.forEach(function (r) { tbody.appendChild(r); });
        });
    });
});
");
?>

<?php endif ?>

<!-- ── Peers overlay (shared, scheme-styled) ─────────────────────────────────── -->
<?php if ($isLegacy): ?>
<div id="peers-overlay" style="display:none;position:fixed;inset:0;z-index:9000;background:rgba(0,0,0,.45);"
     onclick="if(event.target===this)closePeers();">
    <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);
                background:#fff;border:1px solid #ccc;border-radius:4px;
                min-width:320px;max-width:520px;width:90%;box-shadow:0 4px 16px rgba(0,0,0,.25);">
        <div style="padding:8px 12px;border-bottom:1px solid #ddd;display:flex;align-items:center;justify-content:space-between;gap:8px;">
            <b id="peers-title" style="font-size:.9em;flex:1;"></b>
            <button id="peers-copy" onclick="copyPeers()" style="font-size:.75em;padding:2px 8px;cursor:pointer;border:1px solid #aaa;border-radius:3px;background:#f5f5f5;">Copy</button>
            <a href="javascript:closePeers();" style="font-size:1.3em;text-decoration:none;color:#666;line-height:1;">&times;</a>
        </div>
        <div style="padding:10px 12px;max-height:400px;overflow-y:auto;">
            <pre id="peers-content" style="margin:0;font-size:.8em;white-space:pre-wrap;word-break:break-all;"></pre>
            <p id="peers-empty" style="display:none;color:#999;font-size:.85em;margin:0;">No peers available.</p>
        </div>
    </div>
</div>

<?php elseif (!$isTailwind): ?>
<div id="peers-overlay" style="display:none;position:fixed;inset:0;z-index:9000;background:rgba(0,0,0,.45);"
     onclick="if(event.target===this)closePeers();">
    <div class="card shadow-lg" style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);
                min-width:340px;max-width:540px;width:90%;">
        <div class="card-header d-flex align-items-center gap-2 py-2">
            <span class="fw-semibold small me-auto" id="peers-title"></span>
            <button id="peers-copy" type="button" onclick="copyPeers()"
                    class="btn btn-sm btn-outline-secondary" style="font-size:.72rem;padding:1px 8px;">Copy</button>
            <button type="button" class="btn-close btn-sm" onclick="closePeers()"></button>
        </div>
        <div class="card-body p-3" style="max-height:420px;overflow-y:auto;">
            <pre id="peers-content" class="small mb-0" style="white-space:pre-wrap;word-break:break-all;"></pre>
            <p id="peers-empty" class="text-muted small mb-0" style="display:none;">No peers available.</p>
        </div>
    </div>
</div>

<?php else: ?>
<div id="peers-overlay" class="fixed inset-0 z-50 hidden items-center justify-center"
     onclick="if(event.target===this)closePeers();">
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>
    <div class="relative bg-white dark:bg-gray-800 rounded-2xl shadow-2xl
                w-full max-w-lg mx-4 overflow-hidden">
        <div class="flex items-center gap-3 px-5 py-3
                    border-b border-gray-200 dark:border-gray-700">
            <span class="text-sm font-semibold text-gray-800 dark:text-gray-100 flex-1"
                  id="peers-title"></span>
            <button id="peers-copy" onclick="copyPeers()"
                    class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium rounded-lg
                           border border-gray-300 dark:border-gray-600
                           bg-white dark:bg-gray-700 text-gray-600 dark:text-gray-300
                           hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors">
                Copy
            </button>
            <button onclick="closePeers()"
                    class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300
                           transition-colors text-xl leading-none">&times;</button>
        </div>
        <div class="px-5 py-4 max-h-96 overflow-y-auto">
            <pre id="peers-content"
                 class="text-xs font-mono text-gray-700 dark:text-gray-300
                        bg-gray-50 dark:bg-gray-900/40 rounded-lg p-3
                        whitespace-pre-wrap break-all m-0"></pre>
            <p id="peers-empty"
               class="hidden text-sm text-gray-400 dark:text-gray-500 m-0">No peers available.</p>
        </div>
    </div>
</div>
<?php endif ?>

<script>
var _peersData  = <?= json_encode($peersData, JSON_UNESCAPED_UNICODE) ?>;
var _isTailwind = <?= $isTailwind ? 'true' : 'false' ?>;

function showPeers(id) {
    var data    = _peersData[id];
    if (!data) return;
    var lines   = data.peers.map(function(a) { return data.prefix + a; });
    var content = document.getElementById('peers-content');
    var empty   = document.getElementById('peers-empty');
    var overlay = document.getElementById('peers-overlay');

    document.getElementById('peers-title').textContent = data.name + ' — Peers';

    if (lines.length > 0) {
        content.textContent   = lines.join('\n');
        content.style.display = '';
        if (empty) empty.style.display = 'none';
    } else {
        content.style.display = 'none';
        if (empty) empty.style.display = '';
    }

    if (_isTailwind) {
        overlay.classList.remove('hidden');
        overlay.classList.add('flex');
    } else {
        overlay.style.display = 'block';
    }
}

function closePeers() {
    var overlay = document.getElementById('peers-overlay');
    if (_isTailwind) {
        overlay.classList.add('hidden');
        overlay.classList.remove('flex');
    } else {
        overlay.style.display = 'none';
    }
}

function copyPeers() {
    var text = (document.getElementById('peers-content').textContent || '').trim();
    if (!text) return;
    var btn = document.getElementById('peers-copy');
    var original = btn.textContent;

    var done = function() {
        btn.textContent = 'Copied ✓';
        setTimeout(function() { btn.textContent = original; }, 1500);
    };

    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(done).catch(function() {
            fallbackCopy(text, done);
        });
    } else {
        fallbackCopy(text, done);
    }
}

function fallbackCopy(text, done) {
    var ta = document.createElement('textarea');
    ta.value = text;
    ta.style.cssText = 'position:fixed;top:-9999px;left:-9999px;opacity:0;';
    document.body.appendChild(ta);
    ta.focus();
    ta.select();
    try { document.execCommand('copy'); done(); } catch(e) {}
    document.body.removeChild(ta);
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closePeers();
});
</script>
