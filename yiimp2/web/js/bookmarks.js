/**
 * Bookmark Management for Yiimp2
 * Handles wallet address bookmarking using browser localStorage
 */

var BookmarkManager = (function() {
    'use strict';
    
    var STORAGE_KEY = 'yiimp_bookmarks';
    var CURRENT_KEY = 'yiimp_current_bookmark';
    
    /**
     * Get all bookmarks from localStorage
     * @returns {Array} Array of bookmark objects
     */
    function getBookmarks() {
        try {
            var data = localStorage.getItem(STORAGE_KEY);
            return data ? JSON.parse(data) : [];
        } catch (e) {
            console.error('Error reading bookmarks:', e);
            return [];
        }
    }
    
    /**
     * Save bookmarks to localStorage
     * @param {Array} bookmarks - Array of bookmark objects
     */
    function saveBookmarks(bookmarks) {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(bookmarks));
            return true;
        } catch (e) {
            console.error('Error saving bookmarks:', e);
            return false;
        }
    }
    
    /**
     * Add a new bookmark
     * @param {string} address - Wallet address
     * @param {string} label - Optional label for the bookmark
     * @returns {boolean} Success status
     */
    function addBookmark(address, label) {
        if (!address || address.trim() === '') {
            console.error('Invalid address');
            return false;
        }
        
        address = address.trim();
        label = label ? label.trim() : address.substring(0, 10) + '...';
        
        var bookmarks = getBookmarks();
        
        // Check if bookmark already exists
        var exists = bookmarks.some(function(b) {
            return b.address === address;
        });
        
        if (exists) {
            console.log('Bookmark already exists');
            return false;
        }
        
        // Add new bookmark
        bookmarks.push({
            address: address,
            label: label,
            timestamp: Date.now()
        });
        
        return saveBookmarks(bookmarks);
    }
    
    /**
     * Remove a bookmark
     * @param {string} address - Wallet address to remove
     * @returns {boolean} Success status
     */
    function removeBookmark(address) {
        var bookmarks = getBookmarks();
        var filtered = bookmarks.filter(function(b) {
            return b.address !== address;
        });
        
        if (filtered.length === bookmarks.length) {
            console.log('Bookmark not found');
            return false;
        }
        
        // If removing current bookmark, clear it
        if (getCurrentBookmark() === address) {
            clearCurrentBookmark();
        }
        
        return saveBookmarks(filtered);
    }
    
    /**
     * Check if an address is bookmarked
     * @param {string} address - Wallet address
     * @returns {boolean} True if bookmarked
     */
    function isBookmarked(address) {
        var bookmarks = getBookmarks();
        return bookmarks.some(function(b) {
            return b.address === address;
        });
    }
    
    /**
     * Get current active bookmark
     * @returns {string|null} Current bookmark address
     */
    function getCurrentBookmark() {
        try {
            return localStorage.getItem(CURRENT_KEY);
        } catch (e) {
            console.error('Error reading current bookmark:', e);
            return null;
        }
    }
    
    /**
     * Set current active bookmark
     * @param {string} address - Wallet address
     * @returns {boolean} Success status
     */
    function setCurrentBookmark(address) {
        try {
            localStorage.setItem(CURRENT_KEY, address);
            
            // Update timestamp for this bookmark
            var bookmarks = getBookmarks();
            var updated = bookmarks.map(function(b) {
                if (b.address === address) {
                    b.timestamp = Date.now();
                }
                return b;
            });
            saveBookmarks(updated);
            
            return true;
        } catch (e) {
            console.error('Error setting current bookmark:', e);
            return false;
        }
    }
    
    /**
     * Clear current bookmark
     */
    function clearCurrentBookmark() {
        try {
            localStorage.removeItem(CURRENT_KEY);
        } catch (e) {
            console.error('Error clearing current bookmark:', e);
        }
    }
    
    /**
     * Switch to a different bookmark
     * @param {string} address - Wallet address to switch to
     * @returns {boolean} Success status
     */
    function switchBookmark(address) {
        if (!isBookmarked(address)) {
            console.error('Bookmark not found');
            return false;
        }
        
        setCurrentBookmark(address);
        
        // Redirect to wallet page with the address
        var currentUrl = window.location.href.split('?')[0];
        window.location.href = currentUrl + '?address=' + encodeURIComponent(address);
        
        return true;
    }
    
    /**
     * Get bookmark by address
     * @param {string} address - Wallet address
     * @returns {Object|null} Bookmark object or null
     */
    function getBookmark(address) {
        var bookmarks = getBookmarks();
        return bookmarks.find(function(b) {
            return b.address === address;
        }) || null;
    }
    
    /**
     * Update bookmark label
     * @param {string} address - Wallet address
     * @param {string} newLabel - New label
     * @returns {boolean} Success status
     */
    function updateLabel(address, newLabel) {
        var bookmarks = getBookmarks();
        var updated = bookmarks.map(function(b) {
            if (b.address === address) {
                b.label = newLabel.trim();
            }
            return b;
        });
        
        return saveBookmarks(updated);
    }
    
    /**
     * Clear all bookmarks
     * @returns {boolean} Success status
     */
    function clearAll() {
        try {
            localStorage.removeItem(STORAGE_KEY);
            localStorage.removeItem(CURRENT_KEY);
            return true;
        } catch (e) {
            console.error('Error clearing bookmarks:', e);
            return false;
        }
    }
    
    // Public API
    return {
        getAll: getBookmarks,
        add: addBookmark,
        remove: removeBookmark,
        isBookmarked: isBookmarked,
        getCurrent: getCurrentBookmark,
        setCurrent: setCurrentBookmark,
        clearCurrent: clearCurrentBookmark,
        switch: switchBookmark,
        get: getBookmark,
        updateLabel: updateLabel,
        clearAll: clearAll
    };
})();

/**
 * UI Helper functions for bookmark management
 */
var BookmarkUI = (function() {
    'use strict';
    
    /**
     * Initialize bookmark UI elements
     */
    function init() {
        // Add bookmark button click handler
        $(document).on('click', '.bookmark-add-btn', function(e) {
            e.preventDefault();
            var address = $(this).data('address') || $('#wallet-address-input').val();
            var label = $(this).data('label') || '';
            
            if (BookmarkManager.add(address, label)) {
                showMessage('Bookmark added successfully', 'success');
                updateBookmarkButton(address);
                refreshBookmarkList();
            } else {
                showMessage('Failed to add bookmark', 'error');
            }
        });
        
        // Remove bookmark button click handler
        $(document).on('click', '.bookmark-remove-btn', function(e) {
            e.preventDefault();
            var address = $(this).data('address');
            
            if (confirm('Remove this bookmark?')) {
                if (BookmarkManager.remove(address)) {
                    showMessage('Bookmark removed', 'success');
                    updateBookmarkButton(address);
                    refreshBookmarkList();
                } else {
                    showMessage('Failed to remove bookmark', 'error');
                }
            }
        });
        
        // Switch bookmark click handler
        $(document).on('click', '.bookmark-switch-btn', function(e) {
            e.preventDefault();
            var address = $(this).data('address');
            BookmarkManager.switch(address);
        });
        
        // Update UI on page load
        updateCurrentPageBookmark();
    }
    
    /**
     * Update bookmark button state for current address
     * @param {string} address - Wallet address
     */
    function updateBookmarkButton(address) {
        var isBookmarked = BookmarkManager.isBookmarked(address);
        var $btn = $('.bookmark-toggle-btn[data-address="' + address + '"]');
        
        if (isBookmarked) {
            $btn.removeClass('bookmark-add-btn').addClass('bookmark-remove-btn');
            $btn.html('<i class="fa fa-star"></i> Remove Bookmark');
        } else {
            $btn.removeClass('bookmark-remove-btn').addClass('bookmark-add-btn');
            $btn.html('<i class="fa fa-star-o"></i> Add Bookmark');
        }
    }
    
    /**
     * Refresh bookmark list display
     */
    function refreshBookmarkList() {
        var bookmarks = BookmarkManager.getAll();
        var $list = $('#bookmark-list');
        
        if (!$list.length) return;
        
        if (bookmarks.length === 0) {
            $list.html('<li class="list-group-item text-muted">No bookmarks yet</li>');
            return;
        }
        
        var html = '';
        bookmarks.forEach(function(bookmark) {
            var isCurrent = BookmarkManager.getCurrent() === bookmark.address;
            var activeClass = isCurrent ? 'active' : '';
            
            html += '<li class="list-group-item ' + activeClass + '">';
            html += '<div class="d-flex justify-content-between align-items-center">';
            html += '<div>';
            html += '<strong>' + escapeHtml(bookmark.label) + '</strong><br>';
            html += '<small class="text-muted">' + escapeHtml(bookmark.address) + '</small>';
            html += '</div>';
            html += '<div class="btn-group">';
            html += '<button class="btn btn-sm btn-primary bookmark-switch-btn" data-address="' + escapeHtml(bookmark.address) + '">View</button>';
            html += '<button class="btn btn-sm btn-danger bookmark-remove-btn" data-address="' + escapeHtml(bookmark.address) + '">Remove</button>';
            html += '</div>';
            html += '</div>';
            html += '</li>';
        });
        
        $list.html(html);
    }
    
    /**
     * Update bookmark state for current page
     */
    function updateCurrentPageBookmark() {
        // Get address from URL parameter
        var urlParams = new URLSearchParams(window.location.search);
        var address = urlParams.get('address');
        
        if (address) {
            updateBookmarkButton(address);
            BookmarkManager.setCurrent(address);
        }
    }
    
    /**
     * Show message to user
     * @param {string} message - Message text
     * @param {string} type - Message type (success, error, info)
     */
    function showMessage(message, type) {
        // Simple alert for now - can be enhanced with toast notifications
        var alertClass = type === 'success' ? 'alert-success' : 
                        type === 'error' ? 'alert-danger' : 'alert-info';
        
        var $alert = $('<div class="alert ' + alertClass + ' alert-dismissible fade show" role="alert">' +
                      message +
                      '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>' +
                      '</div>');
        
        $('#bookmark-messages').html($alert);
        
        // Auto-dismiss after 3 seconds
        setTimeout(function() {
            $alert.alert('close');
        }, 3000);
    }
    
    /**
     * Escape HTML to prevent XSS
     * @param {string} text - Text to escape
     * @returns {string} Escaped text
     */
    function escapeHtml(text) {
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }
    
    // Public API
    return {
        init: init,
        updateBookmarkButton: updateBookmarkButton,
        refreshBookmarkList: refreshBookmarkList,
        showMessage: showMessage
    };
})();

// Initialize on document ready
$(document).ready(function() {
    BookmarkUI.init();
});
