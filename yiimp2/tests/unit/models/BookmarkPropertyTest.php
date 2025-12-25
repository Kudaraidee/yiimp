<?php

namespace app\tests\unit\models;

use tests\helpers\DatabaseTestHelper;

use Codeception\Test\Unit;
use app\models\Bookmarks;
use app\models\Coins;

/**
 * Property-based tests for Bookmark functionality
 * 
 * Feature: yiimp-to-yiimp2-migration, Property 35: Bookmark storage
 * Feature: yiimp-to-yiimp2-migration, Property 37: Bookmark management operations
 * Validates: Requirements 9.1, 9.3
 */
class BookmarkPropertyTest extends Unit
{
    use DatabaseTestHelper;

    protected function _before()
    {
        // Clean up any existing test bookmarks
        Bookmarks::deleteAll(['address' => ['LIKE', 'TEST_%']]);
    }

    protected function _after()
    {
        // Clean up test data
        Bookmarks::deleteAll(['address' => ['LIKE', 'TEST_%']]);
    }

    /**
     * Property 35: Bookmark storage
     * For any bookmark action, the wallet address should be stored in the database
     * and be retrievable on subsequent queries
     * 
     * @group property
     */
    public function testBookmarkStorageProperty()
    {
        // Skip test if database is not available
        $this->requireDatabase();
        $iterations = 100;
        
        for ($i = 0; $i < $iterations; $i++) {
            // Generate random bookmark data
            $address = 'TEST_' . $this->generateRandomAddress();
            $label = $this->generateRandomLabel();
            $coinId = $this->getRandomCoinId();
            
            // Create bookmark
            $bookmark = new Bookmarks();
            $bookmark->address = $address;
            $bookmark->label = $label;
            $bookmark->idcoin = $coinId;
            $bookmark->lastused = time();
            
            $this->assertTrue($bookmark->save(), "Failed to save bookmark: " . json_encode($bookmark->errors));
            
            // Retrieve bookmark
            $retrieved = Bookmarks::findOne(['address' => $address]);
            
            // Property: Stored bookmark should be retrievable with same data
            $this->assertNotNull($retrieved, "Bookmark should be retrievable after storage");
            $this->assertEquals($address, $retrieved->address, "Address should match");
            $this->assertEquals($label, $retrieved->label, "Label should match");
            $this->assertEquals($coinId, $retrieved->idcoin, "Coin ID should match");
            
            // Clean up this iteration
            $bookmark->delete();
        }
    }

    /**
     * Property 37: Bookmark management operations
     * For any bookmark operation (add, remove, update), the system should correctly
     * update the bookmark state in the database
     * 
     * @group property
     */
    public function testBookmarkManagementOperationsProperty()
    {
        // Skip test if database is not available
        $this->requireDatabase();
        $iterations = 100;
        
        for ($i = 0; $i < $iterations; $i++) {
            $address = 'TEST_' . $this->generateRandomAddress();
            $initialLabel = $this->generateRandomLabel();
            $updatedLabel = $this->generateRandomLabel();
            $coinId = $this->getRandomCoinId();
            
            // Operation 1: Add bookmark
            $bookmark = new Bookmarks();
            $bookmark->address = $address;
            $bookmark->label = $initialLabel;
            $bookmark->idcoin = $coinId;
            $bookmark->lastused = time();
            
            $this->assertTrue($bookmark->save(), "Add operation should succeed");
            
            // Verify add operation
            $count = Bookmarks::find()->where(['address' => $address])->count();
            $this->assertEquals(1, $count, "After add, exactly one bookmark should exist");
            
            // Operation 2: Update bookmark label
            $bookmark->label = $updatedLabel;
            $this->assertTrue($bookmark->save(), "Update operation should succeed");
            
            // Verify update operation
            $retrieved = Bookmarks::findOne(['address' => $address]);
            $this->assertEquals($updatedLabel, $retrieved->label, "Label should be updated");
            
            // Operation 3: Update last used timestamp
            $oldTimestamp = $bookmark->lastused;
            sleep(1); // Ensure time difference
            $bookmark->updateLastUsed();
            
            // Verify timestamp update
            $retrieved = Bookmarks::findOne(['address' => $address]);
            $this->assertGreaterThan($oldTimestamp, $retrieved->lastused, "Timestamp should be updated");
            
            // Operation 4: Remove bookmark
            $deleteResult = $bookmark->delete();
            $this->assertNotFalse($deleteResult, "Delete operation should succeed");
            
            // Verify remove operation
            $count = Bookmarks::find()->where(['address' => $address])->count();
            $this->assertEquals(0, $count, "After remove, no bookmark should exist");
        }
    }

    /**
     * Test that duplicate bookmarks are prevented
     * 
     * @group property
     */
    public function testNoDuplicateBookmarksProperty()
    {
        // Skip test if database is not available
        $this->requireDatabase();
        $iterations = 50;
        
        for ($i = 0; $i < $iterations; $i++) {
            $address = 'TEST_' . $this->generateRandomAddress();
            $coinId = $this->getRandomCoinId();
            
            // Create first bookmark
            $bookmark1 = new Bookmarks();
            $bookmark1->address = $address;
            $bookmark1->label = 'Label 1';
            $bookmark1->idcoin = $coinId;
            $bookmark1->lastused = time();
            
            $this->assertTrue($bookmark1->save(), "First bookmark should save");
            
            // Attempt to create duplicate
            $bookmark2 = new Bookmarks();
            $bookmark2->address = $address;
            $bookmark2->label = 'Label 2';
            $bookmark2->idcoin = $coinId;
            $bookmark2->lastused = time();
            
            // Property: System should prevent duplicate addresses
            // Note: This depends on database constraints
            $result = $bookmark2->save();
            
            // Count bookmarks with this address
            $count = Bookmarks::find()->where(['address' => $address])->count();
            
            // Either save fails OR only one bookmark exists
            $this->assertTrue(!$result || $count === 1, 
                "System should prevent duplicate bookmarks for same address");
            
            // Clean up
            Bookmarks::deleteAll(['address' => $address]);
        }
    }

    /**
     * Test bookmark-coin relationship integrity
     * 
     * @group property
     */
    public function testBookmarkCoinRelationshipProperty()
    {
        // Skip test if database is not available
        $this->requireDatabase();
        $iterations = 50;
        
        for ($i = 0; $i < $iterations; $i++) {
            $address = 'TEST_' . $this->generateRandomAddress();
            $coinId = $this->getRandomCoinId();
            
            // Create bookmark
            $bookmark = new Bookmarks();
            $bookmark->address = $address;
            $bookmark->label = $this->generateRandomLabel();
            $bookmark->idcoin = $coinId;
            $bookmark->lastused = time();
            
            $this->assertTrue($bookmark->save(), "Bookmark should save");
            
            // Property: Bookmark should have valid coin relationship
            $coin = $bookmark->getCoin()->one();
            
            if ($coinId > 0) {
                $this->assertNotNull($coin, "Bookmark should have valid coin relationship");
                $this->assertEquals($coinId, $coin->id, "Coin ID should match");
            }
            
            // Clean up
            $bookmark->delete();
        }
    }

    /**
     * Generate random wallet address
     */
    private function generateRandomAddress()
    {
        $length = rand(26, 42);
        $chars = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
        $address = '';
        
        for ($i = 0; $i < $length; $i++) {
            $address .= $chars[rand(0, strlen($chars) - 1)];
        }
        
        return $address;
    }

    /**
     * Generate random label
     */
    private function generateRandomLabel()
    {
        $labels = [
            'My Wallet',
            'Mining Wallet',
            'Main Address',
            'Secondary Wallet',
            'Test Wallet',
            'Pool Wallet',
            'Exchange Wallet',
            'Cold Storage',
            'Hot Wallet',
            'Trading Wallet'
        ];
        
        return $labels[array_rand($labels)] . ' ' . rand(1, 999);
    }

    /**
     * Get random coin ID from database
     */
    private function getRandomCoinId()
    {
        $coins = Coins::find()->select('id')->asArray()->all();
        
        if (empty($coins)) {
            // If no coins exist, return 1 as default
            return 1;
        }
        
        $randomCoin = $coins[array_rand($coins)];
        return $randomCoin['id'];
    }
}
