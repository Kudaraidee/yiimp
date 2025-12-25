<?php

/** @var yii\web\View $this */

$this->title = 'Multi-Algo Switching';
?>

<div class="container mt-4">
    <div class="card">
        <div class="card-header">
            <h4>MULTI-ALGO SWITCHING</h4>
        </div>
        <div class="card-body">
            <p>Here's how you can achieve automatic switching to the best algo.</p>

            <p>Use the password parameter to define a set of algos you want to mine. Your miner
            connection will close (and move to your next configured algo) if the algo is not the best profitable of your set.</p>

            <h5 class="mt-4">Examples:</h5>

            <pre class="bg-light p-3 rounded"><code>-p x11,neoscrypt,lyra2</code></pre>

            <pre class="bg-light p-3 rounded"><code>-p scrypt,scryptn</code></pre>

            <p>The difficulty parameter can be combined with algos:</p>

            <pre class="bg-light p-3 rounded"><code>-p d=64,scrypt,scryptn</code></pre>

            <p>Or with any other parameter:</p>

            <pre class="bg-light p-3 rounded"><code>-p rig1,scrypt,scryptn</code></pre>

            <p><strong>Note:</strong> The password parameter must be all together, that is no spaces.</p>

            <h5 class="mt-4">Miner Configuration:</h5>

            <p>To complete your setup, you will need to configure your miner to round robin through all algos.</p>

            <p>Here is an example of a windows batch file for ccminer:</p>

            <pre class="bg-light p-3 rounded"><code>:start

ccminer -r 0 -a x11   -o stratum+tcp://<?= YAAMP_STRATUM_URL ?>:3533 -u wallet -p x11,x13,x14,x15,quark,lyra2
ccminer -r 0 -a x13   -o stratum+tcp://<?= YAAMP_STRATUM_URL ?>:3633 -u wallet -p x11,x13,x14,x15,quark,lyra2
ccminer -r 0 -a x15   -o stratum+tcp://<?= YAAMP_STRATUM_URL ?>:3733 -u wallet -p x11,x13,x14,x15,quark,lyra2
ccminer -r 0 -a lyra2 -o stratum+tcp://<?= YAAMP_STRATUM_URL ?>:4433 -u wallet -p x11,x13,x14,x15,quark,lyra2
ccminer -r 0 -a quark -o stratum+tcp://<?= YAAMP_STRATUM_URL ?>:4033 -u wallet -p x11,x13,x14,x15,quark,lyra2

sleep 5000
goto start</code></pre>

            <h5 class="mt-4">Profitability Normalization:</h5>

            <p>By default, we use our built-in factor table to normalize the profitability. The scrypt algo
            is the reference with a factor of 1.</p>

            <pre class="bg-light p-3 rounded"><code>'scrypt'    => 1,
'scryptn'   => 0.5,
'c11'       => 2.0,
'x11'       => 5.5,
'x13'       => 3.9,
'x14'       => 3.7,
'x15'       => 3.5,
'nist5'     => 6.0,
'zr5'       => 10.0,
'drop'      => 5.0,
'neoscrypt' => 0.3,
'lyra2'     => 1.3,
'quark'     => 6</code></pre>

            <p>But you can also specify your own profitability factors for each algo:</p>

            <pre class="bg-light p-3 rounded"><code>-p x11=5.1,neoscrypt=0.5,lyra2=2</code></pre>
        </div>
    </div>
</div>
