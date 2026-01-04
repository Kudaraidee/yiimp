<?php

/** @var yii\web\View $this */

$this->title = 'Stratum Difficulty';
?>

<div class="container mt-4">
    <div class="card">
        <div class="card-header">
            <h4><?= YAAMP_SITE_NAME ?> STRATUM DIFFICULTY</h4>
        </div>
        <div class="card-body">
            <p>By default, <?= YAAMP_SITE_NAME ?> will adjust the difficulty of your miner automatically over time until
            you have from 5 to 15 submits per minute. It's a good trade off between bandwidth and accuracy.</p>

            <p>You can also set a fixed custom difficulty using the password parameter. For example,
            if you want to set the difficulty to 64, you would use:</p>

            <pre class="bg-light p-3 rounded"><code>-o stratum+tcp://<?= YAAMP_STRATUM_URL ?>:3433 -u wallet_address -p d=64</code></pre>

            <p>Here are the accepted values for the custom diff:</p>

            <ul>
                <li><strong>Scrypt, Scrypt-N and Neoscrypt:</strong> from 2 to 65536</li>
                <li><strong>X11, X13, X14 and X15:</strong> from 0.002 to 0.512</li>
                <li><strong>Lyra2:</strong> from 0.01 to 2048</li>
            </ul>

            <p>If the difficulty is set higher than one of the mined coins, it will be forced down to fit
            the lowest coin's difficulty.</p>
        </div>
    </div>
</div>
