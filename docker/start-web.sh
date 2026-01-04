#!/bin/bash
set -e

# Container startup script for yiimp2-web
# Starts PHP-FPM and Nginx for the Yii2 web frontend

echo "[$(date '+%Y-%m-%d %H:%M:%S')] Starting yiimp2-web container..."

# Function to handle errors
error_exit() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] ERROR: $1" >&2
    exit 1
}

# Function to check if a process is running
check_process() {
    local process_name=$1
    if pgrep -x "$process_name" > /dev/null; then
        echo "[$(date '+%Y-%m-%d %H:%M:%S')] $process_name is running"
        return 0
    else
        echo "[$(date '+%Y-%m-%d %H:%M:%S')] WARNING: $process_name is not running" >&2
        return 1
    fi
}

# Verify PHP-FPM configuration
echo "[$(date '+%Y-%m-%d %H:%M:%S')] Verifying PHP-FPM configuration..."
if ! php-fpm8.4 -t 2>&1; then
    error_exit "PHP-FPM configuration test failed"
fi

# Verify Nginx configuration
echo "[$(date '+%Y-%m-%d %H:%M:%S')] Verifying Nginx configuration..."
if ! nginx -t 2>&1; then
    error_exit "Nginx configuration test failed"
fi

# Publish assets if not already present (one-time setup)
echo "[$(date '+%Y-%m-%d %H:%M:%S')] Checking asset publishing..."
if [ ! -d "/var/yiimp2/web/assets" ] || [ -z "$(ls -A /var/yiimp2/web/assets 2>/dev/null)" ]; then
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] Publishing assets..."
    cd /var/yiimp2
    php -r "
        require_once 'vendor/autoload.php';
        require_once 'vendor/yiisoft/yii2/Yii.php';
        require_once '/etc/yiimp2/serverconfig.php';
        \$config = require 'config/web.php';
        \$app = new yii\web\Application(\$config);
        \$assetManager = \$app->assetManager;
        \$assetManager->forceCopy = true;
        echo 'Publishing AppAsset...' . PHP_EOL;
        \$appAssetUrl = \app\assets\AppAsset::register(\$app->view)->baseUrl;
        echo 'AppAsset published to: ' . \$appAssetUrl . PHP_EOL;
        echo 'Publishing BootstrapAsset...' . PHP_EOL;
        \$bootstrapAssetUrl = \yii\bootstrap5\BootstrapAsset::register(\$app->view)->baseUrl;
        echo 'BootstrapAsset published to: ' . \$bootstrapAssetUrl . PHP_EOL;
        echo 'Publishing AdminAsset...' . PHP_EOL;
        \$adminAssetUrl = \app\assets\AdminAsset::register(\$app->view)->baseUrl;
        echo 'AdminAsset published to: ' . \$adminAssetUrl . PHP_EOL;
        echo 'Publishing ChartJsAsset...' . PHP_EOL;
        \$chartJsAssetUrl = \app\assets\ChartJsAsset::register(\$app->view)->baseUrl;
        echo 'ChartJsAsset published to: ' . \$chartJsAssetUrl . PHP_EOL;
        echo 'Publishing CspCompliantAsset...' . PHP_EOL;
        \$cspAssetUrl = \app\assets\CspCompliantAsset::register(\$app->view)->baseUrl;
        echo 'CspCompliantAsset published to: ' . \$cspAssetUrl . PHP_EOL;
        echo 'Assets published successfully!' . PHP_EOL;
    "
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] Asset publishing completed"
else
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] Assets already present - found $(ls /var/yiimp2/web/assets | wc -l) asset bundles"
fi

# Start PHP-FPM in background
echo "[$(date '+%Y-%m-%d %H:%M:%S')] Starting PHP-FPM..."
php-fpm8.4 --nodaemonize --fpm-config /etc/php/8.4/fpm/php-fpm.conf &
PHP_FPM_PID=$!

# Wait a moment for PHP-FPM to initialize
sleep 2

# Check if PHP-FPM started successfully
if ! check_process "php-fpm8.4"; then
    error_exit "PHP-FPM failed to start"
fi

echo "[$(date '+%Y-%m-%d %H:%M:%S')] PHP-FPM started successfully (PID: $PHP_FPM_PID)"

# Start Nginx in foreground
echo "[$(date '+%Y-%m-%d %H:%M:%S')] Starting Nginx..."
exec nginx -g 'daemon off;'
