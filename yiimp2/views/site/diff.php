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
?>
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
