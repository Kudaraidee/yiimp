# NiceHash Views

This directory contains views for the NiceHash integration feature.

## Views

### index.php
Main NiceHash statistics page that displays:
- NiceHash account balance (confirmed and pending)
- Summary statistics (active orders, total workers, total hashrate, total BTC)
- Auto-refreshing order list (refreshes every 30 seconds)

### index_results.php
AJAX partial view that displays the NiceHash orders table with:
- Order ID and algorithm
- BTC balance per order
- Price comparisons (NiceHash service price, Yaamp price, order price)
- Hashrate and worker statistics
- Accepted/rejected shares
- Start/Stop actions for each order

## Features

- **Auto-refresh**: The order list automatically refreshes every 30 seconds
- **Price comparison**: Highlights when Yaamp prices are significantly higher than NiceHash prices
- **Visual indicators**: 
  - Green text: Yaamp price is >10% higher than NiceHash service price
  - Red text: Order price is higher than Yaamp price
- **Admin-only access**: Requires authentication to view

## Configuration

The NiceHash integration requires the following configuration in `serverconfig.php`:

```php
define('YAAMP_USE_NICEHASH_API', true);
define('NICEHASH_API_KEY', 'your-api-key');
define('NICEHASH_API_ID', 'your-api-id');
```

## Requirements

- Requirement 5.2: Track NiceHash order IDs, algorithms, and hashrate
- Requirement 5.3: Display active orders, hashrate, and earnings from NiceHash
