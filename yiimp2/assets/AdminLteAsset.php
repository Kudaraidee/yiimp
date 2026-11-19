<?php

namespace app\assets;

use yii\web\AssetBundle;

/**
 * AdminLTE 4 asset bundle (Bootstrap 5 base).
 *
 * Development / pre-npm: loads AdminLTE 4 from jsDelivr CDN.
 *
 * Production (after `npm install` in yiimp2/):
 *   Switch to local files by replacing the $css/$js CDN entries with:
 *     public $sourcePath = '@app/node_modules/admin-lte/dist';
 *     public $css = ['css/adminlte.min.css'];
 *     public $js  = ['js/adminlte.min.js'];
 *   and remove the CDN entries.
 *
 * Font Awesome 6 Free is bundled with AdminLTE 4 when using npm; the CDN
 * entry below includes it via the AdminLTE CSS import chain.
 */
class AdminLteAsset extends AssetBundle
{
    public $css = [
        'https://cdn.jsdelivr.net/npm/admin-lte@4.0.0/dist/css/adminlte.min.css',
    ];
    public $js = [
        'https://cdn.jsdelivr.net/npm/admin-lte@4.0.0/dist/js/adminlte.min.js',
    ];
    public $depends = [
        'yii\web\YiiAsset',
        'yii\bootstrap5\BootstrapAsset',
        'yii\bootstrap5\BootstrapPluginAsset',
        'app\assets\ChartAsset',
        'app\assets\TablesorterAsset',
    ];
}
