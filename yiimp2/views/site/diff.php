<?php

use yii\helpers\Html;
use yii\helpers\Url;

/** @var array $rows */
/** @var string $stratumUrl */

$ports = [];
foreach ($rows as $r) {
    if ($r['port']) $ports[$r['algo']] = $r['port'];
}
$scryptPort = (int) ($ports['scrypt'] ?? 3433);
$ethashPort = (int) ($ports['ethash'] ?? 3639);

if (Yii::$app->LayoutManager->isLegacy()): ?>
<style>
/* Difficulty page — same chrome as /site/mining */
.df-toolbar {
    display: flex; flex-direction: column; gap: 10px;
    background: #fff; border: 1px solid #d8e2ef; border-radius: 8px;
    padding: 11px 14px 12px; margin: 12px 0 10px;
    box-shadow: 0 1px 2px rgba(20,40,80,.04);
    color: #334155; font-size: 11px;
}
.df-toolbar-head { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
.df-toolbar-label {
    font-size: 10px; font-weight: 700; letter-spacing: .12em;
    color: #8a94a6; text-transform: uppercase; white-space: nowrap;
}
.df-toolbar-title { font-size: 13px; font-weight: 800; color: #26385a; letter-spacing: .01em; }
.df-toolbar-hint {
    display: flex; align-items: center; gap: 6px; flex-wrap: wrap;
    border-top: 1px solid #eef2f7; padding-top: 9px; color: #8a94a6; font-size: 10px;
}
.df-toolbar-pill {
    display: inline-block; border: 1px solid #dfe5ec; background: #fafbfc; color: #64748b;
    border-radius: 999px; padding: 2px 9px; line-height: 15px;
    font-size: 9px; font-weight: 800; white-space: nowrap;
}
.df-live {
    margin-left: auto; display: flex; align-items: center; gap: 6px;
    white-space: nowrap; font-size: 11px; color: #5b6675;
}
.df-live i {
    width: 7px; height: 7px; border-radius: 50%; background: #2fae5a;
    box-shadow: 0 0 0 3px rgba(47,174,90,.15);
}
.df-live #df-last-update { color: #98a3b5; font-size: 10px; font-variant-numeric: tabular-nums; }

@media (max-width: 720px) { .df-live { margin-left: 0; } }
</style>

<div class="df-toolbar">
    <div class="df-toolbar-head">
        <span class="df-toolbar-label">Difficulty</span>
        <span class="df-toolbar-title">Stratum difficulty — live</span>
        <span class="df-live"><i></i> live · 60s <span id="df-last-update">just now</span></span>
    </div>
    <div class="df-toolbar-hint">
        <span>Vardiff targets 5–15 submits/min · override with a password parameter</span>
        <span class="df-toolbar-pill">d= fixed</span>
        <span class="df-toolbar-pill">sd= seed</span>
    </div>
</div>

<div id="diff-live">
    <?= $this->render('diff_live_legacy', [
        'rows'       => $rows,
        'workers'    => $workers,
        'now'        => $now,
        'stratumUrl' => $stratumUrl,
    ]) ?>
</div>

<script>
(function () {
    var url   = <?= json_encode(Url::to(['/site/diff_data'])) ?>;
    var box   = document.getElementById('diff-live');
    var state = document.getElementById('df-last-update');
    if (!box || !url) return;

    var last = Date.now();

    function tick() {
        if (!last || !state) return;
        var age = Math.floor((Date.now() - last) / 1000);
        state.textContent = age + 's ago · next ' + Math.max(0, 60 - age) + 's';
    }

    function refresh() {
        fetch(url, {cache: 'no-store', credentials: 'same-origin'})
            .then(function (r) {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.json();
            })
            .then(function (data) {
                if (data && data.html) box.innerHTML = data.html;
                if (state) state.textContent = (data.generated || '').slice(11) + ' UTC · just now';
                last = Date.now();
            })
            .catch(function (e) {
                if (state) state.textContent = '(refresh failed: ' + e.message + ')';
            })
            .then(function () {
                setTimeout(refresh, 60000);
            });
    }

    setInterval(tick, 1000);
    setTimeout(refresh, 60000);
})();
</script>
<?php return; endif; ?>
<br>

<div class="main-left-box">
<div class="main-left-title">YIIMP STRATUM DIFFICULTY — LIVE</div>
<div class="main-left-inner">

<p style="width: 700px;">
    Yiimp normally uses variable difficulty (vardiff) and aims for roughly 5–15 submits per minute.
    You can override the starting value with a password parameter.
</p>

<pre class="main-left-box" style="padding: 6px; font-size: .9em; background-color: #ffffee; font-family: monospace;">
-o stratum+tcp://<?= Html::encode($stratumUrl) ?>:<?= $scryptPort ?> -u WALLET -p d=64
</pre>

<p style="width: 700px;">
    <code>d=</code> pins the difficulty for the whole session.
    <code>sd=</code> only seeds the initial difficulty and still lets vardiff adjust it later.
    Values outside the configured range are clamped by the stratum server.
</p>

<p style="width: 700px; color: #666;">
    NEB difficulty is shown in the miner's unit (1 = 10^10 expected hashes) so the web matches ForgeMiner;
    hover the value for the raw 256-bit target difficulty. For NEB, <code>d=0.1</code> pins that displayed
    difficulty; <code>sd=0.1</code> seeds it and lets vardiff adjust it. Integer values 1–48 still select
    the reference pool's <code>k = share_target &lt;&lt; k</code> shift.
</p>

<p style="width: 700px; color: #666;">
    Auto-refresh: every 60s
    <span id="diff-refresh-state">(waiting)</span>
</p>

<div id="diff-live">
    <?= $this->render('diff_live', [
        'rows'       => $rows,
        'workers'    => $workers,
        'now'        => $now,
        'stratumUrl' => $stratumUrl,
    ]) ?>
</div>

<p style="width: 700px;"><strong>Examples:</strong></p>

<pre class="main-left-box" style="padding: 6px; font-size: .9em; background-color: #ffffee; font-family: monospace;">
# Scrypt, fixed diff 64
-o stratum+tcp://<?= Html::encode($stratumUrl) ?>:<?= $scryptPort ?> -u WALLET.worker -p d=64

# Ethash / Etchash, fixed diff 0.1
-o stratum+tcp://<?= Html::encode($stratumUrl) ?>:<?= $ethashPort ?> -u WALLET.worker -p d=0.1
</pre>

<p style="width: 700px;">
    Firopow (Kiiro) always uses the pool difficulty shown above; a custom <code>d=</code>
    value is ignored. For all other algos, omit <code>d=</code> to let vardiff manage
    the difficulty automatically.
</p>

</div>
</div>

<script>
(function () {
    var url   = <?= json_encode(Url::to(['/site/diff_data'])) ?>;
    var box   = document.getElementById('diff-live');
    var state = document.getElementById('diff-refresh-state');
    if (!box || !url) return;

    function refresh() {
        fetch(url, {cache: 'no-store', credentials: 'same-origin'})
            .then(function (r) {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.json();
            })
            .then(function (data) {
                if (data && data.html) box.innerHTML = data.html;
                if (state) state.textContent = '(updated ' + (data.generated || '') + ' UTC)';
            })
            .catch(function (e) {
                if (state) state.textContent = '(refresh failed: ' + e.message + ')';
            })
            .then(function () {
                setTimeout(refresh, 60000);
            });
    }

    setTimeout(refresh, 60000);
})();
</script>
