<?php

use yii\helpers\Url;

/** @var yii\web\View $this */

$algo = Yii::$app->session->get('yaamp-algo');
$homeUrl = Yii::$app->homeUrl;

$height = '240px';

// Build the miners results URL - use underscore format to match action name
$minersResultsUrl = Url::to(['site/miners_results']);

// Register JavaScript via Yii's registerJs for CSP compliance
$minersJs = <<<JS
// Event listeners
document.addEventListener('DOMContentLoaded', function() {
	var resumeBtn = document.getElementById('resume_update_button');
	if (resumeBtn) {
		resumeBtn.addEventListener('click', function() {
			auto_page_resume();
		});
	}
});

function page_refresh()
{
	miners_refresh();
	pool_current_refresh();
}

function select_algo(algo)
{
	window.location.href = '{$homeUrl}site/algo?algo='+algo+'&r=/site/miners';
}

////////////////////////////////////////////////////

function pool_current_ready(data)
{
	$('#pool_current_results').html(data);
}

function pool_current_refresh()
{
	var url = "{$homeUrl}site/current_results";
	$.get(url, '', pool_current_ready);
}

////////////////////////////////////////////////////

function miners_ready(data)
{
	$('#miners_results').html(data);
}

function miners_refresh()
{
	var url = "{$minersResultsUrl}";
	$.get(url, '', miners_ready).fail(function(xhr, status, error) {
		console.error('AJAX Error:', status, error);
		$('#miners_results').html('<div class="alert alert-danger">Failed to load miners data. Please try again.</div>');
	});
}
JS;

$this->registerJs($minersJs, \yii\web\View::POS_HEAD);
$this->registerJsFile('@web/js/auto_refresh.js', ['depends' => [yii\web\JqueryAsset::class]]);

echo <<<end

<div id='resume_update_button' class='resume-update-button'>
	<b>Auto refresh is paused - Click to resume</b>
</div>

<table cellspacing=20 width=100%>
<tr><td valign=top width=50%>

<div id='miners_results'>
<br><br><br><br><br><br><br><br><br><br>
<br><br><br><br><br><br><br><br><br><br>
</div>

</td><td valign=top>

<div id='pool_current_results'>
<br><br><br><br><br><br><br><br><br><br>
</div>

</td></tr></table>

<br><br><br><br><br><br><br><br><br><br>
<br><br><br><br><br><br><br><br><br><br>
<br><br><br><br><br><br><br><br><br><br>
<br><br><br><br><br><br><br><br><br><br>

end;
