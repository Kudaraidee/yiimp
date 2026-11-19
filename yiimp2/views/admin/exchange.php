<?php

/** @var yii\web\View $this */

$this->title = 'Exchange';
echo Yii::$app->ViewUtils->getAdminSideBarLinks();
?>

<div id="main_results"></div>

<script>
$(function () { main_refresh(); });

var main_delay   = 60000;
var main_timeout;

function main_ready(data) {
    $('#main_results').html(data);
    if (typeof lucide !== 'undefined') lucide.createIcons();
    main_timeout = setTimeout(main_refresh, main_delay);
}
function main_error() {
    main_timeout = setTimeout(main_refresh, main_delay * 2);
}
function main_refresh() {
    clearTimeout(main_timeout);
    $.get('/admin/exchange_results', '', main_ready).fail(main_error);
}
</script>
