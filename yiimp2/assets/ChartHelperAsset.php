<?php

namespace app\assets;

use yii\web\AssetBundle;

/**
 * Chart Helper asset bundle
 * 
 * Provides client-side JavaScript helpers for Chart.js charts.
 * This bundle depends on ChartJsAsset and provides utility functions
 * for creating and updating charts dynamically.
 */
class ChartHelperAsset extends AssetBundle
{
    public $basePath = '@webroot';
    public $baseUrl = '@web';
    
    public $js = [
        'js/chart-helper.js',
    ];
    
    public $depends = [
        'app\assets\ChartJsAsset',
    ];
}
