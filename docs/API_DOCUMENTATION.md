# Yiimp2 API Documentation

## Overview

The Yiimp2 API provides programmatic access to pool statistics, wallet information, and mining data. All endpoints return JSON responses.

## Base URL

```
https://your-pool.com/api
```

## Authentication

Currently, all API endpoints are public and do not require authentication. Rate limiting may be applied to prevent abuse.

## Response Format

All API responses follow this general structure:

### Success Response
```json
{
    "field1": "value1",
    "field2": "value2",
    ...
}
```

### Error Response
```json
{
    "error": "Error message describing what went wrong"
}
```

## Endpoints

### 1. Pool Status

Get current pool statistics for all algorithms.

**Endpoint**: `GET /api/status`

**Parameters**: None

**Response**:
```json
{
    "sha256": {
        "name": "SHA256",
        "port": 3333,
        "coins": 5,
        "fees": 0.5,
        "fees_solo": 0.5,
        "hashrate": 1234567890,
        "hashrate_shared": 1000000000,
        "workers": 150,
        "workers_shared": 120,
        "workers_solo": 30,
        "estimate_current": "0.00001234",
        "estimate_last24h": "0.00001200",
        "actual_last24h": "0.00001180",
        "mbtc_mh_factor": 1000.0,
        "hashrate_last24h": 1200000000.0
    },
    "scrypt": {
        "name": "Scrypt",
        "port": 3433,
        ...
    },
    ...
}
```

**Field Descriptions**:
- `name`: Algorithm display name
- `port`: Stratum port for this algorithm
- `coins`: Number of coins using this algorithm
- `fees`: Pool fee percentage for shared mining
- `fees_solo`: Pool fee percentage for solo mining
- `hashrate`: Current total hashrate (hashes/second)
- `hashrate_shared`: Current shared mining hashrate
- `workers`: Total number of active workers
- `workers_shared`: Number of workers in shared mode
- `workers_solo`: Number of workers in solo mode
- `estimate_current`: Current profitability estimate (BTC/MH/day)
- `estimate_last24h`: 24-hour average profitability estimate
- `actual_last24h`: Actual earnings in last 24 hours
- `mbtc_mh_factor`: Conversion factor for profitability calculations
- `hashrate_last24h`: Average hashrate over last 24 hours

**Example Request**:
```bash
curl https://your-pool.com/api/status
```

**Example Response**:
```json
{
    "sha256": {
        "name": "SHA256",
        "port": 3333,
        "coins": 3,
        "fees": 0.5,
        "fees_solo": 0.5,
        "hashrate": 5000000000000,
        "hashrate_shared": 4500000000000,
        "workers": 250,
        "workers_shared": 220,
        "workers_solo": 30,
        "estimate_current": "0.00000850",
        "estimate_last24h": "0.00000820",
        "actual_last24h": "0.00000800",
        "mbtc_mh_factor": 1000.0,
        "hashrate_last24h": 4800000000000.0
    }
}
```

---

### 2. Wallet Statistics

Get statistics for a specific wallet address.

**Endpoint**: `GET /api/wallet`

**Parameters**:
- `address` (required): Wallet address to query

**Response**:
```json
{
    "address": "1A1zP1eP5QGefi2DMPTfTL5SLmv7DivfNa",
    "balance": 1.23456789,
    "unpaid": 0.12345678,
    "paid_total": 10.5,
    "paid_24h": 0.5,
    "hashrate": 1000000000,
    "hashrate_24h": 950000000,
    "workers": 5,
    "workers_online": 4,
    "workers_offline": 1,
    "last_share": 1640000000,
    "blocks_found": 3,
    "blocks_found_24h": 1
}
```

**Field Descriptions**:
- `address`: Wallet address queried
- `balance`: Current unpaid balance
- `unpaid`: Same as balance (for compatibility)
- `paid_total`: Total amount paid out all time
- `paid_24h`: Amount paid in last 24 hours
- `hashrate`: Current hashrate (hashes/second)
- `hashrate_24h`: Average hashrate over last 24 hours
- `workers`: Total number of workers
- `workers_online`: Number of currently active workers
- `workers_offline`: Number of inactive workers
- `last_share`: Unix timestamp of last share submission
- `blocks_found`: Total blocks found by this wallet
- `blocks_found_24h`: Blocks found in last 24 hours

**Example Request**:
```bash
curl "https://your-pool.com/api/wallet?address=1A1zP1eP5QGefi2DMPTfTL5SLmv7DivfNa"
```

**Error Response** (Invalid/Unknown Address):
```json
{
    "error": "Wallet not found"
}
```

---

### 3. Currency Information

Get exchange rates and profitability data for all pool coins.

**Endpoint**: `GET /api/currency`

**Parameters**: None

**Response**:
```json
{
    "BTC": {
        "name": "Bitcoin",
        "symbol": "BTC",
        "algo": "sha256",
        "price": 45000.00,
        "price_btc": 1.0,
        "volume_24h": 1000000.0,
        "market_cap": 850000000000.0,
        "difficulty": 25000000000000.0,
        "block_reward": 6.25,
        "block_time": 600,
        "network_hashrate": 150000000000000000000,
        "profitability": 0.00000850,
        "profitability_24h": 0.00000820,
        "enabled": true,
        "visible": true
    },
    "LTC": {
        "name": "Litecoin",
        "symbol": "LTC",
        "algo": "scrypt",
        ...
    },
    ...
}
```

**Field Descriptions**:
- `name`: Coin full name
- `symbol`: Coin ticker symbol
- `algo`: Mining algorithm
- `price`: Current price in USD
- `price_btc`: Current price in BTC
- `volume_24h`: 24-hour trading volume
- `market_cap`: Market capitalization
- `difficulty`: Current network difficulty
- `block_reward`: Current block reward
- `block_time`: Average time between blocks (seconds)
- `network_hashrate`: Network hashrate
- `profitability`: Current profitability (BTC/MH/day)
- `profitability_24h`: 24-hour average profitability
- `enabled`: Whether coin is enabled for mining
- `visible`: Whether coin is visible on pool

**Example Request**:
```bash
curl https://your-pool.com/api/currency
```

---

### 4. Block Information

Get information about recently found blocks.

**Endpoint**: `GET /api/blocks`

**Parameters**:
- `limit` (optional): Number of blocks to return (default: 50, max: 100)

**Response**:
```json
[
    {
        "height": 700000,
        "blockhash": "00000000000000000008a89e7d5b8f0b6e5c3d2a1f9e8d7c6b5a4938271605f4",
        "coin": "BTC",
        "coin_name": "Bitcoin",
        "amount": 6.25,
        "difficulty": 25000000000000.0,
        "time": 1640000000,
        "algo": "sha256",
        "category": "generate",
        "confirmations": 120,
        "status": "confirmed",
        "finder": "1A1zP1eP5QGefi2DMPTfTL5SLmv7DivfNa",
        "worker": "worker1"
    },
    {
        "height": 699999,
        ...
    },
    ...
]
```

**Field Descriptions**:
- `height`: Block height
- `blockhash`: Block hash
- `coin`: Coin symbol
- `coin_name`: Coin full name
- `amount`: Block reward amount
- `difficulty`: Block difficulty
- `time`: Unix timestamp when block was found
- `algo`: Mining algorithm
- `category`: Block category (generate, immature, orphan)
- `confirmations`: Number of confirmations
- `status`: Block status (pending, confirmed, orphaned)
- `finder`: Wallet address that found the block
- `worker`: Worker name that found the block

**Example Request**:
```bash
curl "https://your-pool.com/api/blocks?limit=10"
```

---

## Error Codes

| HTTP Status | Description |
|-------------|-------------|
| 200 | Success |
| 400 | Bad Request - Invalid parameters |
| 404 | Not Found - Resource doesn't exist |
| 429 | Too Many Requests - Rate limit exceeded |
| 500 | Internal Server Error |

## Rate Limiting

To prevent abuse, the API implements rate limiting:
- **Default**: 60 requests per minute per IP address
- **Burst**: Up to 10 requests in quick succession

When rate limit is exceeded, the API returns HTTP 429 with:
```json
{
    "error": "Rate limit exceeded. Please try again later."
}
```

## Best Practices

1. **Cache Responses**: Cache API responses on your end to reduce requests
2. **Use Appropriate Intervals**: Don't poll more frequently than necessary
   - Pool status: Every 60 seconds
   - Wallet stats: Every 30-60 seconds
   - Currency info: Every 5-10 minutes
   - Blocks: Every 60 seconds
3. **Handle Errors Gracefully**: Implement proper error handling
4. **Respect Rate Limits**: Implement exponential backoff on errors

## Example Implementations

### JavaScript (Browser)

```javascript
// Get pool status
async function getPoolStatus() {
    try {
        const response = await fetch('https://your-pool.com/api/status');
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        const data = await response.json();
        console.log('Pool Status:', data);
        return data;
    } catch (error) {
        console.error('Error fetching pool status:', error);
    }
}

// Get wallet statistics
async function getWalletStats(address) {
    try {
        const response = await fetch(`https://your-pool.com/api/wallet?address=${address}`);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        const data = await response.json();
        
        if (data.error) {
            console.error('API Error:', data.error);
            return null;
        }
        
        console.log('Wallet Stats:', data);
        return data;
    } catch (error) {
        console.error('Error fetching wallet stats:', error);
    }
}

// Usage
getPoolStatus();
getWalletStats('1A1zP1eP5QGefi2DMPTfTL5SLmv7DivfNa');
```

### Python

```python
import requests
import time

class YiimpAPI:
    def __init__(self, base_url):
        self.base_url = base_url.rstrip('/')
        self.session = requests.Session()
    
    def get_pool_status(self):
        """Get pool status for all algorithms"""
        try:
            response = self.session.get(f'{self.base_url}/api/status')
            response.raise_for_status()
            return response.json()
        except requests.exceptions.RequestException as e:
            print(f'Error: {e}')
            return None
    
    def get_wallet_stats(self, address):
        """Get statistics for a wallet address"""
        try:
            response = self.session.get(
                f'{self.base_url}/api/wallet',
                params={'address': address}
            )
            response.raise_for_status()
            data = response.json()
            
            if 'error' in data:
                print(f'API Error: {data["error"]}')
                return None
            
            return data
        except requests.exceptions.RequestException as e:
            print(f'Error: {e}')
            return None
    
    def get_currency_info(self):
        """Get currency information and profitability"""
        try:
            response = self.session.get(f'{self.base_url}/api/currency')
            response.raise_for_status()
            return response.json()
        except requests.exceptions.RequestException as e:
            print(f'Error: {e}')
            return None
    
    def get_blocks(self, limit=50):
        """Get recent blocks"""
        try:
            response = self.session.get(
                f'{self.base_url}/api/blocks',
                params={'limit': limit}
            )
            response.raise_for_status()
            return response.json()
        except requests.exceptions.RequestException as e:
            print(f'Error: {e}')
            return None

# Usage
api = YiimpAPI('https://your-pool.com')

# Get pool status
status = api.get_pool_status()
if status:
    print(f'SHA256 hashrate: {status["sha256"]["hashrate"]}')

# Get wallet stats
wallet = api.get_wallet_stats('1A1zP1eP5QGefi2DMPTfTL5SLmv7DivfNa')
if wallet:
    print(f'Balance: {wallet["balance"]} BTC')
    print(f'Hashrate: {wallet["hashrate"]} H/s')

# Get recent blocks
blocks = api.get_blocks(limit=10)
if blocks:
    print(f'Latest block: {blocks[0]["height"]}')
```

### PHP

```php
<?php

class YiimpAPI {
    private $baseUrl;
    
    public function __construct($baseUrl) {
        $this->baseUrl = rtrim($baseUrl, '/');
    }
    
    public function getPoolStatus() {
        return $this->request('/api/status');
    }
    
    public function getWalletStats($address) {
        return $this->request('/api/wallet', ['address' => $address]);
    }
    
    public function getCurrencyInfo() {
        return $this->request('/api/currency');
    }
    
    public function getBlocks($limit = 50) {
        return $this->request('/api/blocks', ['limit' => $limit]);
    }
    
    private function request($endpoint, $params = []) {
        $url = $this->baseUrl . $endpoint;
        
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            return ['error' => "HTTP Error: $httpCode"];
        }
        
        return json_decode($response, true);
    }
}

// Usage
$api = new YiimpAPI('https://your-pool.com');

// Get pool status
$status = $api->getPoolStatus();
if (isset($status['sha256'])) {
    echo "SHA256 hashrate: " . $status['sha256']['hashrate'] . "\n";
}

// Get wallet stats
$wallet = $api->getWalletStats('1A1zP1eP5QGefi2DMPTfTL5SLmv7DivfNa');
if (!isset($wallet['error'])) {
    echo "Balance: " . $wallet['balance'] . " BTC\n";
    echo "Hashrate: " . $wallet['hashrate'] . " H/s\n";
}
```

## Support

For API issues or questions:
- Check this documentation
- Review example implementations
- Report bugs on GitHub
- Contact pool administrator

## Changelog

### Version 2.0 (Current)
- Initial Yiimp2 API release
- All endpoints return JSON
- Improved error handling
- Added rate limiting

### Future Enhancements
- API authentication for write operations
- WebSocket support for real-time updates
- Additional endpoints for detailed statistics
- API versioning
