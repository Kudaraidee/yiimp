# Yiimp2 Installation and Configuration Guide

## Overview

This guide provides step-by-step instructions for installing and configuring the Yiimp2 mining pool platform.

## Prerequisites

### System Requirements
- **Operating System**: Ubuntu 22.04 LTS (recommended) or similar Linux distribution
- **PHP**: 8.1 or higher
- **MySQL/MariaDB**: 5.7+ / 10.3+
- **Web Server**: Apache 2.4+ with mod_php or Nginx with PHP-FPM
- **Memcached**: Latest stable version
- **Composer**: Latest version for PHP dependency management

### PHP Extensions Required
- bcmath
- curl
- gd
- intl
- memcache
- mysqli
- opcache
- mbstring
- xml
- zip

## Installation Steps

### 1. Install System Dependencies

```bash
# Update system packages
sudo apt update && sudo apt upgrade -y

# Install PHP and required extensions
sudo apt install -y php8.1 php8.1-cli php8.1-fpm php8.1-mysql php8.1-curl \
    php8.1-gd php8.1-intl php8.1-mbstring php8.1-xml php8.1-zip \
    php8.1-bcmath php8.1-opcache

# Install Memcached
sudo apt install -y memcached php8.1-memcache

# Install MySQL/MariaDB
sudo apt install -y mariadb-server mariadb-client

# Install Apache (or Nginx)
sudo apt install -y apache2 libapache2-mod-php8.1

# Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

### 2. Configure MySQL/MariaDB

```bash
# Secure MySQL installation
sudo mysql_secure_installation

# Create database and users
sudo mysql -u root -p
```

```sql
-- Create database
CREATE DATABASE yaamp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Create web user (for PHP application)
CREATE USER 'yaamp_web'@'localhost' IDENTIFIED BY 'secure_password_here';
GRANT SELECT, INSERT, UPDATE, DELETE ON yaamp.* TO 'yaamp_web'@'localhost';

-- Create stratum user (for mining server)
CREATE USER 'yaamp_stratum'@'localhost' IDENTIFIED BY 'another_secure_password';
GRANT SELECT, INSERT, UPDATE, DELETE ON yaamp.* TO 'yaamp_stratum'@'localhost';

FLUSH PRIVILEGES;
EXIT;
```

### 3. Import Database Schema

```bash
# Navigate to SQL directory
cd /path/to/yiimp2/../sql

# Import base schema
mysql -u root -p yaamp < 2024-03-06-complete_export.sql.gz

# Apply migrations in chronological order
mysql -u root -p yaamp < 2024-03-18-add_aurum_algo.sql
mysql -u root -p yaamp < 2024-03-29-add_github_version.sql
mysql -u root -p yaamp < 2024-03-31-add_payout_threshold.sql
# ... continue with other migration files
```

### 4. Clone and Configure Yiimp2

```bash
# Clone repository (if not already done)
cd /var/www
sudo git clone https://github.com/your-repo/yiimp.git
cd yiimp/yiimp2

# Install PHP dependencies
composer install --no-dev --optimize-autoloader

# Set proper permissions
sudo chown -R www-data:www-data /var/www/yiimp
sudo chmod -R 755 /var/www/yiimp
sudo chmod -R 777 /var/www/yiimp/yiimp2/runtime
sudo chmod -R 777 /var/www/yiimp/log
```

### 5. Configure Application

```bash
# Copy configuration template
sudo cp /var/www/yiimp/config/serverconfig.php /etc/yiimp/serverconfig.php

# Edit configuration
sudo nano /etc/yiimp/serverconfig.php
```

**Key Configuration Settings**:

```php
<?php

// Database Configuration
define('YIIMP_DBHOST', 'localhost');
define('YIIMP_DBNAME', 'yaamp');
define('YIIMP_DBUSER', 'yaamp_web');
define('YIIMP_DBPASSWORD', 'secure_password_here');

// Site Configuration
define('YIIMP_SITE_URL', 'https://your-pool.com');
define('YIIMP_SITE_NAME', 'Your Pool Name');
define('YIIMP_ADMIN_EMAIL', 'admin@your-pool.com');

// Pool Fees
define('YAAMP_FEES_MINING', 0.5); // 0.5% mining fee
define('YAAMP_FEES_SOLO', 0.5);   // 0.5% solo mining fee

// Payout Settings
define('YAAMP_PAYMENTS_FREQ', 3600); // Payout frequency in seconds
define('YAAMP_PAYMENTS_MINI', 0.001); // Minimum payout amount

// Memcached Configuration
define('YIIMP_MEMCACHED_HOST', 'localhost');
define('YIIMP_MEMCACHED_PORT', 11211);

// Security
define('YIIMP_ADMIN_IP', '127.0.0.1'); // Admin IP whitelist (comma-separated)
define('YIIMP_ADMIN_PASSWORD', 'admin_password_hash'); // Use password_hash()

// Exchange API Keys (optional)
define('YIIMP_EXCHANGE_ENABLED', false);
// Add exchange API keys in /etc/yiimp/keys.php
```

### 6. Configure Yii2 Application

```bash
# Edit Yii2 database configuration
sudo nano /var/www/yiimp/yiimp2/config/db.php
```

```php
<?php

return [
    'class' => 'yii\db\Connection',
    'dsn' => 'mysql:host=localhost;dbname=yaamp',
    'username' => 'yaamp_web',
    'password' => 'secure_password_here',
    'charset' => 'utf8mb4',
    
    // Enable schema caching for better performance
    'enableSchemaCache' => true,
    'schemaCacheDuration' => 3600,
    'schemaCache' => 'cache',
];
```

### 7. Configure Web Server

#### Apache Configuration

```bash
# Create virtual host configuration
sudo nano /etc/apache2/sites-available/yiimp2.conf
```

```apache
<VirtualHost *:80>
    ServerName your-pool.com
    ServerAdmin admin@your-pool.com
    
    DocumentRoot /var/www/yiimp/yiimp2/web
    
    <Directory /var/www/yiimp/yiimp2/web>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
        
        # Enable URL rewriting
        RewriteEngine on
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteRule . index.php
    </Directory>
    
    # Logging
    ErrorLog ${APACHE_LOG_DIR}/yiimp2-error.log
    CustomLog ${APACHE_LOG_DIR}/yiimp2-access.log combined
</VirtualHost>
```

```bash
# Enable required Apache modules
sudo a2enmod rewrite
sudo a2enmod headers
sudo a2enmod expires

# Enable site
sudo a2ensite yiimp2.conf

# Restart Apache
sudo systemctl restart apache2
```

#### Nginx Configuration (Alternative)

```bash
# Create Nginx configuration
sudo nano /etc/nginx/sites-available/yiimp2
```

```nginx
server {
    listen 80;
    server_name your-pool.com;
    root /var/www/yiimp/yiimp2/web;
    index index.php;
    
    access_log /var/log/nginx/yiimp2-access.log;
    error_log /var/log/nginx/yiimp2-error.log;
    
    location / {
        try_files $uri $uri/ /index.php?$args;
    }
    
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
    
    location ~ /\.(ht|git|svn) {
        deny all;
    }
}
```

```bash
# Enable site
sudo ln -s /etc/nginx/sites-available/yiimp2 /etc/nginx/sites-enabled/

# Test configuration
sudo nginx -t

# Restart Nginx
sudo systemctl restart nginx
```

### 8. Configure SSL/TLS (Recommended)

```bash
# Install Certbot
sudo apt install -y certbot python3-certbot-apache

# Obtain SSL certificate
sudo certbot --apache -d your-pool.com

# Auto-renewal is configured automatically
# Test renewal
sudo certbot renew --dry-run
```

### 9. Configure Memcached

```bash
# Edit Memcached configuration
sudo nano /etc/memcached.conf
```

```
# Increase memory limit
-m 256

# Listen on localhost only
-l 127.0.0.1

# Port
-p 11211
```

```bash
# Restart Memcached
sudo systemctl restart memcached
```

### 10. Set Up Cron Jobs

```bash
# Edit crontab for www-data user
sudo crontab -e -u www-data
```

```cron
# Backend processing scripts
*/1 * * * * cd /var/www/yiimp/web && php run.php
*/5 * * * * cd /var/www/yiimp/web && php loop2.php
*/10 * * * * cd /var/www/yiimp/web && php blocks.php

# Cleanup old data
0 0 * * * cd /var/www/yiimp/web && php cleanup.php

# Update exchange rates
*/15 * * * * cd /var/www/yiimp/web && php exchange.php
```

## Post-Installation

### 1. Verify Installation

```bash
# Check PHP version and extensions
php -v
php -m | grep -E 'bcmath|curl|gd|intl|memcache|mysqli|opcache|mbstring|xml|zip'

# Check database connection
mysql -u yaamp_web -p yaamp -e "SHOW TABLES;"

# Check Memcached
echo "stats" | nc localhost 11211

# Check web server
curl -I http://localhost
```

### 2. Access Application

Open your browser and navigate to:
- **Homepage**: `https://your-pool.com/`
- **Admin Panel**: `https://your-pool.com/admin/dashboard`
- **API Status**: `https://your-pool.com/api/status`

### 3. Initial Configuration

1. **Add Coins**: Navigate to Admin → Coin Wallets → Add Coin
2. **Configure Algorithms**: Set up stratum ports for each algorithm
3. **Test RPC Connections**: Verify coin daemon connectivity
4. **Configure Payouts**: Set minimum payout thresholds
5. **Enable Monitoring**: Set up log monitoring and alerts

## Troubleshooting

### Common Issues

#### 1. Database Connection Error
```bash
# Check MySQL is running
sudo systemctl status mariadb

# Verify credentials
mysql -u yaamp_web -p yaamp

# Check PHP MySQL extension
php -m | grep mysqli
```

#### 2. Permission Errors
```bash
# Fix permissions
sudo chown -R www-data:www-data /var/www/yiimp
sudo chmod -R 755 /var/www/yiimp
sudo chmod -R 777 /var/www/yiimp/yiimp2/runtime
sudo chmod -R 777 /var/www/yiimp/log
```

#### 3. Memcached Not Working
```bash
# Check Memcached is running
sudo systemctl status memcached

# Test connection
telnet localhost 11211

# Check PHP extension
php -m | grep memcache
```

#### 4. 404 Errors
```bash
# Apache: Enable mod_rewrite
sudo a2enmod rewrite
sudo systemctl restart apache2

# Nginx: Check try_files directive
# Verify .htaccess or nginx config
```

### Debug Mode

Enable debug mode for troubleshooting:

```php
// yiimp2/web/index.php
defined('YII_DEBUG') or define('YII_DEBUG', true);
defined('YII_ENV') or define('YII_ENV', 'dev');
```

**Important**: Disable debug mode in production!

## Security Hardening

1. **Disable debug mode** in production
2. **Set strong passwords** for database users
3. **Configure firewall** to restrict access
4. **Enable HTTPS** with valid SSL certificate
5. **Restrict admin access** by IP address
6. **Keep software updated** (PHP, MySQL, OS packages)
7. **Regular backups** of database and configuration
8. **Monitor logs** for suspicious activity

## Backup and Restore

### Backup

```bash
# Database backup
mysqldump -u root -p yaamp > yaamp_backup_$(date +%Y%m%d).sql

# Configuration backup
tar -czf config_backup_$(date +%Y%m%d).tar.gz /etc/yiimp/

# Application backup
tar -czf yiimp2_backup_$(date +%Y%m%d).tar.gz /var/www/yiimp/yiimp2/
```

### Restore

```bash
# Restore database
mysql -u root -p yaamp < yaamp_backup_20240101.sql

# Restore configuration
tar -xzf config_backup_20240101.tar.gz -C /

# Restore application
tar -xzf yiimp2_backup_20240101.tar.gz -C /var/www/yiimp/
```

## Upgrading

### From Legacy Yiimp to Yiimp2

1. **Backup everything** (database, configuration, files)
2. **Install Yiimp2** alongside legacy Yiimp
3. **Configure Yiimp2** to use same database
4. **Test thoroughly** before switching
5. **Update web server** configuration to point to Yiimp2
6. **Monitor** for issues after migration

### Updating Yiimp2

```bash
# Pull latest changes
cd /var/www/yiimp
git pull origin main

# Update dependencies
cd yiimp2
composer install --no-dev --optimize-autoloader

# Run database migrations (if any)
./yii migrate

# Clear cache
./yii cache/flush-all

# Restart web server
sudo systemctl restart apache2
```

## Support

For issues and questions:
- **Documentation**: Check this guide and other docs in the repository
- **GitHub Issues**: Report bugs and request features
- **Community**: Join the mining pool community forums

## License

See LICENSE file in the repository root.
