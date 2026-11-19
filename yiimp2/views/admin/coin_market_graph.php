<?php

/*
JavascriptFile("/extensions/jqplot/jquery.jqplot.js");
JavascriptFile("/extensions/jqplot/plugins/jqplot.enhancedLegendRenderer.js");
JavascriptFile("/extensions/jqplot/plugins/jqplot.dateAxisRenderer.js");
JavascriptFile("/extensions/jqplot/plugins/jqplot.highlighter.js");
*/
$refSymbol = 'BTC';
if ($coin->symbol == 'BTC') $refSymbol = 'USD';

echo <<<end

<style type="text/css">
#graph_history_price, #graph_history_balance {
	width: 75%; height: 300px; float: right;
	margin-bottom: 8px;
}

</style>

<div class="graph" id="graph_history_price"></div>
<div class="graph" id="graph_history_balance"></div>

<script type="text/javascript">

var last_graph_update, graph_need_update, graph_timeout = 0;
var price_graph, balance_graph = '';

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

	var url = "/admin/graph_market_balance?id={$coin->id}";
	$.get(url, '', graph_balance_data);

	var url = "/admin/graph_market_prices?id={$coin->id}";
	$.get(url, '', graph_price_data);
}

function graph_resized()
{
	graph_need_update = true;
	if (graph_timeout) clearTimeout(graph_timeout);
	graph_timeout = setTimeout(graph_refresh, 2000);
}

function graph_price_data(data) {
	var t = JSON.parse(data);
	yiimpChart('graph_history_price', t.data, {
		title:  'Price history',
		labels: t.labels,
		decimals: 8
	});
}

function graph_balance_data(data) {
	var t = JSON.parse(data);
	yiimpChart('graph_history_balance', t.data, {
		title:  'Balances',
		labels: t.labels,
		fill:   true,
		stack:  true,
		decimals: 8
	});
}
</script>
end;

// JavascriptReady("$(window).resize(graph_resized);");
