<?php

/** @var yii\web\View $this */

use app\models\Coins;
use app\models\Markets;

echo <<<end
<style type="text/css">
tr.ssrow.filtered { display: none; }
.page .footer { clear: both; width: auto; margin-top: 16px; }
.coin-search-wrapper { text-align: right; margin-bottom: 6px; }
.coin-search-input { width: 140px; }
</style>
<div class="coin-search-wrapper">
<input class="search coin-search-input" type="search" data-column="all" placeholder="Search..." />
</div>
end;

Yii::$app->ViewUtils->showTableSorter('maintable', "{
	tableClass: 'dataGrid',
	textExtraction: {
		6: function(node, table, n) { return $(node).attr('data'); }
	},
	widgets: ['zebra','filter'],
	widgetOptions: {
		filter_external: '.search',
		filter_columnFilters: false,
		filter_childRows : true,
		filter_ignoreCase: true
	}
}");

echo <<<end
<thead><tr>
<th data-sorter="" width="30"></th>
<th data-sorter="text">Name</th>
<th data-sorter="text">Symbol</th>
<th data-sorter="text">Algo</th>
<th data-sorter="text">Status</th>
<th data-sorter="text">Version</th>
<th data-sorter="numeric">Created</th>
<th data-sorter="numeric">Height</th>
<th data-sorter="text">Message</th>
<th data-sorter="">Links</th>
</tr></thead>
<tbody>
end;

$total_active = 0;
$total_installed = 0;

$coins = Coins::find()->orderBy('created DESC')->all();
foreach($coins as $coin)
{
//	if($coin->symbol == 'BTC') continue;
	if($coin->enable) $total_active++;
	if($coin->installed) $total_installed++;

	$coin->version = substr($coin->version, 0, 20);
	$difficulty = Yii::$app->ConversionUtils->Itoa2($coin->difficulty, 3);
	// Note: 'created' field doesn't exist in coins table, using ID as fallback
	$created = $coin->id ? 'ID: ' . $coin->id : '-';

	echo '<tr class="ssrow">';
	echo '<td><img src="'.$coin->image.'" width="18"></td>';

	echo '<td><b>' . \yii\helpers\Html::a($coin->name, ['coin-update', 'id' => $coin->id]) . '</b></td>';

	echo '<td><b>' . \yii\helpers\Html::a($coin->symbol, ['coinwallet-update', 'id' => $coin->id]) . '</b></td>';

	echo "<td>$coin->algo</td>";

	if($coin->enable)
		echo "<td>running</td>";

	else if($coin->installed)
		echo "<td>installed</td>";

	else
		echo "<td></td>";

	echo "<td>$coin->version</td>";
	echo '<td data="'.$coin->created.'">'.$created.'</td>';

//	echo "<td align=right>$difficulty</td>";
	echo '<td align="center">'.$coin->block_height.'</td>';

	echo "<td>".substr($coin->errors, 0, 30)."</td>";
	echo "<td>";

	if(!empty($coin->link_bitcointalk))
		echo "<a href='$coin->link_bitcointalk' target=_blank>forum</a> ";

	if(!empty($coin->link_github))
		echo "<a href='$coin->link_github' target=_blank>git</a> ";

//	if(!empty($coin->link_explorer))
//		echo "<a href='$coin->link_explorer' target=_blank>expl</a> ";

	echo "<a href='http://google.com/search?q=$coin->name%20$coin->symbol%20bitcointalk' target=_blank>google</a> ";

//	if(!empty($coin->link_exchange))
//		echo "<a href='$coin->link_exchange' target=_blank>exch</a> ";


	$list2 = Markets::find()->where(['coinid' => $coin->id])->all();
	foreach($list2 as $market)
	{
		$url = '';//getMarketUrl($coin, $market->name);
		echo "<a href='$url' target=_blank>{$market->name}</a> ";
	}

	echo "</td>";
	echo "</tr>";
}

echo "</tbody>";
echo "<tfoot>";

$total = count($coins);

echo '<tr class="ssrow sfooter">';
echo '<th></th>';
echo '<th colspan="9">';
echo "<b>$total coins, $total_installed installed, $total_active running</b>";
echo '<br/><br/>' . \yii\helpers\Html::a('Add a new coin', ['coin-create']);
echo '</th>';
echo "</tr>";

echo '</tfoot>';
echo "</table>";
