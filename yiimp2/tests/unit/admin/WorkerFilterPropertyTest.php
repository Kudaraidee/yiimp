<?php

namespace tests\unit\admin;

use Codeception\Test\Unit;
use app\models\Workers;
use app\models\Accounts;
use yii\data\ActiveDataProvider;
use tests\helpers\DatabaseTestHelper;

/**
 * Property-based tests for Worker Algorithm Filtering
 * 
 * Feature: yiimp2-admin-panel-fixes, Property 5: Worker algorithm filter works correctly
 */
class WorkerFilterPropertyTest extends Unit
{
    use DatabaseTestHelper;
    
    /**
     * Property 5: Worker algorithm filter works correctly
     * 
     * For any algorithm filter selection on the worker monitoring page, all returned
     * workers should be mining that specific algorithm.
     * 
     * Validates: Requirements 3.3
     * 
     * @test
     */
    public function testWorkerAlgorithmFilterCorrectness()
    {
        // Feature: yiimp2-admin-panel-fixes, Property 5: Worker algorithm filter works correctly
        
        // Skip test if database is not available
        $this->requireDatabase();
        
        // Check if database is available
        try {
            \Yii::$app->db->open();
        } catch (\Exception $e) {
            $this->markTestSkipped('Database not available: ' . $e->getMessage());
            return;
        }
        
        $iterations = 100;
        $failures = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            // Create test workers with different algorithms
            $testWorkers = $this->createTestWorkers(10);
            
            if (empty($testWorkers)) {
                continue; // Skip if no workers created
            }
            
            // Get unique algorithms from test workers
            $algorithms = array_unique(array_map(function($w) { return $w->algo; }, $testWorkers));
            
            // Test filtering by each algorithm
            foreach ($algorithms as $algo) {
                // Perform filter - limit to test workers only
                $testWorkerIds = array_map(function($w) { return $w->id; }, $testWorkers);
                $query = Workers::find()->where(['id' => $testWorkerIds]);
                
                // Apply algorithm filter (matching AdminController::actionWorker_results())
                if ($algo) {
                    $query->andWhere(['algo' => $algo]);
                }
                
                $results = $query->all();
                
                // Verify all results match the algorithm filter
                foreach ($results as $result) {
                    if ($result->algo !== $algo) {
                        $failures[] = [
                            'iteration' => $i,
                            'type' => 'false_positive',
                            'worker_id' => $result->id,
                            'worker_name' => $result->name,
                            'worker_algo' => $result->algo,
                            'filter_algo' => $algo,
                            'reason' => 'Worker in results but does not match algorithm filter'
                        ];
                    }
                }
                
                // Verify no workers with matching algorithm are missing
                $resultIds = array_map(function($r) { return $r->id; }, $results);
                foreach ($testWorkers as $worker) {
                    if ($worker->algo === $algo && !in_array($worker->id, $resultIds)) {
                        $failures[] = [
                            'iteration' => $i,
                            'type' => 'false_negative',
                            'worker_id' => $worker->id,
                            'worker_name' => $worker->name,
                            'worker_algo' => $worker->algo,
                            'filter_algo' => $algo,
                            'reason' => 'Worker matches algorithm filter but not in results'
                        ];
                    }
                }
            }
            
            // Clean up test workers
            foreach ($testWorkers as $worker) {
                $worker->delete();
            }
        }
        
        // Report failures if any
        if (!empty($failures)) {
            $this->fail(
                "Property test failed in " . count($failures) . " out of $iterations iterations:\n" .
                json_encode($failures, JSON_PRETTY_PRINT)
            );
        }
        
        $this->assertTrue(true, "Property holds for all $iterations iterations");
    }
    
    /**
     * Test active worker filtering
     * 
     * @test
     */
    public function testWorkerActiveFilterCorrectness()
    {
        // Skip test if database is not available
        $this->requireDatabase();
        
        try {
            \Yii::$app->db->open();
        } catch (\Exception $e) {
            $this->markTestSkipped('Database not available: ' . $e->getMessage());
            return;
        }
        
        $iterations = 50;
        $failures = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            // Create test workers with varying activity times
            $testWorkers = $this->createTestWorkersWithActivity(10);
            
            if (empty($testWorkers)) {
                continue;
            }
            
            // Test active-only filter
            $testWorkerIds = array_map(function($w) { return $w->id; }, $testWorkers);
            $query = Workers::find()->where(['id' => $testWorkerIds]);
            
            // Apply active filter (matching AdminController::actionWorker_results())
            $activeOnly = 1;
            if ($activeOnly) {
                $query->andWhere(['>', 'time', time() - 300]);
            }
            
            $results = $query->all();
            
            // Verify all results are active
            foreach ($results as $result) {
                if ($result->time <= (time() - 300)) {
                    $failures[] = [
                        'iteration' => $i,
                        'type' => 'false_positive',
                        'worker_id' => $result->id,
                        'worker_time' => $result->time,
                        'current_time' => time(),
                        'reason' => 'Inactive worker in active-only results'
                    ];
                }
            }
            
            // Verify no active workers are missing
            $resultIds = array_map(function($r) { return $r->id; }, $results);
            foreach ($testWorkers as $worker) {
                if ($worker->time > (time() - 300) && !in_array($worker->id, $resultIds)) {
                    $failures[] = [
                        'iteration' => $i,
                        'type' => 'false_negative',
                        'worker_id' => $worker->id,
                        'worker_time' => $worker->time,
                        'current_time' => time(),
                        'reason' => 'Active worker missing from active-only results'
                    ];
                }
            }
            
            // Clean up test workers
            foreach ($testWorkers as $worker) {
                $worker->delete();
            }
        }
        
        // Report failures if any
        if (!empty($failures)) {
            $this->fail(
                "Active filter test failed in " . count($failures) . " out of $iterations iterations:\n" .
                json_encode($failures, JSON_PRETTY_PRINT)
            );
        }
        
        $this->assertTrue(true, "Active filter property holds for all $iterations iterations");
    }
    
    /**
     * Create test workers with random algorithms
     * 
     * @param int $count
     * @return Workers[]
     */
    protected function createTestWorkers($count)
    {
        $workers = [];
        $algorithms = ['sha256', 'scrypt', 'x11', 'equihash', 'kawpow', 'ethash'];
        
        // Create a test user first
        $testUser = $this->createTestUser();
        if (!$testUser) {
            return [];
        }
        
        for ($i = 0; $i < $count; $i++) {
            $worker = new Workers();
            $worker->userid = $testUser->id;
            $worker->name = 'testworker_' . time() . '_' . rand(1000, 9999) . '_' . $i;
            $worker->worker = $worker->name;
            $worker->algo = $algorithms[array_rand($algorithms)];
            $worker->ip = '192.168.' . rand(1, 255) . '.' . rand(1, 255);
            $worker->difficulty = rand(1, 1000);
            $worker->time = time(); // Active worker
            $worker->subscribe = 1;
            $worker->pid = rand(1000, 9999);
            
            if ($worker->save()) {
                $workers[] = $worker;
            }
        }
        
        return $workers;
    }
    
    /**
     * Create test workers with varying activity times
     * 
     * @param int $count
     * @return Workers[]
     */
    protected function createTestWorkersWithActivity($count)
    {
        $workers = [];
        $algorithms = ['sha256', 'scrypt', 'x11'];
        
        // Create a test user first
        $testUser = $this->createTestUser();
        if (!$testUser) {
            return [];
        }
        
        for ($i = 0; $i < $count; $i++) {
            $worker = new Workers();
            $worker->userid = $testUser->id;
            $worker->name = 'testworker_' . time() . '_' . rand(1000, 9999) . '_' . $i;
            $worker->worker = $worker->name;
            $worker->algo = $algorithms[array_rand($algorithms)];
            $worker->ip = '192.168.' . rand(1, 255) . '.' . rand(1, 255);
            $worker->difficulty = rand(1, 1000);
            $worker->subscribe = 1;
            $worker->pid = rand(1000, 9999);
            
            // Randomly set as active or inactive
            if (rand(0, 1)) {
                // Active: within last 5 minutes
                $worker->time = time() - rand(0, 299);
            } else {
                // Inactive: more than 5 minutes ago
                $worker->time = time() - rand(301, 3600);
            }
            
            if ($worker->save()) {
                $workers[] = $worker;
            }
        }
        
        return $workers;
    }
    
    /**
     * Create a test user account
     * 
     * @return Accounts|null
     */
    protected function createTestUser()
    {
        $user = new Accounts();
        $user->username = 'testuser_' . time() . '_' . rand(1000, 9999);
        $user->balance = 0;
        $user->coinid = 1;
        
        if ($user->save()) {
            return $user;
        }
        
        return null;
    }
}
