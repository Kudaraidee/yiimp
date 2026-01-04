<?php

use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'Earnings Monitoring';
?>
<div class="admin-earning">
    <h1><?= Html::encode($this->title) ?></h1>

    <div class="card mt-4">
        <div class="card-header">
            <h5>Filter Earnings</h5>
        </div>
        <div class="card-body">
            <form id="earning-filter-form" class="row g-3">
                <div class="col-md-5">
                    <label for="coinid" class="form-label">Coin</label>
                    <select class="form-select" id="coinid" name="coinid">
                        <option value="">All Coins</option>
                    </select>
                </div>
                <div class="col-md-5">
                    <label for="algo" class="form-label">Algorithm</label>
                    <select class="form-select" id="algo" name="algo">
                        <option value="">All Algorithms</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-body">
            <div id="earning-results">Loading...</div>
        </div>
    </div>
</div>

<?php
$earningResultsUrl = Url::to(['earning-results']);
$this->registerJs(<<<JS
function loadEarnings() {
    var formData = $('#earning-filter-form').serialize();
    $.ajax({
        url: '$earningResultsUrl',
        type: 'GET',
        data: formData,
        success: function(data) {
            $('#earning-results').html(data);
        }
    });
}

$('#earning-filter-form').on('submit', function(e) {
    e.preventDefault();
    loadEarnings();
});

loadEarnings();
JS
);
?>
