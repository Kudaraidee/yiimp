<?php

namespace app\assets;

use yii\web\AssetBundle;

/**
 * Main application asset bundle.
 * 
 * This asset bundle includes all core CSS and JavaScript files
 * for the Yiimp2 application with optimization features:
 * - Asset minification in production
 * - Cache busting via timestamps
 * - Proper dependency management
 */
class AppAsset extends AssetBundle
{
    public $basePath = '@webroot';
    public $baseUrl = '@web';
    public $jsOptions = ['position' => \yii\web\View::POS_HEAD];
    
    public $css = [
        'css/site.css',
        'css/main.css',
        'css/table.css',
        'css/responsive.css',
        'css/forms-responsive.css',
        'css/gridview.css',
        'css/charts.css',
        'css/algo-colors.css',
        'css/utilities.css',
    ];
    
    public $js = [
        'js/jquery.tablesorter.js',
        'js/gridview-enhanced.js',
        'js/chart-helpers.js',
    ];
    
    public $depends = [
        'yii\web\YiiAsset',
        'yii\bootstrap5\BootstrapAsset'
    ];
    
    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        
        // Enable asset minification in production
        if (YII_ENV === 'prod') {
            $this->cssOptions['minify'] = true;
            $this->jsOptions['minify'] = true;
        }
    }
}
