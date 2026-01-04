<?php

use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'Exchange Management';
?>
<div class="admin-exchange">
    <h1><?= Html::encode($this->title) ?></h1>

    <div class="card mt-4">
        <div class="card-body">
            <div id="exchange-results">Loading...</div>
        </div>
    </div>
</div>

<?php
$exchangeResultsUrl = Url::to(['exchange-results']);
$this->registerJs(<<<JS
function loadExchange() {
    $.ajax({
        url: '$exchangeResultsUrl',
        type: 'GET',
        success: function(data) {
            $('#exchange-results').html(data);
        }
    });
}

loadExchange();
setInterval(loadExchange, 60000);
JS
);
?>
