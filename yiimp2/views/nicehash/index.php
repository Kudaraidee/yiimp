<?php
/** @var yii\web\View $this */
$this->title = 'NiceHash Orders';
?>
<div id="index_results"></div>

<script>
$(function () { index_refresh(); });

var index_timeout;
function index_ready(data) {
    $('#index_results').html(data);
    index_timeout = setTimeout(index_refresh, 30000);
}
function index_refresh() {
    clearTimeout(index_timeout);
    $.get('/nicehash/index_results', '', index_ready);
}
</script>
