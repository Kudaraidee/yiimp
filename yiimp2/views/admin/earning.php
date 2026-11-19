<?php

/** @var yii\web\View          $this   */
/** @var int                   $coinId */
/** @var app\models\Coins|null $coin   */

$this->title = $coin ? 'Earnings — ' . $coin->symbol : 'Earnings';

echo Yii::$app->ViewUtils->getAdminSideBarLinks();
?>

<div id="main_results"></div>

<script>
var main_delay = 60000;
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
    var minh = $(window).height() - 150;
    $('#main_results').css({'min-height': minh + 'px'});
    clearTimeout(main_timeout);
    $.get('/admin/earning_results?id=<?= $coinId ?>', '', main_ready).fail(main_error);
}
</script>

<?php Yii::$app->view->registerJs('main_refresh();'); ?>
