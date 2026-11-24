<?php

use yii\helpers\Html;

/** @var array $rows */
/** @var array $workers */
/** @var int $now */

$conv = Yii::$app->ConversionUtils;

$num = function ($value, int $decimals = 6): string {
    if ($value === null || $value === '') return '—';
    $s = number_format((float) $value, $decimals, '.', ',');
    return rtrim(rtrim($s, '0'), '.');
};

$util = Yii::$app->YiimpUtils;
$diffText = function ($algo, $raw) use ($num, $util): string {
    if ($algo === 'nebhash') {
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
?>

<p style="width: 700px;">
    <strong>Live stratum status:</strong>
    read from <code>stratums</code> / <code>hashrate</code> / <code>coins</code> and the runtime
    <code>config/stratum/*.conf</code> files on every request. Generated
    <?= Html::encode(gmdate('Y-m-d H:i:s', $now)) ?> UTC.
</p>

<table style="border-collapse: collapse; font-size: .85em; margin-bottom: 14px; width: 100%;">
    <thead>
    <tr style="background: #eee;">
        <th style="border: 1px solid #ccc; padding: 4px 6px; text-align: left;">Algo</th>
        <th style="border: 1px solid #ccc; padding: 4px 6px; text-align: left;">Port</th>
        <th style="border: 1px solid #ccc; padding: 4px 6px; text-align: left;">TLS Port</th>
        <th style="border: 1px solid #ccc; padding: 4px 6px; text-align: left;">Coin</th>
        <th style="border: 1px solid #ccc; padding: 4px 6px; text-align: left;">Status</th>
        <th style="border: 1px solid #ccc; padding: 4px 6px; text-align: left;">Pool diff</th>
        <th style="border: 1px solid #ccc; padding: 4px 6px; text-align: left;">Custom <code>d=</code></th>
        <th style="border: 1px solid #ccc; padding: 4px 6px; text-align: left;">Network diff</th>
        <th style="border: 1px solid #ccc; padding: 4px 6px; text-align: left;">Pool hashrate</th>
    </tr>
    </thead>
    <tbody>
    <?php if (empty($rows)): ?>
    <tr><td colspan="9" style="border: 1px solid #ccc; padding: 6px;">No stratum data available.</td></tr>
    <?php else: ?>
        <?php foreach ($rows as $r): ?>
            <?php
            $stale = $r['seen'] && ($now - $r['seen'] > 180);
            $state = !$r['seen'] ? 'unknown' : ($stale ? 'stale' : 'live');
            $color = !$r['seen'] ? '#999' : ($stale ? '#e3342f' : '#38a169');
            $symbol = $r['symbol'] !== '' ? $r['symbol'] : ($r['coin_name'] !== '' ? $r['coin_name'] : '—');
            $hashrate = $r['hashrate'] === null
                ? '—'
                : strtoupper((string) $conv->Itoa2($r['hashrate'], 2)) . 'H/s';
            ?>
        <tr>
            <td style="border: 1px solid #ccc; padding: 4px 6px;"><?= Html::encode($r['algo']) ?></td>
            <td style="border: 1px solid #ccc; padding: 4px 6px;"><?= $r['port'] ? (int) $r['port'] : '—' ?></td>
            <td style="border: 1px solid #ccc; padding: 4px 6px;"><?= $r['tls_port'] ? (int) $r['tls_port'] : '—' ?></td>
            <td style="border: 1px solid #ccc; padding: 4px 6px;"><?= Html::encode($symbol) ?></td>
            <td style="border: 1px solid #ccc; padding: 4px 6px;">
                <span style="display:inline-block;width:7px;height:7px;border-radius:50%;background:<?= $color ?>"></span>
                <?= Html::encode($state) ?>
                <span style="color:#777;">
                    (<?= (int) $r['workers'] ?> workers,
                    <?= (int) $r['fds'] ?> fds,
                    up <?= Html::encode($ago($r['started'])) ?>,
                    seen <?= Html::encode($ago($r['seen'])) ?>)
                </span>
            </td>
            <td style="border: 1px solid #ccc; padding: 4px 6px;"><?= Html::encode($num($r['default'])) ?></td>
            <td style="border: 1px solid #ccc; padding: 4px 6px;">
                <?= Html::encode($num($r['min'])) ?> – <?= Html::encode($num($r['max'])) ?>
                <span style="color:#777;">(<?= Html::encode($customNote($r['algo'])) ?>)</span>
            </td>
            <td style="border: 1px solid #ccc; padding: 4px 6px;"><?= Html::encode($compact($r['network_diff'])) ?></td>
            <td style="border: 1px solid #ccc; padding: 4px 6px;"><?= Html::encode($hashrate) ?></td>
        </tr>
        <?php endforeach ?>
    <?php endif ?>
    </tbody>
</table>

<p style="width: 700px;"><strong>Active workers (last hour):</strong></p>

<table style="border-collapse: collapse; font-size: .85em; margin-bottom: 14px; width: 100%;">
    <thead>
    <tr style="background: #eee;">
        <th style="border: 1px solid #ccc; padding: 4px 6px; text-align: left;">Worker</th>
        <th style="border: 1px solid #ccc; padding: 4px 6px; text-align: left;">Algo</th>
        <th style="border: 1px solid #ccc; padding: 4px 6px; text-align: left;">Difficulty</th>
        <th style="border: 1px solid #ccc; padding: 4px 6px; text-align: left;">Override</th>
        <th style="border: 1px solid #ccc; padding: 4px 6px; text-align: left;">Version</th>
        <th style="border: 1px solid #ccc; padding: 4px 6px; text-align: left;">Last submit</th>
    </tr>
    </thead>
    <tbody>
    <?php if (empty($workers)): ?>
    <tr><td colspan="6" style="border: 1px solid #ccc; padding: 6px;">No workers submitted in the last hour.</td></tr>
    <?php else: ?>
        <?php foreach ($workers as $w): ?>
            <?php
            $password = (string) ($w['password'] ?? '');
            $override = preg_match('/(?:^|,)([ds])=([^,]+)/', $password, $m)
                ? $m[1] . '=' . $m[2]
                : 'vardiff';
            $workerName = (string) ($w['worker'] ?? '') !== ''
                ? (string) $w['worker']
                : (string) ($w['name'] ?? '');
            ?>
        <tr>
            <td style="border: 1px solid #ccc; padding: 4px 6px;"><?= Html::encode($workerName) ?></td>
            <td style="border: 1px solid #ccc; padding: 4px 6px;"><?= Html::encode($w['algo']) ?></td>
            <td style="border: 1px solid #ccc; padding: 4px 6px;" title="raw target difficulty: <?= Html::encode($num($w['difficulty'])) ?>"><?= Html::encode($diffText($w['algo'], $w['difficulty'])) ?></td>
            <td style="border: 1px solid #ccc; padding: 4px 6px;"><?= Html::encode($override) ?></td>
            <td style="border: 1px solid #ccc; padding: 4px 6px;"><?= Html::encode($w['version']) ?></td>
            <td style="border: 1px solid #ccc; padding: 4px 6px;"><?= Html::encode($ago($w['time'])) ?></td>
        </tr>
        <?php endforeach ?>
    <?php endif ?>
    </tbody>
</table>
