<?php

namespace app\assets;

use yii\web\AssetBundle;

/**
 * Chart.js 4.x — shared by all layout schemes.
 *
 * Loaded from CDN. When a local build is preferred, set $sourcePath to the
 * npm install directory and override $js:
 *   public $sourcePath = '@app/node_modules/chart.js/dist';
 *   public $js = ['chart.umd.min.js'];
 *
 * Registered automatically by AdminLteAsset and TailwindAsset.
 * AppAsset will depend on it once graph partials are migrated in Phase 7.
 *
 * No $jsOptions — uses the default POS_END position so it does not conflict
 * with YiiAsset's position when referenced through dependency chains that
 * include AppAsset (which pins YiiAsset to POS_HEAD).
 */
class ChartAsset extends AssetBundle
{
    public $basePath = '@webroot';
    public $baseUrl  = '@web';
    public $js       = [
        'https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js',
        'js/yiimp_charts.js',
    ];
    public $depends  = ['yii\web\YiiAsset'];
}
