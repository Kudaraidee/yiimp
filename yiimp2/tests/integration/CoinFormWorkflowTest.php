<?php

namespace tests\integration;

use Codeception\Test\Unit;
use app\models\Coins;
use app\models\Algos;

/**
 * Integration test for complete coin form workflow
 * 
 * Tests end-to-end coin form operations including:
 * - Creating a new coin with data in all tabs
 * - Updating an existing coin
 * - Validation error handling
 * - Tab navigation and state preservation
 * 
 * Feature: yiimp2-coin-form-completion
 * Requirements: 6.2, 6.3, 6.4
 */
class CoinFormWorkflowTest extends Unit
{
    /**
     * Test creating a new coin with data in all tabs
     * Tests Requirement 6.4
     * 
     * @test
     */
    public function testCreateNewCoinWithAllTabsData()
    {
        // Step 1: Prepare form data from all tabs
        $formData = [
            // General tab
            'name' => 'IntegrationTestCoin',
            'symbol' => 'ITC',
            'symbol2' => 'ITC2',
            'algo' => 'sha256',
            'image' => 'https://example.com/itc.png',
            'version_installed' => '1.0.0',
            'payout_min' => 0.01,
            'payout_max' => 100.0,
            'target_height' => 500000,
            'powend_height' => 1000000,
            'mature_blocks' => 100,
            'powlimit_bits' => 24,
            'block_time' => 60,
            'decimals' => 8,
            
            // Settings tab
            'enable' => 0, // Start disabled
            'auto_ready' => 1,
            'visible' => 1,
            'installed' => 1,
            'no_explorer' => 0,
            'watch' => 1,
            'auxpow' => 0,
            'enable_rpcdebug' => 0,
            'hasgetinfo' => 1,
            'hassubmitblock' => 1,
            'txmessage' => 0,
            'hasmasternodes' => 0,
            'usesegwit' => 1,
            'usemweb' => 0,
            'personalization' => null,
            'max_miners' => 100,
            'max_shares' => 10000,
            'master_wallet' => 'ITCtestwalletaddress123456789',
            'wallet_zaddress' => null,
            'reward_mul' => 1.0,
            'charity_percent' => 2.5,
            'charity_address' => 'ITCcharityaddress987654321',
            
            // Exchange tab
            'dontsell' => 0,
            'sellonbid' => 1,
            'auto_exchange' => 1,
            'sellthreshold' => 1000.0,
            'market' => 'bittrex',
            'price' => 0.00001234,
            
            // Daemon tab
            'program' => 'itccoind',
            'conf_folder' => '/root/.itccoin',
            'rpchost' => '127.0.0.1',
            'rpcport' => 8332,
            'rpcwallet' => 'wallet1',
            'rpcuser' => 'itcrpcuser',
            'rpcpasswd' => 'securepassword123',
            'serveruser' => 'pooluser',
            'rpcencoding' => 'POW',
            'dedicatedport' => 3333,
            'rpccurl' => 0,
            'rpcssl' => 0,
            'rpccert' => null,
            'account' => '',
            
            // Links tab
            'link_bitcointalk' => 'https://bitcointalk.org/index.php?topic=123456',
            'link_github' => 'https://github.com/itccoin/itccoin',
            'link_site' => 'https://itccoin.example.com',
            'link_exchange' => 'https://bittrex.com/Market/Index?MarketName=BTC-ITC',
            'link_explorer' => 'https://explorer.itccoin.com',
            'link_twitter' => 'https://twitter.com/itccoin',
            'link_discord' => 'https://discord.gg/itccoin',
            'link_facebook' => 'https://facebook.com/itccoin',
        ];
        
        // Step 2: Create coin model and load form data
        $coin = new Coins();
        $coin->setAttributes($formData);
        
        // Step 3: Validate the coin
        $isValid = $coin->validate();
        
        if (!$isValid) {
            $errors = $coin->getErrors();
            $this->fail('Coin validation failed: ' . print_r($errors, true));
        }
        
        $this->assertTrue($isValid, 'Coin should pass validation with all tabs data');
        
        // Step 4: Save the coin
        $saved = $coin->save();
        
        $this->assertTrue($saved, 'Coin should be saved successfully');
        $this->assertNotNull($coin->id, 'Coin should have an ID after save');
        
        $coinId = $coin->id;
        
        // Step 5: Verify all data was saved correctly
        $savedCoin = Coins::findOne($coinId);
        
        $this->assertNotNull($savedCoin, 'Saved coin should be retrievable');
        
        // Verify General tab data
        $this->assertEquals('IntegrationTestCoin', $savedCoin->name);
        $this->assertEquals('ITC', $savedCoin->symbol);
        $this->assertEquals('ITC2', $savedCoin->symbol2);
        $this->assertEquals('sha256', $savedCoin->algo);
        $this->assertEquals(8, $savedCoin->decimals);
        
        // Verify Settings tab data
        $this->assertEquals(0, $savedCoin->enable);
        $this->assertEquals(1, $savedCoin->visible);
        $this->assertEquals(1, $savedCoin->usesegwit);
        $this->assertEquals(2.5, $savedCoin->charity_percent);
        
        // Verify Exchange tab data
        $this->assertEquals(1, $savedCoin->auto_exchange);
        $this->assertEquals(1000.0, $savedCoin->sellthreshold);
        $this->assertEquals('bittrex', $savedCoin->market);
        
        // Verify Daemon tab data
        $this->assertEquals('127.0.0.1', $savedCoin->rpchost);
        $this->assertEquals(8332, $savedCoin->rpcport);
        $this->assertEquals('wallet1', $savedCoin->rpcwallet);
        $this->assertEquals('itcrpcuser', $savedCoin->rpcuser);
        
        // Verify Links tab data
        $this->assertEquals('https://github.com/itccoin/itccoin', $savedCoin->link_github);
        $this->assertEquals('https://twitter.com/itccoin', $savedCoin->link_twitter);
        
        // Clean up
        $coin->delete();
    }
    
    /**
     * Test updating an existing coin
     * Tests Requirement 6.4
     * 
     * @test
     */
    public function testUpdateExistingCoin()
    {
        // Step 1: Create initial coin
        $coin = new Coins();
        $coin->name = 'UpdateTestCoin';
        $coin->symbol = 'UTC';
        $coin->algo = 'scrypt';
        $coin->rpchost = '127.0.0.1';
        $coin->rpcport = 9333;
        $coin->rpcuser = 'user';
        $coin->rpcpasswd = 'pass';
        $coin->enable = 0;
        $coin->visible = 0;
        $coin->charity_percent = 1.0;
        $coin->sellthreshold = 500.0;
        $coin->save(false);
        
        $coinId = $coin->id;
        
        // Step 2: Load coin for editing (simulate form load)
        $editCoin = Coins::findOne($coinId);
        
        $this->assertNotNull($editCoin, 'Coin should be loadable for editing');
        $this->assertEquals('UpdateTestCoin', $editCoin->name);
        $this->assertEquals(0, $editCoin->enable);
        
        // Step 3: Update fields from different tabs
        $editCoin->enable = 1; // Settings tab
        $editCoin->visible = 1; // Settings tab
        $editCoin->charity_percent = 2.5; // Settings tab
        $editCoin->sellthreshold = 1000.0; // Exchange tab
        $editCoin->rpcport = 9334; // Daemon tab
        $editCoin->link_github = 'https://github.com/updatetest/coin'; // Links tab
        
        // Step 4: Validate and save updates
        $isValid = $editCoin->validate();
        $this->assertTrue($isValid, 'Updated coin should pass validation');
        
        $saved = $editCoin->save();
        $this->assertTrue($saved, 'Updated coin should save successfully');
        
        // Step 5: Reload and verify updates
        $updatedCoin = Coins::findOne($coinId);
        
        $this->assertEquals(1, $updatedCoin->enable, 'Enable should be updated');
        $this->assertEquals(1, $updatedCoin->visible, 'Visible should be updated');
        $this->assertEquals(2.5, $updatedCoin->charity_percent, 'Charity percent should be updated');
        $this->assertEquals(1000.0, $updatedCoin->sellthreshold, 'Sell threshold should be updated');
        $this->assertEquals(9334, $updatedCoin->rpcport, 'RPC port should be updated');
        $this->assertEquals('https://github.com/updatetest/coin', $updatedCoin->link_github, 
            'GitHub link should be updated');
        
        // Verify unchanged fields remain the same
        $this->assertEquals('UpdateTestCoin', $updatedCoin->name);
        $this->assertEquals('UTC', $updatedCoin->symbol);
        $this->assertEquals('scrypt', $updatedCoin->algo);
        
        // Clean up
        $updatedCoin->delete();
    }
    
    /**
     * Test validation error handling
     * Tests Requirements 6.2, 6.3, 6.4
     * 
     * @test
     */
    public function testValidationErrorHandling()
    {
        // Test 1: Missing required fields
        $coin = new Coins();
        $coin->algo = 'sha256';
        // Missing name and symbol
        
        $isValid = $coin->validate();
        $this->assertFalse($isValid, 'Coin without required fields should fail validation');
        
        $errors = $coin->getErrors();
        $this->assertArrayHasKey('name', $errors, 'Should have error for missing name');
        $this->assertArrayHasKey('symbol', $errors, 'Should have error for missing symbol');
        
        // Test 2: Invalid charity_percent (out of range)
        $coin2 = new Coins();
        $coin2->name = 'ValidationTest';
        $coin2->symbol = 'VLD';
        $coin2->algo = 'sha256';
        $coin2->charity_percent = 150; // Invalid: > 100
        
        $isValid2 = $coin2->validate(['charity_percent']);
        $this->assertFalse($isValid2, 'Charity percent > 100 should fail validation');
        $this->assertArrayHasKey('charity_percent', $coin2->getErrors());
        
        // Test 3: Invalid RPC port (out of range)
        $coin3 = new Coins();
        $coin3->name = 'ValidationTest2';
        $coin3->symbol = 'VLD2';
        $coin3->algo = 'sha256';
        $coin3->rpcport = 70000; // Invalid: > 65535
        
        $isValid3 = $coin3->validate(['rpcport']);
        $this->assertFalse($isValid3, 'RPC port > 65535 should fail validation');
        $this->assertArrayHasKey('rpcport', $coin3->getErrors());
        
        // Test 4: Invalid URL format
        $coin4 = new Coins();
        $coin4->name = 'ValidationTest3';
        $coin4->symbol = 'VLD3';
        $coin4->algo = 'sha256';
        $coin4->link_github = 'not a valid url';
        
        $isValid4 = $coin4->validate(['link_github']);
        $this->assertFalse($isValid4, 'Invalid URL should fail validation');
        $this->assertArrayHasKey('link_github', $coin4->getErrors());
        
        // Test 5: Negative sellthreshold
        $coin5 = new Coins();
        $coin5->name = 'ValidationTest4';
        $coin5->symbol = 'VLD4';
        $coin5->algo = 'sha256';
        $coin5->sellthreshold = -100; // Invalid: negative
        
        $isValid5 = $coin5->validate(['sellthreshold']);
        $this->assertFalse($isValid5, 'Negative sellthreshold should fail validation');
        $this->assertArrayHasKey('sellthreshold', $coin5->getErrors());
    }
    
    /**
     * Test tab navigation and state preservation
     * Tests Requirements 6.2, 6.3
     * 
     * @test
     */
    public function testTabNavigationAndStatePreservation()
    {
        // This test simulates the workflow of filling out the form across multiple tabs
        // In a real browser, data would be preserved in the form as the user switches tabs
        
        // Step 1: Start with General tab data
        $formState = [
            'name' => 'TabTestCoin',
            'symbol' => 'TTC',
            'algo' => 'x11',
            'decimals' => 8,
        ];
        
        // Step 2: Switch to Settings tab, add more data
        $formState['enable'] = 1;
        $formState['visible'] = 1;
        $formState['charity_percent'] = 3.0;
        
        // Step 3: Switch to Exchange tab, add more data
        $formState['auto_exchange'] = 1;
        $formState['sellthreshold'] = 2000.0;
        
        // Step 4: Switch to Daemon tab, add more data
        $formState['rpchost'] = '127.0.0.1';
        $formState['rpcport'] = 8888;
        $formState['rpcuser'] = 'tabtest';
        $formState['rpcpasswd'] = 'tabpass';
        
        // Step 5: Switch to Links tab, add more data
        $formState['link_github'] = 'https://github.com/tabtest/coin';
        
        // Step 6: Submit form with all accumulated data
        $coin = new Coins();
        $coin->setAttributes($formState);
        
        // Verify all data from all tabs is present
        $this->assertEquals('TabTestCoin', $coin->name, 'General tab data should be preserved');
        $this->assertEquals(1, $coin->enable, 'Settings tab data should be preserved');
        $this->assertEquals(1, $coin->auto_exchange, 'Exchange tab data should be preserved');
        $this->assertEquals(8888, $coin->rpcport, 'Daemon tab data should be preserved');
        $this->assertEquals('https://github.com/tabtest/coin', $coin->link_github, 
            'Links tab data should be preserved');
        
        // Step 7: Validate and save
        $isValid = $coin->validate();
        $this->assertTrue($isValid, 'Coin with data from all tabs should be valid');
        
        $saved = $coin->save();
        $this->assertTrue($saved, 'Coin should save with data from all tabs');
        
        // Step 8: Verify all tab data was saved
        $savedCoin = Coins::findOne($coin->id);
        
        $this->assertEquals('TabTestCoin', $savedCoin->name);
        $this->assertEquals(1, $savedCoin->enable);
        $this->assertEquals(1, $savedCoin->auto_exchange);
        $this->assertEquals(8888, $savedCoin->rpcport);
        $this->assertEquals('https://github.com/tabtest/coin', $savedCoin->link_github);
        
        // Clean up
        $savedCoin->delete();
    }
    
    /**
     * Test form workflow with validation errors across tabs
     * Tests Requirements 6.2, 6.3, 6.4
     * 
     * @test
     */
    public function testFormWorkflowWithValidationErrorsAcrossTabs()
    {
        // Simulate user filling form with some invalid data in different tabs
        $formData = [
            // General tab - valid
            'name' => 'ErrorTestCoin',
            'symbol' => 'ETC',
            'algo' => 'sha256',
            
            // Settings tab - invalid charity_percent
            'charity_percent' => 150, // Invalid: > 100
            
            // Exchange tab - invalid sellthreshold
            'sellthreshold' => -500, // Invalid: negative
            
            // Daemon tab - invalid rpcport
            'rpcport' => 100000, // Invalid: > 65535
            
            // Links tab - invalid URL
            'link_github' => 'not-a-url',
        ];
        
        $coin = new Coins();
        $coin->setAttributes($formData);
        
        // Validate
        $isValid = $coin->validate();
        $this->assertFalse($isValid, 'Form with multiple errors should fail validation');
        
        $errors = $coin->getErrors();
        
        // Verify errors from different tabs are all captured
        $this->assertArrayHasKey('charity_percent', $errors, 
            'Should have error from Settings tab');
        $this->assertArrayHasKey('sellthreshold', $errors, 
            'Should have error from Exchange tab');
        $this->assertArrayHasKey('rpcport', $errors, 
            'Should have error from Daemon tab');
        $this->assertArrayHasKey('link_github', $errors, 
            'Should have error from Links tab');
        
        // Fix errors one by one (simulating user correcting errors)
        $coin->charity_percent = 2.5; // Fix Settings tab
        $coin->sellthreshold = 1000.0; // Fix Exchange tab
        $coin->rpcport = 8332; // Fix Daemon tab
        $coin->link_github = 'https://github.com/errortest/coin'; // Fix Links tab
        
        // Validate again
        $isValid2 = $coin->validate();
        $this->assertTrue($isValid2, 'Form should be valid after fixing all errors');
        
        // Save should now succeed
        $saved = $coin->save();
        $this->assertTrue($saved, 'Coin should save after fixing validation errors');
        
        // Clean up
        $coin->delete();
    }
    
    /**
     * Check if database is available
     * 
     * @return bool
     */
    protected function isDatabaseAvailable()
    {
        try {
            \Yii::$app->db->open();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
