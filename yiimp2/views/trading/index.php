<?php

use yii\helpers\Html;
use yii\helpers\Url;

/* @var $this yii\web\View */
/* @var $marketData array */

$this->title = 'Trading & Market Prices';
$this->params['breadcrumbs'][] = $this->title;

// Register JavaScript for auto-refresh
$this->registerJs("
var delay = 60000;

$(function() {
    setTimeout(mining_refresh, delay);
});

function mining_ready(data) {
    $('#mining_results').html(data);
    setTimeout(mining_refresh, delay);
}

function mining_error() {
    setTimeout(mining_refresh, delay * 2);
}

function mining_refresh() {
    var url = '" . Url::to(['trading/mining_results']) . "';
    $.get(url, '', mining_ready).error(mining_error);
}
");

?>

<div class="trading-index">
    <h1><?= Html::encode($this->title) ?></h1>

    <div class="row">
        <div class="col-md-12">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Market Prices</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($marketData)): ?>
                        <p class="text-muted">No market data available.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th></th>
                                        <th>Coin</th>
                                        <th>Symbol</th>
                                        <th>Algorithm</th>
                                        <th class="text-end">Best Price (BTC)</th>
                                        <th class="text-end">24h Volume</th>
                                        <th>Markets</th>
                                        <th class="text-end">Last Update</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($marketData as $data): ?>
                                        <?php 
                                        $coin = $data['coin'];
                                        $markets = $data['markets'];
                                        $bestPrice = $data['best_price'];
                                        ?>
                                        <tr>
                                            <td>
                                                <?php if ($coin->image): ?>
                                                    <img src="<?= Html::encode($coin->image) ?>" 
                                                         alt="<?= Html::encode($coin->symbol) ?>" 
                                                         width="20" height="20">
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <strong>
                                                    <?= Html::a(
                                                        Html::encode($coin->name),
                                                        ['site/coin', 'id' => $coin->id]
                                                    ) ?>
                                                </strong>
                                            </td>
                                            <td><?= Html::encode($coin->symbol) ?></td>
                                            <td>
                                                <span class="badge bg-secondary">
                                                    <?= Html::encode($coin->algo) ?>
                                                </span>
                                            </td>
                                            <td class="text-end">
                                                <strong><?= Yii::$app->formatter->asDecimal($bestPrice, 8) ?></strong>
                                            </td>
                                            <td class="text-end">
                                                <?php
                                                $totalVolume = 0;
                                                foreach ($markets as $market) {
                                                    $totalVolume += $market->volume;
                                                }
                                                echo Yii::$app->formatter->asDecimal($totalVolume, 4);
                                                ?>
                                            </td>
                                            <td>
                                                <?php
                                                $marketNames = [];
                                                foreach ($markets as $market) {
                                                    $marketNames[] = Html::encode($market->name);
                                                }
                                                echo implode(', ', array_slice($marketNames, 0, 3));
                                                if (count($marketNames) > 3) {
                                                    echo ' <small class="text-muted">+' . (count($marketNames) - 3) . ' more</small>';
                                                }
                                                ?>
                                            </td>
                                            <td class="text-end">
                                                <?php
                                                $lastUpdate = 0;
                                                foreach ($markets as $market) {
                                                    if ($market->lasttraded > $lastUpdate) {
                                                        $lastUpdate = $market->lasttraded;
                                                    }
                                                }
                                                if ($lastUpdate > 0) {
                                                    echo '<small>' . Yii::$app->formatter->asRelativeTime($lastUpdate) . '</small>';
                                                } else {
                                                    echo '<small class="text-muted">Never</small>';
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Mining Profitability</h5>
                </div>
                <div class="card-body">
                    <div id="mining_results">
                        <div class="text-center py-5">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-2 text-muted">Loading profitability data...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
