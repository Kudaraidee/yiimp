# Performance Testing Report

## Overview
This document outlines performance testing strategies, benchmarks, and optimization recommendations for the Yiimp2 application.

## Testing Date
Generated: 2024

## 1. Load Testing Strategy

### 1.1 Test Scenarios

#### Scenario 1: Homepage Load
- **Endpoint**: `/`
- **Expected Load**: 100 concurrent users
- **Target Response Time**: < 500ms
- **Test Duration**: 5 minutes

#### Scenario 2: Wallet Statistics
- **Endpoint**: `/site/wallet?address={address}`
- **Expected Load**: 50 concurrent users
- **Target Response Time**: < 1000ms
- **Test Duration**: 5 minutes

#### Scenario 3: API Pool Status
- **Endpoint**: `/api/status`
- **Expected Load**: 200 concurrent users
- **Target Response Time**: < 300ms
- **Test Duration**: 10 minutes

#### Scenario 4: API Wallet Statistics
- **Endpoint**: `/api/wallet?address={address}`
- **Expected Load**: 100 concurrent users
- **Target Response Time**: < 500ms
- **Test Duration**: 10 minutes

#### Scenario 5: AJAX Endpoints
- **Endpoints**: Various AJAX endpoints
- **Expected Load**: 150 concurrent users
- **Target Response Time**: < 200ms
- **Test Duration**: 5 minutes

### 1.2 Load Testing Tools

**Recommended Tools**:
1. **Apache JMeter**: Comprehensive load testing
2. **Gatling**: Scala-based load testing with detailed reports
3. **k6**: Modern load testing tool with JavaScript scripting
4. **Locust**: Python-based load testing

**Example k6 Script**:
```javascript
import http from 'k6/http';
import { check, sleep } from 'k6';

export let options = {
    stages: [
        { duration: '2m', target: 100 }, // Ramp up to 100 users
        { duration: '5m', target: 100 }, // Stay at 100 users
        { duration: '2m', target: 0 },   // Ramp down to 0 users
    ],
    thresholds: {
        http_req_duration: ['p(95)<500'], // 95% of requests under 500ms
    },
};

export default function () {
    // Test homepage
    let res = http.get('http://localhost/');
    check(res, {
        'status is 200': (r) => r.status === 200,
        'response time < 500ms': (r) => r.timings.duration < 500,
    });
    
    sleep(1);
    
    // Test API status
    res = http.get('http://localhost/api/status');
    check(res, {
        'status is 200': (r) => r.status === 200,
        'response time < 300ms': (r) => r.timings.duration < 300,
    });
    
    sleep(1);
}
```

## 2. Database Query Optimization

### 2.1 Slow Query Analysis

**Queries to Monitor**:
1. Wallet statistics aggregation
2. Worker hashrate calculations
3. Earnings summation
4. Block history retrieval
5. Market data queries

**Optimization Techniques**:

#### Query 1: Wallet Statistics
```php
// BEFORE: N+1 query problem
$account = Accounts::findOne(['username' => $address]);
$workers = Workers::find()->where(['userid' => $account->id])->all();
foreach ($workers as $worker) {
    $hashrate += $worker->hashrate; // Note: hashrate is a virtual property
}

// NOTE: hashrate is a virtual property, not a database column
// It cannot be used in SQL aggregation functions like SUM()
// To calculate total hashrate, you must load workers and sum in PHP:
$workers = Workers::find()
    ->where(['userid' => $account->id])
    ->all();
$totalHashrate = array_sum(array_map(function($w) { return $w->hashrate; }, $workers));
```

#### Query 2: Earnings Calculation
```php
// BEFORE: Multiple queries
$earnings = Earnings::find()->where(['userid' => $userId])->all();
$total = 0;
foreach ($earnings as $earning) {
    $total += $earning->amount;
}

// AFTER: Database aggregation
$total = Earnings::find()
    ->where(['userid' => $userId])
    ->sum('amount');
```

#### Query 3: Block History with Coin Info
```php
// BEFORE: N+1 query problem
$blocks = Blocks::find()->where(['userid' => $userId])->all();
foreach ($blocks as $block) {
    $coin = Coins::findOne($block->coin_id); // N queries
}

// AFTER: Eager loading
$blocks = Blocks::find()
    ->with('coin')
    ->where(['userid' => $userId])
    ->all();
```

### 2.2 Index Recommendations

**Critical Indexes**:
```sql
-- Accounts table
CREATE INDEX idx_accounts_username ON accounts(username);
CREATE INDEX idx_accounts_coinid ON accounts(coinid);

-- Workers table
CREATE INDEX idx_workers_userid ON workers(userid);
CREATE INDEX idx_workers_algo ON workers(algo);
CREATE INDEX idx_workers_userid_algo ON workers(userid, algo);

-- Shares table
CREATE INDEX idx_shares_userid ON shares(userid);
CREATE INDEX idx_shares_workerid ON shares(workerid);
CREATE INDEX idx_shares_time ON shares(time);
CREATE INDEX idx_shares_userid_time ON shares(userid, time);

-- Blocks table
CREATE INDEX idx_blocks_userid ON blocks(userid);
CREATE INDEX idx_blocks_coinid ON blocks(coin_id);
CREATE INDEX idx_blocks_time ON blocks(time);
CREATE INDEX idx_blocks_confirmations ON blocks(confirmations);

-- Earnings table
CREATE INDEX idx_earnings_userid ON earnings(userid);
CREATE INDEX idx_earnings_coinid ON earnings(coinid);
CREATE INDEX idx_earnings_status ON earnings(status);
CREATE INDEX idx_earnings_userid_status ON earnings(userid, status);

-- Payouts table
CREATE INDEX idx_payouts_accountid ON payouts(account_id);
CREATE INDEX idx_payouts_coinid ON payouts(coinid);
CREATE INDEX idx_payouts_time ON payouts(time);

-- Markets table
CREATE INDEX idx_markets_coinid ON markets(coinid);
CREATE INDEX idx_markets_lastupdated ON markets(last_updated);

-- Nicehash table
CREATE INDEX idx_nicehash_orderid ON nicehash(orderid);
CREATE INDEX idx_nicehash_algo ON nicehash(algo);
CREATE INDEX idx_nicehash_active ON nicehash(active);
```

### 2.3 Query Profiling

**Enable MySQL Slow Query Log**:
```sql
SET GLOBAL slow_query_log = 'ON';
SET GLOBAL long_query_time = 1; -- Log queries taking > 1 second
SET GLOBAL slow_query_log_file = '/var/log/mysql/slow-query.log';
```

**Analyze Slow Queries**:
```bash
# Use pt-query-digest to analyze slow query log
pt-query-digest /var/log/mysql/slow-query.log

# Or use mysqldumpslow
mysqldumpslow -s t -t 10 /var/log/mysql/slow-query.log
```

## 3. Caching Effectiveness

### 3.1 Memcached Usage

**Current Caching Strategy**:
- Pool statistics cached for 60 seconds
- Coin list cached for 300 seconds
- Market prices cached for 120 seconds
- Worker statistics cached for 30 seconds

**Cache Hit Rate Monitoring**:
```php
// Add cache statistics to admin dashboard
$cache = \Yii::$app->cache;
$stats = $cache->get('cache_stats');

// Monitor:
// - Hit rate (hits / (hits + misses))
// - Miss rate
// - Eviction rate
// - Memory usage
```

**Optimization Recommendations**:

1. **Increase Cache Duration for Static Data**:
```php
// Coin list (changes infrequently)
$coins = $cache->getOrSet('coin_list', function() {
    return Coins::find()->where(['enable' => 1])->all();
}, 600); // 10 minutes instead of 5
```

2. **Implement Cache Warming**:
```php
// Warm cache for popular wallets
public function warmCache()
{
    $popularWallets = $this->getPopularWallets();
    foreach ($popularWallets as $wallet) {
        $this->getWalletStatistics($wallet);
    }
}
```

3. **Use Cache Tags for Invalidation**:
```php
// Tag-based cache invalidation
$cache->set('wallet_' . $address, $stats, 300, new TagDependency([
    'tags' => ['wallet', 'user_' . $userId],
]));

// Invalidate all wallet caches
TagDependency::invalidate($cache, 'wallet');
```

### 3.2 Cache Performance Metrics

**Target Metrics**:
- Cache hit rate: > 80%
- Cache miss rate: < 20%
- Average cache retrieval time: < 1ms
- Memory usage: < 80% of allocated memory

**Monitoring Script**:
```php
// Monitor cache performance
$memcached = new Memcached();
$memcached->addServer('localhost', 11211);

$stats = $memcached->getStats();
foreach ($stats as $server => $serverStats) {
    $hitRate = $serverStats['get_hits'] / ($serverStats['get_hits'] + $serverStats['get_misses']);
    $memoryUsage = $serverStats['bytes'] / $serverStats['limit_maxbytes'];
    
    echo "Hit Rate: " . ($hitRate * 100) . "%\n";
    echo "Memory Usage: " . ($memoryUsage * 100) . "%\n";
}
```

## 4. AJAX Endpoint Performance

### 4.1 AJAX Endpoint Optimization

**Current AJAX Endpoints**:
1. `/site/wallet_results` - Wallet statistics
2. `/site/mining_results` - Mining profitability
3. `/site/current_results` - Pool status
4. `/site/found_results` - Recent blocks

**Optimization Strategies**:

1. **Reduce Payload Size**:
```php
// Return only necessary fields
public function actionWallet_results($address)
{
    $account = Accounts::findOne(['username' => $address]);
    
    // BEFORE: Return full objects
    return [
        'workers' => $account->workers,
        'earnings' => $account->earnings,
    ];
    
    // AFTER: Return minimal data
    return [
        'workers' => Workers::find()
            ->select(['name', 'hashrate', 'difficulty'])
            ->where(['userid' => $account->id])
            ->asArray()
            ->all(),
        'earnings' => Earnings::find()
            ->select(['coinid', 'amount', 'status'])
            ->where(['userid' => $account->id])
            ->asArray()
            ->all(),
    ];
}
```

2. **Implement Pagination**:
```php
// Paginate large result sets
public function actionFound_results()
{
    $page = \Yii::$app->request->get('page', 1);
    $pageSize = 20;
    
    $blocks = Blocks::find()
        ->orderBy(['time' => SORT_DESC])
        ->offset(($page - 1) * $pageSize)
        ->limit($pageSize)
        ->all();
    
    return [
        'blocks' => $blocks,
        'page' => $page,
        'hasMore' => count($blocks) == $pageSize,
    ];
}
```

3. **Use HTTP Caching Headers**:
```php
// Set cache headers for AJAX responses
public function actionCurrent_results()
{
    \Yii::$app->response->headers->set('Cache-Control', 'public, max-age=60');
    \Yii::$app->response->headers->set('Expires', gmdate('D, d M Y H:i:s', time() + 60) . ' GMT');
    
    return $this->getPoolStatus();
}
```

### 4.2 AJAX Performance Targets

**Target Response Times**:
- Wallet results: < 200ms
- Mining results: < 150ms
- Current results: < 100ms
- Found results: < 200ms

**Monitoring**:
```javascript
// Client-side performance monitoring
$.ajax({
    url: '/site/wallet_results',
    data: { address: address },
    beforeSend: function() {
        window.ajaxStartTime = Date.now();
    },
    success: function(data) {
        var duration = Date.now() - window.ajaxStartTime;
        console.log('AJAX request took: ' + duration + 'ms');
        
        // Send to analytics if too slow
        if (duration > 500) {
            sendPerformanceMetric('slow_ajax', duration);
        }
    }
});
```

## 5. Asset Optimization

### 5.1 CSS and JavaScript Optimization

**Current Implementation**:
- Asset bundles configured in `assets/AppAsset.php`
- Minification enabled in production

**Optimization Recommendations**:

1. **Enable Asset Compression**:
```php
// config/web.php
'assetManager' => [
    'bundles' => [
        'yii\web\JqueryAsset' => [
            'sourcePath' => null,
            'js' => ['//code.jquery.com/jquery-3.6.0.min.js'],
        ],
    ],
    'converter' => [
        'class' => 'yii\web\AssetConverter',
        'commands' => [
            'scss' => ['css', 'sass {from} {to} --style compressed'],
            'less' => ['css', 'lessc {from} {to} --compress'],
        ],
    ],
],
```

2. **Implement Asset Versioning**:
```php
// AppAsset.php
class AppAsset extends AssetBundle
{
    public $css = [
        'css/site.css?v=' . YII_VERSION,
    ];
    
    public $js = [
        'js/main.js?v=' . YII_VERSION,
    ];
}
```

3. **Use CDN for Common Libraries**:
```php
// Use CDN for Bootstrap, jQuery, etc.
'assetManager' => [
    'bundles' => [
        'yii\bootstrap5\BootstrapAsset' => [
            'css' => ['https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css'],
        ],
    ],
],
```

### 5.2 Image Optimization

**Recommendations**:
1. Use WebP format for images
2. Implement lazy loading for images
3. Serve responsive images
4. Use image CDN

## 6. Server Configuration

### 6.1 PHP Configuration

**Recommended php.ini Settings**:
```ini
; Memory
memory_limit = 256M

; Execution time
max_execution_time = 30

; OPcache
opcache.enable = 1
opcache.memory_consumption = 128
opcache.interned_strings_buffer = 8
opcache.max_accelerated_files = 10000
opcache.revalidate_freq = 2
opcache.fast_shutdown = 1

; Realpath cache
realpath_cache_size = 4096K
realpath_cache_ttl = 600
```

### 6.2 Apache Configuration

**Recommended Settings**:
```apache
# Enable compression
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/html text/plain text/xml text/css text/javascript application/javascript application/json
</IfModule>

# Enable browser caching
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType image/jpg "access plus 1 year"
    ExpiresByType image/jpeg "access plus 1 year"
    ExpiresByType image/gif "access plus 1 year"
    ExpiresByType image/png "access plus 1 year"
    ExpiresByType text/css "access plus 1 month"
    ExpiresByType application/javascript "access plus 1 month"
</IfModule>

# Enable KeepAlive
KeepAlive On
MaxKeepAliveRequests 100
KeepAliveTimeout 5
```

### 6.3 MySQL Configuration

**Recommended my.cnf Settings**:
```ini
[mysqld]
# InnoDB settings
innodb_buffer_pool_size = 2G
innodb_log_file_size = 256M
innodb_flush_log_at_trx_commit = 2
innodb_flush_method = O_DIRECT

# Query cache (if MySQL < 8.0)
query_cache_type = 1
query_cache_size = 128M
query_cache_limit = 2M

# Connection settings
max_connections = 200
thread_cache_size = 50

# Slow query log
slow_query_log = 1
long_query_time = 1
```

## 7. Performance Monitoring

### 7.1 Application Performance Monitoring (APM)

**Recommended Tools**:
1. **New Relic**: Comprehensive APM solution
2. **Datadog**: Infrastructure and application monitoring
3. **Blackfire.io**: PHP profiling and monitoring
4. **Tideways**: PHP performance monitoring

### 7.2 Custom Performance Metrics

**Implement Custom Metrics**:
```php
// Track response times
class PerformanceMonitor
{
    public static function trackRequest($action, $duration)
    {
        $metrics = [
            'action' => $action,
            'duration' => $duration,
            'memory' => memory_get_peak_usage(true),
            'timestamp' => time(),
        ];
        
        // Log to file or send to monitoring service
        \Yii::info($metrics, 'performance');
    }
}

// Use in controllers
public function actionWallet($address)
{
    $startTime = microtime(true);
    
    // ... action logic ...
    
    $duration = (microtime(true) - $startTime) * 1000;
    PerformanceMonitor::trackRequest('wallet', $duration);
}
```

## 8. Performance Testing Checklist

- [ ] Run load tests for all critical endpoints
- [ ] Analyze slow query log
- [ ] Verify cache hit rates > 80%
- [ ] Check AJAX response times < 200ms
- [ ] Verify database indexes are used
- [ ] Test with realistic data volumes
- [ ] Monitor memory usage under load
- [ ] Test concurrent user scenarios
- [ ] Verify asset compression is working
- [ ] Check server resource utilization

## 9. Performance Benchmarks

### 9.1 Target Benchmarks

| Metric | Target | Critical |
|--------|--------|----------|
| Homepage load time | < 500ms | < 1000ms |
| API response time | < 300ms | < 500ms |
| AJAX response time | < 200ms | < 400ms |
| Database query time | < 100ms | < 200ms |
| Cache hit rate | > 80% | > 60% |
| Concurrent users | 200+ | 100+ |
| Requests per second | 500+ | 200+ |

### 9.2 Performance Testing Schedule

- **Daily**: Automated performance tests in CI/CD
- **Weekly**: Manual load testing
- **Monthly**: Comprehensive performance audit
- **Quarterly**: Capacity planning review

## 10. Optimization Priorities

### High Priority
1. ✅ Implement database query optimization
2. ✅ Verify cache effectiveness
3. ✅ Optimize AJAX endpoints
4. ⚠️ Add database indexes

### Medium Priority
1. ⚠️ Implement asset optimization
2. ⚠️ Configure server optimizations
3. ⚠️ Add performance monitoring

### Low Priority
1. ⚠️ Implement CDN for assets
2. ⚠️ Add image optimization
3. ⚠️ Implement advanced caching strategies

## Conclusion

The Yiimp2 application has a solid foundation for performance, but several optimizations can significantly improve response times and scalability. Focus on database query optimization, caching effectiveness, and AJAX endpoint performance for immediate gains.
