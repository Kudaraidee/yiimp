<?php

use yii\helpers\Html;
use yii\helpers\Url;

/* @var $this yii\web\View */
/* @var $dataProvider yii\data\ActiveDataProvider */
/* @var $balance float|null */
/* @var $balancePending float|null */
/* @var $stats array */

$this->title = 'NiceHash Integration';
$this->params['breadcrumbs'][] = ['label' => 'Admin', 'url' => ['admin/dashboard']];
$this->params['breadcrumbs'][] = $this->title;

// Register auto-refresh JavaScript
$this->registerJs(<<<JS
function page_refresh() {
    index_refresh();
}

function index_ready(data) {
    $('#index_results').html(data);
}

function index_refresh() {
    var url = '/nicehash/index_results';
    $.get(url, '', index_ready);
}

// Auto-refresh every 30 seconds
setInterval(page_refresh, 30000);

// Initial load
$(document).ready(function() {
    index_refresh();
});
JS
);
?>

<div class="nicehash-index">
    <h1><?= Html::encode($this->title) ?></h1>

    <?php if (!defined('YAAMP_USE_NICEHASH_API') || !YAAMP_USE_NICEHASH_API): ?>
        <div class="alert alert-warning">
            <strong>Warning:</strong> NiceHash API integration is not enabled. 
            Please configure YAAMP_USE_NICEHASH_API, NICEHASH_API_KEY, and NICEHASH_API_ID in your serverconfig.php.
        </div>
    <?php endif; ?>

    <?php if ($balance !== null): ?>
        <div class="alert alert-info">
            <strong>NiceHash Balance:</strong> 
            <?= number_format($balance, 8) ?> BTC
            <?php if ($balancePending !== null && $balancePending > 0): ?>
                (Pending: <?= number_format($balancePending, 8) ?> BTC)
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($stats)): ?>
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Active Orders</h5>
                        <p class="card-text display-6"><?= $stats['total_orders'] ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Total Workers</h5>
                        <p class="card-text display-6"><?= $stats['total_workers'] ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Total Hashrate</h5>
                        <p class="card-text display-6">
                            <?php
                            $hashrate = $stats['total_hashrate'];
                            if ($hashrate >= 1000000000000) {
                                echo number_format($hashrate / 1000000000000, 2) . ' TH/s';
                            } elseif ($hashrate >= 1000000000) {
                                echo number_format($hashrate / 1000000000, 2) . ' GH/s';
                            } elseif ($hashrate >= 1000000) {
                                echo number_format($hashrate / 1000000, 2) . ' MH/s';
                            } else {
                                echo number_format($hashrate / 1000, 2) . ' kH/s';
                            }
                            ?>
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Total BTC</h5>
                        <p class="card-text display-6"><?= number_format($stats['total_btc'], 8) ?></p>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div id="index_results">
        <div class="text-center py-5">
            <div class="spinner-border" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Loading NiceHash orders...</p>
        </div>
    </div>
</div>
