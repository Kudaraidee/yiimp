<?php

/** @var yii\web\View $this */
/** @var string[]     $serverList */

echo Yii::$app->ViewUtils->getAdminSideBarLinks();

echo '&nbsp;<a href="/admin/emptymarkets">Empty Markets</a>&nbsp;';

$server     = (string) Yii::$app->getRequest()->getQueryParam('server');
$serverJson = json_encode($server);

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

<br><a href='/admin/coinwallet_create'><b>CREATE COIN</b></a>

<br><br><br>

end;

Yii::$app->view->registerJs("
$('#server_select').change(function(event)
{
	var server = $('#server_select').val();
	clearTimeout(main_timeout);
	window.location.href = '/admin/coinwallets?server='+encodeURIComponent(server);
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
	$('#server_select').val({$serverJson});

	if (lastSearch) {
		$('input.search').val(lastSearch);
		$('#maintable').trigger('search');
	}

	main_timeout = setTimeout(main_refresh, main_delay);
}

function main_refresh()
{
	var url = '/admin/coinwallet_results?server=' + encodeURIComponent({$serverJson});

	clearTimeout(main_timeout);
	lastSearch = $('input.search').val() || false;
	$.get(url, '', main_ready);
}
");