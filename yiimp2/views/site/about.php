<?php

/** @var yii\web\View $this */

$this->title = 'About';
?>

<div class="container mt-4">
    <div class="card">
        <div class="card-header">
            <h4>About <?= YAAMP_SITE_NAME ?></h4>
        </div>
        <div class="card-body">
            <h5>Welcome to <?= YAAMP_SITE_NAME ?></h5>
            
            <p><?= YAAMP_SITE_NAME ?> is a multi-algorithm cryptocurrency mining pool that supports over 100 different mining algorithms.</p>
            
            <h5 class="mt-4">Features</h5>
            <ul>
                <li>Multi-algorithm support (SHA256, Scrypt, X11, Equihash, KawPow, and many more)</li>
                <li>Automatic payouts every <?= (YAAMP_PAYMENTS_FREQ / 3600) ?> hours</li>
                <li>No registration required - mine directly to your wallet</li>
                <li>Real-time statistics and monitoring</li>
                <li>Low fees and transparent operations</li>
                <li>Solo and shared mining modes</li>
                <li>Block explorer for all supported coins</li>
            </ul>
            
            <h5 class="mt-4">Getting Started</h5>
            <p>To start mining, simply point your miner to our stratum servers using your wallet address as the username. 
            Visit our <a href="<?= Yii::$app->homeUrl ?>site/mining">Mining</a> page for detailed instructions.</p>
            
            <h5 class="mt-4">Contact</h5>
            <p>For support and questions, please join our community channels listed on the homepage.</p>
        </div>
    </div>
</div>
