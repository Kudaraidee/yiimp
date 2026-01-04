<?php

use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'Admin Dashboard';
?>
<div class="admin-dashboard">
    <h1><?= Html::encode($this->title) ?></h1>

    <div class="row mt-4">
        <div class="col-md-3">
            <div class="card text-white bg-primary mb-3">
                <div class="card-body">
                    <h5 class="card-title">Active Workers</h5>
                    <p class="card-text display-4" id="active-workers">-</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-success mb-3">
                <div class="card-body">
                    <h5 class="card-title">Active Miners</h5>
                    <p class="card-text display-4" id="active-miners">-</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-info mb-3">
                <div class="card-body">
                    <h5 class="card-title">Blocks (24h)</h5>
                    <p class="card-text display-4" id="blocks-24h">-</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-warning mb-3">
                <div class="card-body">
                    <h5 class="card-title">Payouts (24h)</h5>
                    <p class="card-text display-4" id="payouts-24h">-</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5>Hashrate by Algorithm</h5>
                </div>
                <div class="card-body">
                    <div id="hashrate-by-algo">Loading...</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-md-12">
            <h3>Quick Actions</h3>
            <div class="list-group">
                <?= Html::a('Manage Coins', ['coinwallets'], ['class' => 'list-group-item list-group-item-action']) ?>
                <?= Html::a('Manage Users', ['user'], ['class' => 'list-group-item list-group-item-action']) ?>
                <?= Html::a('Monitor Workers', ['worker'], ['class' => 'list-group-item list-group-item-action']) ?>
                <?= Html::a('Monitor Payments', ['payments'], ['class' => 'list-group-item list-group-item-action']) ?>
                <?= Html::a('View Earnings', ['earning'], ['class' => 'list-group-item list-group-item-action']) ?>
                <?= Html::a('Exchange Status', ['exchange'], ['class' => 'list-group-item list-group-item-action']) ?>
                <?= Html::a('Active Connections', ['connections'], ['class' => 'list-group-item list-group-item-action']) ?>
                <?= Html::a('Security Monitoring', ['botnets'], ['class' => 'list-group-item list-group-item-action']) ?>
                <?= Html::a('Cache Management', ['memcached'], ['class' => 'list-group-item list-group-item-action']) ?>
                <?= Html::a('Version Information', ['version'], ['class' => 'list-group-item list-group-item-action']) ?>
            </div>
        </div>
    </div>
</div>

<?php
$dashboardUrl = Url::to(['dashboard-results']);
$this->registerJs(<<<JS
function updateDashboard() {
    $.ajax({
        url: '$dashboardUrl',
        type: 'GET',
        dataType: 'json',
        success: function(data) {
            $('#active-workers').text(data.active_workers);
            $('#active-miners').text(data.active_miners);
            $('#blocks-24h').text(data.total_blocks_24h);
            $('#payouts-24h').text(data.total_payouts_24h);
            
            // Display hashrate by algo
            var algoHtml = '<table class="table table-sm"><thead><tr><th>Algorithm</th><th>Workers</th></tr></thead><tbody>';
            if (data.hashrate_by_algo && data.hashrate_by_algo.length > 0) {
                data.hashrate_by_algo.forEach(function(item) {
                    algoHtml += '<tr><td>' + item.algo + '</td><td>' + item.workers + '</td></tr>';
                });
            } else {
                algoHtml += '<tr><td colspan="2" class="text-center">No active workers</td></tr>';
            }
            algoHtml += '</tbody></table>';
            $('#hashrate-by-algo').html(algoHtml);
        },
        error: function() {
            console.error('Failed to load dashboard data');
        }
    });
}

// Update dashboard on load and every 30 seconds
updateDashboard();
setInterval(updateDashboard, 30000);
JS
);
?>
