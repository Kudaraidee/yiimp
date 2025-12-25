<?php

use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'Exchange Balances';
?>
<div class="admin-balances">
    <h1><?= Html::encode($this->title) ?></h1>

    <div class="card mt-4">
        <div class="card-body">
            <div id="balances-results">Loading...</div>
        </div>
    </div>
</div>

<?php
$balancesResultsUrl = Url::to(['balances-results']);
$this->registerJs(<<<JS
function loadBalances() {
    $.ajax({
        url: '$balancesResultsUrl',
        type: 'GET',
        success: function(data) {
            $('#balances-results').html(data);
        }
    });
}

loadBalances();
setInterval(loadBalances, 60000);
JS
);
?>
