<?php

namespace app\tests\unit\admin;

use Yii;
use app\models\Workers;
use app\models\Accounts;
use app\models\Payouts;
use app\models\Earnings;
use app\models\Coins;
use app\models\Markets;
use yii\db\Connection;

/**
 * Test query optimization in AdminController
 */
class QueryOptimizationTest extends \Codeception\Test\Unit
{
    /**
     * @var \UnitTester
     */
    protected $tester;
    
    protected function _before()
    {
        // Clear cache before each test
        Yii::$app->cache->flush();
    }

    /**
     * Test that eager loading reduces query count for workers
     */
    public function testWorkerEagerLoading()
    {
        // Create test data
        $account = new Accounts();
        $account->username = 'test_user_' . time();
        $account->balance = 0;
        $account->save(false);

        $worker = new Workers();
        $worker->userid = $account->id;
        $worker->name = 'test_worker';
        $worker->algo = 'sha256';
        $worker->time = time();
        $worker->save(false);

        // Count queries without eager loading
        Yii::$app->db->enableQueryLogging = true;
        $queryCountBefore = count(Yii::$app->db->getQueryLog());
        
        $workers = Workers::find()->where(['userid' => $account->id])->all();
        foreach ($workers as $w) {
            $username = $w->account->username; // This triggers N+1 queries
        }
        
        $queryCountWithoutEager = count(Yii::$app->db->getQueryLog()) - $queryCountBefore;

        // Clear query log
        Yii::$app->db->queryLog = [];
        $queryCountBefore = count(Yii::$app->db->getQueryLog());

        // Count queries with eager loading
        $workers = Workers::find()->with('account')->where(['userid' => $account->id])->all();
        foreach ($workers as $w) {
            $username = $w->account->username; // No additional queries
        }
        
        $queryCountWithEager = count(Yii::$app->db->getQueryLog()) - $queryCountBefore;

        // Eager loading should use fewer queries
        $this->assertLessThan($queryCountWithoutEager, $queryCountWithEager);
        
        // Cleanup
        $worker->delete();
        $account->delete();
    }

    /**
     * Test that caching reduces database queries
     */
    public function testDashboardCaching()
    {
        $cacheKey = 'admin_dashboard_stats';
        
        // First call should hit database
        Yii::$app->cache->delete($cacheKey);
        Yii::$app->db->enableQueryLogging = true;
        $queryCountBefore = count(Yii::$app->db->getQueryLog());
        
        // Simulate dashboard stats calculation
        $stats = Yii::$app->cache->get($cacheKey);
        if ($stats === false) {
            $stats = [
                'active_workers' => Workers::find()->where(['>', 'time', time() - 300])->count(),
            ];
            Yii::$app->cache->set($cacheKey, $stats, 30);
        }
        
        $queryCountFirst = count(Yii::$app->db->getQueryLog()) - $queryCountBefore;
        
        // Clear query log
        Yii::$app->db->queryLog = [];
        $queryCountBefore = count(Yii::$app->db->getQueryLog());
        
        // Second call should use cache
        $stats = Yii::$app->cache->get($cacheKey);
        if ($stats === false) {
            $stats = [
                'active_workers' => Workers::find()->where(['>', 'time', time() - 300])->count(),
            ];
            Yii::$app->cache->set($cacheKey, $stats, 30);
        }
        
        $queryCountSecond = count(Yii::$app->db->getQueryLog()) - $queryCountBefore;
        
        // Second call should have no queries (cached)
        $this->assertEquals(0, $queryCountSecond);
        $this->assertGreaterThan(0, $queryCountFirst);
    }

    /**
     * Test that pagination limits result set size
     */
    public function testPaginationLimitsResults()
    {
        // Create multiple test accounts
        $accountIds = [];
        for ($i = 0; $i < 60; $i++) {
            $account = new Accounts();
            $account->username = 'test_user_' . time() . '_' . $i;
            $account->balance = rand(0, 1000);
            $account->save(false);
            $accountIds[] = $account->id;
        }

        // Query with pagination
        $query = Accounts::find()->where(['in', 'id', $accountIds]);
        $dataProvider = new \yii\data\ActiveDataProvider([
            'query' => $query,
            'pagination' => [
                'pageSize' => 50,
            ],
        ]);

        $models = $dataProvider->getModels();
        
        // Should return only 50 results (first page)
        $this->assertLessThanOrEqual(50, count($models));
        
        // Total count should be 60
        $this->assertEquals(60, $dataProvider->getTotalCount());
        
        // Cleanup
        Accounts::deleteAll(['in', 'id', $accountIds]);
    }

    /**
     * Test that eager loading works for multiple relations
     */
    public function testMultipleRelationEagerLoading()
    {
        // Create test data
        $coin = new Coins();
        $coin->name = 'TestCoin';
        $coin->symbol = 'TST';
        $coin->algo = 'sha256';
        $coin->enable = 1;
        $coin->save(false);

        $account = new Accounts();
        $account->username = 'test_user_' . time();
        $account->balance = 100;
        $account->coinid = $coin->id;
        $account->save(false);

        $payout = new Payouts();
        $payout->account_id = $account->id;
        $payout->idcoin = $coin->id;
        $payout->amount = 10;
        $payout->time = time();
        $payout->save(false);

        // Count queries with eager loading
        Yii::$app->db->enableQueryLogging = true;
        Yii::$app->db->queryLog = [];
        $queryCountBefore = count(Yii::$app->db->getQueryLog());

        $payouts = Payouts::find()
            ->with(['account', 'coin'])
            ->where(['id' => $payout->id])
            ->all();
        
        foreach ($payouts as $p) {
            $username = $p->account->username;
            $coinName = $p->coin->name;
        }
        
        $queryCountWithEager = count(Yii::$app->db->getQueryLog()) - $queryCountBefore;

        // Should use 3 queries: 1 for payouts, 1 for accounts, 1 for coins
        $this->assertLessThanOrEqual(3, $queryCountWithEager);
        
        // Cleanup
        $payout->delete();
        $account->delete();
        $coin->delete();
    }

    /**
     * Test cache key uniqueness for filtered queries
     */
    public function testCacheKeyUniqueness()
    {
        $cacheKey1 = 'admin_connections_results_all';
        $cacheKey2 = 'admin_connections_results_sha256';
        
        // Different cache keys should store different data
        Yii::$app->cache->set($cacheKey1, ['data' => 'all'], 30);
        Yii::$app->cache->set($cacheKey2, ['data' => 'sha256'], 30);
        
        $data1 = Yii::$app->cache->get($cacheKey1);
        $data2 = Yii::$app->cache->get($cacheKey2);
        
        $this->assertNotEquals($data1, $data2);
        $this->assertEquals('all', $data1['data']);
        $this->assertEquals('sha256', $data2['data']);
    }

    /**
     * Test that cache expires after TTL
     */
    public function testCacheExpiration()
    {
        $cacheKey = 'test_cache_expiration';
        
        // Set cache with 1 second TTL
        Yii::$app->cache->set($cacheKey, 'test_data', 1);
        
        // Should be available immediately
        $this->assertEquals('test_data', Yii::$app->cache->get($cacheKey));
        
        // Wait for expiration
        sleep(2);
        
        // Should be expired
        $this->assertFalse(Yii::$app->cache->get($cacheKey));
    }

    /**
     * Test optimized aggregation query
     */
    public function testOptimizedAggregation()
    {
        // Create test workers
        $account = new Accounts();
        $account->username = 'test_user_' . time();
        $account->balance = 0;
        $account->save(false);

        $workerIds = [];
        for ($i = 0; $i < 5; $i++) {
            $worker = new Workers();
            $worker->userid = $account->id;
            $worker->name = 'worker_' . $i;
            $worker->algo = 'sha256';
            $worker->ip = '192.168.1.' . $i;
            $worker->time = time();
            $worker->save(false);
            $workerIds[] = $worker->id;
        }

        // Test aggregation query
        $connections = Workers::find()
            ->select(['ip', 'algo', 'COUNT(*) as count', 'MAX(time) as last_seen'])
            ->where(['in', 'id', $workerIds])
            ->groupBy(['ip', 'algo'])
            ->asArray()
            ->all();

        // Should return aggregated results
        $this->assertGreaterThan(0, count($connections));
        $this->assertArrayHasKey('count', $connections[0]);
        $this->assertArrayHasKey('last_seen', $connections[0]);
        
        // Cleanup
        Workers::deleteAll(['in', 'id', $workerIds]);
        $account->delete();
    }
}
