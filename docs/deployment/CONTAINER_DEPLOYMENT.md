# Container Deployment Guide

## Overview

This guide documents the dual-container architecture for deploying both legacy Yiimp (Yii1) and modern Yiimp2 (Yii2) applications. The architecture allows both applications to run simultaneously with different PHP versions while sharing the same database and configuration.

## Architecture

### Dual-Container Design

The deployment uses two separate containers:

1. **Legacy Yiimp Container** (PHP 8.1)
   - Runs legacy Yii1 web frontend
   - Runs backend processing scripts (main.sh, loop2.sh, blocks.sh)
   - Runs stratum mining servers
   - Listens on ports 80, 443, 8080

2. **Yiimp2 Container** (PHP 8.2+)
   - Runs modern Yii2 web frontend
   - Web server only (no backend scripts or stratum)
   - Listens on port 8090
   - Requires PHP 8.2+ for Symfony 7.x dependencies

### Shared Resources

Both containers share:
- **Database**: Same MySQL/MariaDB instance
- **Configuration**: Mounted from `./config` to `/etc/yiimp`
- **Logs**: Mounted from `./log` to `/var/log/apache2` and `/var/log/yiimp`

## Building Containers

### Build Legacy Yiimp Container

```bash
# Production build
make build

# Development build (with Xdebug)
make build-devel
```

### Build Yiimp2 Container

```bash
# Production build
make build-yiimp2

# Development build (with Xdebug)
make build-yiimp2-devel
```

## Running Containers

### Run Legacy Yiimp Container

```bash
# Production mode
make run

# Development mode (with volume mounts for live code editing)
make run-devel

# Initialize Let's Encrypt certificates
make run-init-letsencrypt
```

### Run Yiimp2 Container

```bash
# Production mode
make run-yiimp2

# Development mode (with volume mounts for live code editing)
make run-yiimp2-devel
```

## Port Mappings

### Legacy Yiimp Container
- **80**: HTTP web interface
- **443**: HTTPS web interface (with SSL)
- **8080**: Alternative HTTP port
- **3333, 3334, etc.**: Stratum mining ports

### Yiimp2 Container
- **8090**: HTTP web interface (Yii2 frontend)

## Volume Mounts

### Legacy Yiimp Container

```bash
-v ./config/letsencrypt:/etc/letsencrypt    # SSL certificates
-v ./config:/etc/yiimp                       # Configuration files
-v ./log:/var/log/apache2                    # Apache logs
-v ./log:/var/log/yiimp                      # Application logs
-v ./log:/var/www/yaamp/runtime              # Yii1 runtime logs
-v ./config/supervisord.conf:/etc/supervisor/conf.d/supervisord.conf
```

### Yiimp2 Container

```bash
-v ./config:/etc/yiimp                       # Configuration files (shared)
-v ./log:/var/log/apache2                    # Apache logs (shared)
-v ./log:/var/log/yiimp                      # Application logs (shared)
-v ./config/supervisord.conf.yiimp2:/etc/supervisor/conf.d/supervisord.conf
```

### Development Mode Additional Mounts

Legacy Yiimp:
```bash
-v ./web:/var/www/                           # Live code editing for Yii1
-v ./yiimp2:/var/yiimp2/                     # Live code editing for Yii2
```

Yiimp2:
```bash
-v ./yiimp2:/var/yiimp2/                     # Live code editing for Yii2
```

## Configuration Files

### Shared Configuration

Both containers read from `/etc/yiimp/serverconfig.php`:

```php
<?php
// Database configuration (shared by both containers)
define('YIIMP_DBHOST', 'localhost');
define('YIIMP_DBNAME', 'yaamp');
define('YIIMP_DBUSER', 'yaampuser');
define('YIIMP_DBPASSWORD', 'password');

// Other shared configuration...
?>
```

### Container-Specific Configuration

**Legacy Yiimp:**
- `config/supervisord.conf` - Enables all services (web, backend, stratum)
- `config/ports.conf` - Listens on ports 80, 443, 8080
- `config/000-default.conf` - Apache vhost for legacy Yii1

**Yiimp2:**
- `config/supervisord.conf.yiimp2` - Enables only web server and memcached
- `config/ports.conf.yiimp2` - Listens only on port 8090
- `config/001-yiimp2.conf` - Apache vhost for Yii2

## Database Connection

Both containers connect to the same database instance. Configuration options:

### Option 1: Database on Host
```bash
# Use host.docker.internal or host IP in serverconfig.php
define('YIIMP_DBHOST', 'host.docker.internal');
```

### Option 2: Database in Separate Container
```bash
# Use container name or network alias
define('YIIMP_DBHOST', 'mysql-container');

# Run containers on same network
podman network create yiimp-network
podman run --network yiimp-network ...
```

### Option 3: External Database
```bash
# Use external database IP or hostname
define('YIIMP_DBHOST', '192.168.1.100');
```

## Process Isolation

### Legacy Yiimp Container Processes

Enabled in `supervisord.conf`:
- Apache web server (autostart=true)
- Backend scripts: main.sh, loop2.sh, blocks.sh (autostart=true)
- Stratum servers (autostart=true/false per algorithm)
- Memcached (autostart=true)
- Cron (autostart=true)
- HAProxy (autostart=false, enable if needed)

### Yiimp2 Container Processes

Enabled in `supervisord.conf.yiimp2`:
- Apache web server (autostart=true)
- Memcached (autostart=true)
- Backend scripts (autostart=false) - NOT RUNNING
- Stratum servers (autostart=false) - NOT RUNNING
- Cron (autostart=false) - NOT NEEDED

## Testing

### Test Legacy Yiimp Container

```bash
# Run all tests
make test

# Run unit tests only
make test-unit

# Run integration tests
make test-integration

# Run with verbose output
make test-verbose
```

### Test Yiimp2 Container

```bash
# Run all tests
make test-yiimp2

# Run unit tests only
make test-yiimp2-unit

# Run integration tests
make test-yiimp2-integration

# Run with verbose output
make test-yiimp2-verbose
```

## Accessing Containers

### Execute Commands in Legacy Yiimp Container

```bash
# Interactive shell
podman exec -it yiimp bash

# Run specific command
podman exec -it yiimp php /var/www/run.php
```

### Execute Commands in Yiimp2 Container

```bash
# Interactive shell
podman exec -it yiimp2 bash

# Run Yii2 console command
podman exec -it yiimp2 php /var/yiimp2/yii help

# Run tests
podman exec -it yiimp2 bash -c "cd /var/yiimp2 && vendor/bin/codecept run"
```

## Monitoring

### Check Container Status

```bash
# List running containers
podman ps

# Check container logs
podman logs yiimp
podman logs yiimp2

# Follow logs in real-time
podman logs -f yiimp2
```

### Check Supervisor Status

```bash
# Legacy Yiimp container
podman exec -it yiimp supervisorctl -u yiimp -p supervisor -s http://127.0.0.1:8900 status

# Yiimp2 container
podman exec -it yiimp2 supervisorctl -u yiimp -p supervisor -s http://127.0.0.1:8900 status
```

### Check Apache Status

```bash
# Legacy Yiimp
podman exec -it yiimp apachectl status

# Yiimp2
podman exec -it yiimp2 apachectl status
```

## Troubleshooting

### Container Won't Start

1. Check if ports are already in use:
   ```bash
   netstat -tulpn | grep -E ':(80|443|8090|3333)'
   ```

2. Check container logs:
   ```bash
   podman logs yiimp2
   ```

3. Verify configuration files exist:
   ```bash
   ls -la config/supervisord.conf.yiimp2
   ls -la config/ports.conf.yiimp2
   ls -la config/001-yiimp2.conf
   ```

### Database Connection Issues

1. Verify database is accessible:
   ```bash
   podman exec -it yiimp2 mysql -h DBHOST -u DBUSER -p
   ```

2. Check database configuration in serverconfig.php:
   ```bash
   podman exec -it yiimp2 cat /etc/yiimp/serverconfig.php | grep YIIMP_DB
   ```

3. Test database connection from Yii2:
   ```bash
   podman exec -it yiimp2 php /var/yiimp2/yii migrate
   ```

### PHP Version Issues

1. Check PHP version in Yiimp2 container:
   ```bash
   podman exec -it yiimp2 php -v
   ```

2. Verify PHP extensions:
   ```bash
   podman exec -it yiimp2 php -m
   ```

3. Check Composer dependencies:
   ```bash
   podman exec -it yiimp2 bash -c "cd /var/yiimp2 && composer show"
   ```

### Process Isolation Issues

1. Verify only web processes are running in Yiimp2:
   ```bash
   podman exec -it yiimp2 ps aux
   ```

2. Check supervisor configuration:
   ```bash
   podman exec -it yiimp2 cat /etc/supervisor/conf.d/supervisord.conf
   ```

3. Verify backend scripts are not running:
   ```bash
   podman exec -it yiimp2 ps aux | grep -E '(main|loop2|blocks|stratum)'
   ```

## Deployment Scenarios

### Scenario 1: Both Containers on Same Host

```bash
# Build both containers
make build
make build-yiimp2

# Run both containers
make run
make run-yiimp2

# Access legacy Yiimp: http://localhost
# Access Yiimp2: http://localhost:8090
```

### Scenario 2: Gradual Migration

```bash
# Start with legacy container only
make run

# Add Yiimp2 container for testing
make run-yiimp2

# Test Yiimp2 on port 8090
# When ready, switch traffic to Yiimp2
```

### Scenario 3: Development Environment

```bash
# Build development containers
make build-devel
make build-yiimp2-devel

# Run with volume mounts for live editing
make run-devel
make run-yiimp2-devel

# Edit code in ./web or ./yiimp2 directories
# Changes are immediately reflected in containers
```

## Security Considerations

1. **Firewall Rules**: Restrict access to port 8090 if needed
2. **SSL/TLS**: Configure SSL for Yiimp2 if exposing to internet
3. **Database Access**: Use separate database users with minimal privileges
4. **Configuration Files**: Protect serverconfig.php and keys.php
5. **Container Updates**: Regularly rebuild containers with security updates

## Performance Optimization

1. **Memcached**: Both containers use memcached for caching
2. **PHP OpCache**: Enabled in both containers
3. **Apache MPM**: Configured for optimal worker processes
4. **Database Connection Pooling**: Shared database reduces connection overhead

## Backup and Recovery

### Backup Configuration

```bash
# Backup configuration files
tar -czf config-backup.tar.gz config/

# Backup logs
tar -czf logs-backup.tar.gz log/
```

### Backup Database

```bash
# From legacy container
podman exec -it yiimp mysqldump -h DBHOST -u DBUSER -p yaamp > backup.sql

# Or from Yiimp2 container
podman exec -it yiimp2 mysqldump -h DBHOST -u DBUSER -p yaamp > backup.sql
```

### Restore Database

```bash
# To legacy container
podman exec -i yiimp mysql -h DBHOST -u DBUSER -p yaamp < backup.sql

# Or to Yiimp2 container
podman exec -i yiimp2 mysql -h DBHOST -u DBUSER -p yaamp < backup.sql
```

## Upgrading

### Upgrade Legacy Yiimp Container

```bash
# Pull latest code
git pull

# Rebuild container
make build

# Stop old container
podman stop yiimp

# Start new container
make run
```

### Upgrade Yiimp2 Container

```bash
# Pull latest code
git pull

# Update Composer dependencies
cd yiimp2 && composer update

# Rebuild container
make build-yiimp2

# Stop old container
podman stop yiimp2

# Start new container
make run-yiimp2
```

## Additional Resources

- [Yiimp Installation Guide](./yiimp2/INSTALLATION.md)
- [Yiimp2 Migration Guide](./yiimp2/MIGRATION_GUIDE.md)
- [API Documentation](./yiimp2/API_DOCUMENTATION.md)
- [Performance Testing](./yiimp2/PERFORMANCE_TESTING.md)
- [Security Audit](./yiimp2/SECURITY_AUDIT.md)
