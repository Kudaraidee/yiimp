<?php

use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'Worker Monitoring';
?>
<div class="admin-worker">
    <h1><?= Html::encode($this->title) ?></h1>

    <div class="card mt-4">
        <div class="card-header">
            <h5>Filter Workers</h5>
        </div>
        <div class="card-body">
            <form id="worker-filter-form" class="row g-3">
                <div class="col-md-4">
                    <label for="algo" class="form-label">Algorithm</label>
                    <select class="form-select" id="algo" name="algo">
                        <option value="">All Algorithms</option>
                        <!-- Algorithms will be populated dynamically -->
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="active" class="form-label">Status</label>
                    <select class="form-select" id="active" name="active">
                        <option value="1">Active Only</option>
                        <option value="0">All Workers</option>
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
            <div id="worker-results">
                Loading...
            </div>
        </div>
    </div>
</div>

<?php
$workerResultsUrl = Url::to(['worker-results']);
$this->registerJs(<<<JS
function loadWorkers() {
    var formData = $('#worker-filter-form').serialize();
    $.ajax({
        url: '$workerResultsUrl',
        type: 'GET',
        data: formData,
        success: function(data) {
            $('#worker-results').html(data);
        },
        error: function() {
            $('#worker-results').html('<div class="alert alert-danger">Failed to load workers</div>');
        }
    });
}

$('#worker-filter-form').on('submit', function(e) {
    e.preventDefault();
    loadWorkers();
});

// Load workers on page load and refresh every 30 seconds
loadWorkers();
setInterval(loadWorkers, 30000);
JS
);
?>
