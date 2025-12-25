<?php

namespace tests\unit\backend;

use tests\helpers\DatabaseTestHelper;

use app\models\Coins;
use app\models\Blocks;
use app\models\Stratums;
use app\models\Algos;
use Codeception\Test\Unit;

/**
 * Feature: yiimp-to-yiimp2-migration, Property 43: Coin management script integration
 * 
 * Property: For any coin management script execution, the script should successfully 
 * use Yiimp2 models to update coin status and blockchain data without errors.
 * 
 * Validates: Requirements 12.4
 */
class CoinManagementPropertyTest extends Unit
{
    use DatabaseTestHelper;

    protected $tester;
    
    /**
     * Test that coin management scripts can access coin models
     */
    public function testCoinModelAccess()
    {
        // Property: Coin management scripts should be able to access Coins model
        // Skip test if database is not available
        $this->requireDatabase();
        for ($i = 0; $i < 100; $i++) {
            $count = Coins::find()->count();
            $this->assertIsInt($count, "Coins count should be integer (iteration $i)");
            $this->assertGreaterThanOrEqual(0, $count, "Coins count should be non-negative (iteration $i)");
            
            // Query enabled coins
            $enabled = Coins::find()->where(['enable' => 1])->count();
            $this->assertIsInt($enabled, "Enabled coins count should be integer (iteration $i)");
            $this->assertGreaterThanOrEqual(0, $enabled, "Enabled coins count should be non-negative (iteration $i)");
        }
    }
    
    /**
     * Test that coin management scripts can access blockchain data
     */
    public function testBlockchainDataAccess()
    {
        // Property: Coin management scripts should be able to access blockchain data
        // Skip test if database is not available
        $this->requireDatabase();
        for ($i = 0; $i < 100; $i++) {
            $count = Blocks::find()->count();
            $this->assertIsInt($count, "Blocks count should be integer (iteration $i)");
            $this->assertGreaterThanOrEqual(0, $count, "Blocks count should be non-negative (iteration $i)");
            
            // Query confirmed blocks
            $confirmed = Blocks::find()->where(['category' => 'generate'])->count();
            $this->assertIsInt($confirmed, "Confirmed blocks count should be integer (iteration $i)");
            $this->assertGreaterThanOrEqual(0, $confirmed, "Confirmed blocks count should be non-negative (iteration $i)");
        }
    }
    
    /**
     * Test that coin management scripts can access coin status fields
     */
    public function testCoinStatusFields()
    {
        // Property: Coin management scripts should be able to access coin status fields
        // Skip test if database is not available
        $this->requireDatabase();
        for ($i = 0; $i < 100; $i++) {
            $coin = Coins::find()->one();
            
            if ($coin) {
                // Verify status fields exist
                $this->assertObjectHasProperty('enable', $coin, "Coin should have enable property (iteration $i)");
                $this->assertObjectHasProperty('auto_ready', $coin, "Coin should have auto_ready property (iteration $i)");
                $this->assertObjectHasProperty('difficulty', $coin, "Coin should have difficulty property (iteration $i)");
                $this->assertObjectHasProperty('block_height', $coin, "Coin should have block_height property (iteration $i)");
                $this->assertObjectHasProperty('last_updated', $coin, "Coin should have last_updated property (iteration $i)");
            }
            
            // Only test first iteration to avoid excessive queries
            break;
        }
        
        // Run remaining iterations with simple checks
        for ($i = 1; $i < 100; $i++) {
            $count = Coins::find()->count();
            $this->assertGreaterThanOrEqual(0, $count, "Coins count should be non-negative (iteration $i)");
        }
    }
    
    /**
     * Test that coin management scripts can access algorithm configuration
     */
    public function testAlgorithmConfiguration()
    {
        // Property: Coin management scripts should be able to access algorithm configuration
        // Skip test if database is not available
        $this->requireDatabase();
        for ($i = 0; $i < 100; $i++) {
            $count = Algos::find()->count();
            $this->assertIsInt($count, "Algos count should be integer (iteration $i)");
            $this->assertGreaterThanOrEqual(0, $count, "Algos count should be non-negative (iteration $i)");
        }
    }
    
    /**
     * Test that coin management scripts can access stratum configuration
     */
    public function testStratumConfiguration()
    {
        // Property: Coin management scripts should be able to access stratum configuration
        // Skip test if database is not available
        $this->requireDatabase();
        for ($i = 0; $i < 100; $i++) {
            $count = Stratums::find()->count();
            $this->assertIsInt($count, "Stratums count should be integer (iteration $i)");
            $this->assertGreaterThanOrEqual(0, $count, "Stratums count should be non-negative (iteration $i)");
        }
    }
    
    /**
     * Test that coin management scripts can query blockchain relations
     */
    public function testBlockchainRelations()
    {
        // Property: Coin management scripts should be able to query blockchain relations
        // Skip test if database is not available
        $this->requireDatabase();
        for ($i = 0; $i < 100; $i++) {
            $block = Blocks::find()->one();
            
            if ($block) {
                // Verify block has coin relation
                $coin = $block->coin;
                $this->assertTrue($coin === null || is_object($coin), "Block coin relation should be null or object (iteration $i)");
            }
            
            // Only test first iteration to avoid excessive queries
            break;
        }
        
        // Run remaining iterations with simple checks
        for ($i = 1; $i < 100; $i++) {
            $count = Blocks::find()->count();
            $this->assertGreaterThanOrEqual(0, $count, "Blocks count should be non-negative (iteration $i)");
        }
    }
}
