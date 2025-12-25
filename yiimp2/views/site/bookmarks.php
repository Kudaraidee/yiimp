<?php

/** @var yii\web\View $this */

use yii\helpers\Html;
use app\components\CspHelper;

$this->title = 'My Bookmarks - ' . YAAMP_SITE_NAME;
$this->registerJsFile('@web/js/bookmarks.js', ['depends' => [yii\web\JqueryAsset::className()]]);

$homeUrl = Yii::$app->homeUrl;
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <div class="card">
                <div class="card-header">
                    <h3><i class="fa fa-star"></i> My Bookmarked Wallets</h3>
                </div>
                <div class="card-body">
                    <div id="bookmark-messages"></div>
                    
                    <div class="mb-3">
                        <p class="text-muted">
                            Bookmark your wallet addresses for quick access. Your bookmarks are stored locally in your browser.
                        </p>
                    </div>
                    
                    <div class="mb-4">
                        <h5>Add New Bookmark</h5>
                        <div class="input-group">
                            <input type="text" 
                                   id="new-bookmark-address" 
                                   class="form-control" 
                                   placeholder="Enter wallet address">
                            <input type="text" 
                                   id="new-bookmark-label" 
                                   class="form-control" 
                                   placeholder="Label (optional)">
                            <button class="btn btn-primary" id="add-bookmark-btn">
                                <i class="fa fa-plus"></i> Add Bookmark
                            </button>
                        </div>
                    </div>
                    
                    <h5>Your Bookmarks</h5>
                    <ul id="bookmark-list" class="list-group">
                        <li class="list-group-item text-muted">Loading bookmarks...</li>
                    </ul>
                    
                    <div class="mt-3">
                        <button class="btn btn-danger btn-sm" id="clear-all-bookmarks">
                            <i class="fa fa-trash"></i> Clear All Bookmarks
                        </button>
                        <a href="<?= $homeUrl ?>" class="btn btn-secondary btn-sm">
                            <i class="fa fa-home"></i> Back to Home
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?= CspHelper::beginScript() ?>
$(document).ready(function() {
    // Refresh bookmark list on page load
    BookmarkUI.refreshBookmarkList();
    
    // Add bookmark button handler
    $('#add-bookmark-btn').on('click', function() {
        var address = $('#new-bookmark-address').val().trim();
        var label = $('#new-bookmark-label').val().trim();
        
        if (!address) {
            BookmarkUI.showMessage('Please enter a wallet address', 'error');
            return;
        }
        
        if (BookmarkManager.add(address, label)) {
            BookmarkUI.showMessage('Bookmark added successfully', 'success');
            $('#new-bookmark-address').val('');
            $('#new-bookmark-label').val('');
            BookmarkUI.refreshBookmarkList();
        } else {
            BookmarkUI.showMessage('Failed to add bookmark (may already exist)', 'error');
        }
    });
    
    // Clear all bookmarks handler
    $('#clear-all-bookmarks').on('click', function() {
        if (confirm('Are you sure you want to clear all bookmarks? This cannot be undone.')) {
            if (BookmarkManager.clearAll()) {
                BookmarkUI.showMessage('All bookmarks cleared', 'success');
                BookmarkUI.refreshBookmarkList();
            } else {
                BookmarkUI.showMessage('Failed to clear bookmarks', 'error');
            }
        }
    });
    
    // Allow Enter key to add bookmark
    $('#new-bookmark-address, #new-bookmark-label').on('keypress', function(e) {
        if (e.which === 13) {
            $('#add-bookmark-btn').click();
        }
    });
});
<?= CspHelper::endScript() ?>
