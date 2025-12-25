<?php

use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'User Management';
?>
<div class="admin-user">
    <h1><?= Html::encode($this->title) ?></h1>

    <div class="card mt-4">
        <div class="card-header">
            <h5>Search and Filter</h5>
        </div>
        <div class="card-body">
            <form id="user-search-form" class="row g-3">
                <div class="col-md-4">
                    <label for="search" class="form-label">Search Username</label>
                    <input type="text" class="form-control" id="search" name="search" placeholder="Enter wallet address">
                </div>
                <div class="col-md-3">
                    <label for="coinid" class="form-label">Coin</label>
                    <select class="form-select" id="coinid" name="coinid">
                        <option value="">All Coins</option>
                        <!-- Coins will be populated dynamically -->
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="locked" class="form-label">Status</label>
                    <select class="form-select" id="locked" name="locked">
                        <option value="">All Users</option>
                        <option value="0">Active</option>
                        <option value="1">Banned</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100">Search</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-body">
            <div id="user-results">
                Loading...
            </div>
        </div>
    </div>
</div>

<?php
$userResultsUrl = Url::to(['user-results']);
$this->registerJs(<<<JS
function loadUsers() {
    var formData = $('#user-search-form').serialize();
    $.ajax({
        url: '$userResultsUrl',
        type: 'GET',
        data: formData,
        success: function(data) {
            $('#user-results').html(data);
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', status, error);
            var errorMsg = '<div class="alert alert-danger">';
            errorMsg += '<strong>Failed to load user data.</strong><br>';
            if (status === 'timeout') {
                errorMsg += 'Request timed out. Please try again.';
            } else if (status === 'error') {
                errorMsg += 'Server error occurred. Status: ' + xhr.status;
            } else {
                errorMsg += 'An unexpected error occurred: ' + status;
            }
            errorMsg += '</div>';
            $('#user-results').html(errorMsg);
        },
        timeout: 30000
    });
}

$('#user-search-form').on('submit', function(e) {
    e.preventDefault();
    loadUsers();
});

// Load users on page load
loadUsers();
JS
);
?>
