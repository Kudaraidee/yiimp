<?php

namespace app\assets;

use yii\web\AssetBundle;

/**
 * CSP Compliant Asset Bundle
 * 
 * This asset bundle provides CSS and JavaScript files for CSP-compliant
 * styling and behavior, specifically for Yii2 widgets and form handling.
 * 
 * Features:
 * - Yii2 widget styling without inline styles
 * - CSP-compliant JavaScript utilities for style manipulation
 * - Form validation styling using CSS classes
 * - Dropdown and form field state management
 * 
 * Usage:
 * Register this asset in views that use ActiveForm or other Yii2 widgets:
 * 
 * ```php
 * use app\assets\CspCompliantAsset;
 * CspCompliantAsset::register($this);
 * ```
 */
class CspCompliantAsset extends AssetBundle
{
    public $basePath = '@webroot';
    public $baseUrl = '@web';
    
    public $css = [
        'css/yii-widgets.css',
    ];
    
    public $js = [
        'js/csp-utils.js',
    ];
    
    public $depends = [
        'yii\web\YiiAsset',
        'yii\bootstrap5\BootstrapAsset',
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
