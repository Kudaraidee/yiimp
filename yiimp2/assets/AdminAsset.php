<?php

namespace app\assets;

use yii\web\AssetBundle;

/**
 * Admin-specific asset bundle
 * 
 * This bundle is loaded only for admin pages to reduce
 * the asset load on public pages
 */
class AdminAsset extends AssetBundle
{
    public $basePath = '@webroot';
    public $baseUrl = '@web';
    
    public $css = [
        'css/admin.css',
    ];
    
    public $js = [
        'js/admin.js',
    ];
    
    public $depends = [
        'app\assets\AppAsset',
    ];
}
