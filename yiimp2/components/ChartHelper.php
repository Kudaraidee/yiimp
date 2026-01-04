<?php

namespace app\components;

use yii\helpers\Json;
use yii\helpers\Html;

/**
 * ChartHelper provides methods for generating Chart.js charts
 * 
 * This component replaces jqPlot with Chart.js for CSP compliance.
 * Chart.js uses canvas-based rendering which doesn't require inline styles.
 * 
 * @package app\components
 */
class ChartHelper
{
    /**
     * Default chart colors matching the original jqPlot theme
     */
    const DEFAULT_COLORS = [
        'rgba(78, 180, 180, 0.8)',   // Teal (primary)
        'rgba(54, 162, 235, 0.8)',   // Blue
        'rgba(255, 99, 132, 0.8)',   // Red
        'rgba(255, 206, 86, 0.8)',   // Yellow
        'rgba(75, 192, 192, 0.8)',   // Green
        'rgba(153, 102, 255, 0.8)',  // Purple
        'rgba(255, 159, 64, 0.8)',   // Orange
        'rgba(199, 199, 199, 0.8)',  // Grey
    ];

    /**
     * Render a line chart using Chart.js
     * 
     * @param string $containerId Canvas element ID
     * @param array $data Chart data series - array of [timestamp, value] pairs
     * @param array $options Chart configuration options
     * @return string JavaScript code for chart initialization
     */
    public static function lineChart(string $containerId, array $data, array $options = []): string
    {
        $defaultOptions = [
            'title' => '',
            'xAxisFormat' => 'HH:mm',
            'yAxisFormat' => '0.000',
            'yAxisMin' => 0,
            'fill' => false,
            'tension' => 0.4,
            'borderColor' => self::DEFAULT_COLORS[0],
            'backgroundColor' => self::DEFAULT_COLORS[0],
            'pointRadius' => 0,
        ];
        
        $options = array_merge($defaultOptions, $options);
        
        $chartConfig = [
            'type' => 'line',
            'data' => [
                'datasets' => [[
                    'data' => self::formatTimeSeriesData($data),
                    'borderColor' => $options['borderColor'],
                    'backgroundColor' => $options['backgroundColor'],
                    'fill' => $options['fill'],
                    'tension' => $options['tension'],
                    'pointRadius' => $options['pointRadius'],
                ]]
            ],
            'options' => [
                'responsive' => true,
                'maintainAspectRatio' => false,
                'plugins' => [
                    'title' => [
                        'display' => !empty($options['title']),
                        'text' => strip_tags($options['title']),
                        'font' => ['weight' => 'bold']
                    ],
                    'legend' => [
                        'display' => false
                    ],
                    'tooltip' => [
                        'mode' => 'index',
                        'intersect' => false,
                    ]
                ],
                'scales' => [
                    'x' => [
                        'type' => 'time',
                        'time' => [
                            'displayFormats' => [
                                'hour' => $options['xAxisFormat'],
                                'day' => 'MMM d'
                            ]
                        ],
                        'grid' => [
                            'display' => true,
                            'color' => 'rgba(0, 0, 0, 0.1)'
                        ],
                        'ticks' => [
                            'font' => ['size' => 10]
                        ]
                    ],
                    'y' => [
                        'min' => $options['yAxisMin'],
                        'grid' => [
                            'display' => true,
                            'color' => 'rgba(0, 0, 0, 0.1)'
                        ],
                        'ticks' => [
                            'font' => ['size' => 10]
                        ]
                    ]
                ],
                'interaction' => [
                    'mode' => 'nearest',
                    'axis' => 'x',
                    'intersect' => false
                ]
            ]
        ];
        
        // Add min/max for x-axis if provided
        if (isset($options['xAxisMin'])) {
            $chartConfig['options']['scales']['x']['min'] = $options['xAxisMin'];
        }
        if (isset($options['xAxisMax'])) {
            $chartConfig['options']['scales']['x']['max'] = $options['xAxisMax'];
        }
        
        return self::generateChartJs($containerId, $chartConfig);
    }

    /**
     * Render a bar chart using Chart.js
     * 
     * @param string $containerId Canvas element ID
     * @param array $data Chart data series - array of [timestamp, value] pairs
     * @param array $options Chart configuration options
     * @return string JavaScript code for chart initialization
     */
    public static function barChart(string $containerId, array $data, array $options = []): string
    {
        $defaultOptions = [
            'title' => '',
            'xAxisFormat' => 'HH:mm',
            'yAxisFormat' => '0.00000000',
            'yAxisMin' => 0,
            'barWidth' => 3,
            'backgroundColor' => 'rgba(54, 162, 235, 0.8)',
            'borderColor' => 'rgba(54, 162, 235, 1)',
        ];
        
        $options = array_merge($defaultOptions, $options);
        
        $chartConfig = [
            'type' => 'bar',
            'data' => [
                'datasets' => [[
                    'data' => self::formatTimeSeriesData($data),
                    'backgroundColor' => $options['backgroundColor'],
                    'borderColor' => $options['borderColor'],
                    'borderWidth' => 1,
                    'barPercentage' => 0.8,
                    'categoryPercentage' => 0.9,
                ]]
            ],
            'options' => [
                'responsive' => true,
                'maintainAspectRatio' => false,
                'plugins' => [
                    'title' => [
                        'display' => !empty($options['title']),
                        'text' => strip_tags($options['title']),
                        'font' => ['weight' => 'bold']
                    ],
                    'legend' => [
                        'display' => false
                    ],
                    'tooltip' => [
                        'mode' => 'index',
                        'intersect' => false,
                    ]
                ],
                'scales' => [
                    'x' => [
                        'type' => 'time',
                        'time' => [
                            'displayFormats' => [
                                'hour' => $options['xAxisFormat'],
                                'day' => 'MMM d'
                            ]
                        ],
                        'grid' => [
                            'display' => false
                        ],
                        'ticks' => [
                            'font' => ['size' => 10]
                        ]
                    ],
                    'y' => [
                        'min' => $options['yAxisMin'],
                        'grid' => [
                            'display' => true,
                            'color' => 'rgba(0, 0, 0, 0.1)'
                        ],
                        'ticks' => [
                            'font' => ['size' => 10]
                        ]
                    ]
                ]
            ]
        ];
        
        // Add min/max for x-axis if provided
        if (isset($options['xAxisMin'])) {
            $chartConfig['options']['scales']['x']['min'] = $options['xAxisMin'];
        }
        if (isset($options['xAxisMax'])) {
            $chartConfig['options']['scales']['x']['max'] = $options['xAxisMax'];
        }
        
        return self::generateChartJs($containerId, $chartConfig);
    }

    /**
     * Render a stacked area chart using Chart.js
     * 
     * @param string $containerId Canvas element ID
     * @param array $data Array of data series with labels
     * @param array $options Chart configuration options
     * @return string JavaScript code for chart initialization
     */
    public static function stackedAreaChart(string $containerId, array $data, array $options = []): string
    {
        $defaultOptions = [
            'title' => '',
            'xAxisFormat' => 'HH:mm',
            'yAxisFormat' => '0.00000000',
            'yAxisMin' => 0,
            'labels' => [],
        ];
        
        $options = array_merge($defaultOptions, $options);
        
        $datasets = [];
        $colorIndex = 0;
        
        foreach ($data as $index => $series) {
            $color = self::DEFAULT_COLORS[$colorIndex % count(self::DEFAULT_COLORS)];
            $label = isset($options['labels'][$index]) ? $options['labels'][$index] : "Series " . ($index + 1);
            
            $datasets[] = [
                'label' => $label,
                'data' => self::formatTimeSeriesData($series),
                'backgroundColor' => $color,
                'borderColor' => $color,
                'fill' => true,
                'tension' => 0.4,
                'pointRadius' => 0,
            ];
            $colorIndex++;
        }
        
        $chartConfig = [
            'type' => 'line',
            'data' => [
                'datasets' => $datasets
            ],
            'options' => [
                'responsive' => true,
                'maintainAspectRatio' => false,
                'plugins' => [
                    'title' => [
                        'display' => !empty($options['title']),
                        'text' => strip_tags($options['title']),
                        'font' => ['weight' => 'bold']
                    ],
                    'legend' => [
                        'display' => count($datasets) > 1,
                        'position' => 'bottom'
                    ],
                    'tooltip' => [
                        'mode' => 'index',
                        'intersect' => false,
                    ],
                    'filler' => [
                        'propagate' => false
                    ]
                ],
                'scales' => [
                    'x' => [
                        'type' => 'time',
                        'time' => [
                            'displayFormats' => [
                                'hour' => $options['xAxisFormat'],
                                'day' => 'MMM d'
                            ]
                        ],
                        'grid' => [
                            'display' => true,
                            'color' => 'rgba(0, 0, 0, 0.1)'
                        ],
                        'ticks' => [
                            'font' => ['size' => 10]
                        ]
                    ],
                    'y' => [
                        'stacked' => true,
                        'min' => $options['yAxisMin'],
                        'grid' => [
                            'display' => true,
                            'color' => 'rgba(0, 0, 0, 0.1)'
                        ],
                        'ticks' => [
                            'font' => ['size' => 10]
                        ]
                    ]
                ],
                'interaction' => [
                    'mode' => 'nearest',
                    'axis' => 'x',
                    'intersect' => false
                ]
            ]
        ];
        
        return self::generateChartJs($containerId, $chartConfig);
    }

    /**
     * Generate AJAX update code for Chart.js
     * 
     * @param string $chartVar JavaScript variable name for the chart
     * @param string $dataUrl AJAX endpoint URL
     * @param array $options Additional options
     * @return string JavaScript code for AJAX updates
     */
    public static function ajaxUpdate(string $chartVar, string $dataUrl, array $options = []): string
    {
        $defaultOptions = [
            'parseData' => true,
            'dataTransform' => null,
        ];
        
        $options = array_merge($defaultOptions, $options);
        
        $parseCode = $options['parseData'] ? 'var chartData = JSON.parse(data);' : 'var chartData = data;';
        $transformCode = $options['dataTransform'] ? "chartData = {$options['dataTransform']}(chartData);" : '';
        
        return <<<JS
function update_{$chartVar}() {
    $.get('{$dataUrl}', '', function(data) {
        {$parseCode}
        {$transformCode}
        if (window.{$chartVar}) {
            window.{$chartVar}.data.datasets[0].data = ChartHelper.formatTimeSeriesData(chartData);
            window.{$chartVar}.update('none');
        }
    });
}
JS;
    }

    /**
     * Generate JavaScript code to initialize a Chart.js chart
     * 
     * @param string $containerId Container element ID
     * @param array $config Chart configuration
     * @return string JavaScript code
     */
    protected static function generateChartJs(string $containerId, array $config): string
    {
        $configJson = Json::encode($config);
        
        return <<<JS
(function() {
    var container = document.getElementById('{$containerId}');
    if (!container) return;
    
    // Create canvas if it doesn't exist
    var canvas = container.querySelector('canvas');
    if (!canvas) {
        canvas = document.createElement('canvas');
        container.appendChild(canvas);
    }
    
    var ctx = canvas.getContext('2d');
    
    // Destroy existing chart if any
    if (window.chart_{$containerId}) {
        window.chart_{$containerId}.destroy();
    }
    
    window.chart_{$containerId} = new Chart(ctx, {$configJson});
    
    // Register with ChartManager if available
    if (typeof ChartManager !== 'undefined') {
        ChartManager.register('{$containerId}', window.chart_{$containerId});
    }
})();
JS;
    }

    /**
     * Format time series data for Chart.js
     * Converts [timestamp, value] pairs to {x: timestamp, y: value} format
     * 
     * @param array $data Array of [timestamp, value] pairs
     * @return array Formatted data for Chart.js
     */
    public static function formatTimeSeriesData(array $data): array
    {
        $formatted = [];
        foreach ($data as $point) {
            if (is_array($point) && count($point) >= 2) {
                $formatted[] = [
                    'x' => $point[0],
                    'y' => $point[1]
                ];
            }
        }
        return $formatted;
    }

    /**
     * Generate JavaScript helper functions for client-side use
     * 
     * @return string JavaScript code with helper functions
     */
    public static function getClientHelpers(): string
    {
        return <<<JS
/**
 * ChartHelper client-side utilities
 */
window.ChartHelper = window.ChartHelper || {
    /**
     * Format time series data for Chart.js
     * Converts [timestamp, value] pairs to {x: timestamp, y: value} format
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
     * Update chart with new data
     */
    updateChart: function(chartId, newData, datasetIndex) {
        datasetIndex = datasetIndex || 0;
        var chart = window['chart_' + chartId];
        if (chart) {
            chart.data.datasets[datasetIndex].data = this.formatTimeSeriesData(newData);
            chart.update('none');
        }
    },
    
    /**
     * Update multiple datasets
     */
    updateChartMultiple: function(chartId, datasetsData) {
        var chart = window['chart_' + chartId];
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
     */
    destroyChart: function(chartId) {
        var chart = window['chart_' + chartId];
        if (chart) {
            chart.destroy();
            delete window['chart_' + chartId];
        }
    },
    
    /**
     * Create a line chart
     */
    createLineChart: function(containerId, data, options) {
        options = options || {};
        var container = document.getElementById(containerId);
        if (!container) return null;
        
        var canvas = container.querySelector('canvas');
        if (!canvas) {
            canvas = document.createElement('canvas');
            container.appendChild(canvas);
        }
        
        var ctx = canvas.getContext('2d');
        
        // Destroy existing chart
        this.destroyChart(containerId);
        
        var config = {
            type: 'line',
            data: {
                datasets: [{
                    data: this.formatTimeSeriesData(data),
                    borderColor: options.borderColor || 'rgba(78, 180, 180, 0.8)',
                    backgroundColor: options.backgroundColor || 'rgba(78, 180, 180, 0.8)',
                    fill: options.fill || false,
                    tension: options.tension || 0.4,
                    pointRadius: options.pointRadius || 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: !!options.title,
                        text: options.title || '',
                        font: { weight: 'bold' }
                    },
                    legend: { display: false },
                    tooltip: { mode: 'index', intersect: false }
                },
                scales: {
                    x: {
                        type: 'time',
                        time: {
                            displayFormats: {
                                hour: options.xAxisFormat || 'HH:mm',
                                day: 'MMM d'
                            }
                        },
                        grid: { display: true, color: 'rgba(0, 0, 0, 0.1)' },
                        ticks: { font: { size: 10 } }
                    },
                    y: {
                        min: options.yAxisMin || 0,
                        grid: { display: true, color: 'rgba(0, 0, 0, 0.1)' },
                        ticks: { font: { size: 10 } }
                    }
                },
                interaction: { mode: 'nearest', axis: 'x', intersect: false }
            }
        };
        
        if (options.xAxisMin) config.options.scales.x.min = options.xAxisMin;
        if (options.xAxisMax) config.options.scales.x.max = options.xAxisMax;
        
        window['chart_' + containerId] = new Chart(ctx, config);
        return window['chart_' + containerId];
    },
    
    /**
     * Create a bar chart
     */
    createBarChart: function(containerId, data, options) {
        options = options || {};
        var container = document.getElementById(containerId);
        if (!container) return null;
        
        var canvas = container.querySelector('canvas');
        if (!canvas) {
            canvas = document.createElement('canvas');
            container.appendChild(canvas);
        }
        
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
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: !!options.title,
                        text: options.title || '',
                        font: { weight: 'bold' }
                    },
                    legend: { display: false },
                    tooltip: { mode: 'index', intersect: false }
                },
                scales: {
                    x: {
                        type: 'time',
                        time: {
                            displayFormats: {
                                hour: options.xAxisFormat || 'HH:mm',
                                day: 'MMM d'
                            }
                        },
                        grid: { display: false },
                        ticks: { font: { size: 10 } }
                    },
                    y: {
                        min: options.yAxisMin || 0,
                        grid: { display: true, color: 'rgba(0, 0, 0, 0.1)' },
                        ticks: { font: { size: 10 } }
                    }
                }
            }
        };
        
        if (options.xAxisMin) config.options.scales.x.min = options.xAxisMin;
        if (options.xAxisMax) config.options.scales.x.max = options.xAxisMax;
        
        window['chart_' + containerId] = new Chart(ctx, config);
        return window['chart_' + containerId];
    },
    
    /**
     * Create a stacked area chart
     */
    createStackedAreaChart: function(containerId, data, options) {
        options = options || {};
        var container = document.getElementById(containerId);
        if (!container) return null;
        
        var canvas = container.querySelector('canvas');
        if (!canvas) {
            canvas = document.createElement('canvas');
            container.appendChild(canvas);
        }
        
        var ctx = canvas.getContext('2d');
        
        // Destroy existing chart
        this.destroyChart(containerId);
        
        var colors = [
            'rgba(78, 180, 180, 0.8)',
            'rgba(54, 162, 235, 0.8)',
            'rgba(255, 99, 132, 0.8)',
            'rgba(255, 206, 86, 0.8)',
            'rgba(75, 192, 192, 0.8)',
            'rgba(153, 102, 255, 0.8)',
            'rgba(255, 159, 64, 0.8)',
            'rgba(199, 199, 199, 0.8)'
        ];
        
        var datasets = [];
        var labels = options.labels || [];
        
        for (var i = 0; i < data.length; i++) {
            datasets.push({
                label: labels[i] || ('Series ' + (i + 1)),
                data: this.formatTimeSeriesData(data[i]),
                backgroundColor: colors[i % colors.length],
                borderColor: colors[i % colors.length],
                fill: true,
                tension: 0.4,
                pointRadius: 0
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
                        display: datasets.length > 1,
                        position: 'bottom'
                    },
                    tooltip: { mode: 'index', intersect: false },
                    filler: { propagate: false }
                },
                scales: {
                    x: {
                        type: 'time',
                        time: {
                            displayFormats: {
                                hour: options.xAxisFormat || 'HH:mm',
                                day: 'MMM d'
                            }
                        },
                        grid: { display: true, color: 'rgba(0, 0, 0, 0.1)' },
                        ticks: { font: { size: 10 } }
                    },
                    y: {
                        stacked: true,
                        min: options.yAxisMin || 0,
                        grid: { display: true, color: 'rgba(0, 0, 0, 0.1)' },
                        ticks: { font: { size: 10 } }
                    }
                },
                interaction: { mode: 'nearest', axis: 'x', intersect: false }
            }
        };
        
        window['chart_' + containerId] = new Chart(ctx, config);
        return window['chart_' + containerId];
    }
};
JS;
    }
}
