# Migration Guide: Legacy Yiimp to Yiimp2

## Overview

This guide provides step-by-step instructions for migrating from the legacy Yiimp (Yii Framework 1.x) to Yiimp2 (Yii Framework 2.x).

## Why Migrate?

- **Modern Framework**: Yii2 provides better performance, security, and maintainability
- **Improved Architecture**: Better separation of concerns and code organization
- **Enhanced Features**: Modern UI components, better caching, improved error handling
- **Active Development**: Yii2 is actively maintained with regular security updates
- **Backward Compatible**: Uses same database schema, no data migration needed

## Prerequisites

Before starting the migration:

1. **Backup Everything**:
   - Database (full dump)
   - Configuration files
   - Custom modifications
   - Log files

2. **Test Environment**:
   - Set up a test server
   - Clone production database
   - Test migration process before production

3. **Downtime Planning**:
   - Schedule maintenance window
   - Notify users in advance
   - Prepare rollback plan

## Migration Strategy

### Option 1: Side-by-Side Migration (Recommended)

Run Yiimp2 alongside legacy Yiimp, then switch over when ready.

**Advantages**:
- Zero downtime
- Easy rollback
- Thorough testing possible

**Process**:
1. Install Yiimp2 on same server (different web root)
2. Configure Yiimp2 to use same database
3. Test Yiimp2 thoroughly
4. Switch web server configuration to Yiimp2
5. Keep legacy Yiimp as backup

### Option 2: Direct Replacement

Replace legacy Yiimp directly with Yiimp2.

**Advantages**:
- Simpler process
- Clean installation

**Disadvantages**:
- Requires downtime
- Harder to rollback

## Step-by-Step Migration

### Phase 1: Preparation

#### 1.1 Backup Current System

```bash
# Backup database
mysqldump -u root -p yaamp > yaamp_backup_$(date +%Y%m%d_%H%M%S).sql

# Backup configuration
tar -czf yiimp_config_backup_$(date +%Y%m%d).tar.gz /etc/yiimp/

# Backup web files
tar -czf yiimp_web_backup_$(date +%Y%m%d).tar.gz /var/www/yiimp/web/

# Backup logs
tar -czf yiimp_logs_backup_$(date +%Y%m%d).tar.gz /var/www/yiimp/log/
```

#### 1.2 Document Current Configuration

```bash
# Export current configuration
cat /etc/yiimp/serverconfig.php > current_config.txt

# Document custom modifications
find /var/www/yiimp/web -name "*.php" -newer /var/www/yiimp/web/index.php > custom_files.txt

# List installed coins
mysql -u root -p yaamp -e "SELECT name, symbol, algo, enable FROM coins;" > coins_list.txt
```

#### 1.3 Verify System Requirements

```bash
# Check PHP version (need 8.1+)
php -v

# Check required extensions
php -m | grep -E 'bcmath|curl|gd|intl|memcache|mysqli|opcache|mbstring|xml|zip'

# Check MySQL version
mysql --version

# Check disk space
df -h
```

### Phase 2: Install Yiimp2

#### 2.1 Install Yiimp2 Files

```bash
# Navigate to web root
cd /var/www/yiimp

# If using Git
git pull origin main

# Or download latest release
# wget https://github.com/your-repo/yiimp/archive/refs/heads/main.zip
# unzip main.zip

# Install Composer dependencies
cd yiimp2
composer install --no-dev --optimize-autoloader

# Set permissions
sudo chown -R www-data:www-data /var/www/yiimp/yiimp2
sudo chmod -R 755 /var/www/yiimp/yiimp2
sudo chmod -R 777 /var/www/yiimp/yiimp2/runtime
```

#### 2.2 Configure Yiimp2

```bash
# Yiimp2 uses the same /etc/yiimp/serverconfig.php
# No changes needed if file exists

# Configure Yii2 database connection
nano /var/www/yiimp/yiimp2/config/db.php
```

```php
<?php
return [
    'class' => 'yii\db\Connection',
    'dsn' => 'mysql:host=localhost;dbname=yaamp',
    'username' => 'yaamp_web',
    'password' => 'your_password',
    'charset' => 'utf8mb4',
    'enableSchemaCache' => true,
    'schemaCacheDuration' => 3600,
];
```

#### 2.3 Configure Web Server (Side-by-Side)

**Apache - Add Virtual Host for Yiimp2**:

```bash
sudo nano /etc/apache2/sites-available/yiimp2-test.conf
```

```apache
<VirtualHost *:8080>
    ServerName your-pool.com
    DocumentRoot /var/www/yiimp/yiimp2/web
    
    <Directory /var/www/yiimp/yiimp2/web>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
        
        RewriteEngine on
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteRule . index.php
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/yiimp2-test-error.log
    CustomLog ${APACHE_LOG_DIR}/yiimp2-test-access.log combined
</VirtualHost>
```

```bash
# Enable site
sudo a2ensite yiimp2-test.conf

# Add Listen directive if needed
echo "Listen 8080" | sudo tee -a /etc/apache2/ports.conf

# Restart Apache
sudo systemctl restart apache2
```

### Phase 3: Testing

#### 3.1 Functional Testing

Test all major features:

```bash
# Test homepage
curl -I http://localhost:8080/

# Test API endpoints
curl http://localhost:8080/api/status
curl "http://localhost:8080/api/wallet?address=YOUR_TEST_ADDRESS"

# Test admin panel (if accessible)
curl -I http://localhost:8080/admin/dashboard
```

#### 3.2 Database Compatibility Testing

```bash
# Run test script
cd /var/www/yiimp/yiimp2
./yii test/database-compatibility

# Check for any errors in logs
tail -f runtime/logs/app.log
```

#### 3.3 Backend Script Testing

```bash
# Test backend scripts can access Yiimp2 models
cd /var/www/yiimp/web
php -r "require '/var/www/yiimp/yiimp2/vendor/autoload.php'; \
        require '/var/www/yiimp/yiimp2/vendor/yiisoft/yii2/Yii.php'; \
        \$config = require '/var/www/yiimp/yiimp2/config/console.php'; \
        new yii\console\Application(\$config); \
        echo 'Backend integration OK';"
```

#### 3.4 Performance Testing

```bash
# Run load test (using Apache Bench)
ab -n 1000 -c 10 http://localhost:8080/

# Monitor response times
ab -n 100 -c 5 http://localhost:8080/api/status

# Check memory usage
ps aux | grep php
```

#### 3.5 User Acceptance Testing

1. **Test Wallet Statistics**:
   - Enter wallet address
   - Verify balance, hashrate, workers display correctly
   - Check earnings and payout history

2. **Test Mining Information**:
   - View algorithm profitability
   - Check difficulty statistics
   - Verify block explorer works

3. **Test Admin Functions** (if applicable):
   - Coin management
   - User management
   - Payment monitoring

### Phase 4: Cutover

#### 4.1 Pre-Cutover Checklist

- [ ] All tests passed
- [ ] Backup completed
- [ ] Rollback plan documented
- [ ] Users notified of maintenance
- [ ] Monitoring tools ready

#### 4.2 Perform Cutover

```bash
# 1. Put pool in maintenance mode (optional)
# Create maintenance page or use existing

# 2. Stop backend scripts
sudo killall php

# 3. Update web server configuration
sudo nano /etc/apache2/sites-available/yiimp.conf
```

**Update DocumentRoot**:
```apache
# Change from:
DocumentRoot /var/www/yiimp/web

# To:
DocumentRoot /var/www/yiimp/yiimp2/web
```

```bash
# 4. Restart web server
sudo systemctl restart apache2

# 5. Restart backend scripts
# (They will automatically use Yiimp2 models)
cd /var/www/yiimp/web
./main.sh &
./loop2.sh &
./blocks.sh &

# 6. Verify everything is working
curl -I https://your-pool.com/
curl https://your-pool.com/api/status
```

#### 4.3 Post-Cutover Verification

```bash
# Check web server logs
tail -f /var/log/apache2/yiimp-error.log

# Check application logs
tail -f /var/www/yiimp/yiimp2/runtime/logs/app.log

# Monitor database queries
mysql -u root -p yaamp -e "SHOW PROCESSLIST;"

# Check memcached
echo "stats" | nc localhost 11211

# Verify backend scripts are running
ps aux | grep "main.sh\|loop2.sh\|blocks.sh"
```

### Phase 5: Cleanup

#### 5.1 Monitor for Issues

Monitor for 24-48 hours:
- Error logs
- User reports
- Performance metrics
- Database load

#### 5.2 Remove Old Files (After Successful Migration)

```bash
# Keep backup for at least 30 days
# Then remove old Yii1 files
# DO NOT remove /var/www/yiimp/web yet - backend scripts use it

# Remove test virtual host
sudo a2dissite yiimp2-test.conf
sudo systemctl reload apache2
```

## Rollback Procedure

If issues occur, rollback to legacy Yiimp:

```bash
# 1. Update web server configuration
sudo nano /etc/apache2/sites-available/yiimp.conf

# Change DocumentRoot back to:
DocumentRoot /var/www/yiimp/web

# 2. Restart web server
sudo systemctl restart apache2

# 3. Verify legacy Yiimp is working
curl -I https://your-pool.com/

# 4. Restore database if needed
mysql -u root -p yaamp < yaamp_backup_YYYYMMDD_HHMMSS.sql
```

## Common Migration Issues

### Issue 1: Database Connection Errors

**Symptom**: "SQLSTATE[HY000] [2002] No such file or directory"

**Solution**:
```bash
# Check MySQL is running
sudo systemctl status mariadb

# Verify credentials in config/db.php
# Check socket path in php.ini
```

### Issue 2: Permission Errors

**Symptom**: "Failed to open stream: Permission denied"

**Solution**:
```bash
sudo chown -R www-data:www-data /var/www/yiimp/yiimp2
sudo chmod -R 755 /var/www/yiimp/yiimp2
sudo chmod -R 777 /var/www/yiimp/yiimp2/runtime
```

### Issue 3: Missing PHP Extensions

**Symptom**: "Class 'Memcache' not found"

**Solution**:
```bash
# Install missing extension
sudo apt install php8.1-memcache

# Restart web server
sudo systemctl restart apache2
```

### Issue 4: 404 Errors

**Symptom**: All pages return 404

**Solution**:
```bash
# Enable mod_rewrite
sudo a2enmod rewrite

# Check .htaccess exists in web root
ls -la /var/www/yiimp/yiimp2/web/.htaccess

# Restart Apache
sudo systemctl restart apache2
```

### Issue 5: Slow Performance

**Symptom**: Pages load slowly

**Solution**:
```bash
# Enable OPcache
sudo nano /etc/php/8.1/apache2/php.ini

# Set:
opcache.enable=1
opcache.memory_consumption=128

# Restart Apache
sudo systemctl restart apache2

# Verify cache is working
curl https://your-pool.com/admin/memcached
```

## Feature Comparison

| Feature | Legacy Yiimp | Yiimp2 | Notes |
|---------|--------------|--------|-------|
| Database Schema | ✅ | ✅ | Identical |
| Pool Statistics | ✅ | ✅ | Enhanced UI |
| Wallet Dashboard | ✅ | ✅ | Improved layout |
| Block Explorer | ✅ | ✅ | Better performance |
| Admin Panel | ✅ | ✅ | Modern interface |
| API Endpoints | ✅ | ✅ | Same endpoints |
| Rental System | ✅ | ✅ | Unchanged |
| NiceHash Support | ✅ | ✅ | Unchanged |
| Backend Scripts | ✅ | ✅ | Compatible |
| Stratum Server | ✅ | ✅ | No changes needed |

## Post-Migration Optimization

After successful migration:

1. **Enable Production Mode**:
```php
// yiimp2/web/index.php
defined('YII_DEBUG') or define('YII_DEBUG', false);
defined('YII_ENV') or define('YII_ENV', 'prod');
```

2. **Configure Caching**:
```php
// config/web.php
'cache' => [
    'class' => 'yii\caching\MemCache',
    'servers' => [
        ['host' => 'localhost', 'port' => 11211],
    ],
],
```

3. **Optimize Database**:
```sql
-- Add indexes if not present
-- See PERFORMANCE_TESTING.md for recommendations
```

4. **Monitor Performance**:
- Set up application monitoring
- Configure log rotation
- Enable error alerting

## Support

For migration assistance:
- Review this guide thoroughly
- Check GitHub issues for similar problems
- Contact pool administrator
- Join community forums

## Conclusion

The migration from legacy Yiimp to Yiimp2 is straightforward due to database compatibility. The side-by-side approach minimizes risk and allows thorough testing before cutover.

Key success factors:
- Thorough testing in test environment
- Complete backups before migration
- Clear rollback plan
- Monitoring after cutover
- User communication

With proper planning and execution, the migration can be completed with minimal downtime and no data loss.
