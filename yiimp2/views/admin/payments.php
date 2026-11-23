<?php

/** @var yii\web\View          $this   */
/** @var int                   $coinId */
/** @var app\models\Coins|null $coin   */

$this->title = 'Payments' . ($coin ? ' — ' . $coin->symbol : '');
$badge = $coin ? strtoupper((string) $coin->symbol) : 'ALL';
?>
<div class="admn-page">
<?= Yii::$app->ViewUtils->getAdminMiningToolbar('Payments', 'payout queue · admin console', $badge, 'live · 60s') ?>

<div id="main_results"></div>
</div>

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
