<?php

use yii\helpers\Html;

$stratumUrl = defined('YAAMP_STRATUM_URL')
    ? YAAMP_STRATUM_URL
    : (defined('YIIMP_STRATUM_URL') ? YIIMP_STRATUM_URL : 'localhost');
?>
<br>

<div class="main-left-box">
<div class="main-left-title">YIIMP STRATUM DIFFICULTY</div>
<div class="main-left-inner">

<p style="width: 700px;">Yiimp normally uses variable difficulty (vardiff) and aims for roughly 5–15
submits per minute. You can override the starting value with a password parameter.</p>

<pre class="main-left-box" style="padding: 6px; font-size: .9em; background-color: #ffffee; font-family: monospace;">
-o stratum+tcp://<?= Html::encode($stratumUrl) ?>:3433 -u WALLET -p d=64
</pre>

<p style="width: 700px;">
    <code>d=</code> pins the difficulty for the whole session.
    <code>sd=</code> only seeds the initial difficulty and still lets vardiff adjust it later.
    Values outside the configured range are clamped by the stratum server.
</p>

<p style="width: 700px;"><strong>Current stratum endpoints and custom difficulty ranges:</strong></p>

<table style="border-collapse: collapse; font-size: .9em; margin-bottom: 12px;">
    <thead>
    <tr style="background: #eee;">
        <th style="border: 1px solid #ccc; padding: 4px 8px; text-align: left;">Algo</th>
        <th style="border: 1px solid #ccc; padding: 4px 8px; text-align: left;">Port</th>
        <th style="border: 1px solid #ccc; padding: 4px 8px; text-align: left;">Pool default</th>
        <th style="border: 1px solid #ccc; padding: 4px 8px; text-align: left;">Custom <code>d=</code></th>
    </tr>
    </thead>
    <tbody>
    <tr>
        <td style="border: 1px solid #ccc; padding: 4px 8px;">Scrypt</td>
        <td style="border: 1px solid #ccc; padding: 4px 8px;">3433</td>
        <td style="border: 1px solid #ccc; padding: 4px 8px;">128</td>
        <td style="border: 1px solid #ccc; padding: 4px 8px;">64 – 1,048,576</td>
    </tr>
    <tr>
        <td style="border: 1px solid #ccc; padding: 4px 8px;">Ethash</td>
        <td style="border: 1px solid #ccc; padding: 4px 8px;">3639</td>
        <td style="border: 1px solid #ccc; padding: 4px 8px;">0.1</td>
        <td style="border: 1px solid #ccc; padding: 4px 8px;">0.01 – 100 (used as-is)</td>
    </tr>
    <tr>
        <td style="border: 1px solid #ccc; padding: 4px 8px;">Etchash</td>
        <td style="border: 1px solid #ccc; padding: 4px 8px;">3640</td>
        <td style="border: 1px solid #ccc; padding: 4px 8px;">0.1</td>
        <td style="border: 1px solid #ccc; padding: 4px 8px;">0.01 – 100 (used as-is)</td>
    </tr>
    <tr>
        <td style="border: 1px solid #ccc; padding: 4px 8px;">Firopow</td>
        <td style="border: 1px solid #ccc; padding: 4px 8px;">3641</td>
        <td style="border: 1px solid #ccc; padding: 4px 8px;">0.1</td>
        <td style="border: 1px solid #ccc; padding: 4px 8px;">ignored — pool fixed</td>
    </tr>
    </tbody>
</table>

<p style="width: 700px;"><strong>Examples:</strong></p>

<pre class="main-left-box" style="padding: 6px; font-size: .9em; background-color: #ffffee; font-family: monospace;">
# Scrypt, fixed diff 64
-o stratum+tcp://<?= Html::encode($stratumUrl) ?>:3433 -u WALLET.worker -p d=64

# Ethash / Etchash, fixed diff 0.1
-o stratum+tcp://<?= Html::encode($stratumUrl) ?>:3639 -u WALLET.worker -p d=0.1
</pre>

<p style="width: 700px;">
    Firopow (Kiiro) always uses the pool difficulty of <code>0.1</code>; a custom <code>d=</code>
    value is ignored on port 3641. For all other algos, omit <code>d=</code> to let vardiff manage
    the difficulty automatically.
</p>

</div>
</div>
