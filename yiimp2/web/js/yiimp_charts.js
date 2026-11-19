/**
 * yiimp_charts.js — Chart.js 4.x wrapper replacing jqPlot.
 *
 * Usage:
 *   yiimpChart(divId, jsonData, options)
 *
 * jsonData formats accepted:
 *   [[date, val], ...]                    — single series
 *   [[[date,v], ...], [[date,v], ...]]    — multiple series
 *   [[[s1],[s2],[s3]], "title"]           — series array + trailing title string
 *
 * Options:
 *   type        'line'|'bar'          default 'line'
 *   title       string                chart title
 *   labels      string[]              per-dataset legend labels
 *   colors      string[]              override palette
 *   fill        bool                  fill area under line
 *   stack       bool                  stack datasets
 *   decimals    int                   y-axis decimal places (default 3)
 *   btcDecimals bool                  use 8 decimal places for BTC values
 *   showLegend  bool                  force legend when single series
 */
(function (window) {
    'use strict';

    var PALETTE = [
        '#4e9fd9', '#e06c75', '#98c379', '#d19a66',
        '#c678dd', '#56b6c2', '#e5c07b', '#abb2bf'
    ];

    // Format a 'Y-m-d H:i:s' date string into a compact label.
    // periodDays: approximate span of the x-axis in days.
    function fmtLabel(dateStr, periodDays) {
        if (!dateStr) return '';
        var parts = dateStr.split(' ');
        var datePart = parts[0] || '';
        var timePart = parts[1] || '00:00:00';
        var tp = timePart.split(':');
        var h = parseInt(tp[0], 10) || 0;
        var m = parseInt(tp[1], 10) || 0;

        if (periodDays >= 20) {
            // 30-day view: show M/D
            var dp = datePart.split('-');
            return (parseInt(dp[1], 10) || 0) + '/' + (parseInt(dp[2], 10) || 0);
        }
        if (periodDays >= 3) {
            // 7-day view: show day number
            var dp2 = datePart.split('-');
            return String(parseInt(dp2[2], 10) || '');
        }
        // 48h / 24h view: show hours
        return h + (m > 0 ? ':' + (m < 10 ? '0' : '') + m : 'h');
    }

    // Normalise incoming JSON into an array of series arrays.
    // Each series is [[date_str, value], ...].
    // Returns {seriesArray, title}.
    function normalise(raw) {
        if (!raw || !raw.length) return null;

        var title = '';
        var data = raw;

        // Trailing string element = chart title (graph_user_results format)
        if (typeof data[data.length - 1] === 'string') {
            title = data[data.length - 1];
            data  = data.slice(0, -1);
        }

        // Single series: data[0][0] is a scalar (not an array of arrays)
        if (!Array.isArray(data[0])) {
            return { seriesArray: [data], title: title };
        }

        // Multiple series: data[0] is itself a series array
        // Check if it looks like [[date, val], ...] (first element has string date)
        if (Array.isArray(data[0][0]) && typeof data[0][0][0] === 'string') {
            // Already an array of series
            return { seriesArray: data, title: title };
        }

        // data[0] is a non-nested array — treat whole thing as one series
        if (typeof data[0][0] === 'string') {
            return { seriesArray: [data], title: title };
        }

        // Nested: [[series1], [series2], ...]
        return { seriesArray: data, title: title };
    }

    window.yiimpChart = function (divId, rawData, opts) {
        opts = opts || {};

        var div = document.getElementById(divId);
        if (!div) return null;

        var data;
        try {
            data = (typeof rawData === 'string') ? JSON.parse(rawData) : rawData;
        } catch (e) { return null; }

        var norm = normalise(data);
        if (!norm) return null;

        var seriesArray = norm.seriesArray;
        var chartTitle  = opts.title || norm.title || '';

        if (!seriesArray.length || !seriesArray[0] || !seriesArray[0].length) return null;

        // Estimate period in days from label spread
        var firstLabel = seriesArray[0][0][0] || '';
        var lastLabel  = seriesArray[0][seriesArray[0].length - 1][0] || '';
        var t0 = firstLabel ? new Date(firstLabel.replace(' ', 'T')).getTime() : 0;
        var t1 = lastLabel  ? new Date(lastLabel.replace(' ', 'T')).getTime() : 0;
        var periodDays = (t1 - t0) / (86400 * 1000);

        var labels   = seriesArray[0].map(function (p) { return p[0]; });
        var dispLabels = labels.map(function (l) { return fmtLabel(l, periodDays); });

        var decimals = opts.btcDecimals ? 8 : (opts.decimals !== undefined ? opts.decimals : 3);
        var chartType = opts.type || 'line';

        var datasets = seriesArray.map(function (series, i) {
            var values = series.map(function (p) { return p[1]; });
            var color  = (opts.colors && opts.colors[i]) ? opts.colors[i] : PALETTE[i % PALETTE.length];
            var ds = {
                data:          values,
                borderColor:   color,
                borderWidth:   1.5,
                pointRadius:   0,
                tension:       0.3,
                fill:          false,
                backgroundColor: color
            };
            if (opts.labels && opts.labels[i]) ds.label = opts.labels[i];

            if (chartType === 'line') {
                if (opts.fill) {
                    // semi-transparent fill
                    ds.fill            = true;
                    ds.backgroundColor = color.replace('rgb(', 'rgba(').replace(')', ', 0.15)');
                    // Handle rgba/hex colours without rgb() prefix
                    if (ds.backgroundColor === color) {
                        ds.backgroundColor = color + '26'; // hex + 15% alpha
                    }
                }
                if (opts.stack) {
                    ds.fill  = 'origin';
                    ds.stack = 'main';
                }
            }

            if (chartType === 'bar' && opts.stack) {
                ds.stack = 'main';
            }

            return ds;
        });

        // Create canvas inside the div (Chart.js needs <canvas>)
        div.innerHTML = '<canvas style="width:100%;height:100%;"></canvas>';
        var canvas = div.querySelector('canvas');

        var showLegend = (datasets.length > 1) || !!opts.showLegend;

        var chart = new Chart(canvas, {
            type: chartType,
            data: { labels: dispLabels, datasets: datasets },
            options: {
                responsive:          true,
                maintainAspectRatio: false,
                animation:           false,
                plugins: {
                    legend: {
                        display:  showLegend,
                        position: 'top',
                        labels:   { boxWidth: 12, font: { size: 11 } }
                    },
                    title: {
                        display: !!chartTitle,
                        text:    chartTitle,
                        font:    { size: 12, weight: 'bold' }
                    },
                    tooltip: {
                        callbacks: {
                            title: function (items) {
                                return labels[items[0].dataIndex] || '';
                            },
                            label: function (item) {
                                return (item.dataset.label ? item.dataset.label + ': ' : '')
                                     + Number(item.raw).toFixed(decimals);
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        ticks: {
                            maxTicksLimit: 8,
                            maxRotation:  0,
                            font:         { size: 10 }
                        },
                        grid: { color: 'rgba(0,0,0,0.05)' }
                    },
                    y: {
                        min: 0,
                        stacked: opts.stack || false,
                        ticks: {
                            font: { size: 10 },
                            callback: function (v) { return Number(v).toFixed(decimals); }
                        },
                        grid: { color: 'rgba(0,0,0,0.05)' }
                    }
                }
            }
        });

        // Replace window.resize + replot() with ResizeObserver
        if (window.ResizeObserver) {
            new ResizeObserver(function () { chart.resize(); }).observe(div);
        }

        return chart;
    };

}(window));
