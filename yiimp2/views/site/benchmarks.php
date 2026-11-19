<?php
/** @var yii\web\View $this */
use yii\helpers\Html;

$this->title = 'Benchmarks';
$stratumUrl = defined('YIIMP_STRATUM_URL') ? YIIMP_STRATUM_URL : '';
?>

<div class="main-left-box">
<div class="main-left-title">YIIMP BENCHMARKS</div>
<div class="main-left-inner">

<p style="width:700px;">YiiMP allows users to share their ccminer (1.7.6+) device hashrate. More supported miners may be added later.</p>

<pre class="main-left-box" style="padding:3px; font-size:.9em; background-color:#ffffee; font-family:monospace;">
-o stratum+tcp://<?= Html::encode($stratumUrl) ?>:&lt;PORT&gt; -a &lt;algo&gt; -u &lt;wallet_address&gt; -p stats
</pre>

<p style="width:700px;">
With this option enabled, the stratum will request device stats every 50 shares (up to 4 times).<br><br>
You can combine this option with others, such as the <?= Html::a('pool difficulty', ['/site/diff']) ?> setting, separated by a comma.<br><br>
You can also use the generic username <b>benchmark</b> if you don't have a valid address —
in that case you will mine without reward (like a donator).
</p>

<p style="width:700px;">
Note: only the first device's stats will be submitted on multi-GPU systems.<br>
To monitor a different card with ccminer, use the <b>--device</b> parameter, e.g. <b>-d 1</b>.
</p>

<p style="margin-bottom:0; font-weight:bold;">Compatible versions of ccminer:</p>
<ul>
<li><?= Html::a('https://github.com/tpruvot/ccminer/releases', 'https://github.com/tpruvot/ccminer/releases', ['target' => '_blank']) ?></li>
<li><?= Html::a('https://github.com/KlausT/ccminer/releases', 'https://github.com/KlausT/ccminer/releases', ['target' => '_blank']) ?></li>
</ul>

<br>
<?= Html::a('Browse submitted benchmark results', ['/bench']) ?>

</div>
</div>
