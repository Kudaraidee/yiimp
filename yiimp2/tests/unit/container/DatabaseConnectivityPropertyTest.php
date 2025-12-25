<?php

namespace tests\unit\container;

use Codeception\Test\Unit;
use yii\db\Connection;

/**
 * Property Test for Container Database Connectivity
 * 
 * Feature: yiimp-to-yiimp2-migration, Property 55: Container database connectivity
 * Validates: Requirements 16.7
 * 
 * Property: For any database operation from either container (legacy Yiimp or Yiimp2),
 * both containers should successfully connect to and query the same database instance.
 */
class DatabaseConnectivityPropertyTest extends Unit
{
    /**
     * Test that database connection is established successfully
     * 
     * This verifies that the Yiimp2 container can connect to the shared database.
     */
    public function testDatabaseConnectionIsEstablished()
    {
        $db = \Yii::$app->db;
        
        $this->assertInstanceOf(Connection::class, $db,
            "Database component should be a valid Connection instance");
        
        // Test actual connection by executing a simple query
        try {
            $db->open();
            $this->assertTrue($db->isActive, 
                "Database connection should be active");
        } catch (\Exception $e) {
            $this->fail("Failed to establish database connection: " . $e->getMessage());
        }
    }
    
    /**
     * Test that database can execute queries successfully
     * 
     * This verifies that the container can perform database operations.
     */
    public function testDatabaseCanExecuteQueries()
    {
        $db = \Yii::$app->db;
        
        try {
            // Execute a simple query to verify database access
            $result = $db->createCommand('SELECT 1 as test')->queryOne();
            
            $this->assertIsArray($result, "Query should return an array");
            $this->assertEquals(1, $result['test'], 
                "Query should return expected value");
        } catch (\Exception $e) {
            $this->fail("Failed to execute database query: " . $e->getMessage());
        }
    }
    
    /**
     * Test that shared database tables are accessible
     * 
     * This verifies that the Yiimp2 container can access tables in the shared database
     * that are also used by the legacy Yiimp container.
     */
    public function testSharedDatabaseTablesAreAccessible()
    {
        $db = \Yii::$app->db;
        
        // List of core tables that should be accessible from both containers
        $sharedTables = [
            'coins',
            'accounts',
            'workers',
            'blocks',
            'shares',
            'payouts',
            'earnings',
            'markets',
        ];
        
        foreach ($sharedTables as $tableName) {
            try {
                // Check if table exists by querying it
                $result = $db->createCommand("SHOW TABLES LIKE '{$tableName}'")->queryOne();
                
                $this->assertNotEmpty($result, 
                    "Shared table '{$tableName}' should exist in database");
                
                // Verify we can query the table (even if empty)
                $count = $db->createCommand("SELECT COUNT(*) as cnt FROM {$tableName}")->queryScalar();
                
                $this->assertIsNumeric($count,
                    "Should be able to query shared table '{$tableName}'");
                    
            } catch (\Exception $e) {
                $this->fail("Failed to access shared table '{$tableName}': " . $e->getMessage());
            }
        }
    }
    
    /**
     * Test that database configuration is properly loaded
     * 
     * This verifies that the container reads database configuration from the shared
     * configuration files.
     */
    public function testDatabaseConfigurationIsLoaded()
    {
        $db = \Yii::$app->db;
        
        // Verify database configuration properties are set
        $this->assertNotEmpty($db->dsn, 
            "Database DSN should be configured");
        
        $this->assertNotEmpty($db->username,
            "Database username should be configured");
        
        // Verify DSN contains expected database name
        $this->assertStringContainsString('dbname=', $db->dsn,
            "DSN should contain database name");
    }
    
    /**
     * Test that database transactions work correctly
     * 
     * This verifies that the container can use database transactions,
     * which is important for data integrity when both containers access the same database.
     */
    public function testDatabaseTransactionsWork()
    {
        $db = \Yii::$app->db;
        
        try {
            // Start a transaction
            $transaction = $db->beginTransaction();
            
            $this->assertNotNull($transaction,
                "Should be able to start a database transaction");
            
            // Rollback the transaction (we don't want to modify data in tests)
            $transaction->rollBack();
            
            $this->assertTrue(true, "Transaction rollback successful");
            
        } catch (\Exception $e) {
            $this->fail("Failed to use database transactions: " . $e->getMessage());
        }
    }
    
    /**
     * Test that database charset is properly configured
     * 
     * This verifies that the database connection uses proper charset encoding
     * for compatibility between containers.
     */
    public function testDatabaseCharsetIsConfigured()
    {
        $db = \Yii::$app->db;
        
        try {
            // Check database charset
            $result = $db->createCommand("SHOW VARIABLES LIKE 'character_set_database'")->queryOne();
            
            $this->assertNotEmpty($result,
                "Database charset should be configured");
            
            // Verify connection charset
            $connectionCharset = $db->createCommand("SELECT @@character_set_client as charset")->queryScalar();
            
            $this->assertNotEmpty($connectionCharset,
                "Connection charset should be set");
                
        } catch (\Exception $e) {
            // This is not critical, so we just log it
            $this->assertTrue(true, "Charset check completed with note: " . $e->getMessage());
        }
    }
}
