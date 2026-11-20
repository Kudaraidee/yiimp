<?php

/**
 * Redesigned mining-command generator used on /site/mining.
 *
 * @var yii\web\View $this
 * @var array        $algoNav
 */

use yii\helpers\Html;

$isLegacy   = Yii::$app->LayoutManager->isLegacy();
$isTailwind = Yii::$app->LayoutManager->isTailwind();
$algoNav    = $algoNav ?? ['active' => 'all', 'items' => []];
$algoItems  = $algoItems ?? ($algoNav['items'] ?? []);
$activeAlgo = $activeAlgo ?? ($algoNav['active'] ?? 'all');
$jsonFlags  = JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP;

if ($isLegacy): ?>
    <div class="main-left-box mc-card">
        <div class="main-left-title">
            <span class="mn-head-main">
                <span>Mining command generator</span>
                <span class="mn-sub">presets · live command</span>
            </span>
            <span class="mc-badge">copy-ready</span>
        </div>
        <div class="main-left-inner">
<?php elseif (!$isTailwind): ?>
    <div class="card shadow-sm mb-3">
        <div class="card-header py-2 small fw-semibold"><i class="bi bi-terminal me-1"></i>Mining command generator</div>
        <div class="card-body">
<?php else: ?>
    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm overflow-hidden mb-4">
        <div class="px-4 py-2.5 border-b border-gray-200 dark:border-gray-700 text-sm font-semibold text-gray-800 dark:text-gray-100">
            Mining command generator
        </div>
        <div class="p-4">
<?php endif; ?>

<style>
.mc-gen { font-size: 12px; color: #334155; }
.mc-top {
    display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between;
    gap: 8px; margin-bottom: 10px;
}
.mc-presets { display: inline-flex; flex-wrap: wrap; gap: 4px; }
.mc-preset {
    border: 1px solid #dbe3ef; background: #f8fafc; color: #64748b;
    border-radius: 999px; padding: 3px 10px; font-size: 10px; font-weight: 700;
    letter-spacing: .03em; cursor: pointer; line-height: 1.6;
}
.mc-preset:hover { border-color: #b9d4ff; color: #2c5aa0; }
.mc-preset.active { background: #2c5aa0; border-color: #2c5aa0; color: #fff; }
.mc-badge {
    font-size: 10px; color: #2c5aa0; background: #eef4ff;
    border: 1px solid #d7e5ff; border-radius: 999px; padding: 2px 8px;
}
.mc-grid {
    display: grid; grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 9px 12px; margin-bottom: 10px;
}
.mc-field { min-width: 0; }
.mc-field label {
    display: block; font-size: 9px; font-weight: 700; letter-spacing: .05em;
    text-transform: uppercase; color: #8a94a6; margin-bottom: 3px;
}
.mc-field input, .mc-field select {
    width: 100%; box-sizing: border-box; border: 1px solid #dbe3ef;
    background: #fff; color: #26364f; border-radius: 4px;
    padding: 6px 8px; font: 11px/1.4 ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
}
.mc-field input:focus, .mc-field select:focus {
    outline: none; border-color: #7aa5e8; box-shadow: 0 0 0 2px rgba(70,130,220,.12);
}
.mc-output {
    position: relative; margin-top: 2px; padding: 12px 14px;
    background: #0f172a; border: 1px solid #1e293b; border-radius: 5px;
    color: #e2e8f0; font: 11px/1.7 ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    white-space: pre-wrap; word-break: break-all; min-height: 46px;
}
.mc-output code { color: inherit; }
.mc-copy {
    position: absolute; top: 7px; right: 7px;
    border: 1px solid #334155; background: #1e293b; color: #cbd5e1;
    border-radius: 4px; padding: 3px 9px; font-size: 10px; font-weight: 700;
    cursor: pointer;
}
.mc-copy:hover { background: #334155; color: #fff; }
.mc-hint { margin-top: 6px; font-size: 10px; color: #94a3b8; }
.mc-hint b { color: #64748b; }
@media (max-width: 900px) { .mc-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 560px) {
    .mc-grid { grid-template-columns: 1fr; }
    .mc-card > .main-left-title { flex-wrap: wrap; align-items: flex-start; }
    .mc-card > .main-left-title .mn-head-main { flex: 1 1 100%; }
    .mc-card > .main-left-title .mc-badge { flex: 0 0 auto; margin-left: auto; }
    .mc-top { align-items: flex-start; }
    .mc-presets { flex: 1 1 auto; }
    .mc-badge { max-width: 100%; white-space: normal; }
    .mc-output { display: flex; flex-wrap: wrap; align-items: flex-start; gap: 8px; }
    .mc-output code { flex: 1 1 100%; min-width: 0; }
    .mc-copy { position: static; margin-left: auto; flex: 0 0 auto; }
}
</style>

<div class="mc-gen" id="mc-root">
    <div class="mc-top">
        <div class="mc-presets" role="group" aria-label="Miner preset">
            <button type="button" class="mc-preset active" data-preset="generic">Generic</button>
            <button type="button" class="mc-preset" data-preset="gminer">GMiner</button>
            <button type="button" class="mc-preset" data-preset="lolminer">lolMiner</button>
            <button type="button" class="mc-preset" data-preset="trex">T-Rex</button>
        </div>
        <span class="mc-badge">auto from Algorithm bar</span>
    </div>

    <div class="mc-grid">
        <div class="mc-field">
            <label for="mc-algo">Algo</label>
            <select id="mc-algo"></select>
        </div>
        <div class="mc-field">
            <label for="mc-coin">Coin / payout</label>
            <select id="mc-coin"></select>
        </div>
        <div class="mc-field">
            <label for="mc-mode">Payout mode</label>
            <select id="mc-mode">
                <option value="">Shared</option>
                <option value="solo">Solo</option>
            </select>
        </div>
        <div class="mc-field">
            <label for="mc-wallet">Wallet address</label>
            <input id="mc-wallet" type="text" placeholder="WALLET_ADDRESS" autocomplete="off" spellcheck="false">
        </div>
        <div class="mc-field">
            <label for="mc-rig">Rig name</label>
            <input id="mc-rig" type="text" placeholder="WORKER_NAME" autocomplete="off" spellcheck="false">
        </div>
        <div class="mc-field">
            <label for="mc-conn">Connection</label>
            <select id="mc-conn">
                <option value="tcp">TCP · stratum+tcp</option>
                <option value="ssl">TLS · stratum+ssl</option>
            </select>
        </div>
    </div>

    <pre class="mc-output"><code id="mc-command">-a  -o stratum+tcp://<?= Html::encode(YIIMP_STRATUM_URL) ?>:0000 -u WALLET_ADDRESS.WORKER_NAME -p c=SYMBOL</code></pre>
    <div class="mc-hint">
        <b>TIP</b> — ใช้ address ของคุณเป็น username โดยไม่ต้อง register · TLS ใช้พอร์ตเดียวกับ TCP (หรือพอร์ต TLS เฉพาะถ้ากำหนดไว้)
    </div>
</div>

<script>
(function () {
    var data    = <?= json_encode($algoItems, $jsonFlags) ?>;
    var current = <?= json_encode($activeAlgo, $jsonFlags) ?>;
    var host    = <?= json_encode(YIIMP_STRATUM_URL, $jsonFlags) ?>;

    var algoSel  = document.getElementById('mc-algo');
    var coinSel  = document.getElementById('mc-coin');
    var modeSel  = document.getElementById('mc-mode');
    var connSel  = document.getElementById('mc-conn');
    var walletEl = document.getElementById('mc-wallet');
    var rigEl    = document.getElementById('mc-rig');
    var out      = document.getElementById('mc-command');
    var copyBtn  = document.createElement('button');
    var preset   = 'generic';

    Object.keys(data).forEach(function (key) {
        var opt = document.createElement('option');
        opt.value = key;
        opt.textContent = (data[key].label || key) + (key === 'all' ? ' · all algos' : '');
        algoSel.appendChild(opt);
    });
    algoSel.value = data[current] ? current : 'all';

    copyBtn.type = 'button';
    copyBtn.className = 'mc-copy';
    copyBtn.textContent = 'Copy';
    out.parentNode.appendChild(copyBtn);

    function coinsFor(algo) {
        return (data[algo] && data[algo].coins) || [];
    }

    function selectedCoin() {
        var list = coinsFor(algoSel.value);
        for (var i = 0; i < list.length; i++) {
            if (list[i].symbol === coinSel.value) return list[i];
        }
        return null;
    }

    function fillCoins() {
        var list = coinsFor(algoSel.value);
        coinSel.innerHTML = '';
        if (!list.length) {
            var empty = document.createElement('option');
            empty.value = '';
            empty.textContent = 'No coin available';
            coinSel.appendChild(empty);
        } else {
            list.forEach(function (c) {
                var opt = document.createElement('option');
                opt.value = c.symbol;
                opt.textContent = c.name + ' (' + c.symbol + ')';
                coinSel.appendChild(opt);
            });
        }
        build();
    }

    function build() {
        var algo = algoSel.value;
        var coin = selectedCoin();
        var symbol = coin ? coin.symbol : 'SYMBOL';
        var port = coin ? coin.port : ((data[algo] && data[algo].port) || 0);
        if (connSel.value === 'ssl' && coin && coin.tls_port) port = coin.tls_port;

        var scheme = connSel.value === 'ssl' ? 'stratum+ssl://' : 'stratum+tcp://';
        var server = scheme + host + ':' + (port || '0000');
        var pass = 'c=' + symbol;
        if (coin && coin.no_exchange) pass += ',mc=' + symbol;
        if (modeSel.value === 'solo') pass += ',m=solo';

        var user = (walletEl.value.trim() || 'WALLET_ADDRESS') + '.'
                 + (rigEl.value.trim() || 'WORKER_NAME');
        // "all" is only a UI scope — the command must use the selected coin's algo.
        var effectiveAlgo = (algo === 'all' && coin) ? coin.algo : algo;
        var algoArg = preset === 'lolminer' ? effectiveAlgo.toUpperCase() : effectiveAlgo;
        var cmd;

        if (preset === 'gminer') {
            cmd = './miner --algo ' + algoArg + ' --server ' + server
                + ' --user ' + user + ' --pass ' + pass;
        } else if (preset === 'lolminer') {
            cmd = './lolMiner --algo ' + algoArg + ' --pool ' + server
                + ' --user ' + user + ' --pass ' + pass;
        } else if (preset === 'trex') {
            cmd = './t-rex -a ' + algoArg + ' -o ' + server
                + ' -u ' + user + ' -p ' + pass;
        } else {
            cmd = '-a ' + algoArg + ' -o ' + server
                + ' -u ' + user + ' -p ' + pass;
        }

        out.textContent = cmd;
    }

    function done() {
        copyBtn.textContent = 'Copied';
        setTimeout(function () { copyBtn.textContent = 'Copy'; }, 1400);
    }

    function fallbackCopy() {
        var ta = document.createElement('textarea');
        ta.value = out.textContent;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); done(); } catch (e) {}
        document.body.removeChild(ta);
    }

    copyBtn.addEventListener('click', function () {
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(out.textContent).then(done, fallbackCopy);
        } else {
            fallbackCopy();
        }
    });

    [algoSel, coinSel, modeSel, connSel, walletEl, rigEl].forEach(function (el) {
        el.addEventListener(el.tagName === 'INPUT' ? 'input' : 'change', function () {
            if (el === algoSel) fillCoins(); else build();
        });
    });

    Array.prototype.forEach.call(document.querySelectorAll('.mc-preset'), function (btn) {
        btn.addEventListener('click', function () {
            Array.prototype.forEach.call(document.querySelectorAll('.mc-preset'), function (b) {
                b.classList.toggle('active', b === btn);
            });
            preset = btn.dataset.preset || 'generic';
            build();
        });
    });

    fillCoins();
})();
</script>

<?php if ($isLegacy): ?>
        </div>
    </div>
<?php elseif (!$isTailwind): ?>
        </div>
    </div>
<?php else: ?>
        </div>
    </div>
<?php endif; ?>
