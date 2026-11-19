<?php

/** @var yii\web\View $this */
/** @var string[]     $serverList */

echo Yii::$app->ViewUtils->getAdminSideBarLinks();

echo '&nbsp;<a href="/admin/emptymarkets">Empty Markets</a>&nbsp;';

$server = Yii::$app->getRequest()->getQueryParam('server');

echo <<<end
<div align="right" style="margin-top: -14px; margin-bottom: 6px;">
Select Server:
<select id="server_select">
<option value="">all</option>
end;

foreach ($serverList as $host) {
	echo '<option value="' . \yii\helpers\Html::encode($host) . '">' . \yii\helpers\Html::encode($host) . '</option>';
}

echo <<<end
</select>&nbsp;
<input class="search" type="search" data-column="all" style="width: 140px;" placeholder="Search..." />
</div>

<div id='main_results'>
<br><br><br><br><br><br><br><br><br><br>
<br><br><br><br><br><br><br><br><br><br>
<br><br><br><br><br><br><br><br><br><br>
<br><br><br><br><br><br><br><br><br><br>
<br><br><br><br><br><br><br><br><br><br>
</div>

<br><a href='/admin/coinwallet_create'><img width=16 src=''><b>CREATE COIN</b></a>
<!-- br><a href='/admin/updateprice'><img width=16 src=''><b>UPDATE PRICE</b></a -->
<!-- br><a href='/admin/dopayments'><img width=16 src=''><b>DO PAYMENTS</b></a -->

<br><br><br>

end;

Yii::$app->view->registerJs("
$('#server_select').change(function(event)
{
	var server = $('#server_select').val();
	clearTimeout(main_timeout);
	window.location.href = '/admin/coinwallets?server='+server;
});

$(function()
{
	main_refresh();
});

var main_delay=30000;
var main_timeout;
var lastSearch = false;

function main_ready(data)
{
	$('#main_results').html(data);
	$('#server_select').val('{$server}');

	if (lastSearch !== false) {
		$('input.search').val(lastSearch);
		$('table.dataGrid').trigger('search');
	}

	main_timeout = setTimeout(main_refresh, main_delay);
}

function main_refresh()
{
	var url = \"/admin/coinwallet_results?server=$server\";

	clearTimeout(main_timeout);
	lastSearch = $('input.search').val();
	$.get(url, '', main_ready);
}
");