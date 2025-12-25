<?php

use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'Connection Monitoring';
?>
<div class="admin-connections">
    <h1><?= Html::encode($this->title) ?></h1>

    <div class="card mt-4">
        <div class="card-header">
            <h5>Filter Connections</h5>
        </div>
        <div class="card-body">
            <form id="connection-filter-form" class="row g-3">
                <div class="col-md-10">
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
            <div id="connections-results">Loading...</div>
        </div>
    </div>
</div>

<?php
$connectionsResultsUrl = Url::to(['connections-results']);
$this->registerJs(<<<JS
function loadConnections() {
    var formData = $('#connection-filter-form').serialize();
    $.ajax({
        url: '$connectionsResultsUrl',
        type: 'GET',
        data: formData,
        success: function(data) {
            $('#connections-results').html(data);
        }
    });
}

$('#connection-filter-form').on('submit', function(e) {
    e.preventDefault();
    loadConnections();
});

loadConnections();
setInterval(loadConnections, 30000);
JS
);
?>
