<?php

use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'Version Monitoring';
?>
<div class="admin-version">
    <h1><?= Html::encode($this->title) ?></h1>

    <div class="card mt-4">
        <div class="card-body">
            <div id="version-results">Loading...</div>
        </div>
    </div>
</div>

<?php
$versionResultsUrl = Url::to(['version-results']);
$this->registerJs(<<<JS
function loadVersions() {
    $.ajax({
        url: '$versionResultsUrl',
        type: 'GET',
        success: function(data) {
            $('#version-results').html(data);
        }
    });
}

loadVersions();
JS
);
?>
