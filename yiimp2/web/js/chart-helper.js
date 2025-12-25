/**
 * ChartHelper client-side utilities for Chart.js
 * 
 * This module provides helper functions for creating and managing Chart.js charts
 * in a CSP-compliant way. It replaces jqPlot functionality with Chart.js.
 */
window.ChartHelper = window.ChartHelper || {
    /**
     * Default chart colors matching the original jqPlot theme
     */
    colors: [
        'rgba(78, 180, 180, 0.8)',   // Teal (primary)
        'rgba(54, 162, 235, 0.8)',   // Blue
        'rgba(255, 99, 132, 0.8)',   // Red
        'rgba(255, 206, 86, 0.8)',   // Yellow
        'rgba(75, 192, 192, 0.8)',   // Green
        'rgba(153, 102, 255, 0.8)',  // Purple
        'rgba(255, 159, 64, 0.8)',   // Orange
        'rgba(199, 199, 199, 0.8)'   // Grey
    ],

    /**
     * Format time series data for Chart.js
     * Converts [timestamp, value] pairs to {x: timestamp, y: value} format
     * 
     * @param {Array} data - Array of [timestamp, value] pairs
     * @returns {Array} Formatted data for Chart.js
     */
    formatTimeSeriesData: function(data) {
        if (!Array.isArray(data)) return [];
        return data.map(function(point) {
            if (Array.isArray(point) && point.length >= 2) {
                return { x: point[0], y: point[1] };
            }
            return point;
        });
    },

    /**
     * Get chart instance by container ID
     * 
     * @param {string} containerId - Container element ID
     * @returns {Chart|null} Chart instance or null
     */
    getChart: function(containerId) {
        return window['chart_' + containerId] || null;
    },

    /**
     * Update chart with new data
     * 
     * @param {string} chartId - Chart container ID
     * @param {Array} newData - New data array
     * @param {number} datasetIndex - Dataset index to update (default: 0)
     */
    updateChart: function(chartId, newData, datasetIndex) {
        datasetIndex = datasetIndex || 0;
        var chart = this.getChart(chartId);
        if (chart) {
            chart.data.datasets[datasetIndex].data = this.formatTimeSeriesData(newData);
            chart.update('none');
        }
    },

    /**
     * Update multiple datasets
     * 
     * @param {string} chartId - Chart container ID
     * @param {Array} datasetsData - Array of data arrays for each dataset
     */
    updateChartMultiple: function(chartId, datasetsData) {
        var chart = this.getChart(chartId);
        if (chart) {
            for (var i = 0; i < datasetsData.length; i++) {
                if (chart.data.datasets[i]) {
                    chart.data.datasets[i].data = this.formatTimeSeriesData(datasetsData[i]);
                }
            }
            chart.update('none');
        }
    },

    /**
     * Destroy chart
     * 
     * @param {string} chartId - Chart container ID
     */
    destroyChart: function(chartId) {
        var chart = this.getChart(chartId);
        if (chart) {
            chart.destroy();
            delete window['chart_' + chartId];
        }
    },

    /**
     * Create or get canvas element in container
     * 
     * @param {string} containerId - Container element ID
     * @returns {HTMLCanvasElement|null} Canvas element or null
     */
    getOrCreateCanvas: function(containerId) {
        var container = document.getElementById(containerId);
        if (!container) return null;
        
        // Clear container
        container.innerHTML = '';
        
        var canvas = document.createElement('canvas');
        container.appendChild(canvas);
        return canvas;
    },

    /**
     * Create a line chart
     * 
     * @param {string} containerId - Container element ID
     * @param {Array} data - Chart data as [timestamp, value] pairs
     * @param {Object} options - Chart options
     * @returns {Chart|null} Chart instance or null
     */
    createLineChart: function(containerId, data, options) {
        options = options || {};
        
        var canvas = this.getOrCreateCanvas(containerId);
        if (!canvas) return null;
        
        var ctx = canvas.getContext('2d');
        
        // Destroy existing chart
        this.destroyChart(containerId);
        
        var config = {
            type: 'line',
            data: {
                datasets: [{
                    data: this.formatTimeSeriesData(data),
                    borderColor: options.borderColor || this.colors[0],
                    backgroundColor: options.backgroundColor || this.colors[0],
                    fill: options.fill !== undefined ? options.fill : false,
                    tension: options.tension !== undefined ? options.tension : 0.4,
                    pointRadius: options.pointRadius !== undefined ? options.pointRadius : 0
                }]
            },
            options: this.getLineChartOptions(options)
        };
        
        window['chart_' + containerId] = new Chart(ctx, config);
        
        // Register with ChartManager if available
        if (typeof ChartManager !== 'undefined') {
            ChartManager.register(containerId, window['chart_' + containerId]);
        }
        
        return window['chart_' + containerId];
    },

    /**
     * Create a bar chart
     * 
     * @param {string} containerId - Container element ID
     * @param {Array} data - Chart data as [timestamp, value] pairs
     * @param {Object} options - Chart options
     * @returns {Chart|null} Chart instance or null
     */
    createBarChart: function(containerId, data, options) {
        options = options || {};
        
        var canvas = this.getOrCreateCanvas(containerId);
        if (!canvas) return null;
        
        var ctx = canvas.getContext('2d');
        
        // Destroy existing chart
        this.destroyChart(containerId);
        
        var config = {
            type: 'bar',
            data: {
                datasets: [{
                    data: this.formatTimeSeriesData(data),
                    backgroundColor: options.backgroundColor || 'rgba(54, 162, 235, 0.8)',
                    borderColor: options.borderColor || 'rgba(54, 162, 235, 1)',
                    borderWidth: 1,
                    barPercentage: 0.8,
                    categoryPercentage: 0.9
                }]
            },
            options: this.getBarChartOptions(options)
        };
        
        window['chart_' + containerId] = new Chart(ctx, config);
        
        // Register with ChartManager if available
        if (typeof ChartManager !== 'undefined') {
            ChartManager.register(containerId, window['chart_' + containerId]);
        }
        
        return window['chart_' + containerId];
    },

    /**
     * Create a stacked area chart
     * 
     * @param {string} containerId - Container element ID
     * @param {Array} data - Array of data series
     * @param {Object} options - Chart options
     * @returns {Chart|null} Chart instance or null
     */
    createStackedAreaChart: function(containerId, data, options) {
        options = options || {};
        
        var canvas = this.getOrCreateCanvas(containerId);
        if (!canvas) return null;
        
        var ctx = canvas.getContext('2d');
        
        // Destroy existing chart
        this.destroyChart(containerId);
        
        var datasets = [];
        var labels = options.labels || [];
        var self = this;
        
        for (var i = 0; i < data.length; i++) {
            datasets.push({
                label: labels[i] || ('Series ' + (i + 1)),
                data: this.formatTimeSeriesData(data[i]),
                backgroundColor: this.colors[i % this.colors.length],
                borderColor: this.colors[i % this.colors.length],
                fill: true,
                tension: 0.4,
                pointRadius: 0
            });
        }
        
        var config = {
            type: 'line',
            data: { datasets: datasets },
            options: this.getStackedAreaChartOptions(options, datasets.length)
        };
        
        window['chart_' + containerId] = new Chart(ctx, config);
        
        // Register with ChartManager if available
        if (typeof ChartManager !== 'undefined') {
            ChartManager.register(containerId, window['chart_' + containerId]);
        }
        
        return window['chart_' + containerId];
    },

    /**
     * Get default line chart options
     * 
     * @param {Object} options - User options
     * @returns {Object} Chart.js options object
     */
    getLineChartOptions: function(options) {
        var chartOptions = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: !!options.title,
                    text: options.title || '',
                    font: { weight: 'bold' }
                },
                legend: { display: false },
                tooltip: { 
                    mode: 'index', 
                    intersect: false,
                    callbacks: options.tooltipCallbacks || {}
                }
            },
            scales: {
                x: {
                    type: 'time',
                    time: {
                        displayFormats: {
                            hour: options.xAxisFormat || 'HH:mm',
                            day: options.xAxisDayFormat || 'MMM d'
                        }
                    },
                    grid: { display: true, color: 'rgba(0, 0, 0, 0.1)' },
                    ticks: { font: { size: 10 } }
                },
                y: {
                    min: options.yAxisMin !== undefined ? options.yAxisMin : 0,
                    grid: { display: true, color: 'rgba(0, 0, 0, 0.1)' },
                    ticks: { font: { size: 10 } }
                }
            },
            interaction: { mode: 'nearest', axis: 'x', intersect: false }
        };
        
        if (options.xAxisMin !== undefined) chartOptions.scales.x.min = options.xAxisMin;
        if (options.xAxisMax !== undefined) chartOptions.scales.x.max = options.xAxisMax;
        
        return chartOptions;
    },

    /**
     * Get default bar chart options
     * 
     * @param {Object} options - User options
     * @returns {Object} Chart.js options object
     */
    getBarChartOptions: function(options) {
        var chartOptions = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: !!options.title,
                    text: options.title || '',
                    font: { weight: 'bold' }
                },
                legend: { display: false },
                tooltip: { 
                    mode: 'index', 
                    intersect: false,
                    callbacks: options.tooltipCallbacks || {}
                }
            },
            scales: {
                x: {
                    type: 'time',
                    time: {
                        displayFormats: {
                            hour: options.xAxisFormat || 'HH:mm',
                            day: options.xAxisDayFormat || 'MMM d'
                        }
                    },
                    grid: { display: false },
                    ticks: { font: { size: 10 } }
                },
                y: {
                    min: options.yAxisMin !== undefined ? options.yAxisMin : 0,
                    grid: { display: true, color: 'rgba(0, 0, 0, 0.1)' },
                    ticks: { font: { size: 10 } }
                }
            }
        };
        
        if (options.xAxisMin !== undefined) chartOptions.scales.x.min = options.xAxisMin;
        if (options.xAxisMax !== undefined) chartOptions.scales.x.max = options.xAxisMax;
        
        return chartOptions;
    },

    /**
     * Get default stacked area chart options
     * 
     * @param {Object} options - User options
     * @param {number} datasetCount - Number of datasets
     * @returns {Object} Chart.js options object
     */
    getStackedAreaChartOptions: function(options, datasetCount) {
        var chartOptions = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: !!options.title,
                    text: options.title || '',
                    font: { weight: 'bold' }
                },
                legend: {
                    display: datasetCount > 1,
                    position: 'bottom'
                },
                tooltip: { 
                    mode: 'index', 
                    intersect: false,
                    callbacks: options.tooltipCallbacks || {}
                },
                filler: { propagate: false }
            },
            scales: {
                x: {
                    type: 'time',
                    time: {
                        displayFormats: {
                            hour: options.xAxisFormat || 'HH:mm',
                            day: options.xAxisDayFormat || 'MMM d'
                        }
                    },
                    grid: { display: true, color: 'rgba(0, 0, 0, 0.1)' },
                    ticks: { font: { size: 10 } }
                },
                y: {
                    stacked: true,
                    min: options.yAxisMin !== undefined ? options.yAxisMin : 0,
                    grid: { display: true, color: 'rgba(0, 0, 0, 0.1)' },
                    ticks: { font: { size: 10 } }
                }
            },
            interaction: { mode: 'nearest', axis: 'x', intersect: false }
        };
        
        return chartOptions;
    },

    /**
     * Create a chart with enhanced legend (similar to jqPlot EnhancedLegendRenderer)
     * 
     * @param {string} containerId - Container element ID
     * @param {Array} data - Array of data series
     * @param {Object} options - Chart options
     * @returns {Chart|null} Chart instance or null
     */
    createChartWithLegend: function(containerId, data, options) {
        options = options || {};
        
        var canvas = this.getOrCreateCanvas(containerId);
        if (!canvas) return null;
        
        var ctx = canvas.getContext('2d');
        
        // Destroy existing chart
        this.destroyChart(containerId);
        
        var datasets = [];
        var labels = options.labels || [];
        
        for (var i = 0; i < data.length; i++) {
            datasets.push({
                label: labels[i] || ('Series ' + (i + 1)),
                data: this.formatTimeSeriesData(data[i]),
                borderColor: this.colors[i % this.colors.length],
                backgroundColor: options.fill ? this.colors[i % this.colors.length] : 'transparent',
                fill: options.fill || false,
                tension: 0.4,
                pointRadius: 2,
                pointHoverRadius: 4
            });
        }
        
        var config = {
            type: 'line',
            data: { datasets: datasets },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: !!options.title,
                        text: options.title || '',
                        font: { weight: 'bold' }
                    },
                    legend: {
                        display: true,
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            padding: 10
                        },
                        onClick: function(e, legendItem, legend) {
                            // Toggle dataset visibility
                            var index = legendItem.datasetIndex;
                            var ci = legend.chart;
                            var meta = ci.getDatasetMeta(index);
                            meta.hidden = meta.hidden === null ? !ci.data.datasets[index].hidden : null;
                            ci.update();
                        }
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        callbacks: options.tooltipCallbacks || {
                            label: function(context) {
                                var label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.parsed.y !== null) {
                                    label += context.parsed.y.toFixed(options.yDecimals || 8);
                                }
                                return label;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        type: 'time',
                        time: {
                            displayFormats: {
                                hour: options.xAxisFormat || 'HH:mm',
                                day: options.xAxisDayFormat || 'MMM d'
                            }
                        },
                        grid: { display: true, color: 'rgba(0, 0, 0, 0.1)' },
                        ticks: { font: { size: 10 } }
                    },
                    y: {
                        min: options.yAxisMin,
                        max: options.yAxisMax,
                        grid: { display: true, color: 'rgba(0, 0, 0, 0.1)' },
                        ticks: { font: { size: 10 } }
                    }
                },
                interaction: { mode: 'nearest', axis: 'x', intersect: false }
            }
        };
        
        // Add secondary y-axis if needed
        if (options.useY2Axis) {
            config.options.scales.y2 = {
                position: 'right',
                min: options.y2AxisMin,
                max: options.y2AxisMax,
                grid: { display: false },
                ticks: { font: { size: 10 } }
            };
        }
        
        window['chart_' + containerId] = new Chart(ctx, config);
        
        // Register with ChartManager if available
        if (typeof ChartManager !== 'undefined') {
            ChartManager.register(containerId, window['chart_' + containerId]);
        }
        
        return window['chart_' + containerId];
    }
};
