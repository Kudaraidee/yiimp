<?php

use yii\helpers\Html;

/** @var array  $rows */
/** @var array  $workers */
/** @var int    $now */
/** @var string $stratumUrl */

$conv = Yii::$app->ConversionUtils;

$num = function ($value, int $decimals = 6): string {
    if ($value === null || $value === '') return '—';
    $s = number_format((float) $value, $decimals, '.', ',');
    return rtrim(rtrim($s, '0'), '.');
};

$util = Yii::$app->YiimpUtils;
$diffText = function ($algo, $raw) use ($num, $util): string {
    if ($algo === 'nebhash') {
        // Match ForgeMiner's 4-decimal display; the raw target stays in the tooltip.
        return number_format($util->display_difficulty($algo, $raw), 4, '.', ',');
    }
    return $num($raw);
};

$compact = function ($value) use ($conv): string {
    if ($value === null || $value === '') return '—';
    return (string) $conv->Itoa2($value, 3);
};

$ago = function ($ts) use ($now): string {
    if (!$ts) return '—';
    $d = max(0, $now - (int) $ts);
    if ($d < 60) return $d . 's ago';
    if ($d < 3600) return floor($d / 60) . 'm ago';
    if ($d < 86400) return floor($d / 3600) . 'h ago';
    return floor($d / 86400) . 'd ago';
};

$customNote = function (string $algo): string {
    return match ($algo) {
        'firopow'           => 'ignored (pool fixed)',
        'ethash', 'etchash' => 'used as-is',
        default             => 'clamped',
    };
};

$ports = [];
foreach ($rows as $r) {
    if ($r['port']) $ports[$r['algo']] = (int) $r['port'];
}
$scryptPort = $ports['scrypt'] ?? 3433;
$ethashPort = $ports['ethash'] ?? 3639;

$liveCount = 0;
foreach ($rows as $r) {
    if ($r['seen'] && ($now - $r['seen']) <= 180) $liveCount++;
}

// Styles live inside the fragment: #diff-live is replaced every 60s.
echo <<<'CSS'
<style>
#df-root { color: #334155; font-size: 11px; }
#df-root a { color: #2c5aa0; text-decoration: none; }
#df-root a:hover { color: #1f467f; text-decoration: underline; }

/* Summary strip */
#df-root .df-summary {
    display: flex; gap: 18px; flex-wrap: wrap; padding: 9px 13px; margin-bottom: 12px;
    background: #fcfdff; border: 1px solid #d8e2ef; border-radius: 8px;
    box-shadow: 0 1px 2px rgba(20,40,80,.04);
    font-size: 10px; color: #8a94a6;
}
#df-root .df-summary b { color: #33405a; font-weight: 700; font-variant-numeric: tabular-nums; }
#df-root .df-summary i {
    display: inline-block; width: 6px; height: 6px; border-radius: 50%;
    background: #2fae5a; box-shadow: 0 0 0 3px rgba(47,174,90,.12); margin-right: 5px;
}

/* Cards */
#df-root .df-card {
    background: #fff; border: 1px solid #d8e2ef; border-radius: 8px;
    box-shadow: 0 1px 2px rgba(20,40,80,.04); overflow: hidden; margin-bottom: 12px;
}
#df-root .df-card-head {
    display: flex; align-items: center; justify-content: space-between; gap: 10px;
    min-height: 40px; padding: 9px 13px;
    background: #f8fafc; border-bottom: 1px solid #e8eef6;
}
#df-root .df-head-main { display: flex; align-items: baseline; gap: 9px; flex-wrap: wrap; min-width: 0; }
#df-root .df-title { color: #26385a; font-size: 13px; font-weight: 800; letter-spacing: .01em; }
#df-root .df-sub { color: #8a94a6; font-size: 10px; font-weight: 500; }
#df-root .df-scope {
    border: 1px solid #d7e5ff; background: #eef4ff; color: #2c5aa0;
    border-radius: 999px; padding: 2px 9px; line-height: 15px;
    font-size: 9px; font-weight: 800; white-space: nowrap;
}
#df-root .df-scroll { overflow: auto; }
#df-root #df-stratums .df-scroll { max-height: 420px; }
#df-root #df-workers .df-scroll { max-height: 360px; }

/* Tables */
#df-root .df-table { width: 100%; border-collapse: collapse; font-size: 11px; }
#df-root .df-table th {
    position: sticky; top: 0; z-index: 1;
    background: #fff !important; padding: 8px 9px !important;
    color: #8a94a6; font-size: 9px !important; font-weight: 800 !important;
    letter-spacing: .05em; text-transform: uppercase; white-space: nowrap;
    border-bottom: 1px solid #e2e8f0 !important; text-align: left;
}
#df-root .df-table td {
    padding: 8px 9px; border-bottom: 1px solid #eef2f7 !important;
    vertical-align: middle;
}
#df-root .df-table tbody tr:last-child td { border-bottom: 0 !important; }
#df-root .df-table tbody tr:hover td { background: #f7faff !important; }
#df-root .df-table .num {
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-variant-numeric: tabular-nums; color: #2b3d5b; font-weight: 700; white-space: nowrap;
}
#df-root .df-table .meta { display: block; margin-top: 2px; font-size: 9px; color: #98a3b5; white-space: nowrap; }
#df-root #df-stratums .df-table { min-width: 940px; }
#df-root #df-workers .df-table { min-width: 640px; }

/* Pills */
#df-root .df-pill {
    display: inline-block; border-radius: 999px; padding: 2px 8px;
    border: 1px solid #dfe5ec; background: #fafbfc; color: #64748b;
    font-size: 9px; font-weight: 800; line-height: 14px; white-space: nowrap;
}
#df-root .df-pill.live { background: #e8f7ee; border-color: #ccebd8; color: #187d46; }
#df-root .df-pill.stale { background: #fff5e6; border-color: #fde3b8; color: #b45309; }
#df-root .df-pill.unknown { background: #f1f5f9; border-color: #e2e8f0; color: #94a3b8; }
#df-root .df-pill.blue { background: #eef4ff; border-color: #d7e5ff; color: #2c5aa0; }
#df-root .df-pill.amber { background: #fff7e8; border-color: #ffe2b8; color: #a86400; }
#df-root .df-dot {
    display: inline-block; width: 6px; height: 6px; border-radius: 50%;
    margin-right: 4px; vertical-align: 1px;
}
#df-root .df-dot.live { background: #2fae5a; }
#df-root .df-dot.stale { background: #e0a020; }
#df-root .df-dot.unknown { background: #b6c0cd; }

/* Help / command examples */
#df-root .df-help-body { padding: 12px 13px; font-size: 11px; line-height: 1.65; color: #64748b; }
#df-root .df-help-body p { margin: 0 0 8px; }
#df-root .df-help-body code {
    background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 3px;
    padding: 1px 4px; font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    color: #33405a; font-size: 10px;
}
#df-root .df-code {
    margin: 6px 0; padding: 9px 11px; background: #f8fafc; border: 1px solid #e8eef6;
    border-radius: 6px; color: #33405a;
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 10px;
    white-space: pre-wrap; overflow-x: auto;
}
#df-root .df-foot {
    padding: 8px 13px; background: #fcfdff; border-top: 1px solid #eef2f7;
    font-size: 9px; color: #98a3b5;
}
</style>
CSS;
?>
<div id="df-root">

<div class="df-summary">
    <span><i></i><b><?= count($rows) ?> algos</b> in scope</span>
    <span>Live <b><?= $liveCount ?></b></span>
    <span>Active workers <b><?= count($workers) ?></b></span>
    <span>Generated <b><?= Html::encode(gmdate('H:i:s', $now)) ?></b> UTC</span>
</div>

<div id="df-stratums" class="df-card">
    <div class="df-card-head">
        <span class="df-head-main"><span class="df-title">Stratum difficulty</span>
            <span class="df-sub"> · config/stratum · stratums · hashrate · coins</span></span>
        <span class="df-scope"><?= count($rows) ?> algos</span>
    </div>
    <div class="df-scroll">
        <table class="df-table">
            <thead><tr>
                <th>Algo</th><th>Port</th><th>Coin</th><th>Status</th><th>Pool diff</th>
                <th>Custom <code>d=</code></th><th>Network diff</th><th>Pool hashrate</th>
            </tr></thead>
            <tbody>
<?php if (empty($rows)): ?>
                <tr><td colspan="8" class="meta">No stratum data available.</td></tr>
<?php else: ?>
<?php foreach ($rows as $r): ?>
<?php
    $stale = $r['seen'] && ($now - $r['seen'] > 180);
    $state = !$r['seen'] ? 'unknown' : ($stale ? 'stale' : 'live');
    $symbol = $r['symbol'] !== '' ? $r['symbol'] : ($r['coin_name'] !== '' ? $r['coin_name'] : '—');
    $hashrate = $r['hashrate'] === null
        ? '—'
        : strtoupper((string) $conv->Itoa2($r['hashrate'], 2)) . 'H/s';
?>
                <tr>
                    <td><span class="df-pill blue"><?= Html::encode($r['algo']) ?></span></td>
                    <td><span class="num"><?= $r['port'] ? (int) $r['port'] : '—' ?></span>
                        <?php if ($r['tls_port']): ?><span class="meta">TLS <?= (int) $r['tls_port'] ?></span><?php endif ?></td>
                    <td><b><?= Html::encode($symbol) ?></b>
                        <span class="meta"><?= Html::encode($r['coin_name'] !== '' ? $r['coin_name'] : '—') ?></span></td>
                    <td><span class="df-pill <?= $state ?>"><i class="df-dot <?= $state ?>"></i><?= Html::encode($state) ?></span>
                        <span class="meta"><?= (int) $r['workers'] ?> workers · <?= (int) $r['fds'] ?> fds · up <?= Html::encode($ago($r['started'])) ?> · seen <?= Html::encode($ago($r['seen'])) ?></span></td>
                    <td><span class="num"><?= Html::encode($num($r['default'])) ?></span></td>
                    <td><span class="num"><?= Html::encode($num($r['min'])) ?> – <?= Html::encode($num($r['max'])) ?></span>
                        <span class="meta"><?= Html::encode($customNote($r['algo'])) ?></span></td>
                    <td><span class="num"><?= Html::encode($compact($r['network_diff'])) ?></span></td>
                    <td><span class="num"><?= Html::encode($hashrate) ?></span></td>
                </tr>
<?php endforeach ?>
<?php endif ?>
            </tbody>
        </table>
    </div>
</div>

<div id="df-workers" class="df-card">
    <div class="df-card-head">
        <span class="df-head-main"><span class="df-title">Active workers</span>
            <span class="df-sub"> · last hour · newest 20</span></span>
        <span class="df-scope"><?= count($workers) ?> shown</span>
    </div>
    <div class="df-scroll">
        <table class="df-table">
            <thead><tr>
                <th>Worker</th><th>Algo</th><th>Difficulty</th><th>Override</th><th>Version</th><th>Last submit</th>
            </tr></thead>
            <tbody>
<?php if (empty($workers)): ?>
                <tr><td colspan="6" class="meta">No workers submitted in the last hour.</td></tr>
<?php else: ?>
<?php foreach ($workers as $w): ?>
<?php
    $password = (string) ($w['password'] ?? '');
    $match = preg_match('/(?:^|,)([ds])=([^,]+)/', $password, $m);
    $override = $match ? $m[1] . '=' . $m[2] : 'vardiff';
    $overrideCls = !$match ? '' : ($m[1] === 's' ? 'amber' : 'blue');
    $workerName = (string) ($w['worker'] ?? '') !== '' ? (string) $w['worker'] : (string) ($w['name'] ?? '');
?>
                <tr>
                    <td><b><?= Html::encode($workerName) ?></b></td>
                    <td><span class="df-pill blue"><?= Html::encode($w['algo']) ?></span></td>
                    <td><span class="num" title="raw target difficulty: <?= Html::encode($num($w['difficulty'])) ?>"><?= Html::encode($diffText($w['algo'], $w['difficulty'])) ?></span></td>
                    <td><span class="df-pill <?= $overrideCls ?>"><?= Html::encode($override) ?></span></td>
                    <td><?= Html::encode($w['version']) ?></td>
                    <td><span class="meta" style="margin:0"><?= Html::encode($ago($w['time'])) ?></span></td>
                </tr>
<?php endforeach ?>
<?php endif ?>
            </tbody>
        </table>
    </div>
</div>

<div class="df-card" id="df-help">
    <div class="df-card-head">
        <span class="df-head-main"><span class="df-title">Difficulty overrides</span>
            <span class="df-sub"> · miner password parameters</span></span>
        <span class="df-scope">vardiff</span>
    </div>
    <div class="df-help-body">
        <p>Yiimp normally uses variable difficulty (vardiff) and aims for roughly 5–15 submits per minute.
           Override the starting value with a password parameter; values outside the configured range are clamped.</p>
        <div class="df-code">-o stratum+tcp://<?= Html::encode($stratumUrl) ?>:<?= (int) $scryptPort ?> -u WALLET -p d=64</div>
        <p><code>d=</code> pins difficulty for the whole session. <code>sd=</code> only seeds the initial
           difficulty and still lets vardiff adjust it later. Firopow ignores a custom <code>d=</code> value.</p>
        <p>NEB difficulty is shown in the miner's unit (1 = 10^10 expected hashes), so the web matches
           ForgeMiner; hover the value for the raw 256-bit target difficulty. For NEB, <code>d=</code>/<code>sd=</code>
           pin/seed the vardiff k (1–48), not a decimal difficulty.</p>
        <div class="df-code"># Scrypt, fixed diff 64
-o stratum+tcp://<?= Html::encode($stratumUrl) ?>:<?= (int) $scryptPort ?> -u WALLET.worker -p d=64

# Ethash / Etchash, fixed diff 0.1
-o stratum+tcp://<?= Html::encode($stratumUrl) ?>:<?= (int) $ethashPort ?> -u WALLET.worker -p d=0.1</div>
    </div>
    <div class="df-foot">Leave <code>d=</code> out to let vardiff manage difficulty automatically.</div>
</div>

</div>
