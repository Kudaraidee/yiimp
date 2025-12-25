/**
 * Chart Helper Functions
 * Provides utilities for working with Chart.js charts
 */

(function($) {
    'use strict';
    
    /**
     * Chart manager object
     */
    window.ChartManager = {
        charts: {},
        
        /**
         * Register a chart instance
         */
        register: function(id, chart) {
            this.charts[id] = chart;
        },
        
        /**
         * Get a chart instance by ID
         */
        get: function(id) {
            return this.charts[id];
        },
        
        /**
         * Update chart data
         */
        updateData: function(id, newData) {
            var chart = this.get(id);
            if (chart) {
                chart.data = newData;
                chart.update();
            }
        },
        
        /**
         * Destroy a chart
         */
        destroy: function(id) {
            var chart = this.get(id);
            if (chart) {
                chart.destroy();
                delete this.charts[id];
            }
        },
        
        /**
         * Destroy all charts
         */
        destroyAll: function() {
            for (var id in this.charts) {
                this.destroy(id);
            }
        },
        
        /**
         * Export chart as image
         */
        exportImage: function(id, filename) {
            var chart = this.get(id);
            if (chart) {
                var url = chart.toBase64Image();
                var link = document.createElement('a');
                link.download = filename || 'chart.png';
                link.href = url;
                link.click();
            }
        },
        
        /**
         * Toggle fullscreen mode for chart
         */
        toggleFullscreen: function(id) {
            var $container = $('#chart-' + id).closest('.chart-container');
            
            if ($container.hasClass('chart-fullscreen')) {
                $container.removeClass('chart-fullscreen');
                $container.find('.chart-fullscreen-close').remove();
            } else {
                $container.addClass('chart-fullscreen');
                var $closeBtn = $('<button class="btn btn-secondary chart-fullscreen-close">Exit Fullscreen</button>');
                $closeBtn.on('click', function() {
                    ChartManager.toggleFullscreen(id);
                });
                $container.append($closeBtn);
            }
            
            // Resize chart
            var chart = this.get(id);
            if (chart) {
                chart.resize();
            }
        }
    };
    
    /**
     * Auto-refresh chart data
     */
    $.fn.autoRefreshChart = function(options) {
        var settings = $.extend({
            url: null,
            interval: 30000, // 30 seconds
            chartId: null
        }, options);
        
        if (!settings.url || !settings.chartId) {
            console.error('URL and chartId are required for auto-refresh');
            return this;
        }
        
        var refreshChart = function() {
            $.ajax({
                url: settings.url,
                type: 'GET',
                dataType: 'json',
                success: function(data) {
                    ChartManager.updateData(settings.chartId, data);
                },
                error: function() {
                    console.error('Failed to refresh chart data');
                }
            });
        };
        
        // Initial load
        refreshChart();
        
        // Set up interval
        var intervalId = setInterval(refreshChart, settings.interval);
        
        // Store interval ID for cleanup
        $(this).data('refresh-interval', intervalId);
        
        return this;
    };
    
    /**
     * Stop auto-refresh
     */
    $.fn.stopAutoRefresh = function() {
        var intervalId = $(this).data('refresh-interval');
        if (intervalId) {
            clearInterval(intervalId);
            $(this).removeData('refresh-interval');
        }
        return this;
    };
    
    /**
     * Chart time range selector
     */
    $.fn.chartTimeRange = function(options) {
        var settings = $.extend({
            chartId: null,
            dataUrl: null,
            ranges: {
                '1h': '1 Hour',
                '6h': '6 Hours',
                '24h': '24 Hours',
                '7d': '7 Days',
                '30d': '30 Days'
            }
        }, options);
        
        var $container = $(this);
        var $buttons = $('<div class="chart-time-range"></div>');
        
        $.each(settings.ranges, function(value, label) {
            var $btn = $('<button class="btn btn-sm btn-outline-primary">' + label + '</button>');
            $btn.data('range', value);
            $btn.on('click', function() {
                $buttons.find('.btn').removeClass('active');
                $(this).addClass('active');
                loadChartData(value);
            });
            $buttons.append($btn);
        });
        
        $container.append($buttons);
        
        // Activate first button
        $buttons.find('.btn').first().addClass('active');
        
        function loadChartData(range) {
            if (!settings.dataUrl || !settings.chartId) {
                return;
            }
            
            $.ajax({
                url: settings.dataUrl,
                type: 'GET',
                data: { range: range },
                dataType: 'json',
                success: function(data) {
                    ChartManager.updateData(settings.chartId, data);
                },
                error: function() {
                    console.error('Failed to load chart data');
                }
            });
        }
        
        return this;
    };
    
    /**
     * Chart comparison tool
     */
    $.fn.chartComparison = function(options) {
        var settings = $.extend({
            charts: [],
            syncZoom: true,
            syncTooltip: true
        }, options);
        
        if (settings.syncZoom) {
            // Sync zoom across charts
            settings.charts.forEach(function(chartId) {
                var chart = ChartManager.get(chartId);
                if (chart && chart.options.plugins) {
                    chart.options.plugins.zoom = {
                        zoom: {
                            onZoom: function() {
                                syncZoom(chartId, settings.charts);
                            }
                        }
                    };
                }
            });
        }
        
        return this;
    };
    
    /**
     * Sync zoom across multiple charts
     */
    function syncZoom(sourceChartId, chartIds) {
        var sourceChart = ChartManager.get(sourceChartId);
        if (!sourceChart) return;
        
        var xScale = sourceChart.scales.x;
        var yScale = sourceChart.scales.y;
        
        chartIds.forEach(function(chartId) {
            if (chartId === sourceChartId) return;
            
            var chart = ChartManager.get(chartId);
            if (chart) {
                chart.scales.x.options.min = xScale.min;
                chart.scales.x.options.max = xScale.max;
                chart.scales.y.options.min = yScale.min;
                chart.scales.y.options.max = yScale.max;
                chart.update('none');
            }
        });
    }
    
    /**
     * Format number with appropriate unit
     */
    window.formatHashrate = function(value) {
        var units = ['H/s', 'KH/s', 'MH/s', 'GH/s', 'TH/s', 'PH/s'];
        var unitIndex = 0;
        
        while (value >= 1000 && unitIndex < units.length - 1) {
            value /= 1000;
            unitIndex++;
        }
        
        return value.toFixed(2) + ' ' + units[unitIndex];
    };
    
    /**
     * Format currency value
     */
    window.formatCurrency = function(value, decimals) {
        decimals = decimals || 8;
        return value.toFixed(decimals);
    };
    
    /**
     * Get responsive chart options
     */
    window.getResponsiveChartOptions = function() {
        return {
            responsive: true,
            maintainAspectRatio: true,
            aspectRatio: window.innerWidth < 768 ? 1 : 2,
            plugins: {
                legend: {
                    display: true,
                    position: window.innerWidth < 768 ? 'bottom' : 'top',
                },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                }
            }
        };
    };
    
    /**
     * Initialize charts on page load
     */
    $(document).ready(function() {
        // Clean up charts on page unload
        $(window).on('beforeunload', function() {
            ChartManager.destroyAll();
        });
        
        // Handle window resize
        var resizeTimeout;
        $(window).on('resize', function() {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(function() {
                // Update all charts
                for (var id in ChartManager.charts) {
                    var chart = ChartManager.charts[id];
                    if (chart) {
                        chart.resize();
                    }
                }
            }, 250);
        });
    });
    
})(jQuery);
