<?php

namespace tests\integration;

use Codeception\Test\Unit;
use app\models\Coins;
use app\models\Stratums;
use app\models\Markets;

/**
 * Integration test for coin creation workflow
 * 
 * Tests end-to-end workflow: Admin creates coin → Coin appears in wallet list → Stratum can connect
 */
class CoinCreationWorkflowTest extends Unit
{
    /**
     * Test complete coin creation workflow
     * 
     * @test
     */
    public function testCoinCreationWorkflow()
    {
        // Step 1: Admin creates a new coin
        $coin = new Coins();
        $coin->name = 'TestCoin Integration';
        $coin->symbol = 'TSTI';
        $coin->symbol2 = 'TST';
        $coin->algo = 'sha256';
        $coin->rpchost = '127.0.0.1';
        $coin->rpcport = 8332;
        $coin->rpcuser = 'testuser';
        $coin->rpcpasswd = 'testpass';
        $coin->rpcencoding = 'hex';
        $coin->master_wallet = '1TestWalletAddress123456789';
        $coin->enable = 1;
        $coin->visible = 1;
        $coin->auto_ready = 1;
        
        $this->assertTrue($coin->save(), 'Failed to create coin');
        $coinId = $coin->id;
        
        // Step 2: Verify coin appears in database
        $savedCoin = Coins::findOne($coinId);
        $this->assertNotNull($savedCoin, 'Coin not found in database');
        $this->assertEquals('TestCoin Integration', $savedCoin->name);
        $this->assertEquals('TSTI', $savedCoin->symbol);
        $this->assertEquals(1, $savedCoin->enable);
        
        // Step 3: Verify coin appears in enabled coins list
        $enabledCoins = Coins::find()->where(['enable' => 1])->all();
        $coinFound = false;
        foreach ($enabledCoins as $c) {
            if ($c->id == $coinId) {
                $coinFound = true;
                break;
            }
        }
        $this->assertTrue($coinFound, 'Coin not found in enabled coins list');
        
        // Step 4: Verify coin appears in visible coins list (for wallet display)
        $visibleCoins = Coins::find()->where(['visible' => 1])->all();
        $coinVisible = false;
        foreach ($visibleCoins as $c) {
            if ($c->id == $coinId) {
                $coinVisible = true;
                break;
            }
        }
        $this->assertTrue($coinVisible, 'Coin not visible in wallet list');
        
        // Step 5: Verify stratum configuration can be created for this coin
        $stratum = new Stratums();
        $stratum->algo = $coin->algo;
        $stratum->host = 'stratum.example.com';
        $stratum->port = 3333;
        $stratum->password = 'x';
        
        $this->assertTrue($stratum->save(), 'Failed to create stratum configuration');
        
        // Step 6: Verify stratum can find coins by algorithm
        $coinsForAlgo = Coins::find()
            ->where(['algo' => 'sha256', 'enable' => 1])
            ->all();
        
        $algoMatch = false;
        foreach ($coinsForAlgo as $c) {
            if ($c->id == $coinId) {
                $algoMatch = true;
                break;
            }
        }
        $this->assertTrue($algoMatch, 'Coin not found in algorithm-specific query');
        
        // Step 7: Verify market data can be associated with coin
        $market = new Markets();
        $market->coinid = $coinId;
        $market->name = 'TestExchange';
        $market->marketid = 'TSTI-BTC';
        $market->price = 0.00001;
        $market->lastupdate = time();
        
        $this->assertTrue($market->save(), 'Failed to create market data');
        
        // Step 8: Verify coin can be retrieved with market relation
        $coinWithMarket = Coins::findOne($coinId);
        $markets = $coinWithMarket->getMarkets()->all();
        $this->assertGreaterThan(0, count($markets), 'No markets found for coin');
        
        // Clean up
        $market->delete();
        $stratum->delete();
        $savedCoin->delete();
    }
    
    /**
     * Test coin disable workflow
     * 
     * @test
     */
    public function testCoinDisableWorkflow()
    {
        // Create enabled coin
        $coin = new Coins();
        $coin->name = 'DisableTest';
        $coin->symbol = 'DIS';
        $coin->algo = 'scrypt';
        $coin->rpchost = '127.0.0.1';
        $coin->rpcport = 9332;
        $coin->rpcuser = 'user';
        $coin->rpcpasswd = 'pass';
        $coin->enable = 1;
        $coin->visible = 1;
        $coin->save();
        
        $coinId = $coin->id;
        
        // Verify coin is in enabled list
        $enabledCount = Coins::find()->where(['enable' => 1, 'id' => $coinId])->count();
        $this->assertEquals(1, $enabledCount);
        
        // Disable coin
        $coin->enable = 0;
        $coin->save();
        
        // Verify coin is no longer in enabled list
        $enabledCount = Coins::find()->where(['enable' => 1, 'id' => $coinId])->count();
        $this->assertEquals(0, $enabledCount);
        
        // Verify coin is in disabled list
        $disabledCount = Coins::find()->where(['enable' => 0, 'id' => $coinId])->count();
        $this->assertEquals(1, $disabledCount);
        
        // Clean up
        $coin->delete();
    }
}
