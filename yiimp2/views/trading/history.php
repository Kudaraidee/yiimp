<?php

use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\ActiveForm;
use app\components\CspHelper;
use app\assets\ChartJsAsset;

/* @var $this yii\web\View */
/* @var $coin app\models\Coins */
/* @var $marketHistory array */
/* @var $chartData array */
/* @var $coinsWithHistory array */

$this->title = 'Market History';
$this->params['breadcrumbs'][] = ['label' => 'Trading', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;

// Register Chart.js assets for CSP-compliant charting
ChartJsAsset::register($this);

// Register JavaScript for chart
if ($coin && !empty($chartData)) {
    $chartDataJson = json_encode($chartData);
    $this->registerJs("
    var chartData = $chartDataJson;
    
    // Prepare datasets for Chart.js
    var datasets = [];
    var colors = [
        'rgb(255, 99, 132)',
        'rgb(54, 162, 235)',
        'rgb(255, 205, 86)',
        'rgb(75, 192, 192)',
        'rgb(153, 102, 255)',
        'rgb(255, 159, 64)'
    ];
    
    var colorIndex = 0;
    for (var marketId in chartData) {
        var marketData = chartData[marketId];
        var marketName = marketData.market ? marketData.market.name : 'Market ' + marketId;
        var color = colors[colorIndex % colors.length];
        
        var dataPoints = marketData.data.map(function(point) {
            return {
                x: new Date(point.time * 1000),
                y: point.price
            };
        });
        
        datasets.push({
            label: marketName,
            data: dataPoints,
            borderColor: color,
            backgroundColor: color.replace('rgb', 'rgba').replace(')', ', 0.1)'),
            tension: 0.1,
            fill: false
        });
        
        colorIndex++;
    }
    
    // Create chart
    var ctx = document.getElementById('priceChart').getContext('2d');
    var chart = new Chart(ctx, {
        type: 'line',
        data: {
            datasets: datasets
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: {
                    type: 'time',
                    time: {
                        unit: 'hour',
                        displayFormats: {
                            hour: 'MMM d, HH:mm'
                        }
                    },
                    title: {
                        display: true,
                        text: 'Time'
                    }
                },
                y: {
                    title: {
                        display: true,
                        text: 'Price (BTC)'
                    },
                    ticks: {
                        callback: function(value) {
                            return value.toFixed(8);
                        }
                    }
                }
            },
            plugins: {
                legend: {
                    display: true,
                    position: 'top'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': ' + context.parsed.y.toFixed(8) + ' BTC';
                        }
                    }
                }
            }
        }
    });
    ");
}

?>

<div class="trading-history">
    <h1><?= Html::encode($this->title) ?></h1>

    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Select Coin</h5>
                    <form method="get" action="<?= Url::to(['trading/history']) ?>" id="coin-select-form">
                        <div class="mb-3">
                            <select name="id" class="form-select" id="coin-select">
                                <option value="">-- Select a coin --</option>
                                <?php foreach ($coinsWithHistory as $c): ?>
                                    <option value="<?= $c->id ?>" <?= $coin && $coin->id == $c->id ? 'selected' : '' ?>>
                                        <?= Html::encode($c->name) ?> (<?= Html::encode($c->symbol) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php if ($coin): ?>
        <div class="row">
            <div class="col-md-12">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <?= Html::encode($coin->name) ?> (<?= Html::encode($coin->symbol) ?>) - Price History
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($chartData)): ?>
                            <p class="text-muted">No market history data available for this coin.</p>
                        <?php else: ?>
                            <div class="h-400">
                                <canvas id="priceChart"></canvas>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!empty($marketHistory)): ?>
            <div class="row">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Historical Data (Last 7 Days)</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm table-striped">
                                    <thead>
                                        <tr>
                                            <th>Time</th>
                                            <th>Market</th>
                                            <th class="text-end">Price (BTC)</th>
                                            <th class="text-end">Price 2 (BTC)</th>
                                            <th class="text-end">Balance</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach (array_reverse($marketHistory) as $record): ?>
                                            <tr>
                                                <td>
                                                    <small><?= date('Y-m-d H:i:s', $record->time) ?></small>
                                                </td>
                                                <td>
                                                    <?php if ($record->market): ?>
                                                        <?= Html::encode($record->market->name) ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">Unknown</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-end">
                                                    <?= Yii::$app->formatter->asDecimal($record->price, 8) ?>
                                                </td>
                                                <td class="text-end">
                                                    <?= Yii::$app->formatter->asDecimal($record->price2, 8) ?>
                                                </td>
                                                <td class="text-end">
                                                    <?= $record->balance ? Yii::$app->formatter->asDecimal($record->balance, 4) : '-' ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="alert alert-info">
            <i class="bi bi-info-circle"></i>
            Please select a coin to view its market history and price charts.
        </div>
    <?php endif; ?>
</div>

<?= CspHelper::beginScript() ?>
// Event listener for coin selection
document.addEventListener('DOMContentLoaded', function() {
	var coinSelect = document.getElementById('coin-select');
	if (coinSelect) {
		coinSelect.addEventListener('change', function() {
			var form = document.getElementById('coin-select-form');
			if (form) {
				form.submit();
			}
		});
	}
});
<?= CspHelper::endScript() ?>
