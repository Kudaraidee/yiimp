/**
 * Bookmark Auto-Load for Yiimp2
 * Automatically loads bookmarked wallet statistics on page load
 */

(function() {
    'use strict';
    
    /**
     * Check if we're on the homepage (no address parameter)
     * and auto-load the current bookmark if it exists
     */
    function autoLoadBookmark() {
        // Only auto-load on homepage without address parameter
        var urlParams = new URLSearchParams(window.location.search);
        var addressParam = urlParams.get('address');
        
        // If address is already in URL, don't auto-load
        if (addressParam) {
            return;
        }
        
        // Check if we're on the homepage/index
        var path = window.location.pathname;
        var isHomepage = path === '/' || path === '/index.php' || 
                        path.endsWith('/site/index') || path.endsWith('/');
        
        if (!isHomepage) {
            return;
        }
        
        // Get current bookmark
        var currentBookmark = BookmarkManager.getCurrent();
        
        if (!currentBookmark) {
            // No current bookmark, check if there's any bookmark
            var bookmarks = BookmarkManager.getAll();
            
            if (bookmarks.length > 0) {
                // Use the most recently used bookmark
                bookmarks.sort(function(a, b) {
                    return (b.timestamp || 0) - (a.timestamp || 0);
                });
                currentBookmark = bookmarks[0].address;
            }
        }
        
        // If we have a bookmark, redirect to wallet page
        if (currentBookmark) {
            console.log('Auto-loading bookmark:', currentBookmark);
            
            // Add a flag to prevent infinite redirects
            var autoLoaded = sessionStorage.getItem('bookmark_autoloaded');
            
            if (!autoLoaded) {
                sessionStorage.setItem('bookmark_autoloaded', 'true');
                
                // Redirect to wallet page with bookmark address
                var baseUrl = window.location.href.split('?')[0];
                window.location.href = baseUrl + '?address=' + encodeURIComponent(currentBookmark);
            }
        }
    }
    
    /**
     * Clear auto-load flag when user manually navigates
     */
    function clearAutoLoadFlag() {
        // Clear the flag when user clicks on navigation links
        $(document).on('click', 'a', function() {
            sessionStorage.removeItem('bookmark_autoloaded');
        });
        
        // Clear the flag when user submits a form
        $(document).on('submit', 'form', function() {
            sessionStorage.removeItem('bookmark_autoloaded');
        });
    }
    
    /**
     * Display bookmarked wallet info on homepage
     */
    function displayBookmarkInfo() {
        var currentBookmark = BookmarkManager.getCurrent();
        
        if (!currentBookmark) {
            return;
        }
        
        var bookmark = BookmarkManager.get(currentBookmark);
        
        if (!bookmark) {
            return;
        }
        
        // Create bookmark info display
        var $infoBox = $('#bookmark-info-box');
        
        if (!$infoBox.length) {
            // Create info box if it doesn't exist
            $infoBox = $('<div id="bookmark-info-box" class="alert alert-info m-3"></div>');
            $('body').prepend($infoBox);
        }
        
        var html = '<strong>Current Bookmark:</strong> ' + escapeHtml(bookmark.label) + 
                   ' <small>(' + escapeHtml(bookmark.address) + ')</small> ';
        html += '<button class="btn btn-sm btn-primary ms-2" onclick="BookmarkManager.switch(\'' + 
                escapeHtml(bookmark.address) + '\')">View Stats</button> ';
        html += '<button class="btn btn-sm btn-secondary ms-1" onclick="BookmarkManager.clearCurrent(); location.reload();">Clear</button>';
        
        $infoBox.html(html);
    }
    
    /**
     * Escape HTML to prevent XSS
     */
    function escapeHtml(text) {
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
    }
    
    /**
     * Initialize auto-load functionality
     */
    function init() {
        // Wait for BookmarkManager to be available
        if (typeof BookmarkManager === 'undefined') {
            console.error('BookmarkManager not loaded');
            return;
        }
        
        // Auto-load bookmark on page load
        autoLoadBookmark();
        
        // Set up navigation handlers
        clearAutoLoadFlag();
        
        // Display bookmark info if on homepage
        displayBookmarkInfo();
    }
    
    // Initialize when document is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
