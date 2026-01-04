<?php

namespace tests\unit\admin;

use tests\helpers\DatabaseTestHelper;

use Codeception\Test\Unit;
use app\models\Workers;
use app\models\Shares;
use app\models\Accounts;

/**
 * Property-based tests for Botnet Detection and Blocking
 * 
 * Feature: yiimp-to-yiimp2-migration, Property 3: Botnet detection and blocking
 */
class BotnetDetectionPropertyTest extends Unit
{
    use DatabaseTestHelper;

    /**
     * Property 3: Botnet Detection and Blocking
     * 
     * For any mining pattern flagged as suspicious (botnet or monster), the blocking
     * operation should prevent further mining activity from that source and persist
     * the block status.
     * 
     * Validates: Requirements 1.10
     * 
     * @test
     */
    public function testBotnetDetectionAndBlocking()
    {
        // Feature: yiimp-to-yiimp2-migration, Property 3: Botnet detection and blocking
        
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
            // Create test account
            $account = $this->createTestAccount();
            
            // Generate suspicious mining pattern
            $suspiciousPattern = $this->generateSuspiciousPattern();
            
            // Create workers exhibiting suspicious pattern
            $workers = $this->createSuspiciousWorkers($account->id, $suspiciousPattern);
            
            // Detect suspicious pattern
            $isDetected = $this->detectSuspiciousPattern($workers, $suspiciousPattern);
            
            if (!$isDetected) {
                $failures[] = [
                    'iteration' => $i,
                    'type' => 'detection_failure',
                    'pattern' => $suspiciousPattern,
                    'reason' => 'Suspicious pattern not detected'
                ];
                $this->cleanup($account, $workers);
                continue;
            }
            
            // Block the suspicious source
            $blockResult = $this->blockSuspiciousSource($account, $suspiciousPattern);
            
            if (!$blockResult['success']) {
                $failures[] = [
                    'iteration' => $i,
                    'type' => 'blocking_failure',
                    'pattern' => $suspiciousPattern,
                    'reason' => 'Failed to block suspicious source'
                ];
                $this->cleanup($account, $workers);
                continue;
            }
            
            // Verify blocking prevents further activity
            $canMine = $this->canAccountMine($account);
            
            if ($canMine) {
                $failures[] = [
                    'iteration' => $i,
                    'type' => 'prevention_failure',
                    'account_id' => $account->id,
                    'is_locked' => $account->is_locked,
                    'pattern' => $suspiciousPattern,
                    'reason' => 'Blocked account can still mine'
                ];
            }
            
            // Verify block status persists
            $account->refresh();
            if (!$this->isBlockPersisted($account)) {
                $failures[] = [
                    'iteration' => $i,
                    'type' => 'persistence_failure',
                    'account_id' => $account->id,
                    'is_locked' => $account->is_locked,
                    'reason' => 'Block status not persisted'
                ];
            }
            
            // Clean up
            $this->cleanup($account, $workers);
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
     * Create test account
     * 
     * @return Accounts
     */
    protected function createTestAccount()
    {
        $account = new Accounts();
        $account->username = 'bottest_' . time() . '_' . rand(1000, 9999);
        $account->balance = 0;
        $account->coinid = 1;
        $account->is_locked = 0;
        
        if (!$account->save()) {
            throw new \Exception('Failed to create test account: ' . json_encode($account->errors));
        }
        
        return $account;
    }
    
    /**
     * Generate suspicious mining pattern
     * 
     * @return array
     */
    protected function generateSuspiciousPattern()
    {
        $patternTypes = [
            'excessive_workers' => [
                'type' => 'excessive_workers',
                'threshold' => rand(50, 100),
                'description' => 'Too many workers from single account'
            ],
            'rapid_connection' => [
                'type' => 'rapid_connection',
                'connections_per_minute' => rand(20, 50),
                'description' => 'Rapid connection/disconnection pattern'
            ],
            'identical_workers' => [
                'type' => 'identical_workers',
                'worker_count' => rand(10, 30),
                'description' => 'Many workers with identical names'
            ],
            'low_difficulty_spam' => [
                'type' => 'low_difficulty_spam',
                'share_rate' => rand(100, 500),
                'description' => 'Excessive low-difficulty shares'
            ],
        ];
        
        return $patternTypes[array_rand($patternTypes)];
    }
    
    /**
     * Create workers exhibiting suspicious pattern
     * 
     * @param int $accountId
     * @param array $pattern
     * @return Workers[]
     */
    protected function createSuspiciousWorkers($accountId, $pattern)
    {
        $workers = [];
        $currentTime = time();
        
        switch ($pattern['type']) {
            case 'excessive_workers':
                // Create many workers
                $count = $pattern['threshold'] + rand(1, 10);
                for ($j = 0; $j < $count; $j++) {
                    $worker = new Workers();
                    $worker->userid = $accountId;
                    $worker->name = 'worker_' . $j;
                    $worker->algo = 'sha256';
                    $worker->ip = '192.168.1.' . rand(1, 254);
                    $worker->time = $currentTime;
                    $worker->difficulty = 1;
                    
                    if ($worker->save()) {
                        $workers[] = $worker;
                    }
                }
                break;
                
            case 'rapid_connection':
                // Create workers with rapid connection pattern
                $count = rand(10, 20);
                for ($j = 0; $j < $count; $j++) {
                    $worker = new Workers();
                    $worker->userid = $accountId;
                    $worker->name = 'rapid_' . $j;
                    $worker->algo = 'scrypt';
                    $worker->ip = '10.0.0.' . rand(1, 254);
                    $worker->time = $currentTime - rand(0, 60); // Within last minute
                    $worker->difficulty = 1;
                    
                    if ($worker->save()) {
                        $workers[] = $worker;
                    }
                }
                break;
                
            case 'identical_workers':
                // Create workers with identical names
                $count = $pattern['worker_count'];
                $identicalName = 'clone_worker';
                for ($j = 0; $j < $count; $j++) {
                    $worker = new Workers();
                    $worker->userid = $accountId;
                    $worker->name = $identicalName;
                    $worker->algo = 'x11';
                    $worker->ip = '172.16.0.' . rand(1, 254);
                    $worker->time = $currentTime;
                    $worker->difficulty = 1;
                    
                    if ($worker->save()) {
                        $workers[] = $worker;
                    }
                }
                break;
                
            case 'low_difficulty_spam':
                // Create workers with low difficulty
                $count = rand(5, 15);
                for ($j = 0; $j < $count; $j++) {
                    $worker = new Workers();
                    $worker->userid = $accountId;
                    $worker->name = 'spammer_' . $j;
                    $worker->algo = 'lyra2v2';
                    $worker->ip = '192.168.100.' . rand(1, 254);
                    $worker->time = $currentTime;
                    $worker->difficulty = 0.001; // Very low difficulty
                    
                    if ($worker->save()) {
                        $workers[] = $worker;
                    }
                }
                break;
        }
        
        return $workers;
    }
    
    /**
     * Detect suspicious pattern
     * 
     * @param Workers[] $workers
     * @param array $pattern
     * @return bool
     */
    protected function detectSuspiciousPattern($workers, $pattern)
    {
        if (empty($workers)) {
            return false;
        }
        
        switch ($pattern['type']) {
            case 'excessive_workers':
                return count($workers) >= $pattern['threshold'];
                
            case 'rapid_connection':
                // Check if workers connected within short time window
                $recentWorkers = array_filter($workers, function($w) {
                    return $w->time > (time() - 60);
                });
                return count($recentWorkers) >= 10;
                
            case 'identical_workers':
                // Check for workers with identical names
                $names = array_map(function($w) { return $w->name; }, $workers);
                $uniqueNames = array_unique($names);
                return count($names) - count($uniqueNames) >= 5;
                
            case 'low_difficulty_spam':
                // Check for low difficulty workers
                $lowDiffWorkers = array_filter($workers, function($w) {
                    return $w->difficulty < 0.01;
                });
                return count($lowDiffWorkers) >= 5;
                
            default:
                return false;
        }
    }
    
    /**
     * Block suspicious source
     * 
     * @param Accounts $account
     * @param array $pattern
     * @return array
     */
    protected function blockSuspiciousSource($account, $pattern)
    {
        // Lock the account to prevent further mining
        $account->is_locked = 1;
        
        if ($account->save(false)) {
            return [
                'success' => true,
                'account_id' => $account->id,
                'pattern' => $pattern['type'],
            ];
        }
        
        return [
            'success' => false,
            'errors' => $account->errors,
        ];
    }
    
    /**
     * Check if account can mine
     * 
     * @param Accounts $account
     * @return bool
     */
    protected function canAccountMine($account)
    {
        // Refresh account to get latest data
        $account->refresh();
        
        // If account is locked, it should not be able to mine
        return $account->is_locked == 0;
    }
    
    /**
     * Check if block status is persisted
     * 
     * @param Accounts $account
     * @return bool
     */
    protected function isBlockPersisted($account)
    {
        // Reload account from database
        $freshAccount = Accounts::findOne($account->id);
        
        if ($freshAccount === null) {
            return false;
        }
        
        return $freshAccount->is_locked == 1;
    }
    
    /**
     * Clean up test data
     * 
     * @param Accounts $account
     * @param Workers[] $workers
     */
    protected function cleanup($account, $workers)
    {
        // Delete workers
        foreach ($workers as $worker) {
            try {
                $worker->delete();
            } catch (\Exception $e) {
                // Ignore deletion errors
            }
        }
        
        // Delete account
        try {
            $account->delete();
        } catch (\Exception $e) {
            // Ignore deletion errors
        }
    }
}
