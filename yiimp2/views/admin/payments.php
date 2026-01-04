<?php

use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'Payment Monitoring';
?>
<div class="admin-payments">
    <h1><?= Html::encode($this->title) ?></h1>

    <div class="card mt-4">
        <div class="card-header">
            <h5>Filter Payments</h5>
        </div>
        <div class="card-body">
            <form id="payment-filter-form" class="row g-3">
                <div class="col-md-4">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="pending">Pending</option>
                        <option value="completed">Completed</option>
                        <option value="">All</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="coinid" class="form-label">Coin</label>
                    <select class="form-select" id="coinid" name="coinid">
                        <option value="">All Coins</option>
                        <!-- Coins will be populated dynamically -->
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-body">
            <div id="payment-results">
                Loading...
            </div>
        </div>
    </div>
</div>

<?php
$paymentResultsUrl = Url::to(['payments-results']);
$this->registerJs(<<<JS
function loadPayments() {
    var formData = $('#payment-filter-form').serialize();
    $.ajax({
        url: '$paymentResultsUrl',
        type: 'GET',
        data: formData,
        success: function(data) {
            $('#payment-results').html(data);
        },
        error: function() {
            $('#payment-results').html('<div class="alert alert-danger">Failed to load payments</div>');
        }
    });
}

$('#payment-filter-form').on('submit', function(e) {
    e.preventDefault();
    loadPayments();
});

// Load payments on page load
loadPayments();
JS
);
?>
