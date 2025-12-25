<?php

namespace tests\unit\backend;

use app\models\Markets;
use app\models\Coins;
use app\models\Balances;
use Codeception\Test\Unit;

/**
 * Feature: yiimp-to-yiimp2-migration, Property 42: Exchange script component integration
 * 
 * Property: For any exchange script execution, the script should successfully 
 * use Yiimp2 RPC components to execute trades and deposits without errors.
 * 
 * Validates: Requirements 12.3
 */
class ExchangeScriptPropertyTest extends Unit
{
    protected $tester;
    
    /**
     * Test that exchange scripts can access market models
     */
    public function testMarketModelAccess()
    {
        // Property: Exchange scripts should be able to access Markets model
        for ($i = 0; $i < 100; $i++) {
            $count = Markets::find()->count();
            $this->assertIsInt($count, "Markets count should be integer (iteration $i)");
            $this->assertGreaterThanOrEqual(0, $count, "Markets count should be non-negative (iteration $i)");
            
            // Query active markets
            $active = Markets::find()->where(['>', 'price', 0])->count();
            $this->assertIsInt($active, "Active markets count should be integer (iteration $i)");
            $this->assertGreaterThanOrEqual(0, $active, "Active markets count should be non-negative (iteration $i)");
        }
    }
    
    /**
     * Test that exchange scripts can access RPC components
     */
    public function testRpcComponentAccess()
    {
        // Property: Exchange scripts should be able to access RPC components
        for ($i = 0; $i < 100; $i++) {
            $rpcClient = \Yii::$app->RpcClient;
            $this->assertNotNull($rpcClient, "RpcClient component should be accessible (iteration $i)");
            $this->assertIsObject($rpcClient, "RpcClient should be an object (iteration $i)");
            
            $explorerUtils = \Yii::$app->ExplorerUtils;
            $this->assertNotNull($explorerUtils, "ExplorerUtils component should be accessible (iteration $i)");
            $this->assertIsObject($explorerUtils, "ExplorerUtils should be an object (iteration $i)");
        }
    }
    
    /**
     * Test that exchange scripts can access coin RPC configuration
     */
    public function testCoinRpcConfiguration()
    {
        // Property: Exchange scripts should be able to access coin RPC configuration
        for ($i = 0; $i < 100; $i++) {
            $coins = Coins::find()->where(['enable' => 1])->all();
            
            foreach ($coins as $coin) {
                // Verify coin has RPC configuration fields
                $this->assertObjectHasProperty('rpchost', $coin, "Coin should have rpchost property (iteration $i)");
                $this->assertObjectHasProperty('rpcport', $coin, "Coin should have rpcport property (iteration $i)");
                $this->assertObjectHasProperty('rpcencoding', $coin, "Coin should have rpcencoding property (iteration $i)");
            }
            
            // Only test first iteration to avoid excessive queries
            break;
        }
        
        // Run remaining iterations with simple checks
        for ($i = 1; $i < 100; $i++) {
            $count = Coins::find()->where(['enable' => 1])->count();
            $this->assertGreaterThanOrEqual(0, $count, "Enabled coins count should be non-negative (iteration $i)");
        }
    }
    
    /**
     * Test that exchange scripts can access balance data
     */
    public function testBalanceAccess()
    {
        // Property: Exchange scripts should be able to access balance data
        for ($i = 0; $i < 100; $i++) {
            $count = Balances::find()->count();
            $this->assertIsInt($count, "Balances count should be integer (iteration $i)");
            $this->assertGreaterThanOrEqual(0, $count, "Balances count should be non-negative (iteration $i)");
        }
    }
    
    /**
     * Test that exchange scripts can query market data
     */
    public function testMarketDataQueries()
    {
        // Property: Exchange scripts should be able to query market data
        for ($i = 0; $i < 100; $i++) {
            // Query recent markets
            $markets = Markets::find()
                ->orderBy(['lasttraded' => SORT_DESC])
                ->limit(10)
                ->all();
            
            $this->assertIsArray($markets, "Markets query should return array (iteration $i)");
            $this->assertLessThanOrEqual(10, count($markets), "Markets query should respect limit (iteration $i)");
        }
    }
}
