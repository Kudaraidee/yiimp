<?php

use app\components\CspHelper;

$homeUrl = Yii::$app->homeUrl;

$id = (int) Yii::$app->getRequest()->getQueryParam('id');
echo <<<end

<div id='main_results'></div>

<br><br><br><br><br><br><br><br><br><br>
<br><br><br><br><br><br><br><br><br><br>
<br><br><br><br><br><br><br><br><br><br>
<br><br><br><br><br><br><br><br><br><br>

<?= CspHelper::beginScript() ?>

$(function()
{
	main_refresh();
});

var main_delay=60000;
var main_timeout;

function main_ready(data)
{
	$('#main_results').html(data);
	main_timeout = setTimeout(main_refresh, main_delay);
}

function main_error()
{
	main_timeout = setTimeout(main_refresh, main_delay*2);
}

function main_refresh()
{
	var url = "{$homeUrl}site/block_results?id=$id";

	clearTimeout(main_timeout);
	$.get(url, '', main_ready).error(main_error);
}

<?= CspHelper::endScript() ?>

end;


