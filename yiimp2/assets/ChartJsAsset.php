<?php

namespace app\assets;

use yii\web\AssetBundle;

/**
 * Chart.js asset bundle
 * 
 * Provides Chart.js library for interactive charts with time axis support.
 * This bundle is CSP-compliant as Chart.js uses canvas-based rendering.
 * 
 * Uses locally hosted Chart.js files to eliminate external CDN dependencies
 * and ensure CSP compliance without requiring external script-src allowances.
 * 
 * Includes:
 * - Chart.js 4.5.1 core library (locally hosted)
 * - chartjs-adapter-date-fns 3.0.0 for time scale support (locally hosted)
 */
class ChartJsAsset extends AssetBundle
{
    public $sourcePath = '@webroot/js/vendor';
    
    public $js = [
        'chart.js/chart.umd.min.js',
        'chartjs-adapter-date-fns/chartjs-adapter-date-fns.bundle.min.js',
    ];
    
    public $depends = [
        'yii\web\JqueryAsset',
    ];
}
