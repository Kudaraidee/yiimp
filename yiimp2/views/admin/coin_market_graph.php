<?php

use app\components\CspHelper;
use app\assets\ChartHelperAsset;

// Register Chart.js assets for CSP-compliant charting
ChartHelperAsset::register($this);

$refSymbol = 'BTC';
if ($coin->symbol == 'BTC') $refSymbol = 'USD';

echo <<<end

<style type="text/css">
#graph_history_price, #graph_history_balance {
	width: 75%; height: 300px; float: right;
	margin-bottom: 8px;
}
.chart-title {
	margin-bottom: 4px;
	font-weight: bold;
}
</style>

<div class="graph" id="graph_history_price"></div>
<div class="graph" id="graph_history_balance"></div>

<?= CspHelper::beginScript() ?>

var last_graph_update, graph_need_update, graph_timeout = 0;
var price_graph, balance_graph = null;

function graph_refresh()
{
	var now = Date.now()/1000;
	if (!graph_need_update && (now - 300) < last_graph_update) {
		return;
	}
	last_graph_update = now; graph_need_update = false;
	if (graph_timeout) clearTimeout(graph_timeout);

	var w = 0 + $('div#graph_history_price').parent().width();
	w = w - $('div#sums').width() - 32;
	$('.graph').width(w);

	var url = "<?= \yii\helpers\Url::to(['graph-market-balance', 'id' => $coin->id]) ?>";
	$.get(url, '', graph_balance_data);

	var url = "<?= \yii\helpers\Url::to(['graph-market-prices', 'id' => $coin->id]) ?>";
	$.get(url, '', graph_price_data);
}

function graph_resized()
{
	graph_need_update = true;
	if (graph_timeout) clearTimeout(graph_timeout);
	graph_timeout = setTimeout(graph_refresh, 2000);
}

function graph_price_data(data)
{
	// Destroy existing chart
	if (price_graph) {
		price_graph.destroy();
		price_graph = null;
	}

	var t = JSON.parse(data);
	
	// Prepare datasets for Chart.js
	var datasets = [];
	var colors = ChartHelper.colors;
	
	for (var i = 0; i < t.data.length; i++) {
		datasets.push({
			label: t.labels[i] || ('Series ' + (i + 1)),
			data: ChartHelper.formatTimeSeriesData(t.data[i]),
			borderColor: colors[i % colors.length],
			backgroundColor: 'transparent',
			fill: false,
			tension: 0.4,
			pointRadius: 1,
			pointHoverRadius: 4
		});
	}
	
	var container = document.getElementById('graph_history_price');
	if (!container) return;
	
	container.innerHTML = '';
	var canvas = document.createElement('canvas');
	container.appendChild(canvas);
	var ctx = canvas.getContext('2d');
	
	price_graph = new Chart(ctx, {
		type: 'line',
		data: { datasets: datasets },
		options: {
			responsive: true,
			maintainAspectRatio: false,
			plugins: {
				title: {
					display: true,
					text: 'Price history',
					font: { weight: 'bold' }
				},
				legend: {
					display: true,
					position: 'top',
					labels: {
						usePointStyle: true,
						padding: 10
					},
					onClick: function(e, legendItem, legend) {
						var index = legendItem.datasetIndex;
						var ci = legend.chart;
						var meta = ci.getDatasetMeta(index);
						meta.hidden = meta.hidden === null ? !ci.data.datasets[index].hidden : null;
						ci.update();
					}
				},
				tooltip: {
					mode: 'index',
					intersect: false,
					callbacks: {
						title: function(context) {
							if (context.length > 0) {
								var date = new Date(context[0].parsed.x);
								return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
							}
							return '';
						},
						label: function(context) {
							var label = context.dataset.label || '';
							if (label) {
								label += ': ';
							}
							if (context.parsed.y !== null) {
								label += context.parsed.y.toFixed(8) + ' {$refSymbol}';
							}
							return label;
						}
					}
				}
			},
			scales: {
				x: {
					type: 'time',
					time: {
						displayFormats: {
							hour: 'HH:mm',
							day: 'MMM d'
						}
					},
					grid: { display: true, color: 'rgba(0, 0, 0, 0.1)' },
					ticks: { font: { size: 10 } }
				},
				y: {
					min: t.rangeMin,
					max: t.rangeMax,
					grid: { display: true, color: 'rgba(0, 0, 0, 0.1)' },
					ticks: { font: { size: 10 } }
				}
			},
			interaction: { mode: 'nearest', axis: 'x', intersect: false }
		}
	});
	
	// Register with ChartManager if available
	if (typeof ChartManager !== 'undefined') {
		ChartManager.register('graph_history_price', price_graph);
	}
}

function graph_balance_data(data)
{
	// Destroy existing chart
	if (balance_graph) {
		balance_graph.destroy();
		balance_graph = null;
	}

	var t = JSON.parse(data);
	
	// Prepare datasets for Chart.js (stacked area)
	var datasets = [];
	var colors = ChartHelper.colors;
	
	for (var i = 0; i < t.data.length; i++) {
		datasets.push({
			label: t.labels[i] || ('Series ' + (i + 1)),
			data: ChartHelper.formatTimeSeriesData(t.data[i]),
			backgroundColor: colors[i % colors.length],
			borderColor: colors[i % colors.length],
			fill: true,
			tension: 0.4,
			pointRadius: 0
		});
	}
	
	var container = document.getElementById('graph_history_balance');
	if (!container) return;
	
	container.innerHTML = '';
	var canvas = document.createElement('canvas');
	container.appendChild(canvas);
	var ctx = canvas.getContext('2d');
	
	balance_graph = new Chart(ctx, {
		type: 'line',
		data: { datasets: datasets },
		options: {
			responsive: true,
			maintainAspectRatio: false,
			plugins: {
				title: {
					display: true,
					text: 'Balances',
					font: { weight: 'bold' }
				},
				legend: {
					display: true,
					position: 'top',
					labels: {
						usePointStyle: true,
						padding: 10
					},
					onClick: function(e, legendItem, legend) {
						var index = legendItem.datasetIndex;
						var ci = legend.chart;
						var meta = ci.getDatasetMeta(index);
						meta.hidden = meta.hidden === null ? !ci.data.datasets[index].hidden : null;
						ci.update();
					}
				},
				tooltip: {
					mode: 'index',
					intersect: false,
					callbacks: {
						title: function(context) {
							if (context.length > 0) {
								var date = new Date(context[0].parsed.x);
								return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
							}
							return '';
						},
						label: function(context) {
							var label = context.dataset.label || '';
							if (label) {
								label += ': ';
							}
							if (context.parsed.y !== null) {
								label += context.parsed.y.toFixed(8) + ' {$coin->symbol}';
							}
							return label;
						}
					}
				},
				filler: { propagate: false }
			},
			scales: {
				x: {
					type: 'time',
					time: {
						displayFormats: {
							hour: 'HH:mm',
							day: 'MMM d'
						}
					},
					grid: { display: true, color: 'rgba(0, 0, 0, 0.1)' },
					ticks: { font: { size: 10 } }
				},
				y: {
					stacked: true,
					min: t.rangeMin,
					max: t.rangeMax,
					grid: { display: true, color: 'rgba(0, 0, 0, 0.1)' },
					ticks: { font: { size: 10 } }
				}
			},
			interaction: { mode: 'nearest', axis: 'x', intersect: false }
		}
	});
	
	// Register with ChartManager if available
	if (typeof ChartManager !== 'undefined') {
		ChartManager.register('graph_history_balance', balance_graph);
	}
}
<?= CspHelper::endScript() ?>
end;
