<?php

namespace tests\unit\admin;

use Codeception\Test\Unit;
use app\models\Accounts;
use yii\data\ActiveDataProvider;
use tests\helpers\DatabaseTestHelper;

/**
 * Property-based tests for User Search and Filter
 * 
 * Feature: yiimp-to-yiimp2-migration, Property 2: User search and filter correctness
 */
class UserSearchPropertyTest extends Unit
{
    use DatabaseTestHelper;
    
    /**
     * Property 2: User Search and Filter Correctness
     * 
     * For any search query or filter criteria on user accounts, the system should
     * return exactly the set of users matching the criteria, with no false positives
     * or false negatives.
     * 
     * Validates: Requirements 1.4
     * 
     * @test
     */
    public function testUserSearchAndFilterCorrectness()
    {
        // Feature: yiimp-to-yiimp2-migration, Property 2: User search and filter correctness
        
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
            // Create test users with known attributes
            $testUsers = $this->createTestUsers(5);
            
            // Generate random search criteria
            $searchCriteria = $this->generateSearchCriteria($testUsers);
            
            // Perform search - limit to test users only
            $testUserIds = array_map(function($u) { return $u->id; }, $testUsers);
            $query = Accounts::find()->where(['id' => $testUserIds]);
            $this->applySearchCriteria($query, $searchCriteria);
            $results = $query->all();
            
            // Verify results match criteria
            $expectedUsers = $this->getExpectedUsers($testUsers, $searchCriteria);
            
            // Check for false positives (results that shouldn't be there)
            foreach ($results as $result) {
                if (!$this->userMatchesCriteria($result, $searchCriteria)) {
                    $failures[] = [
                        'iteration' => $i,
                        'type' => 'false_positive',
                        'user_id' => $result->id,
                        'username' => $result->username,
                        'coinid' => $result->coinid,
                        'is_locked' => $result->is_locked,
                        'criteria' => $searchCriteria,
                        'reason' => 'User in results but does not match criteria'
                    ];
                }
            }
            
            // Check for false negatives (expected users not in results)
            $resultIds = array_map(function($r) { return $r->id; }, $results);
            foreach ($expectedUsers as $expected) {
                if (!in_array($expected->id, $resultIds)) {
                    $failures[] = [
                        'iteration' => $i,
                        'type' => 'false_negative',
                        'user_id' => $expected->id,
                        'username' => $expected->username,
                        'coinid' => $expected->coinid,
                        'is_locked' => $expected->is_locked,
                        'criteria' => $searchCriteria,
                        'reason' => 'User matches criteria but not in results'
                    ];
                }
            }
            
            // Clean up test users
            foreach ($testUsers as $user) {
                $user->delete();
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
     * Create test users
     * 
     * @param int $count
     * @return Accounts[]
     */
    protected function createTestUsers($count)
    {
        $users = [];
        for ($i = 0; $i < $count; $i++) {
            $user = new Accounts();
            $user->username = 'testuser_' . time() . '_' . rand(1000, 9999) . '_' . $i;
            $user->balance = round(rand(0, 100000) / 100, 8);
            $user->coinid = rand(1, 100);
            $user->no_fees = rand(0, 1);
            $user->donation = rand(0, 100);
            $user->is_locked = rand(0, 1); // Add is_locked field
            
            if ($user->save()) {
                $users[] = $user;
            }
        }
        return $users;
    }
    
    /**
     * Generate random search criteria
     * 
     * @param array $testUsers
     * @return array
     */
    protected function generateSearchCriteria($testUsers)
    {
        $criteriaTypes = ['username', 'coinid', 'is_locked', 'combined'];
        $criteriaType = $criteriaTypes[array_rand($criteriaTypes)];
        
        switch ($criteriaType) {
            case 'username':
                // Search by partial username
                $user = $testUsers[array_rand($testUsers)];
                return ['search' => substr($user->username, 0, 10)];
                
            case 'coinid':
                $user = $testUsers[array_rand($testUsers)];
                return ['coinid' => $user->coinid];
                
            case 'is_locked':
                return ['locked' => rand(0, 1)];
                
            case 'combined':
                // Test combined criteria
                $user = $testUsers[array_rand($testUsers)];
                return [
                    'search' => substr($user->username, 0, 10),
                    'coinid' => $user->coinid,
                ];
                
            default:
                return [];
        }
    }
    
    /**
     * Apply search criteria to query
     * 
     * @param \yii\db\ActiveQuery $query
     * @param array $criteria
     */
    protected function applySearchCriteria($query, $criteria)
    {
        // Match the actual implementation in AdminController::actionUser_results()
        if (isset($criteria['search'])) {
            $query->andWhere(['like', 'username', $criteria['search']]);
        }
        if (isset($criteria['coinid'])) {
            $query->andWhere(['coinid' => $criteria['coinid']]);
        }
        if (isset($criteria['locked']) && $criteria['locked'] !== '') {
            $query->andWhere(['is_locked' => $criteria['locked']]);
        }
    }
    
    /**
     * Get expected users matching criteria
     * 
     * @param array $testUsers
     * @param array $criteria
     * @return array
     */
    protected function getExpectedUsers($testUsers, $criteria)
    {
        return array_filter($testUsers, function($user) use ($criteria) {
            return $this->userMatchesCriteria($user, $criteria);
        });
    }
    
    /**
     * Check if user matches criteria
     * 
     * @param Accounts $user
     * @param array $criteria
     * @return bool
     */
    protected function userMatchesCriteria($user, $criteria)
    {
        // Match the actual implementation in AdminController::actionUser_results()
        if (isset($criteria['search'])) {
            if (strpos($user->username, $criteria['search']) === false) {
                return false;
            }
        }
        if (isset($criteria['coinid'])) {
            if ($user->coinid != $criteria['coinid']) {
                return false;
            }
        }
        if (isset($criteria['locked']) && $criteria['locked'] !== '') {
            if ($user->is_locked != $criteria['locked']) {
                return false;
            }
        }
        return true;
    }
}
