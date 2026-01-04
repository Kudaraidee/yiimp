# Development Environment Setup Guide

## Quick Start

```bash
# 1. Stop any running containers
make down

# 2. Start development environment
make up-dev

# 3. Wait for services to start (10-15 seconds)

# 4. Test database connection
./test-dev-db-connection.sh

# 5. Load seed data (test coins)
./load-seed-data.sh

# 6. Access the application
# http://localhost:8090
```

## What Gets Loaded

### Seed Data
The `load-seed-data.sh` script adds:
- ✅ **Bitcoin (BTC)** - SHA256 algorithm, disabled by default
- ✅ **Litecoin (LTC)** - Scrypt algorithm, disabled by default

These are test coins with default RPC settings. You'll need to:
1. Configure proper RPC credentials
2. Enable the coin
3. Start the coin daemon

### Database Structure
- Database: `yiimp2`
- Host: `yiimp2-db-dev` (development)
- User: `yiimp2`
- Password: `yiimp2`

## Accessing the Admin Panel

1. Navigate to: http://localhost:8090/admin
2. Default credentials (from serverconfig.php):
   - Username: `admin`
   - Password: `changeme`
   - Allowed IP: `127.0.0.1`

## Managing Coins

### View Coins
```
http://localhost:8090/admin/coinwallets
```

### Add a New Coin
```
http://localhost:8090/admin/coin-create
```

### Edit a Coin
Click on a coin in the Coin Wallets list, then click "Edit"

## Common Tasks

### Reload Seed Data
```bash
# Clear existing data and reload
docker compose -f docker-compose.dev.yml exec -T yiimp2-db-dev \
    mysql -u yiimp2 -pyiimp2 yiimp2 -e "TRUNCATE TABLE coins"

./load-seed-data.sh
```

### Check Database Contents
```bash
# List all coins
docker compose -f docker-compose.dev.yml exec yiimp2-db-dev \
    mysql -u yiimp2 -pyiimp2 yiimp2 -e "SELECT id, name, symbol, algo, enable FROM coins"

# Count coins
docker compose -f docker-compose.dev.yml exec yiimp2-db-dev \
    mysql -u yiimp2 -pyiimp2 yiimp2 -e "SELECT COUNT(*) as total FROM coins"

# List all algos
docker compose -f docker-compose.dev.yml exec yiimp2-db-dev \
    mysql -u yiimp2 -pyiimp2 yiimp2 -e "SELECT id, name FROM algos LIMIT 10"
```

### View Logs
```bash
# All services
make logs

# Specific service
docker compose -f docker-compose.dev.yml logs yiimp2-web-dev
docker compose -f docker-compose.dev.yml logs yiimp2-db-dev
```

### Restart Services
```bash
# All services
make restart

# Specific service
docker compose -f docker-compose.dev.yml restart yiimp2-web-dev
```

## Troubleshooting

### No Coins Showing
**Problem:** Admin panel shows no coins

**Solution:**
```bash
# Load seed data
./load-seed-data.sh

# Or manually add via SQL
docker compose -f docker-compose.dev.yml exec -T yiimp2-db-dev \
    mysql -u yiimp2 -pyiimp2 yiimp2 < sql/seed-test-data.sql
```

### Database Connection Errors
**Problem:** "getaddrinfo for yiimp2-db failed"

**Solution:**
```bash
# Restart environment
make down
make up-dev

# Verify environment variables
docker compose -f docker-compose.dev.yml exec yiimp2-web-dev env | grep DB_HOST
# Should show: DB_HOST=yiimp2-db-dev
```

### CSRF Validation Errors
**Problem:** "Unable to verify your data submission"

**Solution:**
1. Clear browser cookies for localhost:8090
2. Restart browser
3. Try again

### Empty Database
**Problem:** Database has no tables

**Solution:**
```bash
# Check if init SQL was loaded
docker compose -f docker-compose.dev.yml logs yiimp2-db-dev | grep "init"

# If not, restart with clean volumes
make clean
make up-dev
```

## Development Workflow

### 1. Start Development Session
```bash
make up-dev
./test-dev-db-connection.sh
./load-seed-data.sh
```

### 2. Make Code Changes
- Edit files in `yiimp2/` directory
- Changes are immediately reflected (volume mount)
- No need to rebuild container

### 3. Test Changes
- Refresh browser
- Check logs: `make logs`
- Run tests: `make test-unit`

### 4. Stop Development Session
```bash
make down
```

### 5. Clean Up (removes volumes)
```bash
make clean
```

## Environment Variables

The development environment uses these overrides:

| Variable | Production | Development |
|----------|-----------|-------------|
| `DB_HOST` | `yiimp2-db` | `yiimp2-db-dev` |
| `DB_NAME` | `yiimp2` | `yiimp2` |
| `DB_USER` | `yiimp2` | `yiimp2` |
| `DB_PASSWORD` | `yiimp2` | `yiimp2` |
| `MEMCACHED_HOST` | `yiimp2-memcached` | `yiimp2-memcached-dev` |
| `YIIMP_DEBUG` | `false` | `true` |

## Useful Commands

```bash
# Quick commands
make up-dev          # Start dev environment
make down            # Stop dev environment
make logs            # View all logs
make restart         # Restart all services
make clean           # Stop and remove volumes

# Database commands
./test-dev-db-connection.sh    # Test DB connection
./load-seed-data.sh            # Load test coins

# Testing
make test-unit                 # Run unit tests
make test-integration          # Run integration tests
```

## Next Steps

After setup:
1. ✅ Configure a test coin's RPC settings
2. ✅ Enable the coin
3. ✅ Test adding a new coin via admin panel
4. ✅ Verify CSRF validation works
5. ✅ Start developing your feature

## Related Documentation

- **DATABASE_HOSTNAME_FIX.md** - Database hostname configuration
- **CSRF_ISSUE_RESOLUTION.md** - CSRF validation fixes
- **DOCKER_DEV_CONFIG_FIX.md** - Docker configuration details
- **CSRF_FIX_GUIDE.md** - CSRF troubleshooting guide
