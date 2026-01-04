# Container Property Tests

## Overview

This directory contains property-based tests for the Yiimp2 container infrastructure. These tests verify that the containerized deployment meets the requirements specified in the design document.

## Test Files

### PhpVersionPropertyTest.php
**Property 54: Yiimp2 container PHP version**
- Validates: Requirements 16.1
- Tests that PHP version is 8.2 or higher
- Tests that required PHP extensions are loaded
- Tests that Composer and dependencies are installed

**Status**: ✅ PASSING

### DatabaseConnectivityPropertyTest.php
**Property 55: Container database connectivity**
- Validates: Requirements 16.7
- Tests database connection establishment
- Tests database query execution
- Tests access to shared database tables
- Tests database transactions
- Tests database charset configuration

**Status**: ⚠️ REQUIRES CONTAINER ENVIRONMENT
- These tests require a running database instance
- Designed to run inside the container where database is accessible
- Will fail in development environment without database

### ConfigurationSharingPropertyTest.php
**Property 56: Container configuration sharing**
- Validates: Requirements 16.8
- Tests that serverconfig.php is accessible at /etc/yiimp
- Tests that configuration constants are defined
- Tests that database configuration matches between containers
- Tests that log directories are accessible
- Tests configuration file permissions

**Status**: ⚠️ REQUIRES CONTAINER ENVIRONMENT
- These tests require container volume mounts
- Designed to run inside the container where /etc/yiimp is mounted
- Will fail in development environment without volume mounts

### ProcessIsolationPropertyTest.php
**Property 57: Yiimp2 container process isolation**
- Validates: Requirements 16.9, 16.10
- Tests that backend scripts are not running
- Tests that stratum servers are not running
- Tests that only web server processes are running
- Tests that memcached is running
- Tests supervisor configuration
- Tests that application runs from /var/yiimp2
- Tests port configuration

**Status**: ⚠️ REQUIRES CONTAINER ENVIRONMENT
- These tests require running inside the Yiimp2 container
- Designed to verify process isolation in containerized environment
- Will fail in development environment on host system

## Running Tests

### In Development Environment (Host)

```bash
# Run all container tests (some will fail without container environment)
vendor/bin/codecept run unit container

# Run specific test
vendor/bin/codecept run unit container/PhpVersionPropertyTest
```

### In Container Environment

```bash
# Build and run Yiimp2 container
make build-yiimp2
make run-yiimp2

# Run tests inside container
make test-yiimp2-unit

# Or run specific container tests
podman exec -it yiimp2 bash -c "cd /var/yiimp2 && vendor/bin/codecept run unit container"
```

## Test Results Interpretation

### PHP Version Test
- ✅ Should pass in both development and container environments
- Verifies PHP 8.2+ requirement is met
- Verifies required extensions are available

### Database Connectivity Test
- ⚠️ Requires database connection
- Will fail in development without database
- Should pass in container with proper database configuration
- Verifies both containers can access same database

### Configuration Sharing Test
- ⚠️ Requires container volume mounts
- Will fail in development without /etc/yiimp mount
- Should pass in container with proper volume configuration
- Verifies configuration is shared between containers

### Process Isolation Test
- ⚠️ Requires container environment
- Will fail in development (host has many processes)
- Should pass in Yiimp2 container
- Verifies only web processes run in Yiimp2 container
- Verifies backend scripts and stratum servers are disabled

## Expected Test Results

### Development Environment (Host)
```
✅ PhpVersionPropertyTest: All tests pass
⚠️ DatabaseConnectivityPropertyTest: Fails (no database)
⚠️ ConfigurationSharingPropertyTest: Fails (no volume mounts)
⚠️ ProcessIsolationPropertyTest: Fails (host environment)
```

### Container Environment (Yiimp2)
```
✅ PhpVersionPropertyTest: All tests pass
✅ DatabaseConnectivityPropertyTest: All tests pass
✅ ConfigurationSharingPropertyTest: All tests pass
✅ ProcessIsolationPropertyTest: All tests pass
```

## Troubleshooting

### Database Connection Errors
If database connectivity tests fail in container:
1. Verify database is running and accessible
2. Check serverconfig.php has correct database credentials
3. Verify database host is reachable from container
4. Test connection manually: `podman exec -it yiimp2 mysql -h DBHOST -u DBUSER -p`

### Configuration File Not Found
If configuration sharing tests fail in container:
1. Verify volume mount: `-v ./config:/etc/yiimp`
2. Check serverconfig.php exists in ./config directory
3. Verify file permissions allow reading
4. Check container logs for mount errors

### Process Isolation Failures
If process isolation tests fail in container:
1. Verify supervisord.conf.yiimp2 is being used
2. Check supervisor status: `podman exec -it yiimp2 supervisorctl status`
3. Verify backend scripts have autostart=false
4. Verify stratum servers have autostart=false
5. Check that Apache and memcached are running

### PHP Version Errors
If PHP version tests fail:
1. Verify container is built from Ubuntu 24.04 base
2. Check PHP version: `podman exec -it yiimp2 php -v`
3. Verify PHP extensions: `podman exec -it yiimp2 php -m`
4. Rebuild container if needed: `make build-yiimp2`

## Integration with CI/CD

These tests can be integrated into CI/CD pipelines:

```yaml
# Example GitLab CI configuration
test-container:
  stage: test
  script:
    - make build-yiimp2
    - make run-yiimp2
    - make test-yiimp2-unit
  only:
    - merge_requests
    - main
```

## Additional Resources

- [Container Deployment Guide](../../../../CONTAINER_DEPLOYMENT.md)
- [Yiimp2 Installation Guide](../../../INSTALLATION.md)
- [Migration Guide](../../../MIGRATION_GUIDE.md)
- [Requirements Document](../../../../.kiro/specs/yiimp-to-yiimp2-migration/requirements.md)
- [Design Document](../../../../.kiro/specs/yiimp-to-yiimp2-migration/design.md)
