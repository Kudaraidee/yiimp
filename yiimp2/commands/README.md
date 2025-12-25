# Yiimp2 Console Commands

This directory contains console commands for backend script integration with Yiimp2.

## Overview

The Yii2 console application allows backend scripts to access Yiimp2 models and components. This enables:

- Payout processing using Yiimp2 database models
- Exchange operations using Yiimp2 RPC components
- Coin management using Yiimp2 models
- Any other backend operations that need database or component access

## Configuration

The console application is configured in `config/console.php` and includes:

- Database connection (same as web application)
- Cache component (Memcache or FileCache)
- All custom components (YiimpUtils, ConversionUtils, ExplorerUtils, RpcClient)
- Logging configuration

## Entry Point

The console entry point is `yiimp2/yii` (executable PHP script).

## Usage

### Basic Command Execution

```bash
# From yiimp2 directory
./yii <controller>/<action>

# Or using PHP directly
php yii <controller>/<action>
```

### Test Commands

Several test commands are provided to verify backend integration:

#### General Tests

```bash
# Test all integration
./yii test/all

# Test database configuration
./yii test/database

# Test model access
./yii test/models

# Test component access
./yii test/components
```

#### Payout Script Tests

```bash
# Test payout model access
./yii payout-test/check

# Test payment processing workflow
./yii payout-test/process

# Test payout model relations
./yii payout-test/relations
```

#### Exchange Script Tests

```bash
# Test exchange model access
./yii exchange-test/check

# Test RPC component access
./yii exchange-test/rpc

# Test market data access
./yii exchange-test/markets

# Test balance tracking
./yii exchange-test/balances
```

#### Coin Management Tests

```bash
# Test coin model access
./yii coin-test/check

# Test coin status operations
./yii coin-test/status

# Test blockchain data access
./yii coin-test/blockchain

# Test algorithm and stratum configuration
./yii coin-test/config
```

## Creating Custom Commands

To create a new console command:

1. Create a controller in `commands/` directory
2. Extend `yii\console\Controller`
3. Use namespace `app\commands`
4. Create action methods (e.g., `actionMyAction()`)

Example:

```php
<?php
namespace app\commands;

use yii\console\Controller;
use yii\console\ExitCode;
use app\models\Coins;

class MyController extends Controller
{
    public function actionIndex()
    {
        // Access models
        $coins = Coins::find()->all();
        
        // Access components
        $utils = \Yii::$app->YiimpUtils;
        
        // Return exit code
        return ExitCode::OK;
    }
}
```

## Integration with Legacy Backend Scripts

Legacy backend scripts (in `web/` directory) can be migrated to use Yiimp2 by:

1. Creating a new console controller in `yiimp2/commands/`
2. Porting the logic to use Yiimp2 models instead of Yii1 models
3. Using Yii2 components instead of legacy functions
4. Calling the new command from shell scripts

Example migration:

**Legacy (web/yaamp/commands/PayoutCommand.php):**
```php
class PayoutCommand extends CConsoleCommand
{
    public function run($args)
    {
        $coin = dboscalar("SELECT * FROM coins WHERE symbol=:symbol", [':symbol' => $symbol]);
        // ... legacy code
    }
}
```

**Yiimp2 (yiimp2/commands/PayoutController.php):**
```php
namespace app\commands;

use yii\console\Controller;
use app\models\Coins;

class PayoutController extends Controller
{
    public function actionProcess($symbol)
    {
        $coin = Coins::find()->where(['symbol' => $symbol])->one();
        // ... Yii2 code
    }
}
```

## Deployment

In production, ensure:

1. `/etc/yiimp/serverconfig.php` exists and is readable
2. Database credentials are configured correctly
3. Memcache is available (if configured)
4. Log directory is writable
5. The `yii` script is executable (`chmod +x yii`)

## Logging

Console commands log to separate files:

- `yiimp2-console-error.log` - Errors and warnings
- `yiimp2-console-info.log` - Info messages
- `yiimp2-console-db.log` - Database errors

Log location is configured in `config/console.php` and defaults to `YIIMP_LOGS` directory.

## Troubleshooting

### "Failed to open stream: /etc/yiimp/serverconfig.php"

This is expected in development. In production, ensure the file exists and is readable.

### "Class not found" errors

Ensure you've run `composer install` in the `yiimp2/` directory.

### Database connection errors

Check database credentials in `/etc/yiimp/serverconfig.php` or `config/db.php`.

### Component not found

Ensure the component is registered in `config/console.php` under the `components` section.
