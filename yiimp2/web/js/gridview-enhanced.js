/**
 * Enhanced GridView functionality
 * Provides client-side enhancements for table sorting, filtering, and pagination
 */

(function($) {
    'use strict';
    
    /**
     * Initialize enhanced GridView features
     */
    $.fn.enhancedGridView = function(options) {
        var settings = $.extend({
            filterDelay: 500,
            enableClientSort: false,
            enableClientFilter: false,
            highlightOnHover: true,
            stickyHeader: false,
            exportButtons: false
        }, options);
        
        return this.each(function() {
            var $gridView = $(this);
            var filterTimeout;
            
            // Highlight rows on hover
            if (settings.highlightOnHover) {
                $gridView.find('tbody tr').hover(
                    function() {
                        $(this).addClass('table-active');
                    },
                    function() {
                        $(this).removeClass('table-active');
                    }
                );
            }
            
            // Delayed filter input
            if (settings.filterDelay > 0) {
                $gridView.find('.filters input').on('keyup', function() {
                    clearTimeout(filterTimeout);
                    var $form = $(this).closest('form');
                    filterTimeout = setTimeout(function() {
                        $form.submit();
                    }, settings.filterDelay);
                });
            }
            
            // Sticky header
            if (settings.stickyHeader) {
                var $table = $gridView.find('table');
                var $header = $table.find('thead');
                var headerOffset = $header.offset().top;
                
                $(window).scroll(function() {
                    var scrollTop = $(window).scrollTop();
                    if (scrollTop > headerOffset) {
                        $header.addClass('sticky-header');
                    } else {
                        $header.removeClass('sticky-header');
                    }
                });
            }
            
            // Client-side sorting
            if (settings.enableClientSort) {
                $gridView.find('th a').on('click', function(e) {
                    e.preventDefault();
                    var $link = $(this);
                    var $th = $link.closest('th');
                    var columnIndex = $th.index();
                    var isAsc = $link.hasClass('asc');
                    
                    // Remove all sort classes
                    $gridView.find('th a').removeClass('asc desc');
                    
                    // Add appropriate sort class
                    if (isAsc) {
                        $link.addClass('desc');
                        sortTable($gridView, columnIndex, false);
                    } else {
                        $link.addClass('asc');
                        sortTable($gridView, columnIndex, true);
                    }
                });
            }
            
            // Client-side filtering
            if (settings.enableClientFilter) {
                $gridView.find('.filters input').on('keyup', function() {
                    var $input = $(this);
                    var columnIndex = $input.closest('td').index();
                    var filterValue = $input.val().toLowerCase();
                    
                    $gridView.find('tbody tr').each(function() {
                        var $row = $(this);
                        var cellValue = $row.find('td').eq(columnIndex).text().toLowerCase();
                        
                        if (cellValue.indexOf(filterValue) > -1) {
                            $row.show();
                        } else {
                            $row.hide();
                        }
                    });
                });
            }
            
            // Export buttons
            if (settings.exportButtons) {
                addExportButtons($gridView);
            }
        });
    };
    
    /**
     * Sort table by column
     */
    function sortTable($gridView, columnIndex, ascending) {
        var $tbody = $gridView.find('tbody');
        var $rows = $tbody.find('tr').toArray();
        
        $rows.sort(function(a, b) {
            var aValue = $(a).find('td').eq(columnIndex).text();
            var bValue = $(b).find('td').eq(columnIndex).text();
            
            // Try to parse as numbers
            var aNum = parseFloat(aValue);
            var bNum = parseFloat(bValue);
            
            if (!isNaN(aNum) && !isNaN(bNum)) {
                return ascending ? aNum - bNum : bNum - aNum;
            }
            
            // String comparison
            if (ascending) {
                return aValue.localeCompare(bValue);
            } else {
                return bValue.localeCompare(aValue);
            }
        });
        
        $tbody.empty().append($rows);
    }
    
    /**
     * Add export buttons to GridView
     */
    function addExportButtons($gridView) {
        var $toolbar = $('<div class="gridview-toolbar mb-2"></div>');
        
        var $exportBtn = $('<button type="button" class="btn btn-sm btn-outline-secondary">Export</button>');
        var $exportMenu = $('<div class="dropdown-menu"></div>');
        
        $exportMenu.append('<a class="dropdown-item" href="#" data-format="csv">Export as CSV</a>');
        $exportMenu.append('<a class="dropdown-item" href="#" data-format="excel">Export as Excel</a>');
        $exportMenu.append('<a class="dropdown-item" href="#" data-format="pdf">Export as PDF</a>');
        
        var $dropdown = $('<div class="dropdown d-inline-block"></div>');
        $dropdown.append($exportBtn);
        $dropdown.append($exportMenu);
        
        $toolbar.append($dropdown);
        $gridView.prepend($toolbar);
        
        // Export handlers
        $exportMenu.find('a').on('click', function(e) {
            e.preventDefault();
            var format = $(this).data('format');
            exportTable($gridView, format);
        });
    }
    
    /**
     * Export table data
     */
    function exportTable($gridView, format) {
        var data = [];
        var $table = $gridView.find('table');
        
        // Get headers
        var headers = [];
        $table.find('thead th').each(function() {
            headers.push($(this).text().trim());
        });
        data.push(headers);
        
        // Get rows
        $table.find('tbody tr:visible').each(function() {
            var row = [];
            $(this).find('td').each(function() {
                row.push($(this).text().trim());
            });
            data.push(row);
        });
        
        if (format === 'csv') {
            exportAsCSV(data);
        } else if (format === 'excel') {
            alert('Excel export requires server-side processing');
        } else if (format === 'pdf') {
            alert('PDF export requires server-side processing');
        }
    }
    
    /**
     * Export data as CSV
     */
    function exportAsCSV(data) {
        var csv = '';
        data.forEach(function(row) {
            csv += row.map(function(cell) {
                return '"' + cell.replace(/"/g, '""') + '"';
            }).join(',') + '\n';
        });
        
        var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        var link = document.createElement('a');
        var url = URL.createObjectURL(blob);
        
        link.setAttribute('href', url);
        link.setAttribute('download', 'export_' + Date.now() + '.csv');
        link.classList.add('d-none'); // CSP-compliant: use CSS class instead of inline style
        
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
    
    /**
     * Refresh GridView via AJAX
     */
    $.fn.refreshGridView = function(url, data) {
        var $gridView = $(this);
        
        $gridView.addClass('loading');
        
        $.ajax({
            url: url,
            type: 'GET',
            data: data,
            success: function(response) {
                $gridView.html(response);
                $gridView.removeClass('loading');
                $gridView.trigger('gridview:refresh');
            },
            error: function() {
                $gridView.removeClass('loading');
                alert('Failed to refresh data');
            }
        });
    };
    
    /**
     * Auto-initialize GridViews on page load
     */
    $(document).ready(function() {
        $('.grid-view').each(function() {
            var $gridView = $(this);
            
            // Check for data attributes
            var options = {
                filterDelay: $gridView.data('filter-delay') || 500,
                enableClientSort: $gridView.data('client-sort') || false,
                enableClientFilter: $gridView.data('client-filter') || false,
                highlightOnHover: $gridView.data('highlight-hover') !== false,
                stickyHeader: $gridView.data('sticky-header') || false,
                exportButtons: $gridView.data('export-buttons') || false
            };
            
            $gridView.enhancedGridView(options);
        });
    });
    
})(jQuery);
