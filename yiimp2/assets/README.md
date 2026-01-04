# Asset Management

This directory contains asset bundles for the Yiimp2 application.

## Asset Bundles

### AppAsset
Main application asset bundle that includes core CSS and JavaScript files.
- Loaded on all pages
- Includes Bootstrap 5, responsive styles, GridView enhancements, and chart helpers
- Automatically minified in production

### ChartJsAsset
Chart.js library asset bundle for interactive charts.
- Loaded only when charts are needed
- Provides Chart.js for rendering line, bar, pie, and other chart types

### AdminAsset
Admin-specific asset bundle.
- Loaded only on admin pages
- Includes admin dashboard styles and functionality
- Reduces asset load on public pages

## Asset Optimization

### Production Mode
In production (`YII_ENV === 'prod'`), assets are automatically optimized:
- **Minification**: CSS and JavaScript files are minified
- **Cache Busting**: Timestamps are appended to asset URLs
- **CDN Support**: Can be configured to use CDN for common libraries

### Development Mode
In development (`YII_ENV === 'dev'`), assets are optimized for development:
- **Symlinks**: Assets are symlinked for faster updates
- **No Minification**: Full source files for easier debugging
- **Source Maps**: Available for debugging

## Configuration

Asset management is configured in `config/web.php`:

```php
'assetManager' => [
    'appendTimestamp' => true,  // Cache busting
    'bundles' => [
        // Production optimizations
    ],
    'linkAssets' => YII_ENV === 'dev',  // Symlinks in dev
],
```

## Adding New Assets

### Creating a New Asset Bundle

1. Create a new file in `assets/` directory:

```php
<?php
namespace app\assets;

use yii\web\AssetBundle;

class MyAsset extends AssetBundle
{
    public $basePath = '@webroot';
    public $baseUrl = '@web';
    
    public $css = [
        'css/my-styles.css',
    ];
    
    public $js = [
        'js/my-script.js',
    ];
    
    public $depends = [
        'app\assets\AppAsset',
    ];
}
```

2. Register the asset in your view or layout:

```php
use app\assets\MyAsset;

MyAsset::register($this);
```

### Using NPM Packages

For NPM packages, use the `@npm` alias:

```php
public $sourcePath = '@npm/package-name/dist';
```

### Using Bower Packages

For Bower packages, use the `@bower` alias:

```php
public $sourcePath = '@bower/package-name/dist';
```

## Asset Publishing

Assets are published to `web/assets/` directory. This directory is automatically created and managed by Yii2.

### Clearing Asset Cache

To clear the asset cache:

```bash
rm -rf web/assets/*
```

Or use the Yii2 command:

```bash
./yii asset/clear
```

## Best Practices

1. **Separate Concerns**: Create separate asset bundles for different sections (admin, public, etc.)
2. **Lazy Loading**: Load assets only when needed
3. **Dependencies**: Properly declare dependencies between bundles
4. **Minification**: Always minify assets in production
5. **Cache Busting**: Use timestamps or hashes for cache busting
6. **CDN**: Consider using CDN for common libraries in production

## Performance Tips

1. **Combine Assets**: Yii2 can combine multiple CSS/JS files into one
2. **Compress Assets**: Enable gzip compression on your web server
3. **Async Loading**: Use async/defer for non-critical JavaScript
4. **Critical CSS**: Inline critical CSS for faster initial render
5. **Image Optimization**: Optimize images before adding to assets

## Troubleshooting

### Assets Not Loading

1. Check file permissions on `web/assets/` directory
2. Clear asset cache: `rm -rf web/assets/*`
3. Check asset bundle configuration
4. Verify file paths are correct

### Minification Issues

1. Check for JavaScript syntax errors
2. Verify minification is enabled in production
3. Test with minification disabled to isolate issues

### Cache Issues

1. Enable `appendTimestamp` in asset manager
2. Clear browser cache
3. Clear asset cache on server
4. Check cache headers on web server
