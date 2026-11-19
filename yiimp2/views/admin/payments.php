<?php

/** @var yii\web\View          $this   */
/** @var int                   $coinId */
/** @var app\models\Coins|null $coin   */

use yii\helpers\Html;

$this->title = 'Payments' . ($coin ? ' — ' . $coin->symbol : '');

echo Yii::$app->ViewUtils->getAdminSideBarLinks();
?>

<div id="main_results"></div>

<script>
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
    $('#main_results').css('min-height', ($(window).height() - 150) + 'px');
    $.get('<?= Yii::$app->homeUrl ?>admin/payments_results?id=<?= $coinId ?>', '', main_ready)
     .fail(main_error);
}
$(function () { main_refresh(); });
</script>
