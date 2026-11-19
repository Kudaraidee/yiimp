<?php

namespace app\assets;

use yii\web\AssetBundle;
use yii\web\View;

/**
 * jQuery tablesorter plugin — shared by AppAsset (legacy) and AdminLteAsset.
 * TailwindAsset does not depend on this; the Tailwind scheme uses a vanilla-JS
 * sort/filter implemented in ViewUtils::emitVanillaSorter().
 */
class TablesorterAsset extends AssetBundle
{
    public $basePath  = '@webroot';
    public $baseUrl   = '@web';
    public $jsOptions = ['position' => View::POS_HEAD];
    public $js = [
        'js/jquery.tablesorter.js',
    ];
    public $depends = [
        'yii\web\JqueryAsset',
    ];
}
