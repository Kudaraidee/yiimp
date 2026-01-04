<?php
/**
 * Build-time configuration for asset publishing
 * This config is used during Docker build when no database is available
 */

// Minimal configuration for asset publishing without database
$config = [
    'id' => 'build-assets',
    'basePath' => dirname(__DIR__),
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
    ],
    'components' => [
        'assetManager' => [
            'appendTimestamp' => true,
            'forceCopy' => true,
            'bundles' => [
                // Production asset configuration
                'yii\web\JqueryAsset' => [
                    'js' => ['jquery.min.js']
                ],
                'yii\bootstrap5\BootstrapAsset' => [
                    'css' => ['css/bootstrap.min.css'],
                ],
                'yii\bootstrap5\BootstrapPluginAsset' => [
                    'js' => ['js/bootstrap.bundle.min.js']
                ],
            ],
        ],
        'view' => [
            'class' => 'yii\web\View',
        ],
        // Dummy request component (no CSRF validation during build)
        'request' => [
            'class' => 'yii\web\Request',
            'cookieValidationKey' => 'dummy-key-for-build',
            'enableCsrfValidation' => false,
        ],
    ],
];

return $config;