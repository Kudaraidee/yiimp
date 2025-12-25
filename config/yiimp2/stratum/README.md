# Yiimp2 Stratum Configuration Files

This directory contains stratum server configuration files for the Yiimp2 dedicated system.

## Configuration Structure

Each stratum configuration file follows this format:

```ini
[TCP]
server = 0.0.0.0          # Listen on all interfaces
port = 3333               # Internal port (Docker maps to external 4XXX)
password = <password>     # Stratum server password

[SQL]
host = yiimp2-db          # Docker service name for database
database = yiimp2         # Yiimp2 dedicated database
username = yiimp2         # Database user
password = <password>     # Database password

[STRATUM]
algo = <algorithm>        # Mining algorithm name
difficulty = <value>      # Starting difficulty
max_ttf = 40000          # Maximum time to find (milliseconds)
```

## Port Mapping

Docker Compose maps internal port 3333 to external ports with +1000 offset:

- SHA256 (low-diff): Internal 3333 → External 4333
- SHA256 (high-diff): Internal 3333 → External 4334
- Scrypt: Internal 3333 → External 4433
- X11: Internal 3333 → External 4533
- X13: Internal 3333 → External 4633
- X15: Internal 3333 → External 4733
- X17: Internal 3333 → External 4833
- Neoscrypt: Internal 3333 → External 4933
- Lyra2v2: Internal 3333 → External 5033
- Equihash: Internal 3333 → External 5133
- Yescrypt: Internal 3333 → External 5233

## Dual Difficulty Configurations

Some algorithms (like SHA256) have dual difficulty configurations:

- **Low Difficulty** (e.g., sha256.conf with diff 128): For small miners and beginners
- **High Difficulty** (e.g., sha256-high.conf with diff 1000000): For ASICs and large mining operations

This allows the pool to efficiently serve both small and large miners on the same algorithm.

## Database Connection

All stratum servers connect to the `yiimp2-db` Docker service, which is the dedicated Yiimp2 database. This ensures complete isolation from the legacy Yiimp system.

**Important:** The database host must be `yiimp2-db` (Docker service name), not `localhost` or an IP address.

## Password Configuration

Before deploying, replace the placeholder passwords:

1. `YIIMP2_DB_PASSWORD`: Replace with actual database password from .env file
2. `yiimp2_stratum_password`: Replace with a secure stratum server password

The `bin/generate-yiimp2-configs.sh` script can automate this replacement.

## Adding New Algorithms

To add a new algorithm:

1. Create a new .conf file named after the algorithm (e.g., `blake2s.conf`)
2. Copy the template structure from an existing config
3. Set the `algo` parameter to the algorithm name
4. Set an appropriate starting difficulty
5. Add a corresponding service in `docker-compose.yml`
6. Map to an available external port (4XXX range)

## Testing

To test a stratum configuration:

```bash
# Build stratum container
docker compose build yiimp2-stratum-sha256

# Start the stratum service
docker compose up -d yiimp2-stratum-sha256

# Check logs
docker compose logs -f yiimp2-stratum-sha256

# Test connection
telnet localhost 4333
```

## Troubleshooting

**Connection refused:**
- Verify database service is running: `docker compose ps yiimp2-db`
- Check database credentials in config file
- Verify network connectivity: `docker compose exec yiimp2-stratum-sha256 ping yiimp2-db`

**Authentication failed:**
- Verify database password matches .env file
- Check database user has proper privileges
- Verify database name is correct (yiimp2)

**Stratum won't start:**
- Check stratum binary compiled successfully
- Verify config file syntax (no extra spaces, proper sections)
- Check container logs for detailed error messages
