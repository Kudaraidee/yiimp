<?php

namespace yiimp2\tests\unit\database;

use yii\db\Connection;
use Codeception\Test\Unit;

/**
 * Foreign Key Validation Test
 * 
 * This test validates all foreign key constraints in the yiimp2 database:
 * 1. Verifies all foreign keys exist
 * 2. Validates cascade delete/restrict rules are correct
 * 3. Tests foreign key constraints with sample data
 * 
 * Requirements: 2.4, 6.5
 */
class ForeignKeyValidationTest extends Unit
{
    /**
     * @var Connection
     */
    protected $db;

    protected function _before()
    {
        $this->db = \Yii::$app->db;
    }

    /**
     * Test that all expected foreign keys exist in the database
     */
    public function testAllForeignKeysExist()
    {
        // Expected foreign keys based on yiimp2-init.sql
        $expectedForeignKeys = [
            'benchmarks' => [
                'fk_bench_chip' => [
                    'column' => 'idchip',
                    'ref_table' => 'bench_chips',
                    'ref_column' => 'id',
                    'on_delete' => 'RESTRICT',
                    'on_update' => 'NO ACTION'
                ]
            ],
            'bookmarks' => [
                'fk_bookmarks_coin' => [
                    'column' => 'idcoin',
                    'ref_table' => 'coins',
                    'ref_column' => 'id',
                    'on_delete' => 'CASCADE',
                    'on_update' => 'NO ACTION'
                ]
            ],
            'market_history' => [
                'fk_mh_coin' => [
                    'column' => 'idcoin',
                    'ref_table' => 'coins',
                    'ref_column' => 'id',
                    'on_delete' => 'CASCADE',
                    'on_update' => 'NO ACTION'
                ],
                'fk_mh_market' => [
                    'column' => 'idmarket',
                    'ref_table' => 'markets',
                    'ref_column' => 'id',
                    'on_delete' => 'CASCADE',
                    'on_update' => 'NO ACTION'
                ]
            ],
            'notifications' => [
                'fk_notif_coin' => [
                    'column' => 'idcoin',
                    'ref_table' => 'coins',
                    'ref_column' => 'id',
                    'on_delete' => 'CASCADE',
                    'on_update' => 'NO ACTION'
                ]
            ],
            'payouts' => [
                'fk_payouts_account' => [
                    'column' => 'account_id',
                    'ref_table' => 'accounts',
                    'ref_column' => 'id',
                    'on_delete' => 'CASCADE',
                    'on_update' => 'NO ACTION'
                ],
                'fk_payouts_coin' => [
                    'column' => 'idcoin',
                    'ref_table' => 'coins',
                    'ref_column' => 'id',
                    'on_delete' => 'CASCADE',
                    'on_update' => 'NO ACTION'
                ]
            ]
        ];

        foreach ($expectedForeignKeys as $tableName => $foreignKeys) {
            $actualForeignKeys = $this->getForeignKeys($tableName);
            
            foreach ($foreignKeys as $fkName => $expectedFk) {
                $this->assertArrayHasKey(
                    $fkName,
                    $actualForeignKeys,
                    "Foreign key '$fkName' should exist in table '$tableName'"
                );
                
                $actualFk = $actualForeignKeys[$fkName];
                
                $this->assertEquals(
                    $expectedFk['column'],
                    $actualFk['column'],
                    "Foreign key '$fkName' should reference column '{$expectedFk['column']}'"
                );
                
                $this->assertEquals(
                    $expectedFk['ref_table'],
                    $actualFk['ref_table'],
                    "Foreign key '$fkName' should reference table '{$expectedFk['ref_table']}'"
                );
                
                $this->assertEquals(
                    $expectedFk['ref_column'],
                    $actualFk['ref_column'],
                    "Foreign key '$fkName' should reference column '{$expectedFk['ref_column']}'"
                );
                
                $this->assertEquals(
                    $expectedFk['on_delete'],
                    $actualFk['on_delete'],
                    "Foreign key '$fkName' should have ON DELETE {$expectedFk['on_delete']}"
                );
            }
        }
    }

    /**
     * Test CASCADE DELETE behavior for bookmarks when coin is deleted
     */
    public function testBookmarksCascadeDeleteOnCoinDelete()
    {
        // Create a test coin
        $this->db->createCommand()->insert('coins', [
            'name' => 'TestCoin_FK',
            'symbol' => 'TESTFK',
            'algo' => 'sha256',
            'enable' => 0
        ])->execute();
        
        $coinId = $this->db->getLastInsertID();
        
        // Create a bookmark for this coin
        $this->db->createCommand()->insert('bookmarks', [
            'idcoin' => $coinId,
            'label' => 'Test Bookmark',
            'address' => 'test_address_123'
        ])->execute();
        
        $bookmarkId = $this->db->getLastInsertID();
        
        // Verify bookmark exists
        $bookmark = $this->db->createCommand(
            'SELECT * FROM bookmarks WHERE id = :id'
        )->bindValue(':id', $bookmarkId)->queryOne();
        
        $this->assertNotFalse($bookmark, 'Bookmark should exist before coin deletion');
        
        // Delete the coin
        $this->db->createCommand()->delete('coins', ['id' => $coinId])->execute();
        
        // Verify bookmark was cascade deleted
        $bookmark = $this->db->createCommand(
            'SELECT * FROM bookmarks WHERE id = :id'
        )->bindValue(':id', $bookmarkId)->queryOne();
        
        $this->assertFalse($bookmark, 'Bookmark should be cascade deleted when coin is deleted');
    }

    /**
     * Test CASCADE DELETE behavior for payouts when account is deleted
     */
    public function testPayoutsCascadeDeleteOnAccountDelete()
    {
        // Create a test account
        $this->db->createCommand()->insert('accounts', [
            'username' => 'test_user_fk_' . time(),
            'balance' => 0
        ])->execute();
        
        $accountId = $this->db->getLastInsertID();
        
        // Create a test coin
        $this->db->createCommand()->insert('coins', [
            'name' => 'TestCoin_Payout',
            'symbol' => 'TESTPO',
            'algo' => 'sha256',
            'enable' => 0
        ])->execute();
        
        $coinId = $this->db->getLastInsertID();
        
        // Create a payout for this account
        $this->db->createCommand()->insert('payouts', [
            'account_id' => $accountId,
            'idcoin' => $coinId,
            'time' => time(),
            'completed' => 0,
            'amount' => 1.5
        ])->execute();
        
        $payoutId = $this->db->getLastInsertID();
        
        // Verify payout exists
        $payout = $this->db->createCommand(
            'SELECT * FROM payouts WHERE id = :id'
        )->bindValue(':id', $payoutId)->queryOne();
        
        $this->assertNotFalse($payout, 'Payout should exist before account deletion');
        
        // Delete the account
        $this->db->createCommand()->delete('accounts', ['id' => $accountId])->execute();
        
        // Verify payout was cascade deleted
        $payout = $this->db->createCommand(
            'SELECT * FROM payouts WHERE id = :id'
        )->bindValue(':id', $payoutId)->queryOne();
        
        $this->assertFalse($payout, 'Payout should be cascade deleted when account is deleted');
        
        // Cleanup coin
        $this->db->createCommand()->delete('coins', ['id' => $coinId])->execute();
    }

    /**
     * Test CASCADE DELETE behavior for payouts when coin is deleted
     */
    public function testPayoutsCascadeDeleteOnCoinDelete()
    {
        // Create a test account
        $this->db->createCommand()->insert('accounts', [
            'username' => 'test_user_fk2_' . time(),
            'balance' => 0
        ])->execute();
        
        $accountId = $this->db->getLastInsertID();
        
        // Create a test coin
        $this->db->createCommand()->insert('coins', [
            'name' => 'TestCoin_Payout2',
            'symbol' => 'TESTPO2',
            'algo' => 'sha256',
            'enable' => 0
        ])->execute();
        
        $coinId = $this->db->getLastInsertID();
        
        // Create a payout for this coin
        $this->db->createCommand()->insert('payouts', [
            'account_id' => $accountId,
            'idcoin' => $coinId,
            'time' => time(),
            'completed' => 0,
            'amount' => 2.5
        ])->execute();
        
        $payoutId = $this->db->getLastInsertID();
        
        // Verify payout exists
        $payout = $this->db->createCommand(
            'SELECT * FROM payouts WHERE id = :id'
        )->bindValue(':id', $payoutId)->queryOne();
        
        $this->assertNotFalse($payout, 'Payout should exist before coin deletion');
        
        // Delete the coin
        $this->db->createCommand()->delete('coins', ['id' => $coinId])->execute();
        
        // Verify payout was cascade deleted
        $payout = $this->db->createCommand(
            'SELECT * FROM payouts WHERE id = :id'
        )->bindValue(':id', $payoutId)->queryOne();
        
        $this->assertFalse($payout, 'Payout should be cascade deleted when coin is deleted');
        
        // Cleanup account
        $this->db->createCommand()->delete('accounts', ['id' => $accountId])->execute();
    }

    /**
     * Test RESTRICT behavior for benchmarks when chip is deleted
     */
    public function testBenchmarksRestrictDeleteOnChipDelete()
    {
        // Create a test chip
        $this->db->createCommand()->insert('bench_chips', [
            'devicetype' => 'GPU',
            'chip' => 'TestChip_FK'
        ])->execute();
        
        $chipId = $this->db->getLastInsertID();
        
        // Create a benchmark for this chip
        $this->db->createCommand()->insert('benchmarks', [
            'algo' => 'sha256',
            'type' => 'GPU',
            'idchip' => $chipId,
            'time' => time()
        ])->execute();
        
        $benchmarkId = $this->db->getLastInsertID();
        
        // Try to delete the chip - should fail due to RESTRICT
        $deleteFailed = false;
        try {
            $this->db->createCommand()->delete('bench_chips', ['id' => $chipId])->execute();
        } catch (\Exception $e) {
            $deleteFailed = true;
            $this->assertStringContainsString(
                'foreign key constraint',
                strtolower($e->getMessage()),
                'Delete should fail with foreign key constraint error'
            );
        }
        
        $this->assertTrue($deleteFailed, 'Deleting chip with benchmarks should fail due to RESTRICT');
        
        // Cleanup: delete benchmark first, then chip
        $this->db->createCommand()->delete('benchmarks', ['id' => $benchmarkId])->execute();
        $this->db->createCommand()->delete('bench_chips', ['id' => $chipId])->execute();
    }

    /**
     * Test CASCADE DELETE behavior for market_history when coin is deleted
     */
    public function testMarketHistoryCascadeDeleteOnCoinDelete()
    {
        // Create a test coin
        $this->db->createCommand()->insert('coins', [
            'name' => 'TestCoin_MH',
            'symbol' => 'TESTMH',
            'algo' => 'sha256',
            'enable' => 0
        ])->execute();
        
        $coinId = $this->db->getLastInsertID();
        
        // Create market history for this coin
        $this->db->createCommand()->insert('market_history', [
            'time' => time(),
            'idcoin' => $coinId,
            'price' => 0.001
        ])->execute();
        
        $historyId = $this->db->getLastInsertID();
        
        // Verify market history exists
        $history = $this->db->createCommand(
            'SELECT * FROM market_history WHERE id = :id'
        )->bindValue(':id', $historyId)->queryOne();
        
        $this->assertNotFalse($history, 'Market history should exist before coin deletion');
        
        // Delete the coin
        $this->db->createCommand()->delete('coins', ['id' => $coinId])->execute();
        
        // Verify market history was cascade deleted
        $history = $this->db->createCommand(
            'SELECT * FROM market_history WHERE id = :id'
        )->bindValue(':id', $historyId)->queryOne();
        
        $this->assertFalse($history, 'Market history should be cascade deleted when coin is deleted');
    }

    /**
     * Test CASCADE DELETE behavior for notifications when coin is deleted
     */
    public function testNotificationsCascadeDeleteOnCoinDelete()
    {
        // Create a test coin
        $this->db->createCommand()->insert('coins', [
            'name' => 'TestCoin_Notif',
            'symbol' => 'TESTNT',
            'algo' => 'sha256',
            'enable' => 0
        ])->execute();
        
        $coinId = $this->db->getLastInsertID();
        
        // Create a notification for this coin
        $this->db->createCommand()->insert('notifications', [
            'idcoin' => $coinId,
            'enabled' => 0,
            'description' => 'Test notification'
        ])->execute();
        
        $notifId = $this->db->getLastInsertID();
        
        // Verify notification exists
        $notif = $this->db->createCommand(
            'SELECT * FROM notifications WHERE id = :id'
        )->bindValue(':id', $notifId)->queryOne();
        
        $this->assertNotFalse($notif, 'Notification should exist before coin deletion');
        
        // Delete the coin
        $this->db->createCommand()->delete('coins', ['id' => $coinId])->execute();
        
        // Verify notification was cascade deleted
        $notif = $this->db->createCommand(
            'SELECT * FROM notifications WHERE id = :id'
        )->bindValue(':id', $notifId)->queryOne();
        
        $this->assertFalse($notif, 'Notification should be cascade deleted when coin is deleted');
    }

    /**
     * Helper method to get foreign keys for a table
     * 
     * @param string $tableName
     * @return array
     */
    protected function getForeignKeys($tableName)
    {
        $sql = "
            SELECT 
                CONSTRAINT_NAME as fk_name,
                COLUMN_NAME as column_name,
                REFERENCED_TABLE_NAME as ref_table,
                REFERENCED_COLUMN_NAME as ref_column,
                DELETE_RULE as on_delete,
                UPDATE_RULE as on_update
            FROM 
                information_schema.KEY_COLUMN_USAGE
            WHERE 
                TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = :table
                AND REFERENCED_TABLE_NAME IS NOT NULL
        ";
        
        $rows = $this->db->createCommand($sql)
            ->bindValue(':table', $tableName)
            ->queryAll();
        
        $foreignKeys = [];
        foreach ($rows as $row) {
            $foreignKeys[$row['fk_name']] = [
                'column' => $row['column_name'],
                'ref_table' => $row['ref_table'],
                'ref_column' => $row['ref_column'],
                'on_delete' => $row['on_delete'],
                'on_update' => $row['on_update']
            ];
        }
        
        return $foreignKeys;
    }
}
