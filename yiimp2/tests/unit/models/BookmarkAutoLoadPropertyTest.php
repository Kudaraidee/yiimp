<?php

namespace app\tests\unit\models;

use Codeception\Test\Unit;
use app\models\Bookmarks;
use app\models\Accounts;
use app\models\Coins;

/**
 * Property-based test for Bookmark auto-load functionality
 * 
 * Feature: yiimp-to-yiimp2-migration, Property 36: Bookmark auto-load
 * Validates: Requirements 9.2
 * 
 * Note: This tests the server-side support for auto-load functionality.
 * The actual auto-load behavior is implemented in JavaScript and would
 * typically be tested with JavaScript testing frameworks.
 */
class BookmarkAutoLoadPropertyTest extends Unit
{
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
     * Property 36: Bookmark auto-load
     * For any bookmarked wallet address, when the user returns to the site,
     * the system should have the necessary data available to automatically
     * load and display statistics for that wallet
     * 
     * This test verifies that:
     * 1. Bookmarks persist across sessions (via database)
     * 2. Bookmark data includes all necessary information for auto-load
     * 3. Most recently used bookmark can be identified
     * 
     * @group property
     */
    public function testBookmarkAutoLoadDataAvailabilityProperty()
    {
        $iterations = 100;
        
        for ($i = 0; $i < $iterations; $i++) {
            // Create multiple bookmarks with different timestamps
            $bookmarks = [];
            $numBookmarks = rand(1, 5);
            
            for ($j = 0; $j < $numBookmarks; $j++) {
                $address = 'TEST_' . $this->generateRandomAddress();
                $bookmark = new Bookmarks();
                $bookmark->address = $address;
                $bookmark->label = $this->generateRandomLabel();
                $bookmark->idcoin = $this->getRandomCoinId();
                $bookmark->lastused = time() - rand(0, 86400); // Random time in last 24 hours
                
                $this->assertTrue($bookmark->save(), "Bookmark should save");
                $bookmarks[] = $bookmark;
                
                // Small delay to ensure different timestamps
                usleep(1000);
            }
            
            // Property 1: All bookmarks should be retrievable
            $retrieved = Bookmarks::find()
                ->where(['address' => array_map(function($b) { return $b->address; }, $bookmarks)])
                ->all();
            
            $this->assertCount($numBookmarks, $retrieved, 
                "All bookmarks should be retrievable for auto-load");
            
            // Property 2: Most recently used bookmark should be identifiable
            $mostRecent = Bookmarks::find()
                ->where(['address' => array_map(function($b) { return $b->address; }, $bookmarks)])
                ->orderBy(['lastused' => SORT_DESC])
                ->one();
            
            $this->assertNotNull($mostRecent, "Most recent bookmark should be identifiable");
            
            // Find the actual most recent from our created bookmarks
            $expectedMostRecent = $bookmarks[0];
            foreach ($bookmarks as $bookmark) {
                if ($bookmark->lastused > $expectedMostRecent->lastused) {
                    $expectedMostRecent = $bookmark;
                }
            }
            
            $this->assertEquals($expectedMostRecent->address, $mostRecent->address,
                "System should correctly identify most recently used bookmark");
            
            // Property 3: Bookmark contains all data needed for auto-load
            foreach ($retrieved as $bookmark) {
                $this->assertNotEmpty($bookmark->address, 
                    "Bookmark must have address for auto-load");
                $this->assertNotNull($bookmark->idcoin, 
                    "Bookmark must have coin ID for auto-load");
                $this->assertNotNull($bookmark->lastused, 
                    "Bookmark must have timestamp for auto-load ordering");
            }
            
            // Clean up this iteration
            foreach ($bookmarks as $bookmark) {
                $bookmark->delete();
            }
        }
    }

    /**
     * Test that bookmark timestamp updates support auto-load recency
     * 
     * @group property
     */
    public function testBookmarkTimestampUpdateForAutoLoadProperty()
    {
        $iterations = 50;
        
        for ($i = 0; $i < $iterations; $i++) {
            // Create two bookmarks
            $bookmark1 = new Bookmarks();
            $bookmark1->address = 'TEST_' . $this->generateRandomAddress();
            $bookmark1->label = 'Bookmark 1';
            $bookmark1->idcoin = $this->getRandomCoinId();
            $bookmark1->lastused = time() - 3600; // 1 hour ago
            $bookmark1->save();
            
            $bookmark2 = new Bookmarks();
            $bookmark2->address = 'TEST_' . $this->generateRandomAddress();
            $bookmark2->label = 'Bookmark 2';
            $bookmark2->idcoin = $this->getRandomCoinId();
            $bookmark2->lastused = time() - 7200; // 2 hours ago
            $bookmark2->save();
            
            // Initially, bookmark1 should be most recent
            $mostRecent = Bookmarks::find()
                ->where(['address' => [$bookmark1->address, $bookmark2->address]])
                ->orderBy(['lastused' => SORT_DESC])
                ->one();
            
            $this->assertEquals($bookmark1->address, $mostRecent->address,
                "Bookmark 1 should initially be most recent");
            
            // Update bookmark2's timestamp (simulating user accessing it)
            sleep(1);
            $bookmark2->updateLastUsed();
            
            // Now bookmark2 should be most recent
            $mostRecent = Bookmarks::find()
                ->where(['address' => [$bookmark1->address, $bookmark2->address]])
                ->orderBy(['lastused' => SORT_DESC])
                ->one();
            
            $this->assertEquals($bookmark2->address, $mostRecent->address,
                "After update, bookmark 2 should be most recent for auto-load");
            
            // Clean up
            $bookmark1->delete();
            $bookmark2->delete();
        }
    }

    /**
     * Test that bookmarks work with actual wallet accounts
     * 
     * @group property
     */
    public function testBookmarkWithWalletAccountProperty()
    {
        $iterations = 50;
        
        for ($i = 0; $i < $iterations; $i++) {
            // Get a random existing account or skip if none exist
            $account = Accounts::find()->orderBy('RAND()')->one();
            
            if (!$account) {
                // Skip this iteration if no accounts exist
                continue;
            }
            
            // Create bookmark for this account
            $bookmark = new Bookmarks();
            $bookmark->address = $account->username;
            $bookmark->label = 'Test Bookmark';
            $bookmark->idcoin = $account->coinid;
            $bookmark->lastused = time();
            
            $this->assertTrue($bookmark->save(), "Bookmark should save for real account");
            
            // Property: Bookmarked address should match an existing account
            $retrievedAccount = Accounts::findOne(['username' => $bookmark->address]);
            $this->assertNotNull($retrievedAccount, 
                "Bookmarked address should correspond to existing account");
            
            // Property: Bookmark coin should match account coin
            $this->assertEquals($account->coinid, $bookmark->idcoin,
                "Bookmark coin should match account coin for consistency");
            
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
            'Test Wallet'
        ];
        
        return $labels[array_rand($labels)];
    }

    /**
     * Get random coin ID from database
     */
    private function getRandomCoinId()
    {
        $coins = Coins::find()->select('id')->asArray()->all();
        
        if (empty($coins)) {
            return 1;
        }
        
        $randomCoin = $coins[array_rand($coins)];
        return $randomCoin['id'];
    }
}
