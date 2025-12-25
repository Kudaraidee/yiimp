/**
 * Admin-specific JavaScript functionality
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        // Confirm dangerous actions
        $('.btn-danger[data-confirm]').on('click', function(e) {
            var message = $(this).data('confirm') || 'Are you sure?';
            if (!confirm(message)) {
                e.preventDefault();
                return false;
            }
        });
        
        // Auto-refresh admin stats
        if ($('.admin-dashboard').length > 0) {
            setInterval(function() {
                $('.admin-stat-card[data-refresh-url]').each(function() {
                    var $card = $(this);
                    var url = $card.data('refresh-url');
                    
                    $.ajax({
                        url: url,
                        type: 'GET',
                        dataType: 'json',
                        success: function(data) {
                            $card.find('.stat-value').text(data.value);
                        }
                    });
                });
            }, 30000); // Refresh every 30 seconds
        }
    });
    
})(jQuery);
