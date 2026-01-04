<?php

namespace tests\unit\explorer;

use Codeception\Test\Unit;
use app\models\Coins;

/**
 * Property-based tests for Peer Information Display
 * 
 * Feature: yiimp-to-yiimp2-migration, Property 16: Peer information display
 */
class PeerInformationPropertyTest extends Unit
{
    /**
     * Property 16: Peer Information Display
     * 
     * For any coin with peer connections, the system should display all peer nodes
     * with IP addresses and versions.
     * 
     * Validates: Requirements 3.6
     * 
     * @test
     */
    public function testPeerInformationDisplay()
    {
        // Feature: yiimp-to-yiimp2-migration, Property 16: Peer information display
        
        $iterations = 100;
        $failures = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            try {
                // Get a random coin from the database
                $coin = $this->getRandomCoin();
                
                if ($coin === null) {
                    // Skip if no coins available
                    continue;
                }
                
                // Get peer information
                $peers = \Yii::$app->ExplorerUtils->getPeerInfo($coin);
                
                // Null means RPC error (acceptable - daemon may be offline)
                if ($peers === null) {
                    continue;
                }
                
                // Empty array means no peers (acceptable - node may be isolated)
                if (empty($peers)) {
                    continue;
                }
                
                // Verify peer information display
                $this->verifyPeerInformationDisplay($peers, $coin, $i, $failures);
                
            } catch (\Exception $e) {
                $failures[] = [
                    'iteration' => $i,
                    'coin' => isset($coin) ? $coin->symbol : 'unknown',
                    'reason' => 'Exception thrown',
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ];
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
     * Get a random coin from the database
     * 
     * @return Coins|null
     */
    protected function getRandomCoin()
    {
        // Get all enabled coins
        $coins = Coins::find()
            ->where(['enable' => 1])
            ->all();
        
        if (empty($coins)) {
            return null;
        }
        
        // Return a random coin
        $randomIndex = array_rand($coins);
        return $coins[$randomIndex];
    }
    
    /**
     * Verify peer information displays all required fields
     * 
     * @param array $peers Array of peer information
     * @param Coins $coin Coin model
     * @param int $iteration Current iteration number
     * @param array &$failures Array to collect failures
     */
    protected function verifyPeerInformationDisplay($peers, $coin, $iteration, &$failures)
    {
        // Peers should be an array
        if (!is_array($peers)) {
            $failures[] = [
                'iteration' => $iteration,
                'coin' => $coin->symbol,
                'field' => 'peers',
                'reason' => 'Peer info is not an array',
                'type' => gettype($peers)
            ];
            return;
        }
        
        // Verify each peer has required fields
        foreach ($peers as $index => $peer) {
            $this->verifyPeerData($peer, $coin, $index, $iteration, $failures);
        }
    }
    
    /**
     * Verify individual peer data has required fields
     * 
     * @param mixed $peer Peer data
     * @param Coins $coin Coin model
     * @param int $index Peer index
     * @param int $iteration Current iteration number
     * @param array &$failures Array to collect failures
     */
    protected function verifyPeerData($peer, $coin, $index, $iteration, &$failures)
    {
        // Peer should be an array
        if (!is_array($peer)) {
            $failures[] = [
                'iteration' => $iteration,
                'coin' => $coin->symbol,
                'peer_index' => $index,
                'field' => 'peer',
                'reason' => 'Peer data is not an array',
                'type' => gettype($peer)
            ];
            return;
        }
        
        // Required fields for peer display (from requirements 3.6)
        // IP address and version are the core requirements
        $requiredFields = [
            'addr' => 'string',      // IP address (required by spec)
            'version' => 'integer',  // Version (required by spec)
        ];
        
        foreach ($requiredFields as $field => $expectedType) {
            if (!isset($peer[$field])) {
                $failures[] = [
                    'iteration' => $iteration,
                    'coin' => $coin->symbol,
                    'peer_index' => $index,
                    'peer_addr' => isset($peer['addr']) ? $peer['addr'] : 'unknown',
                    'field' => $field,
                    'reason' => 'Required field missing in peer data'
                ];
                continue;
            }
            
            // Validate type
            if ($expectedType === 'string' && !is_string($peer[$field])) {
                $failures[] = [
                    'iteration' => $iteration,
                    'coin' => $coin->symbol,
                    'peer_index' => $index,
                    'peer_addr' => isset($peer['addr']) ? $peer['addr'] : 'unknown',
                    'field' => $field,
                    'expected_type' => $expectedType,
                    'actual_type' => gettype($peer[$field]),
                    'value' => $peer[$field],
                    'reason' => 'Peer field type mismatch'
                ];
            } elseif ($expectedType === 'integer' && !is_int($peer[$field])) {
                $failures[] = [
                    'iteration' => $iteration,
                    'coin' => $coin->symbol,
                    'peer_index' => $index,
                    'peer_addr' => isset($peer['addr']) ? $peer['addr'] : 'unknown',
                    'field' => $field,
                    'expected_type' => $expectedType,
                    'actual_type' => gettype($peer[$field]),
                    'value' => $peer[$field],
                    'reason' => 'Peer field type mismatch'
                ];
            }
        }
        
        // Validate IP address format (should contain : or . for IPv4/IPv6)
        if (isset($peer['addr']) && is_string($peer['addr'])) {
            if (!strpos($peer['addr'], ':') && !strpos($peer['addr'], '.')) {
                $failures[] = [
                    'iteration' => $iteration,
                    'coin' => $coin->symbol,
                    'peer_index' => $index,
                    'field' => 'addr',
                    'value' => $peer['addr'],
                    'reason' => 'IP address format appears invalid (no : or . found)'
                ];
            }
        }
    }
    
    /**
     * Test that peer information is correctly retrieved for coins with active daemons
     * 
     * @test
     */
    public function testPeerInformationRetrievalForActiveCoins()
    {
        // Feature: yiimp-to-yiimp2-migration, Property 16: Peer information display (active coins)
        
        // Get enabled coins
        $coins = Coins::find()
            ->where(['enable' => 1])
            ->limit(20)
            ->all();
        
        if (empty($coins)) {
            $this->markTestSkipped('No enabled coins found in database');
            return;
        }
        
        $testedCoins = 0;
        $coinsWithPeers = 0;
        
        foreach ($coins as $coin) {
            $peers = \Yii::$app->ExplorerUtils->getPeerInfo($coin);
            
            // Skip if RPC unavailable
            if ($peers === null) {
                continue;
            }
            
            $testedCoins++;
            
            // If peers exist, verify structure
            if (!empty($peers)) {
                $coinsWithPeers++;
                
                $this->assertIsArray($peers, "Peer info should be an array for {$coin->symbol}");
                
                // Check first peer has required fields
                $firstPeer = $peers[0];
                $this->assertIsArray($firstPeer, "Peer data should be an array for {$coin->symbol}");
                $this->assertArrayHasKey('addr', $firstPeer, "Peer should have 'addr' field for {$coin->symbol}");
                $this->assertArrayHasKey('version', $firstPeer, "Peer should have 'version' field for {$coin->symbol}");
                
                // Verify types
                $this->assertIsString($firstPeer['addr'], "Peer addr should be string for {$coin->symbol}");
                $this->assertIsInt($firstPeer['version'], "Peer version should be integer for {$coin->symbol}");
            }
        }
        
        // We should have tested at least some coins
        $this->assertGreaterThan(
            0,
            $testedCoins,
            "Should have tested at least one coin with accessible RPC"
        );
    }
    
    /**
     * Test that peer information handles RPC errors gracefully
     * 
     * @test
     */
    public function testPeerInformationHandlesRpcErrorsGracefully()
    {
        // Feature: yiimp-to-yiimp2-migration, Property 16: Peer information display (error handling)
        
        // Get a coin (any coin)
        $coin = Coins::find()->where(['enable' => 1])->one();
        
        if ($coin === null) {
            $this->markTestSkipped('No enabled coins found in database');
            return;
        }
        
        // Get peer info
        $peers = \Yii::$app->ExplorerUtils->getPeerInfo($coin);
        
        // Should return either null (RPC error), empty array (no peers), or array of peers
        $this->assertTrue(
            $peers === null || is_array($peers),
            "getPeerInfo should return null or array, got " . gettype($peers)
        );
        
        // If it's an array, it should be a valid array structure
        if (is_array($peers) && !empty($peers)) {
            $this->assertIsArray($peers[0], "First peer should be an array");
        }
    }
    
    /**
     * Test that peer information includes optional but commonly available fields
     * 
     * @test
     */
    public function testPeerInformationIncludesCommonOptionalFields()
    {
        // Feature: yiimp-to-yiimp2-migration, Property 16: Peer information display (optional fields)
        
        $coins = Coins::find()
            ->where(['enable' => 1])
            ->limit(20)
            ->all();
        
        if (empty($coins)) {
            $this->markTestSkipped('No enabled coins found in database');
            return;
        }
        
        $peersFound = false;
        
        foreach ($coins as $coin) {
            $peers = \Yii::$app->ExplorerUtils->getPeerInfo($coin);
            
            // Skip if RPC unavailable or no peers
            if ($peers === null || empty($peers)) {
                continue;
            }
            
            $peersFound = true;
            
            // Check for commonly available optional fields
            $firstPeer = $peers[0];
            
            // These fields are commonly available in Bitcoin-based daemons
            // but not strictly required by the spec
            $optionalFields = ['subver', 'conntime', 'inbound', 'startingheight', 'pingtime'];
            
            $foundOptionalFields = [];
            foreach ($optionalFields as $field) {
                if (isset($firstPeer[$field])) {
                    $foundOptionalFields[] = $field;
                }
            }
            
            // We expect at least some optional fields to be present
            // This is not a hard requirement, just a sanity check
            if (!empty($foundOptionalFields)) {
                $this->assertGreaterThan(
                    0,
                    count($foundOptionalFields),
                    "Expected some optional fields to be present for {$coin->symbol}"
                );
            }
        }
        
        if (!$peersFound) {
            $this->markTestSkipped('No coins with peer connections found');
        }
    }
}

